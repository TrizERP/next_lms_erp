<?php

namespace Tests\Feature\CoursePresentation;

use App\Domain\AI\Support\AiAuditLogger;
use App\Domain\AI\Support\AiProviderException;
use App\Domain\AI\Support\GeminiClient;
use App\Domain\AI\Support\ProviderKeyResolver;
use App\Services\PAL\Integration\ConceptImageSearchService;
use App\Services\StudyDeck\Story\GeminiStoryCompleter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The stall of a Classroom Presentation in "Generating content": the log channel that could not be built, a provider whose daily quota was spent
 * (every parallel slot failed, then was asked again one by one, with long waits), and retries that outlasted the run's deadline.
 * No model is called: HTTP is faked, the key is plainly not real, and nothing is written to the application's log.
 */
class GenerationStallTest extends CoursePresentationTestCase
{
    // ------------------------------------------------------------------------------------------------ the logger

    public function test_the_application_has_a_default_log_channel_that_is_defined(): void
    {
        $default = config('logging.default');

        $this->assertIsString($default);
        $this->assertNotSame('', $default, 'LOG_CHANNEL is not empty');
        $this->assertArrayHasKey($default, (array) config('logging.channels'), 'the default channel exists in config/logging.php');
        foreach ((array) config('logging.channels.stack.channels') as $channel) {
            $this->assertArrayHasKey($channel, (array) config('logging.channels'), "the stack's channel \"$channel\" is defined");
        }
    }

    public function test_a_logger_that_cannot_be_built_never_breaks_a_picture_search(): void
    {
        config(['pal_content.image.external.endpoint' => 'https://openverse.test/v1/images/', 'pal_content.image.external.client_id' => '', 'pal_content.image.external.client_secret' => '']);
        Http::fake(['openverse.test/*' => Http::response(['results' => [['id' => 'a', 'url' => 'https://img.test/a.png', 'title' => 'Water cycle diagram', 'foreign_landing_url' => 'https://img.test/p/a', 'license' => 'by', 'license_version' => '4.0', 'source' => 'flickr', 'tags' => [['name' => 'water']], 'width' => 800, 'height' => 600]]], 200)]);
        // Exactly what happened: asking the log manager for its default channel raised "Log [] is not defined".
        Log::shouldReceive('info')->andThrow(new \InvalidArgumentException('Log [] is not defined.'));
        Log::shouldReceive('warning')->andThrow(new \InvalidArgumentException('Log [] is not defined.'));

        $service = app(ConceptImageSearchService::class);
        $out = $service->rankedMany(['water cycle'], 6, 0.0, '', 5, 4);
        $single = $service->rankedImagesFor('water cycle diagram', 3, 0.0, '');

        $this->assertNotEmpty($out['water cycle'], 'the search still answered');
        $this->assertNotEmpty($single);
        $this->assertSame('ok', $service->searches[0]['outcome']);
    }

