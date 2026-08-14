<?php

namespace App\Services\PAL\Content;

use Illuminate\Support\Facades\DB;

/**
 * Closed-vocabulary registry and validator for the Content Intelligence Layer.
 *
 * Plan §4: every enum is a closed set registered in config/pal_content.php. An
 * unregistered value is a WRITE FAILURE, not a new category. This is the single
 * thing standing between 62,209 questions and 400 spellings of "apply".
 *
 * Pedagogy is the one vocabulary NOT defined in config: it is read live from
 * lms_mapping_type (children of the configured pedagogy parent), because that
 * estate already exists and is what the rest of the LMS tags against. Inventing
 * a parallel pedagogy list here would fork the taxonomy.
 */
class PalVocabulary
{
    /** Cache for the DB-backed pedagogy vocabulary, per request. */
    protected static ?array $pedagogyCache = null;

    // ── Bloom / practice ladder ──────────────────────────────────────────────

    public static function bloomLevels(): array
    {
        return array_keys(config('pal_content.bloom_levels', []));
    }

    public static function isBloomLevel(?string $value): bool
    {
        return $value !== null && array_key_exists($value, config('pal_content.bloom_levels', []));
    }

    /**
     * Practice level (1-5) for a Bloom level. The spec collapses evaluate+create
     * into L5, so this is many-to-one on the way down and lossy on the way back.
     */
    public static function practiceLevelForBloom(?string $bloom): ?int
    {
        $levels = config('pal_content.bloom_levels', []);

        return isset($levels[$bloom]) ? (int) $levels[$bloom]['practice_level'] : null;
    }

    public static function bloomForPracticeLevel(?int $level): ?string
    {
        $levels = config('pal_content.practice_levels', []);

        return isset($levels[$level]) ? $levels[$level]['bloom_level'] : null;
    }

    public static function bloomOrdinal(?string $bloom): ?int
    {
        $levels = config('pal_content.bloom_levels', []);

        return isset($levels[$bloom]) ? (int) $levels[$bloom]['ordinal'] : null;
    }

    /**
     * The lms_mapping_type id for a Bloom level, so PAL tags reconcile with the
     * ids the LMS already uses (parent 82) instead of forking the taxonomy.
     */
    public static function bloomMappingTypeId(?string $bloom): ?int
    {
        $levels = config('pal_content.bloom_levels', []);

        return isset($levels[$bloom]) ? (int) $levels[$bloom]['mapping_type_id'] : null;
    }

    /** Reverse: an existing lms_mapping_type id (83-88) back to a PAL bloom key. */
    public static function bloomFromMappingTypeId(int $mappingTypeId): ?string
    {
        foreach (config('pal_content.bloom_levels', []) as $key => $def) {
            if ((int) $def['mapping_type_id'] === $mappingTypeId) {
                return $key;
            }
        }

        return null;
    }

    public static function isPracticeLevel($value): bool
    {
        return is_numeric($value) && array_key_exists((int) $value, config('pal_content.practice_levels', []));
    }

    // ── Pedagogy — read from the live LMS estate, never invented ─────────────

    /**
     * @return array<int,string> mapping_type_id => name
     */
    public static function pedagogyOptions(): array
    {
        if (self::$pedagogyCache !== null) {
            return self::$pedagogyCache;
        }

        $src = config('pal_content.pedagogy_source');

        try {
            $rows = DB::table($src['table'])
                ->where('parent_id', $src['parent_id'])
                ->where('status', $src['status'])
                ->orderBy('id')
                ->get(['id', 'name']);

            $out = [];
            foreach ($rows as $r) {
                // Live rows carry trailing newlines ("Flipped classroom pedagogy\n").
                $out[(int) $r->id] = trim((string) $r->name);
            }

            if ($out !== []) {
                return self::$pedagogyCache = $out;
            }
        } catch (\Throwable $e) {
            // No DB (unit tests, migrations on a fresh schema) — fall through.
        }

        // Fallback keeps validation deterministic when the estate is unreachable.
        $fallback = [];
        foreach (config('pal_content.pedagogy_fallback', []) as $i => $slug) {
            $fallback[-1 - $i] = $slug;
        }

        return self::$pedagogyCache = $fallback;
    }

    public static function isPedagogyMappingId($value): bool
    {
        return $value === null || array_key_exists((int) $value, self::pedagogyOptions());
    }

    /** Test seam — drops the per-request pedagogy cache. */
    public static function flushCache(): void
    {
        self::$pedagogyCache = null;
    }

    // ── Generic closed-set checks ────────────────────────────────────────────

    public static function isContentType(?string $v): bool
    {
        return $v !== null && array_key_exists($v, config('pal_content.content_types', []));
    }

