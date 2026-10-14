<?php

namespace Tests\Feature\ExamEvaluation;

use App\Jobs\IdentifyAnswerSheetJob;
use App\Services\Evaluation\ExamEvaluationStorage;
use App\Services\Homework\Exceptions\ProviderBusyException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Tests\Support\FakeGeminiClient;

/**
 * Stage one: an uploaded scan is read for WHOSE it is, matched against the
 * students eligible for the exam, and then left alone until a teacher decides.
 * The model is scripted; nothing leaves the process.
 */
class IdentifyAnswerSheetJobTest extends ExamEvaluationTestCase
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

    // -- Step B/C: read the identity and suggest a student -------------------

    public function test_it_reads_only_the_identity_suggests_a_student_and_waits_for_a_teacher(): void
    {
        $sheetId = $this->identifyingSheet($this->batchId);
        $gemini = new FakeGeminiClient([$this->identityReply()]);

        $this->identify($sheetId, $gemini);

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame('Awaiting identity', $sheet->status);
        $this->assertSame(101, (int) $sheet->student_id);
        $this->assertSame('enrollment_no', $sheet->identity_source);
        $this->assertGreaterThanOrEqual(90.0, (float) $sheet->identity_confidence);
        $this->assertNull($sheet->identity_confirmed_at, 'the AI suggests; only a teacher confirms');
        $this->assertNull($sheet->identity_confirmed_by);
        $this->assertSame(['GR101', '1', 'Asha Patel'], [$sheet->detected_enrollment_no, $sheet->detected_roll_no, $sheet->detected_student_name]);

        // No marking has happened or been paid for.
        $this->assertCount(1, $gemini->calls, 'one cheap read, not a full evaluation');
        $this->assertSame(0, DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->count());
        $this->assertNull($sheet->ai_total);
        $this->assertNull($sheet->annotated_file_name);
    }

    public function test_the_identity_read_sends_the_whole_file_and_asks_for_the_details_wherever_they_are(): void
    {
        $sheetId = $this->identifyingSheet($this->batchId);
        $gemini = new FakeGeminiClient([$this->identityReply()]);

        $this->identify($sheetId, $gemini);

        $prompt = $gemini->promptText(0);

        // The details are not assumed to be on page one: every page is inspected.
        $this->assertStringContainsString('Look through EVERY page', $prompt);
        $this->assertStringContainsString('cover sheet', $prompt);
        // Whole file, original bytes.
        $this->assertSame(base64_encode(file_get_contents($this->scanPath)), $gemini->calls[0][1]['inline_data']['data']);
        // Identity from what is written, never from the handwriting.
        $this->assertStringContainsString('Never work out who the student is from their handwriting', $prompt);
        // It is not shown the questions or the answer key: that is the next stage's business.
        $this->assertStringNotContainsString('Which gas do plants absorb', $prompt);
        $this->assertStringNotContainsString('Q1', $prompt);
        $this->assertSame(600, $gemini->configs[0]['maxOutputTokens']);
    }

    public function test_where_in_the_file_the_details_were_found_is_recorded_for_the_teacher(): void
    {
        $sheetId = $this->identifyingSheet($this->batchId);

        $this->identify($sheetId, new FakeGeminiClient([$this->identityReply(['page' => 3])]));

        $this->assertSame(3, $this->identityBlock($sheetId)['page']);
    }

    public function test_a_roll_number_alone_is_enough_when_it_is_the_only_thing_readable(): void
    {
        $sheetId = $this->identifyingSheet($this->batchId);

        $this->identify($sheetId, new FakeGeminiClient([$this->identityReply(['enrollment_no' => '', 'roll_no' => '02', 'name' => ''])]));

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame(102, (int) $sheet->student_id);
        $this->assertSame('roll_no', $sheet->identity_source);
    }

    public function test_an_unambiguous_name_is_a_last_resort_labelled_as_the_weakest_match(): void
    {
        $sheetId = $this->identifyingSheet($this->batchId);

        $this->identify($sheetId, new FakeGeminiClient([$this->identityReply(['enrollment_no' => '', 'roll_no' => '', 'name' => 'Patel Asha', 'confidence' => 85])]));

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame(101, (int) $sheet->student_id);
        $this->assertSame('name', $sheet->identity_source);
        $this->assertLessThanOrEqual(80.0, (float) $sheet->identity_confidence);
    }

    public function test_an_ambiguous_name_is_not_assigned_and_lists_who_it_could_be(): void
    {
        $this->addSecondRaviShah();
        $sheetId = $this->identifyingSheet($this->batchId);

        $this->identify($sheetId, new FakeGeminiClient([$this->identityReply(['enrollment_no' => '', 'roll_no' => '', 'name' => 'Ravi Shah'])]));

        $sheet = $this->sheetRow($sheetId);
        $this->assertNull($sheet->student_id);
        $this->assertSame('unmatched', $sheet->identity_source);
        $this->assertSame('Awaiting identity', $sheet->status, 'a teacher resolves it; it is not failed');
        $block = $this->identityBlock($sheetId);
        $this->assertSame('ambiguous', $block['issue']);
        $this->assertEqualsCanonicalizing([102, 103], $block['candidates']);
    }

    public function test_identifiers_that_name_different_students_are_flagged_not_resolved(): void
    {
        $sheetId = $this->identifyingSheet($this->batchId);

        // GR101 is Asha; roll 2 is Ravi.
        $this->identify($sheetId, new FakeGeminiClient([$this->identityReply(['enrollment_no' => 'GR101', 'roll_no' => '2'])]));

        $sheet = $this->sheetRow($sheetId);
        $this->assertNull($sheet->student_id);
        $block = $this->identityBlock($sheetId);
        $this->assertSame('conflict', $block['issue']);
        $this->assertEqualsCanonicalizing([101, 102], $block['candidates']);
    }

    public function test_a_blank_identity_block_waits_for_a_teacher_rather_than_failing_the_sheet(): void
    {
        $sheetId = $this->identifyingSheet($this->batchId);

        $this->identify($sheetId, new FakeGeminiClient([$this->identityReply(['enrollment_no' => '', 'roll_no' => '', 'name' => '', 'confidence' => 0, 'page' => 0])]));

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame('Awaiting identity', $sheet->status);
        $this->assertNull($sheet->student_id);
        $this->assertSame('unreadable', $this->identityBlock($sheetId)['issue']);
        $this->assertNull($sheet->failure_reason);
    }

    public function test_a_reply_that_cannot_be_parsed_still_leaves_the_sheet_for_a_teacher(): void
    {
        $sheetId = $this->identifyingSheet($this->batchId);
        $gemini = new FakeGeminiClient(['not json at all', 'still { not json']);

        $this->identify($sheetId, $gemini);

        $this->assertCount(2, $gemini->calls, 'one retry, no more');
        $this->assertGreaterThan($gemini->configs[0]['temperature'], $gemini->configs[1]['temperature']);
        $sheet = $this->sheetRow($sheetId);
        $this->assertSame('Awaiting identity', $sheet->status);
        $this->assertNull($sheet->student_id);
        $this->assertSame('unreadable', $this->identityBlock($sheetId)['issue']);
    }

    public function test_a_file_carrying_two_students_details_is_never_filed_under_either(): void
    {
        $sheetId = $this->identifyingSheet($this->batchId);

        $this->identify($sheetId, new FakeGeminiClient([$this->identityReply(['multiple_students' => true])]));

        $sheet = $this->sheetRow($sheetId);
        $this->assertNull($sheet->student_id, 'the numbers match Asha, but the file says it holds more than one student');
        $this->assertSame('multiple_students', $this->identityBlock($sheetId)['issue']);
    }

    public function test_a_student_who_is_not_eligible_for_this_exam_is_never_suggested(): void
    {
        $this->addClassEightStudent();
        $this->addDroppedStudent();
        $classEight = $this->identifyingSheet($this->batchId);
        $dropped = $this->identifyingSheet($this->batchId);

        // Both are real students at this school; neither is on the Class 7 list.
        $this->identify($classEight, new FakeGeminiClient([$this->identityReply(['enrollment_no' => 'GR104', 'roll_no' => '', 'name' => 'Neha Desai'])]));
        $this->identify($dropped, new FakeGeminiClient([$this->identityReply(['enrollment_no' => 'GR105', 'roll_no' => '', 'name' => 'Kabir Nair'])]));

        foreach ([$classEight, $dropped] as $sheetId) {
            $this->assertNull($this->sheetRow($sheetId)->student_id);
            $this->assertSame('not_on_roll', $this->identityBlock($sheetId)['issue']);
        }
    }

    // -- one sheet per student ----------------------------------------------

    public function test_a_second_scan_of_the_same_student_is_withdrawn_and_says_which_sheet_has_them(): void
    {
        $first = $this->awaitingSheet($this->batchId, ['student_id' => 101]);
        $second = $this->identifyingSheet($this->batchId);

        $this->identify($second, new FakeGeminiClient([$this->identityReply()]));

        $row = $this->sheetRow($second);
        $this->assertNull($row->student_id);
        $block = $this->identityBlock($second);
        $this->assertSame('duplicate', $block['issue']);
        $this->assertSame($first, $block['duplicate_of_sheet_id']);
        $this->assertSame([101], $block['candidates']);
        $this->assertSame(101, (int) $this->sheetRow($first)->student_id, 'the first sheet keeps its student');
    }

    // -- stale and repeated jobs --------------------------------------------

    public function test_a_job_for_a_sheet_that_is_no_longer_waiting_for_identification_changes_nothing(): void
    {
        $sheetId = $this->identifyingSheet($this->batchId, [
            'status' => 'Pending', 'student_id' => 102, 'identity_source' => 'manual', 'identity_confirmed_at' => now(),
        ]);
        $gemini = new FakeGeminiClient([]);

        $this->identify($sheetId, $gemini);

        $this->assertSame([], $gemini->calls, 'no model call for a sheet a teacher has already dealt with');
        $sheet = $this->sheetRow($sheetId);
        $this->assertSame('Pending', $sheet->status);
        $this->assertSame(102, (int) $sheet->student_id);
    }

    public function test_a_teacher_who_confirms_while_the_read_is_in_flight_is_not_overwritten(): void
    {
        $sheetId = $this->identifyingSheet($this->batchId);

        // The model is about to say "Asha" -- but while it was thinking, the teacher picked Ravi.
        $matcher = $this->matcherThat(function () use ($sheetId) {
            DB::table('exam_evaluation_sheet')->where('id', $sheetId)->update([
                'status' => 'Pending', 'student_id' => 102, 'identity_source' => 'manual',
                'identity_confidence' => 100, 'identity_confirmed_at' => now(), 'identity_confirmed_by' => self::TEACHER,
            ]);
        });

        $this->identify($sheetId, new FakeGeminiClient([$this->identityReply()]), $matcher);

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame(102, (int) $sheet->student_id, 'the late read must not put a different child on the sheet');
        $this->assertSame('manual', $sheet->identity_source);
        $this->assertSame('Pending', $sheet->status);
        $this->assertNotNull($sheet->identity_confirmed_at);
    }

    // -- failures -----------------------------------------------------------

    public function test_a_busy_provider_is_raised_for_retry_and_the_sheet_keeps_waiting(): void
    {
        $sheetId = $this->identifyingSheet($this->batchId);

        try {
            $this->identify($sheetId, new FakeGeminiClient([new ProviderBusyException('busy')]));
            $this->fail('the transient failure should have been raised to the queue');
        } catch (ProviderBusyException) {
            // expected: the queue retries after backoff()
        }

        $this->assertSame('Identifying', $this->sheetRow($sheetId)->status);
    }

    public function test_when_every_retry_is_spent_the_teacher_can_still_pick_the_student(): void
    {
        $sheetId = $this->identifyingSheet($this->batchId);

        (new IdentifyAnswerSheetJob($sheetId))->failed(new \RuntimeException('provider gave up'));

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame('Awaiting identity', $sheet->status, 'not Failed: choosing the student by hand still works');
        $this->assertNull($sheet->student_id);
        $this->assertSame('unavailable', $this->identityBlock($sheetId)['issue']);
    }

    public function test_giving_up_never_disturbs_a_sheet_that_has_moved_on(): void
    {
        $evaluating = $this->identifyingSheet($this->batchId, ['status' => 'Processing', 'student_id' => 102, 'identity_source' => 'manual', 'identity_confirmed_at' => now()]);

        (new IdentifyAnswerSheetJob($evaluating))->failed(new \RuntimeException('late'));

        $sheet = $this->sheetRow($evaluating);
        $this->assertSame('Processing', $sheet->status);
        $this->assertSame(102, (int) $sheet->student_id);
    }

    public function test_a_scan_that_has_gone_missing_fails_the_sheet_with_a_reason(): void
    {
        $sheetId = $this->identifyingSheet($this->batchId);
        $missing = new class extends ExamEvaluationStorage {
            public function sheetPath(string $fileName): ?string
            {
                return null;
            }
        };

        $this->identify($sheetId, new FakeGeminiClient([]), null, $missing);

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame('Failed', $sheet->status);
        $this->assertStringContainsString('could not be found', $sheet->failure_reason);
    }

    public function test_it_waits_out_a_busy_provider_and_runs_once_at_a_time_per_sheet(): void
    {
        $job = new IdentifyAnswerSheetJob(5);

        $this->assertSame(4, $job->tries);
        $this->assertSame([30, 120, 300], $job->backoff());
        $this->assertInstanceOf(WithoutOverlapping::class, $job->middleware()[0]);
    }

    // -- privacy ------------------------------------------------------------

    public function test_a_childs_details_never_reach_the_logs(): void
    {
        $this->addSecondRaviShah();
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
            $logged[] = $event->message . ' ' . json_encode($event->context);
        });

        $first = $this->awaitingSheet($this->batchId, ['student_id' => 101]);
        $duplicate = $this->identifyingSheet($this->batchId);
        $unreadable = $this->identifyingSheet($this->batchId);
        $this->identify($duplicate, new FakeGeminiClient([$this->identityReply()]));
        $this->identify($unreadable, new FakeGeminiClient(['{ broken', '{ broken']));

        $this->assertNotEmpty($logged, 'the scenarios above do log something');

        foreach ($logged as $line) {
            foreach (['Asha', 'Patel', 'GR101', 'Ravi', 'Shah'] as $private) {
                $this->assertStringNotContainsString($private, $line, 'a name or a number from the sheet reached the logs');
            }
        }

        $this->assertSame(101, (int) $this->sheetRow($first)->student_id);
    }
}
