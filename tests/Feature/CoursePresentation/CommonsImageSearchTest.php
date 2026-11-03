<?php

namespace Tests\Feature\CoursePresentation;

use App\Services\PAL\Integration\ConceptImageSearchService;
use App\Services\PAL\Integration\WikimediaCommonsImageSearch;
use App\Services\StudyDeck\ImagePlanner;
use App\Services\StudyDeck\StudyImageStores;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Photographs from Wikimedia Commons: what is offered, what is refused and why, that it works while Openverse does not answer, that nobody else
 * is switched over, and that a pilot slide with no photograph is left without one instead of getting a diagram. The network is faked.
 */
class CommonsImageSearchTest extends CoursePresentationTestCase
{
    private function page(int $index, string $title, array $meta = [], array $info = []): array
    {
        $m = fn (string $v) => ['value' => $v];

        return [
            'pageid' => 1000 + $index, 'title' => 'File:' . $title . '.jpg', 'index' => $index,
            'imageinfo' => [$info + [
                'url' => 'https://upload.wikimedia.org/wikipedia/commons/a/ab/' . str_replace(' ', '_', $title) . '.jpg',
                'thumburl' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/' . str_replace(' ', '_', $title) . '.jpg/640px-x.jpg',
                'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:' . str_replace(' ', '_', $title) . '.jpg',
                'width' => 4000, 'height' => 3000, 'mime' => 'image/jpeg',
                'extmetadata' => array_map($m, $meta + ['LicenseShortName' => 'CC BY-SA 4.0', 'Artist' => '<a href="//commons.wikimedia.org/wiki/User:Jo">Jo &amp; Co</a>', 'Categories' => 'Cricket|Sports equipment', 'ImageDescription' => 'A <b>cricket</b> bat and ball']),
            ]],
        ];
    }

    private function commons(array $pages): void
    {
        Http::fake(['commons.wikimedia.org/*' => Http::response(['query' => ['pages' => $pages]], 200)]);
    }

    public function test_a_photograph_comes_back_with_its_licence_creator_source_and_credit_in_the_shape_the_pipeline_uses(): void
    {
        $this->commons([$this->page(1, 'Cricket bat and ball')]);

        $rows = (new WikimediaCommonsImageSearch())->search('cricket bat ball');

        $this->assertCount(1, $rows);
        $r = $rows[0];
        $this->assertSame(['BY-SA 4.0', 'Jo & Co', 'wikimedia', 4000, 3000], [$r['license'], $r['creator'], $r['provider'], $r['width'], $r['height']]);
        $this->assertSame('Cricket bat and ball', $r['title']);
        $this->assertSame('https://commons.wikimedia.org/wiki/File:Cricket_bat_and_ball.jpg', $r['source_url']);
        $this->assertStringStartsWith('https://upload.wikimedia.org/', $r['url']);
        $this->assertStringContainsString('"Cricket bat and ball" by Jo & Co, CC BY-SA 4.0, via Wikimedia Commons', $r['attribution']);
        $this->assertContains('Cricket', $r['tags']);
        $this->assertContains('A cricket bat and ball', $r['tags'], 'the description is plain words, not HTML');
        // The estate's own licence gate accepts it as written.
        $this->assertTrue((new ImagePlanner(new ConceptImageSearchService(), StudyImageStores::database(self::TENANT, self::CHAPTER)))->licenceAccepted($r['license']));
    }

    public function test_licences_are_written_the_way_the_pipeline_accepts_them_and_unclear_ones_are_refused(): void
    {
        $c = new WikimediaCommonsImageSearch();
        $this->assertSame('BY-SA 4.0', $c->licence('CC BY-SA 4.0'));
        $this->assertSame('BY 2.0', $c->licence('CC BY 2.0'));
        $this->assertSame('CC0 1.0', $c->licence('CC0'));
        $this->assertSame('PDM 1.0', $c->licence('Public domain'));
        $this->assertSame('PDM 1.0', $c->licence('PD-USGov-NASA'));
        foreach (['GFDL', 'CC BY-NC 4.0', 'CC BY-ND 2.0', 'CC BY-NC-SA 3.0', 'Fair use', 'Attribution only', '', 'Copyrighted free use'] as $bad) {
            $this->assertNull($c->licence($bad), "'$bad' must not be offered");
        }
    }

