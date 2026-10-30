<?php

namespace Tests\Feature\QuestionPapers;

use App\Services\QuestionPapers\PdfFileCheck;
use App\Services\QuestionPapers\QuestionPaperItemNormaliser;
use App\Services\QuestionPapers\QuestionPaperJobService;
use App\Services\QuestionPapers\QuestionPaperPersistence;
use Illuminate\Support\Facades\DB;

class QuestionPaperPipelineTest extends QuestionPaperTestCase
{
    private const HEADER = ['standard' => 'Class 9', 'subject' => 'Science', 'chapter' => 'Matter in Our Surroundings', 'academic_year' => '2026-27'];

    private function paperResponse(): array
    {
        return ['header' => self::HEADER, 'questions' => [
            $this->mcq('Which state of matter has a definite shape?', 'A'),
            $this->mcq('Which state of matter has no fixed volume?', null),
            ['item_number' => '3', 'page' => 1, 'format' => 'short', 'question_text' => 'Define evaporation.', 'answer_text' => 'Change of liquid to vapour below its boiling point.', 'marks' => 2],
        ]];
    }

    /* ---------- the pipeline ---------- */

    public function test_a_paper_is_extracted_and_saved_with_real_counters_and_one_notification(): void
    {
        $this->fakeParser([$this->paperResponse()]);
        $job = $this->makeJob();

        $this->process($job);

        $row = $this->job($job);
        $this->assertSame('completed_with_warnings', $row->status); // one MCQ has no printed key
        $this->assertSame(3, (int) $row->questions_extracted);
        $this->assertSame(3, (int) $row->questions_saved);
        $this->assertSame(1, (int) $row->questions_needing_review);
        $this->assertSame(1, (int) $row->total_pages);
        $this->assertSame(1, (int) $row->processed_pages);
        $this->assertNull($row->lease_token, 'the lease is released when the run ends');

        // Academic context came from the heading, by exact match inside this school.
        $this->assertSame([9, 3, 101], [(int) $row->standard_id, (int) $row->subject_id, (int) $row->chapter_id]);

        $keyed = DB::table('lms_question_master')->where('question_title', 'like', '%definite shape%')->first();
        $this->assertSame(1, (int) $keyed->status);
        $this->assertSame(self::TENANT, (int) $keyed->sub_institute_id);
        $this->assertSame('Solid', DB::table('answer_master')->where('question_id', $keyed->id)->where('correct_answer', 1)->value('answer'));
        $this->assertSame(4, DB::table('answer_master')->where('question_id', $keyed->id)->count());
        $envelope = json_decode($keyed->answer, true);
        $this->assertSame('question_paper_upload', $envelope['source']['type']);
        $this->assertSame($job->uuid, $envelope['source']['job']);

        // No key printed -> stored inactive and with NO answer rows: a correct option is never invented.
        $unkeyed = DB::table('lms_question_master')->where('question_title', 'like', '%no fixed volume%')->first();
        $this->assertSame(0, (int) $unkeyed->status);
        $this->assertSame(0, DB::table('answer_master')->where('question_id', $unkeyed->id)->count());
        $this->assertNull(json_decode($unkeyed->answer, true)['correct_option']);

        $short = DB::table('lms_question_master')->where('question_title', 'Define evaporation.')->first();
        $this->assertSame(1, (int) $short->status);
        $this->assertSame('short', $short->question_format_code);
        $this->assertSame(2, (int) $short->points);

        $this->assertSame(1, DB::table('user_notifications')->count());
        $note = DB::table('user_notifications')->first();
        $this->assertSame([self::TENANT, self::OWNER, 'warning'], [(int) $note->sub_institute_id, (int) $note->user_id, $note->severity]);
        $this->assertStringContainsString('/documents_new', $note->link);
        $this->assertNull($note->read_at);
    }

    public function test_a_clean_paper_completes_without_warnings(): void
    {
        $this->fakeParser([['header' => self::HEADER, 'questions' => [$this->mcq('Which state of matter has a definite shape?', 'A')]]]);
        $job = $this->makeJob();

        $this->process($job);

        $this->assertSame('completed', $this->job($job)->status);
        $this->assertSame('success', DB::table('user_notifications')->value('severity'));
    }

