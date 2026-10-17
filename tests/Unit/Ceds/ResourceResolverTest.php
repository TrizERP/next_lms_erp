<?php

namespace Tests\Unit\Ceds;

use App\Services\Ceds\ResourceResolver;
use PHPUnit\Framework\TestCase;

/**
 * No application boot and no database. The fixtures reproduce the shape of the rows
 * found by the read-only inventory of chapters 8592 and 8593 (ids, categories, file
 * types, visibility); file names are rebuilt in the strict form the publisher writes.
 */
class ResourceResolverTest extends TestCase
{
    private const LABELS = ['D1' => 'Classroom Content', 'D2' => 'Classroom Presentation', 'D3' => 'Classroom Activity',
        'D4' => 'Prayogshala', 'D5' => 'Revision Note', 'D6' => 'Remedial Class'];

    private function row(int $id, int $chapter, ?string $category, string $type, ?int $showHide, string $filename, ?string $source = 'Claude AI', int $tenant = 1): array
    {
        return ['id' => $id, 'chapter_id' => $chapter, 'content_category' => $category, 'file_type' => $type, 'show_hide' => $showHide,
            'filename' => $filename, 'source' => $source, 'sub_institute_id' => $tenant, 'title' => "row $id", 'concept_id' => null, 'created_at' => '2026-10-10 10:00:00'];
    }

    private function lab(int $id, int $chapter, string $status, ?int $concept = null, string $generation = 'ready'): array
    {
        return ['id' => $id, 'chapter_id' => $chapter, 'topic_id' => 100 + $id, 'concept_id' => $concept, 'status' => $status,
            'generation_status' => $generation, 'lab_config' => '{"x":1}', 'sub_institute_id' => 1, 'title' => "lab $id"];
    }

    /** Mirrors chapter 8592: one live study version of each, older hidden ones beside it. */
    private function chapter8592(): array
    {
        $s = 'exploration_entering_the_world_of_secondary_science';

        return [
            $this->row(53869, 8592, 'Classroom Activity', 'pdf', 0, "classroom_activity_{$s}_1788"),
            $this->row(60600, 8592, 'Classroom Activity', 'pdf', 0, "classroom_activity_{$s}_1789"),
            $this->row(61404, 8592, 'Classroom Activity', 'pdf', 1, "study_classroom_activity_{$s}_all_0123456789ab.pdf"),
            $this->row(60602, 8592, 'Classroom Presentation', 'pdf', 0, "presentation_{$s}_17887"),
            $this->row(61076, 8592, 'Classroom Presentation', 'pptx', 0, "classroom_presentation_{$s}"),
            $this->row(61082, 8592, 'Classroom Presentation', 'pptx', 0, "classroom_presentation_{$s}_2"),
            $this->row(61401, 8592, 'Classroom Presentation', 'pptx', 1, "study_deck_{$s}_4207133.pptx"),
            $this->row(53870, 8592, 'Remedial Class', 'pdf', 0, "remedial_class_{$s}_178"),
            $this->row(61403, 8592, 'Remedial Class', 'pdf', 1, "study_remedial_class_{$s}_all_0123456789cd.pdf"),
            $this->row(60599, 8592, 'Revision Notes', 'pdf', 0, "revision_notes_{$s}_178"),
            $this->row(61402, 8592, 'Revision Notes', 'pdf', 1, "study_revision_notes_{$s}_all_0123456789ef.pdf"),
            // Other categories of the same chapter must be ignored.
            $this->row(61470, 8592, 'Teacher Training', 'pdf', 1, 'lms_teacher_resource_learning_outcomes_1.pdf'),
            $this->row(56476, 8592, null, 'link', 0, 'https://gamma.app/docs/x', 'Gamma AI'),
            // Another chapter must be ignored.
            $this->row(61406, 8593, 'Classroom Presentation', 'pptx', 1, 'study_deck_cell_the_building_block_of_life_23f14ac59c14.pptx'),
        ];
    }

    private function resolve(array $content, array $labs = [], int $chapter = 8592, array $tenants = [1]): array
    {
        return (new ResourceResolver())->resolve($chapter, $content, $labs, $tenants, self::LABELS);
    }

    public function test_live_study_versions_are_found_and_hidden_ones_are_never_offered(): void
    {
        $r = $this->resolve($this->chapter8592());

        $this->assertSame([61401], array_column($r['D2']['items'], 'id'));
        $this->assertSame([61404], array_column($r['D3']['items'], 'id'));
        $this->assertSame([61402], array_column($r['D5']['items'], 'id'));
        $this->assertSame([61403], array_column($r['D6']['items'], 'id'));
        foreach (['D2', 'D3', 'D5', 'D6'] as $d) {
            $this->assertSame(ResourceResolver::AVAILABLE, $r[$d]['status']);
            $this->assertFalse($r[$d]['can_generate'], "$d exists, so it must not be offered for generation");
        }
        $this->assertSame(3, $r['D2']['hidden_ignored']);
        $this->assertSame(2, $r['D3']['hidden_ignored']);
        $this->assertSame(1, $r['D5']['hidden_ignored']);
        $this->assertSame(1, $r['D6']['hidden_ignored']);
    }

