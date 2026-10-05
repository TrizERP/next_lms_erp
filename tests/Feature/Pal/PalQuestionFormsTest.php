<?php

namespace Tests\Feature\Pal;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * What a PAL Test draws, and what it tells the client about each question.
 *
 * TWO THINGS ARE BEING GUARDED, AND BOTH ARE SILENT WHEN BROKEN.
 *
 * The first is the DRAW. PAL used to require at least two `answer_master`
 * rows with one flagged correct, which is right for a question you answer by
 * choosing and impossible for one you answer by typing. A fill-in-the-blank
 * has no options at all, so it could never be drawn however many a chapter
 * held -- and the symptom was not an error, it was a chapter that quietly
 * only ever asked multiple choice.
 *
 * The second is the PAYLOAD. The client picks which H5P player renders a
 * question from the question's own recorded form. Drop `question_type_code` on
 * the way out and every question renders as "no form is recorded on this row"
 * -- which, again, looks like a content problem rather than a missing field.
 */
class PalQuestionFormsTest extends TestCase
{
    use DatabaseTransactions;

    private int $subInstituteId;
    private int $studentId;
    private int $standardId = 1;
    private int $subjectId;
    private int $chapterId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'PAL Forms Test School',
            'ShortCode' => 'PFT' . random_int(1000, 9999),
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
            'first_name' => 'Forms',
            'last_name' => 'Reader',
            'sub_institute_id' => $this->subInstituteId,
            'file_size' => '',
            'file_type' => '',
        ]);

        $this->subjectId = (int) DB::table('subject')->insertGetId([
            'subject_name' => 'Forms Subject ' . random_int(1000, 9999),
            'sub_institute_id' => $this->subInstituteId,
            'status' => 1,
            'created_at' => now(),
        ]);

        // A `type=API` call is authenticated by bearer JWT, and
        // SessionMiddleware hydrates the session from it -- including the
        // current academic term, without which it 422s before the controller
        // ever runs. Every live tenant has one; the fixture has to say so.
        DB::table('academic_year')->insert([
            'term_id' => 1,
            'syear' => (int) date('Y'),
            'sub_institute_id' => $this->subInstituteId,
            'title' => 'Test Term',
            'short_name' => 'TT',
            'sort_order' => 1,
            'start_date' => date('Y-01-01'),
            'end_date' => date('Y-12-31'),
            'post_start_date' => date('Y-01-01'),
            'post_end_date' => date('Y-12-31'),
            'does_grades' => 'Y',
            'does_exams' => 'Y',
        ]);
    }

    private function studentToken(): string
    {
        return JWT::encode(
            [
                'id' => $this->studentId,
                'sub_institute_id' => $this->subInstituteId,
                'is_admin' => 0,
                'is_student' => true,
                'client_id' => null,
            ],
            env('JWT_SECRET'),
            env('JWT_ALGO', 'HS256')
        );
    }

    /** A question with options, answered by choosing one. */
    private function addMultipleChoice(): int
    {
        $id = (int) DB::table('lms_question_master')->insertGetId([
            'question_type_id' => 1,
            'standard_id' => $this->standardId,
            'subject_id' => $this->subjectId,
            'chapter_id' => $this->chapterId,
            'question_title' => 'Which gas do plants absorb?',
            'points' => 1,
            'sub_institute_id' => $this->subInstituteId,
            'status' => 1,
        ]);

        foreach ([['Oxygen', 0], ['Carbon dioxide', 1], ['Nitrogen', 0]] as [$text, $correct]) {
            DB::table('answer_master')->insert([
                'question_id' => $id,
                'answer' => $text,
                'correct_answer' => $correct,
                'sub_institute_id' => $this->subInstituteId,
            ]);
        }

        return $id;
    }

    /** A question with NO options, answered by typing. */
    private function addFillBlank(): int
    {
        return (int) DB::table('lms_question_master')->insertGetId([
            'question_type_id' => 2,
            'standard_id' => $this->standardId,
            'subject_id' => $this->subjectId,
            'chapter_id' => $this->chapterId,
            'question_title' => 'The capital of France is ___.',
            'answer' => json_encode(['item_form' => 'fill_blank', 'model_answer' => 'Paris']),
            'points' => 1,
            'sub_institute_id' => $this->subInstituteId,
            'status' => 1,
        ]);
    }

    /** No options AND no model answer: nothing here could mark it. */
    private function addUnmarkable(): int
    {
        return (int) DB::table('lms_question_master')->insertGetId([
            'question_type_id' => 2,
            'standard_id' => $this->standardId,
            'subject_id' => $this->subjectId,
            'chapter_id' => $this->chapterId,
            'question_title' => 'Discuss the causes of the French Revolution.',
            'points' => 5,
            'sub_institute_id' => $this->subInstituteId,
            'status' => 1,
        ]);
    }

    /** The quiz PAL builds for this student, as the Next client receives it. */
    private function createQuiz(): array
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->studentToken()])
            ->getJson('/lms/pal/create?' . http_build_query([
                'type' => 'API',
                'grade_id' => 1,
                'standard_id' => $this->standardId,
                'subject_id' => $this->subjectId,
                'chapter_id' => $this->chapterId,
            ]));

        $response->assertOk();

        return $response->json() ?? [];
    }

    /** The drawn questions keyed by question id, for assertions that name one. */
    private function drawn(array $payload): array
    {
        $byId = [];
        foreach ($payload['question_arr'] ?? [] as $question) {
            $byId[(int) $question['question_id']] = $question;
        }

        return $byId;
    }

    public function test_a_typed_answer_question_is_drawn_despite_having_no_options(): void
    {
        $blankId = $this->addFillBlank();

        $drawn = $this->drawn($this->createQuiz());

        $this->assertArrayHasKey(
            $blankId,
            $drawn,
            'A fill-in-the-blank has no answer_master rows, so the options-only servability rule could never reach it.'
        );
    }

    public function test_a_drawn_question_carries_the_form_the_client_renders_it_as(): void
    {
        $blankId = $this->addFillBlank();

        $question = $this->drawn($this->createQuiz())[$blankId] ?? null;

        $this->assertNotNull($question);
        $this->assertSame('fill_blank', $question['question_type_code']);
        $this->assertSame('Paris', $question['model_answer']);
        // The scope the player reports its xAPI statements against, read off
        // the question rather than off the paper.
        $this->assertSame($this->chapterId, (int) $question['chapter_id']);
        $this->assertSame($this->subjectId, (int) $question['subject_id']);
    }

    public function test_a_multiple_choice_question_still_reports_both_vocabularies(): void
    {
        $mcqId = $this->addMultipleChoice();

        $payload = $this->createQuiz();
        $question = $this->drawn($payload)[$mcqId] ?? null;

        $this->assertNotNull($question);
        $this->assertSame('mcq', $question['question_type_code']);
        // The grading engine's collapsed spelling, kept alongside the form so
        // the client knows an assertion & reason item still grades as an MCQ.
        $this->assertSame('MCQ', $question['question_type']);

        // And its options still travel, with the ids the submission needs.
        $this->assertCount(3, $payload['answer_arr'][$mcqId] ?? []);
    }

    public function test_a_question_nothing_can_mark_is_not_drawn(): void
    {
        $this->addMultipleChoice();
        $unmarkableId = $this->addUnmarkable();

        $drawn = $this->drawn($this->createQuiz());

        $this->assertArrayNotHasKey(
            $unmarkableId,
            $drawn,
            'Widening the draw must not admit a question with neither options nor a model answer: every attempt at it would record as wrong.'
        );
    }

    public function test_both_kinds_of_question_appear_in_one_paper(): void
    {
        $mcqId = $this->addMultipleChoice();
        $blankId = $this->addFillBlank();

        $drawn = $this->drawn($this->createQuiz());

        $this->assertArrayHasKey($mcqId, $drawn);
        $this->assertArrayHasKey($blankId, $drawn);
    }
}
