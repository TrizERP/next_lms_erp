<?php

namespace Tests\Feature;

use App\Domain\AI\Support\AiProviderException;
use App\Http\Controllers\api\lms\PrayogshalaApiController;
use App\Services\lms\Prayogshala\AiFailure;
use App\Services\lms\Prayogshala\PrayogshalaGenerator;
use App\Services\lms\Prayogshala\PrayogshalaService;
use App\Services\lms\Prayogshala\SimulationConfigValidator;
use App\Services\QuestionGeneration\H5p\ClaudeQuestionClient;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * How Prayogshala generation reacts to each KIND of AI failure.
 *
 * The real incident this covers: the configured Gemini key answered HTTP 429 "exceeded your current
 * quota" with the violated quota id `GenerateRequestsPerDayPerProjectPerModel-FreeTier`. That is a
 * spent daily quota, not "too many requests this minute", and the right reaction is to stop and say
 * what to configure, not to retry. These tests pin that distinction, the bounded backoff for the
 * failures that do clear by themselves, resumable batches, and that no provider text or secret is
 * ever stored or returned.
 *
 * In-memory SQLite, forced in setUp; the provider is scripted - no network, no key, no cost.
 */
class PrayogshalaProviderFailureTest extends TestCase
{
    private ScriptedClaude $ai;

