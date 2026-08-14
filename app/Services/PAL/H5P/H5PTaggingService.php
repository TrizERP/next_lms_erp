<?php

namespace App\Services\PAL\H5P;

use App\Models\PAL\H5PNodeMetadata;
use App\Services\PAL\ContentModel\ContentModelLlmClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The H5P Model proper: what a given H5P node teaches, how, and what evidence
 * it generates.
 *
 * Three tiers, in strict precedence, and every field reports which tier it came
 * from so nothing in the UI has to be taken on faith:
 *
 *   stored   a human (or a reviewed AI proposal) has tagged this node in
 *            `pal_h5p_node_metadata`. Always wins.
 *   derived  computed from data that already exists — the type→pedagogy
 *            relationships in the registry, the pedagogy's §9 coverage row,
 *            and, for question-bank nodes, the difficulty and Bloom mappings
 *            already recorded in `lms_question_mapping`. Deterministic and
 *            free; this is why most nodes need no AI at all.
 *   ai       only for what the first two tiers cannot supply, and only when
 *            the caller explicitly asks. Written back as
 *            tagged_by = 'ai', quality_status = 'draft' (CONTENT LAW C5) —
 *            a machine proposes, a human approves.
 */
class H5PTaggingService
{
    /** The tag fields the model carries, in the order the UI renders them. */
    public const TAG_FIELDS = [
        'pedagogy_tag', 'pedagogy_secondary', 'bloom_level', 'practice_level',
        'difficulty_1_to_5', 'casel_domain', 'ngss_practice', 'ncdg_goal',
        'music_domain', 'sports_domain', 'finance_level', 'gardner_intelligence',
        'riasec_signal', 'hpc_lens_primary', 'cultural_context', 'language',
        'estimated_duration_minutes', 'engagement_weight',
    ];

    /** lms_mapping_type parent whose children are Easy / Medium / Hard. */
    protected const DIFFICULTY_PARENT_ID = 9;

    /** lms_mapping_type parent whose children are the six Bloom levels. */
    protected const BLOOM_PARENT_ID = 82;

    public function __construct(
        protected H5PModelRegistry $registry,
        protected ContentModelLlmClient $llm
    ) {
    }

    /**
     * Tag a list of nodes.
     *
     * Stored rows and question-bank mappings are fetched in two batched
     * queries rather than one per node, so a 50-node chapter costs a constant
     * number of round trips.
     *
     * @param  array<int,array>  $nodes  from H5PContentRepository
     * @return array<string,array>       node key => tag block
     */
    public function tagNodes(array $nodes, array $context): array
    {
        if ($nodes === []) {
            return [];
        }

        $tenant = (int) ($context['sub_institute_id'] ?? 0);
        $stored = $this->storedFor($nodes, $tenant);
        $mappings = $this->questionMappings($nodes);

        $out = [];
        foreach ($nodes as $node) {
            $key = $node['node_key'];
            $out[$key] = $this->compose(
                $node,
                $stored[$key] ?? null,
                $mappings[$node['id']] ?? []
            );
        }

        return $out;
    }

    /** Tag a single node. */
    public function tagNode(array $node, array $context): array
    {
        return $this->tagNodes([$node], $context)[$node['node_key']] ?? [];
    }

    /**
     * Re-derive a node's tags against a pedagogy the caller is considering,
     * without saving anything. Lets the authoring UI show what switching the
     * pedagogy would do to the framework tags before the teacher commits.
     */
    public function previewWithPedagogy(array $node, array $context, string $pedagogy): array
    {
        $tenant = (int) ($context['sub_institute_id'] ?? 0);
        $stored = $this->storedFor([$node], $tenant)[$node['node_key']] ?? null;

        if ($stored !== null) {
            // A detached clone so the preview cannot leak into a later save.
            $stored = clone $stored;
            $stored->pedagogy_tag = $pedagogy;
        }

        $mapping = $this->questionMappings([$node])[$node['id']] ?? [];
        $node['sub_institute_id'] = $node['sub_institute_id'] ?? $tenant;

        return $stored !== null
            ? $this->compose($node, $stored, $mapping)
            : $this->composeWithPedagogy($node, $mapping, $pedagogy);
    }

