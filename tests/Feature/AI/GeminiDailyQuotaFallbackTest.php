<?php

namespace Tests\Feature\AI;

use App\Domain\AI\Support\GeminiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A free-tier DAILY quota is per model, so one model running out must not take every AI button down.
 *
 * HTTP is faked: nothing here reaches Google or spends quota.
 */
class GeminiDailyQuotaFallbackTest extends TestCase
{
    private function ok(string $text): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $text]]]]]];
    }

    private function dailyQuota(): array
    {
        return ['error' => ['code' => 429, 'details' => [['violations' => [
            ['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel-FreeTier'],
        ]]]]];
    }

    private function client(): GeminiClient
    {
        // Nothing may reach the real provider: any model not named in a test's fake is out of quota, and a stray
        // request is an error rather than a real call that spends the school's allowance.
        Http::preventStrayRequests();
        config(['ai.provider.gemini.fallback_models' => ['fallback-model']]);

        return app(GeminiClient::class)->forInstitute(1);
    }

    public function test_a_daily_quota_429_is_answered_by_the_next_model(): void
    {
        Http::fake([
            '*models/exhausted-model:*' => Http::response($this->dailyQuota(), 429),
            '*models/fallback-model:*' => Http::response($this->ok('from the fallback'), 200),
            '*' => Http::response($this->dailyQuota(), 429),
        ]);

        $this->assertSame('from the fallback', $this->client()->chat([['role' => 'user', 'content' => 'hi']], 'exhausted-model'));
    }

    public function test_a_per_minute_429_is_not_retried_on_another_model(): void
    {
        Http::fake([
            '*models/busy-model:*' => Http::response(['error' => ['details' => [['violations' => [
                ['quotaId' => 'GenerateRequestsPerMinutePerProjectPerModel-FreeTier'],
            ]]]]], 429),
            '*models/fallback-model:*' => Http::response($this->ok('should not be reached'), 200),
            '*' => Http::response(['error' => ['code' => 429]], 429),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('rate limit');

        try {
            $this->client()->chat([['role' => 'user', 'content' => 'hi']], 'busy-model');
        } finally {
            Http::assertNotSent(fn ($request) => str_contains($request->url(), 'fallback-model'));
        }
    }

    public function test_when_every_model_is_out_the_original_429_is_reported(): void
    {
        Http::fake(['*' => Http::response($this->dailyQuota(), 429)]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('rate limit');

        $this->client()->chat([['role' => 'user', 'content' => 'hi']], 'exhausted-model');
    }
}
