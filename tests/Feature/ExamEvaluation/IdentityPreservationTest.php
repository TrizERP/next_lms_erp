<?php

namespace Tests\Feature\ExamEvaluation;

use Illuminate\Support\Facades\DB;
use Tests\Support\FakeGeminiClient;

/**
 * A student a teacher has confirmed stays confirmed: through OCR retries, AI
 * retries, a full re-run, and a job that finishes late. Marks and feedback stay
 * on the submission they were written on.
 *
 * The evaluation job is run for real against a scripted model. "Late" is staged
 * by letting a teacher act at the moment the job asks the matcher for its
 * answer -- after the (slow) model call, before the result is saved.
 */
class IdentityPreservationTest extends ExamEvaluationTestCase
{
    use IdentityTestHelpers;

    private int $batchId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPaper();
        $this->setUpScan();
        $this->batchId = $this->makeBatch(['status' => 'Processing']);
    }

    protected function tearDown(): void
    {
        $this->tearDownScan();
        parent::tearDown();
    }

    /** A confirmed sheet queued for marking, as confirming leaves it. */
    private function confirmedPending(int $studentId = 101, string $source = 'enrollment_no'): int
    {
        return $this->bareSheet($this->batchId, [
            'status' => 'Pending', 'student_id' => $studentId, 'identity_source' => $source, 'identity_confidence' => 95,
            'identity_confirmed_at' => now(), 'identity_confirmed_by' => self::TEACHER,
            'detected_enrollment_no' => 'GR101', 'detected_roll_no' => '1', 'detected_student_name' => 'Asha Patel',
        ]);
    }

    // -- an AI match the teacher agreed with is not undone by a re-read --------

    public function test_a_confirmed_ai_match_survives_a_re_read_that_now_points_elsewhere(): void
    {
        $sheetId = $this->confirmedPending(101);

        // The second reading of the scan comes back as Ravi's GR number.
        $this->evaluate($sheetId, new FakeGeminiClient([$this->readerReply(['enrollment_no' => 'GR102', 'roll_no' => '2', 'name' => 'Ravi Shah']), $this->graderReply()]));

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame(101, (int) $sheet->student_id);
        $this->assertSame('enrollment_no', $sheet->identity_source, 'how it was matched is kept');
        $this->assertNotNull($sheet->identity_confirmed_at, 'and the confirmation with it');
        $this->assertSame(self::TEACHER, (int) $sheet->identity_confirmed_by);
        $this->assertEquals(95.0, $sheet->identity_confidence);
        $this->assertSame('GR101', $sheet->detected_enrollment_no, 'what the teacher reviewed is what stays on record');
        $this->assertSame(3, DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->count());
    }

    public function test_a_hand_chosen_student_survives_a_re_read(): void
    {
        $sheetId = $this->confirmedPending(102, 'manual');

        $this->evaluate($sheetId, new FakeGeminiClient([$this->readerReply(), $this->graderReply()]));

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame(102, (int) $sheet->student_id, 'the scan reads as Asha, the teacher said Ravi');
        $this->assertSame('manual', $sheet->identity_source);
    }

    public function test_a_confirmed_student_is_not_withdrawn_by_the_one_sheet_per_student_rule(): void
    {
        // Another sheet claims the same student -- by a race, or an old row. The confirmed sheet is not unassigned for it.
        $this->withoutTheUniqueIndex();
        $other =$this->bareSheet($this->batchId, ['status' => 'Awaiting identity', 'student_id' => 101, 'identity_source' => 'roll_no']);
        $sheetId = $this->confirmedPending(101);

        $this->evaluate($sheetId, new FakeGeminiClient([$this->readerReply(), $this->graderReply()]));

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame(101, (int) $sheet->student_id);
        $this->assertContains($sheet->status, ['Evaluated', 'Needs review'], 'it is marked, not left without a student');
        $this->assertSame(101, (int) $this->sheetRow($other)->student_id, 'and the other sheet is left for a teacher, untouched');
    }

    public function test_an_unconfirmed_suggestion_is_still_refreshed_by_a_re_read(): void
    {
        // Nobody has agreed to this one, so a better reading may replace it.
        $sheetId = $this->bareSheet($this->batchId, ['status' => 'Pending', 'student_id' => 101, 'identity_source' => 'roll_no']);

        $this->evaluate($sheetId, new FakeGeminiClient([$this->readerReply(['enrollment_no' => 'GR102', 'roll_no' => '2', 'name' => 'Ravi Shah']), $this->graderReply()]));

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame(102, (int) $sheet->student_id);
        $this->assertSame('enrollment_no', $sheet->identity_source);
        $this->assertNull($sheet->identity_confirmed_at, 'a suggestion does not become a confirmation by being re-read');
    }

    // -- retries and re-runs ------------------------------------------------

    public function test_a_retried_run_after_a_provider_failure_keeps_the_confirmed_student(): void
    {
        $sheetId = $this->confirmedPending(102, 'manual');

        try {
            $this->evaluate($sheetId, new FakeGeminiClient([new \App\Services\Homework\Exceptions\ProviderBusyException('busy')]));
            $this->fail('the transient failure should have been raised to the queue');
        } catch (\App\Services\Homework\Exceptions\ProviderBusyException) {
            // the queue retries; the sheet is mid-run
        }

        $this->assertSame(102, (int) $this->sheetRow($sheetId)->student_id, 'a failed attempt changes nothing about who it is');

        // The retry: the sheet is still Processing, as a redelivered job finds it.
        $this->evaluate($sheetId, new FakeGeminiClient([$this->readerReply(), $this->graderReply()]));

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame(102, (int) $sheet->student_id);
        $this->assertSame('manual', $sheet->identity_source);
    }

    public function test_a_full_re_run_keeps_the_student_the_marks_and_the_feedback_together(): void
    {
        $sheetId = $this->confirmedPending(101);
        $this->evaluate($sheetId, new FakeGeminiClient([$this->readerReply(), $this->graderReply(3)]));

        DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->where('question_no', 2)
            ->update(['teacher_marks' => 4.5, 'teacher_feedback' => 'Good, but name chlorophyll.']);
        DB::table('exam_evaluation_sheet')->where('id', $sheetId)->update(['status' => 'Pending']);   // "Re-run AI"

        $this->evaluate($sheetId, new FakeGeminiClient([$this->readerReply(['enrollment_no' => 'GR102', 'roll_no' => '2']), $this->graderReply(2)]));

        $sheet = $this->sheetRow($sheetId);
        $answers = DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->orderBy('question_no')->get();

        $this->assertSame(101, (int) $sheet->student_id);
        $this->assertCount(3, $answers, 'no rows duplicated, none lost');
        $this->assertEquals(4.5, $answers[1]->teacher_marks);
        $this->assertSame('Good, but name chlorophyll.', $answers[1]->teacher_feedback);
        $this->assertEquals(2.0, $answers[1]->ai_marks, 'only the AI side is refreshed');
        $this->assertSame(0, DB::table('exam_evaluation_answer')->where('sheet_id', '!=', $sheetId)->count(), 'nothing leaked onto another submission');
    }

    // -- a late job must not change who the sheet belongs to --------------------

    public function test_a_teacher_who_confirms_a_different_student_while_marking_runs_is_not_overwritten(): void
    {
        $sheetId = $this->bareSheet($this->batchId, ['status' => 'Processing', 'student_id' => 101, 'identity_source' => 'enrollment_no']);

        // The model is still reading the scan -- which says Asha -- when the teacher picks Ravi.
        $matcher = $this->matcherThat(function () use ($sheetId) {
            DB::table('exam_evaluation_sheet')->where('id', $sheetId)->update([
                'student_id' => 102, 'identity_source' => 'manual', 'identity_confidence' => 100,
                'identity_confirmed_at' => now(), 'identity_confirmed_by' => self::TEACHER,
            ]);
        });

        $this->evaluate($sheetId, new FakeGeminiClient([$this->readerReply(), $this->graderReply()]), $matcher);

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame(102, (int) $sheet->student_id, 'the late job must not put the AI\'s reading back on the sheet');
        $this->assertSame('manual', $sheet->identity_source);
        $this->assertNotNull($sheet->identity_confirmed_at);
        $this->assertSame(3, DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->count(), 'the marks still land, on the right sheet');
    }

    public function test_a_teacher_who_confirms_the_suggestion_while_marking_runs_keeps_it_when_the_read_changes_its_mind(): void
    {
        $sheetId = $this->bareSheet($this->batchId, ['status' => 'Processing', 'student_id' => 101, 'identity_source' => 'roll_no']);

        $matcher = $this->matcherThat(function () use ($sheetId) {
            DB::table('exam_evaluation_sheet')->where('id', $sheetId)->update(['identity_confirmed_at' => now(), 'identity_confirmed_by' => self::TEACHER]);
        });

        // The full read now names Ravi.
        $this->evaluate($sheetId, new FakeGeminiClient([$this->readerReply(['enrollment_no' => 'GR102', 'roll_no' => '2', 'name' => 'Ravi Shah']), $this->graderReply()]), $matcher);

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame(101, (int) $sheet->student_id);
        $this->assertSame('roll_no', $sheet->identity_source);
    }

    public function test_a_stale_evaluation_job_for_a_sheet_still_waiting_for_its_student_does_nothing(): void
    {
        // An evaluation queued before this flow existed, arriving after the sheet was sent back to identification.
        $sheetId = $this->awaitingSheet($this->batchId);
        $gemini = new FakeGeminiClient([]);

        $this->evaluate($sheetId, $gemini);

        $this->assertSame([], $gemini->calls, 'no marking, no cost, until a teacher has confirmed the student');
        $sheet = $this->sheetRow($sheetId);
        $this->assertSame('Awaiting identity', $sheet->status);
        $this->assertSame(101, (int) $sheet->student_id);
        $this->assertSame(0, DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->count());
    }

    public function test_a_stale_job_never_unassigns_or_reassigns_an_approved_sheet(): void
    {
        $sheetId = $this->makeSheet($this->batchId, ['status' => 'Approved', 'student_id' => 101, 'teacher_total' => 9]);
        $gemini = new FakeGeminiClient([]);

        $this->evaluate($sheetId, $gemini);

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame([], $gemini->calls);
        $this->assertSame('Approved', $sheet->status);
        $this->assertSame(101, (int) $sheet->student_id);
    }

    // -- what the first stage found is not lost when marking begins ---------------

    public function test_the_identity_stage_notes_are_kept_alongside_the_marking_result(): void
    {
        $sheetId = $this->confirmedPending(101);
        DB::table('exam_evaluation_sheet')->where('id', $sheetId)->update([
            'ai_result_json' => json_encode(['student' => [], 'identity' => ['page' => 2]]),
        ]);

        $this->evaluate($sheetId, new FakeGeminiClient([$this->readerReply(), $this->graderReply()]));

        $this->assertSame(2, $this->identityBlock($sheetId)['page']);
        $this->assertEquals(5.0, json_decode((string) $this->sheetRow($sheetId)->ai_result_json, true)['ai_total']);
    }
}