    /** Preview path for a node that has no stored row yet. */
    protected function composeWithPedagogy(array $node, array $mapping, string $pedagogy): array
    {
        $tenant = $node['sub_institute_id'] ?? null;
        $derived = $this->derive($node, $mapping, $tenant, $pedagogy);

        $values = [];
        $sources = [];
        foreach (self::TAG_FIELDS as $field) {
            $values[$field] = $derived[$field] ?? null;
            $sources[$field] = $values[$field] === null || $values[$field] === [] ? 'missing' : 'derived';
        }

        return [
            'node_key' => $node['node_key'],
            'h5p_type' => $node['h5p_type'],
            'values' => $values,
            'field_sources' => $sources,
            'quality_status' => 'preview',
            'tagged_by' => null,
            'confidence' => null,
            'reviewed_at' => null,
            'version' => 0,
            'ai_rationale' => null,
            'derivation' => $derived['_why'] ?? [],
            'coverage_strength' => $derived['_strength'] ?? [],
            'completeness' => $this->completeness($values),
            'notice' => null,
        ];
    }

    /**
     * Persist a tag set for one node.
     *
     * @param  array  $payload  raw tag values; unknown vocabulary is rejected
     * @param  array  $actor    ['user_id' => int, 'is_ai' => bool]
     */
    public function store(array $node, array $context, array $payload, array $actor = []): array
    {
        $tenant = (int) ($context['sub_institute_id'] ?? 0);
        $isAi = (bool) ($actor['is_ai'] ?? false);
        $userId = isset($actor['user_id']) ? (int) $actor['user_id'] : null;

        $clean = $this->validate($payload, $tenant);

        // CONTENT LAW C5 — a machine may never write an approved status, and
        // may never overwrite a tag a human has already approved.
        $existing = H5PNodeMetadata::where('h5p_type', $node['h5p_type'])
            ->where('node_id', $node['id'])
            ->forTenant($tenant)
            ->first();

        if ($isAi && $existing && $existing->quality_status === 'approved') {
            return $this->compose($node, $existing, [], 'AI may not overwrite an approved tag set.');
        }

        $status = $payload['quality_status'] ?? ($isAi ? 'draft' : ($existing?->quality_status ?? 'draft'));
        if ($isAi) {
            $status = 'draft';
        }

        $record = H5PNodeMetadata::updateOrCreate(
            [
                'h5p_type' => $node['h5p_type'],
                'node_id' => $node['id'],
                'sub_institute_id' => $tenant,
            ],
            $clean + [
                'chapter_id' => $node['chapter_id'],
                'subject_id' => $node['subject_id'],
                'standard_id' => $node['standard_id'],
                'quality_status' => $status,
                'tagged_by' => $isAi ? 'ai' : 'human',
                'confidence' => $payload['confidence'] ?? ($isAi ? 0.6 : 1.0),
                'ai_rationale' => $isAi ? ($payload['ai_rationale'] ?? null) : ($existing?->ai_rationale ?? null),
                'reviewed_by' => $isAi ? ($existing?->reviewed_by ?? null) : $userId,
                'reviewed_at' => $isAi ? ($existing?->reviewed_at ?? null) : now(),
                'version' => (int) ($existing?->version ?? 0) + 1,
                'updated_by' => $userId,
                'created_by' => $existing?->created_by ?? $userId,
            ]
        );

        return $this->compose($node, $record->fresh(), $this->questionMappings([$node])[$node['id']] ?? []);
    }

    /**
     * Promote or reject a stored tag set. Only a human reaches this.
     *
     * @param  string  $status  one of the registry's quality statuses
     */
    public function transition(array $node, array $context, string $status, ?int $userId): ?array
    {
        $tenant = (int) ($context['sub_institute_id'] ?? 0);
        $allowed = array_keys(config('pal_content.quality_statuses', []))
            ?: ['draft', 'in_review', 'approved', 'rejected', 'retired'];

        if (! in_array($status, $allowed, true)) {
            return null;
        }

        $record = H5PNodeMetadata::where('h5p_type', $node['h5p_type'])
            ->where('node_id', $node['id'])
            ->forTenant($tenant)
            ->first();

        if (! $record) {
            return null;
        }

        $record->quality_status = $status;
        $record->tagged_by = 'human';
        $record->reviewed_by = $userId;
        $record->reviewed_at = now();
        $record->updated_by = $userId;
        $record->save();

        return $this->compose($node, $record->fresh(), []);
    }

