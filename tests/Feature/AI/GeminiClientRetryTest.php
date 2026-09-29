<?php

namespace Tests\Feature\AI;

use App\Domain\AI\Support\GeminiClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;
use Tests\TestCase;

/**
 * What happens when the model is busy, and what the person who pressed the button sees.
 *
 * Gemini answers 503 "This model is currently experiencing high demand" intermittently
 * on the popular flash models. Two things were wrong with how that landed:
 *
 *   1. The retry waited a flat 750ms three times, so all three attempts fell inside a
 *      second and a half. A capacity spike lasts seconds, so that was really one attempt
 *      with extra steps.
 *   2. Whatever survived was reported as the status code plus 300 characters of the
 *      provider's own JSON — `The AI provider returned 503: {"error":{"code":503,…` —
 *      in front of somebody using the Fees module, who can do nothing with it and has
 *      no way to tell it apart from a bug in the platform.
 *
 * Sleep is faked, so these assert the retry policy without spending the delay.
 */
class GeminiClientRetryTest extends TestCase
{
    private const ENDPOINT = 'generativelanguage.googleapis.com/*';

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
        config(['ai.provider.gemini.api_key' => 'test-key']);
    }

    public function test_a_busy_model_is_retried_and_the_answer_still_arrives(): void
    {
        Http::fake([
            self::ENDPOINT => Http::sequence()
                ->push($this->overloaded(), 503)
                ->push($this->overloaded(), 503)
                ->push(['candidates' => [['content' => ['parts' => [['text' => 'Drafted.']]]]]], 200),
        ]);

        $answer = $this->client()->chat([['role' => 'user', 'content' => 'Draft a fee reminder.']]);

        $this->assertSame('Drafted.', $answer, 'Two busy responses must not cost the turn its answer.');
        Http::assertSentCount(3);
    }

    public function test_the_wait_widens_between_attempts(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->overloaded(), 503)]);

        try {
            $this->client()->chat([['role' => 'user', 'content' => 'Draft a fee reminder.']]);
        } catch (RuntimeException) {
            // The message is the next test's subject; this one is about the waiting.
        }

        // MAX_ATTEMPTS is 3 (two waits) — lowered from 4 (five attempts total) after a
        // run where every attempt failed measured at roughly a minute end to end, long
        // enough that something in front of the call (observed on a Windows box
        // serving Laravel via `php artisan serve`, no Apache or PHP-FPM involved) was
        // cutting the connection before this class's own honest failure ever reached
        // the caller.
        Sleep::assertSleptTimes(2, 'Three attempts means two waits.');

        // The first wait is comfortably under a second; the second is comfortably
        // more than double it. The flat 750ms this replaced could produce neither —
        // that distinction, not a specific millisecond floor, is what "widening"
        // means here.
        Sleep::assertSlept(
            static fn ($duration) => $duration->totalMilliseconds < 1000,
            1
        );
        Sleep::assertSlept(
            static fn ($duration) => $duration->totalMilliseconds > 1500,
            1
        );
    }

    public function test_a_busy_model_is_reported_in_words_rather_than_provider_json(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->overloaded(), 503)]);

        try {
            $this->client()->chat([['role' => 'user', 'content' => 'Draft a fee reminder.']]);
            $this->fail('An exhausted retry must still raise.');
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();

            $this->assertStringContainsString('busy', $message);
            $this->assertStringContainsString('try again in a moment', $message);
            $this->assertStringNotContainsString(
                'The AI provider returned 503:',
                $message,
                'A status code and a blob of JSON is not something a user can act on.'
            );
        }
    }

    public function test_a_rate_limit_is_not_retried(): void
    {
        // Retrying a 429 on a short delay is what causes the next one. It surfaces at
        // once so the caller degrades a single time rather than hammering the provider.
        Http::fake([self::ENDPOINT => Http::response(['error' => ['message' => 'Quota exceeded']], 429)]);

        try {
            $this->client()->chat([['role' => 'user', 'content' => 'Draft a fee reminder.']]);
            $this->fail('A rate limit must raise.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('rate limit', $exception->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_a_rejected_credential_names_the_screen_that_fixes_it(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['error' => ['message' => 'User not found.']], 401)]);

        try {
            $this->client()->chat([['role' => 'user', 'content' => 'Draft a fee reminder.']]);
            $this->fail('A rejected credential must raise.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('AI Providers', $exception->getMessage());
        }

        Http::assertSentCount(1, 'A bad key is not a transient fault.');
    }

    // ---------------------------------------------------------------- helpers

    private function client(): GeminiClient
    {
        return app(GeminiClient::class);
    }

    /**
     * The provider's own wording, verbatim — this is the body that reached a user.
     *
     * @return array<string, mixed>
     */
    private function overloaded(): array
    {
        return [
            'error' => [
                'code' => 503,
                'message' => 'This model is currently experiencing high demand. Spikes in demand are '
                    . 'usually temporary. Please try again later.',
                'status' => 'UNAVAILABLE',
            ],
        ];
    }
}