    public static function isFormat(?string $v): bool
    {
        return $v !== null && array_key_exists($v, config('pal_content.formats', []));
    }

    public static function isQualityStatus(?string $v): bool
    {
        return $v !== null && array_key_exists($v, config('pal_content.quality_statuses', []));
    }

    public static function isServable(?string $v): bool
    {
        return in_array($v, config('pal_content.servable_statuses', ['approved']), true);
    }

    /**
     * CONTENT LAW C5 — statuses a machine may never write. A batch job that
     * stamps `approved` is a defect, so the constraint lives here rather than
     * in each command.
     */
    public static function isMachineWritable(?string $status): bool
    {
        $def = config('pal_content.quality_statuses', [])[$status] ?? null;

        return $def !== null && $def['human_only'] === false;
    }

    /** Legal QA stage transitions (spec §7.1). */
    public static function canTransition(?string $from, string $to): bool
    {
        if (! self::isQualityStatus($to)) {
            return false;
        }

        // A row with no prior status may only be created as draft.
        if ($from === null) {
            return $to === 'draft';
        }

        if ($from === $to) {
            return true;
        }

        return in_array($to, config('pal_content.quality_transitions', [])[$from] ?? [], true);
    }

    public static function defaultVariantForFormat(?string $format): ?int
    {
        $formats = config('pal_content.formats', []);

        return isset($formats[$format]) ? (int) $formats[$format]['variant'] : null;
    }

    // ── Field-level validation ───────────────────────────────────────────────

    /**
     * Validate an arbitrary metadata payload against the closed vocabularies.
     *
     * Returns a list of human-readable errors — empty means valid. Only the keys
     * present in $data are checked, so this works for partial updates as well as
     * full writes.
     *
     * @param  array<string,mixed>  $data
     * @return array<int,string>
     */
    public static function validate(array $data): array
    {
        $errors = [];

        $inSet = function (string $key, array $set, string $label) use ($data, &$errors) {
            if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
                return;
            }
            if (! in_array($data[$key], $set, true)) {
                $errors[] = "{$key}: '{$data[$key]}' is not a registered {$label}. Allowed: " . implode(', ', $set);
            }
        };