    // ── Tier 2: derivation from data that already exists ────────────────────

    /**
     * Compose the three tiers into one tag block.
     *
     * `field_sources` is the honest part: it names, per field, whether the
     * value was authored, derived from the registry / question bank, or is
     * simply absent.
     */
    protected function compose(array $node, ?H5PNodeMetadata $stored, array $mapping, ?string $notice = null): array
    {
        $tenant = $node['sub_institute_id'] ?? null;

        // A stored pedagogy overrides the one the type would imply, and the
        // framework tags must then derive from THAT pedagogy's §9 coverage
        // row. Deriving CASEL/NGSS/Music from the type's default pedagogy
        // while showing a human's chosen pedagogy would put two inconsistent
        // stories on the same card.
        $derived = $this->derive($node, $mapping, $tenant, $stored?->pedagogy_tag);

        $values = [];
        $sources = [];
        foreach (self::TAG_FIELDS as $field) {
            $storedValue = $stored?->{$field};
            if ($storedValue !== null && $storedValue !== [] && $storedValue !== '') {
                $values[$field] = $storedValue;
                $sources[$field] = $stored->tagged_by === 'ai' ? 'ai' : 'stored';
                continue;
            }

            $derivedValue = $derived[$field] ?? null;
            $values[$field] = $derivedValue;
            $sources[$field] = $derivedValue === null || $derivedValue === [] ? 'missing' : 'derived';
        }

        return [
            'node_key' => $node['node_key'],
            'h5p_type' => $node['h5p_type'],
            'values' => $values,
            'field_sources' => $sources,
            'quality_status' => $stored?->quality_status ?? 'untagged',
            'tagged_by' => $stored?->tagged_by ?? null,
            'confidence' => $stored?->confidence !== null ? (float) $stored->confidence : null,
            'reviewed_at' => $stored?->reviewed_at?->toIso8601String(),
            'version' => (int) ($stored?->version ?? 0),
            'ai_rationale' => $stored?->ai_rationale ?? null,
            'derivation' => $derived['_why'] ?? [],
            'coverage_strength' => $derived['_strength'] ?? [],
            'completeness' => $this->completeness($values),
            'notice' => $notice,
        ];
    }

