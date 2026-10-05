<?php

namespace Tests\Feature\Pal;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The PAL Test's second answer channel: questions that are TYPED, not chosen.
 *
 * WHY THESE QUESTIONS EXIST IN PAL AT ALL NOW. Every PAL Test question is
 * rendered as the H5P activity its recorded form calls for -- a blank as
 * Blanks, a match-the-following as a matching activity, a multiple choice as a
 * single choice set. The first two are answered by typing or dragging, so they
 * have no `answer_master` row to send back, and the `id##flag` pair every
 * other PAL answer travels as has nothing to carry.
 *
 * They arrive on `answer_interactive` instead, and everything below is about
 * the ways that can go quietly wrong: not being counted, being counted as
 * unanswered, or -- worst -- being routed through the narrative branch, which
 * marks every answer 'right' on the grounds that prose cannot be marked.
 */
class PalInteractiveSubmissionTest extends TestCase
{
    use DatabaseTransactions;

    private int $subInstituteId;
    private int $studentId;
    private int $subjectId;
    /** A fill-in-the-blank: a model answer, and no options at all. */
    private int $typedQuestionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'PAL Interactive Test School',
            'ShortCode' => 'PIT' . random_int(1000, 9999),
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
            'first_name' => 'Typed',
            'last_name' => 'Answer',
            'sub_institute_id' => $this->subInstituteId,
            'file_size' => '',
            'file_type' => '',
        ]);

        $this->subjectId = (int) DB::table('subject')->insertGetId([
            'subject_name' => 'Interactive Subject ' . random_int(1000, 9999),
            'sub_institute_id' => $this->subInstituteId,
            'status' => 1,
            'created_at' => now(),
        ]);

        $this->typedQuestionId = (int) DB::table('lms_question_master')->insertGetId([
            'subject_id' => $this->subjectId,
            'question_title' => 'The capital of France is ___.',
            'answer' => json_encode(['item_form' => 'fill_blank', 'model_answer' => 'Paris']),
            'points' => 1,
            'sub_institute_id' => $this->subInstituteId,
            'status' => 1,
        ]);
    }

    /** @param array<string, mixed> $interactive */
    private function submit(array $interactive, array $overrides = []): void
    {
        $payload = array_merge([
            'grade_id' => 1,
            'standard_id' => 1,
            'subject_id' => $this->subjectId,
            'chapter_id' => 1,
            'paper_name' => 'PAL Interactive Test',
            'questionpaper_time' => 10,
            'total_marks' => 1,
            'total_question' => 1,
            'question_ids' => [$this->typedQuestionId],
            'answer_interactive' => $interactive,
            'attempt_time' => [$this->typedQuestionId => 12],
        ], $overrides);

        $this->withSession([
            'user_id' => $this->studentId,
            'sub_institute_id' => $this->subInstituteId,
            'syear' => '2026',
            'user_profile_id' => 1,
            'user_profile_name' => 'Super Admin',
        ])->post('/lms/pal', $payload);
    }

    private function answerRow(): ?object
    {
        return DB::table('lms_online_exam_answer')
            ->where('student_id', $this->studentId)
            ->where('question_id', $this->typedQuestionId)
            ->first();
    }

    private function examRow(): ?object
    {
        return DB::table('lms_online_exam')
            ->where('student_id', $this->studentId)
            ->orderByDesc('id')
            ->first();
    }

    public function test_a_typed_answer_is_recorded_with_the_response_and_no_answer_id(): void
    {
        $this->submit([
            $this->typedQuestionId => json_encode([
                'correct' => 1,
                'response' => 'Paris',
                'score' => 1,
                'max_score' => 1,
            ]),
        ]);


        $row = $this->answerRow();

        $this->assertNotNull($row, 'A typed answer must be recorded, not dropped for having no answer_id.');
        $this->assertSame('right', $row->ans_status);
        $this->assertSame('Paris', $row->narrative_answer);
        $this->assertNull(
            $row->answer_id,
            'There is no answer_master row behind a typed answer, so inventing one would be a lie in the audit trail.'
        );
    }

    public function test_a_typed_answer_counts_toward_the_paper_total(): void
    {
        $this->submit([
            $this->typedQuestionId => json_encode([
                'correct' => 1, 'response' => 'Paris', 'score' => 1, 'max_score' => 1,
            ]),
        ]);

        $exam = $this->examRow();

        // get_calculate_marks() is shared with the standard online exam and
        // knows nothing about this channel, so a paper made entirely of typed
        // questions would otherwise score zero out of zero.
        $this->assertSame(1, (int) $exam->total_right);
        $this->assertSame(0, (int) $exam->total_wrong);
        $this->assertSame(1, (int) $exam->obtain_marks);
    }

    public function test_a_wrong_typed_answer_is_marked_wrong_not_right(): void
    {
        // The narrative branch marks every answer 'right' because prose cannot
        // be marked. These CAN be marked, against the question's own stored
        // answer, so routing them there would hand full marks for typing
        // anything at all.
        $this->submit([
            $this->typedQuestionId => json_encode([
                'correct' => 0, 'response' => 'Lyon', 'score' => 0, 'max_score' => 1,
            ]),
        ]);

        $this->assertSame('wrong', $this->answerRow()->ans_status);
        $this->assertSame(1, (int) $this->examRow()->total_wrong);
    }

    public function test_a_fabricated_correctness_claim_is_overruled_by_the_stored_answer(): void
    {
        // The same standard the option path is held to: a client that claims
        // it was right is checked against what the question says, wherever the
        // server can check it.
        $this->submit([
            $this->typedQuestionId => json_encode([
                'correct' => 1, 'response' => 'Lyon', 'score' => 1, 'max_score' => 1,
            ]),
        ]);

        $this->assertSame(
            'wrong',
            $this->answerRow()->ans_status,
            'A typed answer must be marked against the stored model answer, not the client-declared verdict.'
        );

        $result = DB::table('pal_assessment_results')
            ->where('learner_id', $this->studentId)
            ->where('question_id', $this->typedQuestionId)
            ->first();

        $this->assertNotNull($result);
        $this->assertSame(0, (int) $result->is_correct);
    }

    public function test_a_typed_answer_is_marked_case_and_punctuation_insensitively(): void
    {
        $this->submit([
            $this->typedQuestionId => json_encode([
                'correct' => 0, 'response' => '  paris. ', 'score' => 0, 'max_score' => 1,
            ]),
        ]);

        $this->assertSame(
            'right',
            $this->answerRow()->ans_status,
            'A learner who typed the right word with different casing has answered correctly, '
            . 'and the server says so even when the client scored it wrong.'
        );
    }

    public function test_a_multi_slot_answer_is_left_to_the_player_that_marked_it(): void
    {
        // A passage with four blanks is marked blank by blank against a key
        // the CLIENT derives from the stem's markup. This server holds one
        // model answer and no per-blank key, so comparing the joined response
        // against it would mark a correct attempt wrong. It declines instead.
        $this->submit([
            $this->typedQuestionId => json_encode([
                'correct' => 1,
                'response' => 'Paris, Seine, Eiffel, Louvre',
                'score' => 4,
                'max_score' => 4,
            ]),
        ]);

        $this->assertSame(
            'right',
            $this->answerRow()->ans_status,
            'A multi-slot answer must keep the player verdict; re-marking it against a single model answer is a false negative.'
        );
    }

    public function test_a_typed_answer_feeds_mastery_evidence(): void
    {
        $this->submit([
            $this->typedQuestionId => json_encode([
                'correct' => 1, 'response' => 'Paris', 'score' => 1, 'max_score' => 1,
            ]),
        ]);

        $result = DB::table('pal_assessment_results')
            ->where('learner_id', $this->studentId)
            ->where('question_id', $this->typedQuestionId)
            ->first();

        $this->assertNotNull(
            $result,
            'A typed answer is evidence like any other; excluding it would make mastery read only the multiple-choice half of a paper.'
        );
        $this->assertSame(1, (int) $result->is_correct);
        $this->assertSame(12000, (int) $result->response_time_ms);
    }

    public function test_an_unreadable_entry_is_recorded_as_unattempted(): void
    {
        $this->submit([$this->typedQuestionId => 'not json at all']);

        $row = $this->answerRow();
        $this->assertNull($row, 'Nothing interpretable was submitted, so there is no answer to record.');

        $studentRow = DB::table('lms_online_exam_answer_student')
            ->where('student_id', $this->studentId)
            ->where('question_id', $this->typedQuestionId)
            ->first();

        // The unanswered sweep still accounts for it, so the paper's question
        // count and skip rate stay honest.
        $this->assertNotNull($studentRow);
        $this->assertSame('wrong', $studentRow->ans_status);
    }
}
