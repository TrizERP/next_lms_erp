<?php

namespace App\Services\PAL\ContentModel;

use App\Services\PAL\Content\PalVocabulary;

/**
 * Projects the PAL V4 four-type content model out of one extracted concept.
 *
 *   Type 1  Concept Learning   — 4 format variants (spec §2.1)
 *   Type 2  Practice Content   — the 5-level Bloom ladder (spec §3)
 *   Type 3  Misconception Lib. — delegated to MisconceptionProjector (spec §4)
 *   Type 4  Assessment Bank    — calibrated items (spec §5)
 *
 * The two item pools are kept SEPARATE on purpose. `assessment_blueprint` rows
 * are recommended practice questions with a Bloom/DOK/difficulty/marks profile
 * but no answer key — they populate the practice ladder. `assessment_rubrics`
 * items are fully specified: answer key, per-distractor rationale, the
 * misconception each distractor tests, level descriptors, common errors — they
 * populate the assessment bank. The spec treats practice and assessment as
 * different content types serving different purposes, so a concept appearing in
 * both is correct rather than duplicated.
 *
 * A Type 1 variant is projected as a delivery SPECIFICATION plus the extracted
 * material that backs it, not as a finished asset — the extraction is text, and
 * claiming a video exists because a pedagogy note recommends one would put a
 * broken variant in front of a learner. `asset_state` says which it is, and a
 * variant with no backing material at all is reported as an explicit gap.
 */
class ContentModelProjector
{
    public function __construct(
        protected SemanticSourceRepository $source,
        protected ContentMetadataDeriver $deriver,
        protected MisconceptionProjector $misconceptions
    ) {}

    // ── Node keys ────────────────────────────────────────────────────────────

    public function nodeKey(string $type, int $semanticId, string $conceptSlug, string $discriminator = ''): string
    {
        $prefix = config('pal_content_model.node_prefixes.' . $type, strtoupper(substr($type, 0, 2)));
        $key = "{$prefix}.{$semanticId}.{$conceptSlug}";

        return $discriminator === '' ? $key : "{$key}.{$discriminator}";
    }

    /**
     * Reverse a node key. Returns null when it does not parse, so a hand-typed
     * or stale key fails loudly instead of resolving to the wrong node.
     *
     * @return array{type:string, semantic_id:int, concept_slug:string, discriminator:string}|null
     */
    public function parseNodeKey(string $nodeKey): ?array
    {
        $parts = explode('.', $nodeKey);
        if (count($parts) < 3) {
            return null;
        }

        $type = array_search($parts[0], config('pal_content_model.node_prefixes', []), true);
        if ($type === false || ! ctype_digit($parts[1]) || $parts[2] === '') {
            return null;
        }

        return [
            'type' => $type,
            'semantic_id' => (int) $parts[1],
            'concept_slug' => $parts[2],
            'discriminator' => implode('.', array_slice($parts, 3)),
        ];
    }

    // ── Whole-concept projection ─────────────────────────────────────────────

    /**
     * The complete four-type model for one concept.
     *
     * @return array<string,mixed>|null null when the concept does not exist
     */
    public function projectConcept(int $semanticId, string $conceptSlug, ?int $tenant): ?array
    {
        $loaded = $this->source->conceptsFor($semanticId, $tenant);
        $header = $loaded['header'];
        if ($header === null) {
            return null;
        }

        $concept = null;
        foreach ($loaded['concepts'] as $candidate) {
            if ($candidate['slug'] === $conceptSlug) {
                $concept = $candidate;
                break;
            }
        }
        if ($concept === null) {
            return null;
        }

        $variants = $this->conceptLearningVariants($semanticId, $concept, $header, $loaded['chapter']);
        $practice = $this->practiceLadder($semanticId, $concept, $header, $loaded['chapter']);
        $misconceptions = $this->misconceptions->project($semanticId, $concept, $header);
        $assessment = $this->assessmentBank($semanticId, $concept, $header, $loaded['chapter'], $misconceptions['entries']);

        return [
            'semantic_id' => $semanticId,
            'chapter' => $loaded['chapter'] + ['header' => $header],
            'concept' => $this->conceptHeader($concept, $header),
            'type_1_concept_learning' => $variants,
            'type_2_practice' => $practice,
            'type_3_misconceptions' => $misconceptions,
            'type_4_assessment' => $assessment,
            'graph' => $this->conceptGraph($concept),
        ];
    }

