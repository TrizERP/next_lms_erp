<?php

namespace Tests\Unit\CoursePresentation;

use App\Services\CoursePresentation\PresentationQuality;
use App\Services\StudyDeck\Story\StoryTemplate;
use PHPUnit\Framework\TestCase;

/**
 * NO DATABASE and no framework: the quality rules read only the deck array they are given.
 */
class PresentationQualityTest extends TestCase
{
    private function slide(int $n, array $over = []): array
    {
        return array_replace_recursive([
            'n' => $n, 'section' => 'T', 'slide_type' => 'concept_intro', 'title' => "Slide $n", 'concept_ids' => [$n], 'taught_concept_ids' => [$n],
            'content' => ['body' => '', 'explanations' => [['concept_id' => $n, 'text' => "Explanation number $n, written in its own words so that it is unique."]], 'bullets' => [], 'misconception' => null, 'discussion' => null],
            'interaction' => null, 'image' => null,
        ], $over);
    }

    private function deck(array $slides, array $concepts = [1, 2, 3]): array
    {
        return ['version' => 3, 'slides' => $slides, 'concepts' => array_combine(array_map('strval', $concepts), array_map(fn ($c) => ['name' => "Concept $c"], $concepts))];
    }

    private function codes(array $deck): array
    {
        return array_column((new PresentationQuality())->check($deck), 'code');
    }

    public function test_a_clean_deck_has_no_problems(): void
    {
        $this->assertSame([], $this->codes($this->deck([$this->slide(1), $this->slide(2), $this->slide(3)])));
    }

    public function test_a_second_topic_list_slide_is_reported_the_first_is_not(): void
    {
        $hook = fn (int $n) => $this->slide($n, ['slide_type' => 'hook', 'title' => "Topic $n", 'taught_concept_ids' => [], 'content' => ['explanations' => [], 'body' => "In this topic: something $n and more words."]]);
        $deck = $this->deck([$hook(1), $this->slide(2), $hook(3), $this->slide(4), $hook(5)], [2, 4]);
        $found = array_values(array_filter((new PresentationQuality())->check($deck), fn ($p) => $p['code'] === 'repeated_index'));

        $this->assertSame([3, 5], array_column($found, 'slide'), 'the overview may appear once; every later one is a fault');
    }

    public function test_a_framing_slide_with_points_or_an_interaction_is_not_a_topic_list(): void
    {
        $withPoints = $this->slide(1, ['slide_type' => 'objectives', 'taught_concept_ids' => [], 'content' => ['explanations' => [], 'bullets' => ['One point', 'Another point']]]);
        $withReveal = $this->slide(2, ['slide_type' => 'hook', 'taught_concept_ids' => [], 'content' => ['explanations' => [], 'bullets' => []], 'interaction' => ['kind' => 'reveal', 'items' => [['id' => 'a', 'label' => 'A', 'text' => 'a'], ['id' => 'b', 'label' => 'B', 'text' => 'b']]]]);

        $this->assertNotContains('repeated_index', $this->codes($this->deck([$withPoints, $withReveal, $this->slide(3)], [3])));
    }

    public function test_repeated_titles_and_repeated_explanations_are_reported(): void
    {
        $same = 'The very same long explanation sentence that is copied onto two different slides.';
        $a = $this->slide(1, ['title' => 'Same title', 'content' => ['explanations' => [['concept_id' => 1, 'text' => $same]]]]);
        $b = $this->slide(2, ['title' => 'Same title', 'content' => ['explanations' => [['concept_id' => 2, 'text' => $same]]]]);

        $codes = $this->codes($this->deck([$a, $b], [1, 2]));
        $this->assertContains('duplicate_title', $codes);
        $this->assertContains('duplicate_content', $codes);
    }

    public function test_empty_and_overfull_slides_are_reported(): void
    {
        $empty = $this->slide(1);
        $empty['content']['explanations'] = [];
        $dense = $this->slide(2, ['content' => ['bullets' => array_fill(0, 9, 'A point with several words in it')]]);
        $long = $this->slide(3, ['content' => ['explanations' => [['concept_id' => 3, 'text' => str_repeat('word ', 160)]]]]);

        $found = (new PresentationQuality())->check($this->deck([$empty, $dense, $long]));
        $byCode = [];
        foreach ($found as $p) {
            $byCode[$p['code']][] = $p['slide'];
        }
        $this->assertSame([1], $byCode['empty_slide']);
        $this->assertSame([2, 3], $byCode['too_dense']);
    }

