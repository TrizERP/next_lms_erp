<?php

namespace Tests\Feature\CoursePresentation;

use App\Domain\AI\Support\GeminiClient;
use App\Services\CoursePresentation\GenerationProgress;
use App\Services\StudyDeck\ConceptContextBuilder;
use App\Services\StudyDeck\Contracts\Completer;
use App\Services\StudyDeck\Contracts\DeadlineAware;
use App\Services\StudyDeck\Contracts\ParallelCompleter;
use App\Services\StudyDeck\LearningPlanBuilder;
use App\Services\StudyDeck\QuestionSelector;
use App\Services\StudyDeck\SlideContentGenerator;
use App\Services\StudyDeck\SlidePlanner;
use App\Services\StudyDeck\Story\GeminiStoryCompleter;
use Tests\Unit\StudyDeck\StudyDeckFixture;

/**
 * The time budget of a slides run: independent model calls are asked together, no call starts, waits or retries past the deadline, a
 * repair round that cannot finish is not started, and a run says where its time went. No model is called: the clients are fakes.
 * These tests prove the structure (how many round trips, what is skipped); they are NOT a measurement of how fast a real model answers.
 */
class TimeBudgetTest extends CoursePresentationTestCase
{
    use StudyDeckFixture;

    /** @return array<int,array<string,mixed>> plan slides keyed by slide number */
    private function planSlides(): array
    {
        $by = [];
        foreach ($this->plan()['slides'] as $i => $s) {
            $by[$i + 1] = $s + ['n' => $i + 1];
        }

        return $by;
    }

    private function contentFor(array $numbers): string
    {
        return json_encode(['slides' => array_values(array_filter($this->slideText()['slides'], fn ($s) => in_array($s['n'], $numbers, true)))]);
    }

