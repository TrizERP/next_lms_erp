<?php

namespace Tests\Feature\Eso;

use App\Models\Eso\LearnerNodeState;
use App\Services\Eso\EsoPolicyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * EsoPolicyService::learningPath() — PAL loop step 4, the Personal Learning
 * Plan.
 *
 * studentDashboard() already resolved this ordering and then discarded all of
 * it but the current chapter, so the plan the loop follows has never been
 * visible. These tests pin the sequence itself: that it spans chapters in the
 * order the student is meant to work them, that a chapter's status reflects
 * real evidence rather than position, and that the plan says WHY the next step
 * is next.
 */
class EsoLearningPathTest extends TestCase
{
    use DatabaseTransactions;

    private EsoPolicyService $policy;

    private int $subInstituteId;

    private int $studentId;

    private int $subjectId;

    private int $standardId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = app(EsoPolicyService::class);

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'Learning Path School',
            'ShortCode' => 'LP' . random_int(1000, 9999),
            'ContactPerson' => 'Test Contact',
            'Mobile' => '9999999999',
            'Email' => 'lp@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'lp@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);

        $this->studentId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Path',
            'last_name' => 'Learner',
            'sub_institute_id' => $this->subInstituteId,
            'file_size' => '',
            'file_type' => '',
        ]);

        $this->subjectId = (int) DB::table('subject')->insertGetId([
            'subject_name' => 'Path Subject ' . random_int(1000, 9999),
            'sub_institute_id' => $this->subInstituteId,
            'status' => 1,
            'created_at' => now(),
        ]);

        $this->standardId = (int) DB::table('standard')->insertGetId([
            'grade_id' => 1,
            'name' => '9',
            'short_name' => '9',
            'sort_order' => 1,
            'sub_institute_id' => $this->subInstituteId,
        ]);

        DB::table('tblstudent_enrollment')->insert([
            'syear' => 2026,
            'student_id' => $this->studentId,
            'grade_id' => 1,
            'standard_id' => $this->standardId,
            'section_id' => 1,
            'sub_institute_id' => $this->subInstituteId,
            'created_on' => now(),
        ]);

        DB::table('sub_std_map')->insert([
            'subject_id' => $this->subjectId,
            'standard_id' => $this->standardId,
            'display_name' => 'Mapped Subject',
            'add_content' => '1',
            'sort_order' => 1,
            'sub_institute_id' => $this->subInstituteId,
            'created_at' => now(),
        ]);
    }

    /** A chapter with one ESO-ready concept (K + A nodes). @return array{0:int,1:int,2:int} */
    private function makeChapter(string $name, int $sortOrder): array
    {
        $chapterId = (int) DB::table('chapter_master')->insertGetId([
            'subject_id' => $this->subjectId,
            'standard_id' => $this->standardId,
            'sub_institute_id' => $this->subInstituteId,
            'chapter_name' => $name,
            'sort_order' => $sortOrder,
            'created_at' => now(),
        ]);

        $conceptId = (int) DB::table('lms_concept')->insertGetId([
            'name' => $name . ' Concept',
            'subject_id' => $this->subjectId,
            'standard_id' => $this->standardId,
            'chapter_id' => $chapterId,
            'sub_institute_id' => $this->subInstituteId,
            'mastery_threshold' => 80,
            'syear' => 2026,
            'created_at' => now(),
        ]);

        $kNodeId = (int) DB::table('pal_concept_nodes')->insertGetId([
            'concept_id' => $conceptId,
            'sub_institute_id' => $this->subInstituteId,
            'node_type' => 'K',
            'label' => 'Knowledge node',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('pal_concept_nodes')->insert([
            'concept_id' => $conceptId,
            'sub_institute_id' => $this->subInstituteId,
            'node_type' => 'A',
            'label' => 'Application node',
            'sort_order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$chapterId, $conceptId, $kNodeId];
    }

    private function path(): ?array
    {
        return $this->policy->learningPath($this->studentId, $this->subInstituteId, '2026');
    }

    public function test_the_plan_spans_every_ready_chapter_not_just_the_current_one(): void
    {
        [$first] = $this->makeChapter('Chapter One', 1);
        [$second] = $this->makeChapter('Chapter Two', 2);
        [$third] = $this->makeChapter('Chapter Three', 3);

        $path = $this->path();

        $this->assertSame(3, $path['chapter_count']);
        $this->assertSame(
            [$first, $second, $third],
            array_column($path['chapters'], 'chapter_id'),
            'The sequence must come back in the order the student is meant to work it — that ordering is the whole content of a plan.'
        );
    }

    public function test_a_chapter_with_no_evidence_reads_as_not_started_rather_than_in_progress(): void
    {
        $this->makeChapter('Untouched Chapter', 1);

        $path = $this->path();

        $this->assertSame('not_started', $path['chapters'][0]['status']);
        $this->assertSame(0, $path['chapters'][0]['mastered_count']);
        $this->assertSame(1, $path['chapters'][0]['concept_count']);
    }

    public function test_the_plan_points_at_where_the_student_actually_is_and_says_why(): void
    {
        [$firstChapter, $firstConcept] = $this->makeChapter('Chapter One', 1);
        $this->makeChapter('Chapter Two', 2);

        $path = $this->path();

        $this->assertNotNull($path['current']);
        $this->assertSame($firstChapter, $path['current']['chapter_id']);
        $this->assertSame($firstConcept, $path['current']['concept_id']);
        $this->assertNotEmpty($path['current']['action']);
        $this->assertArrayHasKey(
            'rule_fired',
            $path['current'],
            'A plan that shows the sequence without saying why the next step is next is a list, not a plan.'
        );
    }

    public function test_a_completed_chapter_is_skipped_over_when_choosing_where_the_student_is(): void
    {
        [$firstChapter, $firstConcept, $firstK] = $this->makeChapter('Chapter One', 1);
        [$secondChapter, $secondConcept] = $this->makeChapter('Chapter Two', 2);

        // Master every node in chapter one.
        foreach (DB::table('pal_concept_nodes')->where('concept_id', $firstConcept)->pluck('id') as $nodeId) {
            LearnerNodeState::updateOrCreate(
                ['student_id' => $this->studentId, 'node_id' => (int) $nodeId],
                [
                    'sub_institute_id' => $this->subInstituteId,
                    'mastery_estimate' => 1.0,
                    'attempts' => 5,
                    'status' => LearnerNodeState::STATUS_MASTERED,
                    'last_seen_at' => now(),
                ]
            );
        }

        $path = $this->path();

        $this->assertNotNull($path['current']);
        $this->assertSame(
            $secondChapter,
            $path['current']['chapter_id'],
            'A finished chapter must not keep being pointed at as the current one.'
        );
        $this->assertSame($secondConcept, $path['current']['concept_id']);
    }

    public function test_a_student_with_no_enrolment_has_no_plan_at_all(): void
    {
        $strangerId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Not',
            'last_name' => 'Enrolled',
            'sub_institute_id' => $this->subInstituteId,
            'file_size' => '',
            'file_type' => '',
        ]);

        $this->assertNull(
            $this->policy->learningPath($strangerId, $this->subInstituteId, '2026'),
            'No enrolment is a different state from an empty plan, and the endpoint reports it as 404 rather than an empty sequence.'
        );
    }

    public function test_an_enrolled_student_with_nothing_authored_gets_an_empty_plan_not_a_failure(): void
    {
        // Enrolled and mapped, but no chapters exist.
        $path = $this->path();

        $this->assertNotNull($path);
        $this->assertTrue($path['no_content']);
        $this->assertSame([], $path['chapters']);
        $this->assertNull($path['current']);
    }

    public function test_a_chapter_with_no_eso_ready_concept_is_left_out_of_the_plan(): void
    {
        [$readyChapter] = $this->makeChapter('Ready Chapter', 1);

        // A chapter whose concept has no K/A/S nodes: it cannot be worked
        // through, so promising it in a sequence would be a lie.
        $bareChapterId = (int) DB::table('chapter_master')->insertGetId([
            'subject_id' => $this->subjectId,
            'standard_id' => $this->standardId,
            'sub_institute_id' => $this->subInstituteId,
            'chapter_name' => 'Bare Chapter',
            'sort_order' => 2,
            'created_at' => now(),
        ]);

        DB::table('lms_concept')->insert([
            'name' => 'Nodeless Concept',
            'subject_id' => $this->subjectId,
            'standard_id' => $this->standardId,
            'chapter_id' => $bareChapterId,
            'sub_institute_id' => $this->subInstituteId,
            'mastery_threshold' => 80,
            'syear' => 2026,
            'created_at' => now(),
        ]);

        $path = $this->path();

        $this->assertSame([$readyChapter], array_column($path['chapters'], 'chapter_id'));
    }

    public function test_the_extraction_did_not_change_what_the_student_dashboard_returns(): void
    {
        // learningPath() and studentDashboard() now share one ordering helper.
        // The dashboard must still resolve to the first incomplete chapter.
        [$firstChapter] = $this->makeChapter('Chapter One', 1);
        $this->makeChapter('Chapter Two', 2);

        $dashboard = $this->policy->studentDashboard($this->studentId, $this->subInstituteId, '2026');

        $this->assertSame($firstChapter, $dashboard['chapter_id']);
    }
}