    public function test_numbered_placeholder_headings_are_reported_but_real_titles_with_numbers_are_not(): void
    {
        $reveal = fn (array $labels) => ['kind' => 'reveal', 'items' => array_map(fn ($l, $i) => ['id' => 'k' . $i, 'label' => $l, 'text' => 'Some text'], $labels, array_keys($labels))];
        $bad = $this->slide(1, ['interaction' => $reveal(['Key idea 1', 'Key idea 2'])]);
        $good = $this->slide(2, ['interaction' => $reveal(["Newton's 2nd law", 'Estimating with an assumed rate'])]);
        $pair = $this->slide(3, ['interaction' => ['kind' => 'match', 'pairs' => [['id' => 'a', 'term' => 'Card 1', 'meaning' => 'M'], ['id' => 'b', 'term' => 'Law', 'meaning' => 'M'], ['id' => 'c', 'term' => 'Theory', 'meaning' => 'M']]]]);
        $title = $this->slide(4, ['title' => 'Item 4']);

        $found = (new PresentationQuality())->check($this->deck([$bad, $good, $pair, $title], [1, 2, 3, 4]));
        $byCode = array_values(array_filter($found, fn ($p) => $p['code'] === 'generic_label'));

        $this->assertSame([1, 1, 3, 4], array_column($byCode, 'slide'));
        $this->assertStringContainsString('Key idea 1', $byCode[0]['message']);
        $this->assertStringContainsString('Card 1', $byCode[2]['message']);
    }

    public function test_a_concept_no_slide_teaches_is_reported_by_name(): void
    {
        $found = (new PresentationQuality())->check($this->deck([$this->slide(1), $this->slide(2)], [1, 2, 3]));

        $this->assertSame(['missing_concept'], array_column($found, 'code'));
        $this->assertStringContainsString('Concept 3', $found[0]['message']);
    }

    public function test_pictures_that_cannot_be_drawn_are_reported(): void
    {
        $ok = ['type' => 'diagram', 'url' => 'study-deck-image:1', 'width' => 1280, 'height' => 720, 'alt' => 'A diagram'];
        $deck = $this->deck([$this->slide(1, ['image' => $ok]), $this->slide(2, ['image' => ['width' => 0] + $ok]), $this->slide(3, ['image' => ['url' => ''] + $ok]), $this->slide(4, ['image' => ['alt' => ''] + $ok])], [1, 2, 3, 4]);
        $found = array_values(array_filter((new PresentationQuality())->check($deck), fn ($p) => $p['code'] === 'bad_image'));

        $this->assertSame([2, 3, 4], array_column($found, 'slide'));
    }

    public function test_interactions_the_viewer_could_not_play_are_reported(): void
    {
        $spot = fn (string $id) => ['id' => $id, 'label' => 'L', 'x' => 10, 'y' => 10, 'w' => 5, 'h' => 5, 'text' => 'T'];
        $diagram = ['type' => 'diagram', 'url' => 'u', 'width' => 10, 'height' => 5, 'alt' => 'a'];
        $slides = [
            $this->slide(1, ['image' => $diagram, 'interaction' => ['kind' => 'hotspots', 'spots' => [$spot('a'), $spot('b')]]]),   // plays
            $this->slide(2, ['interaction' => ['kind' => 'hotspots', 'spots' => [$spot('a'), $spot('b')]]]),                          // no diagram
            $this->slide(3, ['image' => $diagram, 'interaction' => ['kind' => 'hotspots', 'spots' => [$spot('a'), $spot('a')]]]),      // repeated id
            $this->slide(4, ['interaction' => ['kind' => 'reveal', 'items' => [['id' => 'a', 'label' => 'A', 'text' => 'a']]]]),       // one card
            $this->slide(5, ['interaction' => ['kind' => 'match', 'pairs' => [['id' => 'a', 'term' => 'T', 'meaning' => 'M']]]]),      // one pair
            $this->slide(6, ['interaction' => ['kind' => 'swirl']]),                                                                    // unknown
            $this->slide(7, ['interaction' => ['kind' => 'reveal', 'items' => [['id' => 'a', 'label' => 'A', 'text' => 'a'], ['id' => 'b', 'label' => 'B', 'text' => 'b']]]]),
        ];
        $found = array_values(array_filter((new PresentationQuality())->check($this->deck($slides, range(1, 7))), fn ($p) => $p['code'] === 'broken_interaction'));

        $this->assertSame([2, 3, 4, 5, 6], array_column($found, 'slide'));
    }

    public function test_required_story_scene_pictures_do_not_use_the_supporting_picture_budget(): void
    {
        $nodes = [];
        for ($i = 1; $i <= 13; $i++) {
            $nodes[] = [
                'id' => 's' . $i, 'kind' => 'scene', 'title' => 'Scene ' . $i, 'body' => 'A scene.',
                'choices' => [], 'image' => ['url' => 'image:' . $i, 'width' => 640, 'height' => 480, 'alt' => 'Scene ' . $i],
            ];
        }
        $story = [
            'template' => StoryTemplate::KEY, 'start' => 's1', 'mission' => ['title' => 'Mission', 'body' => 'Learn.'], 'nodes' => $nodes,
        ];
        $deck = $this->deck([$this->slide(1, ['interaction' => $story])], [1]);
        $pictureErrors = fn (array $problems) => array_values(array_filter(
            array_column($problems, 'message'),
            fn ($message) => str_contains($message, 'pictures; the template allows')
        ));

        $this->assertNotEmpty($pictureErrors((new PresentationQuality())->check($deck)));
        $config = array_replace(StoryTemplate::defaults(), ['images' => [0, 60], 'image_coverage' => true]);
        $this->assertSame([], $pictureErrors((new PresentationQuality($config))->check($deck)));
    }
}
