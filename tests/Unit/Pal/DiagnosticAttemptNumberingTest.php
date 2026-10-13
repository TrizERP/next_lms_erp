<?php

namespace Tests\Unit\Pal;

use App\Models\PAL\DiagnosticAttempt;
use App\Models\PAL\DiagnosticResponse;
use App\Services\PAL\Diagnostic\DiagnosticService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * attempt_number is frozen by DiagnosticService::submit() - counting only
 * already-SUBMITTED attempts, per (student_id, chapter_id), never by
 * DiagnosticQuestionSelector (which draws real papers from real question
 * banks - irrelevant to this logic). submit() only needs an attempt plus its
 * response rows, so these are built directly rather than through start(),
 * which keeps this test independent of any chapter's actual question stock.
 */
class DiagnosticAttemptNumberingTest extends TestCase
{
    use DatabaseTransactions;

    private int $subInstituteId = 999001;
    private int $studentId = 999001;
    private int $otherStudentId = 999002;
    private int $chapterId = 999001;
    private int $otherChapterId = 999002;
    private int $subjectId = 999001;

    /**
     * Minimal attempt + one unanswered response row - enough for submit() to
     * score and number, since submit() never touches the selector/question
     * bank, only the attempt's own already-written response rows.
     */
    private function makeAttempt(int $studentId, int $chapterId): DiagnosticAttempt
    {
        $questionId = (int) DB::table('lms_question_master')->insertGetId([
            'subject_id' => $this->subjectId,
            'question_title' => 'Numbering test question',
            'sub_institute_id' => $this->subInstituteId,
            'status' => 1,
        ]);

        DB::table('answer_master')->insert([
            'question_id' => $questionId,
            'answer' => 'Right',
            'correct_answer' => 1,
        ]);

        $attempt = DiagnosticAttempt::create([
            'student_id' => $studentId,
            'subject_id' => $this->subjectId,
            'chapter_id' => $chapterId,
            'standard_id' => null,
            'sub_institute_id' => $this->subInstituteId,
            'syear' => 2026,
            'status' => DiagnosticAttempt::STATUS_IN_PROGRESS,
            'total_questions' => 1,
            'correct' => 0, 'incorrect' => 0, 'unanswered' => 1,
            'percentage' => 0,
            'level' => null,
            'selection_report' => [],
            'started_at' => now(),
        ]);

        DiagnosticResponse::create([
            'attempt_id' => $attempt->id,
            'question_id' => $questionId,
            'answer_master_id' => null,
            'is_correct' => 0,
            'difficulty_served' => 'easy',
            'difficulty_source' => 'dok',
            'concept_exact' => 0,
            'sequence' => 1,
            'answered_at' => null,
        ]);

        return $attempt;
    }

    public function test_first_submitted_attempt_for_a_student_and_chapter_is_numbered_one(): void
    {
        $service = app(DiagnosticService::class);
        $attempt = $this->makeAttempt($this->studentId, $this->chapterId);

        $result = $service->submit($attempt, []);

        $this->assertSame(1, $result['attempt_number']);
        $this->assertSame(1, $attempt->refresh()->attempt_number);
    }

    public function test_second_submitted_attempt_for_the_same_student_and_chapter_is_numbered_two(): void
    {
        $service = app(DiagnosticService::class);

        $first = $this->makeAttempt($this->studentId, $this->chapterId);
        $service->submit($first, []);

        $second = $this->makeAttempt($this->studentId, $this->chapterId);
        $result = $service->submit($second, []);

        $this->assertSame(2, $result['attempt_number']);
    }

    public function test_an_in_progress_attempt_is_never_numbered(): void
    {
        $attempt = $this->makeAttempt($this->studentId, $this->chapterId);

        $this->assertNull($attempt->attempt_number);
    }

    public function test_numbering_is_independent_per_chapter(): void
    {
        $service = app(DiagnosticService::class);

        $onFirstChapter = $this->makeAttempt($this->studentId, $this->chapterId);
        $service->submit($onFirstChapter, []);

        $onOtherChapter = $this->makeAttempt($this->studentId, $this->otherChapterId);
        $result = $service->submit($onOtherChapter, []);

        $this->assertSame(1, $result['attempt_number'], 'A different chapter must start its own count at 1.');
    }

    public function test_numbering_is_independent_per_student(): void
    {
        $service = app(DiagnosticService::class);

        $forFirstStudent = $this->makeAttempt($this->studentId, $this->chapterId);
        $service->submit($forFirstStudent, []);

        $forOtherStudent = $this->makeAttempt($this->otherStudentId, $this->chapterId);
        $result = $service->submit($forOtherStudent, []);

        $this->assertSame(1, $result['attempt_number'], 'A different student on the same chapter must start their own count at 1.');
    }
}
