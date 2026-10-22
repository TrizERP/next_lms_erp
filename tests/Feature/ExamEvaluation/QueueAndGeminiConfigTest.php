<?php

namespace Tests\Feature\ExamEvaluation;

use App\Jobs\EvaluateAnswerSheetJob;
use App\Jobs\IdentifyAnswerSheetJob;
use App\Services\Evaluation\QueueTiming;
use App\Services\Homework\GeminiClient;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeGeminiClient;

/**
 * The deployment settings the answer-sheet jobs depend on, checked by loading the REAL
 * config/queue.php and config/ai.php under different environment values -- not a copy of
 * their logic -- and restoring the environment afterwards. Nothing here reads or writes the
 * shared .env, and no queue or model is contacted.
 */
class QueueAndGeminiConfigTest extends ExamEvaluationTestCase
{
    use IdentityTestHelpers;

    private const KEYS = ['QUEUE_CONNECTION', 'QUEUE_RETRY_AFTER', 'GEMINI_DOCUMENT_TIMEOUT', 'GEMINI_REQUEST_TIMEOUT', 'GEMINI_FALLBACK_MODEL', 'GEMINI_MODEL'];

    /** @var array<string,array{0:string|false,1:mixed,2:mixed}> */
    private array $savedEnv = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpScan();

        foreach (self::KEYS as $key) {
            $this->savedEnv[$key] = [getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null];
            $this->putEnv($key, null);
        }

