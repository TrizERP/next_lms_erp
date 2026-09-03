<?php

namespace Tests\Feature\Eso;

use App\Services\Eso\EsoPolicyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * EsoPolicyService::chapterKnowledgeMap() — the whole chapter's real
 * concept-relationship graph, on the same ESO mastery pipeline as the rest
 * of the student dashboard. Same fixture/helper conventions as
 * EsoChapterDashboardTest.
 */
class EsoKnowledgeMapTest extends TestCase
{
    use DatabaseTransactions;

    private EsoPolicyService $policy;

    private int $subInstituteId;

    private int $studentId;

    private int $subjectId;

    private int $standardId;

    private int $chapterId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = app(EsoPolicyService::class);

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'ESO Knowledge Map Test School',
            'ShortCode' => 'ESOK' . random_int(1000, 9999),
            'ContactPerson' => 'Test Contact',
            'Mobile' => '9999999999',
            'Email' => 'eso-knowledge-map-test@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'eso-knowledge-map-test@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);

        $this->studentId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Eso', 'last_name' => 'Knowledge Map Learner',
            'sub_institute_id' => $this->subInstituteId, 'file_size' => '', 'file_type' => '',
        ]);

        $this->subjectId = (int) DB::table('subject')->insertGetId([
            'subject_name' => 'ESO Knowledge Map Test Subject ' . random_int(1000, 9999),
            'sub_institute_id' => $this->subInstituteId, 'status' => 1, 'created_at' => now(),
        ]);

        $this->standardId = (int) DB::table('standard')->insertGetId([
            'grade_id' => 1, 'name' => '9', 'short_name' => '9', 'sort_order' => 1,
            'sub_institute_id' => $this->subInstituteId,
        ]);

        $this->chapterId = (int) DB::table('chapter_master')->insertGetId([
            'subject_id' => $this->subjectId, 'standard_id' => $this->standardId,
            'sub_institute_id' => $this->subInstituteId, 'chapter_name' => 'ESO Knowledge Map Test Chapter',
            'created_at' => now(),
        ]);
    }

    private function makeConcept(string $name): int
    {
        return (int) DB::table('lms_concept')->insertGetId([
            'name' => $name,
            'subject_id' => $this->subjectId,
            'standard_id' => $this->standardId,
            'chapter_id' => $this->chapterId,
            'sub_institute_id' => $this->subInstituteId,
            'mastery_threshold' => 80,
            'syear' => 2026,
            'created_at' => now(),
        ]);
    }

    /** A concept in a DIFFERENT chapter, to prove cross-chapter edges are excluded. */
    private function makeConceptInOtherChapter(string $name): int
    {
        $otherChapterId = (int) DB::table('chapter_master')->insertGetId([
            'subject_id' => $this->subjectId, 'standard_id' => $this->standardId,
            'sub_institute_id' => $this->subInstituteId, 'chapter_name' => 'Other Chapter ' . random_int(1000, 9999),
            'created_at' => now(),
        ]);

        return (int) DB::table('lms_concept')->insertGetId([
            'name' => $name,
            'subject_id' => $this->subjectId,
            'standard_id' => $this->standardId,
            'chapter_id' => $otherChapterId,
            'sub_institute_id' => $this->subInstituteId,
            'mastery_threshold' => 80,
            'syear' => 2026,
            'created_at' => now(),
        ]);
    }

    private function makeKNode(int $conceptId): int
    {
        return (int) DB::table('pal_concept_nodes')->insertGetId([
            'concept_id' => $conceptId,
            'sub_institute_id' => $this->subInstituteId,
            'node_type' => 'K',
            'label' => 'Knowledge node',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function relate(int $fromConceptId, int $toConceptId, string $relationType): void
    {
        DB::table('pal_concept_relations')->insert([
            'from_concept_id' => $fromConceptId,
            'to_concept_id' => $toConceptId,
            'relation_type' => $relationType,
            'sub_institute_id' => $this->subInstituteId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeMisconception(int $conceptId): void
    {
        DB::table('pal_misconception_library')->insert([
            'tag' => 'eso_knowledge_map_test_' . random_int(100000, 999999),
            'concept_ref_id' => $conceptId,
            'sub_institute_id' => $this->subInstituteId,
            'description' => 'A test misconception.',
            'quality_status' => 'approved',
            'priority_level' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_unknown_concept_returns_null(): void
    {
        $this->assertNull($this->policy->chapterKnowledgeMap($this->studentId, 999999999, $this->subInstituteId));
    }

    public function test_a_concept_with_no_nodes_at_all_returns_null(): void
    {
        $noNodes = $this->makeConcept('No Nodes Concept');
        $this->assertNull($this->policy->chapterKnowledgeMap($this->studentId, $noNodes, $this->subInstituteId));
    }

    public function test_the_whole_chapter_is_returned_with_the_requested_concept_marked_current(): void
    {
        $a = $this->makeConcept('Concept A');
        $this->makeKNode($a);
        $b = $this->makeConcept('Concept B');
        $this->makeKNode($b);
        $c = $this->makeConcept('Concept C');
        $this->makeKNode($c);

        $map = $this->policy->chapterKnowledgeMap($this->studentId, $b, $this->subInstituteId);

        $this->assertSame($this->chapterId, $map['chapter_id']);
        $this->assertSame($b, $map['current_concept_id']);
        $this->assertCount(3, $map['concepts']);

        $current = collect($map['concepts'])->firstWhere('concept_id', $b);
        $this->assertTrue($current['is_current']);
        $others = collect($map['concepts'])->where('concept_id', '!=', $b);
        $this->assertTrue($others->every(fn (array $c) => $c['is_current'] === false));
    }

    public function test_requires_edge_is_drawn_prerequisite_to_dependent_with_correct_depth(): void
    {
        $earlier = $this->makeConcept('Earlier Concept');
        $this->makeKNode($earlier);
        $later = $this->makeConcept('Later Concept');
        $this->makeKNode($later);

        // later requires earlier.
        $this->relate($later, $earlier, 'requires');
        $this->makeMisconception($earlier);
        $this->makeMisconception($earlier);

        $map = $this->policy->chapterKnowledgeMap($this->studentId, $later, $this->subInstituteId);

        $this->assertCount(1, $map['edges']);
        $this->assertSame([
            'from_concept_id' => $earlier,
            'to_concept_id' => $later,
            'type' => 'direct_prerequisite',
        ], $map['edges'][0]);
        $this->assertSame(1, $map['stats']['direct_prerequisites']);

        $earlierNode = collect($map['concepts'])->firstWhere('concept_id', $earlier);
        $laterNode = collect($map['concepts'])->firstWhere('concept_id', $later);
        $this->assertSame(0, $earlierNode['depth']);
        $this->assertSame(1, $laterNode['depth']);
        $this->assertSame(2, $earlierNode['misconception_count']);

        // The prerequisite is unmastered, so 'later' is locked and shows up
        // in the real "why is this closed" summary — both the page-level
        // aggregate and 'later' card's own per-concept reason.
        $this->assertContains('Later Concept', $map['locked_concept_names']);
        $this->assertContains('Earlier Concept', $map['blocking_prerequisite_names']);
        $this->assertSame(['Earlier Concept'], $laterNode['blocking_prerequisite_names']);
        $this->assertSame([], $earlierNode['blocking_prerequisite_names']);
    }

    public function test_cross_curricular_edge_is_related_and_deduped_regardless_of_direction(): void
    {
        $a = $this->makeConcept('Concept A');
        $this->makeKNode($a);
        $b = $this->makeConcept('Concept B');
        $this->makeKNode($b);

        $this->relate($a, $b, 'cross_curricular');
        $this->relate($b, $a, 'cross_curricular'); // same pair, reverse order — must not double-count

        $map = $this->policy->chapterKnowledgeMap($this->studentId, $a, $this->subInstituteId);

        $this->assertCount(1, $map['edges']);
        $this->assertSame('related', $map['edges'][0]['type']);
        $this->assertSame(1, $map['stats']['related']);
        $this->assertSame(0, $map['stats']['direct_prerequisites']);
    }

    public function test_edges_to_a_concept_in_a_different_chapter_are_excluded(): void
    {
        $inChapter = $this->makeConcept('In Chapter Concept');
        $this->makeKNode($inChapter);
        $otherChapterConcept = $this->makeConceptInOtherChapter('Other Chapter Concept');
        $this->makeKNode($otherChapterConcept);

        $this->relate($inChapter, $otherChapterConcept, 'requires');

        $map = $this->policy->chapterKnowledgeMap($this->studentId, $inChapter, $this->subInstituteId);

        $this->assertCount(1, $map['concepts']); // only the in-chapter concept
        $this->assertSame([], $map['edges']);
    }

    public function test_a_mastered_prerequisite_does_not_block(): void
    {
        $prereq = $this->makeConcept('Mastered Prerequisite');
        $kNode = $this->makeKNode($prereq);
        $main = $this->makeConcept('Main Concept');
        $this->makeKNode($main);
        $this->relate($main, $prereq, 'requires');

        \App\Models\Eso\LearnerNodeState::updateOrCreate(
            ['student_id' => $this->studentId, 'node_id' => $kNode],
            ['sub_institute_id' => $this->subInstituteId, 'mastery_estimate' => 0.9, 'attempts' => 3, 'status' => 'mastered']
        );

        $map = $this->policy->chapterKnowledgeMap($this->studentId, $main, $this->subInstituteId);

        $mainNode = collect($map['concepts'])->firstWhere('concept_id', $main);
        $this->assertNotSame('locked', $mainNode['status']);
        $this->assertSame([], $map['locked_concept_names']);
        $this->assertSame([], $map['blocking_prerequisite_names']);
    }

    public function test_a_concept_with_every_node_retained_shows_retained_not_mastered(): void
    {
        $concept = $this->makeConcept('Retained Concept');
        $kNode = $this->makeKNode($concept);

        \App\Models\Eso\LearnerNodeState::updateOrCreate(
            ['student_id' => $this->studentId, 'node_id' => $kNode],
            ['sub_institute_id' => $this->subInstituteId, 'mastery_estimate' => 0.9, 'attempts' => 3, 'status' => 'retained']
        );

        $map = $this->policy->chapterKnowledgeMap($this->studentId, $concept, $this->subInstituteId);

        $node = collect($map['concepts'])->firstWhere('concept_id', $concept);
        $this->assertSame('retained', $node['status']);
    }

    public function test_a_mastered_but_not_retained_concept_stays_mastered(): void
    {
        $concept = $this->makeConcept('Mastered Not Retained Concept');
        $kNode = $this->makeKNode($concept);

        \App\Models\Eso\LearnerNodeState::updateOrCreate(
            ['student_id' => $this->studentId, 'node_id' => $kNode],
            ['sub_institute_id' => $this->subInstituteId, 'mastery_estimate' => 0.9, 'attempts' => 3, 'status' => 'mastered']
        );

        $map = $this->policy->chapterKnowledgeMap($this->studentId, $concept, $this->subInstituteId);

        $node = collect($map['concepts'])->firstWhere('concept_id', $concept);
        $this->assertSame('mastered', $node['status']);
    }

    public function test_chapter_description_is_passed_through_when_present(): void
    {
        DB::table('chapter_master')->where('id', $this->chapterId)->update(['chapter_desc' => 'A real chapter description.']);

        $concept = $this->makeConcept('Described Chapter Concept');
        $this->makeKNode($concept);

        $map = $this->policy->chapterKnowledgeMap($this->studentId, $concept, $this->subInstituteId);

        $this->assertSame('A real chapter description.', $map['chapter_description']);
    }

    public function test_chapter_description_is_null_when_blank(): void
    {
        $concept = $this->makeConcept('Blank Description Concept');
        $this->makeKNode($concept);

        $map = $this->policy->chapterKnowledgeMap($this->studentId, $concept, $this->subInstituteId);

        $this->assertNull($map['chapter_description']);
    }
}