    public function test_items_are_labelled_with_their_kind_and_scope(): void
    {
        $r = $this->resolve($this->chapter8592());
        $this->assertSame('study_deck', $r['D2']['items'][0]['kind']);
        $this->assertSame('study_document', $r['D5']['items'][0]['kind']);
        $this->assertSame('chapter', $r['D5']['items'][0]['scope']);
        $this->assertSame('content_master', $r['D5']['items'][0]['source']);
    }

    public function test_a_concept_scoped_document_is_reported_as_not_chapter_wide(): void
    {
        $rows = [$this->row(70001, 8592, 'Revision Notes', 'pdf', 1, 'study_revision_notes_x_p1a2b3c_0123456789ab.pdf')];
        $this->assertSame('concepts', $this->resolve($rows)['D5']['items'][0]['scope']);
    }

    public function test_d1_is_made_of_the_existing_presentation_and_revision_notes_and_nothing_else(): void
    {
        $r = $this->resolve($this->chapter8592());

        $this->assertSame(ResourceResolver::AVAILABLE, $r['D1']['status']);
        $this->assertSame([61401, 61402], array_column($r['D1']['items'], 'id'), 'the live deck and the live revision notes, by id');
        $this->assertSame(['presentation', 'revision_notes'], array_column($r['D1']['items'], 'component'));
        $this->assertSame(['presentation' => [61401], 'revision_notes' => [61402]], array_map(fn ($c) => $c['item_ids'], $r['D1']['components']));
        $this->assertFalse($r['D1']['can_generate'], 'D1 exists, so nothing is offered for generation');
        // D1 is the same rows as D2 and D5, never a copy of them.
        $this->assertSame(array_column($r['D2']['items'], 'id'), [61401]);
        $this->assertSame(array_column($r['D5']['items'], 'id'), [61402]);
        $this->assertSame(3 + 1, $r['D1']['hidden_ignored'], 'hidden older versions counted, never offered');
        $this->assertNotContains(60602, array_column($r['D1']['items'], 'id'));
        $this->assertNotContains(60599, array_column($r['D1']['items'], 'id'));
    }

    public function test_d1_keeps_the_direct_open_kind_of_the_deck(): void
    {
        $item = $this->resolve($this->chapter8592())['D1']['items'][0];
        $this->assertSame('study_deck', $item['kind'], 'same kind as D2, so the same Open link applies');
        $this->assertSame('content_master', $item['source']);
    }

    public function test_d1_is_partial_when_only_one_of_the_two_exists(): void
    {
        $onlyDeck = array_values(array_filter($this->chapter8592(), fn ($r) => $r['id'] !== 61402));
        $r = $this->resolve($onlyDeck)['D1'];
        $this->assertSame('partial', $r['status']);
        $this->assertSame([61401], array_column($r['items'], 'id'));
        $this->assertStringContainsString('Revision Notes', $r['note']);
        $this->assertSame(ResourceResolver::MISSING, $r['components']['revision_notes']['status']);
        $this->assertFalse($r['can_generate']);

        $onlyNotes = array_values(array_filter($this->chapter8592(), fn ($r) => $r['id'] !== 61401));
        $r = $this->resolve($onlyNotes)['D1'];
        $this->assertSame('partial', $r['status']);
        $this->assertSame([61402], array_column($r['items'], 'id'));
        $this->assertStringContainsString('Classroom Presentation', $r['note']);
    }

    public function test_d1_is_missing_only_when_neither_exists(): void
    {
        $none = array_values(array_filter($this->chapter8592(), fn ($r) => !in_array($r['id'], [61401, 61402], true)));
        $r = $this->resolve($none)['D1'];
        $this->assertSame(ResourceResolver::MISSING, $r['status']);
        $this->assertSame([], $r['items']);
        $this->assertTrue($r['can_generate']);
    }

    public function test_hidden_or_other_school_rows_never_make_d1_available(): void
    {
        $rows = [
            $this->row(70001, 8592, 'Classroom Presentation', 'pptx', 0, 'study_deck_x_1.pptx'),                                   // hidden
            $this->row(70002, 8592, 'Revision Notes', 'pdf', 1, 'study_revision_notes_x_all_0123456789ab.pdf', 'Claude AI', 9),   // other school
            $this->row(70003, 8593, 'Revision Notes', 'pdf', 1, 'study_revision_notes_x_all_0123456789cd.pdf'),                   // other chapter
        ];
        $this->assertSame(ResourceResolver::MISSING, $this->resolve($rows)['D1']['status']);
        // The same school row does count once the school may read that tenant.
        $this->assertSame(ResourceResolver::AVAILABLE, $this->resolve($rows, [], 8592, [1, 9])['D1']['components']['revision_notes']['status']);
    }