    public function test_running_the_job_again_cannot_duplicate_questions_answers_or_notifications(): void
    {
        $this->fakeParser([$this->paperResponse(), $this->paperResponse()]);
        $job = $this->makeJob();
        $this->process($job);
        $questions = DB::table('lms_question_master')->count();
        $answers = DB::table('answer_master')->count();

        // Force a second full run: reopen the job, as a duplicate queue message or recovery would.
        DB::table('question_paper_jobs')->where('id', $job->id)->update(['status' => 'queued', 'parsed_chunks' => 0, 'total_chunks' => null]);
        $this->process($job);

        $this->assertSame($questions, DB::table('lms_question_master')->count());
        $this->assertSame($answers, DB::table('answer_master')->count());
        $this->assertSame(3, DB::table('question_paper_items')->where('job_id', $job->id)->count());
        $this->assertSame(1, DB::table('user_notifications')->count(), 'same outcome in the same run notifies once');
    }

    public function test_a_crash_midway_resumes_without_duplicates_and_never_reports_complete_early(): void
    {
        $parser = $this->fakeParser([$this->paperResponse()]);
        $job = $this->makeJob();

        // The 2nd write fails like a database outage.
        $real = app(QuestionPaperPersistence::class);
        $calls = 0;
        $this->app->instance(QuestionPaperPersistence::class, new class($real, $calls) extends QuestionPaperPersistence {
            public function __construct(private QuestionPaperPersistence $real, private int &$calls)
            {
            }

            public function save(object $job, int $itemId): string
            {
                if (++$this->calls === 2) {
                    throw new \RuntimeException('database went away');
                }

                return $this->real->save($job, $itemId);
            }
        });

        try {
            $this->process($job);
            $this->fail('the transient failure must be rethrown so the queue backs off');
        } catch (\RuntimeException $e) {
            $this->assertSame('database went away', $e->getMessage());
        }

        $row = $this->job($job);
        $this->assertSame('retrying', $row->status, 'never completed with a question unsaved');
        $this->assertSame(1, (int) $row->attempts);
        $this->assertSame('database went away', $row->last_error);
        $this->assertSame(1, DB::table('lms_question_master')->count());
        $this->assertSame(0, DB::table('user_notifications')->count());

        // Next attempt (new worker): AI is not called again, the saved question is not rewritten.
        $this->app->instance(QuestionPaperPersistence::class, $real);
        $this->process($job);

        $this->assertSame(3, DB::table('lms_question_master')->count());
        $this->assertSame('completed_with_warnings', $this->job($job)->status);
        $this->assertSame(2, (int) $this->job($job)->attempts);
        $this->assertSame(1, $parser->calls, 'parsing is not repeated on the resumed attempt');
    }

    public function test_a_question_already_in_the_bank_is_recorded_as_a_duplicate_not_inserted(): void
    {
        $this->fakeParser([$this->paperResponse(), $this->paperResponse()]);
        $first = $this->makeJob();
        $this->process($first);

        $second = $this->makeJob();
        $this->process($second);

        $this->assertSame(3, DB::table('lms_question_master')->count());
        $this->assertSame(3, DB::table('question_paper_items')->where('job_id', $second->id)->where('state', 'duplicate')->count());
        $this->assertSame(3, (int) $this->job($second)->questions_skipped);
        $this->assertSame(0, (int) $this->job($second)->questions_saved);
    }

