<?php

namespace Tests\Unit;

use App\Services\lms\Intelligence\ConceptIntelligenceProjection;
use Tests\TestCase;

/**
 * Projection of the Concept Intelligence blobs into queryable rows.
 *
 * NO DATABASE. phpunit.xml has its sqlite/:memory: lines commented out (:24-25), so any
 * DB-touching test hits the LIVE shared vivek_erp used by 56 tenants. ConceptIntelligenceProjection::project()
 * is deliberately pure so it can be tested this way; only sourceRows() touches the DB and
 * it is not exercised here.
 *
 * Fixtures below are trimmed copies of REAL rows from semantic_intelligence (chapter 1013),
 * so the shapes are the ones actually in production rather than ones invented for a test.
 */
class ConceptIntelligenceProjectionTest extends TestCase
{
    private function row(array $overrides = []): array
    {
        return array_merge([
            'id' => 2,
            'chapter_id' => 1013,
            'sub_institute_id' => 1,
            'knowledge' => null,
            'ability' => null,
            'skill' => null,
            'competency' => null,
            'blooms_level' => null,
            'dok' => null,
            'prerequisites' => null,
            'misconceptions' => null,
            'real_world_applications' => null,
            'pedagogy' => null,
        ], $overrides);
    }

    public function test_it_projects_an_entry_with_its_owning_concept_and_confidence(): void
    {
        $rows = (new ConceptIntelligenceProjection())->project($this->row([
            'knowledge' => json_encode([[
                'knowledge' => 'Litmus is a natural acid-base indicator',
                'statement' => 'Litmus is a natural indicator used to test for acids and bases.',
                'knowledge_type' => 'Fact',
                'confidence' => 1,
                'concept_name' => 'Acid-Base Indicators',
            ]]),
        ]), '2026-09-08 00:00:00');

        $this->assertCount(1, $rows);
        $r = $rows[0];

        $this->assertSame('knowledge', $r['dimension']);
        $this->assertSame('Acid-Base Indicators', $r['concept_name']);
        $this->assertSame('Litmus is a natural acid-base indicator', $r['item_label']);
        $this->assertSame('litmus_is_a_natural_acid_base_indicator', $r['item_key']);
        $this->assertSame(1.0, $r['confidence']);
        $this->assertSame(1013, $r['chapter_id']);
        $this->assertSame(1, $r['sub_institute_id']);

        // The rest of the entry survives verbatim, so the projection is lossless.
        $attrs = json_decode($r['attributes'], true);
        $this->assertSame('Fact', $attrs['knowledge_type']);
        $this->assertArrayNotHasKey('concept_name', $attrs, 'concept_name is promoted to a column, not duplicated.');
    }

    /**
     * The single most error-prone mapping in this projection.
     *
     * On every other dimension `concept_name` is the concept the item belongs to. On a
     * prerequisite entry it is the PREREQUISITE, and `_parent_concept` is the concept
     * that requires it. Getting this backwards would silently mislabel 4,881 rows in a
     * way no type error would catch.
     */
    public function test_prerequisite_entries_are_inverted_relative_to_every_other_dimension(): void
    {
        $rows = (new ConceptIntelligenceProjection())->project($this->row([
            'prerequisites' => json_encode([[
                'concept_name' => 'Properties of Acids',
                '_parent_concept' => 'Acid-Base Indicators',
                'prerequisite_type' => 'Concept',
                'necessity' => 'Mandatory',
            ]]),
        ]));

        $this->assertCount(1, $rows);

        // The ITEM is the prerequisite...
        $this->assertSame('Properties of Acids', $rows[0]['item_label']);
        // ...and the OWNER is the concept that requires it.
        $this->assertSame('Acid-Base Indicators', $rows[0]['concept_name']);
        $this->assertSame('prerequisite', $rows[0]['dimension']);
    }

    public function test_bloom_takes_its_score_from_coverage_score_not_confidence(): void
    {
        $rows = (new ConceptIntelligenceProjection())->project($this->row([
            'blooms_level' => json_encode([
                ['level' => 'Understand', 'coverage_score' => 1, 'concept_name' => 'Acid-Base Indicators'],
                ['level' => 'Apply', 'coverage_score' => 0.5, 'concept_name' => 'Acid-Base Indicators'],
            ]),
        ]));

        $this->assertCount(2, $rows);
        $this->assertSame(1.0, $rows[0]['confidence']);
        $this->assertSame(0.5, $rows[1]['confidence']);
    }