    /** @var list<int> */
    private array $sleeps = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'queue.default' => 'sync',
            'prayogshala.max_provider_attempts' => 3,
            'prayogshala.retry_base_seconds' => 2,
            'prayogshala.max_retry_wait_seconds' => 60,
        ]);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'refusing to run against a real database');

        Schema::create('school_setup', function ($t) {
            $t->integer('Id')->primary();
            $t->string('is_Lms')->nullable();
        });
        Schema::create('standard', function ($t) {
            $t->integer('id')->primary();
            $t->string('name');
        });
        Schema::create('subject', function ($t) {
            $t->integer('id')->primary();
            $t->string('subject_name');
        });
        Schema::create('chapter_master', function ($t) {
            $t->integer('id')->primary();
            $t->integer('sub_institute_id');
            $t->integer('syear')->nullable();
            $t->integer('grade_id')->nullable();
            $t->integer('standard_id');
            $t->integer('subject_id');
            $t->string('chapter_name');
            $t->integer('sort_order')->default(0);
        });
        Schema::create('topic_master', function ($t) {
            $t->integer('id')->primary();
            $t->integer('chapter_id');
            $t->string('name');
            $t->text('description')->nullable();
            $t->integer('topic_sort_order')->default(0);
        });
        Schema::create('lms_concept', function ($t) {
            $t->integer('id')->primary();
            $t->integer('chapter_id');
            $t->integer('topic_id')->nullable();
            $t->string('name')->nullable();
            $t->text('definition')->nullable();
            $t->text('description')->nullable();
        });
        Schema::create('document_extractions', function ($t) {
            $t->integer('id')->primary();
            $t->integer('chapter_id');
            $t->string('document_type');
            $t->longText('md_content')->nullable();
        });
        foreach (['2026_10_08_100000_create_lms_prayogshala_activity_table', '2026_10_09_100000_add_generation_tracking_to_lms_prayogshala_activity_table', '2026_10_15_100000_add_generation_error_code_to_lms_prayogshala_activity_table'] as $m) {
            (require base_path("database/migrations/$m.php"))->up();
        }

        // An institute that owns its own Standard 7 Maths "Integers" chapter (not platform-linked),
        // plus an unrelated institute. Ids are arbitrary: nothing in the code names them.
        DB::table('school_setup')->insert([['Id' => 1, 'is_Lms' => 'Y'], ['Id' => 341, 'is_Lms' => 'N'], ['Id' => 8, 'is_Lms' => 'N']]);
        DB::table('standard')->insert([['id' => 4261, 'name' => '7']]);
        DB::table('subject')->insert([['id' => 5492, 'subject_name' => 'Mathematics']]);
        DB::table('chapter_master')->insert([
            ['id' => 8677, 'sub_institute_id' => 341, 'syear' => 2026, 'grade_id' => 7, 'standard_id' => 4261, 'subject_id' => 5492, 'chapter_name' => 'Integers'],
        ]);
        DB::table('topic_master')->insert([
            ['id' => 1, 'chapter_id' => 8677, 'name' => 'Adding and subtracting integers', 'description' => 'Learners add and subtract positive and negative integers using a number line.', 'topic_sort_order' => 1],
            ['id' => 2, 'chapter_id' => 8677, 'name' => 'Multiplying and dividing integers', 'description' => 'Learners multiply and divide positive and negative integers using sign rules.', 'topic_sort_order' => 2],
            ['id' => 3, 'chapter_id' => 8677, 'name' => 'Square roots and cube roots', 'description' => 'Learners relate square numbers and cube numbers to their roots.', 'topic_sort_order' => 3],
        ]);
        // Concepts are recorded twice per topic (an import ran twice): they must count once.
        DB::table('lms_concept')->insert([
            ['id' => 10, 'chapter_id' => 8677, 'topic_id' => 1, 'name' => 'Adding integers on a number line', 'definition' => 'Move right for positive, left for negative.'],
            ['id' => 11, 'chapter_id' => 8677, 'topic_id' => 1, 'name' => 'Subtraction as inverse of addition', 'definition' => 'a - b = a + (-b).'],
            ['id' => 110, 'chapter_id' => 8677, 'topic_id' => 1, 'name' => 'Adding integers on a number line', 'definition' => 'Move right for positive, left for negative.'],
            ['id' => 111, 'chapter_id' => 8677, 'topic_id' => 1, 'name' => 'Subtraction as inverse of addition', 'definition' => 'a - b = a + (-b).'],
            ['id' => 20, 'chapter_id' => 8677, 'topic_id' => 2, 'name' => 'Multiplying integers with different signs', 'definition' => 'The product is negative.'],
            ['id' => 30, 'chapter_id' => 8677, 'topic_id' => 3, 'name' => 'Square root as inverse of squaring', 'definition' => 'The root undoes the square.'],
        ]);

        $this->ai = new ScriptedClaude();
        app()->instance(ClaudeQuestionClient::class, $this->ai);
        $this->sleeps = [];
        app()->bind(PrayogshalaGenerator::class, fn ($app) => new PrayogshalaGenerator(
            $app->make(PrayogshalaService::class),
            $app->make(SimulationConfigValidator::class),
            $app->make(ClaudeQuestionClient::class),
            function (int $seconds): void {
                $this->sleeps[] = $seconds;
            },
        ));
    }

    private function generator(): PrayogshalaGenerator
    {
        return app(PrayogshalaGenerator::class);
    }

    private function row(int $topicId): ?object
    {
        return DB::table('lms_prayogshala_activity')->where('topic_id', $topicId)->whereNull('deleted_at')->first();
    }

    private function good(string $title = 'Walk the number line'): array
    {
        $a = PrayogshalaGenerationTest::goodActivity(['title' => $title]);

        return $a;
    }

    private const RATE = 'Claude is rate limiting this account. Try again shortly.';
    private const BUSY = 'Claude is temporarily unavailable (HTTP 503).';
    private const KEY = 'The Claude API key was rejected. Check ANTHROPIC_API_KEY.';
    private const QUOTA = 'Claude rejected the request (HTTP 400). Your credit balance is too low to access the API.';

    // ------------------------------------------------ classifying what the provider said

    private function gemini429(array $details, ?string $retryAfterHeader = null): AiProviderException
    {
        $body = json_encode(['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'You exceeded your current quota, please check your plan and billing details.', 'details' => $details]]);
        $headers = ['Content-Type' => 'application/json'] + ($retryAfterHeader !== null ? ['Retry-After' => $retryAfterHeader] : []);
        $response = new Response(new PsrResponse(429, $headers, $body));
        [$retryAfter, $metric, $status] = AiProviderException::metadataFrom($response);

        return new AiProviderException('provider text that must never be stored', 429, $retryAfter, $metric, $status);
    }

    public function test_a_spent_daily_quota_is_classified_as_quota_exhausted_not_a_rate_limit(): void
    {
        $e = $this->gemini429([
            ['@type' => 'type.googleapis.com/google.rpc.QuotaFailure', 'violations' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel-FreeTier']]],
            ['@type' => 'type.googleapis.com/google.rpc.RetryInfo', 'retryDelay' => '46923s'],
        ]);

        $failure = AiFailure::fromThrowable($e);

        $this->assertSame(AiFailure::QUOTA_EXHAUSTED, $failure->code);
        $this->assertFalse($failure->isRetryable());
        $this->assertTrue($failure->isBlocking());
        $this->assertSame(46923, $failure->retryAfter, 'the provider\'s own reset delay is kept');
        $this->assertStringContainsString('will not help', $failure->message());
        $this->assertStringNotContainsString('provider text', $failure->message());
    }

    public function test_a_per_minute_limit_is_a_retryable_rate_limit_and_keeps_its_retry_delay(): void
    {
        $e = $this->gemini429([
            ['violations' => [['quotaId' => 'GenerateRequestsPerMinutePerProjectPerModel-FreeTier']]],
            ['retryDelay' => '12.4s'],
        ]);

        $failure = AiFailure::fromThrowable($e);

        $this->assertSame(AiFailure::RATE_LIMITED, $failure->code);
        $this->assertTrue($failure->isRetryable());
        $this->assertFalse($failure->isBlocking());
        $this->assertSame(13, $failure->retryAfter);
    }

    public function test_a_retry_after_header_is_read_and_a_bare_429_is_treated_as_a_rate_limit(): void
    {
        $failure = AiFailure::fromThrowable($this->gemini429([], '7'));

        $this->assertSame(AiFailure::RATE_LIMITED, $failure->code);
        $this->assertSame(7, $failure->retryAfter);
    }

    public function test_every_other_failure_has_its_own_code_and_retry_class(): void
    {
        $cases = [
            [new AiProviderException('x', 401), AiFailure::INVALID_KEY, false, true],
            [new AiProviderException('x', 403), AiFailure::INVALID_KEY, false, true],
            [new AiProviderException('x', 404), AiFailure::MODEL_NOT_FOUND, false, true],
            [new AiProviderException('x', 400), AiFailure::BAD_REQUEST, false, false],
            [new AiProviderException('x', 503), AiFailure::PROVIDER_UNAVAILABLE, true, false],
            [new AiProviderException('x', 504), AiFailure::TIMEOUT, true, false],
            [new \RuntimeException('cURL error 28: Operation timed out'), AiFailure::TIMEOUT, true, false],
            [new \RuntimeException('No usable AI API key is configured.'), AiFailure::NO_PROVIDER, false, true],
            [new \RuntimeException('something nobody planned for'), AiFailure::UNKNOWN, false, false],
        ];
        foreach ($cases as [$e, $code, $retryable, $blocking]) {
            $f = AiFailure::fromThrowable($e);
            $this->assertSame($code, $f->code, $e->getMessage());
            $this->assertSame($retryable, $f->isRetryable(), "$code retryable");
            $this->assertSame($blocking, $f->isBlocking(), "$code blocking");
            $this->assertNotSame('', $f->message());
        }
    }

    public function test_the_stored_message_is_fixed_text_that_never_carries_provider_payloads_or_keys(): void
    {
        $leaky = new AiProviderException('(provider said: {"error":{"message":"key AIzaSyFAKEKEY123 over quota"}})', 429, null, 'GenerateRequestsPerDayPerProjectPerModel-FreeTier');

        $message = AiFailure::fromThrowable($leaky)->message();

        $this->assertStringNotContainsString('AIza', $message);
        $this->assertStringNotContainsString('provider said', $message);
        $this->assertStringNotContainsString('{', $message);
    }

    // ----------------------------------------------------------- retry and backoff policy

    public function test_a_rate_limit_is_retried_with_growing_pauses_and_then_succeeds(): void
    {
        $this->ai->error(self::RATE);
        $this->ai->error(self::RATE);
        $this->ai->reply($this->good());

        $result = $this->generator()->generate(1, 341, 5);

        $this->assertSame('created', $result['outcome']);
        $this->assertSame(3, $this->ai->calls);
        $this->assertCount(2, $this->sleeps);
        $this->assertGreaterThanOrEqual(2, $this->sleeps[0]);
        $this->assertLessThanOrEqual(3, $this->sleeps[0], 'base 2s + at most 25% jitter');
        $this->assertGreaterThanOrEqual(4, $this->sleeps[1], 'exponential: the second pause is longer');
        $this->assertLessThanOrEqual(5, $this->sleeps[1]);
        $this->assertSame('ready', $this->row(1)->generation_status);
        $this->assertNull($this->row(1)->generation_error_code);
    }

    public function test_a_rate_limit_that_never_clears_stops_after_the_attempt_budget(): void
    {
        foreach (range(1, 10) as $_) {
            $this->ai->error(self::RATE);
        }

        $result = $this->generator()->generate(1, 341, 5);

        $this->assertSame('failed', $result['outcome']);
        $this->assertSame('rate_limited', $result['error_code']);
        $this->assertSame(3, $this->ai->calls, 'three attempts in total, never more');
        $this->assertCount(2, $this->sleeps);
        $this->assertSame('rate_limited', $this->row(1)->generation_error_code);
        $this->assertNull($this->row(1)->lab_config, 'nothing is shown as an experiment');
    }

    public function test_transient_provider_errors_are_retried_the_same_bounded_way(): void
    {
        $this->ai->error(self::BUSY);
        $this->ai->reply($this->good());

        $this->assertSame('created', $this->generator()->generate(1, 341, 5)['outcome']);
        $this->assertSame(2, $this->ai->calls);
        $this->assertCount(1, $this->sleeps);
    }

    public function test_a_provider_retry_after_is_honoured_and_never_undercut_by_the_backoff(): void
    {
        $gen = new ScriptedGenerator(
            app(PrayogshalaService::class), app(SimulationConfigValidator::class), $this->ai,
            function (int $s): void {
                $this->sleeps[] = $s;
            },
        );
        $gen->script = [AiFailure::make(AiFailure::RATE_LIMITED, 30), ['ok' => true, 'content' => json_encode($this->good()), 'model' => 'm']];

        $this->assertSame('created', $gen->generate(1, 341, 5)['outcome']);
        $this->assertSame([30], $this->sleeps, 'waited exactly what the provider asked (30 > the 2s backoff)');
    }

    public function test_a_retry_after_longer_than_the_cap_is_not_slept_through(): void
    {
        $gen = new ScriptedGenerator(
            app(PrayogshalaService::class), app(SimulationConfigValidator::class), $this->ai,
            function (int $s): void {
                $this->sleeps[] = $s;
            },
        );
        $gen->script = [AiFailure::make(AiFailure::RATE_LIMITED, 600)];

        $result = $gen->generate(1, 341, 5);

        $this->assertSame('failed', $result['outcome']);
        $this->assertSame([], $this->sleeps, 'a request is not held open for ten minutes');
        $this->assertSame(600, $result['retry_after']);
        $this->assertSame(600, (int) $this->row(1)->generation_retry_after);
    }

    public function test_exhausted_quota_is_never_retried_and_says_what_to_configure(): void
    {
        $this->ai->error(self::QUOTA);
        $this->ai->reply($this->good()); // would succeed if (wrongly) retried

        $result = $this->generator()->generate(1, 341, 5);

        $this->assertSame('failed', $result['outcome']);
        $this->assertSame('quota_exhausted', $result['error_code']);
        $this->assertSame(1, $this->ai->calls, 'one request, no retry');
        $this->assertSame([], $this->sleeps);
        $row = $this->row(1);
        $this->assertSame('failed', $row->generation_status);
        $this->assertSame('quota_exhausted', $row->generation_error_code);
        $this->assertStringContainsString('quota or billing', $row->generation_error);
        $this->assertStringNotContainsString('credit balance', $row->generation_error, 'the provider\'s words are not stored');
    }

    public function test_a_rejected_key_is_never_retried(): void
    {
        $this->ai->error(self::KEY);

        $this->assertSame('invalid_key', $this->generator()->generate(1, 341, 5)['error_code']);
        $this->assertSame(1, $this->ai->calls);
        $this->assertSame([], $this->sleeps);
    }

    public function test_try_again_works_once_the_provider_is_fixed_and_reuses_the_same_row(): void
    {
        $this->ai->error(self::QUOTA);
        $failed = $this->generator()->generate(1, 341, 5);
        $rowId = $failed['activity_id'];

        $this->ai->reply($this->good());
        $retry = $this->generator()->generate(1, 341, 5);

        $this->assertSame('created', $retry['outcome']);
        $this->assertSame($rowId, $retry['activity_id']);
        $this->assertSame(1, DB::table('lms_prayogshala_activity')->count(), 'no duplicate was created');
        $row = $this->row(1);
        $this->assertSame('ready', $row->generation_status);
        $this->assertNull($row->generation_error_code);
        $this->assertSame(2, (int) $row->generation_attempts);
    }

    public function test_invalid_output_is_recorded_as_such_after_the_repair_round_and_stores_no_content(): void
    {
        $bad = $this->good();
        $bad['lab_config']['simulation']['type'] = 'run_javascript';
        $this->ai->reply($bad);
        $this->ai->reply($bad);

        $result = $this->generator()->generate(1, 341, 5);

        $this->assertSame('failed', $result['outcome']);
        $this->assertSame('invalid_output', $result['error_code']);
        $this->assertSame(2, $this->ai->calls, 'one call plus one repair round');
        $row = $this->row(1);
        $this->assertNull($row->lab_config);
        $this->assertSame('draft', $row->status, 'unvalidated content is never published or even reviewable');
    }

    // ------------------------------------------------------------ batches and resumption

    public function test_a_batch_stops_cleanly_on_a_blocking_failure_and_resumes_without_redoing_finished_topics(): void
    {
        // Topic 1 succeeds, topic 2 hits the quota wall. Topic 3 must not be attempted.
        $this->ai->reply($this->good('First'));
        $this->ai->error(self::QUOTA);

        Artisan::call('prayogshala:generate', ['--tenant' => 341, '--chapter' => 8677, '--limit' => 10]);
        $output = Artisan::output();

        $this->assertSame(2, $this->ai->calls, 'the third topic was not even tried');
        $this->assertStringContainsString('Stopping', $output);
        $this->assertSame('ready', $this->row(1)->generation_status);
        $this->assertSame('failed', $this->row(2)->generation_status);
        $this->assertNull($this->row(3), 'no placeholder or request was spent on the remaining topic');
        $firstId = (int) $this->row(1)->id;
        $firstVersion = (int) $this->row(1)->generation_version;

        // The provider is fixed. Re-running does only the two unfinished topics.
        $this->ai->reply($this->good('Second'));
        $this->ai->reply($this->good('Third'));
        Artisan::call('prayogshala:generate', ['--tenant' => 341, '--chapter' => 8677, '--limit' => 10]);

        $this->assertSame(4, $this->ai->calls, 'exactly two more requests');
        $this->assertSame($firstId, (int) $this->row(1)->id);
        $this->assertSame($firstVersion, (int) $this->row(1)->generation_version, 'the finished topic was left alone');
        $this->assertSame(['ready', 'ready', 'ready'], array_map(fn ($t) => $this->row($t)->generation_status, [1, 2, 3]));
        $this->assertSame(3, DB::table('lms_prayogshala_activity')->whereNull('deleted_at')->count());
    }

    // ------------------------------------------------------------------- source data

    public function test_a_concept_recorded_twice_counts_once_in_the_context_and_the_stored_ids(): void
    {
        $ctx = $this->generator()->context(1, 341);

        $this->assertSame(['Adding integers on a number line', 'Subtraction as inverse of addition'], array_column($ctx['concepts'], 'name'));
        $this->assertSame([10, 11], $ctx['source_refs']['concept_ids']);

        $this->ai->reply($this->good());
        $this->generator()->generate(1, 341, 5);
        $this->assertSame([10, 11], json_decode($this->row(1)->concept_ids, true));
    }

    public function test_the_institute_comes_from_the_chapter_owner_and_another_institute_cannot_reach_it(): void
    {
        $this->assertNotNull($this->generator()->context(1, 341));
        $this->assertNull($this->generator()->context(1, 8), 'an unrelated institute cannot resolve the topic');

        $this->assertSame('not_found', $this->generator()->generate(1, 8, 5)['outcome']);
        $this->assertSame(0, $this->ai->calls);
        $this->assertSame(0, DB::table('lms_prayogshala_activity')->count());
    }

    // ---------------------------------------------------------------- the HTTP surface

    private function act(array $input, int $tenant, string $profile = 'Teacher', bool $student = false)
    {
        $request = Request::create('/x', 'POST', $input);
        $store = app('session.store');
        $store->flush();
        $request->setLaravelSession($store);
        $store->put('sub_institute_id', $tenant);
        $store->put('user_id', 55);
        $store->put('user_profile_name', $profile);
        $store->put('is_student', $student);
        $response = app()->call([app(PrayogshalaApiController::class), 'generate'], ['request' => $request]);

        return [$response->getStatusCode(), $response->getData(true)];
    }

    public function test_the_endpoint_reports_a_quota_failure_with_a_code_and_no_provider_text(): void
    {
        $this->ai->error(self::QUOTA);

        [$status, $body] = $this->act(['topic_id' => 1], 341);

        $this->assertSame(502, $status);
        $this->assertSame(0, $body['status_code']);
        $this->assertSame('failed', $body['data']['outcome']);
        $this->assertSame('quota_exhausted', $body['data']['error_code']);
        $this->assertSame('quota_exhausted', $body['data']['activity']['generation_error_code']);
        $this->assertStringNotContainsString('credit balance', json_encode($body));
        $this->assertSame(1, $this->ai->calls);
    }

    public function test_the_endpoint_does_not_start_a_second_request_for_a_topic_already_generating(): void
    {
        DB::table('lms_prayogshala_activity')->insert([
            'sub_institute_id' => 341, 'standard_id' => 4261, 'subject_id' => 5492, 'chapter_id' => 8677,
            'topic_id' => 1, 'topic_slot' => 1, 'title' => 'x', 'slug' => 'topic-1',
            'generation_status' => 'generating', 'status' => 'draft', 'updated_at' => now(),
        ]);

        [, $body] = $this->act(['topic_id' => 1], 341);

        $this->assertSame('busy', $body['data']['outcome']);
        $this->assertSame(0, $this->ai->calls);
    }

    public function test_learners_cannot_trigger_generation_and_never_see_failure_details(): void
    {
        [$status] = $this->act(['topic_id' => 1], 341, 'Student', true);
        $this->assertSame(403, $status);
        $this->assertSame(0, $this->ai->calls);

        $this->ai->error(self::QUOTA);
        $this->act(['topic_id' => 1], 341);
        $row = $this->row(1);
        $learnerView = app(PrayogshalaService::class)->present($row, 341, null, true);

        $this->assertNull($learnerView['generation_error']);
        $this->assertNull($learnerView['generation_error_code']);
    }
}

/** A scripted stand-in for the Claude client: queued replies and errors, a call counter, no network. */
class ScriptedClaude extends ClaudeQuestionClient
{
    public int $calls = 0;

    /** @var list<array<string,mixed>> */
    private array $queue = [];

    public function __construct()
    {
        // No parent constructor: the client has no credential of its own any more.
    }

    public function error(string $message): void
    {
        $this->queue[] = ['ok' => false, 'error' => $message];
    }

    public function reply(array $activity): void
    {
        $this->queue[] = ['ok' => true, 'content' => json_encode($activity), 'model' => 'scripted', 'finish_reason' => 'end_turn', 'usage' => []];
    }

    public function complete(string $system, string $user, int|string|null $subInstituteId = null, string $module = 'assessment_ai'): array
    {
        $this->calls++;

        return array_shift($this->queue) ?? ['ok' => false, 'error' => 'ScriptedClaude: nothing queued.'];
    }
}

/** A generator whose provider call is scripted with ready-made AiFailure values, to test Retry-After. */
class ScriptedGenerator extends PrayogshalaGenerator
{
    /** @var list<AiFailure|array<string,mixed>> */
    public array $script = [];

    protected function complete(string $system, string $user, int $tenant): array
    {
        $next = array_shift($this->script);
        if ($next instanceof AiFailure) {
            return ['ok' => false, 'failure' => $next];
        }

        return $next ?? ['ok' => false, 'failure' => AiFailure::make(AiFailure::UNKNOWN)];
    }
}
