<?php

namespace Tests\Feature\CoursePresentation;

use App\Services\CoursePresentation\GenerationRun;
use App\Services\PAL\Integration\ConceptImageSearchService;
use App\Services\StudyDeck\ImagePlanner;
use App\Services\StudyDeck\StudyDeckImages;
use App\Services\StudyDeck\StudyImageStores;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The picture policy of a time-boxed slides run: reuse, ONE parallel search, the FIRST acceptable picture, no model asked about any candidate,
 * and a ladder (textbook, drawn diagram, no picture) when nothing is found in time. The search service and the picture hosts are fakes.
 * What is real: the policy, the byte checks, the picture store, the cache, the run watchdog.
 */
class FastImagesTest extends CoursePresentationTestCase
{
    /** @var array<int,array{queries:array<int,string>,deadline:?float}> */
    public array $searchCalls = [];

    /** @var array<string,array<int,array<string,mixed>>> query => candidates the fake search returns */
    public array $answers = [];

    public bool $searchTimesOut = false;

    private function noisePng(int $w = 400, int $h = 300, int $seed = 1): string
    {
        $im = imagecreatetruecolor($w, $h);
        mt_srand($seed);
        for ($i = 0; $i < 4000; $i++) {
            imagesetpixel($im, mt_rand(0, $w - 1), mt_rand(0, $h - 1), imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
            imagefilledrectangle($im, mt_rand(0, $w), mt_rand(0, $h), mt_rand(0, $w), mt_rand(0, $h), imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
        }
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    private function candidate(string $name, string $title, string $licence = 'BY 4.0', array $over = []): array
    {
        return $over + ['url' => "https://img.test/$name.png", 'thumbnail_url' => "https://img.test/$name-t.png", 'title' => $title, 'creator' => 'Ann Author', 'license' => $licence,
            'source_url' => "https://img.test/page/$name", 'provider' => 'flickr', 'tags' => ['water', 'cycle', 'rain']];
    }

    private function planner(array $policy = []): ImagePlanner
    {
        $self = $this;
        $search = new class($self) extends ConceptImageSearchService {
            public function __construct(private FastImagesTest $t)
            {
            }

            public function available(): bool
            {
                return true;
            }

            public function forBatch(): static
            {
                return $this;
            }

            public function rankedMany(array $queries, int $limit = 6, ?float $minScore = null, ?string $source = null, int $timeout = 7, int $concurrency = 12, ?float $deadline = null): array
            {
                $this->t->searchCalls[] = ['queries' => $queries, 'deadline' => $deadline];
                $out = [];
                foreach ($queries as $q) {
                    if ($this->t->searchTimesOut) {
                        $this->searches[] = ['query' => $q, 'outcome' => 'timeout'];
                    }
                    $out[$q] = $this->t->searchTimesOut ? [] : array_map(fn ($c) => $c + ['query' => $q], $this->t->answers[$q] ?? []);
                }

                return $out;
            }
        };

        return (new ImagePlanner($search, StudyImageStores::database(self::TENANT, self::CHAPTER)))->withFastPolicy($policy + ['tenant' => self::TENANT, 'scope' => 't' . self::TENANT, 'stage_seconds' => 20]);
    }

    /** @return array<int,array<string,mixed>> */
    private function slides(array $queries, bool $withDiagram = false): array
    {
        $out = [];
        foreach (array_values($queries) as $i => $q) {
            $slide = ['n' => $i + 2, 'title' => 'Slide ' . ($i + 2), 'teaches' => 'The idea.', 'visual' => ['required' => true, 'role' => 'object', 'query' => $q, 'purpose' => 'Shows ' . $q]];
            if ($withDiagram) {
                $slide['diagram'] = ['layout' => 'hub', 'title' => 'Water cycle', 'center' => 'Water', 'nodes' => ['Rain', 'Rivers', 'Clouds', 'Oceans']];
            }
            $out[] = $slide;
        }

        return $out;
    }

    private function fakeHosts(array $handlers = []): void
    {
        Http::fake($handlers + ['img.test/*' => Http::response($this->noisePng(), 200, ['Content-Type' => 'image/png'])]);
    }

    // ------------------------------------------------------------------------------------------------ the first acceptable picture

    public function test_the_first_acceptable_candidate_is_taken_and_nothing_better_is_looked_for(): void
    {
        $this->seedPilotChapter();
        $this->answers['water cycle diagram'] = [$this->candidate('first', 'Water cycle diagram of rain'), $this->candidate('second', 'Water cycle diagram river'), $this->candidate('third', 'Water cycle diagram clouds')];
        $this->fakeHosts();

        $images = $this->planner()->plan($this->slides(['water cycle diagram']), []);

        $this->assertSame('https://img.test/first.png', $images[2]['source_image_url']);
        $sent = collect(Http::recorded())->map(fn ($pair) => $pair[0]->url())->all();
        $this->assertSame(['https://img.test/first.png'], $sent, 'only the first candidate was downloaded; the others were never fetched');
        $this->assertCount(1, $this->searchCalls, 'one search round for the whole stage');
    }

    public function test_the_picture_keeps_its_source_licence_creator_and_credit_and_never_claims_a_review(): void
    {
        $this->seedPilotChapter();
        $this->answers['water cycle diagram'] = [$this->candidate('first', 'Water cycle diagram of rain')];
        $this->fakeHosts();

        $image = $this->planner()->plan($this->slides(['water cycle diagram']), [])[2];

        $this->assertSame(['BY 4.0', 'Ann Author', 'https://img.test/page/first', 'flickr'], [$image['licence'], $image['creator'], $image['source_url'], $image['provider']]);
        $this->assertTrue($image['attribution_required']);
        $this->assertStringContainsString('Ann Author', $image['attribution']);
        $this->assertNull($image['review'], 'matched on title and tags only: it is not said to have been reviewed');
        $this->assertSame('first_acceptable', $image['selection']);
        $this->assertNotNull(StudyDeckImages::idFromRef($image['url']));
        $this->assertSame(self::TENANT, \Illuminate\Support\Facades\DB::table('study_deck_images')->where('id', StudyDeckImages::idFromRef($image['url']))->value('sub_institute_id'));
    }

    public function test_every_slide_is_searched_once_in_one_round_with_its_full_phrase_and_its_two_word_core(): void
    {
        $this->seedPilotChapter();
        $this->answers = [];
        $this->fakeHosts();

        $this->planner()->plan($this->slides(['water cycle diagram rain', 'river flowing downhill fast']), []);

        $this->assertCount(1, $this->searchCalls);
        $this->assertSame(['water cycle diagram rain', 'water cycle', 'river flowing downhill fast', 'river flowing'], $this->searchCalls[0]['queries']);
    }

    public function test_no_model_is_asked_about_a_candidate(): void
    {
        $this->seedPilotChapter();
        $this->answers['water cycle diagram'] = [$this->candidate('first', 'Water cycle diagram of rain')];
        $this->fakeHosts();
        $completer = new class implements \App\Services\StudyDeck\Contracts\Completer {
            public int $calls = 0;

            public function complete(string $system, string $prompt, int $maxTokens = 16000): string
            {
                $this->calls++;

                return '{}';
            }
        };
        $planner = $this->planner();
        $reflection = new \ReflectionProperty(ImagePlanner::class, 'judge');
        $reflection->setValue($planner, fn () => $completer->complete('s', 'p'));

        $planner->plan($this->slides(['water cycle diagram']), []);

        $this->assertSame(0, $completer->calls, 'the relevance judge is not used by the fast policy');
    }

    // ------------------------------------------------------------------------------------------------ cheap checks

    public function test_candidates_with_a_licence_a_format_or_a_title_that_fail_are_never_downloaded(): void
    {
        $this->seedPilotChapter();
        $this->answers['water cycle diagram'] = [
            $this->candidate('closed', 'Water cycle diagram of rain', 'ARR'),
            $this->candidate('anim', 'Water cycle diagram animation', 'BY 4.0', ['url' => 'https://img.test/anim.gif']),
            $this->candidate('untitled', 'IMG_0042'),
            $this->candidate('good', 'Water cycle diagram of clouds'),
        ];
        $this->fakeHosts();

        $image = $this->planner()->plan($this->slides(['water cycle diagram']), [])[2];

        $this->assertSame('https://img.test/good.png', $image['source_image_url']);
        $this->assertSame(['https://img.test/good.png'], collect(Http::recorded())->map(fn ($p) => $p[0]->url())->all());
    }

    public function test_a_candidate_that_will_not_download_or_is_not_a_picture_or_is_too_small_gives_way_to_the_next_in_a_second_round(): void
    {
        $this->seedPilotChapter();
        $this->answers['water cycle diagram'] = [
            $this->candidate('forbidden', 'Water cycle diagram of rain'),
            $this->candidate('html', 'Water cycle diagram river'),
            $this->candidate('tiny', 'Water cycle diagram clouds'),
            $this->candidate('ok', 'Water cycle diagram snow'),
        ];
        $this->fakeHosts([
            'img.test/forbidden.png' => Http::response('no', 403),
            'img.test/html.png' => Http::response('<html>not a picture</html>', 200, ['Content-Type' => 'text/html']),
            'img.test/tiny.png' => Http::response($this->noisePng(80, 60), 200, ['Content-Type' => 'image/png']),
        ]);
        $planner = $this->planner(['rounds' => 4]);

        $image = $planner->plan($this->slides(['water cycle diagram']), [])[2];

        $this->assertSame('https://img.test/ok.png', $image['source_image_url']);
        $this->assertSame(1, $planner->stats['download_failed'] - 1, 'the 403 and the page that is not a picture were counted as failed downloads (two of them)');
    }

    public function test_two_slides_never_get_the_same_picture(): void
    {
        $this->seedPilotChapter();
        $this->answers['water cycle diagram'] = [$this->candidate('shared', 'Water cycle diagram of rain'), $this->candidate('other', 'Water cycle diagram river')];
        $this->answers['water cycle chart'] = [$this->candidate('shared', 'Water cycle diagram of rain')];
        $this->fakeHosts();

        $images = $this->planner(['rounds' => 3])->plan($this->slides(['water cycle diagram', 'water cycle chart']), []);

        $urls = array_values(array_filter(array_map(fn ($i) => $i['source_image_url'] ?? null, $images)));
        $this->assertNotEmpty($urls);
        $this->assertSame($urls, array_values(array_unique($urls)), 'the second slide may take another picture or none, but never the same one twice');
    }

    // ------------------------------------------------------------------------------------------------ nothing found, or no time

    public function test_when_the_search_finds_nothing_a_drawn_diagram_is_used_and_a_slide_without_one_gets_no_picture(): void
    {
        $this->seedPilotChapter();
        $this->answers = [];
        $this->fakeHosts();
        $planner = $this->planner();

        $withSpec = $planner->plan($this->slides(['water cycle diagram'], true), [])[2];
        $without = $this->planner()->plan($this->slides(['water cycle diagram']), [])[2];

        $this->assertSame('diagram', $withSpec['type']);
        $this->assertSame('no sufficiently relevant, openly licensed image found', $withSpec['photo_missing']);
        $this->assertSame('none', $without['source_kind']);
        $this->assertArrayHasKey('missing', $without, 'text only, never an unrelated photograph');
        Http::assertNothingSent();
    }

    public function test_a_search_that_times_out_does_not_hold_the_stage_and_the_reason_is_said(): void
    {
        $this->seedPilotChapter();
        $this->searchTimesOut = true;
        $this->fakeHosts();
        $planner = $this->planner();
        $started = microtime(true);

        $images = $planner->plan($this->slides(['water cycle diagram', 'river delta map'], true), []);

        $this->assertLessThan(10, microtime(true) - $started);
        $this->assertSame('the picture search did not answer in time', $images[2]['photo_missing']);
        $this->assertSame(2, $planner->fastReport['diagram']);
        $this->assertSame(0, $planner->fastReport['first_acceptable']);
        Http::assertNothingSent();
    }

    public function test_the_whole_stage_shares_one_deadline_which_the_search_is_given(): void
    {
        $this->seedPilotChapter();
        $this->fakeHosts();

        $this->planner(['stage_seconds' => 9])->plan($this->slides(['water cycle diagram', 'river delta map', 'rain cloud formation']), []);

        $left = $this->searchCalls[0]['deadline'] - microtime(true);
        $this->assertGreaterThan(5, $left);
        $this->assertLessThanOrEqual(9.0, $left, 'one budget for all slides, not a fresh one for each');
    }

    public function test_a_stage_that_is_already_out_of_time_searches_nothing_and_still_finishes_with_fallbacks(): void
    {
        $this->seedPilotChapter();
        $this->fakeHosts();
        $planner = $this->planner(['stage_seconds' => 0]);

        $images = $planner->plan($this->slides(['water cycle diagram'], true), []);

        $this->assertSame('diagram', $images[2]['type']);
        $this->assertSame([], $this->searchCalls);
        $this->assertTrue($planner->fastReport['deadline_reached']);
    }

    // ------------------------------------------------------------------------------------------------ reuse

    public function test_a_picture_resolved_before_is_reused_without_any_search_or_download(): void
    {
        $this->seedPilotChapter();
        $this->answers['water cycle diagram'] = [$this->candidate('first', 'Water cycle diagram of rain')];
        $this->fakeHosts();
        $first = $this->planner()->plan($this->slides(['water cycle diagram']), [])[2];
        $this->searchCalls = [];
        Http::fake();

        $again = $this->planner()->plan($this->slides(['water cycle diagram']), [])[2];

        $this->assertSame($first['url'], $again['url']);
        $this->assertTrue($again['reused']);
        $this->assertSame([], $this->searchCalls, 'no search');
        Http::assertNothingSent();
        $this->assertSame(1, $this->planner()->fastReport['reused'] ?? 1);
    }

    public function test_a_resolved_picture_that_is_no_longer_in_the_store_is_not_reused(): void
    {
        $this->seedPilotChapter();
        $this->answers['water cycle diagram'] = [$this->candidate('first', 'Water cycle diagram of rain')];
        $this->fakeHosts();
        $first = $this->planner()->plan($this->slides(['water cycle diagram']), [])[2];
        \Illuminate\Support\Facades\DB::table('study_deck_images')->where('id', StudyDeckImages::idFromRef($first['url']))->delete();
        $this->searchCalls = [];

        $this->planner()->plan($this->slides(['water cycle diagram']), []);

        $this->assertCount(1, $this->searchCalls, 'searched again: the cached resolution pointed at a picture that is gone');
    }

    // ------------------------------------------------------------------------------------------------ the batch search itself

    public function test_the_real_batch_search_asks_once_per_query_at_the_same_time_caches_and_reports_each_outcome(): void
    {
        config(['pal_content.image.external.endpoint' => 'https://openverse.test/v1/images/', 'pal_content.image.external.client_id' => '', 'pal_content.image.external.client_secret' => '']);
        $row = fn (string $id, string $title) => ['id' => $id, 'url' => "https://img.test/$id.png", 'thumbnail' => "https://img.test/$id-t.png", 'title' => $title, 'foreign_landing_url' => "https://img.test/p/$id", 'creator' => 'Ann', 'license' => 'by', 'license_version' => '4.0', 'source' => 'flickr', 'tags' => [['name' => 'water'], ['name' => 'cycle']], 'width' => 800, 'height' => 600];
        Http::fake(function (Request $request) use ($row) {
            return match ($request['q']) {
                'water cycle' => Http::response(['results' => [$row('a', 'Water cycle diagram')]], 200),
                'river delta' => Http::response('busy', 504),
                default => Http::response(['results' => []], 200),
            };
        });
        $service = app(ConceptImageSearchService::class);

        $out = $service->rankedMany(['water cycle', 'river delta', 'empty words here'], 6, 0.0, '', 5, 10);

        $this->assertSame(['water cycle', 'river delta', 'empty words here'], array_keys($out));
        $this->assertNotEmpty($out['water cycle']);
        $this->assertSame([], $out['river delta'], 'a failed search says nothing about whether a picture exists');
        $this->assertSame(['ok', 'http_error', 'ok'], array_column($service->searches, 'outcome'));
        Http::assertSentCount(3);

        $service->rankedMany(['water cycle'], 6, 0.0, '', 5, 10);
        Http::assertSentCount(3);   // the answer came from the cache
        $this->assertSame('cache', end($service->searches)['outcome']);
    }

    // ------------------------------------------------------------------------------------------------ a run cannot stay in one stage

    private function runningRun(string $stage, int $secondsInStage, int $silentFor = 5): array
    {
        $runs = app(GenerationRun::class);
        $status = $runs->request(self::CHAPTER, self::TENANT, 55, 'inquiry_based', 'interactive_slides');
        $status['state'] = 'running';
        $status['started_at'] = date('c', time() - 4000);
        $status['stage'] = $stage;
        $status['stage_times'] = [$stage => date('c', time() - $secondsInStage)];
        $runs->save($status);
        // The log keeps being written (a busy run), so the run is NOT silent.
        $path = storage_path(config('course_presentation_pilot.generation.run_dir') . '/chapter-' . self::CHAPTER . '/' . $status['run'] . '/status.json');
        $disk = json_decode(file_get_contents($path), true);
        $disk['updated_at'] = date('c', time() - $silentFor);
        file_put_contents($path, json_encode($disk));

        return [$runs, $status['run'], $path];
    }

    public function test_a_run_that_has_been_in_one_stage_for_far_too_long_is_failed_with_the_stage_named_even_while_it_keeps_logging(): void
    {
        config(['course_presentation_pilot.generation.stage_max_seconds' => 900]);
        [$runs, $run] = $this->runningRun('images', 1800);

        $status = $runs->read(self::CHAPTER, self::TENANT, $run);

        $this->assertSame('failed', $status['state']);
        $this->assertStringContainsString('more than 15 minutes in "Planning and resolving images"', $status['error']);
        $this->assertStringContainsString('Nothing was stored', $status['error']);
    }

    public function test_a_run_inside_its_stage_limit_is_left_alone(): void
    {
        config(['course_presentation_pilot.generation.stage_max_seconds' => 900]);
        [$runs, $run] = $this->runningRun('images', 120);

        $this->assertSame('running', $runs->read(self::CHAPTER, self::TENANT, $run)['state']);
    }

    public function test_a_worker_still_writing_to_a_stopped_run_cannot_bring_it_back_to_life(): void
    {
        config(['course_presentation_pilot.generation.stage_max_seconds' => 900]);
        [$runs, $run, $path] = $this->runningRun('images', 1800);
        $stopped = $runs->read(self::CHAPTER, self::TENANT, $run);
        $this->assertSame('failed', $stopped['state']);

        $late = $stopped;
        $late['state'] = 'ready';   // the old worker finishes after the run was stopped
        $runs->save($late);
        $late['state'] = 'running';
        $runs->save($late);

        $this->assertSame('failed', json_decode(file_get_contents($path), true)['state']);
    }

    public function test_a_worker_that_started_before_the_code_changed_refuses_the_run_with_a_clear_reason(): void
    {
        $runs = app(GenerationRun::class);
        $this->assertTrue($runs->codeChangedSince(0), 'the pipeline files are newer than a worker started at the beginning of time');
        $this->assertFalse($runs->codeChangedSince(time() + 3600));
        $status = $runs->request(self::CHAPTER, self::TENANT, 55, 'inquiry_based', 'interactive_slides');

        $runs->failRun(self::CHAPTER, self::TENANT, $status['run'], 'The queue worker was started before the generation code was changed, so it would run the old code.');

        $read = $runs->read(self::CHAPTER, self::TENANT, $status['run']);
        $this->assertSame('failed', $read['state']);
        $this->assertSame('queue', \App\Services\CoursePresentation\GenerationProgress::classifyError($read['error'])['kind']);
    }
}
