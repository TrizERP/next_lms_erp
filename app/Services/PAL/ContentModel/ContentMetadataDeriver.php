<?php

namespace App\Services\PAL\ContentModel;

use App\Services\PAL\Content\PalVocabulary;

/**
 * Builds the spec's 30+ field metadata schema for one projected node.
 *
 * Three provenances, kept apart on purpose so the UI can show a reviewer where
 * every value came from:
 *
 *   derived   computed from the extraction by rule (bloom, DOK, difficulty,
 *             knowledge type, scaffold, latency, duration, cognitive load,
 *             reading level, misconception tags, skills, prerequisites …)
 *   ai        proposed by the LLM because the extraction does not carry the
 *             field at all (RIASEC, Gardner, NGSS, CASEL, NCDG, career cluster,
 *             HPC lens, cultural context when the lexicon is inconclusive)
 *   human     typed by an author in the authoring interface
 *
 * A field with no value is left NULL and reported as missing. It is never
 * defaulted to a plausible-looking constant — a wrong tag routes a learner to
 * the wrong content, which is worse than an absent one.
 */
class ContentMetadataDeriver
{
    /**
     * @param  array  $concept   one entry from SemanticSourceRepository::conceptsFor()
     * @param  array  $header    the chapter header row
     * @param  array  $node      the projected node (content_type, format, body …)
     * @return array<string,mixed>
     */
    public function derive(array $node, array $concept, array $header, array $chapter = []): array
    {
        $conceptObject = $concept['concept'] ?? [];
        $contentType = (string) ($node['content_type'] ?? 'concept');
        $bloom = $node['bloom_level'] ?? null;
        $practiceLevel = $node['practice_level'] ?? PalVocabulary::practiceLevelForBloom($bloom);
        $dok = $node['dok_level'] ?? $this->dominantDok($concept);
        $text = $this->nodeText($node);

        $grade = isset($header['standard']) ? (int) $header['standard'] : null;
        $difficulty = $node['difficulty_1_to_5'] ?? $this->difficultyFrom($conceptObject['difficulty'] ?? null);

        $metadata = [
            // ── identity ────────────────────────────────────────────────────
            'node_key' => $node['node_key'] ?? null,
            'content_id_ref' => $node['content_id_ref'] ?? null,
            'concept_key' => $conceptObject['concept_id'] ?? $concept['slug'] ?? null,
            'concept_name' => $concept['name'] ?? null,
            'sub_concept_ref' => $node['sub_concept_ref'] ?? null,
            'content_type' => $contentType,
            'variant_number' => $node['variant_number'] ?? null,
            'practice_level' => $practiceLevel,

            // ── curriculum ──────────────────────────────────────────────────
            'subject' => $header['subject_name'] ?? null,
            'grade' => $grade,
            'grade_band' => $this->gradeBand($grade),
            'stage' => $this->stage($grade),
            'board' => $header['board'] ?? $this->boardFrom($conceptObject),
            'chapter' => $chapter['chapter_name'] ?? ($header['chapter_name'] ?? null),
            'chapter_number' => $header['chapter_number'] ?? null,

            // ── cognitive ───────────────────────────────────────────────────
            'bloom_level' => $bloom,
            'dok_level' => $dok,
            'knowledge_type' => $node['knowledge_type'] ?? $this->knowledgeType($concept),
            'difficulty_1_to_5' => $difficulty,
            'cognitive_load_intrinsic' => $this->intrinsicLoad($dok),
            'cognitive_load_extraneous' => $this->extraneousLoad($text),
            'cognitive_load_germane' => $this->germaneLoad($practiceLevel),

            // ── delivery ────────────────────────────────────────────────────
            'format' => $node['format'] ?? null,
            'h5p_type' => $node['h5p_type'] ?? null,
            'pedagogy_tag' => $node['pedagogy_tag'] ?? $this->dominantPedagogy($concept),
            'pedagogy_secondary' => $node['pedagogy_secondary'] ?? $this->secondaryPedagogy($concept),
            'scaffold_type' => $node['scaffold_type'] ?? $this->scaffold($contentType, $practiceLevel),
            'response_latency_band' => $node['response_latency_band'] ?? $this->latency($practiceLevel),
            'estimated_duration_minutes' => $node['estimated_duration_minutes'] ?? $this->duration($text, $contentType),
            'visual_dependency' => $this->visualDependency($text),
            // The extraction is text; nothing in it needs a live connection.
            'offline_compatible' => true,

            // ── language & culture ──────────────────────────────────────────
            'language' => $node['language'] ?? config('pal_content.default_language', 'en'),
            'language_variants_available' => $node['language_variants_available'] ?? [config('pal_content.default_language', 'en')],
            // Set by CulturalContextClassifier / the LLM, never guessed here.
            'cultural_context' => $node['cultural_context'] ?? null,
            'gender_representation' => $node['gender_representation'] ?? null,
            'reading_level_fk' => $this->fleschKincaid($text),

            // ── standards ───────────────────────────────────────────────────
            'skills' => $node['skills'] ?? $this->skills($concept),
            'competencies' => $this->competencies($concept),
            'casel_domain' => $node['casel_domain'] ?? null,
            'ngss_practice' => $node['ngss_practice'] ?? null,
            'ncdg_goal' => $node['ncdg_goal'] ?? null,
            'riasec_signal' => $node['riasec_signal'] ?? null,
            'gardner_intelligence' => $node['gardner_intelligence'] ?? null,
            'aptitude_domain' => $node['aptitude_domain'] ?? null,
            'p21_skill' => $node['p21_skill'] ?? null,
            'hpc_lens_primary' => $node['hpc_lens_primary'] ?? null,

            // ── misconception ───────────────────────────────────────────────
            'misconception_tags' => $node['misconception_tags'] ?? [],
            'distractor_rationale' => $node['distractor_rationale'] ?? null,
            'common_errors' => $node['common_errors'] ?? [],

            // ── psychometric ────────────────────────────────────────────────
            // IRT parameters are NOT invented. They are computed from response
            // history by the existing pal:derive-irt job once these items have
            // been served; until then they are honestly absent.
            'marks' => $node['marks'] ?? null,
            'avg_time_seconds' => $node['avg_time_seconds'] ?? $this->avgSeconds($node),
            'guessing_vulnerability' => $node['guessing_vulnerability'] ?? null,
            'evidence_verified' => $node['evidence_verified'] ?? null,

            // ── career ──────────────────────────────────────────────────────
            'career_cluster_signal' => $node['career_cluster_signal'] ?? null,
            'soft_skill_signal' => $node['soft_skill_signal'] ?? null,
            'nep_vocational_stream' => $node['nep_vocational_stream'] ?? null,
            'nsqf_level' => $node['nsqf_level'] ?? null,

            // ── quality ─────────────────────────────────────────────────────
            'quality_status' => $node['quality_status'] ?? 'draft',
            'tagged_by' => $node['tagged_by'] ?? 'derived',
            'confidence' => $node['confidence'] ?? $this->confidence($conceptObject),
            'version' => $node['version'] ?? '1.0',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'content_flag' => null,
            'sensitivity_flag' => false,
        ];

        return $metadata;
    }