    public function test_a_dimension_with_no_score_gets_null_not_zero(): void
    {
        // A missing score means "the extractor expressed none", which must not be
        // flattened into 0 — that would read as "we are certain this is worthless".
        $rows = (new ConceptIntelligenceProjection())->project($this->row([
            'skill' => json_encode([['skill' => 'Observation', 'concept_name' => 'Acid-Base Indicators']]),
        ]));

        $this->assertNull($rows[0]['confidence']);
    }

    public function test_repeated_items_within_one_chapter_collapse_to_one_row(): void
    {
        // Bloom levels repeat heavily across concepts in the real data (86 raw entries
        // collapse to 53 for chapter 1013). The unique key would reject the duplicate
        // anyway; collapsing here keeps the batch upsert deterministic.
        $rows = (new ConceptIntelligenceProjection())->project($this->row([
            'blooms_level' => json_encode([
                ['level' => 'Understand', 'coverage_score' => 1, 'concept_name' => 'A'],
                ['level' => 'Understand', 'coverage_score' => 1, 'concept_name' => 'A'],
                ['level' => 'Understand', 'coverage_score' => 1, 'concept_name' => 'B'],
            ]),
        ]));

        // Same level under a DIFFERENT concept is a different fact and is kept.
        $this->assertCount(2, $rows);
        $this->assertSame(['A', 'B'], array_column($rows, 'concept_name'));
    }

    public function test_an_entry_with_no_readable_label_is_skipped_not_defaulted(): void
    {
        // A row labelled "Untitled" would be indistinguishable from real content in a
        // query, which is worse than an absent row.
        $rows = (new ConceptIntelligenceProjection())->project($this->row([
            'skill' => json_encode([
                ['ability_refs' => ['x'], 'concept_name' => 'A'],   // no `skill` key
                ['skill' => '   ', 'concept_name' => 'A'],           // blank
                ['skill' => 'Observation', 'concept_name' => 'A'],   // the only real one
            ]),
        ]));

        $this->assertCount(1, $rows);
        $this->assertSame('Observation', $rows[0]['item_label']);
    }

    public function test_a_malformed_blob_yields_no_rows_rather_than_throwing(): void
    {
        // These columns hold JSON written by an LLM pipeline. One bad chapter must not
        // stop a projection run over 89 of them.
        $projection = new ConceptIntelligenceProjection();

        $this->assertSame([], $projection->project($this->row(['knowledge' => '{not json'])));
        $this->assertSame([], $projection->project($this->row(['knowledge' => ''])));
        $this->assertSame([], $projection->project($this->row(['knowledge' => 'null'])));
        $this->assertSame([], $projection->project($this->row()));
    }

    public function test_the_row_hash_is_stable_and_distinguishes_dimensions(): void
    {
        $projection = new ConceptIntelligenceProjection();
        $payload = json_encode([['skill' => 'Observation', 'concept_name' => 'A']]);

        $a = $projection->project($this->row(['skill' => $payload]))[0];
        $b = $projection->project($this->row(['skill' => $payload]))[0];
        $this->assertSame($a['row_hash'], $b['row_hash'], 'The same input must hash the same, or re-running duplicates rows.');

        // Same label on a different dimension must NOT collide.
        $c = $projection->project($this->row([
            'competency' => json_encode([['competency' => 'Observation', 'concept_name' => 'A']]),
        ]))[0];
        $this->assertNotSame($a['row_hash'], $c['row_hash']);
    }

    public function test_every_declared_dimension_is_projectable(): void
    {
        $this->assertSame(
            ['knowledge', 'ability', 'skill', 'competency', 'bloom', 'dok', 'prerequisite', 'misconception', 'real_world', 'pedagogy'],
            ConceptIntelligenceProjection::dimensions()
        );
        $this->assertCount(10, ConceptIntelligenceProjection::sourceColumns());
    }
}
