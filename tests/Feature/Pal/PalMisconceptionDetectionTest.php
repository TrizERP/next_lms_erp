<?php

namespace Tests\Feature\Pal;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression coverage for the Phase 11 fix: palController::store() (the real
 * PAL submission endpoint) never called misconception detection at all --
 * that logic (recordMisconceptionOnWrongAnswer -> MisconceptionIntelligenceEngine)
 * only existed on onlineExamController::store(), a separate submission path
 * that PAL exams never go through. A wrong PAL answer therefore never became
 * a stored misconception, breaking the whole
 * "wrong answer -> misconception -> remediation -> reassessment" loop before
 * it could even start.
 */
class PalMisconceptionDetectionTest extends TestCase
{
    use DatabaseTransactions;

    private int $subInstituteId;
    private int $studentId;
    private int $subjectId;
    private int $conceptId;
    private int $questionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'Misconception Detection Test School',
            'ShortCode' => 'MDT' . random_int(1000, 9999),
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
            'first_name' => 'Misconception',
            'last_name' => 'Detection',
            'sub_institute_id' => $this->subInstituteId,
            'file_size' => '',
            'file_type' => '',
        ]);

        $this->subjectId = (int) DB::table('subject')->insertGetId([
            'subject_name' => 'Misconception Test Subject ' . random_int(1000, 9999),
            'sub_institute_id' => $this->subInstituteId,
            'status' => 1,
            'created_at' => now(),
        ]);

        $this->conceptId = (int) DB::table('pal_concepts')->insertGetId([
            'name' => 'Test Concept ' . random_int(1000, 9999),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->questionId = (int) DB::table('lms_question_master')->insertGetId([
            'subject_id' => $this->subjectId,
            'concept_id' => $this->conceptId,
            'question_title' => 'Which of these is not a mammal?',
            'sub_institute_id' => $this->subInstituteId,
            'status' => 1,
        ]);

        DB::table('answer_master')->insert([
            'question_id' => $this->questionId,
            'answer' => 'Whale',
            'correct_answer' => 1,
        ]);
    }

    public function test_a_wrong_pal_answer_creates_a_learner_misconception_record(): void
    {
        $wrongAnswerId = (int) DB::table('answer_master')->insertGetId([
            'question_id' => $this->questionId,
            'answer' => 'Shark',
            'correct_answer' => 0,
        ]);

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
            'paper_name' => 'Misconception Detection Test',
            'questionpaper_time' => 10,
            'total_marks' => 1,
            'total_question' => 1,
            'question_ids' => [$this->questionId],
            // "##0" -- the client is declaring this answer wrong.
            'answer_single' => [$this->questionId => $wrongAnswerId . '##0'],
            'attempt_time' => [$this->questionId => 12],
        ]);

        $misconception = DB::table('pal_learner_misconceptions')
            ->where('learner_id', $this->studentId)
            ->where('concept_id', $this->conceptId)
            ->first();

        $this->assertNotNull(
            $misconception,
            'A wrong PAL answer must feed the misconception intelligence engine, same as the legacy exam path does.'
        );
        $this->assertSame('active', $misconception->status);

        $this->assertGreaterThan(
            0,
            DB::table('pal_misconceptions')->where('concept_id', $this->conceptId)->count(),
            'The misconception catalog entry itself should also be created.'
        );
    }

    public function test_a_correct_pal_answer_does_not_create_a_misconception_record(): void
    {
        $correctAnswer = DB::table('answer_master')->where('question_id', $this->questionId)->first();

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
            'paper_name' => 'Misconception Detection Test',
            'questionpaper_time' => 10,
            'total_marks' => 1,
            'total_question' => 1,
            'question_ids' => [$this->questionId],
            'answer_single' => [$this->questionId => $correctAnswer->id . '##1'],
            'attempt_time' => [$this->questionId => 12],
        ]);

        $this->assertSame(
            0,
            DB::table('pal_learner_misconceptions')->where('learner_id', $this->studentId)->count()
        );
    }

    public function test_a_correct_reassessment_resolves_the_learners_active_misconception(): void
    {
        $wrongAnswerId = (int) DB::table('answer_master')->insertGetId([
            'question_id' => $this->questionId,
            'answer' => 'Shark',
            'correct_answer' => 0,
        ]);
        $correctAnswerId = DB::table('answer_master')->where('question_id', $this->questionId)->where('correct_answer', 1)->value('id');

        $session = [
            'user_id' => $this->studentId,
            'sub_institute_id' => $this->subInstituteId,
            'syear' => '2026',
            'user_profile_id' => 1,
            'user_profile_name' => 'Super Admin',
        ];
        $basePayload = [
            'grade_id' => 1,
            'standard_id' => 1,
            'subject_id' => $this->subjectId,
            'chapter_id' => 1,
            'paper_name' => 'Misconception Detection Test',
            'questionpaper_time' => 10,
            'total_marks' => 1,
            'total_question' => 1,
            'question_ids' => [$this->questionId],
            'attempt_time' => [$this->questionId => 12],
        ];

        // First attempt: wrong -- creates an active misconception.
        $this->withSession($session)->post('/lms/pal', $basePayload + [
            'answer_single' => [$this->questionId => $wrongAnswerId . '##0'],
        ]);

        $this->assertSame(
            'active',
            DB::table('pal_learner_misconceptions')->where('learner_id', $this->studentId)->value('status')
        );

        // Reassessment: correct answer on the same concept -- must resolve it.
        $this->withSession($session)->post('/lms/pal', $basePayload + [
            'answer_single' => [$this->questionId => $correctAnswerId . '##1'],
        ]);

        $this->assertSame(
            'resolved',
            DB::table('pal_learner_misconceptions')->where('learner_id', $this->studentId)->value('status'),
            'A correct reassessment on the same concept must resolve the previously active misconception.'
        );
    }
}
