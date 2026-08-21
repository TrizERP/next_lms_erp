<?php

namespace Tests\Feature\Pal;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression coverage for the real PAL write path (palController::store() ->
 * recordPalEvidenceAndMastery()).
 *
 * Before this fix: pal_assessment_results / pal_competencies were dead tables
 * (confirmed by repo-wide grep -- nothing wrote to them). Mastery was only
 * ever computed live, on request, from lms_online_exam_answer for reporting.
 * Separately, the single-choice and narrative answer branches of store() were
 * writing per-question answer data into lms_online_exam (the exam-header
 * table, which has no question_id/answer_id/ans_status columns) instead of
 * lms_online_exam_answer -- a pre-existing bug this test also guards against.
 */
class PalWritePathTest extends TestCase
{
    use DatabaseTransactions;

    private int $subInstituteId;
    private int $studentId;
    private int $subjectId;
    private int $questionId;
    private int $correctAnswerId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'PAL Write Path Test School',
            'ShortCode' => 'PWP' . random_int(1000, 9999),
            'ContactPerson' => 'Test Contact',
            'Mobile' => '9999999999',
            'Email' => 'test@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'test@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);

        $this->studentId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Write',
            'last_name' => 'Path',
            'sub_institute_id' => $this->subInstituteId,
            'file_size' => '',
            'file_type' => '',
        ]);

        $this->subjectId = (int) DB::table('subject')->insertGetId([
            'subject_name' => 'Test Subject ' . random_int(1000, 9999),
            'sub_institute_id' => $this->subInstituteId,
            'status' => 1,
            'created_at' => now(),
        ]);

        $this->questionId = (int) DB::table('lms_question_master')->insertGetId([
            'subject_id' => $this->subjectId,
            'question_title' => 'What is 2 + 2?',
            'sub_institute_id' => $this->subInstituteId,
            'status' => 1,
        ]);

        $this->correctAnswerId = (int) DB::table('answer_master')->insertGetId([
            'question_id' => $this->questionId,
            'answer' => '4',
            'correct_answer' => 1,
        ]);
    }

    private function submitPalAnswer(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        $payload = array_merge([
            'grade_id' => 1,
            'standard_id' => 1,
            'subject_id' => $this->subjectId,
            'chapter_id' => 1,
            'paper_name' => 'PAL Write Path Test',
            'questionpaper_time' => 10,
            'total_marks' => 1,
            'total_question' => 1,
            'question_ids' => [$this->questionId],
            // legacy format: "<answer_id>##<client-declared correctness 0/1>"
            'answer_single' => [$this->questionId => $this->correctAnswerId . '##1'],
            'attempt_time' => [$this->questionId => 12],
        ], $overrides);

        return $this->withSession([
            'user_id' => $this->studentId,
            'sub_institute_id' => $this->subInstituteId,
            'syear' => '2026',
            'user_profile_id' => 1,
            // Bypasses checkPermission's menu-rights lookup so this test isolates
            // the write-path logic rather than the separate permissions system.
            'user_profile_name' => 'Super Admin',
        ])->post('/lms/pal', $payload);
    }

    public function test_submitting_a_pal_answer_writes_to_lms_online_exam_answer_not_lms_online_exam(): void
    {
        $before = DB::table('lms_online_exam')->count();

        $this->submitPalAnswer();

        // The exam-header row for this attempt.
        $this->assertSame($before + 1, DB::table('lms_online_exam')->count());

        // The per-question answer row must land in lms_online_exam_answer,
        // not lms_online_exam (lms_online_exam has no question_id column at
        // all, so this also proves the insert didn't silently go to the
        // wrong table with mismatched columns).
        $answerRow = DB::table('lms_online_exam_answer')
            ->where('student_id', $this->studentId)
            ->where('question_id', $this->questionId)
            ->first();

        $this->assertNotNull($answerRow);
        $this->assertSame('right', $answerRow->ans_status);
    }

    public function test_submitting_a_pal_answer_writes_pal_assessment_results(): void
    {
        $this->submitPalAnswer();

        $row = DB::table('pal_assessment_results')
            ->where('learner_id', $this->studentId)
            ->where('question_id', $this->questionId)
            ->first();

        $this->assertNotNull($row, 'pal_assessment_results should receive a row for the answered question.');
        $this->assertSame(1, (int) $row->is_correct);
        $this->assertSame(12000, (int) $row->response_time_ms);
    }

    public function test_submitting_a_pal_answer_updates_pal_competencies_via_bkt(): void
    {
        $this->submitPalAnswer();

        $competency = DB::table('pal_competencies')
            ->where('learner_id', $this->studentId)
            ->where('subject_id', $this->subjectId)
            ->whereNull('concept_id')
            ->first();

        $this->assertNotNull($competency, 'pal_competencies should receive a row for the subject.');

        // mastery_score is persisted on the 0-100 scale every PAL consumer
        // (LearnerStateEngine, RecommendationEngine, gamification, ...)
        // expects, not BktEngine::trace()'s raw 0.0-1.0 output. BKT default
        // p_init is 0.15 (15), and one correct response must move the
        // posterior mastery above the prior (real update, not a placeholder).
        $this->assertGreaterThan(15.0, (float) $competency->mastery_score);
        $this->assertLessThanOrEqual(100.0, (float) $competency->mastery_score);
    }

    public function test_pal_subjects_is_bridged_to_the_legacy_subject_row(): void
    {
        $this->submitPalAnswer();

        $palSubject = DB::table('pal_subjects')->where('id', $this->subjectId)->first();

        $this->assertNotNull($palSubject, 'pal_subjects must be lazily bridged so the pal_competencies FK is satisfiable.');
    }

    public function test_a_second_submission_recomputes_mastery_from_full_history_and_marks_trend(): void
    {
        $this->submitPalAnswer();
        $firstMastery = (float) DB::table('pal_competencies')
            ->where('learner_id', $this->studentId)
            ->where('subject_id', $this->subjectId)
            ->value('mastery_score');

        // Second question, answered incorrectly.
        $secondQuestionId = (int) DB::table('lms_question_master')->insertGetId([
            'subject_id' => $this->subjectId,
            'question_title' => 'What is 3 + 3?',
            'sub_institute_id' => $this->subInstituteId,
            'status' => 1,
        ]);
        $wrongAnswerId = (int) DB::table('answer_master')->insertGetId([
            'question_id' => $secondQuestionId,
            'answer' => '5',
            'correct_answer' => 1,
        ]);

        $this->submitPalAnswer([
            'question_ids' => [$secondQuestionId],
            'answer_single' => [$secondQuestionId => ($wrongAnswerId + 999) . '##0'],
            'attempt_time' => [$secondQuestionId => 20],
        ]);

        $row = DB::table('pal_competencies')
            ->where('learner_id', $this->studentId)
            ->where('subject_id', $this->subjectId)
            ->whereNull('concept_id')
            ->first();

        $this->assertSame(2, DB::table('pal_assessment_results')->where('learner_id', $this->studentId)->count());
        $this->assertNotSame($firstMastery, (float) $row->mastery_score);
        $this->assertContains($row->proficiency_trend, ['improving', 'declining', 'stable']);
    }
}