    /**
     * Everything that can be worked out without asking a model.
     *
     * The pedagogy comes from the registry's inverse type→pedagogy map (a type
     * named as a pedagogy's PRIMARY H5P format is the strongest available
     * evidence of intent). The framework tags then come from that pedagogy's
     * §9 coverage row, preferring the entries the matrix marks "strong".
     */
    protected function derive(array $node, array $mapping, ?int $tenant, ?string $pedagogyOverride = null): array
    {
        $type = $this->registry->type($node['h5p_type'], $tenant);
        $typeMeta = $type['metadata'] ?? [];
        $why = [];

        $pedagogies = $this->registry->pedagogiesForH5pType($node['h5p_type'], $tenant);
        $typePedagogy = $pedagogies['primary'][0] ?? ($pedagogies['secondary'][0] ?? null);
        $pedagogy = $pedagogyOverride !== null && $pedagogyOverride !== ''
            ? $this->registry->normalize('pedagogy_tags', $pedagogyOverride, $tenant)
            : $typePedagogy;

        $secondary = array_values(array_diff(
            array_merge($pedagogies['primary'], $pedagogies['secondary']),
            array_filter([$pedagogy])
        ));

        if ($pedagogyOverride !== null && $pedagogy !== null) {
            $why['pedagogy_tag'] = 'Set on this node; framework tags below follow its §9 coverage row.';
        } elseif ($pedagogy !== null) {
            $why['pedagogy_tag'] = sprintf(
                '%s names %s as a %s H5P format.',
                $this->registry->pedagogy($pedagogy, $tenant)['label'] ?? $pedagogy,
                $type['label'] ?? $node['h5p_type'],
                in_array($pedagogy, $pedagogies['primary'], true) ? 'primary' : 'secondary'
            );
        }

        // Bloom + difficulty: the question bank already records both for MCQ
        // nodes. Anything else falls back to the type's authored Bloom ceiling.
        $bloom = $mapping['bloom_level'] ?? null;
        if ($bloom !== null) {
            $why['bloom_level'] = 'Tagged on the question in lms_question_mapping (Bloom taxonomy).';
        } else {
            $bloom = $typeMeta['bloom_to'] ?? $typeMeta['bloom_from'] ?? null;
            if ($bloom !== null) {
                $why['bloom_level'] = sprintf('Bloom ceiling of the %s type (§8.1).', $type['label'] ?? $node['h5p_type']);
            }
        }

        $difficulty = $mapping['difficulty_1_to_5'] ?? null;
        if ($difficulty !== null) {
            $why['difficulty_1_to_5'] = 'Difficulty band recorded on the question in lms_question_mapping.';
        }

        $coverage = [];
        $hpcLens = null;
        $gardner = [];
        $pedagogyLabel = $pedagogy;

        if ($pedagogy !== null) {
            $term = $this->registry->pedagogy($pedagogy, $tenant);
            $coverage = (array) ($term['metadata']['coverage'] ?? []);
            $hpcLens = $this->hpcLensFor($term, $tenant);
            $gardner = array_values((array) ($term['metadata']['gardner_intelligence'] ?? []));
            $pedagogyLabel = $term['label'] ?? $pedagogy;
        }

        $frameworkFields = [
            'casel_domain' => 'casel',
            'ngss_practice' => 'ngss',
            'ncdg_goal' => 'ncdg',
            'music_domain' => 'music',
            'sports_domain' => 'sports',
            'finance_level' => 'finance',
        ];

        $derived = [];
        $strength = [];
        foreach ($frameworkFields as $field => $framework) {
            $tag = $this->strongestTag($coverage[$framework] ?? []);
            $derived[$field] = $tag;
            if ($tag !== null) {
                $strength[$field] = ($coverage[$framework] ?? [])[$tag] ?? 'supporting';
                $why[$field] = sprintf(
                    'The §9 coverage matrix marks %s as a %s evidence generator for this tag.',
                    $pedagogyLabel,
                    $strength[$field]
                );
            }
        }

        return $derived + [
            'pedagogy_tag' => $pedagogy,
            'pedagogy_secondary' => $secondary,
            'bloom_level' => $bloom,
            'practice_level' => $mapping['practice_level'] ?? $this->practiceLevelFor($bloom),
            'difficulty_1_to_5' => $difficulty,
            'gardner_intelligence' => $gardner,
            'riasec_signal' => null,
            'hpc_lens_primary' => $hpcLens,
            'cultural_context' => null,
            'language' => null,
            'estimated_duration_minutes' => $typeMeta['estimated_completion_minutes'] ?? null,
            'engagement_weight' => $typeMeta['engagement_weight'] ?? null,
            '_why' => $why,
            '_strength' => $strength,
        ];
    }

    /** Prefer a tag the matrix marks "strong"; otherwise the first supporting one. */
    protected function strongestTag(array $tags): ?string
    {
        foreach ($tags as $tag => $strength) {
            if ($strength === 'strong') {
                return (string) $tag;
            }
        }

        $first = array_key_first($tags);

        return $first !== null ? (string) $first : null;
    }

    /** A pedagogy's HPC lens, matched against the registry's three lenses. */
    protected function hpcLensFor(?array $pedagogyTerm, ?int $tenant): ?string
    {
        $raw = $pedagogyTerm['metadata']['hpc_lens'] ?? null;
        if (is_string($raw)) {
            return $this->registry->normalize('hpc_lenses', $raw, $tenant);
        }

        // Most pedagogies declare an HPC *domain*; map the SEL-facing ones onto
        // Sensitivity, creation-facing onto Creativity, the rest onto Awareness.
        $domain = (string) ($pedagogyTerm['metadata']['hpc_domain'] ?? '');

        return match (true) {
            str_contains($domain, 'socio') || str_contains($domain, 'emotional') => 'Sensitivity',
            str_contains($domain, 'creativ') || str_contains($domain, 'aesthetic') => 'Creativity',
            $domain !== '' => 'Awareness',
            default => null,
        };
    }

    protected function practiceLevelFor(?string $bloom): ?int
    {
        if ($bloom === null) {
            return null;
        }

        $levels = config('pal_content.bloom_levels', []);

        return isset($levels[$bloom]) ? (int) $levels[$bloom]['practice_level'] : null;
    }

