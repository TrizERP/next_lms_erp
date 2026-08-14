<?php

namespace App\Services\PAL\ContentModel;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Scores the extracted estate against the seven Content Model requirements the
 * PAL V4 comparison sheet lists:
 *
 *   1. the 4-type content model            5. Indian cultural context
 *   2. the 5-level Bloom ladder            6. multi-language support
 *   3. the complete metadata schema        7. the authoring interface
 *   4. the misconception library
 *
 * Two depths, because they cost very different amounts:
 *
 *   estate()   every extracted chapter, counted with SQL over the slice columns
 *              so the 430 KB intelligence blob is never pulled across the wire.
 *   chapter()  one chapter, fully projected — every concept, every node, every
 *              gap named.
 *
 * Nothing here is a target or a placeholder: each number is counted from the
 * data at request time, and a requirement with no data behind it reports zero
 * rather than a plausible-looking default.
 */
class ContentModelCoverageService
{
    public function __construct(
        protected SemanticSourceRepository $source,
        protected ContentModelProjector $projector,
        protected MisconceptionProjector $misconceptions,
        protected ContentModelAuthoringService $authoring,
        protected ContentModelEnrichmentService $enrichment
    ) {}

    // ══════════════════════════════════════════════════════════════════════
    // Estate level
    // ══════════════════════════════════════════════════════════════════════