        $inRange = function (string $key, int $min, int $max) use ($data, &$errors) {
            if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
                return;
            }
            $v = (int) $data[$key];
            if ($v < $min || $v > $max) {
                $errors[] = "{$key}: {$v} is outside the allowed range {$min}-{$max}.";
            }
        };

        $unitInterval = function (string $key) use ($data, &$errors) {
            if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
                return;
            }
            $v = (float) $data[$key];
            if ($v < 0.0 || $v > 1.0) {
                $errors[] = "{$key}: {$v} must be between 0 and 1.";
            }
        };

        // Cognitive classification
        $inSet('bloom_level', self::bloomLevels(), 'bloom level');
        $inSet('bloom_level_served', self::bloomLevels(), 'bloom level');
        $inSet('bloom_ceiling', self::bloomLevels(), 'bloom level');
        $inSet('knowledge_type', config('pal_content.knowledge_types', []), 'knowledge type');

        if (array_key_exists('practice_level', $data) && $data['practice_level'] !== null && $data['practice_level'] !== '') {
            if (! self::isPracticeLevel($data['practice_level'])) {
                $errors[] = "practice_level: '{$data['practice_level']}' is not one of 1-5.";
            }
        }
        if (array_key_exists('practice_ceiling', $data) && $data['practice_ceiling'] !== null && $data['practice_ceiling'] !== '') {
            if (! self::isPracticeLevel($data['practice_ceiling'])) {
                $errors[] = "practice_ceiling: '{$data['practice_ceiling']}' is not one of 1-5.";
            }
        }

        // Bloom and practice level must agree when both are supplied — otherwise
        // the ladder routes on one axis while reporting on another.
        if (! empty($data['bloom_level']) && ! empty($data['practice_level'])
            && self::isBloomLevel($data['bloom_level']) && self::isPracticeLevel($data['practice_level'])) {
            $expected = self::practiceLevelForBloom($data['bloom_level']);
            if ($expected !== (int) $data['practice_level']) {
                $errors[] = "practice_level {$data['practice_level']} contradicts bloom_level '{$data['bloom_level']}' (expected L{$expected}).";
            }
        }

        $d = config('pal_content.difficulty_range');
        $inRange('difficulty_1_to_5', $d['min'], $d['max']);
        $inRange('cognitive_load_intrinsic', 1, 5);
        $inRange('cognitive_load_extraneous', 1, 5);
        $inRange('cognitive_load_germane', 1, 5);
        $inRange('priority_score', 1, 10);

        $mc = config('pal_content.misconception');
        $inRange('priority_level', $mc['priority_range']['min'], $mc['priority_range']['max']);

        $nsqf = config('pal_content.nsqf_range');
        $inRange('nsqf_level', $nsqf['min'], $nsqf['max']);

        // Delivery
        $inSet('content_type', array_keys(config('pal_content.content_types', [])), 'content type');
        $inSet('format', array_keys(config('pal_content.formats', [])), 'format');
        $inSet('corrective_format', config('pal_content.corrective_formats', []), 'corrective format');
        $inSet('h5p_type', config('pal_content.h5p_types', []), 'H5P type');
        $inSet('scaffold_type', config('pal_content.scaffold_types', []), 'scaffold type');
        $inSet('response_latency_band', config('pal_content.response_latency_bands', []), 'response latency band');
        $inSet('assessment_type', array_keys(config('pal_content.assessment_types', [])), 'assessment type');
        $inSet('guessing_vulnerability', config('pal_content.guessing_vulnerability', []), 'guessing vulnerability');

        if (array_key_exists('variant_number', $data) && $data['variant_number'] !== null && $data['variant_number'] !== '') {
            $ladder = config('pal_content.variant_ladder', []);
            if (! in_array((int) $data['variant_number'], $ladder, true)) {
                $errors[] = "variant_number: '{$data['variant_number']}' is not in the variant ladder (" . implode(', ', $ladder) . ').';
            }
        }

        // Language & culture
        $inSet('language', config('pal_content.languages', []), 'language');
        $inSet('cultural_context', config('pal_content.cultural_contexts', []), 'cultural context');
        $inSet('gender_representation', config('pal_content.gender_representation', []), 'gender representation');

        if (! empty($data['language_variants_available'])) {
            $langs = is_array($data['language_variants_available'])
                ? $data['language_variants_available']
                : (json_decode((string) $data['language_variants_available'], true) ?: []);
            foreach ($langs as $l) {
                if (! in_array($l, config('pal_content.languages', []), true)) {
                    $errors[] = "language_variants_available: '{$l}' is not a registered language.";
                }
            }
        }

        // Standards
        $inSet('casel_domain', config('pal_content.casel_domains', []), 'CASEL domain');
        $inSet('ngss_practice', config('pal_content.ngss_practices', []), 'NGSS practice');
        $inSet('ncdg_goal', config('pal_content.ncdg_goals', []), 'NCDG goal');
        $inSet('riasec_signal', array_keys(config('pal_content.riasec_signals', [])), 'RIASEC signal');
        $inSet('riasec_primary', array_keys(config('pal_content.riasec_signals', [])), 'RIASEC signal');
        $inSet('aptitude_domain', config('pal_content.aptitude_domains', []), 'aptitude domain');
        $inSet('career_cluster_signal', config('pal_content.career_clusters', []), 'career cluster');
        $inSet('soft_skill_signal', config('pal_content.soft_skill_signals', []), 'soft skill signal');
        $inSet('p21_skill', config('pal_content.p21_skills', []), 'P21 skill');
        $inSet('nep_vocational_stream', config('pal_content.nep_vocational_streams', []), 'NEP vocational stream');
        $inSet('hpc_lens_primary', config('pal_content.hpc_lenses', []), 'HPC lens');
        $inSet('hpc_lens', config('pal_content.hpc_lenses', []), 'HPC lens');

        // gardner_intelligence is a single value on questions and an array on content.
        if (! empty($data['gardner_intelligence'])) {
            $set = config('pal_content.gardner_intelligences', []);
            $vals = is_array($data['gardner_intelligence'])
                ? $data['gardner_intelligence']
                : [$data['gardner_intelligence']];
            foreach ($vals as $g) {
                if (! in_array($g, $set, true)) {
                    $errors[] = "gardner_intelligence: '{$g}' is not a registered intelligence.";
                }
            }
        }
        $inSet('gardner_primary', config('pal_content.gardner_intelligences', []), 'Gardner intelligence');

        // Quality & provenance
        $inSet('quality_status', array_keys(config('pal_content.quality_statuses', [])), 'quality status');
        $inSet('tagged_by', config('pal_content.tagged_by', []), 'tagged_by value');
        $unitInterval('confidence');
        $unitInterval('first_attempt_correct_rate');
        $unitInterval('prevalence_rate');
        $unitInterval('avg_mastery_post');
        $unitInterval('mastery_gate');
        $unitInterval('resolution_rate');
        $unitInterval('suggestion_trigger_mastery');

        // Pedagogy must reference the live LMS estate.
        if (array_key_exists('pedagogy_mapping_id', $data) && ! self::isPedagogyMappingId($data['pedagogy_mapping_id'])) {
            $errors[] = "pedagogy_mapping_id: '{$data['pedagogy_mapping_id']}' is not a live pedagogy row in "
                . config('pal_content.pedagogy_source.table') . '.';
        }

        // Tenancy — CONTENT LAW C3.
        $inSet('scope', config('pal_content.scopes', []), 'scope');
        if (($data['scope'] ?? null) === 'global'
            && array_key_exists('sub_institute_id', $data)
            && (int) $data['sub_institute_id'] !== (int) config('pal_content.global_sub_institute_id')) {
            $errors[] = "scope 'global' requires sub_institute_id = " . config('pal_content.global_sub_institute_id') . '.';
        }
        if (($data['scope'] ?? null) === 'tenant'
            && array_key_exists('sub_institute_id', $data)
            && (int) $data['sub_institute_id'] === 0) {
            $errors[] = "scope 'tenant' requires a non-zero sub_institute_id (undecidable tenancy is rejected).";
        }

        // Misconception tags are lower_snake_case and permanent.
        if (! empty($data['tag']) && ! preg_match('/^[a-z][a-z0-9_]{2,95}$/', (string) $data['tag'])) {
            $errors[] = "tag: '{$data['tag']}' must be lower_snake_case, 3-96 chars, starting with a letter.";
        }
        if (! empty($data['misconception_tags'])) {
            $tags = is_array($data['misconception_tags'])
                ? $data['misconception_tags']
                : (json_decode((string) $data['misconception_tags'], true) ?: []);
            foreach ($tags as $t) {
                if (! preg_match('/^[a-z][a-z0-9_]{2,95}$/', (string) $t)) {
                    $errors[] = "misconception_tags: '{$t}' must be lower_snake_case.";
                }
            }
        }

        return $errors;
    }

    /**
     * Everything the authoring UI needs to render its dropdowns, in one payload.
     * Spec §9.1 requires selectors for all 30+ metadata fields; this is the
     * single source those selectors read.
     */
    public static function all(): array
    {
        return [
            'bloom_levels' => config('pal_content.bloom_levels'),
            'practice_levels' => config('pal_content.practice_levels'),
            'content_types' => config('pal_content.content_types'),
            'formats' => config('pal_content.formats'),
            'variant_ladder' => config('pal_content.variant_ladder'),
            'difficulty_range' => config('pal_content.difficulty_range'),
            'quality_statuses' => config('pal_content.quality_statuses'),
            'quality_transitions' => config('pal_content.quality_transitions'),
            'servable_statuses' => config('pal_content.servable_statuses'),
            'tagged_by' => config('pal_content.tagged_by'),
            'cultural_contexts' => config('pal_content.cultural_contexts'),
            'languages' => config('pal_content.languages'),
            'corrective_formats' => config('pal_content.corrective_formats'),
            'knowledge_types' => config('pal_content.knowledge_types'),
            'scaffold_types' => config('pal_content.scaffold_types'),
            'response_latency_bands' => config('pal_content.response_latency_bands'),
            'guessing_vulnerability' => config('pal_content.guessing_vulnerability'),
            'gender_representation' => config('pal_content.gender_representation'),
            'assessment_types' => config('pal_content.assessment_types'),
            'casel_domains' => config('pal_content.casel_domains'),
            'ngss_practices' => config('pal_content.ngss_practices'),
            'ncdg_goals' => config('pal_content.ncdg_goals'),
            'riasec_signals' => config('pal_content.riasec_signals'),
            'gardner_intelligences' => config('pal_content.gardner_intelligences'),
            'aptitude_domains' => config('pal_content.aptitude_domains'),
            'career_clusters' => config('pal_content.career_clusters'),
            'soft_skill_signals' => config('pal_content.soft_skill_signals'),
            'p21_skills' => config('pal_content.p21_skills'),
            'nep_vocational_streams' => config('pal_content.nep_vocational_streams'),
            'nsqf_range' => config('pal_content.nsqf_range'),
            'hpc_lenses' => config('pal_content.hpc_lenses'),
            'hpc_ceilings' => config('pal_content.hpc_ceilings'),
            'h5p_types' => config('pal_content.h5p_types'),
            'h5p_gates_delivery' => config('pal_content.h5p_gates_delivery'),
            'regression' => config('pal_content.regression'),
            'misconception' => config('pal_content.misconception'),
            // Live from lms_mapping_type, not from config.
            'pedagogy' => self::pedagogyOptions(),
        ];
    }
}
