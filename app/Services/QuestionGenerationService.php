<?php

namespace App\Services;

use App\Models\lms\lmsQuestionMasterModel;
use App\Services\QuestionGeneration\H5p\ClaudeQuestionClient;
use App\Services\QuestionGeneration\H5p\H5pContentType;
use App\Services\QuestionGeneration\H5p\H5pContentTypeRegistry;
use App\Services\QuestionGeneration\H5p\H5pPrompts;
use App\Services\QuestionGeneration\QuestionFormat;
use App\Services\QuestionGeneration\QuestionFormatRegistry;
use App\Services\QuestionGeneration\QuestionFormResolver;
use App\Services\QuestionGeneration\SourcesOwnRows;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Question Generation Service (DeepSeek LLM)
 *
 * Implements the "Question Generation Prompt Pack v2.0 (Single-Table)" contract.
 * The LLM emits COLUMN-SHAPED JSON for `lms_question_master`: every top-level key
 * in a row object is a literal column name. Laravel injects the caller-owned and
 * deterministic fields, then inserts. See prompt pack `qgen-sys-2.0` / `ans-2.0`.
 */
class QuestionGenerationService
{
    protected string $model;
    protected float $temperature;
    protected int $timeout;
    protected int $maxTokens;
    protected string $promptVersion;
    protected string $envelopeVersion;

    // Bloom's taxonomy order used for quota allocation.
    protected const BLOOM_LEVELS = ['Remember', 'Understand', 'Apply', 'Analyze', 'Evaluate', 'Create'];

    /**
     * lms_mapping_type parent groups used to auto-tag generated questions in
     * lms_question_mapping. Same hardcoded ids the map:question command uses.
     */
    protected const DOK_MAPPING_TYPE_ID   = 9;  // "Depth of Knowledge (Easy, Medium, Hard)"
    protected const BLOOM_MAPPING_TYPE_ID = 82; // "Blooms Taxonomy (...)"

    /**
     * Teacher-facing DOK labels by numeric level, matched case-insensitively
     * against the children of parent 9. Level 4 tries the preferred names first
     * and falls back to Hard until a dedicated level-4 row exists in the table.
     */
    protected const DOK_LABEL_CANDIDATES = [
        1 => ['easy'],
        2 => ['medium'],
        3 => ['hard'],
        4 => ['challenge', 'advanced', 'expert', 'extended thinking', 'very hard', 'hard'],
    ];

    /** @var array|null Per-request cache of DOK/Bloom child rows from lms_mapping_type. */
    protected ?array $mappingCatalog = null;

    // DoK / difficulty / marks ladder, keyed by Bloom level.
    protected const BLOOM_META = [
        'Remember' => ['dok' => 1, 'difficulty' => 'Easy',   'points' => 1, 'sub_type' => 'Very Short Answer'],
        'Understand' => ['dok' => 1, 'difficulty' => 'Easy',   'points' => 2, 'sub_type' => 'Short Answer'],
        'Apply'    => ['dok' => 2, 'difficulty' => 'Medium', 'points' => 3, 'sub_type' => 'Short Answer'],
        'Analyze'  => ['dok' => 3, 'difficulty' => 'Hard',   'points' => 4, 'sub_type' => 'Long Answer'],
        'Evaluate' => ['dok' => 4, 'difficulty' => 'Hard',   'points' => 5, 'sub_type' => 'Long Answer'],
        'Create'   => ['dok' => 4, 'difficulty' => 'Hard',   'points' => 5, 'sub_type' => 'Case Study'],
    ];

    /** Resolved lazily so `new QuestionGenerationService()` stays dependency-free. */
    protected ?QuestionFormatRegistry $formatRegistry;

    public function __construct(?QuestionFormatRegistry $formatRegistry = null)
    {
        $this->formatRegistry = $formatRegistry;
        $this->model         = config('deepseek.model', 'deepseek-chat');
        $this->temperature   = config('deepseek.temperature_mcq', 0.4);
        $this->timeout       = (int) config('deepseek.timeout_seconds', 600);
        $this->maxTokens     = (int) config('deepseek.max_output_tokens', 0);
        $this->promptVersion = config('deepseek.prompt_version', 'qgen-sys-2.0');
        $this->envelopeVersion = config('deepseek.answer_envelope_version', 'ans-2.0');
    }

    /* ----------------------------------------------------------------
     * Key resolution
     * ---------------------------------------------------------------- */

    /**
     * The school this service resolves credentials for.
     *
     * Set by the caller from the request's own context — never defaulted to a literal
     * institute id. Null keeps the previous behaviour, platform keys only.
     */
    protected int|string|null $subInstituteId = null;

    /**
     * Scope this service to the signed-in school.
     *
     * Returns $this rather than a clone because callers construct this service per
     * request; there is no shared instance for a tenant to leak out of.
     */
    public function forInstitute(int|string|null $subInstituteId): static
    {
        $this->subInstituteId = $subInstituteId;

        return $this;
    }

    /** The formats this service can write; see QuestionFormatRegistry. */
    public function formats(): QuestionFormatRegistry
    {
        return $this->formatRegistry ??= app(QuestionFormatRegistry::class);
    }

    /**
     * The provider credential, from the platform's shared pool.
     *
     * Was two private copies of the same lookup — `getAIKey($type, 1)` with an
     * unordered `first()` and no tenant filter, plus an inline DB fallback that did
     * the same thing. Both meant question generation used whichever key the database
     * happened to return, regardless of which school was generating.
     *
     * `ProviderKeyResolver` is the one lookup the Gemini and OpenRouter clients also
     * use: a school's own key first, the platform key next, newest active row within
     * either, env last.
     */
    protected function resolveApiKey(): ?string
    {
        $key = app(\App\Domain\AI\Support\ProviderKeyResolver::class)->resolve(
            (string) config('deepseek.api_type', 'DEEPSEEK_API_KEY'),
            $this->subInstituteId,
            config('deepseek.api_key'),
        );

        return $key['api_key'] ?? null;
    }

    /* ----------------------------------------------------------------
     * Concept slice loading
     * ---------------------------------------------------------------- */

    protected function loadConceptSlice(
        int $conceptId,
        $subInstituteId,
        ?int $chapterId = null,
        ?int $subjectId = null,
        ?int $standardId = null
    ): array
    {
        $conceptQuery = DB::table('lms_concept')->where('id', $conceptId);

        if ($chapterId) {
            $conceptQuery->where('chapter_id', $chapterId);
        }
        if ($subjectId) {
            $conceptQuery->where('subject_id', $subjectId);
        }
        if ($standardId) {
            $conceptQuery->where('standard_id', $standardId);
        }
        $concept = $conceptQuery->first();

        if (!$concept) {
            return ['concept' => null, 'chapter' => null, 'intel' => null, 'found' => false];
        }

        $chapterId = $chapterId ?: (int) $concept->chapter_id;
        $subjectId = $subjectId ?: (int) $concept->subject_id;

        $chapter = DB::table('chapter_master')
            ->select('id', 'grade_id', 'standard_id', 'subject_id', 'sub_institute_id')
            ->where('id', $chapterId)
            ->first();

        $semanticQuery = DB::table('semantic_intelligence')
            ->where('chapter_id', $chapterId)
            ->where('subject_id', $subjectId);

        // 1. Exact match: chapter + sub_institute.
        $intel = null;
        if ($subInstituteId) {
            $intel = (clone $semanticQuery)
                ->where('sub_institute_id', $subInstituteId)
                ->first();
        }

        // 2. Fallback: any slice for this concept's chapter (sub_institute may differ).
        if (!$intel) {
            $intel = (clone $semanticQuery)->first();
        }

        // 3. Fallback: slice linked via the concept's extraction_id.
        if (!$intel && !empty($concept->extraction_id)) {
            $intel = DB::table('semantic_intelligence')
                ->where('extraction_id', $concept->extraction_id)
                ->first();
        }

        return ['concept' => $concept, 'chapter' => $chapter, 'intel' => $intel, 'found' => true];
    }

    protected function decodeJsonColumn($value)
    {
        if ($value === null || $value === '') {
            return [];
        }
        $decoded = is_string($value) ? json_decode($value, true) : $value;
        return is_array($decoded) ? $decoded : [];
    }

    protected function normalizeConceptName($value): string
    {
        $value = mb_strtolower(trim((string) $value));
        return preg_replace('/\s+/', ' ', $value) ?? '';
    }

    protected function isListArray(array $value): bool
    {
        if ($value === []) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }

    protected function findConceptEntry(array $blob, int $conceptId, ?string $conceptName): array
    {
        $entries = [];
        if (isset($blob['concepts']) && is_array($blob['concepts'])) {
            $entries = $blob['concepts'];
        } elseif ($this->isListArray($blob)) {
            $entries = $blob;
        }

        $normalizedName = $this->normalizeConceptName($conceptName);
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $entryConcept = is_array($entry['concept'] ?? null) ? $entry['concept'] : [];
            $entryConceptId = (int) ($entryConcept['concept_id'] ?? $entry['concept_id'] ?? 0);
            if ($entryConceptId > 0 && $entryConceptId === $conceptId) {
                return $entry;
            }

            $entryConceptName = $entryConcept['concept_name'] ?? $entry['concept_name'] ?? null;
            if ($normalizedName !== '' && $this->normalizeConceptName($entryConceptName) === $normalizedName) {
                return $entry;
            }
        }

