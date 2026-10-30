<?php

namespace Tests\Feature\CoursePresentation;

use App\Domain\AI\Support\AiProviderException;
use App\Domain\AI\Support\GeminiClient;
use App\Domain\AI\Support\ProviderKeyResolver;
use App\Services\CoursePresentation\GenerationProgress;
use App\Services\StudyDeck\Story\GeminiStoryCompleter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP 503 UNAVAILABLE ("This model is currently experiencing high demand") in a time-boxed run: told apart from a 429 and from a timeout, retried
 * with Retry-After or a bounded jittered backoff that can never outlast the run's deadline, handed to an already-configured fallback model after
 * repeated overload, logged without secrets, and reported to the teacher as what it is. No model is called: the client is a mock or faked HTTP.
 */
class OverloadHandlingTest extends CoursePresentationTestCase
{
    private function overload(?int $retryAfter = null): AiProviderException
    {
        return new AiProviderException('The AI model is busy at the provider. (provider said: This model is currently experiencing high demand.)', 503, $retryAfter, null, 'UNAVAILABLE');
    }

    /** A mocked client whose configured model is `gemini-primary` and whose configured fallbacks are `$fallbacks`. */
    private function client(array $fallbacks = []): \Mockery\MockInterface
    {
        $client = \Mockery::mock(GeminiClient::class);
        $client->shouldReceive('defaultModel')->andReturn('gemini-primary');
        $client->shouldReceive('fallbackModels')->andReturn($fallbacks);

        return $client;
    }

    /** @return array{0:GeminiStoryCompleter,1:\ArrayObject} the completer (deadline set, jitter fixed at 1.0, no real waiting) and the pauses it asked for */
    private function completer(\Mockery\MockInterface $client, ?float $secondsLeft = 240): array
    {
        $sleeps = new \ArrayObject();
        $completer = (new GeminiStoryCompleter($client, function (int $s) use ($sleeps): void {
            $sleeps[] = $s;
        }))->useRandom(fn (float $a, float $b): float => 1.0);
        if ($secondsLeft !== null) {
            $completer->setDeadline(microtime(true) + $secondsLeft);
        }

        return [$completer, $sleeps];
    }

    // ------------------------------------------------------------------------------------------------ classification

    public function test_a_503_is_overload_and_is_told_apart_from_rate_limits_quota_server_errors_and_timeouts(): void
    {
        $this->assertSame('overloaded', GeminiStoryCompleter::classifyFailure($this->overload()));
        $this->assertSame('overloaded', GeminiStoryCompleter::classifyFailure(new AiProviderException('x', 503)));
        $this->assertSame('overloaded', GeminiStoryCompleter::classifyFailure(new AiProviderException('x', 529)));
        $this->assertSame('overloaded', GeminiStoryCompleter::classifyFailure(new AiProviderException('x', 500, null, null, 'UNAVAILABLE')), 'the provider\'s own status wins');
        $this->assertSame('server_error', GeminiStoryCompleter::classifyFailure(new AiProviderException('x', 500)));
        $this->assertSame('server_error', GeminiStoryCompleter::classifyFailure(new AiProviderException('x', 502)));
        $this->assertSame('rate_limit', GeminiStoryCompleter::classifyFailure(new AiProviderException('x', 429, 20, 'GenerateContentInputTokensPerMinutePerProjectPerModel')));
        $this->assertSame('quota', GeminiStoryCompleter::classifyFailure(new AiProviderException('x', 429, 1, 'GenerateRequestsPerDayPerProjectPerModel')));
        $this->assertSame('unclassified', GeminiStoryCompleter::classifyFailure(new AiProviderException('x', 429)));
        $this->assertSame('timeout', GeminiStoryCompleter::classifyFailure(new \Illuminate\Http\Client\ConnectionException('cURL error 28')));
        $this->assertSame('other', GeminiStoryCompleter::classifyFailure(new AiProviderException('x', 400)));
        $this->assertSame('other', GeminiStoryCompleter::classifyFailure(new \RuntimeException('x')));
    }

    public function test_the_teachers_error_message_names_provider_overload_and_is_not_taken_for_a_quota_or_a_time_out(): void
    {
        [$completer] = $this->completer($this->client());
        $message = $completer->overloadMessage('gemini-3.6-flash', true);

        $this->assertStringContainsString('gemini-3.6-flash', $message);
        $this->assertStringContainsString('overloaded at the provider', $message);
        $this->assertStringContainsString('Nothing was stored', $message);
        $this->assertStringContainsString('not a problem with your chapter', $message);
        $this->assertStringNotContainsStringIgnoringCase('key', $message, 'no key, header or prompt in the words');

        $failure = GenerationProgress::classifyError($message);
        $this->assertSame(['overload', true], [$failure['kind'], $failure['retryable']]);
        $this->assertStringContainsString('overloaded', $failure['hint']);
        $this->assertSame('quota', GenerationProgress::classifyError('Gemini daily quota exhausted')['kind'], 'a quota is still a quota');
        $this->assertSame('budget', GenerationProgress::classifyError('The time allowed for this generation ran out while waiting to ask the model again.')['kind'], 'a plain time-out is still a time-out');
        $this->assertSame('overload', GenerationProgress::classifyError('The AI provider returned: UNAVAILABLE, high demand')['kind']);
    }

