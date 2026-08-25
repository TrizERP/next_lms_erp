<?php

namespace Tests\Feature\Pal;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression coverage for the PAL grading-integrity fix: answer_single/
 * answer_multiple correctness was a client-declared "<answer_id>##0_or_1"
 * flag, trusted outright by everything -- including, before this fix,
 * PAL's own pal_assessment_results/pal_competencies/misconception writes.
 * A student could submit "##1" for an objectively wrong answer_id and PAL
 * would record it as a correct response, inflating BKT mastery and
 * suppressing misconception detection.
 *
 * isAnswerCorrectServerSide() now verifies against answer_master.correct_answer
 * for everything PAL's own intelligence consumes. The legacy score/
 * lms_online_exam_answer.ans_status path is deliberately left as-is (a
 * separate, wider-scoped, not-yet-decided change) -- this test asserts PAL's
 * signal is now trustworthy even though the legacy display score is not.
 */
class PalGradingIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    private int $subInstituteId;
    private int $studentId;
    private int $subjectId;
    private int $questionId;
    private int $wrongAnswerId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'Grading Integrity Test School',
            'ShortCode' => 'GIT' . random_int(1000, 9999),
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
            'first_name' => 'Grading',
            'last_name' => 'Integrity',
            'sub_institute_id' => $this->subInstituteId,
            'file_size' => '',
            'file_type' => '',
        ]);

        $this->subjectId = (int) DB::table('subject')->insertGetId([
            'subject_name' => 'Grading Integrity Subject ' . random_int(1000, 9999),
            'sub_institute_id' => $this->subInstituteId,
            'status' => 1,
            'created_at' => now(),
        ]);

        $conceptId = (int) DB::table('pal_concepts')->insertGetId([
            'name' => 'Grading Integrity Concept ' . random_int(1000, 9999),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->questionId = (int) DB::table('lms_question_master')->insertGetId([
            'subject_id' => $this->subjectId,
            'concept_id' => $conceptId,
            'question_title' => 'Grading integrity test question',
            'sub_institute_id' => $this->subInstituteId,
            'status' => 1,
        ]);

        DB::table('answer_master')->insert([
            'question_id' => $this->questionId,
            'answer' => 'Actually correct option',
            'correct_answer' => 1,
        ]);
        $this->wrongAnswerId = (int) DB::table('answer_master')->insertGetId([
            'question_id' => $this->questionId,
            'answer' => 'Actually wrong option',
            'correct_answer' => 0,
        ]);
    }

    public function test_a_fabricated_correctness_claim_does_not_fool_pal_assessment_results(): void
    {
        $this->withSession([
            'user_id' => $this->studentId,
            'sub_institute_id' => $this->subInstituteId,
            'syear' => '2026',
            'user_profile_id' => 1,
            'user_profile_name' => 'Super Admin',
        ])->post('/lms/pal', [
            'grade_id' => 1,
            'standard_id' => 1,
            'subject_id' => $this->subjectId,
            'chapter_id' => 1,
            'paper_name' => 'Grading Integrity Test',
            'questionpaper_time' => 10,
            'total_marks' => 1,
            'total_question' => 1,
            'question_ids' => [$this->questionId],
            // The client picked the WRONG answer_id but claims "##1" (correct).
            'answer_single' => [$this->questionId => $this->wrongAnswerId . '##1'],
            'attempt_time' => [$this->questionId => 12],
        ]);

        $result = DB::table('pal_assessment_results')
            ->where('learner_id', $this->studentId)
            ->where('question_id', $this->questionId)
            ->first();

        $this->assertNotNull($result);
        $this->assertSame(
            0,
            (int) $result->is_correct,
            'PAL must grade the actual answer_id against answer_master, not trust the client-declared ##1/##0 flag.'
        );

        $misconception = DB::table('pal_learner_misconceptions')
            ->where('learner_id', $this->studentId)
            ->first();
        $this->assertNotNull(
            $misconception,
            'An objectively wrong answer must still trigger misconception detection, regardless of the client claiming it was correct.'
        );
    }

    public function test_a_fabricated_wrong_claim_on_an_actually_correct_answer_still_credits_mastery(): void
    {
        $correctAnswerId = DB::table('answer_master')
            ->where('question_id', $this->questionId)->where('correct_answer', 1)->value('id');

        $this->withSession([
            'user_id' => $this->studentId,
            'sub_institute_id' => $this->subInstituteId,
            'syear' => '2026',
            'user_profile_id' => 1,
            'user_profile_name' => 'Super Admin',
        ])->post('/lms/pal', [
            'grade_id' => 1,
            'standard_id' => 1,
            'subject_id' => $this->subjectId,
            'chapter_id' => 1,
            'paper_name' => 'Grading Integrity Test',
            'questionpaper_time' => 10,
            'total_marks' => 1,
            'total_question' => 1,
            'question_ids' => [$this->questionId],
            // Picked the actually-correct answer_id, but the client (perhaps
            // buggy, not just malicious) claims "##0".
            'answer_single' => [$this->questionId => $correctAnswerId . '##0'],
            'attempt_time' => [$this->questionId => 12],
        ]);

        $result = DB::table('pal_assessment_results')
            ->where('learner_id', $this->studentId)
            ->where('question_id', $this->questionId)
            ->first();

        $this->assertSame(1, (int) $result->is_correct);
    }
}