        QueueTiming::forgetWarnings();
    }

    protected function tearDown(): void
    {
        foreach ($this->savedEnv as $key => [$process, $env, $server]) {
            $process === false ? putenv($key) : putenv("{$key}={$process}");
            $env === null ? $this->forget($_ENV, $key) : $_ENV[$key] = $env;
            $server === null ? $this->forget($_SERVER, $key) : $_SERVER[$key] = $server;
        }

        QueueTiming::forgetWarnings();
        $this->tearDownScan();
        parent::tearDown();
    }

    private function forget(array &$bag, string $key): void
    {
        unset($bag[$key]);
    }

    /** Sets (or, with null, removes) one variable everywhere Laravel's env() looks. */
    private function putEnv(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv("{$key}={$value}");
        $_ENV[$key] = $_SERVER[$key] = $value;
    }

    /**
     * @param  array<string,string|null>  $env
     * @return array<string,mixed>
     */
    private function config(string $file, array $env = []): array
    {
        foreach ($env as $key => $value) {
            $this->putEnv($key, $value);
        }

        return require config_path($file);
    }

    /** @return array<string,int|null> */
    private function leases(array $queue): array
    {
        return array_map(
            static fn (string $name) => $queue['connections'][$name]['retry_after'] ?? null,
            ['database' => 'database', 'redis' => 'redis', 'beanstalkd' => 'beanstalkd', 'sqs' => 'sqs']
        );
    }

    // -- QUEUE_RETRY_AFTER reaches every driver that has the setting ---------------

    public function test_with_nothing_set_every_driver_keeps_the_old_lease_of_ninety_seconds(): void
    {
        $this->assertSame(['database' => 90, 'redis' => 90, 'beanstalkd' => 90, 'sqs' => null], $this->leases($this->config('queue.php')));
    }

    public function test_the_setting_reaches_redis_and_beanstalkd_not_just_the_database_queue(): void
    {
        // The reported fault: QUEUE_CONNECTION=redis with QUEUE_RETRY_AFTER=660 left redis at 90.
        $leases = $this->leases($this->config('queue.php', ['QUEUE_RETRY_AFTER' => '660']));

        $this->assertSame(['database' => 660, 'redis' => 660, 'beanstalkd' => 660, 'sqs' => null], $leases);
    }

    /** @return array<string,array{0:string}> */
    public static function valuesThatAreNotALease(): array
    {
        return ['zero' => ['0'], 'negative' => ['-5'], 'a typo' => ['abc'], 'empty' => ['']];
    }

    #[DataProvider('valuesThatAreNotALease')]
    public function test_a_value_that_is_not_a_positive_number_falls_back_to_the_default_not_to_an_instant_redelivery(string $value): void
    {
        $leases = $this->leases($this->config('queue.php', ['QUEUE_RETRY_AFTER' => $value]));

        $this->assertSame(90, $leases['database']);
        $this->assertSame(90, $leases['redis']);
        $this->assertSame(90, $leases['beanstalkd']);
    }

    public function test_the_default_connection_is_still_chosen_by_queue_connection(): void
    {
        $this->assertSame('sync', $this->config('queue.php')['default']);
        $this->assertSame('redis', $this->config('queue.php', ['QUEUE_CONNECTION' => 'redis'])['default']);
    }

    // -- the lease against the job timeouts ---------------------------------------

    public function test_the_documented_lease_is_longer_than_every_answer_sheet_job_timeout(): void
    {
        $lease = $this->leases($this->config('queue.php', ['QUEUE_RETRY_AFTER' => '660']))['redis'];

        foreach ([new EvaluateAnswerSheetJob(1), new IdentifyAnswerSheetJob(1)] as $job) {
            $this->assertGreaterThan($job->timeout, $lease, get_class($job) . ' must finish before its lease runs out');
        }

        // And the overlap lock is held for the same margin, so a redelivered copy meets it.
        $this->assertSame(600 + 60, (new EvaluateAnswerSheetJob(1))->middleware()[0]->expiresAfter);
        $this->assertSame(300 + 60, (new IdentifyAnswerSheetJob(1))->middleware()[0]->expiresAfter);
    }

    public function test_the_old_lease_is_shorter_than_the_evaluation_timeout_which_is_why_the_setting_exists(): void
    {
        $this->assertLessThan((new EvaluateAnswerSheetJob(1))->timeout, $this->leases($this->config('queue.php'))['database']);
    }

    // -- QueueTiming says so, once, in the log ------------------------------------

    /** @return array<int,array{0:string,1:array<string,mixed>}> */
    private function captureWarnings(\Closure $work): array
    {
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
            if ($event->level === 'warning' && str_contains($event->message, 'retry_after')) {
                $logged[] = [$event->message, $event->context];
            }
        });

        $work();

        return $logged;
    }

    public function test_a_lease_not_longer_than_the_job_timeout_is_reported_once_per_process(): void
    {
        config(['queue.default' => 'redis', 'queue.connections.redis.retry_after' => 90]);

        $warnings = $this->captureWarnings(function () {
            $this->assertTrue(QueueTiming::warnIfLeaseIsShorterThanTimeout(EvaluateAnswerSheetJob::class, 600, null));
            $this->assertFalse(QueueTiming::warnIfLeaseIsShorterThanTimeout(EvaluateAnswerSheetJob::class, 600, null), 'once, not on every job');
            $this->assertTrue(QueueTiming::warnIfLeaseIsShorterThanTimeout(IdentifyAnswerSheetJob::class, 300, 'redis'), 'each job class has its own');
        });

        $this->assertCount(2, $warnings);
        $this->assertSame(90, $warnings[0][1]['retry_after_seconds']);
        $this->assertSame(600, $warnings[0][1]['job_timeout_seconds']);
        $this->assertSame('QUEUE_RETRY_AFTER >= 660', $warnings[0][1]['set']);
        $this->assertSame('redis', $warnings[0][1]['connection']);
    }

    public function test_the_warning_names_no_student_school_or_sheet(): void
    {
        config(['queue.default' => 'database', 'queue.connections.database.retry_after' => 90]);

        $warnings = $this->captureWarnings(fn () => QueueTiming::warnIfLeaseIsShorterThanTimeout(EvaluateAnswerSheetJob::class, 600, 'database'));

        $this->assertSame(['job', 'job_timeout_seconds', 'retry_after_seconds', 'connection', 'set'], array_keys($warnings[0][1]));
    }

    public function test_a_long_enough_lease_and_drivers_without_one_say_nothing(): void
    {
        $warnings = $this->captureWarnings(function () {
            config(['queue.connections.redis.retry_after' => 660]);
            $this->assertFalse(QueueTiming::warnIfLeaseIsShorterThanTimeout(EvaluateAnswerSheetJob::class, 600, 'redis'));
            $this->assertFalse(QueueTiming::warnIfLeaseIsShorterThanTimeout(EvaluateAnswerSheetJob::class, 600, 'sync'), 'sync has no lease');
            $this->assertFalse(QueueTiming::warnIfLeaseIsShorterThanTimeout(EvaluateAnswerSheetJob::class, 600, 'sqs'), 'sqs keeps its own visibility timeout');
            $this->assertFalse(QueueTiming::warnIfLeaseIsShorterThanTimeout(EvaluateAnswerSheetJob::class, 600, 'no-such-connection'));
        });

        $this->assertSame([], $warnings);
    }

    public function test_a_lease_equal_to_the_timeout_is_still_too_short(): void
    {
        config(['queue.connections.redis.retry_after' => 600]);

        $this->assertTrue(QueueTiming::warnIfLeaseIsShorterThanTimeout(EvaluateAnswerSheetJob::class, 600, 'redis'));
    }

    public function test_both_jobs_check_the_lease_they_are_actually_running_under(): void
    {
        config(['queue.default' => 'redis', 'queue.connections.redis.retry_after' => 90]);

        // A sheet that does not exist: the jobs return at once, after the check.
        $warnings = $this->captureWarnings(function () {
            $this->evaluate(999999, new FakeGeminiClient([]));
            $this->identify(999999, new FakeGeminiClient([]));
        });

        $this->assertSame(
            [EvaluateAnswerSheetJob::class, IdentifyAnswerSheetJob::class],
            array_column(array_column($warnings, 1), 'job')
        );
    }

    // -- GEMINI_REQUEST_TIMEOUT and the document client -----------------------------

    public function test_documents_keep_their_ninety_seconds_and_the_brain_its_forty_five(): void
    {
        $gemini = $this->config('ai.php')['provider']['gemini'];

        $this->assertSame(90, $gemini['document_timeout']);
        $this->assertSame(45, $gemini['timeout']);
    }

    public function test_setting_the_request_timeout_to_forty_five_also_shortens_documents_as_it_always_did(): void
    {
        // The client read GEMINI_REQUEST_TIMEOUT directly before; reading it through config keeps that.
        $gemini = $this->config('ai.php', ['GEMINI_REQUEST_TIMEOUT' => '45'])['provider']['gemini'];

        $this->assertSame(45, $gemini['timeout']);
        $this->assertSame(45, $gemini['document_timeout']);
    }

    public function test_a_document_timeout_of_its_own_keeps_documents_at_ninety_whatever_the_brain_uses(): void
    {
        $gemini = $this->config('ai.php', ['GEMINI_REQUEST_TIMEOUT' => '45', 'GEMINI_DOCUMENT_TIMEOUT' => '90'])['provider']['gemini'];

        $this->assertSame(45, $gemini['timeout']);
        $this->assertSame(90, $gemini['document_timeout']);

        $this->assertSame(150, $this->config('ai.php', ['GEMINI_DOCUMENT_TIMEOUT' => '150'])['provider']['gemini']['document_timeout']);
    }

    #[DataProvider('valuesThatAreNotALease')]
    public function test_a_document_timeout_that_is_not_a_positive_number_falls_back_to_ninety(string $value): void
    {
        $gemini = $this->config('ai.php', ['GEMINI_DOCUMENT_TIMEOUT' => $value])['provider']['gemini'];

        $this->assertSame(90, $gemini['document_timeout']);
    }

    public function test_the_client_reads_the_timeout_from_config_so_a_cached_config_is_honoured(): void
    {
        $method = new \ReflectionMethod(GeminiClient::class, 'requestTimeout');

        config(['ai.provider.gemini.document_timeout' => 33]);

        $this->assertSame(33, $method->invoke(new GeminiClient()));
    }

    // -- the fallback model --------------------------------------------------------

    public function test_there_is_no_fallback_model_unless_one_is_named(): void
    {
        $this->assertSame('', $this->config('ai.php')['provider']['gemini']['fallback_model']);
        $this->assertSame('gemini-3.6-pro', $this->config('ai.php', ['GEMINI_FALLBACK_MODEL' => 'gemini-3.6-pro'])['provider']['gemini']['fallback_model']);
    }

    public function test_a_retired_fallback_id_resolves_to_the_main_model_so_it_is_treated_as_no_fallback(): void
    {
        $gemini = $this->config('ai.php', ['GEMINI_FALLBACK_MODEL' => 'gemini-2.5-flash'])['provider']['gemini'];

        $this->assertSame($gemini['model'], $gemini['fallback_model']);
        $this->assertSame('gemini-3.6-flash', $gemini['model']);

        config(['ai.provider.gemini.fallback_model' => $gemini['fallback_model'], 'gemini.model' => $gemini['model']]);
        $method = new \ReflectionMethod(GeminiClient::class, 'fallbackModel');
        $this->assertNull($method->invoke(new GeminiClient()), 'the client does not retry the model that just refused');
    }

    public function test_a_pinned_retired_main_model_is_still_mapped_forward(): void
    {
        // The local .env pins gemini-2.5-flash; nothing needs editing for that to keep working.
        $this->assertSame('gemini-3.6-flash', $this->config('ai.php', ['GEMINI_MODEL' => 'gemini-2.5-flash'])['provider']['gemini']['model']);
        $this->assertSame('gemini-3.6-pro', $this->config('ai.php', ['GEMINI_MODEL' => 'gemini-1.5-pro'])['provider']['gemini']['model']);
    }
}
