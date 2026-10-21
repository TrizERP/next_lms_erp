<?php

namespace Tests\Feature\ExamEvaluation;

use App\Services\Homework\Exceptions\ModelQuotaExhaustedException;
use App\Services\Homework\Exceptions\ProviderBusyException;
use App\Services\Homework\Exceptions\RateLimitedException;
use App\Services\Homework\GeminiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * App\Services\Homework\GeminiClient is shared by homework, assignments and exam
 * answer sheets. These pin what it has always done (so those flows are unchanged)
 * next to what the exam work added to it. Every request is faked; none leaves
 * the process.
 */
class GeminiDocumentClientTest extends ExamEvaluationTestCase
{
    private const MAIN = 'https://generativelanguage.googleapis.com/v1beta/models/main-model:generateContent';
    private const BACKUP = 'https://generativelanguage.googleapis.com/v1beta/models/backup-model:generateContent';

    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
        config(['ai.provider.gemini.fallback_model' => '']);
    }

    /** A client handed the configuration the module would resolve to, so no database is needed. */
    private function client(?string $key = 'test-key-not-real'): GeminiClient
    {
        return (new GeminiClient())->forConfiguration(new ResolvedAiConfiguration('gemini', 'main-model', $key, 'test'));
    }

    private function ok(string $text = '{"a":1}', string $finish = 'STOP'): \GuzzleHttp\Promise\PromiseInterface
    {
        return Http::response(['candidates' => [['content' => ['parts' => [['text' => $text]]], 'finishReason' => $finish]]], 200);
    }

    private function requestsTo(string $url): int
    {
        return Http::recorded(fn (Request $request) => $request->url() === $url)->count();
    }

    public function test_a_reply_comes_back_with_its_text_and_how_it_finished(): void
    {
        Http::fake([self::MAIN => $this->ok('hello')]);

        $result = $this->client()->generateContent([['text' => 'hi']]);

        $this->assertSame('hello', $result['text']);
        $this->assertSame('STOP', $result['finish_reason']);
        $this->assertSame('main-model', $result['model']);
    }

    public function test_a_reply_cut_off_at_the_token_limit_is_reported_not_thrown(): void
    {
        // Homework callers ignore finish_reason and behave as before; exam callers act on it.
        Http::fake([self::MAIN => $this->ok('{"half":', 'MAX_TOKENS')]);

        $result = $this->client()->generateContent([['text' => 'hi']]);

        $this->assertSame('MAX_TOKENS', $result['finish_reason']);
        $this->assertSame('{"half":', $result['text']);
    }

    public function test_the_document_timeout_defaults_to_ninety_seconds_not_the_brains_forty_five(): void
    {
        $method = new \ReflectionMethod(GeminiClient::class, 'requestTimeout');

        $this->assertSame(90, $method->invoke(new GeminiClient()));

        config(['ai.provider.gemini.document_timeout' => 120]);
        $this->assertSame(120, $method->invoke(new GeminiClient()));
    }

    public function test_a_busy_provider_is_retried_then_raised_as_busy(): void
    {
        Http::fake([self::MAIN => Http::response(['error' => ['message' => 'high demand']], 503)]);

        $this->expectException(ProviderBusyException::class);

        try {
            $this->client()->generateContent([['text' => 'hi']]);
        } finally {
            $this->assertSame(4, $this->requestsTo(self::MAIN));
        }
    }

    public function test_a_busy_provider_falls_back_to_the_configured_model(): void
    {
        config(['ai.provider.gemini.fallback_model' => 'backup-model']);
        Http::fake([
            self::MAIN => Http::response(['error' => ['message' => 'high demand']], 503),
            self::BACKUP => $this->ok('from backup'),
        ]);

        $result = $this->client()->generateContent([['text' => 'hi']]);

        $this->assertSame('from backup', $result['text']);
        $this->assertSame('backup-model', $result['model']);
    }

    public function test_with_no_fallback_configured_no_other_model_is_tried(): void
    {
        // The old built-in default was a retired model id. Nothing is assumed now.
        Http::fake([self::MAIN => Http::response(['error' => ['message' => 'high demand']], 503)]);

        try {
            $this->client()->generateContent([['text' => 'hi']]);
            $this->fail('should have raised');
        } catch (ProviderBusyException) {
            // expected
        }

        $this->assertSame(0, Http::recorded(fn (Request $request) => ! str_contains($request->url(), 'main-model'))->count());
    }

    public function test_a_fallback_equal_to_the_main_model_is_ignored(): void
    {
        config(['ai.provider.gemini.fallback_model' => 'main-model']);
        Http::fake([self::MAIN => Http::response(['error' => ['message' => 'high demand']], 503)]);

        $this->expectException(ProviderBusyException::class);
        $this->client()->generateContent([['text' => 'hi']]);
    }

    public function test_a_rate_limit_is_its_own_type_and_is_not_retried_at_once(): void
    {
        Http::fake([self::MAIN => Http::response(['error' => ['message' => 'quota']], 429)]);

        try {
            $this->client()->generateContent([['text' => 'hi']]);
            $this->fail('should have raised');
        } catch (RateLimitedException $exception) {
            // Still a RuntimeException, so every existing `catch (\RuntimeException)` keeps working.
            $this->assertInstanceOf(\RuntimeException::class, $exception);
            $this->assertStringContainsString('rate limit', $exception->getMessage());
        }

        $this->assertSame(1, $this->requestsTo(self::MAIN));
    }

    public function test_a_per_model_daily_quota_is_distinguished_from_a_rate_limit(): void
    {
        Http::fake([self::MAIN => Http::response(['error' => [
            'message' => 'quota',
            'details' => [['violations' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel-FreeTier']]]],
        ]], 429)]);

        $this->expectException(ModelQuotaExhaustedException::class);
        $this->client()->generateContent([['text' => 'hi']]);
    }

    public function test_a_bad_key_is_a_plain_runtime_exception_with_a_message_a_teacher_can_act_on(): void
    {
        Http::fake([self::MAIN => Http::response(['error' => ['message' => 'API key not valid']], 403)]);

        try {
            $this->client()->generateContent([['text' => 'hi']]);
            $this->fail('should have raised');
        } catch (\RuntimeException $exception) {
            $this->assertNotInstanceOf(RateLimitedException::class, $exception);
            $this->assertNotInstanceOf(ProviderBusyException::class, $exception);
            $this->assertStringContainsString('Gemini key', $exception->getMessage());
        }
    }

    public function test_a_network_timeout_surfaces_as_a_connection_exception(): void
    {
        Http::fake([self::MAIN => fn () => throw new ConnectionException('cURL error 28: Operation timed out')]);

        $this->expectException(ConnectionException::class);
        $this->client()->generateContent([['text' => 'hi']]);
    }

    public function test_a_refused_prompt_says_why_instead_of_returning_nothing(): void
    {
        Http::fake([self::MAIN => Http::response(['promptFeedback' => ['blockReason' => 'SAFETY']], 200)]);

        try {
            $this->client()->generateContent([['text' => 'hi']]);
            $this->fail('should have raised');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('SAFETY', $exception->getMessage());
        }
    }

    public function test_an_empty_reply_is_still_an_error(): void
    {
        Http::fake([self::MAIN => Http::response(['candidates' => [['content' => ['parts' => [['text' => '   ']]]]]], 200)]);

        $this->expectExceptionMessage('Gemini returned an empty response.');
        $this->client()->generateContent([['text' => 'hi']]);
    }

    public function test_a_missing_key_fails_before_any_request_is_made(): void
    {
        Http::fake();

        try {
            $this->client(null)->generateContent([['text' => 'hi']]);
            $this->fail('should have raised');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('AI Providers', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_the_provider_body_is_not_logged_in_full(): void
    {
        // A failure log line keeps enough to diagnose, not an unbounded provider payload.
        $logged = null;
        \Illuminate\Support\Facades\Log::shouldReceive('warning')->andReturnUsing(function ($message, $context = []) use (&$logged) {
            $logged = $context;
        });
        Http::fake([self::MAIN => Http::response(['error' => ['message' => str_repeat('x', 5000)]], 403)]);

        try {
            $this->client()->generateContent([['text' => 'hi']]);
        } catch (\RuntimeException) {
        }

        $this->assertNotNull($logged);
        $this->assertLessThanOrEqual(1000, mb_strlen($logged['body']));
    }
}