    /** Concept-level facts, including the :Concept node fields from spec §6.1. */
    public function conceptHeader(array $concept, array $header): array
    {
        $object = $concept['concept'] ?? [];
        $bloomCeiling = $this->bloomCeiling($concept);

        return [
            'slug' => $concept['slug'],
            'index' => $concept['index'],
            'name' => $concept['name'],
            'concept_key' => $object['concept_id'] ?? $concept['slug'],
            'concept_type' => $object['concept_type'] ?? null,
            'definition' => $object['definition'] ?? null,
            'importance' => $object['importance'] ?? null,
            'difficulty_label' => $object['difficulty'] ?? null,
            'difficulty_1_to_5' => $this->deriver->difficultyFrom($object['difficulty'] ?? null),
            'confidence' => $this->deriver->confidence($object),
            'priority_score' => $this->deriver->priorityFrom($object['importance'] ?? null),
            'bloom_ceiling' => $bloomCeiling,
            'practice_ceiling' => PalVocabulary::practiceLevelForBloom($bloomCeiling),
            'dominant_dok' => $this->deriver->dominantDok($concept),
            'knowledge_type' => $this->deriver->knowledgeType($concept),
            'mastery_gate' => (float) config('pal_content.practice_levels.3.gate.threshold', 0.70),
            'stage_gate' => $this->deriver->stage($header['standard'] ?? null),
            'counts' => [
                'knowledge' => count($concept['knowledge_items'] ?? []),
                'abilities' => count($concept['abilities'] ?? []),
                'skills' => count($concept['skills'] ?? []),
                'competencies' => count($concept['competencies'] ?? []),
                'objectives' => count($concept['learning_objectives'] ?? []),
                'outcomes' => count($concept['learning_outcomes'] ?? []),
                'prerequisites' => count($concept['prerequisites'] ?? []),
                'misconceptions' => count($concept['misconceptions'] ?? []),
                'applications' => count($concept['real_world_applications'] ?? []),
                'pedagogy' => count($concept['pedagogy_recommendations'] ?? []),
                'blueprint' => count($concept['assessment_blueprint'] ?? []),
                'rubric_items' => count($concept['assessment_rubrics'] ?? []),
                'evidence' => count($concept['evidence'] ?? []),
            ],
        ];
    }

    /** The highest Bloom level anything in this concept reaches. */
    protected function bloomCeiling(array $concept): ?string
    {
        $best = null;
        $bestOrdinal = 0;

        $raws = [];
        foreach ($concept['blooms'] ?? [] as $row) {
            $raws[] = $row['level'] ?? null;
        }
        foreach ($concept['assessment_blueprint'] ?? [] as $row) {
            $raws[] = $row['bloom_level'] ?? null;
        }
        foreach ($concept['assessment_rubrics'] ?? [] as $row) {
            $raws[] = $row['bloom_level'] ?? null;
        }

        foreach ($raws as $raw) {
            $key = $this->deriver->bloomKey(is_string($raw) ? $raw : null);
            $ordinal = PalVocabulary::bloomOrdinal($key);
            if ($key !== null && $ordinal !== null && $ordinal > $bestOrdinal) {
                $best = $key;
                $bestOrdinal = $ordinal;
            }
        }

        return $best;
    }

