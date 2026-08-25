<?php

namespace App\Services;

use App\Models\lms\lmsQuestionMasterModel;
use Illuminate\Support\Facades\DB;
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

    public function __construct()
    {
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

    protected function resolveApiKey(): ?string
    {
        // 1. ai_api_keys table (matches existing getAIKey() pattern).
        if (function_exists('getAIKey')) {
            $row = getAIKey(config('deepseek.api_type', 'DEEPSEEK_API_KEY'), 1);
            if (!empty($row) && !empty($row->api_key) && $row->api_key !== '-') {
                return $row->api_key;
            }
        } elseif (class_exists(\Illuminate\Support\Facades\DB::class)) {
            $row = DB::table('ai_api_keys')
                ->where('api_type', config('deepseek.api_type', 'DEEPSEEK_API_KEY'))
                ->where('status', 1)
                ->first();
            if (!empty($row) && !empty($row->api_key) && $row->api_key !== '-') {
                return $row->api_key;
            }
        }

        // 2. Environment variable.
        $envKey = config('deepseek.api_key');
        return !empty($envKey) ? $envKey : null;
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

    protected function buildDedupCorpus(int $conceptId, int $questionTypeId, int $limit = 200): array
    {
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
        string $semanticKey
    ): array {
        $concept = $slice['concept'];
        $chapter = $slice['chapter'] ?? null;

        return [
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

    protected function userPrompt(string $type, array $quota, array $slice, array $stems, string $semanticKey, bool $hasContent = true): string
    {
        $total = array_sum(array_column($quota, 'count'));
        $quotaTable = $this->quotaTableMarkdown($quota, $type);
        $dedup = $this->dedupMarkdown($stems);
        $sliceJson = json_encode($slice, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $schema = $type === 'mcq' ? $this->mcqColumnSchema() : $this->narrativeColumnSchema();
        $taskType = $type === 'mcq' ? 'multiple-choice' : 'narrative (constructed-response)';

        $bestEffortNote = '';
        if (!$hasContent) {
            $bestEffortNote = "\nNOTE: The structured CONCEPT SLICE for this concept is empty in our records "
                . "(no extracted knowledge_items / abilities / misconceptions). Return fewer rows or no rows and "
                . "set `underfilled` true with a clear reason. Do not use general knowledge to fill the batch.\n";
        }

        $common = <<<PROMPT
TASK: Write {$total} {$taskType} rows for the concept below.

SEMANTIC_CONCEPT_KEY (echo it back unchanged in your response): {$semanticKey}

## QUOTA TABLE (binding)
{$quotaTable}

## CONCEPT SLICE
{$sliceJson}

## DEDUP CORPUS (do not reproduce or rephrase)
{$dedup}
{$bestEffortNote}
PROMPT;

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

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])
            ->timeout($this->timeout)
            ->connectTimeout(20)
            ->post(rtrim(config('deepseek.base_url'), '/') . '/chat/completions', $body);

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

    protected function validateRows(string $type, array $rows, array $quota): array
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

            $exists = DB::table('lms_question_master')
                ->where('concept_id', $conceptId)
                ->where('question_type_id', $questionTypeId)
                ->whereRaw("JSON_EXTRACT(answer, '$.content_hash') = ?", [$hash])
                ->exists();

            if ($exists) {
                $skippedDup++;
                continue;
            }

            $answer = $row['answer'];
            $answer['semantic_concept_key'] = $resp['semantic_concept_key'] ?? $ctx['semantic_concept_key'];
            $answer['v'] = $answer['v'] ?? $this->envelopeVersion;
            $answer['content_hash'] = $hash;
            $answer['generation_meta'] = $meta;
            $answer['times_served'] = 0;
            $answer['times_correct'] = 0;
            $answer['p_value'] = null;
            $answer['discrimination'] = null;

            // mb_substr is a backstop, not a strategy — log if it ever fires.
            $desc = mb_substr((string) $row['description'], 0, 250);
            if (mb_strlen((string) $row['description']) > 250) {
                Log::warning("QuestionGeneration: description truncated for concept {$conceptId}");
            }
            $sub = mb_substr((string) $row['subconcept'], 0, 250);
            if (mb_strlen((string) $row['subconcept']) > 250) {
                Log::warning("QuestionGeneration: subconcept truncated for concept {$conceptId}");
            }

            $id = DB::table('lms_question_master')->insertGetId([
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
            ]);

            if ($id) {
                $insertedIds[] = $id;
                $insertedQuestions[] = $this->questionPreview((int) $id, $row, $type);
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

    public function generate(array $input): array
    {
        $type = strtolower((string) ($input['question_type'] ?? ''));
        if (!in_array($type, ['mcq', 'narrative'], true)) {
            return $this->fail('question_type must be "mcq" or "narrative".');
        }

        $conceptId = (int) ($input['concept_id'] ?? 0);
        $subInstituteId = $input['sub_institute_id'] ?? null;
        $questionTypeId = (int) ($input['question_type_id'] ?? 0);
        $total = (int) ($input['total_questions'] ?? 0);
        $chapterId = (int) ($input['chapter_id'] ?? 0);
        $subjectId = (int) ($input['subject_id'] ?? 0);
        $standardId = (int) ($input['standard_id'] ?? 0);

        if ($conceptId <= 0 || $questionTypeId <= 0 || $total <= 0) {
            return $this->fail('concept_id, question_type_id and total_questions are required and must be positive.');
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

        $conceptName = $slice['concept']->name ?? 'CONCEPT';
        $semanticKey = $this->semanticConceptKey($conceptId, $conceptName);
        $conceptIntelligence = $this->buildConceptIntelligence($slice);
        $builtSlice = $this->buildConceptSlice($slice, $conceptIntelligence);
        $sliceHasContent = $this->sliceHasContent($builtSlice);

        $quota = $this->buildQuota($type, $total, $input, $slice, $conceptIntelligence);
        if (empty($quota)) {
            return $this->fail('Could not build a quota (check explicit quota or total_questions).');
        }

        $stems = $this->buildDedupCorpus($conceptId, $questionTypeId);

        $system = $this->systemPrompt();

        $opts = [
            'model'       => $input['model'] ?? $this->model,
            'temperature' => $input['temperature'] ?? ($type === 'narrative'
                ? config('deepseek.temperature_narrative', 0.6)
                : config('deepseek.temperature_mcq', 0.4)),
            'seed'        => $input['seed'] ?? null,
        ];

        $quotaBatches = $this->splitQuotaIntoBatches($quota, $this->questionBatchSize($type));
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

            $user = $this->userPrompt($type, $batchQuota, $builtSlice, $stems, $semanticKey, $sliceHasContent);
            $call = $this->callDeepSeek($system, $user, $batchOpts);
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
            if (($resp['question_type'] ?? null) !== $type) {
                return $this->fail('LLM returned question_type "' . ($resp['question_type'] ?? '') . '" but "' . $type . '" was requested.');
            }

            $validated = $this->validateRows($type, $resp['rows'], $batchQuota);
            $validRows = array_slice($validated['valid'], 0, $this->quotaCount($batchQuota));
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
            'batch_id'        => (string) \Illuminate\Support\Str::uuid(),
            'input_tokens'    => $inputTokens,
            'output_tokens'   => $outputTokens,
        ];

        $ctx = $this->buildPersistenceContext($slice, $input, $questionTypeId, $semanticKey);
        $resp = [
            'semantic_concept_key' => $semanticKey,
            'question_type'        => $type,
            'rows'                 => $rows,
        ];

        try {
            $persist = DB::transaction(fn() => $this->persist($resp, $type, $ctx, $meta));
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
                'question_type'         => $type,
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

    protected function fail(string $message): array
    {
        return ['status' => false, 'message' => $message, 'data' => []];
    }
}
