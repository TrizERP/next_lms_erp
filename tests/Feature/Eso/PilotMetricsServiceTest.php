<?php

namespace Tests\Feature\Eso;

use App\Models\Eso\PilotEnrollment;
use App\Services\Pilot\PilotMetricsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pre-pilot readiness — proves PilotMetricsService computes the Developer
 * Brief's five metrics correctly from EXISTING tables
 * (eso_decision_log for Arm B, lms_online_exam/suggested_content for Arm A),
 * per docs/CHAPTER_1014_PILOT_MEASUREMENT_PLAN.md. No new event table is
 * exercised here because none exists — this is the point of the design.
 */
class PilotMetricsServiceTest extends TestCase
{
    use DatabaseTransactions;

    private PilotMetricsService $metrics;

    private int $subInstituteId;

    private int $chapterId;

    private int $conceptId;

    private int $nodeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->metrics = app(PilotMetricsService::class);

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'Pilot Metrics Test School',
            'ShortCode' => 'PMT' . random_int(1000, 9999),
            'ContactPerson' => 'x', 'Mobile' => '9999999999', 'Email' => 'x@example.com',
            'ReceiptHeader' => 'x', 'ReceiptAddress' => 'x', 'FeeEmail' => 'x@example.com',
            'ReceiptContact' => '9999999999', 'SortOrder' => '1', 'Logo' => '', 'created_at' => now(),
        ]);

        $this->chapterId = (int) DB::table('chapter_master')->insertGetId([
            'subject_id' => 1, 'standard_id' => 1, 'sub_institute_id' => $this->subInstituteId,
            'chapter_name' => 'Pilot Metrics Test Chapter', 'created_at' => now(),
        ]);

        $this->conceptId = (int) DB::table('lms_concept')->insertGetId([
            'name' => 'Pilot Metrics Test Concept', 'subject_id' => 1, 'standard_id' => 1,
            'chapter_id' => $this->chapterId, 'sub_institute_id' => $this->subInstituteId,
            'mastery_threshold' => 80, 'syear' => 2026, 'created_at' => now(),
        ]);

        $this->nodeId = (int) DB::table('pal_concept_nodes')->insertGetId([
            'concept_id' => $this->conceptId, 'sub_institute_id' => $this->subInstituteId,
            'node_type' => 'K', 'label' => 'Test node', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeStudent(): int
    {
        return (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Pilot', 'last_name' => 'Student', 'sub_institute_id' => $this->subInstituteId,
            'file_size' => '', 'file_type' => '',
        ]);
    }

    private function enroll(int $studentId, string $arm, ?string $cohort = null): PilotEnrollment
    {
        return PilotEnrollment::create([
            'student_id' => $studentId, 'sub_institute_id' => $this->subInstituteId,
            'chapter_id' => $this->chapterId, 'arm' => $arm, 'cohort_label' => $cohort,
            'status' => PilotEnrollment::STATUS_ACTIVE, 'enrolled_at' => now(),
        ]);
    }

    private function logDecision(int $studentId, string $action, string $ruleFired, array $snapshot = [], ?int $nodeId = null, ?\DateTimeInterface $at = null): void
    {
        DB::table('eso_decision_log')->insert([
            'student_id' => $studentId, 'concept_id' => $this->conceptId, 'node_id' => $nodeId ?? $this->nodeId,
            'sub_institute_id' => $this->subInstituteId, 'state_snapshot' => json_encode($snapshot),
            'rule_fired' => $ruleFired, 'action' => $action, 'llm_instruction' => in_array($action, ['teach', 'practice'], true) ? 'Teach something.' : null,
            'created_at' => $at ?? now(), 'updated_at' => $at ?? now(),
        ]);
    }

    // ── Arm B: mastery timing ────────────────────────────────────────────

    public function test_arm_b_time_to_mastery_is_computed_from_entry_to_mastered_decision_log_rows(): void
    {
        $student = $this->makeStudent();
        $this->enroll($student, PilotEnrollment::ARM_B);

        $entryAt = now()->subMinutes(40);
        $masteryAt = now()->subMinutes(5);

        $this->logDecision($student, 'diagnostic', 'D1: concept entry', [], null, $entryAt);
        $this->logDecision($student, 'mastered_stop_practice', 'D4: mastery', [], null, $masteryAt);

        $summary = $this->metrics->summary($this->chapterId);

        $this->assertSame(1, $summary['arm_b']['mastered']);
        $this->assertEqualsWithDelta(35.0, $summary['arm_b']['time_to_mastery_minutes_avg'], 1.0);
    }

    // ── Arm B: retention ──────────────────────────────────────────────────

    public function test_arm_b_retention_pass_requires_the_latest_d5_event_per_node_to_be_retained(): void
    {
        $student = $this->makeStudent();
        $this->enroll($student, PilotEnrollment::ARM_B);

        $this->logDecision($student, 'diagnostic', 'D1', [], null, now()->subDays(10));
        $this->logDecision($student, 'mastered_stop_practice', 'D4', [], null, now()->subDays(9));
        $this->logDecision($student, 'retained', 'D5: retrieval check passed', [], null, now()->subDays(5));

        $summary = $this->metrics->summary($this->chapterId);

        $this->assertSame(1, $summary['arm_b']['retention_eligible_count']);
        $this->assertSame(1.0, $summary['arm_b']['retention_rate']);
    }

    public function test_arm_b_retention_fails_when_the_latest_d5_event_for_a_node_is_a_reloop(): void
    {
        $student = $this->makeStudent();
        $this->enroll($student, PilotEnrollment::ARM_B);

        $this->logDecision($student, 'diagnostic', 'D1', [], null, now()->subDays(10));
        $this->logDecision($student, 'mastered_stop_practice', 'D4', [], null, now()->subDays(9));
        $this->logDecision($student, 'reloop_node', 'D5: retrieval check failed', [], null, now()->subDays(5));

        $summary = $this->metrics->summary($this->chapterId);

        $this->assertSame(0.0, $summary['arm_b']['retention_rate']);
    }

    public function test_arm_b_retention_is_not_yet_eligible_before_three_days_have_passed_since_mastery(): void
    {
        $student = $this->makeStudent();
        $this->enroll($student, PilotEnrollment::ARM_B);

        $this->logDecision($student, 'diagnostic', 'D1', [], null, now()->subMinutes(30));
        $this->logDecision($student, 'mastered_stop_practice', 'D4', [], null, now()->subMinutes(5));

        $summary = $this->metrics->summary($this->chapterId);

        $this->assertSame(1, $summary['arm_b']['mastered']);
        $this->assertSame(0, $summary['arm_b']['retention_eligible_count']);
        $this->assertNull($summary['arm_b']['retention_rate']);
    }

    // ── Arm B: explanation served / skipped ────────────────────────────

    public function test_arm_b_explanation_served_and_skipped_are_counted_separately(): void
    {
        $student = $this->makeStudent();
        $this->enroll($student, PilotEnrollment::ARM_B);

        $this->logDecision($student, 'diagnostic', 'D1', [], null, now()->subMinutes(30));
        $this->logDecision($student, 'skip_instruction', 'D1: skip-eligible', [], null, now()->subMinutes(29));
        $this->logDecision($student, 'teach', 'D1: teach', [], null, now()->subMinutes(20));
        $this->logDecision($student, 'practice', 'D4: continue practice', [], null, now()->subMinutes(15));
        $this->logDecision($student, 'mastered_stop_practice', 'D4', [], null, now()->subMinutes(5));

        $summary = $this->metrics->summary($this->chapterId);

        $this->assertSame(2.0, $summary['arm_b']['explanations_served_avg']);
        $this->assertSame(1.0, $summary['arm_b']['explanations_skipped_avg']);
    }

    // ── Arm B: misconception recurrence ────────────────────────────────

    public function test_arm_b_misconception_recurrence_is_detected_when_flagged_again_after_being_corrected(): void
    {
        $student = $this->makeStudent();
        $this->enroll($student, PilotEnrollment::ARM_B);

        $snapshot = ['misconception_id' => 999];
        $this->logDecision($student, 'diagnostic', 'D1', [], null, now()->subMinutes(60));
        $this->logDecision($student, 'serve_contrast_pair', 'D3: flagged', $snapshot, null, now()->subMinutes(50));
        $this->logDecision($student, 'misconception_corrected', 'D3: corrected', $snapshot, null, now()->subMinutes(45));
        $this->logDecision($student, 'serve_contrast_pair', 'D3: flagged again', $snapshot, null, now()->subMinutes(20));
        $this->logDecision($student, 'mastered_stop_practice', 'D4', [], null, now()->subMinutes(5));

        $summary = $this->metrics->summary($this->chapterId);

        $this->assertTrue($summary['arm_b']['misconception_recurrence_applicable']);
        $this->assertSame(1.0, $summary['arm_b']['misconception_recurrence_rate']);
    }

    public function test_arm_b_misconception_not_recurred_when_never_flagged_again(): void
    {
        $student = $this->makeStudent();
        $this->enroll($student, PilotEnrollment::ARM_B);

        $snapshot = ['misconception_id' => 999];
        $this->logDecision($student, 'diagnostic', 'D1', [], null, now()->subMinutes(60));
        $this->logDecision($student, 'serve_contrast_pair', 'D3: flagged', $snapshot, null, now()->subMinutes(50));
        $this->logDecision($student, 'misconception_corrected', 'D3: corrected', $snapshot, null, now()->subMinutes(45));
        $this->logDecision($student, 'mastered_stop_practice', 'D4', [], null, now()->subMinutes(5));

        $summary = $this->metrics->summary($this->chapterId);

        $this->assertTrue($summary['arm_b']['misconception_recurrence_applicable']);
        $this->assertSame(0.0, $summary['arm_b']['misconception_recurrence_rate']);
    }

    // ── Arm A: derived from legacy tables, no engine involved ───────────

    public function test_arm_a_mastery_and_retention_are_derived_from_lms_online_exam_scores(): void
    {
        $student = $this->makeStudent();
        $this->enroll($student, PilotEnrollment::ARM_A);

        $questionPaper1 = (int) DB::table('question_paper')->insertGetId([
            'exam_type' => 'PAL', 'paper_desc' => (string) $this->chapterId, 'subject_id' => 1,
            'standard_id' => 1, 'sub_institute_id' => $this->subInstituteId, 'created_on' => now(),
        ]);
        DB::table('lms_online_exam')->insert([
            'student_id' => $student, 'question_paper_id' => $questionPaper1,
            'total_right' => 8, 'total_wrong' => 2, 'obtain_marks' => 8,
            'start_time' => now()->subDays(9), 'created_at' => now()->subDays(9),
        ]);

        $questionPaper2 = (int) DB::table('question_paper')->insertGetId([
            'exam_type' => 'PAL', 'paper_desc' => (string) $this->chapterId, 'subject_id' => 1,
            'standard_id' => 1, 'sub_institute_id' => $this->subInstituteId, 'created_on' => now(),
        ]);
        DB::table('lms_online_exam')->insert([
            'student_id' => $student, 'question_paper_id' => $questionPaper2,
            'total_right' => 9, 'total_wrong' => 1, 'obtain_marks' => 9,
            'start_time' => now()->subDays(4), 'created_at' => now()->subDays(4),
        ]);

        $summary = $this->metrics->summary($this->chapterId);

        $this->assertSame(1, $summary['arm_a']['mastered']);
        $this->assertSame(1, $summary['arm_a']['retention_eligible_count']);
        $this->assertSame(1.0, $summary['arm_a']['retention_rate']);
    }

    public function test_arm_a_explanation_served_counts_suggested_content_rows(): void
    {
        $student = $this->makeStudent();
        $this->enroll($student, PilotEnrollment::ARM_A);

        $questionPaper = (int) DB::table('question_paper')->insertGetId([
            'exam_type' => 'PAL', 'paper_desc' => (string) $this->chapterId, 'subject_id' => 1,
            'standard_id' => 1, 'sub_institute_id' => $this->subInstituteId, 'created_on' => now(),
        ]);
        DB::table('lms_online_exam')->insert([
            'student_id' => $student, 'question_paper_id' => $questionPaper,
            'total_right' => 5, 'total_wrong' => 5, 'obtain_marks' => 5,
            'start_time' => now()->subMinutes(30), 'created_at' => now()->subMinutes(30),
        ]);
        DB::table('suggested_content')->insert([
            'type' => 'pal_content', 'type_id' => 1, 'standard_id' => 1, 'subject_id' => 1,
            'chapter_id' => $this->chapterId, 'sub_institute_id' => $this->subInstituteId,
            'student_id' => $student, 'created_by' => $student, 'created_at' => now(),
        ]);
        DB::table('suggested_content')->insert([
            'type' => 'misconception', 'type_id' => 2, 'standard_id' => 1, 'subject_id' => 1,
            'chapter_id' => $this->chapterId, 'sub_institute_id' => $this->subInstituteId,
            'student_id' => $student, 'created_by' => $student, 'created_at' => now(),
        ]);

        $summary = $this->metrics->summary($this->chapterId);

        $this->assertSame(2.0, $summary['arm_a']['explanations_served_avg']);
        $this->assertNull($summary['arm_a']['explanations_skipped_avg'], 'Arm A has no skip mechanism — structural null, not missing data.');
    }

    // ── Aggregation across multiple enrollments ─────────────────────────

    public function test_mastery_rate_counts_non_mastering_students_as_a_real_zero(): void
    {
        $mastered = $this->makeStudent();
        $this->enroll($mastered, PilotEnrollment::ARM_B);
        $this->logDecision($mastered, 'diagnostic', 'D1', [], null, now()->subMinutes(30));
        $this->logDecision($mastered, 'mastered_stop_practice', 'D4', [], null, now()->subMinutes(5));

        $notMastered = $this->makeStudent();
        $this->enroll($notMastered, PilotEnrollment::ARM_B);
        $this->logDecision($notMastered, 'diagnostic', 'D1', [], null, now()->subMinutes(30));
        $this->logDecision($notMastered, 'practice', 'D4: continue', [], null, now()->subMinutes(20));

        $neverStarted = $this->makeStudent();
        $this->enroll($neverStarted, PilotEnrollment::ARM_B);

        $summary = $this->metrics->summary($this->chapterId);

        $this->assertSame(3, $summary['arm_b']['enrolled']);
        $this->assertSame(2, $summary['arm_b']['started'], 'A never-started enrollment is excluded from "started" — plan §9.');
        $this->assertSame(1, $summary['arm_b']['mastered']);
        $this->assertSame(0.5, $summary['arm_b']['mastery_rate']);
    }

    public function test_a_withdrawn_enrollment_is_excluded_from_every_metric(): void
    {
        $student = $this->makeStudent();
        $enrollment = $this->enroll($student, PilotEnrollment::ARM_B);
        $this->logDecision($student, 'diagnostic', 'D1', [], null, now()->subMinutes(30));
        $this->logDecision($student, 'mastered_stop_practice', 'D4', [], null, now()->subMinutes(5));

        $enrollment->update(['status' => PilotEnrollment::STATUS_WITHDRAWN]);

        $summary = $this->metrics->summary($this->chapterId);

        $this->assertSame(0, $summary['arm_b']['enrolled']);
    }

    public function test_cohort_label_scopes_the_summary(): void
    {
        $studentInCohort = $this->makeStudent();
        $this->enroll($studentInCohort, PilotEnrollment::ARM_B, 'cohort-x');

        $studentInOtherCohort = $this->makeStudent();
        $this->enroll($studentInOtherCohort, PilotEnrollment::ARM_B, 'cohort-y');

        $summary = $this->metrics->summary($this->chapterId, 'cohort-x');

        $this->assertSame(1, $summary['arm_b']['enrolled']);
    }
}
