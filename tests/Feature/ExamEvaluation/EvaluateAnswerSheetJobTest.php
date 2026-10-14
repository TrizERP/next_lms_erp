<?php

namespace Tests\Feature\ExamEvaluation;

use App\Jobs\EvaluateAnswerSheetJob;
use App\Services\Evaluation\AnswerKeyService;
use App\Services\Evaluation\AnswerSheetReaderService;
use App\Services\Evaluation\AnswerSheetScoringService;
use App\Services\Evaluation\ExamEvaluationStorage;
use App\Services\Evaluation\StudentMatcherService;
use App\Services\Homework\Exceptions\ModelQuotaExhaustedException;
use App\Services\Homework\Exceptions\ProviderBusyException;
use App\Services\Homework\Exceptions\RateLimitedException;
use App\Services\Homework\HomeworkAnnotatedPdfService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeGeminiClient;

/**
 * The background job, run for real against the real services with the model
 * replaced by a script: reader reply first, then the written-answer grader's.
 */
class EvaluateAnswerSheetJobTest extends ExamEvaluationTestCase
{
    private int $batchId;

    private string $scan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPaper();
        $this->batchId = $this->makeBatch(['status' => 'Processing']);
        $this->scan = (string) tempnam(sys_get_temp_dir(), 'exam-eval-scan-');
        file_put_contents($this->scan, '%PDF-1.4 test scan');
    }

    protected function tearDown(): void
    {
        @unlink($this->scan);
        parent::tearDown();
    }

    /** A pending sheet, as the upload endpoint leaves it. */
    private function pendingSheet(array $over = []): int
    {
        $sheetId = $this->makeSheet($this->batchId, $over + ['status' => 'Pending', 'student_id' => null, 'identity_source' => null, 'ai_total' => null, 'percentage' => null]);
        DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->delete();

        return $sheetId;
    }

    /** @return array<string,mixed> What the vision reader returns for a clean three-question sheet. */
    private function readerReply(array $over = []): array
    {
        return array_replace_recursive([
            'student' => ['roll_no' => '1', 'enrollment_no' => 'GR101', 'name' => 'Asha Patel', 'confidence' => 90],
            'responses' => [
                ['question_no' => 1, 'attempted' => true, 'selected_options' => ['A'], 'answer_text' => '', 'page' => 1, 'box_2d' => [100, 100, 200, 200]],
                ['question_no' => 2, 'attempted' => true, 'selected_options' => [], 'answer_text' => 'Plants use sunlight and water', 'page' => 2, 'box_2d' => null],
                ['question_no' => 3, 'attempted' => true, 'selected_options' => [], 'answer_text' => 'It speeds up a reaction', 'page' => 2, 'box_2d' => null],
            ],
        ], $over);
    }

    /** The grader is only asked about Q2: Q3 has no model answer. */
    private function graderReply(float $marks = 3, float $confidence = 80): array
    {
        return ['results' => [['question_no' => 2, 'marks' => $marks, 'confidence' => $confidence, 'remark' => 'Two points made.']]];
    }

    private function evaluate(int $sheetId, FakeGeminiClient $gemini): void
    {
        $scan = $this->scan;

        $storage = new class($scan) extends ExamEvaluationStorage {
            public function __construct(private string $path)
            {
            }

            public function sheetPath(string $fileName): ?string
            {
                return $this->path;
            }

            public function putAnnotated(int $sheetId, string $pdfBinary): string
            {
                return 'annotated-test.pdf';
            }
        };

        $annotator = new class extends HomeworkAnnotatedPdfService {
            public function annotate(string $originalAbsolutePath, string $mimeType, array $annotations): string
            {
                return '%PDF-1.4 marked up';
            }
        };

        (new EvaluateAnswerSheetJob($sheetId))->handle(
            app(AnswerKeyService::class),
            new AnswerSheetReaderService($gemini),
            new AnswerSheetScoringService($gemini),
            new StudentMatcherService(),
            $annotator,
            $storage
        );
    }

    private function answers(int $sheetId): \Illuminate\Support\Collection
    {
        return DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->orderBy('question_no')->get()->keyBy('question_no');
    }

    public function test_first_run_stores_ai_proposals_for_the_right_student_and_never_a_teacher_mark(): void
    {
        $sheetId = $this->pendingSheet();
        $gemini = new FakeGeminiClient([$this->readerReply(), $this->graderReply()]);

        $this->evaluate($sheetId, $gemini);

        $sheet = DB::table('exam_evaluation_sheet')->find($sheetId);
        $answers = $this->answers($sheetId);

        // Student matched on the GR number, even though they hold two term enrollments.
        $this->assertSame(101, (int) $sheet->student_id);
        $this->assertSame('enrollment_no', $sheet->identity_source);

        // Objective: scored against the key in PHP.
        $this->assertEquals(2.0, $answers[1]->ai_marks);
        $this->assertSame('correct', $answers[1]->status);
        // Written: the AI's proposal, with partial marks.
        $this->assertEquals(3.0, $answers[2]->ai_marks);
        $this->assertSame('partially_correct', $answers[2]->status);
        // Page numbers from a multi-page scan are kept per answer.
        $this->assertSame([1, 2, 2], $answers->pluck('page')->map(fn ($p) => (int) $p)->values()->all());
        // No teacher mark, no approval: a proposal only.
        $this->assertSame([null, null, null], $answers->pluck('teacher_marks')->values()->all());
        $this->assertSame('Needs review', $sheet->status);
        $this->assertEquals(5.0, $sheet->ai_total);
        $this->assertEquals(50.0, $sheet->percentage);
        $this->assertNull($sheet->approved_at);
    }

    public function test_a_question_with_no_model_answer_is_flagged_not_guessed_at(): void
    {
        $sheetId = $this->pendingSheet();
        $gemini = new FakeGeminiClient([$this->readerReply(), $this->graderReply()]);

        $this->evaluate($sheetId, $gemini);

        $q3 = $this->answers($sheetId)[3];
        $this->assertEquals(0.0, $q3->ai_marks);
        $this->assertEquals(0.0, $q3->ai_confidence, 'zero confidence is what puts it in front of the teacher');
        $this->assertStringContainsString('No model answer is saved', $q3->ai_remark);
        // And the model was never asked to invent a scheme for it.
        $this->assertStringNotContainsString('Define a catalyst', $gemini->promptText(1));
    }

    public function test_the_grader_is_shown_the_questions_marking_points_as_a_rubric(): void
    {
        $sheetId = $this->pendingSheet();
        $gemini = new FakeGeminiClient([$this->readerReply(), $this->graderReply()]);

        $this->evaluate($sheetId, $gemini);

        $prompt = $gemini->promptText(1);
        $this->assertStringContainsString('MARKING POINT 1 (1 mark): Names carbon dioxide as an input', $prompt);
        $this->assertStringContainsString('ACCEPT: CO2; carbon dioxide', $prompt);
        $this->assertStringContainsString('REJECT: oxygen as an input', $prompt);
        $this->assertStringContainsString('COMMON ERROR (at most 1 marks): Says plants breathe in oxygen', $prompt);
        $this->assertStringContainsString('KEYWORDS: chlorophyll (green pigment)', $prompt);
        // Not the raw JSON blob it used to receive as the "model answer".
        $this->assertStringNotContainsString('"marking_points"', $prompt);
    }

    public function test_rerunning_a_sheet_keeps_what_the_teacher_wrote_and_adds_no_rows(): void
    {
        $sheetId = $this->pendingSheet();
        $this->evaluate($sheetId, new FakeGeminiClient([$this->readerReply(), $this->graderReply(3)]));

        // The teacher works on the sheet, and assigns a student by hand.
        DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->where('question_no', 2)
            ->update(['teacher_marks' => 4.5, 'teacher_feedback' => 'Good, but name chlorophyll.']);
        DB::table('exam_evaluation_sheet')->where('id', $sheetId)->update(['student_id' => 102, 'identity_source' => 'manual', 'status' => 'Pending']);

        // Re-run: the model now proposes 2.
        $this->evaluate($sheetId, new FakeGeminiClient([$this->readerReply(), $this->graderReply(2)]));

        $answers = $this->answers($sheetId);
        $this->assertCount(3, $answers, 're-running must not duplicate answer rows');
        $this->assertEquals(2.0, $answers[2]->ai_marks, 'the AI side is refreshed');
        $this->assertEquals(4.5, $answers[2]->teacher_marks, 'the teacher side is not');
        $this->assertSame('Good, but name chlorophyll.', $answers[2]->teacher_feedback);

        $sheet = DB::table('exam_evaluation_sheet')->find($sheetId);
        $this->assertSame(102, (int) $sheet->student_id, 'a hand-assigned student is not undone by re-reading the scan');
        $this->assertSame('manual', $sheet->identity_source);
        // Totals reflect the teacher's mark: 2 (Q1) + 4.5 (Q2, teacher) + 0 (Q3) = 6.5 of 10.
        $this->assertEquals(6.5, $sheet->teacher_total);
        $this->assertEquals(65.0, $sheet->percentage);
    }

    public function test_a_retried_run_leaves_one_row_per_question(): void
    {
        $sheetId = $this->pendingSheet();

        $this->evaluate($sheetId, new FakeGeminiClient([$this->readerReply(), $this->graderReply()]));
        DB::table('exam_evaluation_sheet')->where('id', $sheetId)->update(['status' => 'Processing']); // a redelivered job
        $this->evaluate($sheetId, new FakeGeminiClient([$this->readerReply(), $this->graderReply()]));

        $this->assertSame(3, DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->count());
    }

    public function test_a_stale_job_never_touches_an_approved_sheet(): void
    {
        $sheetId = $this->makeSheet($this->batchId, ['status' => 'Approved', 'teacher_total' => 9, 'approved_by' => self::TEACHER]);
        $gemini = new FakeGeminiClient([]);

        $this->evaluate($sheetId, $gemini);

        $this->assertSame([], $gemini->calls, 'no model call may be made for finished work');
        $sheet = DB::table('exam_evaluation_sheet')->find($sheetId);
        $this->assertSame('Approved', $sheet->status);
        $this->assertEquals(9.0, $sheet->teacher_total);
        $this->assertSame(3, DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->count());
    }

    public function test_a_late_failure_does_not_overwrite_an_approved_sheet(): void
    {
        $approved = $this->makeSheet($this->batchId, ['status' => 'Approved']);
        $waiting = $this->makeSheet($this->batchId, ['status' => 'Processing', 'student_id' => 102]);

        (new EvaluateAnswerSheetJob($approved))->failed(new \RuntimeException('too late'));
        (new EvaluateAnswerSheetJob($waiting))->failed(new \RuntimeException('provider gave up'));

        $this->assertSame('Approved', DB::table('exam_evaluation_sheet')->find($approved)->status);
        $failed = DB::table('exam_evaluation_sheet')->find($waiting);
        $this->assertSame('Failed', $failed->status);
        $this->assertSame('provider gave up', $failed->failure_reason);
    }

    public function test_two_scans_that_read_as_the_same_student_leave_the_second_unmatched(): void
    {
        $first = $this->pendingSheet();
        $second = $this->pendingSheet();

        $this->evaluate($first, new FakeGeminiClient([$this->readerReply(), $this->graderReply()]));
        $this->evaluate($second, new FakeGeminiClient([$this->readerReply(), $this->graderReply()]));

        $this->assertSame(101, (int) DB::table('exam_evaluation_sheet')->find($first)->student_id);
        $secondRow = DB::table('exam_evaluation_sheet')->find($second);
        $this->assertNull($secondRow->student_id);
        $this->assertSame('unmatched', $secondRow->identity_source);
        $this->assertSame('Needs review', $secondRow->status);
        // What was read off the page is still there for the teacher to act on.
        $this->assertSame('GR101', $secondRow->detected_enrollment_no);
    }

    public function test_a_busy_provider_is_raised_for_retry_and_does_not_fail_the_sheet(): void
    {
        $sheetId = $this->pendingSheet();

        try {
            $this->evaluate($sheetId, new FakeGeminiClient([new ProviderBusyException('busy')]));
            $this->fail('the transient failure should have been raised to the queue');
        } catch (ProviderBusyException) {
            // expected
        }

        $this->assertSame('Processing', DB::table('exam_evaluation_sheet')->find($sheetId)->status);
        $this->assertSame(0, DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->count());
    }

    public function test_a_network_timeout_is_raised_for_retry_and_does_not_fail_the_sheet(): void
    {
        $sheetId = $this->pendingSheet();

        try {
            $this->evaluate($sheetId, new FakeGeminiClient([new ConnectionException('cURL error 28: Operation timed out')]));
            $this->fail('a timeout should have been raised to the queue');
        } catch (ConnectionException) {
            // expected
        }

        $this->assertSame('Processing', DB::table('exam_evaluation_sheet')->find($sheetId)->status);
    }

    public function test_a_rate_limit_during_the_written_pass_is_raised_for_retry_not_left_as_zero_marks(): void
    {
        $sheetId = $this->pendingSheet();

        try {
            $this->evaluate($sheetId, new FakeGeminiClient([$this->readerReply(), new RateLimitedException('slow down')]));
            $this->fail('the rate limit should have been raised to the queue');
        } catch (RateLimitedException) {
            // expected
        }

        $this->assertSame(0, DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->count(), 'nothing half-finished is saved');
    }

    public function test_a_spent_model_quota_fails_the_sheet_once_with_a_clear_reason(): void
    {
        $sheetId = $this->pendingSheet();

        $this->evaluate($sheetId, new FakeGeminiClient([new ModelQuotaExhaustedException('The daily allowance for the model is spent.')]));

        $sheet = DB::table('exam_evaluation_sheet')->find($sheetId);
        $this->assertSame('Failed', $sheet->status);
        $this->assertStringContainsString('daily allowance', $sheet->failure_reason);
    }

    public function test_unparseable_handwriting_output_is_retried_once_then_reported(): void
    {
        $sheetId = $this->pendingSheet();
        $gemini = new FakeGeminiClient(['this is not json', 'still { not json']);

        $this->evaluate($sheetId, $gemini);

        $this->assertCount(2, $gemini->calls, 'one retry, no more');
        $this->assertGreaterThan($gemini->configs[0]['temperature'], $gemini->configs[1]['temperature'], 'the retry is not the identical request');
        $sheet = DB::table('exam_evaluation_sheet')->find($sheetId);
        $this->assertSame('Failed', $sheet->status);
        $this->assertStringContainsString('could not be parsed as JSON', $sheet->failure_reason);
    }

    public function test_a_second_attempt_that_parses_is_used(): void
    {
        $sheetId = $this->pendingSheet();

        $this->evaluate($sheetId, new FakeGeminiClient(['{ broken', $this->readerReply(), $this->graderReply()]));

        $this->assertSame('Needs review', DB::table('exam_evaluation_sheet')->find($sheetId)->status);
        $this->assertSame(3, DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->count());
    }

    public function test_a_reading_cut_off_at_the_token_limit_says_so_instead_of_retrying(): void
    {
        $sheetId = $this->pendingSheet();
        $gemini = new FakeGeminiClient([['__text' => '{"student": {"name": "A', 'finish_reason' => 'MAX_TOKENS']]);

        $this->evaluate($sheetId, $gemini);

        $this->assertCount(1, $gemini->calls);
        $sheet = DB::table('exam_evaluation_sheet')->find($sheetId);
        $this->assertSame('Failed', $sheet->status);
        $this->assertStringContainsString('more writing than can be read in one pass', $sheet->failure_reason);
    }

    public function test_uncertain_question_mapping_is_never_invented(): void
    {
        $sheetId = $this->pendingSheet();
        $reply = $this->readerReply();
        // The reader invents a Q99 and says nothing at all about Q3.
        $reply['responses'] = [
            $reply['responses'][0],
            $reply['responses'][1],
            ['question_no' => 99, 'attempted' => true, 'selected_options' => ['A'], 'answer_text' => 'x', 'page' => 1, 'box_2d' => null],
        ];

        $this->evaluate($sheetId, new FakeGeminiClient([$reply, $this->graderReply()]));

        $answers = $this->answers($sheetId);
        $this->assertSame([1, 2, 3], $answers->keys()->map(fn ($k) => (int) $k)->all(), 'only questions on the paper get a row');
        $this->assertSame('unattempted', $answers[3]->status);
        $this->assertEquals(0.0, $answers[3]->ai_marks);
    }

    public function test_a_written_answer_the_grader_skipped_is_flagged_not_silently_zeroed(): void
    {
        $sheetId = $this->pendingSheet();
        // The grader answers with a valid payload that has no entry for Q2.
        $this->evaluate($sheetId, new FakeGeminiClient([$this->readerReply(), ['results' => [['question_no' => 77, 'marks' => 1, 'confidence' => 90, 'remark' => 'n/a']]]]));

        $q2 = $this->answers($sheetId)[2];
        $this->assertEquals(0.0, $q2->ai_confidence);
        $this->assertStringContainsString('did not return a mark', $q2->ai_remark);
    }

    public function test_the_job_waits_out_a_busy_provider_and_runs_one_at_a_time_per_sheet(): void
    {
        $job = new EvaluateAnswerSheetJob(5);

        $this->assertSame(4, $job->tries);
        $this->assertSame([60, 180, 420], $job->backoff());
        $this->assertInstanceOf(WithoutOverlapping::class, $job->middleware()[0]);
    }
}