    // ------------------------------------------------------------------------------------------------ backoff

    public function test_without_a_retry_after_the_backoff_doubles_from_two_seconds_with_jitter_and_stays_bounded(): void
    {
        [$c] = $this->completer($this->client());
        $this->assertSame([2, 4, 8, 16, 16], [$c->backoffSeconds(1), $c->backoffSeconds(2), $c->backoffSeconds(3), $c->backoffSeconds(4), $c->backoffSeconds(9)], '2, 4, 8, 16 s, then capped at 16 s');

        $low = (new GeminiStoryCompleter($this->client(), fn () => null))->useRandom(fn ($a, $b) => $a);
        $high = (new GeminiStoryCompleter($this->client(), fn () => null))->useRandom(fn ($a, $b) => $b);
        $low->setDeadline(microtime(true) + 240);
        $high->setDeadline(microtime(true) + 240);
        $this->assertSame([3, 5], [$low->backoffSeconds(2), $high->backoffSeconds(2)], 'a quarter less or more, so parallel callers do not return together');
        $this->assertGreaterThanOrEqual(1, $low->backoffSeconds(0));
    }

    public function test_the_providers_retry_after_is_obeyed_and_capped(): void
    {
        [$c] = $this->completer($this->client());

        $this->assertSame(7, $c->backoffSeconds(1, 7));
        $this->assertSame(30, $c->backoffSeconds(1, 600), 'a very long Retry-After is capped at 30 s');
        $this->assertSame(2, $c->backoffSeconds(1, 0), 'a zero or missing one falls back to the backoff');
    }

    // ------------------------------------------------------------------------------------------------ retry limits