    // ── Completeness reporting ───────────────────────────────────────────────

    /**
     * Mandatory fields (spec Appendix) still empty. A node with anything in this
     * list may not be approved.
     */
    public function missingMandatory(string $contentType, array $metadata): array
    {
        $required = config('pal_content_model.mandatory_fields.' . $contentType, []);

        $missing = [];
        foreach ($required as $field) {
            $value = $metadata[$field] ?? null;
            if ($value === null || $value === '' || $value === []) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /** 0-100 across every field the schema defines, not just the mandatory ones. */
    public function completeness(array $metadata): int
    {
        $groups = config('pal_content_model.metadata_field_groups', []);

        $total = 0;
        $filled = 0;
        foreach ($groups as $fields) {
            foreach ($fields as $field) {
                $total++;
                $value = $metadata[$field] ?? null;
                if ($value !== null && $value !== '' && $value !== []) {
                    $filled++;
                }
            }
        }

        return $total === 0 ? 0 : (int) round($filled / $total * 100);
    }

    /**
     * Which fields the LLM could fill, given what is already there. Drives the
     * "enrich with AI" affordance so it is never offered for a field a human
     * has already answered.
     */
    public function llmCandidates(array $metadata): array
    {
        $candidates = [];
        foreach (config('pal_content_model.llm_only_fields', []) as $field) {
            $value = $metadata[$field] ?? null;
            if ($value === null || $value === '' || $value === []) {
                $candidates[] = $field;
            }
        }
        if (($metadata['cultural_context'] ?? null) === null) {
            $candidates[] = 'cultural_context';
        }

        return array_values(array_unique($candidates));
    }

    /** Field → provenance label, for the authoring UI's per-field badge. */
    public function provenance(array $metadata): array
    {
        $llmOnly = array_flip(array_merge(
            config('pal_content_model.llm_only_fields', []),
            ['cultural_context']
        ));

        $out = [];
        foreach ($metadata as $field => $value) {
            if ($value === null || $value === '' || $value === []) {
                $out[$field] = 'missing';
                continue;
            }
            $out[$field] = isset($llmOnly[$field]) ? 'ai' : 'derived';
        }

        return $out;
    }

    // ── Derivations ──────────────────────────────────────────────────────────

    /** Extractor Bloom tier name → PAL bloom key. Unknown → null, never coerced. */
    public function bloomKey(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $aliases = config('pal_content_model.bloom_aliases', []);
        $key = $aliases[mb_strtolower(trim($raw))] ?? null;

        return PalVocabulary::isBloomLevel($key) ? $key : null;
    }

    public function difficultyFrom($raw): ?int
    {
        if (is_numeric($raw)) {
            $n = (int) $raw;

            return ($n >= 1 && $n <= 5) ? $n : null;
        }
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        return config('pal_content_model.difficulty_aliases', [])[mb_strtolower(trim($raw))] ?? null;
    }

    public function priorityFrom($raw): ?int
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        return config('pal_content_model.importance_priority', [])[mb_strtolower(trim($raw))] ?? null;
    }

    /** The DOK level most of this concept's evidence sits at. */
    public function dominantDok(array $concept): ?int
    {
        $counts = [];
        foreach ($concept['dok'] ?? [] as $row) {
            $level = (int) ($row['level'] ?? 0);
            if ($level >= 1 && $level <= 4) {
                $counts[$level] = ($counts[$level] ?? 0) + 1;
            }
        }
        if ($counts === []) {
            return null;
        }
        arsort($counts);

        return (int) array_key_first($counts);
    }

    public function knowledgeType(array $concept): ?string
    {
        $aliases = config('pal_content_model.knowledge_type_aliases', []);

        $counts = [];
        foreach ($concept['knowledge_items'] ?? [] as $row) {
            $raw = mb_strtolower(trim((string) ($row['knowledge_type'] ?? '')));
            $mapped = $aliases[$raw] ?? null;
            if ($mapped !== null) {
                $counts[$mapped] = ($counts[$mapped] ?? 0) + 1;
            }
        }
        if ($counts === []) {
            return null;
        }
        arsort($counts);

        return (string) array_key_first($counts);
    }

    /** The pedagogy strategy the extractor recommends most often. */
    public function dominantPedagogy(array $concept): ?string
    {
        $counts = [];
        foreach ($concept['pedagogy_recommendations'] ?? [] as $row) {
            $strategy = trim((string) ($row['strategy'] ?? ''));
            if ($strategy !== '') {
                $counts[$strategy] = ($counts[$strategy] ?? 0) + 1;
            }
        }
        if ($counts === []) {
            return null;
        }
        arsort($counts);

        return (string) array_key_first($counts);
    }

    public function secondaryPedagogy(array $concept): array
    {
        $counts = [];
        foreach ($concept['pedagogy_recommendations'] ?? [] as $row) {
            $strategy = trim((string) ($row['strategy'] ?? ''));
            if ($strategy !== '') {
                $counts[$strategy] = ($counts[$strategy] ?? 0) + 1;
            }
        }
        if ($counts === []) {
            return [];
        }
        arsort($counts);

        return array_values(array_slice(array_keys($counts), 1, 3));
    }

    public function skills(array $concept): array
    {
        $skills = [];
        foreach ($concept['skills'] ?? [] as $row) {
            $name = trim((string) ($row['skill'] ?? ''));
            if ($name !== '' && ! in_array($name, $skills, true)) {
                $skills[] = $name;
            }
        }

        return $skills;
    }

    public function competencies(array $concept): array
    {
        $out = [];
        foreach ($concept['competencies'] ?? [] as $row) {
            $name = trim((string) ($row['competency'] ?? ''));
            if ($name !== '' && ! in_array($name, $out, true)) {
                $out[] = $name;
            }
        }

        return $out;
    }

    public function scaffold(string $contentType, ?int $practiceLevel): ?string
    {
        if ($contentType === 'concept') {
            return 'worked_example';
        }
        if ($contentType === 'corrective') {
            return 'hint_sequence';
        }
        if ($practiceLevel === null) {
            return null;
        }

        return config('pal_content_model.scaffold_by_level', [])[$practiceLevel] ?? null;
    }

    public function latency(?int $practiceLevel): ?string
    {
        if ($practiceLevel === null) {
            return null;
        }

        return config('pal_content_model.latency_by_level', [])[$practiceLevel] ?? null;
    }

    public function duration(string $text, string $contentType): ?int
    {
        $wpm = (int) config('pal_content_model.estimation.words_per_minute', 180);
        $floor = (int) (config('pal_content_model.estimation.min_minutes_by_content_type', [])[$contentType] ?? 1);

        $words = str_word_count(strip_tags($text));
        if ($words === 0) {
            return null;
        }

        return max($floor, (int) ceil($words / max(1, $wpm)));
    }

    protected function avgSeconds(array $node): ?int
    {
        $marks = $node['marks'] ?? null;
        if (! is_numeric($marks) || (int) $marks <= 0) {
            return null;
        }

        return (int) $marks * (int) config('pal_content_model.estimation.seconds_per_mark', 90);
    }

    public function intrinsicLoad(?int $dok): ?int
    {
        if ($dok === null) {
            return null;
        }

        return config('pal_content_model.estimation.intrinsic_by_dok', [])[$dok] ?? null;
    }

    /**
     * Extraneous load is the load the PRESENTATION adds. An item that leans on a
     * figure the estate may not hold carries more of it than a self-contained one.
     */
    public function extraneousLoad(string $text): int
    {
        return $this->visualDependency($text) ? 3 : 1;
    }

    public function germaneLoad(?int $practiceLevel): ?int
    {
        if ($practiceLevel === null) {
            return null;
        }

        return config('pal_content_model.estimation.germane_by_practice_level', [])[$practiceLevel] ?? null;
    }

    public function visualDependency(string $text): bool
    {
        $haystack = mb_strtolower($text);
        foreach (config('pal_content_model.visual_dependency_stems', []) as $stem) {
            if ($stem !== '' && str_contains($haystack, $stem)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Flesch-Kincaid grade level. Computed, not stored — the authoring UI shows
     * it live as the author types, so it has to be cheap and deterministic.
     */
    public function fleschKincaid(string $text): ?float
    {
        $clean = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');
        if ($clean === '') {
            return null;
        }

        $sentences = max(1, preg_match_all('/[.!?]+(\s|$)/', $clean));
        $words = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $wordCount = count($words);
        if ($wordCount < 5) {
            return null;
        }

        $syllables = 0;
        foreach ($words as $word) {
            $syllables += $this->syllables($word);
        }

        $grade = 0.39 * ($wordCount / $sentences) + 11.8 * ($syllables / $wordCount) - 15.59;

        return round(max(0.0, min(20.0, $grade)), 1);
    }

    /** Vowel-group syllable count — the standard approximation for FK. */
    protected function syllables(string $word): int
    {
        $word = preg_replace('/[^a-z]/', '', mb_strtolower($word)) ?? '';
        if ($word === '') {
            return 0;
        }

        $count = preg_match_all('/[aeiouy]+/', $word);
        // A trailing silent "e" is not its own syllable.
        if (str_ends_with($word, 'e') && $count > 1) {
            $count--;
        }

        return max(1, $count);
    }

    public function confidence(array $conceptObject): ?float
    {
        $value = $conceptObject['confidence'] ?? null;

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    public function gradeBand(?int $grade): ?string
    {
        if ($grade === null || $grade <= 0) {
            return null;
        }
        if ($grade <= 2) {
            return '1-2';
        }
        if ($grade <= 5) {
            return '3-5';
        }
        if ($grade <= 8) {
            return '6-8';
        }

        return '9-12';
    }

    /** NEP 2020 4-stage HPC architecture. */
    public function stage(?int $grade): ?string
    {
        if ($grade === null || $grade <= 0) {
            return null;
        }
        if ($grade <= 2) {
            return 'Foundational';
        }
        if ($grade <= 5) {
            return 'Preparatory';
        }
        if ($grade <= 8) {
            return 'Middle';
        }

        return 'Secondary';
    }

    /**
     * The board, when the extractor's own concept_id namespaces it
     * (e.g. "NCERT-CH3-NONMETALS-PHYSICAL"). Absent rather than assumed.
     */
    protected function boardFrom(array $conceptObject): ?string
    {
        $id = (string) ($conceptObject['concept_id'] ?? '');
        if ($id === '' || ! str_contains($id, '-')) {
            return null;
        }
        $head = strtoupper(explode('-', $id)[0]);

        return preg_match('/^[A-Z]{3,10}$/', $head) ? $head : null;
    }

    /** Everything on a node a reading-level / duration calculation should see. */
    protected function nodeText(array $node): string
    {
        $parts = array_filter([
            $node['title'] ?? null,
            $node['body'] ?? null,
            $node['prompt'] ?? null,
        ], fn ($v) => is_string($v) && $v !== '');

        return implode("\n", $parts);
    }
}
