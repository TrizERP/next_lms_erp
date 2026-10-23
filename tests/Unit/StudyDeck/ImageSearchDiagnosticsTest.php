<?php

namespace Tests\Unit\StudyDeck;

use App\Services\PAL\Integration\ConceptImageSearchService;
use App\Services\StudyDeck\ImagePlanner;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use ReflectionMethod;
use ReflectionProperty;
use Tests\Unit\StudyDeck\Support\BootSafeTestCase;

/**
 * Why a picture was not found: a search that could not be made is reported as that (timeout, HTTP status), a valid empty answer is
 * "nothing found", the generation search waits longer than the Learn page's, and a Wikimedia SVG is fetched as its PNG rendering.
 * The network is faked; nothing leaves the machine and nothing is written to a database.
 */
class ImageSearchDiagnosticsTest extends BootSafeTestCase
{
    use StudyDeckFixture;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'pal_content.image.enabled' => true,
            'pal_content.image.external.enabled' => true,
            'pal_content.image.external.client_id' => '',
            'pal_content.image.external.client_secret' => '',
            'pal_content.image.external.timeout' => 6,
            'pal_content.image.external.batch_timeout' => 40,
        ]);
        Cache::flush();
    }

    private function planner(ConceptImageSearchService $search): ImagePlanner
    {
        return new ImagePlanner($search, $this->memoryStore(), fn () => null);
    }

    private function find(ImagePlanner $planner, string $query = 'plant cell'): array
    {
        return $planner->find($query);
    }

    public function test_a_timed_out_search_is_reported_as_a_timeout_not_as_no_image(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 6001 milliseconds with 0 bytes received'));
        $search = new ConceptImageSearchService();

        $result = $this->find($this->planner($search));

        $this->assertSame('image search failed: timed out', $result['missing']);
        $this->assertSame('timeout', $search->searches[0]['outcome']);
    }

    public function test_an_http_error_is_reported_with_its_status_and_retry_after(): void
    {
        Http::fake(['api.openverse.org/*' => Http::response('quota', 429, ['Retry-After' => '3600'])]);
        $search = new ConceptImageSearchService();

        $result = $this->find($this->planner($search));

        $this->assertSame('image search failed: HTTP 429 (retry after 3600 s)', $result['missing']);
        $this->assertSame(429, $search->searches[0]['status']);
    }

    public function test_a_technical_failure_skips_the_second_pass(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));
        $search = new ConceptImageSearchService();

        $this->find($this->planner($search));

        // `searches` holds the LAST attempt: still the first pass (Wikimedia only), so the pass over all sources was never made.
        $this->assertNotEmpty($search->searches);
        foreach ($search->searches as $row) {
            $this->assertSame('wikimedia', $row['source']);
            $this->assertSame('timeout', $row['outcome']);
        }
    }

    public function test_a_timeout_does_not_try_the_narrower_variants_of_the_same_query(): void
    {
        // Four words make four variants; each used to wait the full timeout for the same provider that had just timed out.
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('cURL error 28: Operation timed out after 40001 milliseconds');
        });
        $search = new ConceptImageSearchService();

        $this->assertNull($search->bestImageFor('stopwatch speed distance time'));
        $this->assertSame(1, $attempts, 'one request, not one per variant');

        $attempts = 0;
        $this->assertSame([], $search->rankedImagesFor('stopwatch speed distance time'));
        $this->assertSame(1, $attempts);
    }

    public function test_an_empty_answer_still_tries_the_narrower_variants(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;

            return Http::response(['results' => []], 200);
        });

        (new ConceptImageSearchService())->rankedImagesFor('stopwatch speed distance time');

        $this->assertGreaterThan(1, $attempts, 'a real answer of "nothing" says the wording was too narrow, so a broader one is tried');
    }

    public function test_a_valid_empty_answer_is_nothing_found_not_a_failure(): void
    {
        Http::fake(['api.openverse.org/*' => Http::response(['results' => []], 200)]);
        $search = new ConceptImageSearchService();

        $result = $this->find($this->planner($search));

        $this->assertSame('no sufficiently relevant, openly licensed image found', $result['missing']);
        $this->assertSame('ok', $search->searches[0]['outcome']);
        $this->assertSame(0, $search->searches[0]['raw']);
        // Both passes ran, because the first one really got an answer: some request asked every source, not only Wikimedia.
        $allSources = array_filter(Http::recorded()->all(), fn ($pair) => !str_contains($pair[0]->url(), 'source=wikimedia'));
        $this->assertNotEmpty($allSources);
    }

    public function test_a_real_empty_answer_is_not_overruled_by_a_later_failure_of_the_second_pass(): void
    {
        // The first pass (Wikimedia) really answers "nothing" for both variants of "plant cell"; the pass over every source then times out.
        $calls = 0;
        Http::fake(function () use (&$calls) {
            if (++$calls > 2) {
                throw new ConnectionException('cURL error 28: timed out');
            }

            return Http::response(['results' => []], 200);
        });

        $result = $this->find($this->planner(new ConceptImageSearchService()));

        $this->assertSame('no sufficiently relevant, openly licensed image found', $result['missing']);
    }

    public function test_the_generation_search_waits_longer_but_the_shared_search_keeps_the_learn_page_timeout(): void
    {
        $shared = new ConceptImageSearchService();
        $batch = $shared->forBatch();

        $timeout = new ReflectionProperty(ConceptImageSearchService::class, 'timeoutOverride');
        $this->assertNull($timeout->getValue($shared), 'the shared instance is not changed');
        $this->assertSame(40, $timeout->getValue($batch));
        $this->assertNotSame($shared, $batch);
    }

    /** @return array<int,ConceptImageSearchService> the search of every ImagePlanner reachable from the object */
    private function searchesIn(object $root, int $depth = 0, array &$seen = []): array
    {
        $found = [];
        if ($depth > 5 || isset($seen[spl_object_id($root)])) {
            return $found;
        }
        $seen[spl_object_id($root)] = true;
        if ($root instanceof ImagePlanner) {
            return [(new ReflectionProperty(ImagePlanner::class, 'search'))->getValue($root)];
        }
        foreach ((new \ReflectionObject($root))->getProperties() as $p) {
            $value = $p->isInitialized($root) ? $p->getValue($root) : null;
            if (is_object($value) && !$value instanceof \Closure) {
                $found = array_merge($found, $this->searchesIn($value, $depth + 1, $seen));
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    if (is_object($item) && !$item instanceof \Closure) {
                        $found = array_merge($found, $this->searchesIn($item, $depth + 1, $seen));
                    }
                }
            }
        }

        return $found;
    }

    public function test_the_deck_and_document_services_use_the_batch_timeout_and_leave_the_shared_search_alone(): void
    {
        $timeout = new ReflectionProperty(ConceptImageSearchService::class, 'timeoutOverride');
        $shared = new ConceptImageSearchService();
        $completer = $this->completer([]);

        $services = [
            'study deck' => \App\Services\StudyDeck\StudyDeckService::make($completer, $this->memoryStore(), $shared),
            'study document' => \App\Services\StudyDeck\Documents\StudyDocumentService::make($completer, $this->memoryStore(), $shared),
        ];

        foreach ($services as $name => $service) {
            $searches = $this->searchesIn($service);
            $this->assertNotEmpty($searches, "$name: found no picture search");
            foreach ($searches as $search) {
                $this->assertSame(40, $timeout->getValue($search), "$name: generation must wait for the search");
                $this->assertNotSame($shared, $search);
            }
        }
        $this->assertNull($timeout->getValue($shared), 'the instance the Learn page shares keeps the configured 6 s');
    }

    public function test_the_search_and_the_download_send_the_configured_user_agent(): void
    {
        config(['pal_content.image.user_agent' => 'EduERP-Test/1.0 (+https://example.org/contact)']);
        Http::fake(['*' => Http::response($this->png()['bytes'], 200, ['Content-Type' => 'image/png'])]);

        $planner = new ImagePlanner($this->imageSearch(null), $this->memoryStore());
        (new ReflectionProperty(ImagePlanner::class, 'paced'))->setValue($planner, false);
        (new ReflectionMethod(ImagePlanner::class, 'fetch'))->invoke($planner, 'https://live.staticflickr.com/1/2_b.jpg');

        Http::assertSent(fn ($request) => $request->header('User-Agent')[0] === 'EduERP-Test/1.0 (+https://example.org/contact)');
    }

    public function test_the_download_falls_back_to_the_earlier_user_agent_when_none_is_configured(): void
    {
        config(['pal_content.image.user_agent' => '']);
        Http::fake(['*' => Http::response($this->png()['bytes'], 200, ['Content-Type' => 'image/png'])]);

        $planner = new ImagePlanner($this->imageSearch(null), $this->memoryStore());
        (new ReflectionProperty(ImagePlanner::class, 'paced'))->setValue($planner, false);
        (new ReflectionMethod(ImagePlanner::class, 'fetch'))->invoke($planner, 'https://live.staticflickr.com/1/2_b.jpg');

        Http::assertSent(fn ($request) => str_starts_with($request->header('User-Agent')[0], 'EduERP-StudyDeck/1.0'));
    }

    /** @return array<string,array{string,string}> */
    public static function wikimediaAddresses(): array
    {
        $base = 'https://upload.wikimedia.org/wikipedia/commons';

        return [
            'svg becomes a png rendering' => [$base . '/3/3a/Water_cycle_diagram-vi.svg', $base . '/thumb/3/3a/Water_cycle_diagram-vi.svg/960px-Water_cycle_diagram-vi.svg.png'],
            'svg with tracking parameters' => [$base . '/3/3a/Water_cycle_diagram.svg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo', $base . '/thumb/3/3a/Water_cycle_diagram.svg/960px-Water_cycle_diagram.svg.png'],
            'uppercase svg' => [$base . '/3/3a/Cell.SVG', $base . '/thumb/3/3a/Cell.SVG/960px-Cell.SVG.png'],
            'jpeg is unchanged in type' => [$base . '/a/ab/Microscope.jpg', $base . '/thumb/a/ab/Microscope.jpg/960px-Microscope.jpg'],
            'tiff becomes a jpeg' => [$base . '/c/cd/Cork.tif', $base . '/thumb/c/cd/Cork.tif/960px-Cork.tif.jpg'],
            'svg on another host is left alone' => ['https://example.org/a/ab/Cell.svg', 'https://example.org/a/ab/Cell.svg'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('wikimediaAddresses')]
    public function test_preferThumbnail_converts_only_wikimedia_addresses(string $in, string $expected): void
    {
        $planner = new ImagePlanner($this->imageSearch(null), $this->memoryStore());

        $this->assertSame($expected, (new ReflectionMethod(ImagePlanner::class, 'preferThumbnail'))->invoke($planner, $in));
    }

    public function test_a_wikimedia_svg_is_downloaded_as_png_and_never_as_the_svg(): void
    {
        $svg = 'https://upload.wikimedia.org/wikipedia/commons/3/3a/Water_cycle_diagram-vi.svg';
        $thumb = 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3a/Water_cycle_diagram-vi.svg/960px-Water_cycle_diagram-vi.svg.png';
        Http::fake([
            $thumb => Http::response($this->png()['bytes'], 200, ['Content-Type' => 'image/png']),
            $svg => Http::response('<svg xmlns="http://www.w3.org/2000/svg"/>', 200, ['Content-Type' => 'image/svg+xml']),
        ]);
        $planner = new ImagePlanner($this->imageSearch(null), $this->memoryStore());
        (new ReflectionProperty(ImagePlanner::class, 'paced'))->setValue($planner, false);

        $file = (new ReflectionMethod(ImagePlanner::class, 'fetch'))->invoke($planner, $svg);

        $this->assertSame('image/png', $file['mime']);
        $this->assertSame($thumb, $file['source']);
        Http::assertNotSent(fn ($request) => $request->url() === $svg);
    }

    public function test_a_refused_svg_rendering_does_not_fall_back_to_the_svg_itself(): void
    {
        $svg = 'https://upload.wikimedia.org/wikipedia/commons/3/3a/Water_cycle_diagram-vi.svg';
        Http::fake(['upload.wikimedia.org/*' => Http::response('slow down', 429)]);
        $planner = new ImagePlanner($this->imageSearch(null), $this->memoryStore());
        (new ReflectionProperty(ImagePlanner::class, 'paced'))->setValue($planner, false);

        $this->assertNull((new ReflectionMethod(ImagePlanner::class, 'fetch'))->invoke($planner, $svg));
        Http::assertSentCount(1);
    }

    public function test_a_wikimedia_svg_candidate_is_no_longer_rejected_for_its_format_but_another_hosts_svg_still_is(): void
    {
        $wiki = ['url' => 'https://upload.wikimedia.org/wikipedia/commons/3/3a/Plant_cell_diagram.svg', 'thumbnail_url' => 'x', 'title' => 'Plant cell diagram', 'license' => 'BY-SA 4.0', 'provider' => 'wikimedia', 'creator' => 'A', 'attribution' => 'A, CC BY-SA 4.0', 'tags' => ['plant', 'cell']];
        $other = ['url' => 'https://example.org/plant_cell_diagram.svg'] + $wiki;

        $planner = new ImagePlanner($this->imageSearch($wiki, [$other]), $this->memoryStore(), fn (string $url) => ['bytes' => $this->png()['bytes'], 'mime' => 'image/png', 'width' => 600, 'height' => 500, 'source' => $url]);
        $planner->find('plant cell');

        $byUrl = [];
        foreach ($planner->events as $e) {
            $byUrl[$e['url']] = $e['why'];
        }
        $this->assertSame('format the library does not store', $byUrl[$other['url']] ?? null);
        $this->assertNotSame('format the library does not store', $byUrl[$wiki['url']] ?? null);
    }
}