    public function test_a_model_that_stays_overloaded_is_asked_a_bounded_number_of_times_with_growing_pauses_then_the_run_fails_with_the_overload_message(): void
    {
        $client = $this->client();
        $client->shouldReceive('chat')->andThrow($this->overload());
        [$completer, $sleeps] = $this->completer($client);
        $started = microtime(true);

        try {
            $completer->complete('s', 'p');
            $this->fail('An overloaded model produced an answer.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('overloaded at the provider', $e->getMessage());
            $this->assertStringContainsString('did not recover after several attempts', $e->getMessage());
        }

        $this->assertSame(4, $completer->calls, 'four attempts, the first included');
        $this->assertSame([2, 4, 8], $sleeps->getArrayCopy());
        $this->assertLessThan(5, microtime(true) - $started, 'no real time was spent waiting');
    }

    public function test_a_retry_after_from_the_provider_sets_the_pause(): void
    {
        $client = $this->client();
        $client->shouldReceive('chat')->andThrow($this->overload(7));
        [$completer, $sleeps] = $this->completer($client);

        try {
            $completer->complete('s', 'p');
        } catch (\RuntimeException) {
        }

        $this->assertSame([7, 7, 7], $sleeps->getArrayCopy());
    }

    public function test_an_answer_after_an_overload_is_used_and_nothing_more_is_waited_for(): void
    {
        $client = $this->client();
        $client->shouldReceive('chat')->once()->andThrow($this->overload());
        $client->shouldReceive('chat')->once()->andReturn('{"ok":1}');
        [$completer, $sleeps] = $this->completer($client);

        $this->assertSame('{"ok":1}', $completer->complete('s', 'p'));
        $this->assertSame([2], $sleeps->getArrayCopy());
    }

    public function test_a_429_and_a_400_are_not_treated_as_overload(): void
    {
        $quota = $this->client();
        $quota->shouldReceive('chat')->once()->andThrow(new AiProviderException('quota or billing limit used up', 429, 1, 'GenerateRequestsPerDayPerProjectPerModel'));
        [$a, $sleptA] = $this->completer($quota);
        try {
            $a->complete('s', 'p');
            $this->fail('A spent quota produced an answer.');
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString('overloaded at the provider', $e->getMessage());
        }
        $this->assertSame([], $sleptA->getArrayCopy(), 'a spent quota is not waited for');

        $bad = $this->client();
        $bad->shouldReceive('chat')->once()->andThrow(new AiProviderException('malformed request', 400));
        [$b, $sleptB] = $this->completer($bad);
        try {
            $b->complete('s', 'p');
            $this->fail('A 400 produced an answer.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Gemini could not answer', $e->getMessage());
        }
        $this->assertSame([], $sleptB->getArrayCopy());
    }

    public function test_without_a_deadline_the_story_path_keeps_its_patient_twenty_second_steps_and_the_provider_retry_after(): void
    {
        $client = $this->client();
        $client->shouldReceive('chat')->andThrow($this->overload());
        [$completer, $sleeps] = $this->completer($client, null);

        try {
            $completer->complete('s', 'p');
        } catch (\RuntimeException) {
        }

        $this->assertSame([20, 40, 60, 80], $sleeps->getArrayCopy());
        $this->assertSame(5, $completer->calls);
    }

    // ------------------------------------------------------------------------------------------------ the deadline

    public function test_a_pause_is_clipped_so_it_never_outlasts_the_deadline(): void
    {
        $client = $this->client();
        $client->shouldReceive('chat')->once()->andThrow($this->overload(30));
        $client->shouldReceive('chat')->once()->andReturn('{"ok":1}');
        [$completer, $sleeps] = $this->completer($client, 10);   // 10 s left: the 30 s Retry-After cannot be honoured in full

        $this->assertSame('{"ok":1}', $completer->complete('s', 'p'));

        $this->assertSame([6], $sleeps->getArrayCopy(), 'clipped to what is left minus the 3 s margin the rest of the run keeps');
    }

    public function test_when_no_room_is_left_to_wait_the_run_stops_with_the_overload_reason_not_a_generic_timeout(): void
    {
        $client = $this->client();
        $client->shouldReceive('chat')->once()->andThrow($this->overload(20));
        [$completer, $sleeps] = $this->completer($client, 3.5);

        try {
            $completer->complete('s', 'p');
            $this->fail('It waited past the deadline.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('overloaded at the provider', $e->getMessage());
            $this->assertStringContainsString('did not recover within the time allowed', $e->getMessage());
            $this->assertSame('overload', GenerationProgress::classifyError($e->getMessage())['kind']);
        }
        $this->assertSame([], $sleeps->getArrayCopy());
    }

    // ------------------------------------------------------------------------------------------------ the fallback model

    public function test_after_repeated_overload_the_first_already_configured_fallback_model_is_used_and_the_switch_is_logged(): void
    {
        $client = $this->client(['gemini-fallback', 'gemini-other']);
        $models = [];
        $client->shouldReceive('chat')->andReturnUsing(function ($messages, $model) use (&$models) {
            $models[] = $model;
            if ($model === null) {
                throw $this->overload();
            }

            return '{"ok":"from ' . $model . '"}';
        });
        [$completer] = $this->completer($client);
        $logged = [];
        Log::shouldReceive('warning')->andReturnUsing(function ($message, $context = []) use (&$logged) {
            $logged[] = [$message, $context];
        });
        Log::shouldReceive('info')->andReturnNull();

        $answer = $completer->complete('s', 'p');

        $this->assertSame('{"ok":"from gemini-fallback"}', $answer);
        $this->assertSame([null, null, 'gemini-fallback'], $models, 'the configured model twice, then the first configured fallback');
        $this->assertSame('[gemini] model overloaded, using the configured fallback model', $logged[0][0]);
        $this->assertSame(['gemini-primary', 'gemini-fallback'], [$logged[0][1]['from'], $logged[0][1]['to']], 'never silent');
    }

    public function test_with_no_fallback_configured_or_the_switch_turned_off_the_configured_model_is_kept(): void
    {
        $none = $this->client([]);
        $none->shouldReceive('chat')->andThrow($this->overload());
        [$a] = $this->completer($none);
        try {
            $a->complete('s', 'p');
        } catch (\RuntimeException) {
        }
        $this->assertSame(4, $a->calls);

        config(['ai.provider.gemini.overload_fallback' => false]);
        $off = $this->client(['gemini-fallback']);
        $seen = [];
        $off->shouldReceive('chat')->andReturnUsing(function ($m, $model) use (&$seen) {
            $seen[] = $model;
            throw $this->overload();
        });
        [$b] = $this->completer($off);
        try {
            $b->complete('s', 'p');
        } catch (\RuntimeException) {
        }
        $this->assertSame([null, null, null, null], $seen, 'the configured model is never changed when the switch is off');
    }

    // ------------------------------------------------------------------------------------------------ parallel slots

    public function test_overloaded_parallel_slots_are_asked_again_together_after_one_shared_pause(): void
    {
        $client = $this->client();
        $rounds = [];
        $client->shouldReceive('chatMany')->andReturnUsing(function ($requests) use (&$rounds) {
            $rounds[] = array_keys($requests);
            $out = [];
            foreach (array_keys($requests) as $i) {
                $out[$i] = count($rounds) === 1 && $i !== 1 ? '{"ok":' . $i . '}' : (count($rounds) === 1 ? $this->overload() : '{"ok":' . $i . '}');
            }

            return $out;
        });
        [$completer, $sleeps] = $this->completer($client);

        $out = $completer->completeMany('s', ['a', 'b', 'c']);

        $this->assertSame([0 => '{"ok":0}', 1 => '{"ok":1}', 2 => '{"ok":2}'], $out);
        $this->assertSame([[0, 1, 2], [1]], $rounds, 'only the refused slot went again, in a parallel round and not one by one');
        $this->assertSame([2], $sleeps->getArrayCopy(), 'one shared pause');
    }

    public function test_when_every_parallel_slot_stays_overloaded_the_run_fails_once_with_the_overload_message_not_one_wait_per_slot(): void
    {
        $client = $this->client();
        $client->shouldReceive('chatMany')->andReturnUsing(fn ($requests) => array_map(fn () => $this->overload(), $requests));
        $client->shouldReceive('chat')->never();
        [$completer, $sleeps] = $this->completer($client);

        try {
            $completer->completeMany('s', ['a', 'b', 'c', 'd', 'e']);
            $this->fail('An overloaded model produced answers.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('overloaded at the provider', $e->getMessage());
        }

        $this->assertSame([2, 4, 8], $sleeps->getArrayCopy(), 'three shared pauses for five slots, not fifteen');
    }

    // ------------------------------------------------------------------------------------------------ diagnostics

    public function test_every_retry_logs_status_model_attempt_delay_and_time_left_and_never_a_secret_or_a_prompt(): void
    {
        $client = $this->client();
        $client->shouldReceive('chat')->andThrow($this->overload(5));
        [$completer] = $this->completer($client);
        $lines = [];
        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('info')->andReturnUsing(function ($message, $context = []) use (&$lines) {
            $lines[] = [$message, $context];
        });

        try {
            $completer->complete('SECRET SYSTEM TEXT', 'SECRET PROMPT TEXT');
        } catch (\RuntimeException) {
        }

        $this->assertCount(3, $lines);
        [$message, $context] = $lines[0];
        $this->assertSame('[gemini] retrying', $message);
        $this->assertSame(['overloaded', 503, 'UNAVAILABLE', 'gemini-primary', 1, 5, 5], [$context['kind'], $context['status'], $context['provider_status'], $context['model'], $context['attempt'], $context['delay'], $context['retry_after']]);
        $this->assertGreaterThan(200, $context['seconds_left']);
        $dump = json_encode($lines);
        $this->assertStringNotContainsString('SECRET', $dump);
        $this->assertStringNotContainsString('test-key', $dump);
        $this->assertArrayNotHasKey('prompt', $context);
    }

    // ------------------------------------------------------------------------------------------------ the real client

    private function realClient(): GeminiClient
    {
        config(['ai.provider.gemini.model' => 'gemini-primary', 'ai.provider.gemini.fallback_models' => ['gemini-fallback'], 'ai.provider.gemini.base_url' => 'https://gemini.test/v1beta']);
        $this->app->instance(ProviderKeyResolver::class, new class extends ProviderKeyResolver {
            public function __construct()
            {
            }

            public function resolve(string $apiType, int|string|null $subInstituteId = null, ?string $envFallback = null): ?array
            {
                return ['api_key' => 'test-key-not-real', 'id' => 0];
            }
        });

        return app(GeminiClient::class);
    }

    private function unavailable(): \GuzzleHttp\Promise\PromiseInterface
    {
        return Http::response(['error' => ['code' => 503, 'status' => 'UNAVAILABLE', 'message' => 'This model is currently experiencing high demand. Spikes in demand are usually temporary. Please try again later.']], 503, ['Retry-After' => '9']);
    }

    public function test_the_real_client_turns_the_providers_503_into_a_typed_error_with_its_status_and_retry_after(): void
    {
        $client = $this->realClient();
        Http::fake(['gemini.test/*' => $this->unavailable()]);

        try {
            $client->chat([['role' => 'user', 'content' => 'hi']], null, 100, null, true, 5, 1);
            $this->fail('A 503 produced an answer.');
        } catch (AiProviderException $e) {
            $this->assertSame([503, 'UNAVAILABLE', 9], [$e->status, $e->providerStatus, $e->retryAfter]);
            $this->assertSame('overloaded', GeminiStoryCompleter::classifyFailure($e));
        }
        $this->assertCount(1, Http::recorded(), 'a caller that owns the retrying asks the provider once');
    }

    public function test_the_real_client_lists_only_fallback_models_that_are_already_configured(): void
    {
        $client = $this->realClient();

        $this->assertSame(['gemini-fallback'], $client->fallbackModels());
        $this->assertNotContains('gemini-primary', $client->fallbackModels());
    }
}
