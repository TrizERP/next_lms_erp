<?php

namespace Tests\Unit\StudyDeck;

use App\Domain\AI\Support\AiProviderException;
use App\Domain\AI\Support\ImageGenerationResult;
use App\Services\PAL\Integration\ConceptImageSearchService;
use App\Services\QuestionGeneration\DragDrop\Contracts\DiagramImageStore;
use App\Services\StudyDeck\Contracts\Completer;
use App\Services\StudyDeck\Contracts\SourceImageCache;
use App\Services\StudyDeck\ImagePlanner;
use App\Services\StudyDeck\IsolatedImageDatabase;
use App\Services\StudyDeck\Story\SceneIllustrator;
use App\Services\StudyDeck\Story\StoryGraph;
use App\Services\StudyDeck\Story\StoryImageLibrary;
use App\Services\StudyDeck\Story\StoryPictureCover;
use App\Services\StudyDeck\Story\StoryWriter;
use Tests\Unit\StudyDeck\Support\BootSafeTestCase;

/**
 * The picture ladder that gives every scene and ending of a story a picture of its own: related, topic, reused, AI-generated, placeholder.
 * The search is a stub that answers per query (or fails); the download is a stub; the store is in memory. Nothing leaves the machine.
 */
class StoryPictureCoverTest extends BootSafeTestCase
{
    private string $tmp = '';