    public function test_unresolved_standard_holds_questions_instead_of_guessing_then_a_retry_with_metadata_saves_them(): void
    {
        $header = ['standard' => 'Grade Nine', 'subject' => 'Science', 'chapter' => null, 'academic_year' => null];
        $this->fakeParser([['header' => $header, 'questions' => [$this->mcq('Which state of matter has a definite shape?', 'A')]]]);
        $job = $this->makeJob();

        $this->process($job);

        $row = $this->job($job);
        $this->assertSame('completed_with_warnings', $row->status);
        $this->assertNull($row->standard_id, 'a heading that matches no standard is not guessed');
        $this->assertSame(0, DB::table('lms_question_master')->count());
        $this->assertSame(1, DB::table('question_paper_items')->where('state', 'unresolved')->count());
        $this->assertNotEmpty(app(QuestionPaperJobService::class)->present($row)['metadata_notes']);

        $this->assertTrue(app(QuestionPaperJobService::class)->prepareManualRetry($row, ['standard_id' => 9, 'subject_id' => 3, 'chapter_id' => 101]));
        $this->process($job);

        $this->assertSame(1, DB::table('lms_question_master')->count());
        $final = $this->job($job);
        $this->assertSame('completed', $final->status);
        $this->assertSame(101, (int) DB::table('lms_question_master')->value('chapter_id'));
        // A new run may notify again; the first run's alert is kept.
        $this->assertSame(2, DB::table('user_notifications')->count());
    }

    public function test_a_paper_with_no_questions_fails_clearly_and_is_never_marked_complete(): void
    {
        $this->fakeParser([['header' => [], 'questions' => []]]);
        $job = $this->makeJob();

        $this->process($job);

        $row = $this->job($job);
        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('No questions were found', $row->last_error);
        $this->assertSame('error', DB::table('user_notifications')->value('severity'));
    }

    public function test_a_corrupt_file_fails_permanently_without_retry(): void
    {
        $parser = $this->fakeParser([]);
        $job = $this->makeJob(bytes: "%PDF-1.4\nthis was cut off");

        $this->process($job);

        $row = $this->job($job);
        $this->assertSame('failed', $row->status);
        $this->assertSame(0, (int) $row->retryable);
        $this->assertSame(0, $parser->calls, 'the AI is never called for a file that is not a PDF');
        $this->assertFalse(app(QuestionPaperJobService::class)->present($row)['can_retry']);
    }