        return [];
    }

    protected function firstNonEmptyConceptValue(array $entry, array $keys): array
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $entry)) {
                continue;
            }

            $value = is_array($entry[$key]) ? $entry[$key] : $this->decodeJsonColumn($entry[$key]);
            if (!empty($value)) {
                return $value;
            }
        }

        return [];
    }

    protected function buildConceptIntelligence(array $slice): array
    {
        $concept = $slice['concept'];
        $intel = $slice['intel'];

        $blob = [];
        if ($intel) {
            $fullRaw = $intel->full_intelligence_json
                ?? $intel->full_intelegance_json
                ?? null;
            $blob = $this->decodeJsonColumn($fullRaw);
        }

        $conceptId = (int) ($concept->id ?? 0);
        $conceptName = $concept->name ?? null;

        return [
            'blob' => $blob,
            'entry' => $this->findConceptEntry($blob, $conceptId, $conceptName),
        ];
    }

    protected function intelligenceField(array $slice, array $intelligence, array $keys, ?string $column = null): array
    {
        $intel = $slice['intel'];
        $blob = $intelligence['blob'] ?? [];
        $entry = $intelligence['entry'] ?? [];

        $value = $this->firstNonEmptyConceptValue($entry, $keys);

        if (empty($value) && $intel && $column && isset($intel->{$column})) {
            $value = $this->decodeJsonColumn($intel->{$column});
        }

        if (empty($value) && is_array($blob)) {
            foreach ($keys as $key) {
                if (isset($blob[$key])) {
                    $value = is_array($blob[$key]) ? $blob[$key] : $this->decodeJsonColumn($blob[$key]);
                    break;
                }
            }
        }

        return is_array($value) ? $value : [];
    }

    protected function uniqueStructuredItems(array $items): array
    {
        $seen = [];
        $unique = [];

        foreach ($items as $item) {
            $key = is_array($item)
                ? (json_encode($item, JSON_UNESCAPED_UNICODE) ?: '')
                : (string) $item;
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $item;
        }

        return $unique;
    }

    /**
     * Build the "concept slice" handed to the LLM. Sends one concept, not the
     * full 240KB intelligence blob. Field names deliberately mirror the prompt
     * pack's references (knowledge_items, abilities, misconceptions, ...).
     *
     * Reads from BOTH the normalised separate columns AND the raw
     * `full_intelligence_json` / `full_intelegance_json` blob, since extractions
     * may have populated either (or both).
     */
    protected function buildConceptSlice(array $slice, ?array $intelligence = null): array
    {
        $concept = $slice['concept'];
        $intelligence = $intelligence ?? $this->buildConceptIntelligence($slice);

        $blobKeys = [
            'knowledge_items'        => ['knowledge_items', 'knowledge'],
            'abilities'              => ['abilities', 'ability'],
            'competency'             => ['competencies', 'competency'],
            'dok'                    => ['dok'],
            'evidence'               => ['evidence'],
            'prerequisites'          => ['prerequisites'],
            'misconceptions'         => ['misconceptions'],
            'real_world_applications'=> ['real_world_applications', 'applications'],
            'learning_objectives'    => ['learning_objectives', 'objectives'],
            'learning_outcomes'      => ['learning_outcomes', 'outcomes'],
        ];

        $colKeys = [
            'knowledge_items'        => 'knowledge',
            'abilities'              => 'ability',
            'competency'             => 'competency',
            'dok'                    => 'dok',
            'evidence'               => null,
            'prerequisites'          => 'prerequisites',
            'misconceptions'         => 'misconceptions',
            'real_world_applications'=> 'real_world_applications',
            'learning_objectives'    => 'learning_objectives',
            'learning_outcomes'      => 'learning_outcomes',
        ];

        $out = [
            'concept'             => $concept->name ?? null,
            'concept_description' => $concept->description ?? null,
        ];

        foreach ($blobKeys as $field => $candidates) {
            $value = $this->intelligenceField($slice, $intelligence, $candidates, $colKeys[$field]);
            $out[$field] = $field === 'evidence' ? $this->uniqueStructuredItems($value) : $value;
        }

        return $out;
    }

    /**
     * True when the built slice carries any testable content (knowledge / abilities
     * / misconceptions / outcomes). Used to decide best-effort vs grounded generation.
     */
    protected function sliceHasContent(array $slice): bool
    {
        foreach (['knowledge_items', 'abilities', 'misconceptions', 'learning_outcomes', 'competency'] as $f) {
            if (!empty($slice[$f])) {
                return true;
            }
        }
        return false;
    }

    protected function normalizeBloomLevel($value): ?string
    {
        $needle = mb_strtolower(trim((string) $value));
        foreach (self::BLOOM_LEVELS as $level) {
            if (mb_strtolower($level) === $needle) {
                return $level;
            }
        }

        return null;
    }

    protected function conceptScopedRows(array $rows, ?string $conceptName): array
    {
        $normalizedConcept = $this->normalizeConceptName($conceptName);
        if ($normalizedConcept === '') {
            return $rows;
        }

        return array_values(array_filter($rows, function ($row) use ($normalizedConcept) {
            if (!is_array($row) || empty($row['concept_name'])) {
                return true;
            }

            return $this->normalizeConceptName($row['concept_name']) === $normalizedConcept;
        }));
    }

    protected function bloomWeightsFromIntelligence(array $slice, array $intelligence): array
    {
        $conceptName = $slice['concept']->name ?? null;
        $blooms = $this->conceptScopedRows(
            $this->intelligenceField($slice, $intelligence, ['blooms_level', 'blooms'], 'blooms_level'),
            $conceptName
        );

        $weights = [];
        foreach ($blooms as $row) {
            if (!is_array($row)) {
                continue;
            }

            $level = $this->normalizeBloomLevel($row['level'] ?? $row['bloom_level'] ?? $row['blooms_level'] ?? null);
            if (!$level) {
                continue;
            }

            $coverage = $row['coverage_score'] ?? $row['score'] ?? $row['weight'] ?? 1;
            $weights[$level] = max(0, (float) $coverage);
        }

        return array_filter($weights, fn($weight) => $weight > 0);
    }

    protected function availableDokLevels(array $slice, array $intelligence): array
    {
        $conceptName = $slice['concept']->name ?? null;
        $dokRows = $this->conceptScopedRows(
            $this->intelligenceField($slice, $intelligence, ['dok'], 'dok'),
            $conceptName
        );

        $levels = [];
        foreach ($dokRows as $row) {
            $level = is_array($row)
                ? ($row['level'] ?? $row['dok_level'] ?? null)
                : $row;
            $level = (int) $level;
            if ($level >= 1 && $level <= 4) {
                $levels[$level] = $level;
            }
        }

        sort($levels);
        return $levels;
    }

    protected function clampDok(int $dok, array $availableLevels): int
    {
        $dok = max(1, min(4, $dok));
        if (empty($availableLevels)) {
            return $dok;
        }

        $allowed = array_filter($availableLevels, fn($level) => $level <= $dok);
        return !empty($allowed) ? max($allowed) : min($availableLevels);
    }

    /* ----------------------------------------------------------------
     * Quota allocation (BloomQuotaAllocator — largest-remainder)
     * ---------------------------------------------------------------- */

    /**
     * Resolve the binding QUOTA TABLE. Accepts an explicit `quota` array from the
     * caller, otherwise derives one from a default distribution over Bloom levels.
     */
    protected function buildQuota(
        string $type,
        int $total,
        array $input,
        array $slice = [],
        array $intelligence = []
    ): array
    {
        $availableDokLevels = (!empty($slice) && !empty($intelligence))
            ? $this->availableDokLevels($slice, $intelligence)
            : [];

        if (!empty($input['quota']) && is_array($input['quota'])) {
            $quota = [];
            foreach ($input['quota'] as $row) {
                $level = $row['level'] ?? null;
                if (!in_array($level, self::BLOOM_LEVELS, true)) {
                    continue;
                }
                $meta = self::BLOOM_META[$level];
                $quota[] = [
                    'level'      => $level,
                    'count'      => (int) ($row['count'] ?? 0),
                    'dok'        => $this->clampDok((int) ($row['dok'] ?? $meta['dok']), $availableDokLevels),
                    'difficulty' => $row['difficulty'] ?? $meta['difficulty'],
                    'points'     => $type === 'mcq' ? 1 : (int) ($row['points'] ?? $meta['points']),
                    'sub_type'   => $type === 'mcq' ? 'MCQ' : ($row['sub_type'] ?? $meta['sub_type']),
                ];
            }
            return $quota;
        }

        $weights = (!empty($slice) && !empty($intelligence))
            ? $this->bloomWeightsFromIntelligence($slice, $intelligence)
            : [];

        return !empty($weights)
            ? $this->quotaFromWeights($type, $total, $weights, $availableDokLevels)
            : $this->defaultQuota($type, $total, $availableDokLevels);
    }

    protected function defaultQuota(string $type, int $total, array $availableDokLevels = []): array
    {
        // Weighted default distribution across Bloom levels.
        $weights = [
            'Remember'  => 0.15,
            'Understand'=> 0.30,
            'Apply'     => 0.30,
            'Analyze'   => 0.15,
            'Evaluate'  => 0.10,
            'Create'    => 0.00,
        ];

        return $this->quotaFromWeights($type, $total, $weights, $availableDokLevels);
    }

    protected function quotaFromWeights(string $type, int $total, array $weights, array $availableDokLevels = []): array
    {
        $counts = $this->largestRemainder($weights, $total);

        $quota = [];
        foreach (self::BLOOM_LEVELS as $level) {
            $count = $counts[$level] ?? 0;
            if ($count <= 0) {
                continue;
            }
            $meta = self::BLOOM_META[$level];
            $quota[] = [
                'level'      => $level,
                'count'      => $count,
                'dok'        => $this->clampDok($meta['dok'], $availableDokLevels),
                'difficulty' => $meta['difficulty'],
                'points'     => $type === 'mcq' ? 1 : $meta['points'],
                'sub_type'   => $type === 'mcq' ? 'MCQ' : $meta['sub_type'],
            ];
        }

        return $quota;
    }

    /**
     * Largest-remainder apportionment so integer counts always sum to $total.
     */
    protected function largestRemainder(array $weights, int $total): array
    {
        $sum = array_sum($weights) ?: 1;
        $raw = [];
        $floor = [];
        $remainder = [];
        foreach ($weights as $k => $w) {
            $exact = ($w / $sum) * $total;
            $f = (int) floor($exact);
            $raw[$k] = $exact;
            $floor[$k] = $f;
            $remainder[$k] = $exact - $f;
        }
        $assigned = array_sum($floor);
        $left = $total - $assigned;

        arsort($remainder);
        foreach (array_keys($remainder) as $k) {
            if ($left <= 0) {
                break;
            }
            $floor[$k]++;
            $left--;
        }

        return $floor;
    }

    /* ----------------------------------------------------------------
     * Deduplication corpus + semantic key
     * ---------------------------------------------------------------- */

    protected function buildDedupCorpus(int $conceptId, int $questionTypeId, int $limit = 200, ?string $formatCode = null): array
    {
        // A format-scoped run dedups against its own FORMAT, not the coarse type id:
        // every narrative-typed format shares question_type_id 2, so a type-id corpus
        // would feed true/false stems to a fill-in-the-blank run. Extracted questions
        // of the same form are included on purpose -- a generated item must not
        // restate a textbook one either.
        if ($formatCode !== null) {
            $hasSidecar = Schema::hasTable('lms_question_extraction');
            $query = DB::table('lms_question_master as q')
                ->where('q.concept_id', $conceptId)
                ->whereNull('q.deleted_at')
                ->whereRaw(
                    QuestionFormResolver::sqlExpression('q', $hasSidecar ? 'x.question_type_code' : 'NULL') . ' = ?',
                    [$formatCode]
                )
                ->orderByDesc('q.id')
                ->limit($limit);

            if ($hasSidecar) {
                $query->leftJoin('lms_question_extraction as x', 'x.question_id', '=', 'q.id');
            }

            return $query->pluck('q.question_title')
                ->map(fn($t) => mb_substr((string) $t, 0, 300))
                ->all();
        }

        return DB::table('lms_question_master')
            ->where('concept_id', $conceptId)
            ->where('question_type_id', $questionTypeId)
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('question_title')
            ->map(fn($t) => mb_substr((string) $t, 0, 300))
            ->all();
    }

    protected function semanticConceptKey(int $conceptId, ?string $conceptName): string
    {
        $slug = preg_replace('/[^A-Za-z0-9]+/', '_', strtoupper((string) $conceptName));
        $slug = trim($slug, '_');
        $slug = $slug === '' ? 'CONCEPT' : $slug;
        return mb_substr($slug, 0, 40) . '_' . str_pad((string) $conceptId, 4, '0', STR_PAD_LEFT);
    }

    protected function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    protected function buildPersistenceContext(
        array $slice,
        array $input,
        int $questionTypeId,
        string $semanticKey,
        ?QuestionFormat $format = null
    ): array {
        $concept = $slice['concept'];
        $chapter = $slice['chapter'] ?? null;

        return [
            // The catalogue form to record (question_format_code + answer.item_form),
            // or null for the legacy narrative alias. scope_dedup narrows the
            // content-hash collision check to that form.
            'format_code'                  => $format?->persistedFormatCode(),
            'scope_dedup'                  => $format?->scopesDedupByFormat() ?? false,
            'concept_id'                   => (int) $concept->id,
            'concept_name'                 => $concept->name ?? 'CONCEPT',
            'question_type_id'             => $questionTypeId,
            'sub_institute_id'             => $this->nullableInt($input['sub_institute_id'] ?? null)
                ?? $this->nullableInt($concept->sub_institute_id ?? null)
                ?? $this->nullableInt($chapter->sub_institute_id ?? null),
            'standard_id'                  => $this->nullableInt($concept->standard_id ?? null)
                ?? $this->nullableInt($chapter->standard_id ?? null)
                ?? $this->nullableInt($input['standard_id'] ?? null),
            'subject_id'                   => $this->nullableInt($concept->subject_id ?? null)
                ?? $this->nullableInt($chapter->subject_id ?? null)
                ?? $this->nullableInt($input['subject_id'] ?? null),
            'grade_id'                     => $this->nullableInt($chapter->grade_id ?? null)
                ?? $this->nullableInt($input['grade_id'] ?? null),
            'chapter_id'                   => $this->nullableInt($concept->chapter_id ?? null)
                ?? $this->nullableInt($chapter->id ?? null)
                ?? $this->nullableInt($input['chapter_id'] ?? null),
            'created_by'                   => $this->nullableInt($input['created_by'] ?? null),
            'semantic_concept_key'         => $semanticKey,
            'pre_grade_topic'              => $input['pre_grade_topic'] ?? null,
            'post_grade_topic'             => $input['post_grade_topic'] ?? null,
            'cross_curriculum_grade_topic' => $input['cross_curriculum_grade_topic'] ?? null,
        ];
    }

    /* ----------------------------------------------------------------
     * Prompt construction
     * ---------------------------------------------------------------- */

    protected function systemPrompt(): string
    {
        return <<<'SYSPROMPT'
You are an assessment item writer for a K-12 CBSE curriculum question bank.

You receive a CONCEPT SLICE (one concept, curriculum-derived), a QUOTA TABLE, and a
DEDUP CORPUS. You emit rows for a database table. Every top-level key you write is a
literal column name. Write no other keys.

## Absolute rules

1. GROUNDEDNESS. Every question must be answerable using only the CONCEPT SLICE.
   Never introduce facts, numbers, reactions, formulae, or examples absent from
   `knowledge_items`, `evidence`, or `real_world_applications`. If you cannot write
   the requested count from the slice alone, return fewer rows and set
   `"underfilled": true` with a `"reason"`. Never invent content to hit a number.

2. PROVENANCE. Inside `answer`, every item cites what it tests:
     `knowledge_refs`     one or more exact `knowledge` values from `knowledge_items`
     `ability_ref`        exactly one exact `ability` value, or null for pure recall
     `misconception_refs` exact `misconception` values, or []
     `competency_ref`     the exact `competency` value the item operationalises,
                          or null when none applies (optional key)
   And `learning_outcome` is a JSON array of exact `outcome` strings.
   All refs must match the slice CHARACTER FOR CHARACTER. Never paraphrase a ref.
   Never invent a ref to satisfy a rule. An item you cannot ground with at least one
   `knowledge_ref` must not be written.

3. QUOTA. The QUOTA TABLE is binding. Produce exactly the stated count at each Bloom's
   level. Do not rebalance. Do not add levels absent from the table. Each item's
   `dok_level`, `difficulty` and `points` must equal what the QUOTA TABLE assigns to
   that Bloom's level.

4. NO PREREQUISITE LEAKAGE. The learner already knows the listed `prerequisites`.
   Do not test them. Test THIS concept.

5. NO DUPLICATION. Each item must be semantically distinct from every DEDUP CORPUS
   entry and from every other item in this response. Rephrasing is duplication. Use
   `blueprint_exemplars` for STYLE only — never for content.

6. MISCONCEPTION TARGETING. Where a `misconceptions[]` entry relates to the knowledge
   under test, items at Understand level or above SHOULD create a situation in which a
   student holding that misconception selects a predictable wrong option (MCQ) or
   writes a predictable wrong claim (narrative).

7. CHARACTER SET. The database columns are utf8mb3. Emit BMP characters only.
   No emoji. No mathematical alphanumeric symbols. No 4-byte characters of any kind.
   Standard arrows (→ ⇌), subscripts (₂), degree (°) and Devanagari/Gujarati are fine.

8. LENGTH CAPS ARE HARD. `description` and `subconcept` are VARCHAR(250) and TRUNCATE
   SILENTLY. Keep each under 240 characters. Count before you emit.

9. LANGUAGE AND NEUTRALITY. Indian English, CBSE register. Sentences under 25 words.
   No idioms. SI units. Use the slice's exact terminology — never a synonym for a
   defined term. No names, regions, religions, castes, genders or economic markers
   that advantage any group. Where a person is needed: "a student", "a technician",
   "an observer".

10. OUTPUT. A single JSON object matching the RESPONSE SCHEMA. No markdown fences.
    No commentary. No trailing commas. Never emit these keys: id, question_type_id,
    grade_id, standard_id, subject_id, chapter_id, concept_id, topic_id,
    sub_institute_id, status, created_by, created_on, content_hash, generation_meta.
    They are the caller's, not yours.

11. HINTS. `hint_text` is one sentence redirecting the student to the relevant
    knowledge without stating it. A hint that eliminates any option is not a hint.
    For Remember-level items return null — a fact cannot be hinted at without being
    supplied.

12. SELF-CHECK before emitting. Per item, in order:
    (a) answerable from the slice alone;
    (b) every ref matches the slice character for character;
    (c) bloom, dok, difficulty, points internally consistent and matching the quota;
    (d) exactly one defensible correct answer exists;
    (e) `description` and `subconcept` under 240 characters;
    (f) not a rephrasing of anything in the DEDUP CORPUS.
    Silently discard and rewrite any failure. Do not report the self-check.
SYSPROMPT;
    }

    protected function quotaTableMarkdown(array $quota, string $type): string
    {
        if ($type === 'mcq') {
            $rows = "| Bloom's Level | Count | dok_level | difficulty | points |\n|---|---|---|---|---|\n";
            foreach ($quota as $q) {
                $rows .= "| {$q['level']} | {$q['count']} | {$q['dok']} | {$q['difficulty']} | 1 |\n";
            }
            return $rows;
        }

        $rows = "| Bloom's Level | Count | dok_level | difficulty | sub_type | points |\n|---|---|---|---|---|---|\n";
        foreach ($quota as $q) {
            $rows .= "| {$q['level']} | {$q['count']} | {$q['dok']} | {$q['difficulty']} | {$q['sub_type']} | {$q['points']} |\n";
        }
        return $rows;
    }

    protected function dedupMarkdown(array $stems): string
    {
        if (empty($stems)) {
            return "- (none yet)\n";
        }
        return implode("\n", array_map(fn($s) => '- ' . $s, $stems));
    }

    /**
     * The part of the user prompt every format shares: task line, quota table,
     * concept slice, dedup corpus and the best-effort / diagnostic-stage notes.
     *
     * Lifted out of userPrompt() unchanged so the legacy MCQ and narrative prompts
     * and the generic format prompt are built from one header; the golden tests
     * pin that the legacy output did not move.
     */
    protected function promptHeader(
        int $total,
        string $taskType,
        string $semanticKey,
        string $quotaTable,
        string $sliceJson,
        string $dedup,
        string $bestEffortNote,
        string $stageInstruction
    ): string {
        return <<<PROMPT
TASK: Write {$total} {$taskType} rows for the concept below.

SEMANTIC_CONCEPT_KEY (echo it back unchanged in your response): {$semanticKey}

## QUOTA TABLE (binding)
{$quotaTable}

## CONCEPT SLICE
{$sliceJson}

## DEDUP CORPUS (do not reproduce or rephrase)
{$dedup}
{$bestEffortNote}
{$stageInstruction}
PROMPT;
    }

    protected function bestEffortNote(bool $hasContent): string
    {
        if ($hasContent) {
            return '';
        }

        return "\nNOTE: The structured CONCEPT SLICE for this concept is empty in our records "
            . "(no extracted knowledge_items / abilities / misconceptions). Return fewer rows or no rows and "
            . "set `underfilled` true with a clear reason. Do not use general knowledge to fill the batch.\n";
    }

    protected function stageInstruction(?string $diagnosticStage): string
    {
        return $diagnosticStage === null ? '' : "\n## ESO DIAGNOSTIC STAGE\nEvery generated item in this batch is for the ESO stage `{$diagnosticStage}`. Keep every stem focused on the concept itself and make the item suitable for that diagnostic purpose.\n";
    }

    /**
     * The user prompt for every format that is not a legacy one.
     *
     * Same header as the legacy prompts, then a FORMAT CONTRACT that names the one
     * format this run may produce -- the model never chooses it -- then the
     * format's own construction rules and JSON Schema.
     */
    protected function formatUserPrompt(
        QuestionFormat $format,
        array $quota,
        array $slice,
        array $stems,
        string $semanticKey,
        bool $hasContent = true,
        ?string $diagnosticStage = null
    ): string {
        $total = array_sum(array_column($quota, 'count'));
        $code = $format->responseType();

        $header = $this->promptHeader(
            $total,
            $format->taskLabel(),
            $semanticKey,
            $this->formatQuotaTable($quota),
            json_encode($slice, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            $this->dedupMarkdown($stems),
            $this->bestEffortNote($hasContent),
            $this->stageInstruction($diagnosticStage)
        );

        return $header
            . "\n\n## FORMAT CONTRACT\n"
            . "Every row you write is a `{$code}` item and nothing else. Set the top-level `question_type` to \"{$code}\" "
            . "and `answer.question_type` to \"{$code}\" on every row. Do not mix in any other question format, "
            . "even where the concept slice would suit one.\n"
            . "\n## CONSTRUCTION RULES (" . strtoupper($format->taskLabel()) . ")\n\n"
            . trim($format->constructionRules($total))
            . "\n\n## RESPONSE SCHEMA\n"
            . $format->responseSchema();
    }

    protected function formatQuotaTable(array $quota): string
    {
        $rows = "| Bloom's Level | Count | dok_level | difficulty | points |\n|---|---|---|---|---|\n";
        foreach ($quota as $q) {
            $rows .= "| {$q['level']} | {$q['count']} | {$q['dok']} | {$q['difficulty']} | {$q['points']} |\n";
        }

        return $rows;
    }

    /** Default Bloom spread for a format run with no intelligence-derived weights. */
    protected const FORMAT_DEFAULT_WEIGHTS = [
        'Remember' => 0.15,
        'Understand' => 0.30,
        'Apply' => 0.30,
        'Analyze' => 0.15,
        'Evaluate' => 0.10,
        'Create' => 0.00,
    ];

    /**
     * The binding quota for a format run.
     *
     * Differs from buildQuota() in three ways: the format's allowed Bloom levels
     * are enforced (an explicit level it cannot honestly be written at is refused,
     * not silently dropped, because dropping it would make the counts lie), marks
     * are held inside the format's range, and Auto weights are renormalised over the
     * allowed levels only.
     *
     * @throws \InvalidArgumentException when an explicit quota asks for a level the format excludes
     */
    protected function buildFormatQuota(
        QuestionFormat $format,
        int $total,
        array $input,
        array $slice,
        array $intelligence,
        int $defaultPoints
    ): array {
        $allowed = $format->allowedBloomLevels();
        [$minPoints, $maxPoints] = $format->marksRange();
        $availableDok = (!empty($slice) && !empty($intelligence))
            ? $this->availableDokLevels($slice, $intelligence)
            : [];

        $row = function (string $level, int $count, array $override = []) use ($format, $defaultPoints, $minPoints, $maxPoints, $availableDok): array {
            $meta = self::BLOOM_META[$level];
            $points = isset($override['points']) ? (int) $override['points'] : $defaultPoints;

            return [
                'level'      => $level,
                'count'      => $count,
                'dok'        => $this->clampDok((int) ($override['dok'] ?? $meta['dok']), $availableDok),
                'difficulty' => $override['difficulty'] ?? $meta['difficulty'],
                'points'     => max($minPoints, min($maxPoints, $points)),
                'sub_type'   => $format->subTypeFor($level),
            ];
        };

        if (!empty($input['quota']) && is_array($input['quota'])) {
            $quota = [];
            foreach ($input['quota'] as $explicit) {
                $level = $explicit['level'] ?? null;
                $count = (int) ($explicit['count'] ?? 0);
                if (!in_array($level, self::BLOOM_LEVELS, true) || $count <= 0) {
                    continue;
                }
                if (!in_array($level, $allowed, true)) {
                    throw new \InvalidArgumentException(
                        "{$format->label()} cannot be written at the {$level} level. Allowed: " . implode(', ', $allowed) . '.'
                    );
                }
                $quota[] = $row($level, $count, $explicit);
            }

            return $quota;
        }

        $weights = (!empty($slice) && !empty($intelligence))
            ? $this->bloomWeightsFromIntelligence($slice, $intelligence)
            : [];
        $weights = array_intersect_key($weights, array_flip($allowed));

        if (array_sum($weights) <= 0) {
            $weights = array_intersect_key(self::FORMAT_DEFAULT_WEIGHTS, array_flip($allowed));
        }
        if (array_sum($weights) <= 0) {
            $weights = array_fill_keys($allowed, 1.0);
        }

        $counts = $this->largestRemainder($weights, $total);
        $quota = [];
        foreach (self::BLOOM_LEVELS as $level) {
            if (($counts[$level] ?? 0) > 0) {
                $quota[] = $row($level, $counts[$level]);
            }
        }

        return $quota;
    }

    protected function userPrompt(string $type, array $quota, array $slice, array $stems, string $semanticKey, bool $hasContent = true, ?string $diagnosticStage = null): string
    {
        $total = array_sum(array_column($quota, 'count'));
        $quotaTable = $this->quotaTableMarkdown($quota, $type);
        $dedup = $this->dedupMarkdown($stems);
        $sliceJson = json_encode($slice, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $schema = $type === 'mcq' ? $this->mcqColumnSchema() : $this->narrativeColumnSchema();
        $taskType = $type === 'mcq' ? 'multiple-choice' : 'narrative (constructed-response)';

        $bestEffortNote = $this->bestEffortNote($hasContent);
        $stageInstruction = $this->stageInstruction($diagnosticStage);

        $common = $this->promptHeader($total, $taskType, $semanticKey, $quotaTable, $sliceJson, $dedup, $bestEffortNote, $stageInstruction);

        if ($type === 'mcq') {
            return $common . <<<PROMPT

## CONSTRUCTION RULES (MCQ)

## CBSE TYPOLOGY (2025-26 board pattern — answer.sub_type, one per row)

  "MCQ"              Stand-alone item. The default.
  "Assertion-Reason" Understand, Analyze or Evaluate only. `question_title` carries
                     "Assertion (A): ..." and "Reason (R): ..." on separate lines.
                     Options are EXACTLY the four standard CBSE choices:
                       A: Both A and R are true, and R is the correct explanation of A
                       B: Both A and R are true, but R is not the correct explanation of A
                       C: A is true but R is false
                       D: A is false but R is true
                     These fixed options are exempt from the forbidden-option,
                     parallel-length and key-rotation rules below. Build A and R so a
                     slice misconception leads to a predictable wrong option. At most
                     ceil({$total} / 4) Assertion-Reason items per batch.
  "Case-Based MCQ"   Apply or Analyze only. Put a 40-60 word stimulus in
                     `answer.stimulus`, assembled ONLY from `evidence` or
                     `real_world_applications`. The stem interrogates the stimulus —
                     it must be unanswerable without reading it.

Competency focus: CBSE papers weight ~50% of items as competency-focused
(scenario-embedded, Assertion-Reason, or Case-Based). Let the quota's
Apply/Analyze/Evaluate share carry that weight; keep Remember/Understand items as
plain stand-alone MCQs unless Assertion-Reason fits naturally.

question_title (the stem)
- Self-contained: answerable without reading the options.
- Direct question or sentence completion. Never a bare "Which of the following is true?"
- ≤ 35 words. Negatives (NOT, EXCEPT) at most once per five items, and CAPITALISED.
- Apply and Analyze stems must embed a scenario drawn from `real_world_applications`
  or `evidence`. Remember and Understand stems must not.

description  (VARCHAR(250) — teacher-facing one-liner, ≤ 240 chars)
- "Tests <ability_ref> at <bloom_level>; distractor D targets <misconception>."
- Written for a teacher scanning a list of 50 items. Not a restatement of the stem.

subconcept  (VARCHAR(250), ≤ 240 chars)
- The single `knowledge` string most central to this item. Exact from the slice.

answer.options — exactly 4, labelled A B C D
- Exactly one correct, defensible from `knowledge_items` alone.
- All four: grammatically parallel to the stem; similar length (longest ≤ 1.5×
  shortest); mutually exclusive; plausible to an unprepared student.
- FORBIDDEN: "All of the above", "None of the above", "Both A and B", "A and C only",
  the words "always" or "never" in any option, and any option that is a substring of
  another.
- Vary the key position. The correct answer sits in the same slot at most
  ceil({$total} / 3) times across the set.

answer.options[].distractor_type — every distractor declares its origin
  "misconception" → derived from a `misconceptions[]` entry. Set `misconception_ref`
                    to the exact string. MANDATORY: at least one such distractor per
                    item wherever a related misconception exists in the slice.
  "near_miss"     → a true statement from a DIFFERENT `knowledge_items` entry that
                    does not answer this stem. Set `knowledge_ref`.
  "overgeneral"   → the correct idea extended past its stated scope.
  "plausible"     → last resort. Justify in `rationale`. Never fabricate a
                    `misconception_ref` to avoid using this type.

answer.options[].rationale
- One sentence naming the specific reasoning error a student makes in selecting it.
- "It is wrong" is not a rationale. "It is incorrect because it is false" is not a
  rationale. Name the error.

answer.explanation — 2–3 sentences, student-facing, why the key is correct.
answer.remediation — 1 sentence, teacher-facing: what to reteach if the class clusters
                     on a misconception distractor. Draw on that misconception's
                     `correction` field when present.
hint_text — see SYSTEM rule 11. null at Remember level.
learning_outcome — JSON array of exact `outcome` strings from `learning_outcomes[]`.

## RESPONSE SCHEMA
{$schema}
PROMPT;
        }

        return $common . <<<PROMPT

## CONSTRUCTION RULES (NARRATIVE)

question_title
- Open with the command verb matching the Bloom level, taken from the `verb` field of
  the `abilities[]` entry you cite:
    Remember    → State / List / Define / Name
    Understand  → Explain / Describe / Differentiate / Interpret
    Apply       → Apply / Calculate / Predict / Determine / Identify
    Analyze     → Analyse / Compare / Examine / Justify why
    Evaluate    → Evaluate / Assess / Critique / Argue whether
    Create      → Design / Propose / Construct
- The number of things demanded equals `points`. A 3-mark item asks for three
  creditable elements. Never "some" or "a few".
- Apply level and above open with a 1–2 sentence scenario from
  `real_world_applications` or `evidence`. Recall items must not.
- Assertion Reason: Assertion (A) and Reason (R) on separate lines, then the four
  standard CBSE options. Build it so a slice misconception leads to the wrong option.
- Case Study: 60–90 word stimulus in `answer.stimulus`, then 2–3 `sub_parts` whose
  marks sum to `points`.

answer.model_answer
- A full-credit student answer, in student voice, at the ladder length.
- No meta-language ("The answer is…", "Students should…"). Slice content only.

answer.marking_points — the load-bearing field. One entry per mark.
    mark           always 1. Half-marks are not permitted.
    criterion      what the student must have written to earn it
    accept         2–4 acceptable alternative phrasings
    reject         1–2 near-answers that must NOT earn the mark
    knowledge_ref  the knowledge item this mark tests
  count(marking_points) MUST equal points.

answer.keywords — 4–8 scoring keywords, each with `weight` (0.0–1.0) and `synonyms[]`.
  These drive keyword-overlap auto-scoring. A keyword appearing in `question_title` is
  disqualified — it rewards copying the question.

answer.common_errors — for each related `misconceptions[]` entry: what the erroneous
  answer looks like, and the `mark_ceiling` it should receive.

answer.full_credit_threshold — minimum marks to count as "mastered" for adaptive
  routing. Default ceil(0.75 × points).

description (≤ 240 chars) — "Tests <ability_ref> at <bloom_level>, <points> marks;
  common error: <misconception>."

## RESPONSE SCHEMA
{$schema}
PROMPT;
    }

    protected function mcqResponseSchema(): string
    {
        return <<<'SCHEMA'
{
  "semantic_concept_key": "string",
  "question_type": "mcq",
  "underfilled": false,
  "reason": null,
  "rows": [
    {
      "question_title": "string (self-contained stem, ≤400 chars)",
      "description": "string (teacher one-liner, ≤240 chars)",
      "subconcept": "string (single knowledge item, ≤240 chars)",
      "points": 1,
      "multiple_answer": 0,
      "hint_text": "string|null (≤200 chars)",
      "learning_outcome": ["exact outcome string"],
      "answer": {
        "v": "ans-2.0",
        "question_type": "mcq",
        "sub_type": "MCQ|Assertion-Reason|Case-Based MCQ",
        "competency_ref": "exact competency | null",
        "bloom_level": "Remember|Understand|Apply|Analyze|Evaluate|Create",
        "dok_level": 1,
        "difficulty": "Easy|Medium|Hard",
        "stimulus": "null, or 40-60 word stimulus (required for Case-Based MCQ)",
        "estimated_time_seconds": 70,
        "options": [
          {"label":"A","text":"...","is_correct":false,"distractor_type":"misconception|near_miss|overgeneral|plausible","misconception_ref":null,"knowledge_ref":null,"rationale":"..."},
          {"label":"B","text":"...","is_correct":true,"distractor_type":"correct","misconception_ref":null,"knowledge_ref":null,"rationale":"..."},
          {"label":"C","text":"...","is_correct":false,"distractor_type":"near_miss","misconception_ref":null,"knowledge_ref":"...","rationale":"..."},
          {"label":"D","text":"...","is_correct":false,"distractor_type":"plausible","misconception_ref":null,"knowledge_ref":null,"rationale":"..."}
        ],
        "correct_option": "B",
        "explanation": "student-facing, why key is correct",
        "remediation": "teacher-facing, what to reteach",
        "knowledge_refs": ["exact knowledge item"],
        "ability_ref": "exact ability | null",
        "misconception_refs": ["exact misconception"],
        "semantic_concept_key": "will be set by caller"
      }
    }
  ]
}
SCHEMA;
    }

    protected function narrativeResponseSchema(): string
    {
        return <<<'SCHEMA'
{
  "semantic_concept_key": "string",
  "question_type": "narrative",
  "underfilled": false,
  "reason": null,
  "rows": [
    {
      "question_title": "string (command verb first, ≤1200 chars)",
      "description": "string (teacher one-liner, ≤240 chars)",
      "subconcept": "string (single knowledge item, ≤240 chars)",
      "points": 3,
      "multiple_answer": 0,
      "hint_text": "string|null (≤200 chars)",
      "learning_outcome": ["exact outcome string"],
      "answer": {
        "v": "ans-2.0",
        "question_type": "narrative",
        "sub_type": "Very Short Answer|Short Answer|Long Answer|Assertion Reason|Case Study",
        "bloom_level": "Remember|Understand|Apply|Analyze|Evaluate|Create",
        "dok_level": 2,
        "difficulty": "Easy|Medium|Hard",
        "stimulus": null,
        "estimated_time_seconds": 120,
        "sub_parts": null,
        "model_answer": "full-credit student answer",
        "marking_points": [
          {"mark":1,"criterion":"...","accept":["...","..."],"reject":["..."],"knowledge_ref":"..."}
        ],
        "keywords": [
          {"term":"...","weight":0.5,"synonyms":["..."]}
        ],
        "common_errors": [
          {"misconception_ref":"...","erroneous_answer":"...","mark_ceiling":1}
        ],
        "full_credit_threshold": 3,
        "explanation": "student-facing, why key is correct",
        "remediation": "teacher-facing, what to reteach",
        "knowledge_refs": ["exact knowledge item"],
        "ability_ref": "exact ability | null",
        "misconception_refs": ["exact misconception"],
        "semantic_concept_key": "will be set by caller"
      }
    }
  ]
}
SCHEMA;
    }

    protected function mcqColumnSchema(): string
    {
        return <<<'SCHEMA'
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "type": "object",
  "additionalProperties": false,
  "required": ["semantic_concept_key", "question_type", "rows"],
  "properties": {
    "semantic_concept_key": { "type": "string" },
    "question_type": { "const": "mcq" },
    "underfilled": { "type": "boolean" },
    "reason": { "type": ["string", "null"] },
    "rows": {
      "type": "array",
      "items": {
        "type": "object",
        "additionalProperties": false,
        "required": ["question_title", "description", "subconcept", "points", "multiple_answer", "answer", "hint_text", "learning_outcome"],
        "properties": {
          "question_title": { "type": "string", "minLength": 10, "maxLength": 400 },
          "description": { "type": "string", "minLength": 10, "maxLength": 240 },
          "subconcept": { "type": "string", "minLength": 1, "maxLength": 240 },
          "points": { "const": 1 },
          "multiple_answer": { "const": 0 },
          "hint_text": { "type": ["string", "null"], "maxLength": 200 },
          "learning_outcome": { "type": "array", "minItems": 1, "items": { "type": "string" } },
          "answer": {
            "type": "object",
            "additionalProperties": false,
            "required": ["v", "question_type", "sub_type", "bloom_level", "dok_level", "difficulty", "options", "correct_option", "explanation", "remediation", "knowledge_refs", "ability_ref", "misconception_refs", "estimated_time_seconds"],
            "properties": {
              "v": { "const": "ans-2.0" },
              "question_type": { "const": "mcq" },
              "sub_type": { "enum": ["MCQ", "Assertion-Reason", "Case-Based MCQ"] },
              "competency_ref": { "type": ["string", "null"] },
              "bloom_level": { "enum": ["Remember", "Understand", "Apply", "Analyze", "Evaluate", "Create"] },
              "dok_level": { "enum": [1, 2, 3, 4] },
              "difficulty": { "enum": ["Easy", "Medium", "Hard"] },
              "stimulus": { "type": ["string", "null"], "maxLength": 700 },
              "estimated_time_seconds": { "type": "integer", "minimum": 20, "maximum": 180 },
              "options": {
                "type": "array",
                "minItems": 4,
                "maxItems": 4,
                "items": {
                  "type": "object",
                  "additionalProperties": false,
                  "required": ["label", "text", "is_correct", "distractor_type", "rationale"],
                  "properties": {
                    "label": { "enum": ["A", "B", "C", "D"] },
                    "text": { "type": "string", "minLength": 1, "maxLength": 200 },
                    "is_correct": { "type": "boolean" },
                    "distractor_type": { "enum": ["correct", "misconception", "near_miss", "overgeneral", "plausible"] },
                    "misconception_ref": { "type": ["string", "null"] },
                    "knowledge_ref": { "type": ["string", "null"] },
                    "rationale": { "type": "string", "minLength": 15 }
                  }
                }
              },
              "correct_option": { "enum": ["A", "B", "C", "D"] },
              "explanation": { "type": "string", "minLength": 30 },
              "remediation": { "type": "string", "minLength": 10 },
              "knowledge_refs": { "type": "array", "minItems": 1, "items": { "type": "string" } },
              "ability_ref": { "type": ["string", "null"] },
              "misconception_refs": { "type": "array", "items": { "type": "string" } }
            }
          }
        }
      }
    }
  }
}
SCHEMA;
    }

    protected function narrativeColumnSchema(): string
    {
        return <<<'SCHEMA'
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "type": "object",
  "additionalProperties": false,
  "required": ["semantic_concept_key", "question_type", "rows"],
  "properties": {
    "semantic_concept_key": { "type": "string" },
    "question_type": { "const": "narrative" },
    "underfilled": { "type": "boolean" },
    "reason": { "type": ["string", "null"] },
    "rows": {
      "type": "array",
      "items": {
        "type": "object",
        "additionalProperties": false,
        "required": ["question_title", "description", "subconcept", "points", "multiple_answer", "answer", "hint_text", "learning_outcome"],
        "properties": {
          "question_title": { "type": "string", "minLength": 15, "maxLength": 1200 },
          "description": { "type": "string", "minLength": 10, "maxLength": 240 },
          "subconcept": { "type": "string", "minLength": 1, "maxLength": 240 },
          "points": { "type": "integer", "minimum": 1, "maximum": 6 },
          "multiple_answer": { "const": 0 },
          "hint_text": { "type": ["string", "null"], "maxLength": 200 },
          "learning_outcome": { "type": "array", "minItems": 1, "items": { "type": "string" } },
          "answer": {
            "type": "object",
            "additionalProperties": false,
            "required": ["v", "question_type", "sub_type", "bloom_level", "dok_level", "difficulty", "model_answer", "marking_points", "keywords", "common_errors", "full_credit_threshold", "explanation", "remediation", "knowledge_refs", "ability_ref", "misconception_refs", "estimated_time_seconds"],
            "properties": {
              "v": { "const": "ans-2.0" },
              "question_type": { "const": "narrative" },
              "sub_type": { "enum": ["Very Short Answer", "Short Answer", "Long Answer", "Assertion Reason", "Case Study"] },
              "competency_ref": { "type": ["string", "null"] },
              "bloom_level": { "enum": ["Remember", "Understand", "Apply", "Analyze", "Evaluate", "Create"] },
              "dok_level": { "enum": [1, 2, 3, 4] },
              "difficulty": { "enum": ["Easy", "Medium", "Hard"] },
              "stimulus": { "type": ["string", "null"], "maxLength": 700 },
              "estimated_time_seconds": { "type": "integer", "minimum": 60, "maximum": 900 },
              "sub_parts": {
                "type": ["array", "null"],
                "items": {
                  "type": "object",
                  "additionalProperties": false,
                  "required": ["label", "text", "marks"],
                  "properties": {
                    "label": { "type": "string" },
                    "text": { "type": "string" },
                    "marks": { "type": "integer", "minimum": 1 }
                  }
                }
              },
              "model_answer": { "type": "string", "minLength": 20 },
              "marking_points": {
                "type": "array",
                "minItems": 1,
                "items": {
                  "type": "object",
                  "additionalProperties": false,
                  "required": ["mark", "criterion", "accept", "reject", "knowledge_ref"],
                  "properties": {
                    "mark": { "const": 1 },
                    "criterion": { "type": "string", "minLength": 10 },
                    "accept": { "type": "array", "minItems": 2, "maxItems": 4, "items": { "type": "string" } },
                    "reject": { "type": "array", "minItems": 1, "maxItems": 2, "items": { "type": "string" } },
                    "knowledge_ref": { "type": "string" }
                  }
                }
              },
              "keywords": {
                "type": "array",
                "minItems": 4,
                "maxItems": 8,
                "items": {
                  "type": "object",
                  "additionalProperties": false,
                  "required": ["term", "weight", "synonyms"],
                  "properties": {
                    "term": { "type": "string" },
                    "weight": { "type": "number", "minimum": 0, "maximum": 1 },
                    "synonyms": { "type": "array", "items": { "type": "string" } }
                  }
                }
              },
              "common_errors": {
                "type": "array",
                "items": {
                  "type": "object",
                  "additionalProperties": false,
                  "required": ["erroneous_answer", "mark_ceiling"],
                  "properties": {
                    "misconception_ref": { "type": ["string", "null"] },
                    "erroneous_answer": { "type": "string" },
                    "mark_ceiling": { "type": "integer", "minimum": 0 }
                  }
                }
              },
              "full_credit_threshold": { "type": "integer", "minimum": 1 },
              "explanation": { "type": "string", "minLength": 30 },
              "remediation": { "type": "string", "minLength": 10 },
              "knowledge_refs": { "type": "array", "minItems": 1, "items": { "type": "string" } },
              "ability_ref": { "type": ["string", "null"] },
              "misconception_refs": { "type": "array", "items": { "type": "string" } }
            }
          }
        }
      }
    }
  }
}
SCHEMA;
    }

    /* ----------------------------------------------------------------
     * LLM call
     * ---------------------------------------------------------------- */

    protected function callDeepSeek(string $system, string $user, array $opts): array
    {
        $apiKey = $this->resolveApiKey();
        if (empty($apiKey)) {
            return ['ok' => false, 'error' => 'DeepSeek API key not configured (set DEEPSEEK_API_KEY or ai_api_keys row).'];
        }

        $model = $opts['model'] ?? $this->model;
        $temperature = $opts['temperature'] ?? $this->temperature;
        $seed = $opts['seed'] ?? random_int(1000, 999999);

        $body = [
            'model'    => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
            'temperature' => (float) $temperature,
            'stream'      => false,
        ];

        if ($this->maxTokens > 0) {
            $body['max_tokens'] = $this->maxTokens;
        }

        if (!empty($opts['seed'])) {
            $body['seed'] = (int) $opts['seed'];
        }
        if (!empty(config('deepseek.thinking'))) {
            $body['thinking'] = ['type' => 'enabled'];
        }
        if (!empty(config('deepseek.reasoning_effort'))) {
            $body['reasoning_effort'] = config('deepseek.reasoning_effort');
        }

        try {
            // Ensure PHP doesn't kill the script before the HTTP timeout fires.
            set_time_limit($this->timeout + 60);

            $headers = [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ];

            $response = Http::withHeaders($headers)
                ->timeout($this->timeout)
                ->connectTimeout(20)
                ->post(rtrim(config('deepseek.base_url'), '/') . '/chat/completions', $body);

            if ($response->status() === 402 && filled(config('openrouter.api_key'))) {
                $body['model'] = config('openrouter.model', 'openai/gpt-4o-mini');
                $response = Http::withHeaders(config('openrouter.headers'))
                    ->timeout($this->timeout)
                    ->connectTimeout(20)
                    ->post(rtrim(config('openrouter.base_url'), '/') . '/chat/completions', $body);
            }

            if ($response->status() === 402) {
                // Same shared resolver as the primary key above, so the last-resort
                // provider is scoped to the same school rather than picking whichever
                // gemini row the table returned first — this pool holds two active
                // gemini rows, one of which answers 400 API_KEY_INVALID.
                $geminiKey = app(\App\Domain\AI\Support\ProviderKeyResolver::class)->resolve(
                    'gemini',
                    $this->subInstituteId,
                    config('ai.provider.gemini.api_key'),
                )['api_key'] ?? null;

                if (filled($geminiKey)) {
                    $geminiResponse = Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'x-goog-api-key' => $geminiKey,
                    ])
                        ->timeout($this->timeout)
                        ->connectTimeout(20)
                        ->post(
                            'https://generativelanguage.googleapis.com/v1beta/models/'
                            . config('gemini.model') . ':generateContent',
                            [
                                'contents' => [[
                                    'role' => 'user',
                                    'parts' => [[
                                        'text' => $system . "\n\n" . $user,
                                    ]],
                                ]],
                                'generationConfig' => [
                                    'temperature' => (float) $temperature,
                                    'maxOutputTokens' => $this->maxTokens > 0 ? $this->maxTokens : 8000,
                                ],
                            ]
                        );

                    if ($geminiResponse->successful()) {
                        $geminiParts = $geminiResponse->json('candidates.0.content.parts', []);
                        $content = collect($geminiParts)
                            ->pluck('text')
                            ->filter(fn ($text) => is_string($text))
                            ->implode('');

                        if (trim($content) !== '') {
                            return [
                                'ok' => true,
                                'content' => $content,
                                'model' => config('gemini.model'),
                                'finish_reason' => $geminiResponse->json('candidates.0.finishReason'),
                                'usage' => $geminiResponse->json('usageMetadata', []),
                            ];
                        }
                    }

                    return [
                        'ok' => false,
                        'error' => 'Gemini request failed: ' . $geminiResponse->status() . ' ' . $geminiResponse->body(),
                    ];
                }
            }

            if (!$response->successful()) {
                return [
                    'ok'    => false,
                    'error' => 'DeepSeek request failed: ' . $response->status() . ' ' . $response->body(),
                ];
            }

            $json = $response->json();
            $choice = $json['choices'][0] ?? [];
            $content = $choice['message']['content'] ?? null;
            if ($content === null) {
                return ['ok' => false, 'error' => 'DeepSeek returned an empty message.'];
            }

            return [
                'ok'           => true,
                'content'      => is_string($content) ? $content : json_encode($content, JSON_UNESCAPED_UNICODE),
                'model'        => $json['model'] ?? $model,
                'usage'        => $json['usage'] ?? [],
                'finish_reason'=> $choice['finish_reason'] ?? null,
                'seed'         => $seed,
            ];
        } catch (Throwable $e) {
            Log::error('DeepSeek call failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'DeepSeek call exception: ' . $e->getMessage()];
        }
    }

    /* ----------------------------------------------------------------
     * H5P content-type driven generation (Claude)
     *
     * For a format that has an active H5P content type the selected type chooses the
     * prompt, the JSON Claude returns and the validator. What it hands back is a list of
     * ordinary rows, so everything after the model call -- validateRows(), prepareRow(),
     * dedup, persist() -- is the existing pipeline, unchanged.
     * ---------------------------------------------------------------- */

    /** The H5P content type this format is generated from, or null for the existing path. */
    protected function h5pContentTypeFor(QuestionFormat $format): ?H5pContentType
    {
        $code = $format->persistedFormatCode();

        return $code === null ? null : app(H5pContentTypeRegistry::class)->activeFor($code);
    }

    /** One Claude call, in callDeepSeek()'s return shape. */
    protected function callClaude(string $system, string $user, array $opts): array
    {
        return app(ClaudeQuestionClient::class)->complete($system, $user, $this->subInstituteId);
    }

    /**
     * Each question's slot, in order: the Bloom level, DOK, difficulty and marks the
     * quota assigned to it. Server-owned, so the model is never asked for them.
     *
     * @return list<array{level: string, dok: int, difficulty: string, points: int}>
     */
    protected function h5pSlots(array $quota): array
    {
        $slots = [];
        foreach ($quota as $row) {
            for ($i = 0; $i < (int) ($row['count'] ?? 0); $i++) {
                $slots[] = [
                    'level' => (string) $row['level'],
                    'dok' => (int) $row['dok'],
                    'difficulty' => (string) $row['difficulty'],
                    'points' => max(1, (int) ($row['points'] ?? 1)),
                ];
            }
        }

        return $slots;
    }

    /**
     * The user prompt: the same context the DeepSeek prompts carry (concept slice, dedup
     * corpus, thin-content note, diagnostic stage), the slots to fill, then the selected
     * content type's own rules and schema.
     */
    protected function h5pUserPrompt(H5pContentType $h5p, array $slots, array $slice, array $stems, string $semanticKey, bool $hasContent, ?string $diagnosticStage): string
    {
        $count = count($slots);

        $slotLines = [];
        foreach ($slots as $i => $slot) {
            $slotLines[] = 'Question ' . ($i + 1) . " -> Bloom's level: {$slot['level']}; difficulty: {$slot['difficulty']}";
        }

        return "TASK: Write {$count} {$h5p->taskLabel()} question(s) for the concept below, designed from the start "
            . "as an H5P \"{$h5p->h5pLabel()}\" ({$h5p->h5pType()}) activity.\n\n"
            . "SEMANTIC_CONCEPT_KEY (context only; do not return it): {$semanticKey}\n\n"
            . "## QUESTION SLOTS (binding)\n" . implode("\n", $slotLines) . "\n\n"
            . "## CONCEPT SLICE\n" . json_encode($slice, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n"
            . "## DEDUP CORPUS (do not reproduce or rephrase)\n" . $this->dedupMarkdown($stems) . "\n"
            . $this->bestEffortNote($hasContent)
            . $this->stageInstruction($diagnosticStage)
            . "\n## H5P CONTENT TYPE: " . strtoupper($h5p->h5pLabel()) . "\n\n"
            . trim($h5p->prompt($count))
            . "\n\n## RESPONSE SCHEMA\n" . $h5p->responseSchema()
            . "\n\nReturn ONLY valid JSON matching the schema. Do not return markdown. Do not return anything outside the JSON. "
            . 'Do not wrap the JSON in ```json fences.';
    }

    /**
     * Why Claude's decoded answer is not acceptable for this content type, as a list of
     * reasons (empty when it is). Count, shape, each question, then the set.
     *
     * @return list<string>
     */
    protected function h5pCheckResponse(H5pContentType $h5p, array $data, int $expected): array
    {
        $questions = $data['questions'] ?? null;
        if (!is_array($questions) || !array_is_list($questions)) {
            return ['the top-level object must have a "questions" list'];
        }
        if (count($questions) !== $expected) {
            return ['exactly ' . $expected . ' question(s) are required, got ' . count($questions)];
        }

        $reasons = [];
        foreach ($questions as $i => $question) {
            $reason = is_array($question) ? $h5p->validateQuestion($question) : 'not an object';
            if ($reason !== null) {
                $reasons[] = 'question ' . ($i + 1) . ': ' . $reason;
            }
        }
        if ($reasons !== []) {
            return $reasons;
        }

        $set = $h5p->validateSet($questions);

        return $set === null ? [] : [$set];
    }

    /**
     * One batch for an H5P content type: ask Claude, validate against the type, and on
     * failure ask again with the reasons -- at most config('question_formats.h5p.max_attempts')
     * calls. Nothing is returned unless every question passed.
     *
     * @return array{ok: bool, error?: string, rows?: list<array>, model?: string, usage?: array<string, int>}
     */
    protected function generateH5pBatch(
        H5pContentType $h5p,
        array $batchQuota,
        array $slice,
        array $stems,
        string $semanticKey,
        bool $hasContent,
        ?string $diagnosticStage,
        array $opts
    ): array {
        // Without extracted knowledge there is nothing to ground the questions in, and the
        // system prompt forbids filling the gap from general knowledge. Say so up front
        // instead of spending a call on an answer that cannot pass.
        if (!$hasContent) {
            return ['ok' => false, 'error' => 'This concept has no extracted knowledge items, abilities, misconceptions or outcomes yet, '
                . 'so grounded ' . $h5p->h5pLabel() . ' questions cannot be written. Add concept intelligence for it first.'];
        }

        $slots = $this->h5pSlots($batchQuota);
        $expected = count($slots);
        $attempts = max(1, (int) config('question_formats.h5p.max_attempts', 3));
        $system = H5pPrompts::system();
        $base = $this->h5pUserPrompt($h5p, $slots, $slice, $stems, $semanticKey, $hasContent, $diagnosticStage);

        $usage = ['prompt_tokens' => 0, 'completion_tokens' => 0];
        $model = $opts['model'] ?? null;
        $reasons = [];

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $user = $reasons === [] ? $base : $base . H5pPrompts::retryNotice($reasons, $expected);

            $call = $this->callClaude($system, $user, $opts);
            if (!$call['ok']) {
                // Provider / credential failures are not something a second prompt fixes.
                return ['ok' => false, 'error' => $call['error']];
            }

            $model = $call['model'] ?? $model;
            $usage['prompt_tokens'] += (int) ($call['usage']['prompt_tokens'] ?? 0);
            $usage['completion_tokens'] += (int) ($call['usage']['completion_tokens'] ?? 0);

            if (($call['finish_reason'] ?? null) === 'length') {
                $reasons = ['the response was cut off before the JSON was complete; keep every field short'];
                continue;
            }

            $parsed = $this->parseResponse((string) $call['content']);
            if (!$parsed['ok']) {
                $reasons = ['the response was not a single valid JSON object (' . $parsed['error'] . ')'];
                continue;
            }

            $reasons = $this->h5pCheckResponse($h5p, $parsed['data'], $expected);
            if ($reasons !== []) {
                Log::warning('QuestionGeneration: ' . $h5p->h5pLabel() . " answer rejected (attempt {$attempt}/{$attempts}): " . implode(' | ', $reasons));
                continue;
            }

            $rows = [];
            foreach ($parsed['data']['questions'] as $i => $question) {
                $rows[] = $h5p->toRow($question, $slots[$i], $this->envelopeVersion);
            }

            return ['ok' => true, 'rows' => $rows, 'model' => $model, 'usage' => $usage];
        }

        return ['ok' => false, 'error' => "Claude's {$h5p->h5pLabel()} questions failed validation after {$attempts} attempt(s): "
            . implode('; ', array_slice($reasons, 0, 5))];
    }

    protected function extractJsonCandidate(string $raw): ?string
    {
        $text = trim(preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw);

        if (preg_match('/^```[a-zA-Z0-9_-]*\s*([\s\S]*?)\s*```$/', $text, $m)) {
            $text = trim($m[1]);
        }

        $objectStart = strpos($text, '{');
        $arrayStart = strpos($text, '[');
        if ($objectStart === false && $arrayStart === false) {
            return null;
        }

        $start = $objectStart !== false ? $objectStart : $arrayStart;

        $stack = [];
        $inString = false;
        $escaped = false;
        $length = strlen($text);

        for ($i = $start; $i < $length; $i++) {
            $char = $text[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($char === '"') {
                $inString = true;
                continue;
            }

            if ($char === '{') {
                $stack[] = '}';
                continue;
            }

            if ($char === '[') {
                $stack[] = ']';
                continue;
            }

            if ($char === '}' || $char === ']') {
                if (empty($stack) || end($stack) !== $char) {
                    return null;
                }

                array_pop($stack);
                if (empty($stack)) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    protected function decodeJsonCandidate(string $json): array
    {
        $decoded = json_decode($json, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (is_string($decoded)) {
                $nested = trim($decoded);
                if (str_starts_with($nested, '{') || str_starts_with($nested, '[')) {
                    $decodedAgain = json_decode($nested, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return ['ok' => true, 'data' => $decodedAgain];
                    }
                }
            }

            return ['ok' => true, 'data' => $decoded];
        }

        $originalError = json_last_error_msg();
        $withoutTrailingCommas = preg_replace('/,\s*([}\]])/', '$1', $json);
        if (is_string($withoutTrailingCommas) && $withoutTrailingCommas !== $json) {
            $decoded = json_decode($withoutTrailingCommas, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
            if (json_last_error() === JSON_ERROR_NONE) {
                return ['ok' => true, 'data' => $decoded];
            }
        }

        return ['ok' => false, 'error' => $originalError];
    }

    protected function compactSnippet(string $text, int $limit = 600): string
    {
        $snippet = preg_replace('/\s+/', ' ', trim($text));
        return mb_substr((string) $snippet, 0, $limit);
    }

    protected function parseResponse(string $raw): array
    {
        $candidate = $this->extractJsonCandidate($raw);
        if ($candidate === null) {
            Log::warning('QuestionGeneration LLM response did not contain complete JSON', [
                'snippet' => $this->compactSnippet($raw),
            ]);
            return ['ok' => false, 'error' => 'Could not find a complete JSON object in the DeepSeek response.'];
        }

        $decoded = $this->decodeJsonCandidate($candidate);
        if (!$decoded['ok']) {
            Log::warning('QuestionGeneration LLM JSON decode failed', [
                'error'   => $decoded['error'],
                'snippet' => $this->compactSnippet($candidate),
            ]);
            return ['ok' => false, 'error' => 'Could not decode LLM JSON: ' . $decoded['error']];
        }

        if (!is_array($decoded['data'])) {
            return ['ok' => false, 'error' => 'DeepSeek JSON root must be an object.'];
        }

        return ['ok' => true, 'data' => $decoded['data']];
    }

    /* ----------------------------------------------------------------
     * Validation (gates G1–G10, pragmatic)
     * ---------------------------------------------------------------- */

    protected function validateRows(string $type, array $rows, array $quota, ?QuestionFormat $format = null): array
    {
        $valid = [];
        $skipped = [];

        $rowSchema = ['question_title', 'description', 'subconcept', 'points', 'multiple_answer', 'answer', 'hint_text', 'learning_outcome'];

        foreach ($rows as $i => $row) {
            $reason = null;

            if (!is_array($row)) {
                $reason = "row {$i}: not an object";
            } elseif (count(array_diff($rowSchema, array_keys($row))) > 0) {
                $reason = "row {$i}: missing column keys " . implode(',', array_diff($rowSchema, array_keys($row)));
            } elseif (!is_array($row['answer']) || !isset($row['answer']['bloom_level'], $row['answer']['dok_level'], $row['answer']['difficulty'])) {
                $reason = "row {$i}: answer envelope missing bloom/dok/difficulty";
            } elseif (!in_array($row['answer']['bloom_level'], self::BLOOM_LEVELS, true)) {
                $reason = "row {$i}: invalid bloom_level";
            } elseif (!is_int($row['points']) || $row['points'] < 1) {
                $reason = "row {$i}: points must be int >= 1";
            } elseif ($row['multiple_answer'] !== 0) {
                $reason = "row {$i}: multiple_answer must be 0";
            } elseif (!is_array($row['learning_outcome']) || empty($row['learning_outcome'])) {
                $reason = "row {$i}: learning_outcome must be a non-empty array";
            } elseif ($format !== null && !$format->isLegacy()) {
                // A non-legacy format: the shared checks above have passed, so only
                // what makes THIS format what it claims to be is left to check.
                $reason = $this->formatRowReason($format, $row, $i);
            } else {
                $ans = $row['answer'];
                if ($type === 'mcq') {
                    // CBSE 2025-26 select-response typologies.
                    if (!in_array($ans['sub_type'] ?? null, ['MCQ', 'Assertion-Reason', 'Case-Based MCQ'], true)) {
                        $reason = "row {$i}: mcq sub_type must be MCQ, Assertion-Reason or Case-Based MCQ";
                    } elseif (($ans['sub_type'] ?? null) === 'Case-Based MCQ' && trim((string) ($ans['stimulus'] ?? '')) === '') {
                        $reason = "row {$i}: Case-Based MCQ requires answer.stimulus";
                    } elseif (empty($ans['options']) || count($ans['options']) !== 4) {
                        $reason = "row {$i}: mcq must have exactly 4 options";
                    } elseif (empty($ans['correct_option']) || !in_array($ans['correct_option'], ['A','B','C','D'], true)) {
                        $reason = "row {$i}: invalid correct_option";
                    } elseif (empty($ans['knowledge_refs']) || !is_array($ans['knowledge_refs'])) {
                        $reason = "row {$i}: knowledge_refs required";
                    }
                } else {
                    if (empty($ans['model_answer']) || !is_array($ans['marking_points']) || count($ans['marking_points']) !== $row['points']) {
                        $reason = "row {$i}: marking_points count must equal points";
                    } elseif (empty($ans['keywords']) || count($ans['keywords']) < 4) {
                        $reason = "row {$i}: need >= 4 keywords";
                    }
                }
            }

            if ($reason === null) {
                $valid[] = $row;
            } else {
                $skipped[] = $reason;
                Log::warning('QuestionGeneration skipped row: ' . $reason);
            }
        }

        return ['valid' => $valid, 'skipped' => $skipped];
    }

    /**
     * Why a row is not the requested format, or null when it is.
     *
     * The format is the teacher's choice, never the model's, so a row that says it
     * is anything else is rejected here rather than stored under the wrong form.
     */
    protected function formatRowReason(QuestionFormat $format, array $row, int|string $i): ?string
    {
        $code = $format->responseType();
        $ans = $row['answer'];

        if (($ans['question_type'] ?? null) !== $code) {
            return "row {$i}: answer.question_type must be \"{$code}\", got \"" . (is_scalar($ans['question_type'] ?? null) ? $ans['question_type'] : 'none') . '"';
        }
        if (!in_array($ans['bloom_level'], $format->allowedBloomLevels(), true)) {
            return "row {$i}: {$format->label()} is not written at the {$ans['bloom_level']} level";
        }

        [$minPoints, $maxPoints] = $format->marksRange();
        if ($row['points'] < $minPoints || $row['points'] > $maxPoints) {
            return "row {$i}: points must be between {$minPoints} and {$maxPoints} for {$format->label()}";
        }

        $reason = $format->validateRow($row);

        return $reason === null ? null : "row {$i}: {$reason}";
    }

    /* ----------------------------------------------------------------
     * Persistence (caller-owned + deterministic + LLM-owned fields)
     * ---------------------------------------------------------------- */

    protected function questionPreview(int $id, array $row, string $type): array
    {
        return [
            'id'               => $id,
            'question_type'    => $type,
            'question_title'   => $row['question_title'] ?? '',
            'description'      => $row['description'] ?? '',
            'subconcept'       => $row['subconcept'] ?? '',
            'points'           => $row['points'] ?? null,
            'hint_text'        => $row['hint_text'] ?? null,
            'learning_outcome' => $row['learning_outcome'] ?? [],
            'answer'           => $row['answer'] ?? [],
        ];
    }

    /**
     * Load (once per request) the active DOK and Bloom child rows from
     * lms_mapping_type, keyed by lower-cased name.
     */
    protected function mappingCatalog(): array
    {
        if ($this->mappingCatalog !== null) {
            return $this->mappingCatalog;
        }

        $catalog = ['dok' => [], 'bloom' => []];

        $children = DB::table('lms_mapping_type')
            ->whereIn('parent_id', [self::DOK_MAPPING_TYPE_ID, self::BLOOM_MAPPING_TYPE_ID])
            ->where('status', 1)
            ->get(['id', 'name', 'parent_id']);

        foreach ($children as $child) {
            $bucket = (int) $child->parent_id === self::DOK_MAPPING_TYPE_ID ? 'dok' : 'bloom';
            $catalog[$bucket][mb_strtolower(trim($child->name))] = [
                'id'   => (int) $child->id,
                'name' => trim($child->name),
            ];
        }

        return $this->mappingCatalog = $catalog;
    }

    /**
     * Build lms_question_mapping rows (DOK + Bloom) for one inserted question,
     * derived from the validated answer envelope. Rows follow the existing
     * convention: mapping_type_id = parent group, mapping_value_id = child,
     * reasons = child name.
     */
    protected function buildQuestionMappings(int $questionId, array $answer): array
    {
        $catalog = $this->mappingCatalog();
        $rows = [];

        // DOK: numeric level (1-4) -> teacher-facing label row under parent 9.
        $dok = (int) ($answer['dok_level'] ?? 0);
        if ($dok >= 1) {
            $candidates = self::DOK_LABEL_CANDIDATES[min($dok, 4)] ?? [];
            foreach ($candidates as $candidate) {
                if (isset($catalog['dok'][$candidate])) {
                    $rows[] = [
                        'questionmaster_id' => $questionId,
                        'mapping_type_id'   => self::DOK_MAPPING_TYPE_ID,
                        'mapping_value_id'  => $catalog['dok'][$candidate]['id'],
                        'reasons'           => $catalog['dok'][$candidate]['name'],
                    ];
                    break;
                }
            }
        }

        // Bloom: canonical level -> child row under parent 82. The table uses
        // slightly different spellings (Analyse, Creating), so match on the
        // first four letters: reme/unde/appl/anal/eval/crea are all distinct.
        $bloom = mb_strtolower(trim((string) ($answer['bloom_level'] ?? '')));
        if ($bloom !== '') {
            $prefix = mb_substr($bloom, 0, 4);
            foreach ($catalog['bloom'] as $name => $info) {
                if (mb_substr($name, 0, 4) === $prefix) {
                    $rows[] = [
                        'questionmaster_id' => $questionId,
                        'mapping_type_id'   => self::BLOOM_MAPPING_TYPE_ID,
                        'mapping_value_id'  => $info['id'],
                        'reasons'           => $info['name'],
                    ];
                    break;
                }
            }
        }

        return $rows;
    }

    /**
     * Build answer_master rows for one inserted MCQ from its answer envelope.
     *
     * The envelope keeps the full option objects (rationale, distractor_type,
     * misconception_ref) but the quiz runtime reads options from answer_master
     * only: palController::create() INNER JOINs it to pick questions, and
     * exam.blade.php renders `answer_master.id ## correct_answer` as the radio
     * value that grading later compares against. A generated question with no
     * answer_master rows is therefore invisible to the learner, so materialise
     * them here alongside the envelope rather than only inside the JSON.
     */
    protected function buildAnswerRows(int $questionId, array $answer, array $ctx): array
    {
        $options = $answer['options'] ?? null;
        if (!is_array($options) || empty($options)) {
            return [];
        }

        $rows = [];
        foreach ($options as $option) {
            $text = trim((string) ($option['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            // answer / feedback are varchar(250) — same backstop as description.
            if (mb_strlen($text) > 250) {
                Log::warning("QuestionGeneration: option text truncated for question {$questionId}");
                $text = mb_substr($text, 0, 250);
            }
            $feedback = trim((string) ($option['rationale'] ?? ''));
            $feedback = $feedback === '' ? null : mb_substr($feedback, 0, 250);

            $rows[] = [
                'question_id'      => $questionId,
                'answer'           => $text,
                'feedback'         => $feedback,
                'correct_answer'   => !empty($option['is_correct']) ? 1 : 0,
                'sub_institute_id' => $ctx['sub_institute_id'] ?? null,
                'created_by'       => $ctx['created_by'] ?? null,
                'created_on'       => now(),
            ];
        }

        // Never persist an unanswerable option set: a question whose options all
        // read is_correct=false would be served and then graded wrong for every
        // learner. Drop it back into the JSON-only state and log instead.
        $correct = array_sum(array_column($rows, 'correct_answer'));
        if ($correct < 1) {
            Log::warning("QuestionGeneration: no correct option for question {$questionId}, skipping answer_master");
            return [];
        }

        return $rows;
    }

    /**
     * The lms_question_master insert for one validated row, and the answer
     * envelope it carries: caller-owned columns, the dual-written format code, the
     * generator-known g_* metadata and the LLM-owned fields.
     *
     * Split out of persist() so the stored shape can be tested without a database.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>} [insert row, answer envelope]
     */
    protected function buildMasterRow(array $row, array $resp, array $ctx, array $meta, string $hash): array
    {
        $conceptId = $ctx['concept_id'];
        $questionTypeId = $ctx['question_type_id'];
        $answer = $row['answer'];
        $answer['semantic_concept_key'] = $resp['semantic_concept_key'] ?? $ctx['semantic_concept_key'];
        $answer['v'] = $answer['v'] ?? $this->envelopeVersion;
        $answer['content_hash'] = $hash;
        $answer['generation_meta'] = $meta;
        $answer['times_served'] = 0;
        $answer['times_correct'] = 0;
        $answer['p_value'] = null;
        $answer['discrimination'] = null;

        // Dual-write the selected catalogue form: here (item_form, which PAL and
        // the bank's shape() already read) and in question_format_code below.
        // Both carry the same code. Null for the legacy narrative alias, which
        // is a mixed bag and not a catalogue form.
        if (!empty($ctx['format_code'])) {
            $answer['item_form'] = $ctx['format_code'];
        }

        // mb_substr is a backstop, not a strategy — log if it ever fires.
        $desc = mb_substr((string) $row['description'], 0, 250);
        if (mb_strlen((string) $row['description']) > 250) {
            Log::warning("QuestionGeneration: description truncated for concept {$conceptId}");
        }
        $sub = mb_substr((string) $row['subconcept'], 0, 250);
        if (mb_strlen((string) $row['subconcept']) > 250) {
            Log::warning("QuestionGeneration: subconcept truncated for concept {$conceptId}");
        }

        $insert = [
            // caller-owned — never from the LLM
            'question_type_id' => $questionTypeId,
            'grade_id'         => $ctx['grade_id'] ?? null,
            'standard_id'      => $ctx['standard_id'] ?? null,
            'subject_id'       => $ctx['subject_id'] ?? null,
            'chapter_id'       => $ctx['chapter_id'] ?? null,
            'concept_id'       => $conceptId,
            'sub_institute_id' => $ctx['sub_institute_id'] ?? null,
            'status'           => 1,
            'created_by'       => $ctx['created_by'] ?? null,
            'created_on'       => now(),

            // deterministic from the slice — never from the LLM
            'concept'                      => $ctx['concept_name'],
            'pre_grade_topic'              => $ctx['pre_grade_topic'] ?? null,
            'post_grade_topic'             => $ctx['post_grade_topic'] ?? null,
            'cross_curriculum_grade_topic' => $ctx['cross_curriculum_grade_topic'] ?? null,

            // LLM-owned
            'question_title'   => $row['question_title'],
            'description'      => $desc,
            'subconcept'       => $sub,
            'points'           => $row['points'],
            'multiple_answer'  => 0,
            'hint_text'        => $row['hint_text'],
            'learning_outcome' => json_encode($row['learning_outcome'], JSON_UNESCAPED_UNICODE),
            'answer'           => json_encode($answer, JSON_UNESCAPED_UNICODE),

            // Derived, not LLM-owned: the Question Bank filters on this.
            'category'         => $this->learningFlowCategory($answer, $row, $meta['diagnostic_stage'] ?? null),
        ];

        // The authoritative catalogue form. Only where the column exists, and
        // only for a catalogue form: g_qtype_code (the tagger's) and
        // g_content_hash are deliberately left alone.
        if (!empty($ctx['format_code']) && $this->questionMasterHasColumn('question_format_code')) {
            $insert['question_format_code'] = $ctx['format_code'];
        }

        // What the generator knows for certain, so a generated item is reachable
        // by the difficulty / Bloom / DOK filters the bank and the H5P pickers use
        // instead of waiting for the out-of-band tagger.
        // Each value is clamped to the vocabulary its column holds: g_difficulty is
        // varchar(8), and a stray model value must not fail the whole transaction.
        foreach ([
            'g_bloom'      => in_array($answer['bloom_level'] ?? null, self::BLOOM_LEVELS, true) ? $answer['bloom_level'] : null,
            'g_difficulty' => in_array($answer['difficulty'] ?? null, ['Easy', 'Medium', 'Hard'], true) ? $answer['difficulty'] : null,
            'g_dok'        => in_array((int) ($answer['dok_level'] ?? 0), [1, 2, 3, 4], true) ? (int) $answer['dok_level'] : null,
        ] as $column => $value) {
            if ($value !== null && $this->questionMasterHasColumn($column)) {
                $insert[$column] = $value;
            }
        }

        return [$insert, $answer];
    }

    /**
     * The idempotency check: is there already a question with this content hash for
     * this concept and type?
     *
     * A format-scoped run only collides with its own format: narrative-typed formats
     * share question_type_id 2, and the same words as a different form (a true/false
     * statement and a fill-in-the-blank stem) are not the same question. Legacy MCQ
     * and narrative runs keep the original concept + type + hash check unchanged.
     *
     * Returned unexecuted so the scoping can be asserted from its SQL.
     */
    protected function contentHashQuery(array $ctx, int $conceptId, int $questionTypeId, string $hash): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('lms_question_master')
            ->where('concept_id', $conceptId)
            ->where('question_type_id', $questionTypeId)
            ->whereRaw("JSON_EXTRACT(answer, '$.content_hash') = ?", [$hash]);

        if (!empty($ctx['scope_dedup']) && !empty($ctx['format_code'])) {
            $query->where('question_format_code', $ctx['format_code']);
        }

        return $query;
    }
    protected function persist(array $resp, string $type, array $ctx, array $meta): array
    {
        $insertedIds = [];
        $insertedQuestions = [];
        $mappingRows = [];
        $answerRows = [];
        $skippedDup = 0;
        $conceptId = $ctx['concept_id'];
        $questionTypeId = $ctx['question_type_id'];

        foreach ($resp['rows'] as $row) {
            // C8: normalise, then hash. Collisions are intentional (idempotency).
            $norm = preg_replace('/[^a-z0-9 ]/', '',
                strtolower(preg_replace('/\s+/', ' ', trim($row['question_title']))));
            $hash = hash('sha256', $norm);

            $exists = $this->contentHashQuery($ctx, $conceptId, $questionTypeId, $hash)->exists();

            if ($exists) {
                $skippedDup++;
                continue;
            }

            [$insert, $answer] = $this->buildMasterRow($row, $resp, $ctx, $meta, $hash);

            $id = DB::table('lms_question_master')->insertGetId($insert);

            if ($id) {
                $insertedIds[] = $id;
                // The preview reports the form the teacher asked for; the legacy
                // narrative alias has none and keeps reporting 'narrative'.
                $insertedQuestions[] = $this->questionPreview((int) $id, $row, $ctx['format_code'] ?? $type);
                // Auto-tag DOK + Bloom in lms_question_mapping from the answer envelope.
                $mappingRows = array_merge($mappingRows, $this->buildQuestionMappings((int) $id, $answer));
                // Materialise the options the quiz runtime actually reads.
                $answerRows = array_merge($answerRows, $this->buildAnswerRows((int) $id, $answer, $ctx));
            }
        }

        if (!empty($mappingRows)) {
            DB::table('lms_question_mapping')->insert($mappingRows);
        }

        if (!empty($answerRows)) {
            DB::table('answer_master')->insert($answerRows);
        }

        return [
            'inserted'          => count($insertedIds),
            'skipped_duplicate' => $skippedDup,
            'ids'               => $insertedIds,
            'questions'         => $insertedQuestions,
            'mappings_inserted' => count($mappingRows),
            'answers_inserted'  => count($answerRows),
        ];
    }

    /** The one DB transaction a run writes in. A seam so tests can run generate() with no database. */
    protected function runInTransaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }

    /** Whether lms_question_master has this column; cached for the life of the service. */
    protected function questionMasterHasColumn(string $column): bool
    {
        static $cache = [];

        return $cache[$column] ??= Schema::hasColumn('lms_question_master', $column);
    }

    protected function questionBatchSize(string $type): int
    {
        $key = $type === 'narrative'
            ? 'deepseek.batch_size_narrative'
            : 'deepseek.batch_size_mcq';

        return max(1, (int) config($key, $type === 'narrative' ? 1 : 2));
    }

    protected function quotaCount(array $quota): int
    {
        return array_sum(array_map(fn($row) => (int) ($row['count'] ?? 0), $quota));
    }

    protected function splitQuotaIntoBatches(array $quota, int $batchSize): array
    {
        $batches = [];
        $current = [];
        $currentCount = 0;

        foreach ($quota as $row) {
            $remaining = (int) ($row['count'] ?? 0);
            if ($remaining <= 0) {
                continue;
            }

            while ($remaining > 0) {
                $space = $batchSize - $currentCount;
                if ($space <= 0) {
                    $batches[] = $current;
                    $current = [];
                    $currentCount = 0;
                    $space = $batchSize;
                }

                $take = min($remaining, $space);
                $batchRow = $row;
                $batchRow['count'] = $take;
                $current[] = $batchRow;
                $currentCount += $take;
                $remaining -= $take;

                if ($currentCount >= $batchSize) {
                    $batches[] = $current;
                    $current = [];
                    $currentCount = 0;
                }
            }
        }

        if (!empty($current)) {
            $batches[] = $current;
        }

        return $batches;
    }

    /* ----------------------------------------------------------------
     * Public entry point
     * ---------------------------------------------------------------- */

    /**
     * PAL learning-flow category for a generated item.
     *
     * The prompt pack is quota-driven (Bloom x count) and says nothing about the
     * learning journey, so the category is DERIVED from what the item turned out
     * to be, and stored on the question so the Question Bank can filter on it.
     *
     * The two prerequisite categories are deliberately unreachable here. System
     * prompt rule 4 forbids testing prerequisites ("The learner already knows the
     * listed prerequisites. Do not test them."), so no item this service produces
     * can honestly carry them.
     */
    protected function learningFlowCategory(array $answer, array $row, ?string $diagnosticStage = null): string
    {
        if ($diagnosticStage !== null) {
            return $diagnosticStage;
        }

        $bloom   = (string) ($answer['bloom_level'] ?? 'Understand');
        $subType = (string) ($answer['sub_type'] ?? '');
        $options = (array) ($answer['options'] ?? []);

        // How many distractors were actually built on a misconception. The count
        // matters, not the presence: system prompt rule 6 asks EVERY item at
        // Understand or above to target a misconception, so `misconception_refs`
        // is non-empty on all of them and cannot separate anything. An option
        // grid built around two or more RIVAL misconceptions is a genuine
        // misconception probe; one built around a single one is a concept check.
        $misconceptionDistractors = 0;
        foreach ($options as $option) {
            if (($option['distractor_type'] ?? null) === 'misconception') {
                $misconceptionDistractors++;
            }
        }

        // Bloom is the primary axis, because the quota ladder ties it to
        // difficulty, DOK and marks — so it is the one field that reliably
        // separates a placement item from a mastery task.
        if (str_contains($subType, 'Case') || $bloom === 'Create') {
            return 'mastery_reverification';
        }
        if ($bloom === 'Evaluate') {
            return 'mastery_check';
        }
        if ($bloom === 'Analyze') {
            return 'adaptive_test';
        }
        if ($bloom === 'Remember') {
            return 'adaptive_diagnostic';
        }
        if (!empty($options)) {
            return $misconceptionDistractors >= 2 ? 'misconception_detection' : 'concept_diagnostic';
        }
        if ($bloom === 'Apply') {
            return 'adaptive_test';
        }
        return 'concept_understanding';
    }

    /**
     * Generate several formats in one request.
     *
     * Every selected format is validated first, through the same rules a single
     * `question_format_code` meets (implemented AND catalogued), so one bad code
     * rejects the request before any model call. The total is then split evenly
     * across the formats (QuestionFormatRegistry::distribute) and each share is run
     * through the ordinary single-format generate(), which is why concept lookup, the
     * tenant check, the per-format Bloom quota, dedup and persistence behave exactly
     * as they do for one format.
     *
     * A format that fails does not discard the others: each persists in its own
     * transaction (generate() already does), and the response reports every format's
     * outcome. The request fails only when NO format produced anything, or when the
     * tenant check refuses it (a 403 is never softened into a partial result).
     */
    protected function generateMany(array $input): array
    {
        $codes = is_array($input['question_format_codes']) ? array_values($input['question_format_codes']) : [];

        $errors = null;
        $formats = $this->formats()->resolveMany($codes, $errors);
        if ($formats === []) {
            return $this->fail(implode(' ', $errors ?: ['Select at least one question format.']));
        }

        $total = (int) ($input['total_questions'] ?? 0);
        if ($total <= 0) {
            return $this->fail('concept_id and total_questions are required and must be positive.');
        }
        if ($total > QuestionFormatRegistry::MAX_QUESTIONS) {
            return $this->fail('total_questions may not exceed ' . QuestionFormatRegistry::MAX_QUESTIONS . '.');
        }
        if (count($formats) > 1 && !empty($input['quota'])) {
            return $this->fail('A custom Bloom mix can only be used with a single question format. Remove the quota or select one format.');
        }
        if ($total < count($formats)) {
            return $this->fail(
                "total_questions ({$total}) must be at least the number of selected formats (" . count($formats) . ').'
            );
        }

        $split = $this->formats()->distribute($total, $formats);

        $entries = [];
        $results = [];
        foreach ($formats as $format) {
            $code = $format->code();
            $count = $split[$code];

            $sub = $input;
            unset($sub['question_format_codes'], $sub['question_type']);
            $sub['question_format_code'] = $code;
            $sub['total_questions'] = $count;

            $result = $this->generate($sub);

            // A tenant refusal applies to the whole request, whatever the format.
            if (!$result['status'] && ($result['code'] ?? null) === 'forbidden') {
                return $result;
            }

            $results[$code] = $result;
            $data = $result['data'] ?? [];
            $entries[] = [
                'question_format_code' => $code,
                'label'                => $this->formats()->catalogue()->find($code)['label'] ?? $format->label(),
                'question_type_id'     => $data['question_type_id'] ?? $this->formats()->questionTypeIdFor($format),
                'status'               => (bool) $result['status'],
                'message'              => $result['message'] ?? '',
                'requested'            => (int) ($data['requested'] ?? $count),
                'generated'            => (int) ($data['generated'] ?? 0),
                'inserted'             => (int) ($data['inserted'] ?? 0),
                'skipped_duplicate'    => (int) ($data['skipped_duplicate'] ?? 0),
                'skipped_invalid'      => (int) ($data['skipped_invalid'] ?? 0),
            ];
        }

        $succeeded = array_filter($results, fn (array $r) => $r['status']);
        if ($succeeded === []) {
            $reasons = [];
            foreach ($entries as $entry) {
                $reasons[] = "{$entry['label']}: {$entry['message']}";
            }

            return [
                'status'  => false,
                'message' => 'No question could be generated. ' . implode(' | ', $reasons),
                'code'    => null,
                'data'    => ['question_format_codes' => array_column($entries, 'question_format_code'), 'formats' => $entries],
            ];
        }

        $sum = function (string $key) use ($succeeded): int {
            return array_sum(array_map(fn (array $r) => (int) ($r['data'][$key] ?? 0), $succeeded));
        };

        $questions = [];
        $questionIds = [];
        $invalidReasons = [];
        $reasons = [];
        $missingSlice = false;
        $failed = [];
        foreach ($results as $code => $result) {
            if (!$result['status']) {
                $failed[] = $code;
                continue;
            }
            $data = $result['data'];
            $questions = array_merge($questions, $data['questions'] ?? []);
            $questionIds = array_merge($questionIds, $data['question_ids'] ?? []);
            foreach ($data['invalid_reasons'] ?? [] as $reason) {
                $invalidReasons[] = "{$code}: {$reason}";
            }
            if (!empty($data['reason'])) {
                $reasons[] = "{$code}: {$data['reason']}";
            }
            $missingSlice = $missingSlice || !empty($data['missing_slice']);
        }

        $requested = array_sum(array_column($entries, 'requested'));
        $generated = array_sum(array_column($entries, 'generated'));
        $underfilled = $failed !== [] || $generated < $requested
            || array_filter($succeeded, fn (array $r) => !empty($r['data']['underfilled'])) !== [];

        $first = reset($succeeded)['data'];
        $single = count($formats) === 1;

        if ($failed !== []) {
            $names = [];
            foreach ($entries as $entry) {
                if (!$entry['status']) {
                    $names[] = $entry['label'];
                }
            }
            $message = "Generated {$generated} of {$requested} - " . implode(', ', $names) . ' could not be generated.';
        } elseif ($underfilled) {
            $message = "Generated {$generated} of {$requested} - concept intelligence may be thin.";
        } else {
            $message = 'Questions generated successfully.';
        }

        return [
            'status'  => true,
            'message' => $message,
            'data'    => [
                'semantic_concept_key'  => $first['semantic_concept_key'] ?? null,
                'question_type'         => $single ? $formats[0]->persistedFormatCode() : null,
                'question_format_code'  => $single ? $formats[0]->persistedFormatCode() : null,
                'question_format_codes' => array_column($entries, 'question_format_code'),
                'question_type_id'      => $single ? ($entries[0]['question_type_id'] ?? null) : null,
                'requested'             => $requested,
                'generated'             => $generated,
                'inserted'              => $sum('inserted'),
                'mappings_inserted'     => $sum('mappings_inserted'),
                'answers_inserted'      => $sum('answers_inserted'),
                'skipped_duplicate'     => $sum('skipped_duplicate'),
                'skipped_invalid'       => $sum('skipped_invalid'),
                'invalid_reasons'       => $invalidReasons,
                'underfilled'           => $underfilled,
                'reason'                => $reasons !== [] ? implode(' | ', $reasons) : null,
                'question_ids'          => $questionIds,
                'questions'             => $questions,
                'model'                 => $first['model'] ?? null,
                'batches'               => $sum('batches'),
                'input_tokens'          => $sum('input_tokens'),
                'output_tokens'         => $sum('output_tokens'),
                'missing_slice'         => $missingSlice,
                'formats'               => $entries,
            ],
        ];
    }
    public function generate(array $input): array
    {
        // Several formats in one request: validated together, split evenly, then each
        // share runs through the ordinary single-format path below.
        if (array_key_exists('question_format_codes', $input) && $input['question_format_codes'] !== null) {
            return $this->generateMany($input);
        }

        // The format is the teacher's choice: question_format_code, or the legacy
        // question_type alias. The model never picks it.
        $formatError = null;
        $format = $this->formats()->resolveRequest($input, $formatError);
        if ($format === null) {
            return $this->fail($formatError ?? 'Unsupported question format.');
        }
        $type = $format->engine();
        $diagnosticStage = $input['diagnostic_stage'] ?? null;
        if ($diagnosticStage !== null && !in_array($diagnosticStage, [
            'prerequisite_concept_check',
            'adaptive_diagnostic',
            'concept_diagnostic',
        ], true)) {
            return $this->fail('diagnostic_stage must be a supported ESO stage.');
        }
        $conceptId = (int) ($input['concept_id'] ?? 0);
        $subInstituteId = $input['sub_institute_id'] ?? null;
        // Resolved server-side from the catalogue's lms_question_type_id. A
        // client-supplied question_type_id is never read: historic rows carry ids
        // such as 4, 7 and 8 precisely because it used to be trusted.
        $questionTypeId = $this->formats()->questionTypeIdFor($format);
        $total = (int) ($input['total_questions'] ?? 0);
        $h5p = $this->h5pContentTypeFor($format);
        if ($h5p !== null) {
            // An H5P content type is written in a fixed, server-owned number of questions.
            // The client's total (already split across formats) and any custom Bloom mix
            // are not used for it: the count is the same for every caller.
            $total = (int) app(H5pContentTypeRegistry::class)->questionsPerType();
            unset($input['quota']);
        }
        $chapterId = (int) ($input['chapter_id'] ?? 0);
        $subjectId = (int) ($input['subject_id'] ?? 0);
        $standardId = (int) ($input['standard_id'] ?? 0);

        if ($conceptId <= 0 || $questionTypeId <= 0 || $total <= 0) {
            return $this->fail('concept_id and total_questions are required and must be positive.');
        }
        if ($total > QuestionFormatRegistry::MAX_QUESTIONS) {
            return $this->fail('total_questions may not exceed ' . QuestionFormatRegistry::MAX_QUESTIONS . '.');
        }

        $slice = $this->loadConceptSlice(
            $conceptId,
            $subInstituteId,
            $chapterId > 0 ? $chapterId : null,
            $subjectId > 0 ? $subjectId : null,
            $standardId > 0 ? $standardId : null
        );
        if (!$slice['found']) {
            return $this->fail("Concept {$conceptId} not found.");
        }

        // Tenant ownership. loadConceptSlice() already pins the concept to the
        // caller's chapter/subject/standard, so a mismatched curriculum id fails
        // as "not found" above - but nothing compared the concept's OWNING
        // school to the caller's. Without this, a teacher at school A could
        // spend school A's LLM budget generating against school B's concept and
        // file the result under school A. lms_concept.sub_institute_id is NOT
        // NULL, so this is always a real comparison.
        $conceptTenantId = $this->nullableInt($slice['concept']->sub_institute_id ?? null);
        $callerTenantId  = $this->nullableInt($subInstituteId);

        if ($callerTenantId === null) {
            return $this->fail('No tenant on the request. This endpoint requires an authenticated session.', 'forbidden');
        }

        if ($conceptTenantId !== null && $conceptTenantId !== $callerTenantId) {
            Log::warning('QuestionGeneration: cross-tenant attempt', [
                'concept_id'       => $conceptId,
                'concept_tenant'   => $conceptTenantId,
                'caller_tenant'    => $callerTenantId,
                'caller_user_id'   => $input['created_by'] ?? null,
            ]);

            return $this->fail("Concept {$conceptId} does not belong to your institute.", 'forbidden');
        }

        $conceptName = $slice['concept']->name ?? 'CONCEPT';
        $semanticKey = $this->semanticConceptKey($conceptId, $conceptName);
        $conceptIntelligence = $this->buildConceptIntelligence($slice);
        $builtSlice = $this->buildConceptSlice($slice, $conceptIntelligence);
        $sliceHasContent = $this->sliceHasContent($builtSlice);

        if ($format->isLegacy()) {
            $quota = $this->buildQuota($type, $total, $input, $slice, $conceptIntelligence);
        } else {
            try {
                $quota = $this->buildFormatQuota(
                    $format,
                    $total,
                    $input,
                    $slice,
                    $conceptIntelligence,
                    $this->formats()->defaultMarksFor($format)
                );
            } catch (\InvalidArgumentException $e) {
                return $this->fail($e->getMessage());
            }
        }
        if (empty($quota)) {
            return $this->fail('Could not build a quota (check explicit quota or total_questions).');
        }
        if ($this->quotaCount($quota) > QuestionFormatRegistry::MAX_QUESTIONS) {
            return $this->fail('The quota may not total more than ' . QuestionFormatRegistry::MAX_QUESTIONS . ' questions.');
        }

        $stems = $this->buildDedupCorpus(
            $conceptId,
            $questionTypeId,
            200,
            $format->scopesDedupByFormat() ? $format->persistedFormatCode() : null
        );

        $system = $this->systemPrompt();

        // Model and temperature are server-owned. They are not read from $input
        // even if a caller manages to smuggle them past the validator: a
        // client-selected model is a direct spend vector on the tenant's key.
        $opts = [
            'model'       => $this->model,
            // The legacy formats read the same config/deepseek.php keys as before;
            // every other format carries its own temperature.
            'temperature' => $format->temperature(),
            'seed'        => $input['seed'] ?? null,
        ];

        $quotaBatches = $this->splitQuotaIntoBatches($quota, $format->batchSize());
        if (empty($quotaBatches)) {
            return $this->fail('Could not split quota into generation batches.');
        }

        $rows = [];
        $invalidReasons = [];
        $underfilled = false;
        $reasons = [];
        $modelUsed = $opts['model'];
        $inputTokens = 0;
        $outputTokens = 0;

        foreach ($quotaBatches as $batchIndex => $batchQuota) {
            $batchNumber = $batchIndex + 1;
            $batchOpts = $opts;
            if (isset($input['seed'])) {
                $batchOpts['seed'] = ((int) $input['seed']) + $batchIndex;
            }

            // Legacy formats keep their prompt exactly as it was; every other format
            // is built from its own construction rules and schema.
            $user = $format->isLegacy()
                ? $this->userPrompt(
                    $type,
                    $batchQuota,
                    $builtSlice,
                    $stems,
                    $semanticKey,
                    $sliceHasContent,
                    $input['diagnostic_stage'] ?? null
                )
                : $this->formatUserPrompt(
                    $format,
                    $batchQuota,
                    $builtSlice,
                    $stems,
                    $semanticKey,
                    $sliceHasContent,
                    $input['diagnostic_stage'] ?? null
                );
            if ($h5p !== null) {
                // The selected H5P content type writes its own prompt and validates its own
                // JSON; what comes back is ordinary rows, re-encoded below so the existing
                // parse, row validation, dedup and persistence run on them unchanged.
                $produced = $this->generateH5pBatch(
                    $h5p,
                    $batchQuota,
                    $builtSlice,
                    $stems,
                    $semanticKey,
                    $sliceHasContent,
                    $input['diagnostic_stage'] ?? null,
                    $batchOpts
                );
                if (!$produced['ok']) {
                    return $this->fail("Batch {$batchNumber} failed: " . ($produced['error'] ?? 'no questions could be produced.'));
                }
                $call = ['ok' => true, 'finish_reason' => 'stop', 'model' => $produced['model'], 'usage' => $produced['usage'], 'content' => json_encode([
                    'question_type' => $format->responseType(),
                    'rows'          => $produced['rows'],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
            } elseif ($format instanceof SourcesOwnRows) {
                // The model cannot write this form (it needs a picture and places in it),
                // so the format sources its own rows. They rejoin the ordinary path here:
                // the same validation, de-duplication and persistence follow.
                $sourced = $format->sourceRows($batchQuota, $builtSlice, [
                    'concept_id'       => $conceptId,
                    'concept_name'     => $conceptName,
                    'sub_institute_id' => $subInstituteId,
                ]);
                if (!$sourced['ok']) {
                    return $this->fail("Batch {$batchNumber} failed: " . ($sourced['error'] ?? 'no rows could be produced.'));
                }
                $call = ['ok' => true, 'finish_reason' => 'stop', 'model' => 'vision', 'usage' => [], 'content' => json_encode([
                    'question_type' => $format->responseType(),
                    'rows'          => $sourced['rows'] ?? [],
                    'underfilled'   => $sourced['underfilled'] ?? false,
                    'reason'        => $sourced['reason'] ?? null,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
            } else {
                $call = $this->callDeepSeek($system, $user, $batchOpts);
            }
            if (!$call['ok']) {
                return $this->fail("Batch {$batchNumber} failed: {$call['error']}");
            }
            if (($call['finish_reason'] ?? null) === 'length') {
                return $this->fail(
                    "Batch {$batchNumber} DeepSeek response was truncated before valid JSON. "
                    . "The local max_tokens cap is disabled by default; reduce DEEPSEEK_BATCH_SIZE_" . strtoupper($type)
                    . " if the provider/model still reaches its own output limit."
                );
            }

            $parsed = $this->parseResponse($call['content']);
            if (!$parsed['ok']) {
                return $this->fail("Batch {$batchNumber} failed: {$parsed['error']}");
            }

            $resp = $parsed['data'];
            if (!isset($resp['rows']) || !is_array($resp['rows'])) {
                return $this->fail("Batch {$batchNumber} LLM response missing \"rows\".");
            }
            // The format is the teacher's, not the model's: a response that names any
            // other form is refused whole, and each row repeats the check in
            // formatRowReason(). For the legacy formats this is the same comparison as
            // ever, since their responseType() is 'mcq' / 'narrative'.
            if (($resp['question_type'] ?? null) !== $format->responseType()) {
                return $this->fail('LLM returned question_type "' . ($resp['question_type'] ?? '') . '" but "' . $format->responseType() . '" was requested.');
            }

            $validated = $this->validateRows($type, $resp['rows'], $batchQuota, $format);
            $validRows = array_slice($validated['valid'], 0, $this->quotaCount($batchQuota));
            if (!$format->isLegacy()) {
                $validRows = array_map(fn (array $row) => $format->prepareRow($row), $validRows);
            }
            if (empty($validRows)) {
                return $this->fail("Batch {$batchNumber} returned no valid rows for the prompt-pack schema.");
            }

            $rows = array_merge($rows, $validRows);
            $stems = array_merge($stems, array_column($validRows, 'question_title'));
            $invalidReasons = array_merge($invalidReasons, $validated['skipped']);
            $underfilled = $underfilled || !empty($resp['underfilled']);
            if (!empty($resp['reason'])) {
                $reasons[] = "Batch {$batchNumber}: {$resp['reason']}";
            }

            $modelUsed = $call['model'] ?? $modelUsed;
            $inputTokens += (int) ($call['usage']['prompt_tokens'] ?? 0);
            $outputTokens += (int) ($call['usage']['completion_tokens'] ?? 0);
        }

        if (empty($rows)) {
            return $this->fail('DeepSeek returned no valid rows for the prompt-pack schema.');
        }

        $meta = [
            'model'           => $modelUsed,
            'temperature'     => $opts['temperature'],
            'seed'            => $opts['seed'] ?? null,
            'prompt_version'  => $this->promptVersion,
            // Additive: which catalogue form was asked for and the version of its
            // own prompt block. prompt_version above stays the system prompt's.
            'format_code'     => $format->persistedFormatCode(),
            'format_prompt_version' => $format->isLegacy() ? $this->promptVersion : $format->promptVersion(),
            'batch_id'        => (string) \Illuminate\Support\Str::uuid(),
            'input_tokens'    => $inputTokens,
            'output_tokens'   => $outputTokens,
            'diagnostic_stage' => $input['diagnostic_stage'] ?? null,
        ];
        if ($h5p !== null) {
            // Additive, and only for H5P-driven runs: which H5P content type shaped the item.
            $meta['h5p_type'] = $h5p->h5pType();
        }

        $ctx = $this->buildPersistenceContext($slice, $input, $questionTypeId, $semanticKey, $format);
        $resp = [
            'semantic_concept_key' => $semanticKey,
            'question_type'        => $type,
            'rows'                 => $rows,
        ];

        try {
            $persist = $this->runInTransaction(fn() => $this->persist($resp, $type, $ctx, $meta));
        } catch (Throwable $e) {
            Log::error('QuestionGeneration persist failed: ' . $e->getMessage());
            return $this->fail('Insert failed: ' . $e->getMessage());
        }

        $generated = count($rows);
        $requested = $this->quotaCount($quota);
        $underfilled = $underfilled || $generated < $requested;

        return [
            'status'  => true,
            'message' => $underfilled
                ? "Generated {$generated} of {$requested} — concept intelligence may be thin."
                : 'Questions generated successfully.',
            'data' => [
                'semantic_concept_key'  => $semanticKey,
                'question_type'         => $format->persistedFormatCode() ?? $type,
                'question_format_code'  => $format->persistedFormatCode(),
                'question_type_id'      => $questionTypeId,
                'requested'             => $requested,
                'generated'             => $generated,
                'inserted'              => $persist['inserted'],
                'mappings_inserted'     => $persist['mappings_inserted'] ?? 0,
                'answers_inserted'      => $persist['answers_inserted'] ?? 0,
                'skipped_duplicate'     => $persist['skipped_duplicate'],
                'skipped_invalid'       => count($invalidReasons),
                'invalid_reasons'       => $invalidReasons,
                'underfilled'           => $underfilled,
                'reason'                => !empty($reasons) ? implode(' | ', $reasons) : null,
                'question_ids'          => $persist['ids'],
                'questions'             => $persist['questions'],
                'model'                 => $modelUsed,
                'batches'               => count($quotaBatches),
                'input_tokens'          => $meta['input_tokens'],
                'output_tokens'         => $meta['output_tokens'],
                'missing_slice'         => !$sliceHasContent,
            ],
        ];
    }

    /**
     * @param string|null $code Machine-readable reason. 'forbidden' is mapped to
     *                          HTTP 403 by the controller; everything else 422.
     */
    protected function fail(string $message, ?string $code = null): array
    {
        return ['status' => false, 'message' => $message, 'code' => $code, 'data' => []];
    }
}