    public function test_slide_text_chunks_are_asked_in_one_parallel_round_trip(): void
    {
        $completer = new class($this) implements ParallelCompleter {
            public int $batches = 0;
            public int $single = 0;
            public array $sizes = [];

            public function __construct(private TimeBudgetTest $t)
            {
            }

            public function complete(string $system, string $prompt, int $maxTokens = 16000): string
            {
                $this->single++;

                return '{}';
            }

            public function completeMany(string $system, array $prompts, int $maxTokens = 16000): array
            {
                $this->batches++;
                $this->sizes[] = count($prompts);
                $chunks = [[1, 2], [3, 4], [5, 6], [7]];

                return array_map(fn ($i) => $this->t->reply($chunks[$i]), array_keys($prompts));
            }
        };

        $plan = $this->plan();
        $raw = $this->raw();
        $context = (new ConceptContextBuilder())->assemble($raw);
        $sel = (new QuestionSelector())->select($raw['questions']);
        $map = (new LearningPlanBuilder())->build($context, $sel['eligible'], 6, 8);
        $plan['slides'] = array_values(array_map(fn ($s, $i) => $s + ['n' => $i + 1], $plan['slides'], array_keys($plan['slides'])));

        $content = (new SlideContentGenerator($completer, 2))->generate($context, $map, $plan);

        $this->assertSame(1, $completer->batches, 'four chunks, one round trip');
        $this->assertSame([4], $completer->sizes);
        $this->assertSame(0, $completer->single, 'nothing had to be asked again');
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], array_keys($content));
    }

    public function test_a_chunk_that_came_back_empty_is_asked_again_alone_and_the_rest_are_kept(): void
    {
        $completer = new class($this) implements ParallelCompleter {
            public int $single = 0;

            public function __construct(private TimeBudgetTest $t)
            {
            }

            public function complete(string $system, string $prompt, int $maxTokens = 16000): string
            {
                $this->single++;

                return $this->t->reply([3, 4]);
            }

            public function completeMany(string $system, array $prompts, int $maxTokens = 16000): array
            {
                $chunks = [[1, 2], [3, 4], [5, 6], [7]];

                return array_map(fn ($i) => $i === 1 ? '{}' : $this->t->reply($chunks[$i]), array_keys($prompts));
            }
        };
        $plan = $this->plan();
        $raw = $this->raw();
        $context = (new ConceptContextBuilder())->assemble($raw);
        $sel = (new QuestionSelector())->select($raw['questions']);
        $map = (new LearningPlanBuilder())->build($context, $sel['eligible'], 6, 8);
        $plan['slides'] = array_values(array_map(fn ($s, $i) => $s + ['n' => $i + 1], $plan['slides'], array_keys($plan['slides'])));

        $content = (new SlideContentGenerator($completer, 2))->generate($context, $map, $plan);

        $this->assertSame(1, $completer->single, 'only the empty chunk was asked again');
        $this->assertCount(7, $content);
    }

    /** Public only so the anonymous completers above can use it. */
    public function reply(array $numbers): string
    {
        return $this->contentFor($numbers);
    }

    // ---------------------------------------------------------------- deadline

    private function gemini(\Mockery\MockInterface $client, ?\Closure $sleep = null): GeminiStoryCompleter
    {
        return new GeminiStoryCompleter($client, $sleep ?? function (int $s): void {
            $this->fail("slept $s s");
        });
    }

    public function test_no_model_call_starts_after_the_deadline(): void
    {
        $client = \Mockery::mock(GeminiClient::class);
        $client->shouldReceive('chat')->never();
        $c = $this->gemini($client);
        $c->setDeadline(microtime(true) - 1);

        $this->expectExceptionMessage('time allowed for this generation ran out');
        $c->complete('s', 'p');
    }

    public function test_a_retry_wait_that_would_end_after_the_deadline_is_not_made(): void
    {
        $client = \Mockery::mock(GeminiClient::class);
        $client->shouldReceive('chat')->once()->andThrow(new \Illuminate\Http\Client\ConnectionException('timed out'));
        $c = $this->gemini($client);
        $c->setDeadline(microtime(true) + 3.5);   // enough to ask once, but a pause needs more than the 3 s margin the rest of the run keeps

        try {
            $c->complete('s', 'p');
            $this->fail('It waited past the deadline.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('ran out while waiting', $e->getMessage());
        }
        $this->assertSame(1, $c->calls, 'one attempt: there was no room to wait for another');
    }

    public function test_the_request_timeout_is_never_longer_than_the_time_left(): void
    {
        $client = \Mockery::mock(GeminiClient::class);
        $client->shouldReceive('chat')->once()->withArgs(fn ($m, $model, $max, $temp, $json, $timeout) => $timeout <= 31 && $timeout >= 5)->andReturn('{"ok":1}');
        $c = $this->gemini($client);
        $c->setDeadline(microtime(true) + 30);

        $this->assertSame('{"ok":1}', $c->complete('s', 'p'));
    }

    public function test_parallel_answers_are_kept_and_only_the_slot_that_failed_is_asked_again(): void
    {
        $client = \Mockery::mock(GeminiClient::class);
        $client->shouldReceive('chatMany')->once()->andReturn([0 => 'a', 1 => new \RuntimeException('overloaded'), 2 => 'c']);
        $client->shouldReceive('chat')->once()->andReturn('b');
        $c = $this->gemini($client);

        $this->assertSame([0 => 'a', 1 => 'b', 2 => 'c'], $c->completeMany('s', ['p0', 'p1', 'p2']));
        $this->assertSame(4, $c->calls, 'three in parallel plus one asked again');
    }

    public function test_the_real_client_sends_the_requests_together_and_reports_each_slot_on_its_own(): void
    {
        config(['ai.provider.gemini.base_url' => 'https://gemini.test/v1beta']);
        // The key pool is a database table; the test gives the client a key that is plainly not real instead of reading it.
        $this->app->instance(\App\Domain\AI\Support\ProviderKeyResolver::class, new class extends \App\Domain\AI\Support\ProviderKeyResolver {
            public function __construct()
            {
            }

            public function resolve(string $apiType, int|string|null $subInstituteId = null, ?string $envFallback = null): ?array
            {
                return ['api_key' => 'test-key-not-real', 'id' => 0];
            }
        });
        $ok = fn (string $t) => \Illuminate\Support\Facades\Http::response(['candidates' => [['content' => ['parts' => [['text' => $t]]]]]], 200);
        $seen = [];
        \Illuminate\Support\Facades\Http::fake(function ($request) use (&$seen, $ok) {
            $body = $request->data();
            $seen[] = $body['contents'][0]['parts'][0]['text'] ?? '';
            $prompt = end($seen);

            return match ($prompt) {
                'p1' => \Illuminate\Support\Facades\Http::response(['error' => ['message' => 'busy']], 503),
                'p2' => \Illuminate\Support\Facades\Http::response(['candidates' => []], 200),
                default => $ok('answer ' . $prompt),
            };
        });
        $client = app(GeminiClient::class);
        $messages = fn (string $p) => [['role' => 'system', 'content' => 's'], ['role' => 'user', 'content' => $p]];

        $out = $client->chatMany([
            ['messages' => $messages('p0'), 'max_tokens' => 100, 'expect_json' => true],
            ['messages' => $messages('p1')],
            ['messages' => $messages('p2')],
            ['messages' => $messages('p3')],
        ], 30);

        $this->assertSame('answer p0', $out[0]);
        $this->assertInstanceOf(\Throwable::class, $out[1], 'a provider error is the slot\'s reason, not an exception for the whole batch');
        $this->assertInstanceOf(\Throwable::class, $out[2], 'an empty answer is a failed slot');
        $this->assertSame('answer p3', $out[3]);
        $this->assertCount(4, $seen, 'one request per slot, none repeated by the batch itself');
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => $r->hasHeader('x-goog-api-key') && str_contains($r->url(), 'generateContent'));
    }

    public function test_a_plan_repair_round_that_cannot_finish_is_not_started(): void
    {
        $bad = $this->plan();
        $bad['slides'][4]['taught_concept_ids'] = [];
        $c = new class(json_encode($bad)) implements Completer, DeadlineAware {
            public int $calls = 0;
            private ?float $until = null;

            public function __construct(private string $reply)
            {
            }

            public function complete(string $system, string $prompt, int $maxTokens = 16000): string
            {
                $this->calls++;

                return $this->reply;
            }

            public function setDeadline(?float $unixTime): void
            {
                $this->until = $unixTime;
            }

            public function deadline(): ?float
            {
                return $this->until;
            }
        };
        $c->setDeadline(microtime(true) + 10);   // under the 25 s a repair round needs
        $raw = $this->raw();
        $context = (new ConceptContextBuilder())->assemble($raw);
        $sel = (new QuestionSelector())->select($raw['questions']);
        $map = (new LearningPlanBuilder())->build($context, $sel['eligible'], 6, 8);

        try {
            (new SlidePlanner($c))->plan($context, $map, $sel['eligible']);
            $this->fail('An invalid plan was accepted.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('no time left for another repair attempt', $e->getMessage());
        }
        $this->assertSame(1, $c->calls, 'the first plan was asked for; no repair round was started');
        $this->assertSame('budget', GenerationProgress::classifyError($e->getMessage())['kind']);
    }

    // ---------------------------------------------------------------- where the time went

    public function test_timings_split_a_run_into_queue_wait_stages_and_total_and_say_whether_the_target_was_kept(): void
    {
        $t = 1_800_000_000;
        $status = ['state' => 'ready', 'stage' => 'ready', 'created_at' => date('c', $t), 'started_at' => date('c', $t + 12), 'finished_at' => date('c', $t + 12 + 48),
            'stage_times' => ['prepare' => date('c', $t + 12), 'content' => date('c', $t + 17), 'images' => date('c', $t + 37), 'validate' => date('c', $t + 52), 'verify' => date('c', $t + 55), 'ready' => date('c', $t + 60)]];

        $x = GenerationProgress::timings($status, 60);

        $this->assertSame(12, $x['queue_wait_seconds']);
        $this->assertSame(48, $x['total_seconds']);
        $this->assertSame([5, 20, 15, 3, 5], [$x['stages']['prepare']['seconds'], $x['stages']['content']['seconds'], $x['stages']['images']['seconds'], $x['stages']['validate']['seconds'], $x['stages']['verify']['seconds']]);
        $this->assertTrue($x['within_target']);
        $this->assertFalse(GenerationProgress::timings($status, 30)['within_target'], 'a 48 s run misses a 30 s target');

        $failed = GenerationProgress::timings(['state' => 'failed', 'stage' => 'content', 'error' => 'Gemini daily quota exhausted'] + $status, 60);
        $this->assertSame('content', $failed['failed_stage']);
        $this->assertSame('Gemini daily quota exhausted', $failed['failure']);
        $this->assertNull($failed['within_target'], 'a failed run never counts as within target');
    }
}