    public function test_transient_ai_failure_backs_off_then_fails_after_the_attempt_budget_and_can_be_retried(): void
    {
        $this->fakeParser(array_fill(0, 6, new \RuntimeException('provider timeout')));
        $job = $this->makeJob();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $this->process($job);
                $this->fail('expected the transient error to be rethrown');
            } catch (\RuntimeException) {
            }
            $this->assertSame('retrying', $this->job($job)->status);
            $this->assertSame($attempt, (int) $this->job($job)->attempts);
        }

        $this->process($job); // 4th attempt exhausts the budget: no rethrow, permanent failure recorded
        $row = $this->job($job);
        $this->assertSame('failed', $row->status);
        $this->assertSame(1, (int) $row->retryable, 'the owner may still retry by hand');
        $this->assertStringContainsString('provider timeout', $row->last_error);
        $this->assertCount(4, json_decode($row->error_log, true));

        $this->assertTrue(app(QuestionPaperJobService::class)->prepareManualRetry($row, []));
        $this->assertSame(0, (int) $this->job($job)->attempts);
    }

    public function test_an_unconfigured_ai_provider_is_a_clear_failure_the_owner_can_retry_after_fixing(): void
    {
        // Real parser, no provider configured (GEMINI_API_KEY blank, nothing saved for the tenant).
        $this->app->forgetInstance(\App\Services\QuestionPapers\QuestionPaperParser::class);
        $this->app->bind(\App\Services\QuestionPapers\QuestionPaperParser::class, fn () => new class extends \App\Services\QuestionPapers\QuestionPaperParser {
            public function parseChunk(array $pages, int $tenant): array
            {
                throw new \App\Services\QuestionPapers\PermanentQuestionPaperException('No AI provider is configured for Document Intelligence.', true);
            }
        });
        $job = $this->makeJob();

        $this->process($job);

        $row = $this->job($job);
        $this->assertSame('failed', $row->status);
        $this->assertTrue(app(QuestionPaperJobService::class)->present($row)['can_retry']);
    }

    /* ---------- single worker, recovery ---------- */

    public function test_only_one_worker_can_hold_a_job_and_an_expired_lease_can_be_taken_over(): void
    {
        $job = $this->makeJob();
        $jobs = app(QuestionPaperJobService::class);

        $first = $jobs->acquireLease((int) $job->id);
        $this->assertNotNull($first);
        $this->assertNull($jobs->acquireLease((int) $job->id), 'a second worker is refused while the lease is live');

        DB::table('question_paper_jobs')->where('id', $job->id)->update(['lease_expires_at' => now()->subMinute()]);
        $second = $jobs->acquireLease((int) $job->id);
        $this->assertNotNull($second);

        $this->expectException(\App\Services\QuestionPapers\LeaseLostException::class);
        $jobs->update((int) $job->id, $first, ['stage' => 'saving']); // the stalled worker may not write
    }

    public function test_a_worker_that_loses_its_lease_stops_without_touching_the_row(): void
    {
        $parser = $this->fakeParser([$this->paperResponse()]);
        $job = $this->makeJob();
        DB::table('question_paper_jobs')->where('id', $job->id)->update(['lease_token' => 'another-worker', 'lease_expires_at' => now()->addMinutes(10)]);

        $this->process($job);

        $this->assertSame(0, $parser->calls);
        $this->assertSame(0, DB::table('lms_question_master')->count());
        $this->assertSame('another-worker', $this->job($job)->lease_token);
    }

    public function test_a_finished_job_is_not_run_again(): void
    {
        $parser = $this->fakeParser([$this->paperResponse(), $this->paperResponse()]);
        $job = $this->makeJob();
        $this->process($job);
        $this->process($job);

        $this->assertSame(1, $parser->calls);
    }

    /* ---------- validation helpers ---------- */

    public function test_pdf_check_looks_at_the_bytes(): void
    {
        $good = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($good, TinyPdf::make(['hello world hello world hello world hello world hello']));
        $fake = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($fake, str_repeat('MZ not a pdf ', 20));
        $truncated = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($truncated, '%PDF-1.4 ' . str_repeat('x', 200));

        $this->assertNull(PdfFileCheck::problem($good));
        $this->assertStringContainsString('not a PDF', PdfFileCheck::problem($fake));
        $this->assertStringContainsString('truncated', PdfFileCheck::problem($truncated));
        array_map('unlink', [$good, $fake, $truncated]);
    }

    public function test_normaliser_keeps_formats_and_never_invents_answers(): void
    {
        $n = new QuestionPaperItemNormaliser();

        $tf = $n->normalise(['format' => 'true_false', 'question_text' => 'Water boils at 100 C at sea level.', 'answer_text' => 'True']);
        $this->assertTrue($tf['ok']);
        $this->assertSame('A', $tf['item']['correct_option']);
        $this->assertSame(['True', 'False'], array_column($tf['item']['options'], 'text'));

        $tfNoKey = $n->normalise(['format' => 'true_false', 'question_text' => 'Water boils at 100 C at sea level.']);
        $this->assertNull($tfNoKey['item']['correct_option']);
        $this->assertNotEmpty($tfNoKey['item']['review']);

        $badKey = $n->normalise($this->mcq('Pick one of the following options', 'Z'));
        $this->assertNull($badKey['item']['correct_option'], 'a key that is not one of the options is dropped, not mapped');

        $this->assertFalse($n->normalise(['format' => 'mcq', 'question_text' => 'Only one option here', 'options' => [['label' => 'A', 'text' => 'x']]])['ok']);
        $this->assertFalse($n->normalise(['format' => 'telepathy', 'question_text' => 'Not a real format at all'])['ok']);
        $this->assertFalse($n->normalise(['format' => 'short', 'question_text' => 'Hi'])['ok']);

        $match = $n->normalise(['format' => 'match_following', 'question_text' => 'Match the columns below.', 'pairs' => [['left' => 'Ice', 'right' => 'Solid'], ['left' => 'Steam', 'right' => 'Gas']]]);
        $this->assertTrue($match['ok']);
        $this->assertCount(2, $match['item']['pairs']);

        $a = $n->normalise($this->mcq('Same words here, same options', 'A'))['item']['content_hash'];
        $b = $n->normalise($this->mcq('  same WORDS here,  same options ', 'B'))['item']['content_hash'];
        $this->assertSame($a, $b, 'spacing/case do not make a different question');
    }
}