    /** Share of the tag fields that carry a value, as a 0–1 fraction. */
    protected function completeness(array $values): float
    {
        $filled = 0;
        foreach ($values as $value) {
            if ($value !== null && $value !== [] && $value !== '') {
                $filled++;
            }
        }

        return $values === [] ? 0.0 : round($filled / count($values), 3);
    }

    // ── Batched reads ───────────────────────────────────────────────────────

    /** @return array<string,H5PNodeMetadata> node key => record */
    protected function storedFor(array $nodes, int $tenant): array
    {
        $byType = [];
        foreach ($nodes as $node) {
            $byType[$node['h5p_type']][] = $node['id'];
        }

        $query = H5PNodeMetadata::query()->forTenant($tenant)->where(function ($outer) use ($byType) {
            foreach ($byType as $type => $ids) {
                $outer->orWhere(fn ($q) => $q->where('h5p_type', $type)->whereIn('node_id', $ids));
            }
        });

        $out = [];
        foreach ($query->get() as $record) {
            $out["{$record->h5p_type}:{$record->node_id}"] = $record;
        }

        return $out;
    }

    /**
     * Bloom and difficulty already recorded against question-bank nodes.
     *
     * `lms_question_mapping` carries one row per (question, taxonomy), where
     * taxonomy 9 is Easy/Medium/Hard and taxonomy 82 is the six Bloom levels —
     * the same ids PalVocabulary reconciles PAL's Bloom keys against. Reading
     * them is the difference between a guessed tag and a real one.
     *
     * @return array<int,array> question id => ['bloom_level','practice_level','difficulty_1_to_5']
     */
    protected function questionMappings(array $nodes): array
    {
        $ids = [];
        foreach ($nodes as $node) {
            if (($node['source_table'] ?? null) === 'lms_question_master') {
                $ids[] = $node['id'];
            }
        }

        if ($ids === [] || ! Schema::hasTable('lms_question_mapping')) {
            return [];
        }

        $rows = DB::table('lms_question_mapping')
            ->whereIn('questionmaster_id', $ids)
            ->whereIn('mapping_type_id', [self::DIFFICULTY_PARENT_ID, self::BLOOM_PARENT_ID])
            ->get(['questionmaster_id', 'mapping_type_id', 'mapping_value_id']);

        // Easy / Medium / Hard → the 1-5 difficulty band, spread across the
        // range rather than crammed into 1-3.
        $difficultyByValue = [];
        foreach (DB::table('lms_mapping_type')->where('parent_id', self::DIFFICULTY_PARENT_ID)->orderBy('id')->pluck('id') as $index => $id) {
            $difficultyByValue[(int) $id] = [1, 3, 5][$index] ?? ($index + 1);
        }

        $out = [];
        foreach ($rows as $row) {
            $questionId = (int) $row->questionmaster_id;

            if ((int) $row->mapping_type_id === self::BLOOM_PARENT_ID) {
                $bloom = $this->bloomFromMappingTypeId((int) $row->mapping_value_id);
                if ($bloom !== null) {
                    $out[$questionId]['bloom_level'] = $bloom;
                    $out[$questionId]['practice_level'] = $this->practiceLevelFor($bloom);
                }
                continue;
            }

            $band = $difficultyByValue[(int) $row->mapping_value_id] ?? null;
            if ($band !== null) {
                // A question can carry several difficulty rows; keep the hardest.
                $out[$questionId]['difficulty_1_to_5'] = max($band, $out[$questionId]['difficulty_1_to_5'] ?? 0);
            }
        }

        return $out;
    }

    protected function bloomFromMappingTypeId(int $mappingTypeId): ?string
    {
        foreach (config('pal_content.bloom_levels', []) as $key => $definition) {
            if ((int) ($definition['mapping_type_id'] ?? 0) === $mappingTypeId) {
                return $key;
            }
        }

        return null;
    }

    // ── Writes ──────────────────────────────────────────────────────────────