    public function test_the_audit_line_falls_back_to_the_stack_channel_when_the_default_is_empty_and_never_throws(): void
    {
        config(['logging.default' => '']);
        Log::shouldReceive('channel')->once()->with('stack')->andThrow(new \InvalidArgumentException('Log [stack] is not defined.'));

        $audit = (new \ReflectionClass(AiAuditLogger::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(AiAuditLogger::class, 'fallback');
        $method->invoke($audit, 'test.event', ['a' => 1]);

        $this->assertTrue(true, 'no exception reached the caller');
    }

    // ------------------------------------------------------------------------------------------------ a provider with its daily quota spent

    private function client(): GeminiClient
    {
        config(['ai.provider.gemini.model' => 'gemini-primary', 'ai.provider.gemini.fallback_models' => ['gemini-alt'], 'ai.provider.gemini.base_url' => 'https://gemini.test/v1beta']);
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

    private function perDay(): \Illuminate\Http\Client\Response|\GuzzleHttp\Promise\PromiseInterface
    {
        return Http::response(['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'quota', 'details' => [['violations' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel-FreeTier']]]]]], 429);
    }

    private function answer(string $text): \GuzzleHttp\Promise\PromiseInterface
    {
        return Http::response(['candidates' => [['content' => ['parts' => [['text' => $text]]]]]], 200);
    }

    private function request(string $p): array
    {
        return ['messages' => [['role' => 'system', 'content' => 's'], ['role' => 'user', 'content' => $p]], 'max_tokens' => 100, 'expect_json' => true];
    }

    public function test_slots_refused_for_a_spent_daily_quota_are_answered_by_the_next_model_in_the_same_parallel_round(): void
    {
        $client = $this->client();
        Http::fake(['gemini.test/v1beta/models/gemini-primary:*' => $this->perDay(), 'gemini.test/v1beta/models/gemini-alt:*' => $this->answer('{"ok":1}')]);

        $out = $client->chatMany([$this->request('p0'), $this->request('p1'), $this->request('p2')], 30);

        $this->assertSame(['{"ok":1}', '{"ok":1}', '{"ok":1}'], array_values($out), 'every slot was answered, none was left to be retried one by one');
        $urls = collect(Http::recorded())->map(fn ($pair) => $pair[0]->url())->all();
        $this->assertCount(3, array_filter($urls, fn ($u) => str_contains($u, 'gemini-primary')));
        $this->assertCount(3, array_filter($urls, fn ($u) => str_contains($u, 'gemini-alt')));
    }

    public function test_a_model_whose_daily_quota_is_spent_is_not_asked_again_by_the_next_calls(): void
    {
        $client = $this->client();
        Http::fake(['gemini.test/v1beta/models/gemini-primary:*' => $this->perDay(), 'gemini.test/v1beta/models/gemini-alt:*' => $this->answer('{"ok":1}')]);
        $client->chatMany([$this->request('p0'), $this->request('p1')], 30);
        $before = collect(Http::recorded())->map(fn ($pair) => $pair[0]->url())->all();
        $this->assertCount(2, array_filter($before, fn ($u) => str_contains($u, 'gemini-primary')), 'the first batch found the primary model spent');

        $out = $client->chatMany([$this->request('q0'), $this->request('q1')], 30);
        $single = $client->chat([['role' => 'user', 'content' => 'hello']], null, 100, null, true, 30);

        $this->assertSame(['{"ok":1}', '{"ok":1}'], array_values($out));
        $this->assertSame('{"ok":1}', $single);
        $after = collect(Http::recorded())->map(fn ($pair) => $pair[0]->url())->all();
        $this->assertCount(2, array_filter($after, fn ($u) => str_contains($u, 'gemini-primary')), 'no further request went to the spent model: no wasted round trip first');
        $this->assertCount(5, array_filter($after, fn ($u) => str_contains($u, 'gemini-alt')), '2 + 2 + 1 requests all went to the model that works');
    }

    public function test_when_every_model_is_spent_the_slots_fail_at_once_with_the_quota_reason_and_nothing_waits(): void
    {
        $client = $this->client();
        Http::fake(['gemini.test/*' => $this->perDay()]);
        $sleeps = [];
        $completer = new GeminiStoryCompleter($client, function (int $s) use (&$sleeps): void {
            $sleeps[] = $s;
        });
        $completer->setDeadline(microtime(true) + 120);

        try {
            $completer->completeMany('s', ['p0', 'p1']);
            $this->fail('A spent quota produced an answer.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('quota', strtolower($e->getMessage()));
        }
        $this->assertSame([], $sleeps, 'a daily quota does not clear by waiting, so nothing was waited for');
    }

    // ------------------------------------------------------------------------------------------------ discard

    public function test_discarding_a_run_twice_is_the_same_as_once_and_a_stored_or_unknown_run_still_cannot_be_discarded(): void
    {
        $runs = app(\App\Services\CoursePresentation\GenerationRun::class);
        $status = $runs->request(self::CHAPTER, self::TENANT, 55, 'inquiry_based', 'interactive_slides');
        $status['state'] = 'failed';
        $runs->save($status);

        $first = $runs->discard(self::CHAPTER, self::TENANT, $status['run']);
        $second = $runs->discard(self::CHAPTER, self::TENANT, $status['run']);   // a stale screen clicks again

        $this->assertSame('discarded', $first['state']);
        $this->assertSame('discarded', $second['state'], 'a second discard is not an error');

        $stored = $runs->request(self::CHAPTER, self::TENANT, 55, 'inquiry_based', 'interactive_slides');
        $stored['state'] = 'stored';
        $runs->save($stored);
        $this->assertNull($runs->discard(self::CHAPTER, self::TENANT, $stored['run']), 'a stored presentation is never discarded');
        $this->assertNull($runs->discard(self::CHAPTER, self::TENANT, 'r000000000000'), 'an unknown run is refused');
        $this->assertNull($runs->discard(self::CHAPTER, self::OTHER_TENANT, $status['run']), 'another school cannot discard it');
    }
}
