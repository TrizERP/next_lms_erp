<?php

namespace Tests\Feature\Eso;

use App\Models\Eso\DecisionLog;
use App\Models\Eso\LearnerNodeState;
use App\Services\Eso\EsoPolicyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * EsoPolicyService::chapterDashboard() — the aggregate behind the "Hello,
 * {name}" chapter-level student dashboard. Exercises the service directly
 * (not over HTTP), same convention as EsoPolicyServiceTest.
 */
class EsoChapterDashboardTest extends TestCase
{
    use DatabaseTransactions;

    private EsoPolicyService $policy;

    private int $subInstituteId;

    private int $studentId;

    private int $subjectId;

    private int $standardId;

    private int $chapterId;

    private int $conceptId;

    private int $kNodeId;

    private int $aNodeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = app(EsoPolicyService::class);

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'ESO Dashboard Test School',
            'ShortCode' => 'ESOD' . random_int(1000, 9999),
            'ContactPerson' => 'Test Contact',
            'Mobile' => '9999999999',
            'Email' => 'eso-dashboard-test@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'eso-dashboard-test@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);

        $this->studentId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Eso',
            'last_name' => 'Dashboard Learner',
            'sub_institute_id' => $this->subInstituteId,
            'file_size' => '',
            'file_type' => '',
        ]);

        $this->subjectId = (int) DB::table('subject')->insertGetId([
            'subject_name' => 'ESO Dashboard Test Subject ' . random_int(1000, 9999),
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

        $this->chapterId = (int) DB::table('chapter_master')->insertGetId([
            'subject_id' => $this->subjectId,
            'standard_id' => $this->standardId,
            'sub_institute_id' => $this->subInstituteId,
            'chapter_name' => 'ESO Dashboard Test Chapter',
            'created_at' => now(),
        ]);

        $this->conceptId = $this->makeConcept('ESO Dashboard Test Concept');
        [$this->kNodeId, $this->aNodeId] = $this->makeKANodes($this->conceptId);
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

    /** @return array{0:int,1:int} [kNodeId, aNodeId] */
    private function makeKANodes(int $conceptId): array
    {
        $k = (int) DB::table('pal_concept_nodes')->insertGetId([
            'concept_id' => $conceptId,
            'sub_institute_id' => $this->subInstituteId,
            'node_type' => 'K',
            'label' => 'Knowledge node',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $a = (int) DB::table('pal_concept_nodes')->insertGetId([
            'concept_id' => $conceptId,
            'sub_institute_id' => $this->subInstituteId,
            'node_type' => 'A',
            'label' => 'Application node',
            'sort_order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$k, $a];
    }

    private function enroll(int $studentId, int $standardId, int $syear = 2026): void
    {
        DB::table('tblstudent_enrollment')->insert([
            'syear' => $syear,
            'student_id' => $studentId,
            'grade_id' => 1,
            'standard_id' => $standardId,
            'section_id' => 1,
            'sub_institute_id' => $this->subInstituteId,
            'created_on' => now(),
        ]);
    }

    private function mapSubjectToStandard(int $subjectId, int $standardId, int $sortOrder = 1): void
    {
        DB::table('sub_std_map')->insert([
            'subject_id' => $subjectId,
            'standard_id' => $standardId,
            'display_name' => 'Mapped Subject',
            'add_content' => '1',
            'sort_order' => $sortOrder,
            'sub_institute_id' => $this->subInstituteId,
            'created_at' => now(),
        ]);
    }

    public function test_student_dashboard_auto_picks_the_chapter_with_open_work(): void
    {
        $this->enroll($this->studentId, $this->standardId);
        $this->mapSubjectToStandard($this->subjectId, $this->standardId);

        $dashboard = $this->policy->studentDashboard($this->studentId, $this->subInstituteId, '2026');

        $this->assertSame($this->chapterId, $dashboard['chapter_id']);
        $this->assertSame($this->conceptId, $dashboard['current_concept_id']);
        $this->assertSame('diagnostic', $dashboard['next_step']['action']);
    }

    public function test_student_dashboard_returns_null_without_enrollment_for_that_year(): void
    {
        $this->assertNull($this->policy->studentDashboard($this->studentId, $this->subInstituteId, '2026'));
    }

    public function test_student_dashboard_reports_no_content_when_nothing_is_eso_ready(): void
    {
        // A second student/standard/subject/chapter with zero pal_concept_nodes.
        $studentId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'No', 'last_name' => 'Content',
            'sub_institute_id' => $this->subInstituteId, 'file_size' => '', 'file_type' => '',
        ]);
        $standardId = (int) DB::table('standard')->insertGetId([
            'grade_id' => 1, 'name' => '10', 'short_name' => '10', 'sort_order' => 2,
            'sub_institute_id' => $this->subInstituteId,
        ]);
        $subjectId = (int) DB::table('subject')->insertGetId([
            'subject_name' => 'Untagged Subject ' . random_int(1000, 9999),
            'sub_institute_id' => $this->subInstituteId, 'status' => 1, 'created_at' => now(),
        ]);
        DB::table('chapter_master')->insertGetId([
            'subject_id' => $subjectId, 'standard_id' => $standardId,
            'sub_institute_id' => $this->subInstituteId, 'chapter_name' => 'Untagged Chapter', 'created_at' => now(),
        ]);
        $this->enroll($studentId, $standardId);
        $this->mapSubjectToStandard($subjectId, $standardId);

        $dashboard = $this->policy->studentDashboard($studentId, $this->subInstituteId, '2026');

        $this->assertSame(['no_content' => true], $dashboard);
    }

    public function test_unknown_chapter_returns_null(): void
    {
        $this->assertNull($this->policy->chapterDashboard($this->studentId, 999999999, $this->subInstituteId));
    }

    public function test_a_fresh_student_sees_the_diagnostic_next_step_and_no_evidence_anywhere(): void
    {
        $dashboard = $this->policy->chapterDashboard($this->studentId, $this->chapterId, $this->subInstituteId);

        $this->assertSame($this->conceptId, $dashboard['current_concept_id']);
        $this->assertSame('ESO Dashboard Test Concept', $dashboard['current_concept_name']);
        $this->assertSame(0, $dashboard['responses_on_current_concept']);
        $this->assertSame(0, $dashboard['all_responses']);
        $this->assertSame(0, $dashboard['mastered_concepts']);
        $this->assertSame(1, $dashboard['total_concepts_in_curriculum']);

        $this->assertSame('diagnostic', $dashboard['next_step']['action']);
        $this->assertFalse($dashboard['next_step']['has_evidence']);

        $this->assertCount(1, $dashboard['chapter_sections']);
        $this->assertSame('not_started', $dashboard['chapter_sections'][0]['status']);

        foreach ($dashboard['mastery_signals'] as $signal) {
            $this->assertNull($signal['value'], "{$signal['key']} should report no evidence yet");
            $this->assertFalse($signal['has_evidence']);
        }
    }

    public function test_dashboard_reads_never_write_a_decision_log_row(): void
    {
        $before = DecisionLog::forStudent($this->studentId)->count();

        $this->policy->chapterDashboard($this->studentId, $this->chapterId, $this->subInstituteId);
        $this->policy->chapterDashboard($this->studentId, $this->chapterId, $this->subInstituteId);

        $this->assertSame($before, DecisionLog::forStudent($this->studentId)->count());
    }

    public function test_mastery_signals_reflect_real_attempts_once_the_student_has_practiced(): void
    {
        LearnerNodeState::updateOrCreate(
            ['student_id' => $this->studentId, 'node_id' => $this->kNodeId],
            [
                'sub_institute_id' => $this->subInstituteId,
                'mastery_estimate' => 0.6,
                'attempts' => 3,
                'hint_used_count' => 1,
                'status' => LearnerNodeState::STATUS_LEARNING,
                'last_seen_at' => now(),
            ]
        );

        $dashboard = $this->policy->chapterDashboard($this->studentId, $this->chapterId, $this->subInstituteId);

        $signals = collect($dashboard['mastery_signals'])->keyBy('key');
        $this->assertSame(0.6, $signals['getting_method_right']['value']);
        $this->assertTrue($signals['getting_method_right']['has_evidence']);
        // Application node still has zero attempts — its signal stays "not enough evidence".
        $this->assertNull($signals['understanding_the_idea']['value']);

        $this->assertSame(3, $dashboard['responses_on_current_concept']);
        $this->assertSame(3, $dashboard['all_responses']);
    }

    public function test_a_locked_concept_is_never_chosen_as_the_current_concept(): void
    {
        $lockedConceptId = $this->makeConcept('Locked Concept');
        [$lockedK, $lockedA] = $this->makeKANodes($lockedConceptId);

        DB::table('pal_concept_relations')->insert([
            'from_concept_id' => $lockedConceptId,
            'to_concept_id' => $this->conceptId,
            'relation_type' => 'requires',
            'sub_institute_id' => $this->subInstituteId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The prerequisite (the original concept) is left completely unmastered.
        $dashboard = $this->policy->chapterDashboard($this->studentId, $this->chapterId, $this->subInstituteId);

        $sections = collect($dashboard['chapter_sections'])->keyBy('concept_id');
        $this->assertSame('locked', $sections[$lockedConceptId]['status']);
        $this->assertNotSame($lockedConceptId, $dashboard['current_concept_id']);
        $this->assertSame($this->conceptId, $dashboard['current_concept_id']);
        $this->assertSame(0, $dashboard['mastered_concepts']);
        $this->assertSame(2, $dashboard['total_concepts_in_curriculum']);
    }
}