    public function test_d2_and_d5_are_unchanged_by_the_d1_mapping(): void
    {
        $r = $this->resolve($this->chapter8592());
        $this->assertSame('study_deck', $r['D2']['items'][0]['kind']);
        $this->assertArrayNotHasKey('component', $r['D2']['items'][0], 'only D1 items carry a component tag');
        $this->assertArrayNotHasKey('components', $r['D2']);
        $this->assertSame(3, $r['D2']['hidden_ignored']);
        $this->assertSame(1, $r['D5']['hidden_ignored']);
    }

    public function test_labs_in_review_are_pending_review_never_approved(): void
    {
        $labs = [$this->lab(1, 8592, 'review', 7189), $this->lab(5, 8592, 'review'), $this->lab(3, 8593, 'review', 7315)];
        $d4 = $this->resolve($this->chapter8592(), $labs)['D4'];

        $this->assertSame(ResourceResolver::PENDING_REVIEW, $d4['status']);
        $this->assertSame([1, 5], array_column($d4['items'], 'id'), 'only this chapter\'s labs');
        foreach ($d4['items'] as $item) {
            $this->assertSame('Pending Review', $item['review_label']);
            $this->assertSame(ResourceResolver::PENDING_REVIEW, $item['status']);
        }
        $this->assertFalse($d4['can_generate']);
        $this->assertSame('concept', $d4['items'][0]['scope']);
        $this->assertSame('topic', $d4['items'][1]['scope']);
    }

    public function test_a_published_lab_is_available_and_listed_before_pending_ones(): void
    {
        $labs = [$this->lab(1, 8592, 'review'), $this->lab(2, 8592, 'published')];
        $d4 = $this->resolve([], $labs)['D4'];

        $this->assertSame(ResourceResolver::AVAILABLE, $d4['status']);
        $this->assertSame([2, 1], array_column($d4['items'], 'id'));
        $this->assertNull($d4['items'][0]['review_label']);
        $this->assertSame('Pending Review', $d4['items'][1]['review_label']);
    }

    public function test_placeholder_labs_that_are_not_real_yet_are_not_shown(): void
    {
        $generating = $this->lab(9, 8592, 'review', null, 'generating');
        $generating['lab_config'] = null;
        $d4 = $this->resolve([], [$generating, $this->lab(10, 8592, 'draft')])['D4'];

        $this->assertSame(ResourceResolver::MISSING, $d4['status']);
        $this->assertSame(2, $d4['unavailable_ignored']);
        $this->assertTrue($d4['can_generate']);
    }

    public function test_another_school_s_rows_are_not_visible(): void
    {
        $rows = [$this->row(80001, 8592, 'Revision Notes', 'pdf', 1, 'study_revision_notes_x_all_0123456789ab.pdf', 'Claude AI', 7)];
        $this->assertSame(ResourceResolver::MISSING, $this->resolve($rows, [], 8592, [1])['D5']['status']);
        $this->assertSame(ResourceResolver::AVAILABLE, $this->resolve($rows, [], 8592, [1, 7])['D5']['status']);
    }

    public function test_a_wrongly_named_or_wrongly_filed_row_is_not_taken_for_a_study_document(): void
    {
        $rows = [
            // Right category, wrong file type: a visible legacy item, not a study document.
            $this->row(81001, 8592, 'Revision Notes', 'link', 1, 'https://example.test/notes'),
            // A study file name filed under the wrong category does not count for revision notes.
            $this->row(81002, 8592, 'Remedial Class', 'pdf', 1, 'study_revision_notes_x_all_0123456789ab.pdf'),
        ];
        $r = $this->resolve($rows);
        $this->assertSame('legacy', $r['D5']['items'][0]['kind']);
        $this->assertSame('legacy', $r['D6']['items'][0]['kind']);
    }

    public function test_study_items_come_before_legacy_ones(): void
    {
        $rows = [
            $this->row(90001, 8592, 'Revision Notes', 'pdf', 1, 'revision_notes_old_178'),
            $this->row(90002, 8592, 'Revision Notes', 'pdf', 1, 'study_revision_notes_x_all_0123456789ab.pdf'),
        ];
        $this->assertSame([90002, 90001], array_column($this->resolve($rows)['D5']['items'], 'id'));
    }

    public function test_chapter_8593_resolves_independently(): void
    {
        $r = $this->resolve($this->chapter8592(), [], 8593);
        $this->assertSame([61406], array_column($r['D2']['items'], 'id'));
        $this->assertSame(ResourceResolver::MISSING, $r['D3']['status']);
    }

    public function test_resolution_does_not_modify_its_input(): void
    {
        $content = $this->chapter8592();
        $labs = [$this->lab(1, 8592, 'review')];
        $before = [$content, $labs];
        $this->resolve($content, $labs);
        $this->assertSame($before, [$content, $labs]);
    }

    public function test_all_six_formats_are_always_returned_with_labels(): void
    {
        $r = $this->resolve([]);
        $this->assertSame(['D1', 'D2', 'D3', 'D4', 'D5', 'D6'], array_keys($r));
        $this->assertSame('Prayogshala', $r['D4']['label']);
        foreach ($r as $x) {
            $this->assertSame(ResourceResolver::MISSING, $x['status']);
            $this->assertTrue($x['can_generate']);
        }
    }
}