    /** Prerequisite + cross-concept edges (spec §6.1 REQUIRES, §6.2 links). */
    protected function conceptGraph(array $concept): array
    {
        $requires = [];
        foreach ($concept['prerequisites'] ?? [] as $row) {
            $name = trim((string) ($row['concept_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $requires[] = [
                'concept_name' => $name,
                'concept_slug' => $this->source->slug($name),
                'prerequisite_type' => $row['prerequisite_type'] ?? null,
                'necessity' => $row['necessity'] ?? null,
                // Mandatory prerequisites gate; recommended ones only suggest.
                'gates' => mb_strtolower((string) ($row['necessity'] ?? '')) === 'mandatory',
            ];
        }

        $related = [];
        foreach ($concept['concept_relationships'] ?? [] as $row) {
            $target = trim((string) ($row['target_concept'] ?? ''));
            if ($target === '') {
                continue;
            }
            $related[] = [
                'source_concept' => $row['source_concept'] ?? null,
                'target_concept' => $target,
                'target_slug' => $this->source->slug($target),
                'relation_type' => $row['relation_type'] ?? 'related_to',
            ];
        }

        return ['requires' => $requires, 'related' => $related];
    }

    // ══════════════════════════════════════════════════════════════════════
    // TYPE 1 — Concept Learning, 4 variants (spec §2.1)
    // ══════════════════════════════════════════════════════════════════════

    public function conceptLearningVariants(int $semanticId, array $concept, array $header, array $chapter): array
    {
        $blueprint = config('pal_content_model.variant_blueprint', []);

        $variants = [];
        $present = 0;

        foreach ($blueprint as $slot => $spec) {
            $material = $this->variantMaterial((int) $slot, $spec, $concept);

            $node = [
                'node_key' => $this->nodeKey('concept', $semanticId, $concept['slug'], 'V' . $slot),
                'content_id_ref' => $this->contentIdRef('CC', $concept, $header, 'V' . $slot),
                'content_type' => 'concept',
                'variant_number' => (int) $slot,
                'format' => $spec['format'],
                'format_label' => $spec['label'],
                'h5p_type' => $spec['h5p_type'],
                'bloom_level' => $spec['serves_bloom'],
                'when_served' => $spec['when_served'],
                'corrective_format' => $spec['corrective_format'],
                'title' => $concept['name'] . ' — ' . $spec['label'],
                'body' => $material['body'],
                'sources_used' => $material['sources_used'],
                'source_items' => $material['items'],
                // 'specified' — the variant has extracted material behind it and
                // is ready to author against. 'gap' — nothing backs it.
                'asset_state' => $material['body'] === '' ? 'gap' : 'specified',
                'gap' => $material['body'] === '',
                'gap_reason' => $material['body'] === ''
                    ? 'No extracted material backs this variant (needs: ' . implode(', ', $spec['sources']) . ').'
                    : null,
                'quality_status' => 'draft',
                'tagged_by' => 'derived',
            ];

            $node['metadata'] = $this->deriver->derive($node, $concept, $header, $chapter);
            $node['missing_mandatory'] = $this->deriver->missingMandatory('concept', $node['metadata']);
            $node['completeness'] = $this->deriver->completeness($node['metadata']);

            if (! $node['gap']) {
                $present++;
            }
            $variants[] = $node;
        }

        return [
            'label' => config('pal_content.content_types.concept.label', 'Concept Learning'),
            'required_minimum' => 3,   // spec §2.1: at minimum 3 format variants
            'present' => $present,
            'total_slots' => count($blueprint),
            // The re-route ladder can only work if there is somewhere to re-route TO.
            'meets_minimum' => $present >= 3,
            'reroute_order' => config('pal_content.variant_ladder', [1, 2, 3, 4]),
            'variants' => $variants,
        ];
    }

    /**
     * Assemble the material that backs one variant slot.
     *
     * @return array{body:string, sources_used:array<int,string>, items:array<int,array>}
     */
    protected function variantMaterial(int $slot, array $spec, array $concept): array
    {
        $object = $concept['concept'] ?? [];
        $body = [];
        $used = [];
        $items = [];

        foreach ($spec['sources'] as $sourceName) {
            switch ($sourceName) {
                case 'definition':
                    $definition = trim((string) ($object['definition'] ?? ''));
                    if ($definition !== '') {
                        $body[] = $definition;
                        $used[] = 'definition';
                    }
                    break;

                case 'evidence':
                    $quotes = [];
                    foreach (array_slice($concept['evidence'] ?? [], 0, 6) as $row) {
                        $textline = trim((string) ($row['source_text'] ?? ''));
                        if ($textline !== '') {
                            $quotes[] = $textline;
                            $items[] = ['kind' => 'evidence', 'source_type' => $row['source_type'] ?? null, 'text' => $textline];
                        }
                    }
                    if ($quotes !== []) {
                        $body[] = "Textbook evidence:\n• " . implode("\n• ", $quotes);
                        $used[] = 'evidence';
                    }
                    break;

                case 'knowledge':
                    $facts = [];
                    foreach (array_slice($concept['knowledge_items'] ?? [], 0, 8) as $row) {
                        $fact = trim((string) ($row['statement'] ?? $row['knowledge'] ?? ''));
                        if ($fact !== '') {
                            $facts[] = $fact;
                            $items[] = ['kind' => 'knowledge', 'type' => $row['knowledge_type'] ?? null, 'text' => $fact];
                        }
                    }
                    if ($facts !== []) {
                        $body[] = "Key knowledge:\n• " . implode("\n• ", $facts);
                        $used[] = 'knowledge';
                    }
                    break;

                case 'pedagogy':
                case 'pedagogy_practical':
                    $stems = $sourceName === 'pedagogy_practical'
                        ? config('pal_content_model.practical_pedagogy_stems', [])
                        : config('pal_content_model.visual_pedagogy_stems', []);

                    $notes = [];
                    foreach ($concept['pedagogy_recommendations'] ?? [] as $row) {
                        $strategy = trim((string) ($row['strategy'] ?? ''));
                        if ($strategy === '' || ! $this->matchesStem($strategy . ' ' . ($row['why_effective'] ?? ''), $stems)) {
                            continue;
                        }
                        $notes[] = $strategy . ' — ' . trim((string) ($row['why_effective'] ?? ''));
                        $items[] = [
                            'kind' => 'pedagogy',
                            'strategy' => $strategy,
                            'why_effective' => $row['why_effective'] ?? null,
                            'characteristics' => $row['concept_characteristics'] ?? [],
                        ];
                    }
                    if ($notes !== []) {
                        $body[] = "Delivery approach:\n• " . implode("\n• ", array_slice($notes, 0, 4));
                        $used[] = $sourceName;
                    }
                    break;

                case 'abilities':
                    $abilities = [];
                    foreach (array_slice($concept['abilities'] ?? [], 0, 6) as $row) {
                        $ability = trim((string) ($row['ability'] ?? ''));
                        if ($ability !== '') {
                            $abilities[] = $ability;
                            $items[] = ['kind' => 'ability', 'verb' => $row['verb'] ?? null, 'text' => $ability];
                        }
                    }
                    if ($abilities !== []) {
                        $body[] = "The learner should be able to:\n• " . implode("\n• ", $abilities);
                        $used[] = 'abilities';
                    }
                    break;

                case 'real_world_applications':
                    $stories = [];
                    foreach (array_slice($concept['real_world_applications'] ?? [], 0, 6) as $row) {
                        $example = trim((string) ($row['example'] ?? ''));
                        if ($example !== '') {
                            $stories[] = '(' . ($row['application_type'] ?? 'Application') . ') ' . $example;
                            $items[] = [
                                'kind' => 'application',
                                'application_type' => $row['application_type'] ?? null,
                                'relevance' => $row['relevance'] ?? null,
                                'text' => $example,
                            ];
                        }
                    }
                    if ($stories !== []) {
                        $body[] = "Real-world hooks:\n• " . implode("\n• ", $stories);
                        $used[] = 'real_world_applications';
                    }
                    break;

                case 'skills':
                    $skills = $this->deriver->skills($concept);
                    if ($skills !== []) {
                        $body[] = 'Skills practised: ' . implode(', ', $skills);
                        $used[] = 'skills';
                        foreach ($skills as $skill) {
                            $items[] = ['kind' => 'skill', 'text' => $skill];
                        }
                    }
                    break;
            }
        }

        return [
            'body' => implode("\n\n", $body),
            'sources_used' => $used,
            'items' => $items,
        ];
    }

    protected function matchesStem(string $haystack, array $stems): bool
    {
        $haystack = mb_strtolower($haystack);
        foreach ($stems as $stem) {
            if ($stem !== '' && str_contains($haystack, $stem)) {
                return true;
            }
        }

        return false;
    }

    // ══════════════════════════════════════════════════════════════════════
    // TYPE 2 — Practice Content: the 5-level Bloom ladder (spec §3)
    // ══════════════════════════════════════════════════════════════════════

    public function practiceLadder(int $semanticId, array $concept, array $header, array $chapter): array
    {
        $levels = config('pal_content.practice_levels', []);
        $minItems = (int) config('pal_content_model.ladder.min_items_per_level', 5);

        // Bucket the blueprint into rungs.
        $buckets = array_fill_keys(array_keys($levels), []);
        $unmapped = [];
        $ordinal = 0;

        foreach ($concept['assessment_blueprint'] ?? [] as $row) {
            $ordinal++;
            $bloomRaw = (string) ($row['bloom_level'] ?? '');
            $bloom = $this->deriver->bloomKey($bloomRaw);
            $level = PalVocabulary::practiceLevelForBloom($bloom);

            if ($level === null) {
                $unmapped[] = [
                    'ordinal' => $ordinal,
                    'raw_bloom' => $bloomRaw,
                    'question' => $row['recommended_question'] ?? null,
                    'reason' => $bloomRaw === ''
                        ? 'No Bloom level on the blueprint row.'
                        : "Bloom value '{$bloomRaw}' is not in the registered vocabulary.",
                ];
                continue;
            }

            $buckets[$level][] = $this->practiceNode($semanticId, $concept, $header, $chapter, $row, $bloom, $level, $ordinal);
        }

        $rungs = [];
        foreach ($levels as $level => $spec) {
            $items = $buckets[$level] ?? [];
            $count = count($items);

            $rungs[] = [
                'level' => (int) $level,
                'name' => $spec['name'],
                'bloom_level' => $spec['bloom_level'],
                'bloom_label' => config('pal_content.bloom_levels.' . $spec['bloom_level'] . '.label'),
                'tasks' => $spec['tasks'] ?? [],
                'scaffold' => $spec['scaffold'] ?? null,
                'h5p_recommended' => $spec['h5p'] ?? [],
                'gate' => $spec['gate'] ?? null,
                'items_total' => $count,
                // A gate needs enough attempts to be measurable at all.
                'min_items_required' => $minItems,
                'servable' => $count >= $minItems,
                'gap' => $count === 0 ? 'hard' : ($count < $minItems ? 'soft' : null),
                'items' => $items,
            ];
        }

        $ceiling = $this->bloomCeiling($concept);

        return [
            'label' => config('pal_content.content_types.practice.label', 'Practice Content'),
            'source' => 'assessment_blueprint',
            'total_items' => array_sum(array_map(fn ($r) => $r['items_total'], $rungs)),
            'servable_levels' => count(array_filter($rungs, fn ($r) => $r['servable'])),
            'hard_gaps' => array_values(array_map(
                fn ($r) => $r['level'],
                array_filter($rungs, fn ($r) => $r['gap'] === 'hard')
            )),
            'bloom_ceiling' => $ceiling,
            'practice_ceiling' => PalVocabulary::practiceLevelForBloom($ceiling),
            'hpc_ceilings' => config('pal_content.hpc_ceilings', []),
            'regression_rules' => config('pal_content.regression', []),
            'unmapped' => $unmapped,
            'levels' => $rungs,
        ];
    }

    protected function practiceNode(
        int $semanticId,
        array $concept,
        array $header,
        array $chapter,
        array $row,
        string $bloom,
        int $level,
        int $ordinal
    ): array {
        $assessmentType = (string) ($row['assessment_type'] ?? '');
        $delivery = $this->deliveryFor($assessmentType);
        $prompt = trim((string) ($row['recommended_question'] ?? ''));

        $node = [
            'node_key' => $this->nodeKey('practice', $semanticId, $concept['slug'], 'L' . $level . '.' . $ordinal),
            'content_id_ref' => $this->contentIdRef('PQ', $concept, $header, 'L' . $level . '_' . str_pad((string) $ordinal, 3, '0', STR_PAD_LEFT)),
            'content_type' => 'practice',
            'practice_level' => $level,
            'bloom_level' => $bloom,
            'dok_level' => is_numeric($row['dok_level'] ?? null) ? (int) $row['dok_level'] : null,
            'difficulty_1_to_5' => $this->deriver->difficultyFrom($row['difficulty'] ?? null),
            'marks' => is_numeric($row['marks'] ?? null) ? (int) $row['marks'] : null,
            'assessment_type_raw' => $assessmentType ?: null,
            'format' => $delivery['format'],
            'h5p_type' => $delivery['h5p_type'],
            'guessing_vulnerability' => $delivery['guessing'],
            'title' => $this->truncate($prompt, 120),
            'prompt' => $prompt,
            'body' => $prompt,
            'quality_status' => 'draft',
            'tagged_by' => 'derived',
            // Practice items come from the blueprint, which does not carry an
            // answer key. Flagged so nothing treats them as gradable.
            'has_answer_key' => false,
        ];

        $node['metadata'] = $this->deriver->derive($node, $concept, $header, $chapter);
        $node['missing_mandatory'] = $this->deriver->missingMandatory('practice', $node['metadata']);
        $node['completeness'] = $this->deriver->completeness($node['metadata']);

        return $node;
    }

    // ══════════════════════════════════════════════════════════════════════
    // TYPE 4 — Assessment Bank (spec §5)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * @param  array  $misconceptionEntries  projected misconceptions, used to
     *                                       resolve a distractor's misconception
     *                                       text back to its library tag
     */
    public function assessmentBank(int $semanticId, array $concept, array $header, array $chapter, array $misconceptionEntries): array
    {
        $tagByText = [];
        foreach ($misconceptionEntries as $entry) {
            $tagByText[$this->source->normalise((string) $entry['statement_source'])] = $entry['tag'];
            $tagByText[$this->source->normalise((string) $entry['title'])] = $entry['tag'];
        }

        $items = [];
        $ordinal = 0;
        foreach ($concept['assessment_rubrics'] ?? [] as $row) {
            $ordinal++;
            $items[] = $this->assessmentNode($semanticId, $concept, $header, $chapter, $row, $ordinal, $tagByText);
        }

        $byBloom = [];
        $byType = [];
        foreach ($items as $item) {
            $bloom = $item['bloom_level'] ?? 'unmapped';
            $byBloom[$bloom] = ($byBloom[$bloom] ?? 0) + 1;
            $type = $item['assessment_type_raw'] ?? 'unspecified';
            $byType[$type] = ($byType[$type] ?? 0) + 1;
        }

        $teachingNotes = null;
        foreach ($concept['assessment_rubrics'] ?? [] as $row) {
            if (is_array($row['_teaching_notes'] ?? null)) {
                $teachingNotes = $row['_teaching_notes'];
                break;
            }
        }

        return [
            'label' => config('pal_content.content_types.assessment.label', 'Assessment Bank'),
            'source' => 'assessment_rubrics',
            'total_items' => count($items),
            'calibrated_items' => count(array_filter($items, fn ($i) => $i['has_answer_key'])),
            'evidence_verified_items' => count(array_filter($items, fn ($i) => $i['evidence_verified'] === true)),
            'by_bloom' => $byBloom,
            'by_type' => $byType,
            'assessment_types' => config('pal_content.assessment_types', []),
            'teaching_notes' => $teachingNotes,
            // Honest gap: most chapters have a blueprint but no rubric pass yet.
            'gap' => $items === []
                ? 'No calibrated rubric items were extracted for this concept — the assessment bank is empty. The practice ladder still has blueprint questions; they carry no answer key.'
                : null,
            'items' => $items,
        ];
    }

    protected function assessmentNode(
        int $semanticId,
        array $concept,
        array $header,
        array $chapter,
        array $row,
        int $ordinal,
        array $tagByText
    ): array {
        $bloom = $this->deriver->bloomKey((string) ($row['bloom_level'] ?? ''));
        $assessmentType = (string) ($row['assessment_type'] ?? '');
        $delivery = $this->deliveryFor($assessmentType);
        $question = trim((string) ($row['question'] ?? ''));

        $answerKey = [];
        $distractorRationale = [];
        $misconceptionTags = [];

        foreach ($row['answer_key'] ?? [] as $option) {
            if (! is_array($option)) {
                continue;
            }
            $label = (string) ($option['option_label'] ?? '');
            $optionText = (string) ($option['option_text'] ?? '');
            $isCorrect = (bool) ($option['is_correct'] ?? false);

            $tested = trim((string) ($option['misconception_tested'] ?? ''));
            $tag = $tested === '' ? null : ($tagByText[$this->source->normalise($tested)] ?? $this->misconceptions->tagFor($tested));
            if ($tag !== null && ! in_array($tag, $misconceptionTags, true)) {
                $misconceptionTags[] = $tag;
            }

            $answerKey[] = [
                'option_label' => $label,
                'option_text' => $optionText,
                'is_correct' => $isCorrect,
                'rationale' => $option['rationale'] ?? null,
                'misconception_tested' => $tested ?: null,
                'misconception_tag' => $tag,
            ];

            if (! $isCorrect && $label !== '') {
                $distractorRationale["option_{$label}"] = $option['rationale'] ?? null;
            }
        }

        $node = [
            'node_key' => $this->nodeKey('assessment', $semanticId, $concept['slug'], (string) ($row['item_id'] ?? $ordinal)),
            'content_id_ref' => $this->contentIdRef('Q', $concept, $header, str_pad((string) $ordinal, 4, '0', STR_PAD_LEFT)),
            'content_type' => 'assessment',
            'item_id' => $row['item_id'] ?? null,
            'bloom_level' => $bloom,
            'bloom_level_raw' => $row['bloom_level'] ?? null,
            'practice_level' => PalVocabulary::practiceLevelForBloom($bloom),
            'dok_level' => is_numeric($row['dok_level'] ?? null) ? (int) $row['dok_level'] : null,
            'difficulty_1_to_5' => $this->deriver->difficultyFrom($row['difficulty'] ?? null),
            'marks' => is_numeric($row['marks'] ?? null) ? (int) $row['marks'] : null,
            'assessment_type_raw' => $assessmentType ?: null,
            'rubric_type' => $row['rubric_type'] ?? null,
            'format' => $delivery['format'],
            'h5p_type' => $delivery['h5p_type'],
            'guessing_vulnerability' => $delivery['guessing'],
            'title' => $this->truncate($question, 120),
            'prompt' => $question,
            'body' => $question,
            'skill_phrase' => $row['skill_phrase'] ?? null,
            'assessment_objectives' => $row['assessment_objectives'] ?? [],
            'source_evidence' => $row['source_evidence'] ?? [],
            'evidence_verified' => array_key_exists('evidence_verified', $row) ? (bool) $row['evidence_verified'] : null,
            'answer_key' => $answerKey,
            'distractor_rationale' => $distractorRationale ?: null,
            'misconception_tags' => $misconceptionTags,
            'acceptable_points' => $row['acceptable_points'] ?? [],
            'indicative_content' => $row['indicative_content'] ?? [],
            'level_descriptors' => $row['level_descriptors'] ?? [],
            'criteria' => $row['criteria'] ?? [],
            'threshold_conditions' => $row['threshold_conditions'] ?? [],
            'common_errors' => $row['common_errors'] ?? [],
            'has_answer_key' => $answerKey !== []
                || ! empty($row['acceptable_points'])
                || ! empty($row['level_descriptors'])
                || ! empty($row['criteria']),
            'quality_status' => 'draft',
            'tagged_by' => 'derived',
        ];

        // Which of spec §5.2's six assessment types this item can serve.
        $node['serves_assessment_types'] = $this->assessmentTypesFor($bloom);

        $node['metadata'] = $this->deriver->derive($node, $concept, $header, $chapter);
        $node['missing_mandatory'] = $this->deriver->missingMandatory('assessment', $node['metadata']);
        $node['completeness'] = $this->deriver->completeness($node['metadata']);

        return $node;
    }

    protected function assessmentTypesFor(?string $bloom): array
    {
        if ($bloom === null) {
            return [];
        }

        $out = [];
        foreach (config('pal_content.assessment_types', []) as $key => $spec) {
            if (in_array($bloom, $spec['blooms'] ?? [], true)) {
                $out[] = $key;
            }
        }

        return $out;
    }

    // ── Shared helpers ───────────────────────────────────────────────────────

    /** assessment_type → format + H5P recommendation, or nulls when unmapped. */
    public function deliveryFor(string $assessmentType): array
    {
        $map = config('pal_content_model.assessment_type_map', []);
        $key = mb_strtolower(trim($assessmentType));

        if (isset($map[$key])) {
            return $map[$key];
        }

        // Tolerate "MCQ (Assertion-Reason)" style compounds without inventing
        // a mapping for a type nobody registered.
        foreach ($map as $candidate => $spec) {
            if ($key !== '' && str_contains($key, $candidate)) {
                return $spec;
            }
        }

        return config('pal_content_model.assessment_type_default', ['format' => null, 'h5p_type' => null, 'guessing' => null]);
    }

    /** The spec's human-readable content_id (spec §2.2 / §5.1). */
    protected function contentIdRef(string $prefix, array $concept, array $header, string $suffix): string
    {
        $subject = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($header['subject_name'] ?? '')) ?? '');
        $grade = 'G' . (int) ($header['standard'] ?? 0);
        $conceptPart = strtoupper(str_replace('-', '_', $concept['slug']));

        return implode('_', array_filter([$prefix, substr($subject, 0, 6), $grade, substr($conceptPart, 0, 28), $suffix]));
    }

    protected function truncate(string $value, int $length): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        return mb_strlen($value) <= $length ? $value : mb_substr($value, 0, $length - 1) . '…';
    }
}
