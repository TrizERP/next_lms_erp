<?php

namespace Tests\Feature\CoursePresentation;

use App\Domain\AI\Support\AiProviderException;
use App\Domain\AI\Support\GeminiClient;
use App\Services\CoursePresentation\GenerationProgress;
use App\Services\StudyDeck\Story\GeminiStoryCompleter;
use Illuminate\Support\Facades\Log;

/**
 * What a 429 is, what the run logs about it, and what the teacher is told when the deadline ends a wait for it.
 * No model is called and no database is read: the client is a mock.
 */
class GeminiRateLimitTest extends CoursePresentationTestCase
{
    private function limit(?string $metric, ?int $retryAfter = null): AiProviderException
    {
        return new AiProviderException('limited', 429, $retryAfter, $metric, 'RESOURCE_EXHAUSTED');
    }

    private function completer(\Mockery\MockInterface $client, array &$slept = []): GeminiStoryCompleter
    {
        return new GeminiStoryCompleter($client, function (int $s) use (&$slept): void {
            $slept[] = $s;
        });
    }

    public function test_a_429_is_classified_only_from_the_quota_id(): void
    {
        $this->assertSame('rate_limit', GeminiStoryCompleter::classify429($this->limit('generativelanguage.googleapis.com/generate_content_free_tier_input_token_count_PerMinutePerProjectPerModel')));
        $this->assertSame('quota', GeminiStoryCompleter::classify429($this->limit('generativelanguage.googleapis.com/generate_requests_per_model_PerDayPerProjectPerModel')));
        $this->assertSame('unclassified', GeminiStoryCompleter::classify429($this->limit(null, 30)));
        $this->assertSame('unclassified', GeminiStoryCompleter::classify429($this->limit('some_other_metric')));
    }

    public function test_a_rate_limit_wait_that_does_not_fit_names_the_rate_limit(): void
    {
        $client = \Mockery::mock(GeminiClient::class);
        $client->shouldReceive('chat')->once()->andThrow($this->limit('x_PerMinute_y', 60));
        $slept = [];
        $c = $this->completer($client, $slept);
        $c->setDeadline(microtime(true) + 30);   // 60 s asked, 30 s left

        try {
            $c->complete('s', 'p');
            $this->fail('It waited past the deadline.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('ran out while waiting', $e->getMessage());
            $this->assertStringContainsString('per-minute rate limit was reached', $e->getMessage());
            $this->assertSame('rate_limit', GenerationProgress::classifyError($e->getMessage())['kind']);
        }
        $this->assertSame([], $slept, 'the wait was never started');
        $this->assertSame(1, $c->calls);
    }

    public function test_an_unclassified_429_is_reported_as_unclassified_not_as_a_rate_limit(): void
    {
        $client = \Mockery::mock(GeminiClient::class);
        $client->shouldReceive('chat')->once()->andThrow($this->limit(null, 60));
        $c = $this->completer($client);
        $c->setDeadline(microtime(true) + 30);

        try {
            $c->complete('s', 'p');
            $this->fail('It waited past the deadline.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('unclassified', $e->getMessage());
            $this->assertStringNotContainsString('rate limit', strtolower($e->getMessage()));
            $this->assertSame('budget', GenerationProgress::classifyError($e->getMessage())['kind']);
        }
    }

    public function test_a_plain_deadline_message_is_unchanged(): void
    {
        $client = \Mockery::mock(GeminiClient::class);
        $client->shouldReceive('chat')->once()->andThrow(new \Illuminate\Http\Client\ConnectionException('timed out'));
        $c = $this->completer($client);
        $c->setDeadline(microtime(true) + 4);

        try {
            $c->complete('s', 'p');
            $this->fail('It waited past the deadline.');
        } catch (\RuntimeException $e) {
            $this->assertSame('The time allowed for this generation ran out while waiting to ask the model again.', $e->getMessage());
            $this->assertSame('budget', GenerationProgress::classifyError($e->getMessage())['kind']);
        }
    }

    public function test_rate_limit_retries_are_bounded_and_the_wait_is_capped(): void
    {
        $client = \Mockery::mock(GeminiClient::class);
        $client->shouldReceive('chat')->times(4)->andThrow($this->limit('x_PerMinute_y', 500));
        $slept = [];
        $c = $this->completer($client, $slept);

        try {
            $c->complete('s', 'p');
            $this->fail('It retried for ever.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Gemini could not answer', $e->getMessage());
        }
        $this->assertSame(4, $c->calls, 'four attempts, no more');
        $this->assertSame([65, 65, 65], $slept, 'a provider asking for 500 s is waited for at most 65 s');
    }

    public function test_a_daily_quota_is_never_retried(): void
    {
        $client = \Mockery::mock(GeminiClient::class);
        $client->shouldReceive('chat')->once()->andThrow($this->limit('x_PerDay_y', 30));
        $slept = [];
        $c = $this->completer($client, $slept);

        $this->expectException(\RuntimeException::class);
        try {
            $c->complete('s', 'p');
        } finally {
            $this->assertSame([], $slept);
        }
    }

    public function test_a_429_is_logged_without_secrets(): void
    {
        Log::spy();
        $client = \Mockery::mock(GeminiClient::class);
        $client->shouldReceive('chat')->once()->andThrow($this->limit('x_PerMinute_y', 7));
        $client->shouldReceive('chat')->once()->andReturn('{"ok":1}');
        $c = $this->completer($client);
        $c->setDeadline(microtime(true) + 120);

        $this->assertSame('{"ok":1}', $c->complete('secret system', 'secret prompt'));
        Log::shouldHaveReceived('warning')->once()->withArgs(function ($message, $context) {
            $flat = json_encode($context);

            return $message === '[gemini] 429' && $context['kind'] === 'rate_limit' && $context['retry_after'] === 7
                && !str_contains($flat, 'secret') && !str_contains(strtolower($flat), 'key') && !str_contains(strtolower($flat), 'prompt');
        });
    }
}