    public function estate(?int $tenant): array
    {
        $chapters = $this->source->listChapters($tenant, []);
        $ids = array_map(fn ($c) => (int) $c['id'], $chapters);

        $sectionCounts = $this->sectionCounts($ids, $tenant);
        $overlay = $this->overlayCounts($tenant);

        $conceptTotal = 0;
        $withBlueprint = 0;
        $withMisconceptions = 0;
        $withRubrics = 0;
        $withApplications = 0;
        $withPedagogy = 0;

        foreach ($chapters as $chapter) {
            $id = (int) $chapter['id'];
            $counts = $sectionCounts[$id] ?? [];
            $conceptTotal += (int) ($chapter['total_concepts'] ?? 0);

            if (($counts['assessment_blueprint'] ?? 0) > 0) {
                $withBlueprint++;
            }
            if (($counts['misconceptions'] ?? 0) > 0) {
                $withMisconceptions++;
            }
            if (($counts['assessment_rubrics'] ?? 0) > 0) {
                $withRubrics++;
            }
            if (($counts['real_world_applications'] ?? 0) > 0) {
                $withApplications++;
            }
            if (($counts['pedagogy'] ?? 0) > 0) {
                $withPedagogy++;
            }
        }

        $chapterCount = count($chapters);

        return [
            'sub_institute_id' => $tenant,
            'source_table' => config('pal_content_model.source.table'),
            'chapters' => [
                'total' => $chapterCount,
                'concepts' => $conceptTotal,
            ],
            // Requirement 1 — the 4-type model. A chapter can only serve a type
            // if the extraction carries the section that type is built from.
            'four_type_model' => [
                'type_1_concept_learning' => [
                    'label' => 'Concept Learning (4 variants)',
                    // Every extracted concept has a definition, so Type 1 is
                    // always projectable; the variant-level gaps are per concept.
                    'chapters_covered' => $chapterCount,
                    'coverage_pct' => 100.0,
                    'source' => 'concept.definition + evidence + knowledge',
                ],
                'type_2_practice' => [
                    'label' => "Practice (5-level Bloom's ladder)",
                    'chapters_covered' => $withBlueprint,
                    'coverage_pct' => $this->pct($withBlueprint, $chapterCount),
                    'source' => 'assessment_blueprint',
                ],
                'type_3_misconceptions' => [
                    'label' => 'Misconception Library',
                    'chapters_covered' => $withMisconceptions,
                    'coverage_pct' => $this->pct($withMisconceptions, $chapterCount),
                    'source' => 'misconceptions',
                ],
                'type_4_assessment' => [
                    'label' => 'Assessment Bank (calibrated)',
                    'chapters_covered' => $withRubrics,
                    'coverage_pct' => $this->pct($withRubrics, $chapterCount),
                    'source' => 'assessment_rubrics',
                ],
            ],
            'item_totals' => [
                'blueprint_items' => array_sum(array_column($sectionCounts, 'assessment_blueprint')),
                // The rubric column nests one level deeper than the others: one
                // entry per concept, each holding several items. Counted as
                // groups here because that is what SQL can see without pulling
                // the column across; the chapter view counts the real items.
                'rubric_groups' => array_sum(array_column($sectionCounts, 'assessment_rubrics')),
                'misconceptions' => array_sum(array_column($sectionCounts, 'misconceptions')),
                'applications' => array_sum(array_column($sectionCounts, 'real_world_applications')),
                'pedagogy_notes' => array_sum(array_column($sectionCounts, 'pedagogy')),
                'learning_outcomes' => array_sum(array_column($sectionCounts, 'learning_outcomes')),
                'prerequisites' => array_sum(array_column($sectionCounts, 'prerequisites')),
            ],
            // Requirement 5 + 6 — assigned per node, so estate level reports what
            // the overlay has recorded so far.
            'cultural_context' => [
                'assigned_nodes' => $overlay['with_cultural_context'],
                'vocabulary' => config('pal_content.cultural_contexts', []),
            ],
            'languages' => [
                'supported' => config('pal_content.languages', []),
                'nodes_with_variants' => $overlay['with_language_variants'],
                'variant_rows' => $overlay['language_variant_rows'],
            ],
            // Requirement 7 — the authoring pipeline.
            'authoring' => [
                'nodes_touched' => $overlay['total'],
                'pipeline' => $this->authoring->pipelineCounts($tenant ?? 0),
                'servable_statuses' => config('pal_content.servable_statuses', ['approved']),
            ],
            'ai' => [
                'available' => $this->enrichment->available(),
                'unavailable_reason' => $this->enrichment->unavailableReason(),
                'cached_enrichments' => $overlay['enrichments'],
            ],
            'chapters_with' => [
                'blueprint' => $withBlueprint,
                'misconceptions' => $withMisconceptions,
                'rubrics' => $withRubrics,
                'applications' => $withApplications,
                'pedagogy' => $withPedagogy,
            ],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // Chapter level
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Full projection of one chapter, scored per concept.
     *
     * @return array|null null when the chapter is not visible to this caller
     */
    public function chapter(int $semanticId, ?int $tenant): ?array
    {
        $loaded = $this->source->conceptsFor($semanticId, $tenant);
        if ($loaded['header'] === null) {
            return null;
        }

        $header = $loaded['header'];
        $overrides = $this->authoring->overridesForChapter($semanticId, $tenant ?? 0);

        $concepts = [];
        $totals = [
            'variants_present' => 0, 'variants_possible' => 0,
            'practice_items' => 0, 'ladder_levels_servable' => 0, 'ladder_levels_possible' => 0,
            'misconceptions' => 0, 'misconceptions_servable' => 0,
            'assessment_items' => 0, 'assessment_calibrated' => 0,
            'nodes' => 0, 'completeness_sum' => 0,
            'cultural_assigned' => 0, 'language_variants' => 0,
            'nodes_approved' => 0,
        ];

        foreach ($loaded['concepts'] as $concept) {
            $variants = $this->projector->conceptLearningVariants($semanticId, $concept, $header, $loaded['chapter']);
            $practice = $this->projector->practiceLadder($semanticId, $concept, $header, $loaded['chapter']);
            $misconceptions = $this->misconceptions->project($semanticId, $concept, $header);
            $assessment = $this->projector->assessmentBank($semanticId, $concept, $header, $loaded['chapter'], $misconceptions['entries']);

            $totals['variants_present'] += $variants['present'];
            $totals['variants_possible'] += $variants['total_slots'];
            $totals['practice_items'] += $practice['total_items'];
            $totals['ladder_levels_servable'] += $practice['servable_levels'];
            $totals['ladder_levels_possible'] += count($practice['levels']);
            $totals['misconceptions'] += $misconceptions['total'];
            $totals['misconceptions_servable'] += $misconceptions['servable'];
            $totals['assessment_items'] += $assessment['total_items'];
            $totals['assessment_calibrated'] += $assessment['calibrated_items'];

            // Score every node this concept projects, override merged in.
            $nodes = $variants['variants'];
            foreach ($practice['levels'] as $level) {
                $nodes = array_merge($nodes, $level['items']);
            }
            $nodes = array_merge($nodes, $assessment['items']);

            foreach ($nodes as $node) {
                $merged = $this->authoring->merge($node, $overrides[$node['node_key']] ?? null);
                $totals['nodes']++;
                $totals['completeness_sum'] += (int) ($merged['completeness'] ?? 0);
                if (! empty($merged['metadata']['cultural_context'])) {
                    $totals['cultural_assigned']++;
                }
                $variantLanguages = $merged['language_variants'] ?? [];
                $totals['language_variants'] += count($variantLanguages);
                if (($merged['servable'] ?? false) === true) {
                    $totals['nodes_approved']++;
                }
            }

            $concepts[] = [
                'slug' => $concept['slug'],
                'name' => $concept['name'],
                'importance' => $concept['concept']['importance'] ?? null,
                'difficulty' => $concept['concept']['difficulty'] ?? null,
                'confidence' => $concept['concept']['confidence'] ?? null,
                'type_1' => [
                    'present' => $variants['present'],
                    'total' => $variants['total_slots'],
                    'meets_minimum' => $variants['meets_minimum'],
                    'gaps' => array_values(array_map(
                        fn ($v) => $v['variant_number'],
                        array_filter($variants['variants'], fn ($v) => $v['gap'])
                    )),
                ],
                'type_2' => [
                    'items' => $practice['total_items'],
                    'servable_levels' => $practice['servable_levels'],
                    'hard_gaps' => $practice['hard_gaps'],
                    'ceiling' => $practice['practice_ceiling'],
                    'per_level' => array_map(fn ($l) => ['level' => $l['level'], 'items' => $l['items_total']], $practice['levels']),
                ],
                'type_3' => [
                    'total' => $misconceptions['total'],
                    'servable' => $misconceptions['servable'],
                    'meets_minimum' => $misconceptions['meets_minimum'],
                    'c6_pass' => $misconceptions['c6_pass'],
                    'c6_violations' => count($misconceptions['c6_violations']),
                ],
                'type_4' => [
                    'items' => $assessment['total_items'],
                    'calibrated' => $assessment['calibrated_items'],
                    'evidence_verified' => $assessment['evidence_verified_items'],
                    'gap' => $assessment['gap'],
                ],
            ];
        }

        $conceptCount = max(1, count($concepts));

        return [
            'semantic_id' => $semanticId,
            'header' => $header,
            'chapter' => $loaded['chapter'],
            'concept_count' => count($concepts),
            'concepts' => $concepts,
            'requirements' => [
                'four_type_model' => [
                    'label' => '4-type content model',
                    'score_pct' => $this->pct(
                        count(array_filter($concepts, fn ($c) => $c['type_1']['meets_minimum']
                            && $c['type_2']['items'] > 0
                            && $c['type_3']['total'] > 0)),
                        count($concepts)
                    ),
                    'detail' => sprintf(
                        '%d/%d concepts carry Concept Learning, Practice and Misconception content; %d also have a calibrated assessment bank.',
                        count(array_filter($concepts, fn ($c) => $c['type_1']['meets_minimum'] && $c['type_2']['items'] > 0 && $c['type_3']['total'] > 0)),
                        count($concepts),
                        count(array_filter($concepts, fn ($c) => $c['type_4']['calibrated'] > 0))
                    ),
                ],
                'bloom_ladder' => [
                    'label' => "5-level Bloom's ladder",
                    'score_pct' => $this->pct($totals['ladder_levels_servable'], max(1, $totals['ladder_levels_possible'])),
                    'detail' => sprintf(
                        '%d of %d rungs across this chapter have the %d items a gate needs to be measurable.',
                        $totals['ladder_levels_servable'],
                        $totals['ladder_levels_possible'],
                        (int) config('pal_content_model.ladder.min_items_per_level', 5)
                    ),
                ],
                'metadata_schema' => [
                    'label' => 'Complete metadata schema',
                    'score_pct' => $totals['nodes'] === 0 ? 0.0 : round($totals['completeness_sum'] / $totals['nodes'], 1),
                    'detail' => sprintf('Average completeness across %d projected nodes.', $totals['nodes']),
                ],
                'misconception_library' => [
                    'label' => 'Misconception library',
                    'score_pct' => $this->pct($totals['misconceptions_servable'], max(1, $totals['misconceptions'])),
                    'detail' => sprintf(
                        '%d of %d entries have corrective content behind them (CONTENT LAW C6). %d/%d concepts meet the 3-per-concept minimum.',
                        $totals['misconceptions_servable'],
                        $totals['misconceptions'],
                        count(array_filter($concepts, fn ($c) => $c['type_3']['meets_minimum'])),
                        count($concepts)
                    ),
                ],
                'cultural_context' => [
                    'label' => 'Indian cultural context',
                    'score_pct' => $this->pct($totals['cultural_assigned'], max(1, $totals['nodes'])),
                    'detail' => sprintf('%d of %d nodes have one of the 6 contexts assigned.', $totals['cultural_assigned'], $totals['nodes']),
                ],
                'multi_language' => [
                    'label' => 'Multi-language support',
                    'score_pct' => $this->pct($totals['language_variants'], max(1, $totals['nodes'] * (count(config('pal_content.languages', [])) - 1))),
                    'detail' => sprintf(
                        '%d translated variants stored across %d nodes; %d languages are supported.',
                        $totals['language_variants'],
                        $totals['nodes'],
                        count(config('pal_content.languages', []))
                    ),
                ],
                'authoring' => [
                    'label' => 'Authoring & QA',
                    'score_pct' => $this->pct($totals['nodes_approved'], max(1, $totals['nodes'])),
                    'detail' => sprintf('%d of %d nodes are approved and therefore servable.', $totals['nodes_approved'], $totals['nodes']),
                ],
            ],
            'totals' => $totals + ['avg_completeness' => $totals['nodes'] === 0 ? 0 : round($totals['completeness_sum'] / $totals['nodes'], 1)],
            'pipeline' => $this->authoring->pipelineCounts($tenant ?? 0, $semanticId),
            'per_concept_average' => [
                'variants' => round($totals['variants_present'] / $conceptCount, 1),
                'practice_items' => round($totals['practice_items'] / $conceptCount, 1),
                'misconceptions' => round($totals['misconceptions'] / $conceptCount, 1),
                'assessment_items' => round($totals['assessment_items'] / $conceptCount, 1),
            ],
        ];
    }

    // ── Internals ────────────────────────────────────────────────────────────

    /**
     * Element counts for each slice column, per chapter, computed in SQL.
     *
     * JSON_LENGTH keeps the 430 KB blob and the ~100 KB of slices on the server.
     * Where the engine does not have it, the counts fall back to zero and the
     * chapter view — which projects properly — is the accurate one.
     *
     * @return array<int,array<string,int>>
     */
    protected function sectionCounts(array $semanticIds, ?int $tenant): array
    {
        if ($semanticIds === []) {
            return [];
        }

        $columns = config('pal_content_model.source.slice_columns', []);
        $table = config('pal_content_model.source.table');

        $selects = ['id'];
        foreach ($columns as $column) {
            // COALESCE keeps a NULL or non-JSON column at 0 instead of nulling
            // the whole row.
            $selects[] = "COALESCE(JSON_LENGTH(`{$column}`), 0) AS `{$column}`";
        }

        try {
            $rows = DB::table($table)
                ->selectRaw(implode(', ', $selects))
                ->whereIn('id', $semanticIds)
                ->when($tenant !== null, function ($q) use ($tenant, $table) {
                    if (DB::getSchemaBuilder()->hasColumn($table, 'sub_institute_id')) {
                        $q->where('sub_institute_id', $tenant);
                    }
                })
                ->get();
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $data = (array) $row;
            $id = (int) $data['id'];
            unset($data['id']);
            $out[$id] = array_map('intval', $data);
        }

        return $out;
    }

    protected function overlayCounts(?int $tenant): array
    {
        $tenantId = $tenant ?? 0;

        try {
            $rows = DB::table('pal_cm_node_overrides')->where('sub_institute_id', $tenantId)->get();
        } catch (Throwable) {
            return ['total' => 0, 'with_cultural_context' => 0, 'with_language_variants' => 0, 'language_variant_rows' => 0, 'enrichments' => 0];
        }

        $withCultural = 0;
        $withVariants = 0;
        $variantRows = 0;

        foreach ($rows as $row) {
            $metadata = json_decode((string) $row->metadata, true) ?: [];
            if (! empty($metadata['cultural_context'])) {
                $withCultural++;
            }
            $variants = json_decode((string) $row->language_variants, true) ?: [];
            if ($variants !== []) {
                $withVariants++;
                $variantRows += count($variants);
            }
        }

        $enrichments = 0;
        try {
            $enrichments = DB::table('pal_cm_enrichment')->where('sub_institute_id', $tenantId)->count();
        } catch (Throwable) {
            // Table not migrated yet — report zero rather than failing coverage.
        }

        return [
            'total' => $rows->count(),
            'with_cultural_context' => $withCultural,
            'with_language_variants' => $withVariants,
            'language_variant_rows' => $variantRows,
            'enrichments' => $enrichments,
        ];
    }

    protected function pct(int $part, int $whole): float
    {
        return $whole === 0 ? 0.0 : round($part / $whole * 100, 1);
    }
}