    /**
     * Reject anything not in the registry. An unregistered tag is a write
     * failure, not a new category — the same rule the Content Intelligence
     * layer enforces, applied to the H5P estate.
     */
    protected function validate(array $payload, int $tenant): array
    {
        $single = [
            'pedagogy_tag' => 'pedagogy_tags',
            'bloom_level' => 'bloom_levels',
            'casel_domain' => 'casel_domains',
            'ngss_practice' => 'ngss_practices',
            'ncdg_goal' => 'ncdg_goals',
            'music_domain' => 'music_domains',
            'sports_domain' => 'sports_domains',
            'finance_level' => 'finance_levels',
            'riasec_signal' => 'riasec_signals',
            'hpc_lens_primary' => 'hpc_lenses',
        ];

        $clean = [];
        foreach ($single as $field => $domain) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }
            $clean[$field] = $payload[$field] === null || $payload[$field] === ''
                ? null
                : $this->registry->normalize($domain, (string) $payload[$field], $tenant);
        }

        if (array_key_exists('pedagogy_secondary', $payload)) {
            $clean['pedagogy_secondary'] = array_values(array_filter(array_map(
                fn ($value) => $this->registry->normalize('pedagogy_tags', (string) $value, $tenant),
                (array) $payload['pedagogy_secondary']
            )));
        }

        if (array_key_exists('gardner_intelligence', $payload)) {
            $clean['gardner_intelligence'] = array_values(array_filter(array_map(
                fn ($value) => $this->registry->normalize('gardner_intelligences', (string) $value, $tenant),
                (array) $payload['gardner_intelligence']
            )));
        }

        foreach (['practice_level', 'difficulty_1_to_5', 'estimated_duration_minutes'] as $field) {
            if (array_key_exists($field, $payload)) {
                $clean[$field] = $payload[$field] === null || $payload[$field] === '' ? null : (int) $payload[$field];
            }
        }

        foreach (['cultural_context', 'language'] as $field) {
            if (array_key_exists($field, $payload)) {
                $clean[$field] = $payload[$field] === '' ? null : $payload[$field];
            }
        }

        if (array_key_exists('engagement_weight', $payload)) {
            $clean['engagement_weight'] = $payload['engagement_weight'] === null || $payload['engagement_weight'] === ''
                ? null
                : round((float) $payload['engagement_weight'], 3);
        }

        if (array_key_exists('concept_ref_id', $payload)) {
            $clean['concept_ref_id'] = $payload['concept_ref_id'] ? (int) $payload['concept_ref_id'] : null;
        }

        return $clean;
    }

    // ── Tier 3: AI, only for what the first two tiers cannot supply ─────────

    public function aiAvailable(): bool
    {
        return (bool) config('pal_h5p.ai.enabled', true) && $this->llm->enabled();
    }

    public function aiUnavailableReason(): ?string
    {
        if (! config('pal_h5p.ai.enabled', true)) {
            return 'AI tagging is switched off for this deployment (PAL_H5P_AI).';
        }

        return $this->llm->unavailableReason();
    }

    /**
     * Ask the model to fill the gaps the derivation left, for nodes whose
     * completeness is below the caller's threshold.
     *
     * The prompt carries the closed vocabulary, so the model chooses from the
     * registry rather than inventing tags; anything it returns outside that
     * vocabulary is dropped by validate(). Nothing is written here — the
     * proposal is returned for the caller to review and save.
     *
     * @param  array<int,array>  $nodes
     * @return array{proposals:array<string,array>, available:bool, reason:?string, model:?string}
     */
    public function proposeTags(array $nodes, array $context, array $tagged = []): array
    {
        if (! $this->aiAvailable()) {
            return ['proposals' => [], 'available' => false, 'reason' => $this->aiUnavailableReason(), 'model' => null];
        }

        $limit = (int) config('pal_h5p.ai.max_nodes_per_call', 8);
        $nodes = array_slice(array_values($nodes), 0, max(1, $limit));
        if ($nodes === []) {
            return ['proposals' => [], 'available' => true, 'reason' => null, 'model' => $this->llm->model()];
        }

        $tenant = (int) ($context['sub_institute_id'] ?? 0);
        $vocabulary = $this->promptVocabulary($tenant);

        $payload = [];
        foreach ($nodes as $node) {
            $existing = $tagged[$node['node_key']]['values'] ?? [];
            $payload[] = [
                'node_key' => $node['node_key'],
                'h5p_type' => $node['h5p_type'],
                'title' => $node['title'],
                'text' => mb_substr(implode(' — ', $node['signals'] ?? []), 0, 1200),
                'already_known' => array_filter([
                    'pedagogy_tag' => $existing['pedagogy_tag'] ?? null,
                    'bloom_level' => $existing['bloom_level'] ?? null,
                ]),
            ];
        }

        $input = ['nodes' => $payload, 'vocabulary' => $vocabulary];
        $fingerprint = $this->llm->fingerprint($input);
        $kind = (string) config('pal_h5p.ai.cache_kind', 'h5p_model_tagging');
        $cacheKey = 'H5P.' . (int) ($context['chapter_id'] ?? 0) . '.tagging';

        // The cache is keyed on a fingerprint of the exact input the model saw,
        // so an edited node invalidates its own answer and a page reload never
        // re-bills the provider.
        $cached = $this->llm->cached($cacheKey, $kind, null, $fingerprint, $tenant);
        $data = $cached['payload'] ?? null;
        $model = $cached['model'] ?? null;
        $error = null;

        if ($data === null) {
            $response = $this->llm->json($this->aiSystemPrompt(), json_encode($input, JSON_UNESCAPED_UNICODE));

            if (empty($response['ok'])) {
                return [
                    'proposals' => [],
                    'available' => true,
                    'reason' => $response['error'] ?? 'The AI provider did not return a usable response.',
                    'model' => $this->llm->model(),
                    'cached' => false,
                ];
            }

            $data = (array) ($response['data'] ?? []);
            $model = $response['model'] ?? $this->llm->model();

            $this->llm->remember(
                $cacheKey,
                $kind,
                null,
                $fingerprint,
                $tenant,
                $data,
                null,
                $model,
                (array) ($response['usage'] ?? []),
                isset($context['user_id']) ? (int) $context['user_id'] : null
            );
        }

        $proposals = [];
        foreach ((array) ($data['nodes'] ?? []) as $entry) {
            $key = (string) ($entry['node_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $clean = $this->validate((array) $entry, $tenant);
            $clean = array_filter($clean, fn ($value) => $value !== null && $value !== []);
            if ($clean === []) {
                continue;
            }
            $proposals[$key] = [
                'values' => $clean,
                'rationale' => $entry['rationale'] ?? null,
                'confidence' => isset($entry['confidence']) ? round((float) $entry['confidence'], 2) : 0.6,
            ];
        }

        return [
            'proposals' => $proposals,
            'available' => true,
            'reason' => null,
            'model' => $this->llm->model(),
            'cached' => $cached !== null,
        ];
    }

    /** The closed vocabulary the model must choose from. */
    protected function promptVocabulary(int $tenant): array
    {
        $codes = fn (string $domain) => array_keys($this->registry->domain($domain, $tenant));

        return [
            'pedagogy_tag' => $codes('pedagogy_tags'),
            'bloom_level' => $codes('bloom_levels'),
            'casel_domain' => $codes('casel_domains'),
            'ngss_practice' => $codes('ngss_practices'),
            'ncdg_goal' => $codes('ncdg_goals'),
            'music_domain' => $codes('music_domains'),
            'sports_domain' => $codes('sports_domains'),
            'finance_level' => $codes('finance_levels'),
            'gardner_intelligence' => $codes('gardner_intelligences'),
            'riasec_signal' => $codes('riasec_signals'),
            'hpc_lens_primary' => $codes('hpc_lenses'),
            'cultural_context' => array_keys(config('pal_content.cultural_contexts', [])),
        ];
    }

    protected function aiSystemPrompt(): string
    {
        return implode("\n", [
            'You tag H5P learning content for the PAL V4 engine used in Indian K-12 schools.',
            'For each node you are given its H5P type, title and text, and any tag already known.',
            'Choose tags ONLY from the supplied vocabulary. Never invent a code. Omit a field entirely rather than guess.',
            'difficulty_1_to_5 is an integer 1-5. confidence is 0-1.',
            'cultural_context should be set only when the text genuinely uses that context.',
            'Return strict JSON: {"nodes":[{"node_key":"…","pedagogy_tag":"…","bloom_level":"…",'
                . '"casel_domain":"…","ngss_practice":"…","ncdg_goal":"…","gardner_intelligence":["…"],'
                . '"riasec_signal":"…","hpc_lens_primary":"…","cultural_context":"…","difficulty_1_to_5":3,'
                . '"confidence":0.7,"rationale":"one sentence"}]}',
            'No prose outside the JSON.',
        ]);
    }
}