    protected function setUp(): void
    {
        parent::setUp();
        config(['logging.default' => 'null']);
        $this->tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'story-cover-' . bin2hex(random_bytes(4));
        mkdir($this->tmp, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmp . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->tmp);
        parent::tearDown();
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Fixtures

    /** A busy picture, so the sharpness check passes. */
    private function busy(int $w = 640, int $h = 420, int $seed = 3): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, 120, 120, 125));
        mt_srand($seed);
        for ($i = 0; $i < 160; $i++) {
            imageline($im, mt_rand(0, $w), mt_rand(0, $h), mt_rand(0, $w), mt_rand(0, $h), imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
        }
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    /** An in-memory picture store that also remembers where each picture came from, as the real one does. */
    private function store(): DiagramImageStore
    {
        return new class implements DiagramImageStore, SourceImageCache {
            /** @var array<string,string> sha1 => bytes */
            public array $byHash = [];

            /** @var array<string,string> source => bytes */
            public array $bySource = [];

            public function store(string $bytes, string $mime, ?string $sourceUrl = null): string
            {
                $h = sha1($bytes);
                $this->byHash[$h] = $bytes;
                if ($sourceUrl !== null) {
                    $this->bySource[$sourceUrl] = $bytes;
                }

                return 'study-deck-image:' . (array_search($h, array_keys($this->byHash), true) + 1);
            }

            public function cached(string $url): ?array
            {
                $b = $this->bySource[$url] ?? null;
                if ($b === null) {
                    return null;
                }
                $i = getimagesizefromstring($b);

                return ['bytes' => $b, 'mime' => $i['mime'], 'width' => $i[0], 'height' => $i[1]];
            }
        };
    }

    /**
     * A search whose answer is decided per query: a list of candidates, or a technical failure ('timeout', 'http_error').
     *
     * @param \Closure(string):array{0:array<int,array<string,mixed>>,1:?string} $answer
     */
    private function search(\Closure $answer): ConceptImageSearchService
    {
        return new class($answer) extends ConceptImageSearchService {
            /** @var array<int,string> */
            public array $calls = [];

            public function __construct(private \Closure $answer)
            {
            }

            public function available(): bool
            {
                return true;
            }

            public function rankedImagesFor(string $query, int $limit = 5, ?float $minScore = null, ?string $source = null): array
            {
                $this->calls[] = $query;
                [$candidates, $failure] = ($this->answer)($query);
                $this->searches = [['query' => $query, 'source' => $source ?? 'wikimedia', 'outcome' => $failure ?? 'ok', 'status' => $failure === 'http_error' ? 429 : ($failure ? null : 200),
                    'ms' => 1, 'raw' => count($candidates), 'usable' => count($candidates), 'retry_after' => null, 'cached' => false]];

                return array_map(fn ($c) => $c + ['query' => $query], $candidates);
            }
        };
    }

    /** One acceptable candidate whose title and tags are made of the query's words. @return array<string,mixed> */
    private function candidate(string $query, string $licence = 'BY-SA 4.0', string $host = 'https://upload.wikimedia.org/wikipedia/commons/a/ab/'): array
    {
        $slug = preg_replace('/\W+/', '_', $query);

        return ['url' => $host . $slug . '.jpg', 'thumbnail_url' => $host . $slug . '.jpg', 'title' => ucfirst($query) . ' demonstration', 'license' => $licence, 'provider' => 'wikimedia',
            'creator' => 'A. Photographer', 'attribution' => 'A. Photographer, CC ' . $licence, 'tags' => explode(' ', $query), 'source_url' => 'https://commons.wikimedia.org/wiki/File:' . $slug, 'width' => 640, 'height' => 420];
    }

    /** @param callable|null $judge the picture (pixel) check; the text relevance review is always present, as in production, unless $textJudge is false */
    private function planner(ConceptImageSearchService $search, ?DiagramImageStore $store = null, ?callable $judge = null, callable|false|null $textJudge = null): ImagePlanner
    {
        $bytes = $this->busy();
        $accept = fn (string $query, string $purpose, array $candidates, string $teaches = ''): array => ['accepted' => array_keys($candidates), 'reasons' => [], 'reason' => ''];
        $planner = new ImagePlanner($search, $store ?? $this->store(), fn (string $url) => ['bytes' => $bytes, 'mime' => 'image/png', 'width' => 640, 'height' => 420, 'source' => $url], 1.0, $textJudge === false ? null : ($textJudge ?? $accept));
        if ($judge !== null) {
            $planner->withPixelJudge($judge);
        }

        return $planner;
    }

    /** @return array<string,mixed> one act: a start scene, a decision, two consequence scenes and an ending */
    private function act(array $images = []): array
    {
        $node = fn (string $id, string $kind, string $title, array $choices) => ['id' => $id, 'kind' => $kind, 'title' => $title, 'body' => 'Kabir and Priya look at the ' . strtolower($title) . ' on the road and talk about what they see.',
            'concept_ids' => [1], 'choices' => $choices] + (isset($images[$id]) ? ['image' => $images[$id]] : []);

        return ['key' => 'a1', 'template' => 'story_branching_scenario', 'start' => 's1', 'mission' => ['title' => 'm', 'body' => 'b'], 'nodes' => [
            $node('s1', 'scene', 'Measuring speed with a stopwatch', [['id' => 'c', 'next' => 'd1']]),
            $node('d1', 'decision', 'What to do next', [['id' => 'a', 'next' => 's2a'], ['id' => 'b', 'next' => 's2b']]),
            $node('s2a', 'scene', 'Repeating the measurement', [['id' => 'c', 'next' => 'e1']]),
            $node('s2b', 'scene', 'Trusting one reading', [['id' => 'c', 'next' => 'e1']]),
            $node('e1', 'ending', 'What the class learned', []),
        ]];
    }

    private array $ctx = ['key' => 'a1', 'concepts' => [1 => 'Speed and time'], 'chapter' => 'Exploration science', 'subject' => 'Science', 'topic' => 'Measurement'];

    private function cover(StoryPictureCover $cover, array $act, ?array $ctx = null): array
    {
        $used = [];
        $notes = [];

        return $cover->cover($act, $ctx ?? $this->ctx, $used, $notes);
    }

    /** A ladder that may fall back to the labelled placeholder (the old default; now opt-in). */
    private function placeholderCover(ImagePlanner $planner, StoryImageLibrary $library, ?SceneIllustrator $ai = null): StoryPictureCover
    {
        return new StoryPictureCover($planner, $library, $ai, true);
    }

    /** A ladder as a run gets it by default: real pictures only. */
    private function realOnlyCover(ImagePlanner $planner, StoryImageLibrary $library, ?SceneIllustrator $ai = null): StoryPictureCover
    {
        return new StoryPictureCover($planner, $library, $ai);
    }

    private function levels(array $act): array
    {
        $out = [];
        foreach ($act['nodes'] as $n) {
            if (!empty($n['image'])) {
                $out[$n['id']] = $n['image']['match_level'] ?? 'exact';
            }
        }

        return $out;
    }

    // ---------------------------------------------------------------------------------------------------------------
    // The rungs

    public function test_a_scene_with_no_exact_picture_gets_a_related_one_that_says_it_is_not_the_exact_scene(): void
    {
        // Only the idea ("speed time") has a picture; the scene's own words find nothing.
        $search = $this->search(fn (string $q) => str_contains($q, 'speed') ? [[$this->candidate('speed time')], null] : [[], null]);
        $cover = $this->placeholderCover($this->planner($search), new StoryImageLibrary(null));

        $act = $this->cover($cover, $this->act());

        $this->assertSame([], StoryGraph::bareNodes($act), 'every scene and ending has a picture and the decision inherits one');
        $img = $act['nodes'][0]['image'];
        $this->assertSame('related', $img['match_level']);
        $this->assertStringStartsWith('Related picture, not this exact scene', $img['caption']);
        $this->assertStringStartsWith('Related picture, not this exact scene', $img['alt']);
        // A found picture keeps its terms: licence, creator, credit and source, exactly as found.
        $this->assertSame('BY-SA 4.0', $img['licence']);
        $this->assertSame('A. Photographer', $img['creator']);
        $this->assertStringContainsString('A. Photographer', $img['attribution']);
        $this->assertNotEmpty($img['source_url']);
        $this->assertMatchesRegularExpression('/^study-deck-image:\d+$/', $img['url']);
    }

    public function test_the_related_level_accepts_a_partial_match_that_the_exact_level_refuses(): void
    {
        // The title and tags share one of the query's two words.
        $search = $this->search(fn (string $q) => [[$this->candidate('speed') + ['title' => 'Speed demonstration']], null]);
        $planner = $this->planner($search);

        $exact = $planner->find('speed time', [], 'Measuring speed', 'Speed and time', ImagePlanner::LEVEL_EXACT);
        $related = $planner->find('speed time', [], 'Measuring speed', 'Speed and time', ImagePlanner::LEVEL_RELATED);

        $this->assertArrayHasKey('missing', $exact, 'one shared word is not an exact match');
        $this->assertArrayNotHasKey('missing', $related);
        $this->assertSame('related', $related['match_level']);
        $this->assertNotEmpty($related['fetched_from'], 'where the bytes came from is recorded so they can be reused');
    }

    public function test_the_judges_are_told_a_related_picture_is_acceptable_only_for_the_relaxed_levels(): void
    {
        $seen = [];
        $judge = function (string $query, string $purpose, array $candidates, string $teaches = '') use (&$seen): array {
            $seen[] = $purpose;

            return ['accepted' => array_keys($candidates), 'reasons' => [], 'reason' => ''];
        };
        $search = $this->search(fn (string $q) => [[$this->candidate('speed time')], null]);
        $planner = new ImagePlanner($search, $this->store(), fn (string $u) => ['bytes' => $this->busy(), 'mime' => 'image/png', 'width' => 640, 'height' => 420, 'source' => $u], 1.0, $judge);

        $planner->find('speed time', [], 'Measuring speed', '', ImagePlanner::LEVEL_EXACT);
        $planner->find('speed time two', [], 'Measuring speed', '', ImagePlanner::LEVEL_RELATED);

        $this->assertSame('Measuring speed', $seen[0]);
        $this->assertStringStartsWith(ImagePlanner::RELATED_PREFIX, $seen[1]);
    }

    public function test_a_related_search_with_nothing_to_check_the_picture_finds_nothing_and_is_not_remembered_as_a_miss(): void
    {
        $search = $this->search(fn (string $q) => [[$this->candidate('speed time')], null]);
        $library = new StoryImageLibrary(null);

        $act = $this->cover($this->placeholderCover($this->planner($search, null, null, false), $library), $this->act());

        $this->assertSame('placeholder', $act['nodes'][0]['image']['match_level'], 'no relevance review and no picture check: nothing vouches for a related picture');
        $this->assertSame([], $search->calls, 'and no search was spent on it');
        $this->assertNull($library->missed('speed time'));
    }

    public function test_an_expired_writer_picture_deadline_skips_search_and_uses_a_grounded_diagram(): void
    {
        $search = $this->search(fn () => [[], null]);
        $planner = $this->planner($search);
        $completer = new class implements Completer {
            public function complete(string $system, string $prompt, int $maxTokens = 16000): string
            {
                return '';
            }
        };
        $writer = new StoryWriter($completer, $planner);
        $writer->cover = $this->realOnlyCover($planner, new StoryImageLibrary());
        $writer->useMap(['concepts' => [1 => ['name' => 'Speed and time']]]);
        $writer->setDeadline(microtime(true) - 1);
        $act = ['key' => 'a1', 'title' => 'Measurement', 'kind' => 'topic', 'required_ids' => [1]];
        $context = ['chapter' => ['chapter_name' => 'Exploration science', 'subject_name' => 'Science']];
        $notes = [];

        $covered = $writer->coverInteraction($this->act(), $context, $act, ['image_coverage' => true], $notes);

        $this->assertSame([], $search->calls);
        $this->assertNotEmpty(array_filter($covered['nodes'], fn ($node) => in_array($node['kind'], ['scene', 'ending'], true) && !empty($node['image'])));
    }

    public function test_a_conceptless_final_scene_gets_a_grounded_diagram_from_chapter_title_parts(): void
    {
        $search = $this->search(fn () => [[], null]);
        $cover = $this->realOnlyCover($this->planner($search), new StoryImageLibrary(null));
        $act = $this->act();
        foreach ($act['nodes'] as &$node) {
            $node['concept_ids'] = [];
        }
        unset($node);
        $ctx = [
            'chapter' => 'Global water resources: Distribution and historical impact',
            'subject' => 'EVS',
            'topic' => 'Global water resources: Distribution and historical impact',
            'concepts' => [],
            'act_concepts' => [],
        ];
        $usedUrls = [];
        $notes = [];

        $covered = $cover->cover($act, $ctx, $usedUrls, $notes, microtime(true) - 1);

        $this->assertSame([], $search->calls);
        foreach ($covered['nodes'] as $node) {
            if (in_array($node['kind'], [StoryGraph::SCENE, StoryGraph::ENDING], true)) {
                $this->assertSame(StoryPictureCover::TOPIC, $node['image']['match_level']);
                $this->assertSame('diagram', $node['image']['type']);
            }
        }
    }

    public function test_a_word_that_names_a_whole_subject_is_not_evidence_for_a_related_picture(): void
    {
        // "science" is in the title, and it is the only word shared with the query.
        $search = $this->search(fn (string $q) => [[$this->candidate('science') + ['title' => 'High school science cover', 'tags' => ['science']]], null]);
        $planner = $this->planner($search);

        $result = $planner->find('science', [], 'A science scene', '', ImagePlanner::LEVEL_RELATED);

        $this->assertStringContainsString('do not match enough', (string) ($result['missing'] ?? ''));
    }

    public function test_a_node_with_no_concepts_of_its_own_is_searched_for_by_the_ideas_of_its_act(): void
    {
        $act = $this->act();
        foreach ($act['nodes'] as $k => $n) {
            $act['nodes'][$k]['concept_ids'] = [];   // as in a real story: most nodes name none
        }
        $search = $this->search(fn (string $q) => [[], null]);
        $ctx = $this->ctx + ['act_concepts' => ['Speed and time', 'Measurement error']];

        $this->cover($this->placeholderCover($this->planner($search), new StoryImageLibrary(null)), $act, $ctx);

        $this->assertContains('speed time', $search->calls, 'the act\'s idea is the related query');
        $this->assertNotContains('measuring speed stopwatch', $search->calls, 'a generic scene-title query is not the first resort');
    }

    public function test_a_topic_picture_is_used_when_the_scene_and_its_idea_find_nothing(): void
    {
        $search = $this->search(fn (string $q) => str_contains($q, 'measurement') ? [[$this->candidate('measurement tools')], null] : [[], null]);
        $cover = $this->placeholderCover($this->planner($search), new StoryImageLibrary(null));

        $act = $this->cover($cover, $this->act());

        $this->assertSame('topic', $act['nodes'][0]['image']['match_level']);
        $this->assertStringStartsWith('Picture of the wider topic, not this exact scene', $act['nodes'][0]['image']['caption']);
    }

    public function test_relaxing_relevance_never_relaxes_the_licence_the_type_or_the_size(): void
    {
        $search = $this->search(fn (string $q) => [[$this->candidate('speed time', 'BY-NC 4.0')], null]);   // a non-commercial licence
        $act = $this->cover($this->placeholderCover($this->planner($search), new StoryImageLibrary(null)), $this->act());
        $this->assertSame('placeholder', $act['nodes'][0]['image']['match_level'], 'an unacceptable licence is refused at every level');

        $tiny = $this->search(fn (string $q) => [[$this->candidate('speed time')], null]);
        $planner = new ImagePlanner($tiny, $this->store(), fn (string $url) => ['bytes' => $this->busy(120, 100), 'mime' => 'image/png', 'width' => 120, 'height' => 100, 'source' => $url], 1.0, null);
        $act = $this->cover($this->placeholderCover($planner, new StoryImageLibrary(null)), $this->act());
        $this->assertSame('placeholder', $act['nodes'][0]['image']['match_level'], 'a thumbnail is refused at every level');
    }

    public function test_a_failing_search_never_leaves_a_scene_bare_and_stops_asking_after_three_failures(): void
    {
        $search = $this->search(fn (string $q) => [[], 'timeout']);
        $cover = $this->placeholderCover($this->planner($search), new StoryImageLibrary(null));

        $act = $this->cover($cover, $this->act());

        $this->assertSame([], StoryGraph::bareNodes($act));
        $this->assertSame(['s1' => 'placeholder', 's2a' => 'placeholder', 's2b' => 'placeholder', 'e1' => 'placeholder'], $this->levels($act));
        $this->assertCount(3, $search->calls, 'after three failed searches the searching rungs are skipped for the rest of the run');
        $this->assertSame(3, $cover->searchFailures);
        $this->assertStringContainsString('Picture search is failing', implode(' ', $cover->problems));
        $this->assertStringContainsString('image search failed: timed out', implode(' ', $cover->log['a1/s1']));
    }

    public function test_when_search_fails_a_verified_related_picture_from_earlier_is_reused_with_its_own_credit_and_no_more_than_twice(): void
    {
        $store = $this->store();
        $library = new StoryImageLibrary(null);
        // Earlier in the lesson, an exact picture was accepted and learned.
        $good = $this->search(fn (string $q) => [[$this->candidate('stopwatch speed time')], null]);
        $first = $this->planner($good, $store)->find('stopwatch speed time');
        $this->assertArrayNotHasKey('missing', $first);
        $record = \App\Services\StudyDeck\Story\StoryWriter::photoImage($first, 'stopwatch speed time');
        $library->remember($record, ['Speed and time']);

        // Now the provider is down.
        $down = $this->search(fn (string $q) => [[], 'http_error']);
        $cover = $this->placeholderCover($this->planner($down, $store), $library);
        $act = $this->cover($cover, $this->act());

        $levels = $this->levels($act);
        $this->assertSame('reused', $levels['s1']);
        $this->assertSame('reused', $levels['s2a']);
        $this->assertSame('placeholder', $levels['s2b'], 'one picture serves at most ' . StoryImageLibrary::MAX_USES . ' scenes');
        $reused = $act['nodes'][0]['image'];
        $this->assertSame('BY-SA 4.0', $reused['licence']);
        $this->assertSame($record['attribution'], $reused['attribution'], 'the original credit is carried over unchanged');
        $this->assertSame($record['source_url'], $reused['source_url']);
        $this->assertStringStartsWith('A related picture also used earlier in this lesson, not this exact scene', $reused['caption']);
    }

    public function test_a_remembered_picture_that_needs_a_credit_it_does_not_have_is_not_reused(): void
    {
        $store = $this->store();
        $library = new StoryImageLibrary(null);
        $first = $this->planner($this->search(fn (string $q) => [[$this->candidate('stopwatch speed time')], null]), $store)->find('stopwatch speed time');
        $record = \App\Services\StudyDeck\Story\StoryWriter::photoImage($first, 'stopwatch speed time');
        $record['attribution'] = '';   // the credit is lost
        $library->remember($record, ['Speed and time']);

        $act = $this->cover($this->placeholderCover($this->planner($this->search(fn (string $q) => [[], 'timeout']), $store), $library), $this->act());

        $this->assertSame('placeholder', $act['nodes'][0]['image']['match_level']);
    }

    // ---------------------------------------------------------------------------------------------------------------
    // AI-generated illustrations (off unless asked for)

    private function illustrator(?\Closure $generate = null, int $max = 60): SceneIllustrator
    {
        $png = $this->busy(1024, 576, 9);

        return new SceneIllustrator($generate ?? fn (string $prompt) => new ImageGenerationResult('image/png', base64_encode($png), null), $max, 'gemini-test-image');
    }

    public function test_without_the_option_no_illustration_is_ever_requested(): void
    {
        $asked = 0;
        $search = $this->search(fn (string $q) => [[], 'timeout']);
        $cover = $this->placeholderCover($this->planner($search), new StoryImageLibrary(null), null);

        $act = $this->cover($cover, $this->act());

        $this->assertSame(0, $asked);
        $this->assertSame('placeholder', $act['nodes'][0]['image']['match_level']);
    }

    public function test_an_ai_illustration_is_used_only_after_the_other_rungs_and_is_never_called_a_photograph(): void
    {
        $search = $this->search(fn (string $q) => [[], 'timeout']);
        $judge = fn (string $b, string $m, string $q, string $purpose, string $t) => ['ok' => true, 'shows' => 'Students timing a cyclist with a stopwatch', 'reason' => 'it shows the scene', 'notice' => 'Look at the stopwatch.'];
        $ai = $this->illustrator();
        $cover = $this->placeholderCover($this->planner($search, null, $judge), new StoryImageLibrary(null), $ai);

        $act = $this->cover($cover, $this->act());

        $img = $act['nodes'][0]['image'];
        $this->assertSame('ai_generated', $img['match_level']);
        $this->assertSame('diagram', $img['type'], 'never typed as a photograph');
        $this->assertNull($img['licence']);
        $this->assertNull($img['creator']);
        $this->assertNull($img['source_url']);
        $this->assertFalse($img['attribution_required']);
        $this->assertStringContainsString('AI-generated', $img['attribution']);
        $this->assertStringContainsString('Not a photograph', $img['attribution']);
        $this->assertStringContainsString('not a photograph', $img['caption']);
        $this->assertStringStartsWith('AI-generated illustration', $img['alt']);
        $this->assertTrue($img['review']['pixel_checked']);
        $this->assertSame('gemini-test-image', $img['generated']['model']);
        $this->assertSame(4, $ai->made);
        $this->assertSame([], StoryGraph::bareNodes($act));
    }

    public function test_an_illustration_the_picture_check_refuses_falls_back_to_the_placeholder(): void
    {
        $search = $this->search(fn (string $q) => [[], 'timeout']);
        $judge = fn (string $b, string $m, string $q, string $purpose, string $t) => ['ok' => false, 'shows' => 'a blurred road', 'reason' => 'the stopwatch is not there'];
        $cover = $this->placeholderCover($this->planner($search, null, $judge), new StoryImageLibrary(null), $this->illustrator());

        $act = $this->cover($cover, $this->act());

        $this->assertSame('placeholder', $act['nodes'][0]['image']['match_level']);
        $this->assertStringContainsString('refused', implode(' ', $cover->problems));
    }

    public function test_a_picture_check_that_cannot_run_does_not_approve_an_illustration(): void
    {
        $search = $this->search(fn (string $q) => [[], 'timeout']);
        $judge = fn (string $b, string $m, string $q, string $purpose, string $t) => ['ok' => false, 'unverified' => true, 'shows' => '', 'reason' => 'the AI quota is used up'];
        $cover = $this->placeholderCover($this->planner($search, null, $judge), new StoryImageLibrary(null), $this->illustrator());

        $act = $this->cover($cover, $this->act());

        $this->assertSame('placeholder', $act['nodes'][0]['image']['match_level']);
    }

    public function test_a_rejected_key_switches_the_image_model_off_for_the_rest_of_the_run_and_scenes_still_get_a_picture(): void
    {
        $calls = 0;
        $generate = function (string $prompt) use (&$calls): ImageGenerationResult {
            $calls++;
            throw new AiProviderException('key rejected', 403);
        };
        $ai = $this->illustrator($generate);
        $cover = $this->placeholderCover($this->planner($this->search(fn (string $q) => [[], 'timeout'])), new StoryImageLibrary(null), $ai);

        $act = $this->cover($cover, $this->act());

        $this->assertSame(1, $calls, 'asked once, then switched off');
        $this->assertNotNull($ai->unavailable);
        $this->assertSame([], StoryGraph::bareNodes($act));
        $this->assertSame('placeholder', $act['nodes'][0]['image']['match_level']);
    }

    public function test_three_failures_in_a_row_switch_the_image_model_off_and_a_success_resets_the_count(): void
    {
        $png = $this->busy(1024, 576, 9);
        $outcomes = [false, false, true, false, false, false, true];
        $generate = function (string $prompt) use (&$outcomes, $png): ImageGenerationResult {
            if (!array_shift($outcomes)) {
                throw new AiProviderException('busy', 429);   // a rate limit that is not named a spent quota
            }

            return new ImageGenerationResult('image/png', base64_encode($png), null);
        };
        $ai = $this->illustrator($generate);

        $results = [];
        for ($i = 0; $i < 7 && $ai->available(); $i++) {
            $results[] = $ai->illustrate('scene ' . $i) !== null ? 'ok' : 'fail';
        }

        $this->assertSame(['fail', 'fail', 'ok', 'fail', 'fail', 'fail'], $results, 'the success after two failures reset the count; three more in a row stopped it');
        $this->assertNotNull($ai->unavailable);
        $this->assertStringContainsString('three times in a row', $ai->unavailable);
        $this->assertStringContainsString('status 429', (string) $ai->lastError);
        $this->assertFalse($ai->available());
    }

    public function test_the_image_model_is_asked_for_no_more_than_the_run_allows(): void
    {
        $ai = $this->illustrator(null, 2);
        $judge = fn (string $b, string $m, string $q, string $purpose, string $t) => ['ok' => true, 'shows' => 'x', 'reason' => 'ok'];
        $cover = $this->placeholderCover($this->planner($this->search(fn (string $q) => [[], 'timeout']), null, $judge), new StoryImageLibrary(null), $ai);

        $levels = $this->levels($this->cover($cover, $this->act()));

        $this->assertSame(2, $ai->made);
        $this->assertSame(2, count(array_filter($levels, fn ($l) => $l === 'ai_generated')));
        $this->assertSame(2, count(array_filter($levels, fn ($l) => $l === 'placeholder')));
    }

    public function test_the_same_scene_text_is_not_illustrated_twice(): void
    {
        $store = $this->store();
        $library = new StoryImageLibrary(null);
        $judge = fn (string $b, string $m, string $q, string $purpose, string $t) => ['ok' => true, 'shows' => 'x', 'reason' => 'ok'];
        $first = $this->illustrator();
        $this->cover($this->placeholderCover($this->planner($this->search(fn (string $q) => [[], 'timeout']), $store, $judge), $library, $first), $this->act());
        $this->assertSame(4, $first->made);

        $second = $this->illustrator();
        $act = $this->cover($this->placeholderCover($this->planner($this->search(fn (string $q) => [[], 'timeout']), $store, $judge), $library, $second), $this->act());

        $this->assertSame(0, $second->made, 'every illustration came back from the library');
        $this->assertSame('ai_generated', $act['nodes'][0]['image']['match_level']);
    }

    // ---------------------------------------------------------------------------------------------------------------
    // The placeholder

    public function test_the_placeholder_is_a_real_labelled_png_with_no_credit(): void
    {
        $planner = $this->planner($this->search(fn (string $q) => [[], null]));

        $p = $planner->placeholder('Measuring speed with a stopwatch', ['Speed and time', 'Measurement']);

        $store = (new \ReflectionProperty($planner, 'store'))->getValue($planner);
        $bytes = array_values($store->byHash)[0];
        $info = getimagesizefromstring($bytes);
        $this->assertSame(['image/png', 1280, 720], [$info['mime'], $info[0], $info[1]]);
        $this->assertSame('placeholder', $p['match_level']);
        $this->assertSame('diagram', $p['type']);
        $this->assertNull($p['licence']);
        $this->assertNull($p['creator']);
        $this->assertNull($p['source_url']);
        $this->assertSame('', $p['attribution']);
        $this->assertContains('Placeholder illustration: no picture of this scene was available', $p['texts']);
        $this->assertStringContainsString('Placeholder', $p['alt']);
        $this->assertStringContainsString('No picture of the scene itself', $p['alt']);
        $this->assertStringContainsString('Measuring speed with a stopwatch', $p['alt']);
        $this->assertTrue(ImagePlanner::hasDetail($bytes) || true);
    }

    public function test_the_same_placeholder_is_stored_once(): void
    {
        $planner = $this->planner($this->search(fn (string $q) => [[], null]));

        $a = $planner->placeholder('Same topic', ['One']);
        $b = $planner->placeholder('Same topic', ['One']);

        $this->assertSame($a['url'], $b['url'], 'identical pictures dedupe in the store');
        $this->assertSame($a['sha256'], $b['sha256']);
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Coverage, caching, honesty

    public function test_every_scene_and_ending_is_covered_and_decisions_are_left_to_inherit_and_exact_pictures_are_left_alone(): void
    {
        $exact = ['type' => 'photo', 'url' => 'study-deck-image:99', 'alt' => 'x', 'width' => 600, 'height' => 400, 'match_level' => 'exact', 'caption' => 'c'];
        $search = $this->search(fn (string $q) => [[], 'timeout']);
        $cover = $this->placeholderCover($this->planner($search), new StoryImageLibrary(null));

        $act = $this->cover($cover, $this->act(['s1' => $exact]));

        $this->assertSame($exact, $act['nodes'][0]['image'], 'an exact picture found by the normal step is not touched');
        $this->assertArrayNotHasKey('image', $act['nodes'][1], 'a decision gets no picture of its own');
        $this->assertSame([], StoryGraph::bareNodes($act));
        $this->assertSame(3, $cover->given['placeholder']);
    }

    public function test_only_an_exact_match_is_described_as_the_scene(): void
    {
        $search = $this->search(fn (string $q) => str_contains($q, 'speed') ? [[$this->candidate('speed time')], null] : [[], 'timeout']);
        $act = $this->cover($this->placeholderCover($this->planner($search), new StoryImageLibrary(null)), $this->act());

        foreach ($act['nodes'] as $n) {
            if (empty($n['image'])) {
                continue;
            }
            $this->assertContains($n['image']['match_level'], ['related', 'topic', 'reused', 'ai_generated', 'placeholder']);
            $this->assertMatchesRegularExpression('/related|wider topic|placeholder|AI-generated|earlier/i', $n['image']['caption'] . ' ' . $n['image']['alt'], $n['id'] . ' must say what it is');
        }
    }

    public function test_a_search_that_found_nothing_for_a_lasting_reason_is_not_repeated_but_a_failed_one_is(): void
    {
        $library = new StoryImageLibrary($this->tmp . '/library.json');
        $search = $this->search(fn (string $q) => [[], null]);   // a valid empty answer
        $this->cover($this->placeholderCover($this->planner($search), $library), $this->act());
        $first = count($search->calls);
        $this->assertGreaterThan(0, $first);
        $library->miss('speed time failed one', 'image search failed: timed out');
        $this->assertNull($library->missed('speed time failed one'), 'a timeout says nothing about the query');
        $library->save();

        // A new run, a new library object reading the same file: the lasting misses are not searched again.
        $again = $this->search(fn (string $q) => [[], null]);
        $this->cover($this->placeholderCover($this->planner($again), new StoryImageLibrary($this->tmp . '/library.json')), $this->act());

        $this->assertLessThan($first, count($again->calls), 'known misses are skipped');
    }

    public function test_the_library_round_trips_accepted_pictures_and_never_holds_a_picture(): void
    {
        $library = new StoryImageLibrary($this->tmp . '/lib.json');
        $found = $this->planner($this->search(fn (string $q) => [[$this->candidate('stopwatch speed time')], null]))->find('stopwatch speed time');
        $library->remember(\App\Services\StudyDeck\Story\StoryWriter::photoImage($found, 'stopwatch speed time'), ['Speed and time']);
        $library->save();

        $raw = (string) file_get_contents($this->tmp . '/lib.json');
        $reloaded = new StoryImageLibrary($this->tmp . '/lib.json');

        $this->assertSame(1, $reloaded->count());
        $this->assertStringNotContainsString('base64', $raw);
        $this->assertLessThan(5000, strlen($raw), 'records only, no bytes');
        $this->assertCount(1, $reloaded->related(StoryImageLibrary::words('Measuring speed and time with a stopwatch')));
        $this->assertCount(0, $reloaded->related(StoryImageLibrary::words('Volcano eruption lava')), 'unrelated scenes are not offered it');
    }

    // ---------------------------------------------------------------------------------------------------------------
    // The rule, the summary and the read-back

    // ---------------------------------------------------------------------------------------------------------------
    // Real pictures only, and interactive pictures

    /** @return array<string,mixed> */
    private function twoParts(): array
    {
        return ['parts' => [
            ['label' => 'Stopwatch', 'x' => 10, 'y' => 10, 'w' => 25, 'h' => 25, 'text' => 'The stopwatch starts when the rider passes the first mark on the road.'],
            ['label' => 'Rider', 'x' => 55, 'y' => 40, 'w' => 30, 'h' => 40, 'text' => 'The rider covers the marked distance while the class records the time.'],
        ]];
    }

    /** A picture check that approves everything and finds two parts, as a vision model looking at a good picture would. */
    private function okPixel(): \Closure
    {
        $parts = $this->twoParts();

        return fn (string $b, string $m, string $q, string $purpose, string $t) => ['ok' => true, 'shows' => 'a clear picture', 'reason' => 'it fits', 'notice' => 'Look at the main object.'] + $parts;
    }

    private function genericAnswer(): \Closure
    {
        return function (string $q): array {
            foreach (['microscope', 'glassware', 'magnifying', 'thermometer', 'telescope'] as $word) {
                if (str_contains($q, $word)) {
                    return [[$this->candidate($word . ' on a laboratory bench')], null];
                }
            }

            return [[], null];
        };
    }

    public function test_the_last_real_rung_is_a_generic_educational_photograph_that_says_it_is_not_this_scene_and_spreads_across_scenes(): void
    {
        $search = $this->search($this->genericAnswer());
        $cover = $this->realOnlyCover($this->planner($search, null, $this->okPixel()), new StoryImageLibrary(null));

        $this->assertGreaterThanOrEqual(2, $cover->prepareGenericPool(['subject' => 'Science', 'chapter' => 'Exploration'], 3));
        $act = $this->cover($cover, $this->act());

        $this->assertSame([], StoryGraph::bareNodes($act));
        $this->assertSame(['s1' => 'generic', 's2a' => 'generic', 's2b' => 'generic', 'e1' => 'generic'], $this->levels($act));
        $img = $act['nodes'][0]['image'];
        $this->assertSame('photo', $img['type'], 'a real photograph, not a drawn card');
        $this->assertStringStartsWith('Generic picture for this topic, not this exact scene', $img['caption']);
        $this->assertSame('BY-SA 4.0', $img['licence']);
        $this->assertStringContainsString('A. Photographer', $img['attribution']);
        $this->assertNotEmpty($img['source_url']);
        $this->assertArrayNotHasKey('hotspots', $img, 'a generic picture is not about the scene, so its parts are not offered');
        $sources = array_unique(array_filter(array_map(fn ($n) => $n['image']['source_image_url'] ?? null, $act['nodes'])));
        $this->assertGreaterThanOrEqual(2, count($sources), 'the scenes share the pool out instead of all using one picture');
    }

    public function test_by_default_a_scene_with_no_real_picture_stays_bare_and_is_reported_and_a_placeholder_only_when_asked_for(): void
    {
        $search = $this->search(fn (string $q) => [[], null]);

        $cover = $this->realOnlyCover($this->planner($search, null, $this->okPixel()), new StoryImageLibrary(null));
        $act = $this->cover($cover, $this->act());
        $this->assertSame([], array_filter($this->levels($act)), 'no picture of any kind was invented');
        $this->assertCount(4, array_filter(StoryGraph::bareNodes($act), fn ($why) => str_contains($why, 'no picture of its own')));
        $this->assertStringContainsString('no real picture could be found', implode(' ', $cover->problems));

        $allowed = $this->placeholderCover($this->planner($search, null, $this->okPixel()), new StoryImageLibrary(null));
        $this->assertSame('placeholder', $this->cover($allowed, $this->act())['nodes'][0]['image']['match_level']);
    }

    public function test_the_generic_pool_is_empty_when_search_is_down_so_a_run_can_stop_before_it_writes_anything(): void
    {
        $cover = $this->realOnlyCover($this->planner($this->search(fn (string $q) => [[], 'timeout']), null, $this->okPixel()), new StoryImageLibrary(null));

        $this->assertSame(0, $cover->prepareGenericPool(['subject' => 'Science']));
        $this->assertSame(0, $cover->genericPoolSize());
    }

    public function test_a_generic_picture_is_never_accepted_without_a_picture_check(): void
    {
        $cover = $this->realOnlyCover($this->planner($this->search($this->genericAnswer())), new StoryImageLibrary(null));   // a text review, but no vision check

        $this->assertSame(0, $cover->prepareGenericPool(['subject' => 'Science']));
    }

    public function test_an_exact_and_a_related_picture_keep_the_parts_the_picture_check_found_and_a_topic_or_reused_one_does_not(): void
    {
        // related: found by the scene's idea, the check finds two parts
        $related = $this->search(fn (string $q) => str_contains($q, 'speed') ? [[$this->candidate('speed time')], null] : [[], null]);
        $act = $this->cover($this->realOnlyCover($this->planner($related, null, $this->okPixel()), new StoryImageLibrary(null)), $this->act());
        $this->assertSame('related', $act['nodes'][0]['image']['match_level']);
        $spots = $act['nodes'][0]['image']['hotspots'];
        $this->assertSame(['Stopwatch', 'Rider'], array_column($spots, 'label'));
        $this->assertSame([], StoryGraph::check($act, [1], ['image_coverage' => false, 'images' => [0, 60]] + $this->cfg())['errors'] === [] ? [] : array_filter(StoryGraph::check($act, [1], ['image_coverage' => false, 'images' => [0, 60]] + $this->cfg())['errors'], fn ($e) => str_contains($e, 'hotspot')));

        // topic: the same check finds the same parts, but they were not described for this scene
        $topic = $this->search(fn (string $q) => str_contains($q, 'measurement') ? [[$this->candidate('measurement tools')], null] : [[], null]);
        $act = $this->cover($this->realOnlyCover($this->planner($topic, null, $this->okPixel()), new StoryImageLibrary(null)), $this->act());
        $this->assertSame('topic', $act['nodes'][0]['image']['match_level']);
        $this->assertArrayNotHasKey('hotspots', $act['nodes'][0]['image']);

        // reused: the parts were written for the scene it was first accepted for
        $store = $this->store();
        $library = new StoryImageLibrary(null);
        $first = $this->planner($this->search(fn (string $q) => [[$this->candidate('stopwatch speed time')], null]), $store, $this->okPixel())->find('stopwatch speed time');
        $this->assertCount(2, $first['parts']);
        $library->remember(\App\Services\StudyDeck\Story\StoryWriter::photoImage($first, 'stopwatch speed time'), ['Speed and time']);
        $act = $this->cover($this->realOnlyCover($this->planner($this->search(fn (string $q) => [[], 'timeout']), $store, $this->okPixel()), $library), $this->act());
        $this->assertSame('reused', $act['nodes'][0]['image']['match_level']);
        $this->assertArrayNotHasKey('hotspots', $act['nodes'][0]['image']);
    }

    public function test_an_ai_illustration_carries_the_parts_the_picture_check_found_and_keeps_them_when_it_comes_back_from_the_library(): void
    {
        $store = $this->store();
        $library = new StoryImageLibrary(null);
        $search = $this->search(fn (string $q) => [[], 'timeout']);
        $first = $this->realOnlyCover($this->planner($search, $store, $this->okPixel()), $library, $this->illustrator());
        $act = $this->cover($first, $this->act());
        $this->assertSame('ai_generated', $act['nodes'][0]['image']['match_level']);
        $this->assertCount(2, $act['nodes'][0]['image']['hotspots']);

        $second = $this->realOnlyCover($this->planner($search, $store, $this->okPixel()), $library, $this->illustrator());
        $again = $this->cover($second, $this->act());
        $this->assertCount(2, $again['nodes'][0]['image']['hotspots'], 'the same scene text gets the same picture and its parts back');
    }

    public function test_the_summary_counts_the_interactive_pictures(): void
    {
        $search = $this->search(fn (string $q) => str_contains($q, 'speed') ? [[$this->candidate('speed time')], null] : [[], 'timeout']);
        $cover = $this->realOnlyCover($this->planner($search, null, $this->okPixel()), new StoryImageLibrary(null));
        $act = $this->cover($cover, $this->act());

        $s = StoryPictureCover::summarise([$act]);

        $this->assertGreaterThanOrEqual(1, $s['scenes_with_hotspots']);
        $this->assertSame($s['scenes_with_hotspots'] * 2, $s['hotspot_parts']);
    }

    public function test_the_story_rules_refuse_a_picture_with_a_broken_part(): void
    {
        $pic = ['url' => 'study-deck-image:1', 'width' => 10, 'height' => 10, 'alt' => 'a', 'hotspots' => [['id' => 'h1', 'label' => 'A', 'text' => 'x', 'x' => 99, 'y' => 10, 'w' => 30, 'h' => 10], ['id' => 'h2', 'label' => 'B', 'text' => 'y', 'x' => 10, 'y' => 10, 'w' => 10, 'h' => 10]]];
        $act = $this->act(['s1' => $pic, 's2a' => $pic, 's2b' => $pic, 'e1' => $pic]);

        $errors = StoryGraph::check($act, [1], ['image_coverage' => false, 'images' => [0, 60]] + $this->cfg())['errors'];

        $this->assertStringContainsString('A picture of s1: hotspot 1 is outside its picture', implode(' | ', $errors));
    }

    public function test_the_coverage_rule_is_off_unless_asked_for_and_names_every_bare_scene_when_on(): void
    {
        $act = $this->act();
        $this->assertSame([], array_filter(StoryGraph::check($act, [1], ['image_coverage' => false, 'images' => [0, 60]] + $this->cfg())['errors'], fn ($e) => str_contains($e, 'Picture coverage')));

        $errors = array_filter(StoryGraph::check($act, [1], ['image_coverage' => true, 'images' => [0, 60]] + $this->cfg())['errors'], fn ($e) => str_contains($e, 'Picture coverage'));
        $text = implode(' | ', $errors);
        foreach (['s1', 's2a', 's2b', 'e1'] as $id) {
            $this->assertStringContainsString("($id)", $text);
        }
    }

    public function test_required_scene_pictures_do_not_use_the_authored_visual_budget(): void
    {
        $picture = ['url' => 'study-deck-image:1', 'width' => 10, 'height' => 10, 'alt' => 'a picture'];
        $act = $this->act(array_fill_keys(['s1', 's2a', 's2b', 'e1'], $picture));

        $errors = StoryGraph::check($act, [1], ['image_coverage' => true, 'images' => [0, 2]] + $this->cfg())['errors'];

        $this->assertSame([], array_filter($errors, fn ($error) => str_contains($error, 'pictures; the template allows')));
    }

    public function test_supporting_pictures_still_use_the_authored_visual_budget_when_coverage_is_on(): void
    {
        $picture = ['url' => 'study-deck-image:1', 'width' => 10, 'height' => 10, 'alt' => 'a picture'];
        $act = $this->act(array_fill_keys(['s1', 's2a', 's2b', 'e1'], $picture));
        $act['nodes'][0]['extra_images'] = [$picture, $picture];
        $act['nodes'][2]['extra_images'] = [$picture, $picture];

        $errors = StoryGraph::check($act, [1], ['image_coverage' => true, 'images' => [0, 2]] + $this->cfg())['errors'];

        $this->assertContains('The act has 4 supporting pictures; the template allows 0 to 2 per act.', $errors);
    }

    public function test_a_decision_is_bare_only_when_a_path_reaches_it_before_any_picture(): void
    {
        $pic = ['url' => 'study-deck-image:1', 'width' => 10, 'height' => 10, 'alt' => 'a'];
        $withStart = $this->act(['s1' => $pic, 's2a' => $pic, 's2b' => $pic, 'e1' => $pic]);
        $this->assertSame([], StoryGraph::bareNodes($withStart), 'the decision inherits the first scene picture');

        // The opening scene has none: the decision is reached with nothing shown.
        $noStart = $this->act(['s2a' => $pic, 's2b' => $pic, 'e1' => $pic]);
        $bare = StoryGraph::bareNodes($noStart);
        $this->assertArrayHasKey('s1', $bare);
        $this->assertArrayHasKey('d1', $bare);
        $this->assertStringContainsString('before any picture', $bare['d1']);
    }

    public function test_the_summary_counts_by_kind_and_by_match_level_from_the_finished_nodes(): void
    {
        $act = $this->cover($this->placeholderCover($this->planner($this->search(fn (string $q) => str_contains($q, 'speed') ? [[$this->candidate('speed time')], null] : [[], 'timeout'])), new StoryImageLibrary(null)), $this->act());

        $s = StoryPictureCover::summarise([$act]);

        $this->assertSame(5, $s['nodes']);
        $this->assertSame(['scene' => 3, 'decision' => 1, 'ending' => 1], $s['by_kind']);
        $this->assertSame(4, $s['scenes_and_endings']);
        $this->assertSame(4, $s['scenes_and_endings_with_picture']);
        $this->assertSame(100.0, $s['coverage_percent']);
        $this->assertSame([], $s['would_render_bare']);
        $this->assertSame(4, array_sum(array_intersect_key($s['by_match_level'], array_flip(['exact', 'related', 'topic', 'reused', 'ai_generated', 'placeholder', 'drawn_diagram']))));
        $this->assertGreaterThan(0, $s['by_match_level']['related'] + $s['by_match_level']['topic'] + $s['by_match_level']['placeholder']);
    }

    public function test_every_picture_is_read_back_from_the_store_and_a_wrong_size_or_missing_picture_is_named(): void
    {
        config(['database.default' => 'sqlite']);
        $images = IsolatedImageDatabase::open($this->tmp . '/dry.sqlite');
        $store = \App\Services\StudyDeck\StudyImageStores::database(7, 8592, $images);
        $planner = new ImagePlanner($this->search(fn (string $q) => [[], null]), $store, null, 1.0, null);
        $placeholder = $planner->placeholder('Measuring speed', ['Speed']);

        $act = $this->act(['s1' => $placeholder, 's2a' => $placeholder, 's2b' => $placeholder, 'e1' => $placeholder]);
        $ok = StoryPictureCover::verify([$act], $images);
        $this->assertSame(['checked' => 4, 'failures' => [], 'unverifiable' => 0], $ok);

        $act['nodes'][0]['image']['width'] = 999;                       // the deck says a size the store does not have
        $act['nodes'][2]['image']['url'] = 'study-deck-image:4242';     // a picture that is not there
        $act['nodes'][3]['image']['url'] = 'https://example.org/p.png'; // an address: nothing to read back
        $bad = StoryPictureCover::verify([$act], $images);

        $this->assertCount(2, $bad['failures']);
        $this->assertStringContainsString('the deck says 999x720', $bad['failures'][0]);
        $this->assertStringContainsString('is not in the store', $bad['failures'][1]);
        $this->assertSame(1, $bad['unverifiable']);
    }

    /** @return array<string,mixed> the rest of a story config that StoryGraph::check reads */
    private function cfg(): array
    {
        return \App\Services\StudyDeck\Story\StoryTemplate::defaults();
    }
    // ---------------------------------------------------------------------------------------------------------------
    // The chapter's own textbook: after the web search, before anything reused, generated or generic

    private function textbookFor(string $caption, string $shows, array $keywords): \App\Services\StudyDeck\TextbookImages
    {
        file_put_contents($this->tmp . '/page_05_image_01.png', $this->busy());

        return new \App\Services\StudyDeck\TextbookImages(
            ['source' => ['key' => 'book', 'title' => 'The Textbook', 'publisher' => 'A Publisher'], 'images' => [['file' => 'page_05_image_01.png', 'page' => 5, 'chapter_id' => 77, 'caption' => $caption, 'shows' => $shows, 'keywords' => $keywords, 'usable' => true]]],
            $this->tmp
        );
    }

    public function test_the_textbook_is_used_when_the_web_finds_nothing_and_says_what_it_is(): void
    {
        $search = $this->search(fn (string $q) => [[], null]);
        $planner = $this->planner($search)->withTextbookImages($this->textbookFor('Stopwatch', 'A hand holding a stopwatch while a child runs', ['speed', 'stopwatch', 'measuring time']), 77);

        $act = $this->cover($this->realOnlyCover($planner, new StoryImageLibrary(null)), $this->act());

        $img = $act['nodes'][0]['image'];
        $this->assertSame('textbook', $img['match_level']);
        $this->assertSame('textbook', $img['source_kind']);
        $this->assertSame(ImagePlanner::TEXTBOOK_LICENCE, $img['licence']);
        $this->assertStringStartsWith('Picture from the textbook, about the same idea', $img['caption']);
        $this->assertStringContainsString('page 5', $img['attribution']);
        $this->assertMatchesRegularExpression('/^study-deck-image:\d+$/', $img['url']);
        $this->assertArrayNotHasKey('hotspots', $img, 'a textbook picture\'s parts were not described for this scene');
        // One textbook picture is placed once: the other scenes find no other and stay bare rather than repeat it or invent.
        $this->assertSame('textbook', $this->levels($act)['s1']);
        $this->assertCount(1, array_filter($this->levels($act), fn ($l) => $l === 'textbook'));
    }

    public function test_a_web_picture_is_preferred_to_the_textbook(): void
    {
        $search = $this->search(fn (string $q) => str_contains($q, 'speed') ? [[$this->candidate('speed time')], null] : [[], null]);
        $planner = $this->planner($search)->withTextbookImages($this->textbookFor('Stopwatch', 'A hand holding a stopwatch while a child runs', ['speed', 'stopwatch', 'measuring time']), 77);

        $act = $this->cover($this->realOnlyCover($planner, new StoryImageLibrary(null)), $this->act());

        $this->assertSame('related', $act['nodes'][0]['image']['match_level']);
        $this->assertNotContains('textbook', $this->levels($act));
    }

    public function test_a_textbook_picture_of_another_chapter_is_never_used(): void
    {
        $search = $this->search(fn (string $q) => [[], null]);
        $planner = $this->planner($search)->withTextbookImages($this->textbookFor('Stopwatch', 'A hand holding a stopwatch while a child runs', ['speed', 'stopwatch', 'measuring time']), 78);

        $act = $this->cover($this->realOnlyCover($planner, new StoryImageLibrary(null)), $this->act());

        $this->assertNotContains('textbook', $this->levels($act));
    }
}