    public function test_what_is_not_a_clear_photograph_for_a_school_is_left_out(): void
    {
        $this->commons([
            $this->page(1, 'Good cricket photo'),
            $this->page(2, 'Small cricket photo', [], ['width' => 400, 'height' => 300]),
            $this->page(3, 'Cricket diagram', ['Categories' => 'Diagrams']),
            $this->page(4, 'Cricket ball logo'),
            $this->page(5, 'Svg cricket', [], ['mime' => 'image/svg+xml']),
            $this->page(6, 'Cricket noncommercial', ['LicenseShortName' => 'CC BY-NC 4.0']),
            $this->page(7, 'Cricket no author', ['Artist' => '', 'Credit' => '']),
            $this->page(8, 'Cricket restricted', ['Restrictions' => 'personality']),
            $this->page(9, 'Cricket nude', ['Categories' => 'Nude cricket']),
            $this->page(10, 'Cricket public domain no author', ['LicenseShortName' => 'Public domain', 'Artist' => '']),
        ]);

        $titles = array_column((new WikimediaCommonsImageSearch())->search('cricket', 10), 'title');

        $this->assertSame(['Good cricket photo', 'Cricket public domain no author'], $titles, 'a public-domain file needs no author; a BY file without one cannot be credited');
    }

    public function test_the_query_is_shortened_when_commons_finds_nothing_for_every_word(): void
    {
        Http::fake(function (Request $request) {
            // Commons wants every word: the long form finds nothing, the shorter form finds the subject.
            return str_contains($request['gsrsearch'], 'gimli glider aircraft') ? Http::response(['query' => ['pages' => []]], 200) : Http::response(['query' => ['pages' => [$this->page(1, 'Gimli Glider')]]], 200);
        });

        $rows = (new WikimediaCommonsImageSearch())->search('the Gimli glider aircraft landing', 4);

        $this->assertSame(['Gimli Glider'], array_column($rows, 'title'));
        $this->assertSame(['gimli glider aircraft landing', 'gimli glider aircraft', 'gimli glider'], (new WikimediaCommonsImageSearch())->variants('the Gimli glider aircraft landing'));
    }

    public function test_a_search_that_failed_is_not_remembered_as_nothing_found(): void
    {
        Http::fake(['commons.wikimedia.org/*' => Http::sequence()->push('', 503)->push(['query' => ['pages' => [$this->page(1, 'Cricket bat')]]], 200)]);

        $this->assertSame([], (new WikimediaCommonsImageSearch())->search('cricket'));
        $this->assertCount(1, (new WikimediaCommonsImageSearch())->search('cricket'), 'the next search asks again');
    }

    // ------------------------------------------------------------------------------------------------ through the existing search service

    public function test_with_the_opt_in_a_photograph_is_found_while_openverse_does_not_answer(): void
    {
        Cache::flush();
        $openverseAsked = 0;
        Http::fake([
            'api.openverse.org/*' => function () use (&$openverseAsked) {
                $openverseAsked++;
                throw new ConnectionException('cURL error 28: Operation timed out');
            },
            'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => [$this->page(1, 'Cricket bat and ball')]]], 200),
        ]);

        $service = (new ConceptImageSearchService())->useCommons();
        $rows = $service->rankedImagesFor('cricket bat ball', 6, 1.0);

        $this->assertSame(['Cricket bat and ball'], array_column($rows, 'title'), 'the photograph is found although Openverse timed out');
        $this->assertSame(1, $openverseAsked, 'Openverse is asked once for what Commons did not fill, and a timeout stops the narrower retries');
    }

    public function test_without_the_opt_in_nobody_else_is_switched_over(): void
    {
        Cache::flush();
        Http::fake([
            'api.openverse.org/*' => Http::response(['results' => []], 200),
            'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => [$this->page(1, 'Cricket bat and ball')]]], 200),
        ]);

        $rows = (new ConceptImageSearchService())->rankedImagesFor('cricket bat ball', 6, 1.0);

        $this->assertSame([], $rows);
        $this->assertCount(0, collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), 'commons.wikimedia.org')), 'Commons is never asked for a caller that did not opt in');
    }

    public function test_a_batch_search_asks_commons_first_for_every_query_and_does_not_wait_on_openverse_for_what_it_found(): void
    {
        Cache::flush();
        $openverseAsked = [];
        Http::fake([
            'api.openverse.org/*' => function (Request $request) use (&$openverseAsked) {
                $openverseAsked[] = $request['q'];
                throw new ConnectionException('cURL error 28: Operation timed out');
            },
            'commons.wikimedia.org/*' => fn (Request $request) => Http::response(['query' => ['pages' => str_contains($request['gsrsearch'], 'unknowable') ? [] : [$this->page(1, 'Cricket bat and ball'), $this->page(2, 'Cricket bat'), $this->page(3, 'Cricket ball')]]], 200),
        ]);

        $out = (new ConceptImageSearchService())->useCommons()->rankedMany(['cricket bat ball', 'unknowable thing'], 4, 1.0, 'wikimedia', 2, 4, microtime(true) + 30);

        $this->assertSame(['Cricket bat and ball', 'Cricket bat', 'Cricket ball'], array_column($out['cricket bat ball'], 'title'), 'every query is answered by Commons');
        $this->assertSame([], $out['unknowable thing']);
        $this->assertSame(['unknowable thing'], $openverseAsked, 'Openverse is asked only for the query Commons could not answer, so its stall costs the others nothing');
    }

    public function test_every_query_of_a_batch_is_asked_in_one_round_not_one_after_another(): void
    {
        Cache::flush();
        $seen = [];
        Http::fake(['commons.wikimedia.org/*' => function (Request $request) use (&$seen) {
            $seen[] = $request['gsrsearch'];

            return Http::response(['query' => ['pages' => [$this->page(1, 'A photo one'), $this->page(2, 'A photo two'), $this->page(3, 'A photo three')]]], 200);
        }]);

        $rows = (new WikimediaCommonsImageSearch())->searchMany(['water cycle', 'solar eclipse', 'cricket ball'], 6, 10);

        $this->assertCount(3, $rows);
        $this->assertCount(3, $seen, 'one request per query, all in the first round: three photographs each is enough, so there is no second round');
        $this->assertSame(['water cycle', 'solar eclipse', 'cricket ball'], array_map(fn ($s) => str_replace(' filetype:bitmap', '', $s), $seen));
        foreach ($rows as $found) {
            $this->assertCount(3, $found);
        }
    }

    public function test_a_query_with_too_few_photographs_is_asked_again_in_a_shorter_form_and_a_late_deadline_stops_the_rounds(): void
    {
        Cache::flush();
        $seen = [];
        Http::fake(['commons.wikimedia.org/*' => function (Request $request) use (&$seen) {
            $seen[] = str_replace(' filetype:bitmap', '', $request['gsrsearch']);

            return Http::response(['query' => ['pages' => str_contains($request['gsrsearch'], 'gimli glider aircraft') ? [] : [$this->page(1, 'Gimli Glider')]]], 200);
        }]);

        $rows = (new WikimediaCommonsImageSearch())->searchMany(['gimli glider aircraft landing'], 6, 10, null, 1);
        $this->assertSame(['Gimli Glider'], array_column($rows['gimli glider aircraft landing'], 'title'));
        $this->assertSame(['gimli glider aircraft landing', 'gimli glider aircraft', 'gimli glider'], $seen, 'the long form found nothing; the shorter forms are tried round by round');

        $seen = [];
        Cache::flush();
        $none = (new WikimediaCommonsImageSearch())->searchMany(['gimli glider aircraft landing'], 6, 10, microtime(true) + 0.5);
        $this->assertSame([], $none['gimli glider aircraft landing']);
        $this->assertSame([], $seen, 'no request is started when the stage has no time left');
    }

    // ------------------------------------------------------------------------------------------------ what is placed on a slide with no one looking

    /** @var array<int,array{minScore:?float,seconds:float}> what the planner asked the search for */
    public array $asked = [];

    /** @var array<string,array<int,array<string,mixed>>> query => candidates the fake search answers */
    public array $answers = [];

    private function noisePng(int $w = 700, int $h = 500, int $seed = 3): string
    {
        $im = imagecreatetruecolor($w, $h);
        mt_srand($seed);
        for ($i = 0; $i < 4000; $i++) {
            imagefilledrectangle($im, mt_rand(0, $w), mt_rand(0, $h), mt_rand(0, $w), mt_rand(0, $h), imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
        }
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    private function candidate(string $name, string $title, array $tags = []): array
    {
        return ['url' => "https://img.test/$name.png", 'thumbnail_url' => "https://img.test/$name-t.png", 'title' => $title, 'creator' => 'Ann Author', 'license' => 'BY 4.0', 'source_url' => "https://img.test/page/$name", 'provider' => 'wikimedia', 'tags' => $tags];
    }

    private function answeringPlanner(bool $photosFirst): ImagePlanner
    {
        $self = $this;
        $search = new class($self) extends ConceptImageSearchService {
            public function __construct(private CommonsImageSearchTest $t)
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
                $this->t->asked[] = ['minScore' => $minScore, 'seconds' => round(($deadline ?? microtime(true)) - microtime(true))];
                $out = [];
                foreach ($queries as $q) {
                    $out[$q] = array_map(fn ($c) => $c + ['query' => $q], $this->t->answers[$q] ?? []);
                }

                return $out;
            }
        };

        return (new ImagePlanner($search, StudyImageStores::database(self::TENANT, self::CHAPTER)))->withFastPolicy(['tenant' => self::TENANT, 'scope' => 't1', 'stage_seconds' => 20])->photosFirst($photosFirst);
    }

    private function photoSlide(string $query): array
    {
        return ['n' => 2, 'title' => 'A slide', 'teaches' => 'The idea.', 'visual' => ['required' => true, 'role' => 'object', 'query' => $query, 'purpose' => 'Shows ' . $query]];
    }

    public function test_a_photograph_is_placed_with_no_one_looking_only_when_the_picture_itself_names_what_the_slide_asked_for(): void
    {
        $this->seedPilotChapter();
        Http::fake(['img.test/*' => Http::response($this->noisePng(), 200, ['Content-Type' => 'image/png'])]);
        $this->answers = [
            'rubber party balloon' => [$this->candidate('duck', 'Rubber Ducky Roundup Pool Party')],                 // two of three words
            'rubber party' => [$this->candidate('duck', 'Rubber Ducky Roundup Pool Party')],
            'cricket ball' => [$this->candidate('ball', 'Cricket ball on grass')],                                    // every word
        ];

        $balloon = $this->answeringPlanner(true)->plan([$this->photoSlide('rubber party balloon')], [])[2];
        $this->assertArrayHasKey('missing', $balloon, 'two of three words is not enough for a picture nobody looked at');
        $this->assertSame('none', $balloon['source_kind']);

        $ball = $this->answeringPlanner(true)->plan([$this->photoSlide('cricket ball')], [])[2];
        $this->assertArrayNotHasKey('missing', $ball);
        $this->assertSame('photo', $ball['type']);

        // The ordinary ladder is exactly as strict as it always was.
        $old = $this->answeringPlanner(false)->plan([$this->photoSlide('rubber party balloon')], [])[2];
        $this->assertArrayNotHasKey('missing', $old);
    }

    public function test_a_one_word_query_is_not_killed_by_a_score_floor_it_can_never_reach(): void
    {
        $this->seedPilotChapter();
        Http::fake(['img.test/*' => Http::response($this->noisePng(), 200, ['Content-Type' => 'image/png'])]);
        $this->answers = ['bicycle' => [$this->candidate('bike', 'Parked bicycle with graffiti')]];

        $this->answeringPlanner(true)->plan([$this->photoSlide('bicycle')], []);
        $this->assertSame(1.0, $this->asked[0]['minScore']);

        $this->asked = [];
        Cache::flush();   // the first run's resolved picture would otherwise be reused without any search
        $this->answeringPlanner(false)->plan([$this->photoSlide('bicycle')], []);
        $this->assertSame(2.0, $this->asked[0]['minScore'], 'every other deck keeps its floor');
    }

    public function test_only_the_photograph_first_mode_gets_a_longer_picture_stage(): void
    {
        $this->seedPilotChapter();
        Http::fake(['img.test/*' => Http::response($this->noisePng(), 200, ['Content-Type' => 'image/png'])]);

        $this->answeringPlanner(true)->plan([$this->photoSlide('cricket ball')], []);
        Cache::flush();
        $this->answeringPlanner(false)->plan([$this->photoSlide('cricket ball')], []);

        $this->assertEqualsWithDelta(75, $this->asked[0]['seconds'], 2, 'a cold search of a whole deck can take 5-18 s before any download starts');
        $this->assertEqualsWithDelta(20, $this->asked[1]['seconds'], 2);
    }

    // ------------------------------------------------------------------------------------------------ real photographs, no stand-in diagram

    private function plannerFindingNothing(bool $photosFirst): ImagePlanner
    {
        $search = new class extends ConceptImageSearchService {
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
                return array_fill_keys($queries, []);
            }
        };

        return (new ImagePlanner($search, StudyImageStores::database(self::TENANT, self::CHAPTER)))->withFastPolicy(['tenant' => self::TENANT, 'scope' => 't1', 'stage_seconds' => 20])->photosFirst($photosFirst);
    }

    private function slide(string $role): array
    {
        return ['n' => 2, 'title' => 'Our Exploration Goals', 'teaches' => 'The idea.', 'visual' => ['required' => true, 'role' => $role, 'query' => 'exploration goals', 'purpose' => 'Shows goals'],
            'diagram' => ['layout' => 'flow', 'title' => 'Our Exploration Goals', 'nodes' => ['Our Exploration Goals', 'Our Exploration Goals']]];
    }

    public function test_a_pilot_slide_with_no_photograph_is_left_without_one_instead_of_a_diagram_that_restates_its_title(): void
    {
        $this->seedPilotChapter();

        $image = $this->plannerFindingNothing(true)->plan([$this->slide('object')], [])[2];

        $this->assertArrayHasKey('missing', $image);
        $this->assertSame('none', $image['source_kind']);
        $this->assertStringContainsString('no sufficiently relevant', $image['missing']);
    }

    public function test_every_other_deck_keeps_the_ladder_and_a_planned_diagram_is_still_drawn(): void
    {
        $this->seedPilotChapter();

        $old = $this->plannerFindingNothing(false)->plan([$this->slide('object')], [])[2];
        $this->assertSame('diagram', $old['type'], 'outside the pilot the ladder is unchanged');

        $planned = $this->plannerFindingNothing(true)->plan([$this->slide('diagram')], [])[2];
        $this->assertSame('diagram', $planned['type'], 'a slide the plan itself marks as a diagram is the picture asked for, not a substitute');
    }
}
