<?php

namespace Tests\Unit\StudyDeck\Branching;

use App\Services\StudyDeck\Branching\Pro\ProScenarioConverter;
use App\Services\StudyDeck\Branching\Pro\ProScenarioFormat;
use App\Services\StudyDeck\Branching\Pro\ProScenarioGate;
use App\Services\StudyDeck\Branching\Pro\ProScenarioValidator;
use App\Services\StudyDeck\Story\StoryTemplate;
use PHPUnit\Framework\TestCase;

/**
 * Showing a stored story-format Branching Scenario in the pro format (read-only), and the rules for which chapters it applies to.
 * Pure: no database, no network, no model.
 */
class ProScenarioConverterTest extends TestCase
{
    private const CONFIG = ['enabled' => true, 'convert_existing' => true, 'standards' => ['8', '9'], 'subjects' => ['*'], 'chapter_numbers' => ['*'], 'exclude_subjects' => ['Testing by APi']];

    private function image(string $name, array $extra = []): array
    {
        return $extra + ['type' => 'photo', 'url' => "study-deck-image:$name", 'alt' => "A picture of $name", 'caption' => null, 'width' => 1000, 'height' => 700, 'licence' => 'CC BY 4.0', 'source_url' => 'https://example.org', 'creator' => 'A', 'attribution' => 'A', 'attribution_required' => true];
    }

    /** A story act: s1 -> d1 (sound: s2a, weaker: s2b) -> s3 (a merge, with a lead-in for each path) -> e1. */
    private function act(string $key, string $title, array $over = []): array
    {
        $scene = fn (string $id, string $next, array $extra = []) => $extra + ['id' => $id, 'kind' => 'scene', 'title' => "Scene $id of $title", 'body' => "Body of $id.", 'prompt' => '', 'concept_ids' => [7],
            'choices' => [['id' => "$id-1", 'text' => 'Continue', 'outcome' => '', 'why' => '', 'sound' => true, 'next' => $next]], 'image' => $this->image("$key$id")];

        return array_replace([
            'n' => 3, 'section' => $title, 'slide_type' => 'scenario', 'title' => "$title mission", 'concept_ids' => [7], 'taught_concept_ids' => [7], 'concepts' => ['Idea'],
            'relationship' => null, 'content' => ['body' => 'Your mission.', 'explanations' => [['concept_id' => 7, 'text' => 'An idea explained in the chapter.']], 'bullets' => [], 'example' => null, 'misconception' => null, 'relationship_note' => null, 'key_idea' => null, 'discussion' => null, 'bloom' => 'understand', 'dok' => 2, 'minutes' => 3],
            'question_ids' => [], 'activities' => [], 'h5p_pattern' => null, 'visual' => null, 'image' => null, 'image_missing' => null,
            'interaction' => ['kind' => 'scenario', 'template' => StoryTemplate::KEY, 'reason' => 'story', 'mission' => ['title' => 'M', 'body' => 'Your mission.'], 'situation' => 'S', 'start' => 's1', 'conclusion' => 'You know the idea now.', 'nodes' => [
                $scene('s1', 'd1'),
                ['id' => 'd1', 'kind' => 'decision', 'title' => "Choose in $title", 'body' => 'Set-up.', 'prompt' => 'What do you do?', 'concept_ids' => [7], 'choices' => [
                    ['id' => 'd1-1', 'text' => 'Sound approach', 'outcome' => 'It works out.', 'why' => 'Because of the idea.', 'sound' => true, 'next' => 's2a'],
                    ['id' => 'd1-2', 'text' => 'Weaker approach', 'outcome' => 'It half works.', 'why' => 'The idea was missed.', 'sound' => false, 'next' => 's2b'],
                ]],
                $scene('s2a', 's3'), $scene('s2b', 's3'),
                $scene('s3', 'e1', ['variants' => [['after' => 's2a', 'body' => 'You come from the sound path.'], ['after' => 's2b', 'body' => 'You come from the weaker path.']], 'explain' => ['title' => 'Why', 'body' => 'Because the idea says so.']]),
                ['id' => 'e1', 'kind' => 'ending', 'title' => "End of $title", 'body' => 'It ends.', 'prompt' => '', 'concept_ids' => [7], 'choices' => [], 'image' => $this->image("{$key}e1")],
            ]],
        ], $over);
    }

    /** @return array<string,mixed> */
    private function deck(int $acts = 2, string $standard = '8', string $subject = 'Science'): array
    {
        $slides = [['n' => 1, 'section' => 'Introduction', 'slide_type' => 'cover', 'title' => 'Chapter', 'concept_ids' => [], 'taught_concept_ids' => [], 'content' => ['body' => 'Hi', 'explanations' => []], 'interaction' => null, 'image' => null, 'activities' => []]];
        for ($k = 1; $k <= $acts; $k++) {
            $slides[] = ['n' => $k + 1] + $this->act("a$k", "Topic $k");
        }

        return ['version' => 3, 'chapter' => ['id' => 5, 'name' => 'Chapter', 'standard_name' => $standard, 'subject_name' => $subject, 'number' => 1], 'slide_count' => count($slides), 'outline' => [], 'concepts' => [7 => ['name' => 'Idea']], 'slides' => $slides, 'concept_slides' => [], 'taught_by' => []];
    }

    // ---- which chapters -------------------------------------------------------------------------------------------------------------

    public function test_standards_8_and_9_take_the_format_for_every_subject_and_chapter_and_nothing_else_does(): void
    {
        $at = fn (string $std, string $subject, int $n = 3) => ProScenarioGate::applies(['standard_name' => $std, 'subject_name' => $subject, 'sort_order' => $n], self::CONFIG);

        $this->assertTrue($at('8', 'Science'));
        $this->assertTrue($at('8', 'Hindi-A', 12));
        $this->assertTrue($at('9', 'Mathematics', 1));
        $this->assertTrue($at('Standard 9', 'English'));
        $this->assertFalse($at('4', 'Science'), 'Standard 4 is untouched');
        $this->assertFalse($at('10', 'Science'));
        $this->assertFalse($at('9', 'Testing by APi'), 'a test subject is not an academic subject');
        $this->assertFalse(ProScenarioGate::applies(['standard_name' => '8', 'subject_name' => 'Science'], ['enabled' => false] + self::CONFIG));
    }

    public function test_the_shipped_configuration_names_standards_8_and_9_and_the_two_test_subjects(): void
    {
        $config = require __DIR__ . '/../../../../config/study_deck_pro_branching.php';

        $this->assertSame(['8', '9'], $config['standards']);
        $this->assertSame(['*'], $config['subjects']);
        $this->assertSame(['*'], $config['chapter_numbers']);
        $this->assertContains('module testing by vivek', $config['exclude_subjects']);
        $this->assertContains('Testing by APi', $config['exclude_subjects']);
    }

    public function test_a_deck_is_converted_only_when_it_is_a_story_deck_of_a_named_chapter_with_conversion_on(): void
    {
        $this->assertTrue(ProScenarioConverter::applies($this->deck(), self::CONFIG));
        $this->assertFalse(ProScenarioConverter::applies($this->deck(2, '4'), self::CONFIG), 'Standard 4');
        $this->assertFalse(ProScenarioConverter::applies($this->deck(2, '9', 'Testing by APi'), self::CONFIG), 'a test subject');
        $this->assertFalse(ProScenarioConverter::applies($this->deck(), ['convert_existing' => false] + self::CONFIG), 'switched off');
        $this->assertFalse(ProScenarioConverter::applies(['slides' => [['n' => 1, 'interaction' => ['kind' => 'reveal']]], 'chapter' => ['standard_name' => '8', 'subject_name' => 'Science']], self::CONFIG), 'another Study Deck format');
    }

    public function test_a_deck_that_is_not_converted_is_returned_exactly_as_it_came(): void
    {
        $standard4 = $this->deck(2, '4');
        $other = ['slides' => [['n' => 1, 'title' => 'x', 'interaction' => ['kind' => 'reveal']]], 'chapter' => ['standard_name' => '8', 'subject_name' => 'Science']];

        $this->assertSame($standard4, ProScenarioConverter::forRead($standard4, self::CONFIG));
        $this->assertSame($other, ProScenarioConverter::forRead($other, self::CONFIG));
    }

    public function test_a_deck_already_in_the_pro_format_is_not_converted_again(): void
    {
        $once = ProScenarioConverter::convert($this->deck());

        $this->assertTrue(ProScenarioFormat::isPro($once));
        $this->assertFalse(ProScenarioConverter::applies($once, self::CONFIG));
        $this->assertSame($once, ProScenarioConverter::forRead($once, self::CONFIG));
    }

    // ---- the conversion ------------------------------------------------------------------------------------------------------------

    public function test_the_conversion_is_a_valid_graph_and_leaves_the_stored_deck_alone(): void
    {
        $stored = $this->deck();
        $copy = $stored;
        $deck = ProScenarioConverter::forRead($stored, self::CONFIG);

        $this->assertSame($copy, $stored, 'the stored deck is not changed');
        $this->assertNull(ProScenarioConverter::$lastRefusal);
        $this->assertSame([], ProScenarioValidator::check($deck['slides'], false, false));
        $this->assertSame('professional', $deck['theme']);
        $this->assertSame(count($deck['slides']), $deck['slide_count']);
        $this->assertSame(range(1, count($deck['slides'])), array_column($deck['slides'], 'n'));
        $this->assertSame('cover', $deck['slides'][0]['slide_type']);
    }

    public function test_every_decision_becomes_a_question_with_an_outcome_for_each_choice_and_the_paths_are_kept(): void
    {
        $deck = ProScenarioConverter::convert($this->deck(1));
        $by = array_column($deck['slides'], null, 'n');
        $decision = array_values(array_filter($deck['slides'], fn ($s) => ($s['interaction']['kind'] ?? '') === 'decision'))[0];

        $this->assertSame('What do you do?', $decision['interaction']['question']);
        $this->assertSame(-1, $decision['next']);
        $this->assertSame(['Sound approach', 'Weaker approach'], array_column($decision['interaction']['options'], 'text'));

        [$sound, $weak] = array_map(fn ($o) => $by[$o['goto']], $decision['interaction']['options']);
        $this->assertSame('sound', $sound['interaction']['tone']);
        $this->assertSame('It works out.', $sound['interaction']['what']);
        $this->assertSame('Because of the idea.', $sound['interaction']['why']);
        $this->assertSame('caution', $weak['interaction']['tone']);
        $this->assertSame('It half works.', $weak['interaction']['what']);
        $labels = array_column($weak['interaction']['actions'], 'label');
        $this->assertContains('Choose again', $labels);
        $this->assertContains('See the intelligent path', $labels);
        $this->assertContains('Choose again', array_column($sound['interaction']['actions'], 'label'));

        // the story's own ways on are the outcomes' Continue: sound goes to s2a's slide, the weaker one to s2b's
        $this->assertSame('Scene s2a of Topic 1', $by[$sound['next']]['title']);
        $this->assertSame('Scene s2b of Topic 1', $by[$weak['next']]['title']);
    }

    public function test_a_merge_keeps_the_lead_in_for_each_path_and_only_that_path_reaches_it(): void
    {
        $deck = ProScenarioConverter::convert($this->deck(1));
        $by = array_column($deck['slides'], null, 'n');
        $titleOf = fn (string $needle) => array_values(array_filter($deck['slides'], fn ($s) => str_contains($s['content']['body'], $needle)))[0];

        $fromSound = $titleOf('You come from the sound path.');
        $fromWeak = $titleOf('You come from the weaker path.');
        $merge = $titleOf('Body of s3.');

        $this->assertSame($fromSound['next'], $merge['n'], 'the lead-in goes on into the scene');
        $s2a = array_values(array_filter($deck['slides'], fn ($s) => str_contains($s['title'], 'Scene s2a')))[0];
        $s2b = array_values(array_filter($deck['slides'], fn ($s) => str_contains($s['title'], 'Scene s2b')))[0];
        $this->assertSame($fromSound['n'], $s2a['next'], 'the sound path reaches its own lead-in');
        $this->assertSame($fromWeak['n'], $s2b['next'], 'the weaker path reaches its own');
        $this->assertContains('Why. Because the idea says so.', array_column($merge['content']['explanations'], 'text'), 'the scene explanation is kept');
    }

    public function test_acts_follow_one_another_and_the_last_ending_has_somewhere_to_go(): void
    {
        $deck = ProScenarioConverter::convert($this->deck(2));
        $slides = $deck['slides'];
        $ends = array_values(array_filter($slides, fn ($s) => str_starts_with($s['title'], 'End of ')));
        $firstOfSecond = array_values(array_filter($slides, fn ($s) => $s['title'] === 'Topic 2 mission'))[0];
        $last = end($slides);

        $this->assertSame($firstOfSecond['n'], $ends[0]['next'], 'the first act ends into the second act');
        $this->assertSame('summary', $last['slide_type'], 'a closing slide, so the last ending does not wander on');
        $this->assertSame($last['n'], $ends[1]['next']);
        $this->assertSame('You know the idea now.', $last['content']['body']);
    }

    public function test_the_words_and_pictures_of_the_story_are_all_still_there(): void
    {
        $deck = ProScenarioConverter::convert($this->deck(2));
        $blob = json_encode($deck['slides']);

        foreach (['Body of s1.', 'Sound approach', 'It works out.', 'The idea was missed.', 'It ends.', 'Your mission.', 'An idea explained in the chapter.'] as $text) {
            $this->assertStringContainsString($text, $blob);
        }
        foreach (['a1s1', 'a1s2a', 'a1e1', 'a2s3'] as $name) {
            $this->assertStringContainsString("study-deck-image:$name", $blob);
        }
        $this->assertSame([7], array_values($deck['taught_by'])[0] === [] ? [] : [7] , 'the concept is still taught');
        $this->assertNotEmpty($deck['taught_by'][7]);
    }

    public function test_a_scene_with_marked_parts_becomes_a_hotspots_slide(): void
    {
        $deck = $this->deck(1);
        $deck['slides'][1]['interaction']['nodes'][0]['image']['hotspots'] = [
            ['id' => 'h1', 'label' => 'Roots', 'x' => 30.0, 'y' => 40.0, 'w' => 20.0, 'h' => 20.0, 'text' => 'The roots hold the plant.'],
            ['id' => 'h2', 'label' => 'Leaves', 'x' => 70.0, 'y' => 40.0, 'w' => 20.0, 'h' => 20.0, 'text' => 'The leaves catch light.'],
        ];
        $out = ProScenarioConverter::convert($deck);
        $explore = array_values(array_filter($out['slides'], fn ($s) => ($s['interaction']['kind'] ?? '') === 'hotspots'));

        $this->assertCount(1, $explore);
        $this->assertTrue($explore[0]['image']['annotated']);
        $this->assertArrayNotHasKey('hotspots', $explore[0]['image']);
        $this->assertSame(['Roots', 'Leaves'], array_column($explore[0]['interaction']['spots'], 'label'));
    }

    public function test_a_decision_with_several_sound_choices_or_none_is_still_converted(): void
    {
        $deck = $this->deck(1);
        $deck['slides'][1]['interaction']['nodes'][1]['choices'][1]['sound'] = true;   // both sound
        $this->assertSame([], ProScenarioValidator::check(ProScenarioConverter::convert($deck)['slides'], false, false));

        $deck['slides'][1]['interaction']['nodes'][1]['choices'][0]['sound'] = false;
        $deck['slides'][1]['interaction']['nodes'][1]['choices'][1]['sound'] = false;   // neither
        $this->assertSame([], ProScenarioValidator::check(ProScenarioConverter::convert($deck)['slides'], false, false));
    }

    public function test_titles_never_repeat_even_when_two_acts_use_the_same_scene_title(): void
    {
        $deck = $this->deck(2);
        $deck['slides'][2]['interaction']['nodes'][0]['title'] = $deck['slides'][1]['interaction']['nodes'][0]['title'];
        $titles = array_map(fn ($s) => mb_strtolower($s['title']), ProScenarioConverter::convert($deck)['slides']);

        $this->assertSame($titles, array_values(array_unique($titles)));
    }

    public function test_a_story_that_cannot_be_converted_cleanly_is_served_as_stored(): void
    {
        $deck = $this->deck(1);
        $deck['slides'][1]['interaction']['nodes'][1]['choices'][0]['next'] = 'nowhere';
        $stored = $deck;

        $this->assertSame($stored, ProScenarioConverter::forRead($deck, self::CONFIG), 'never half converted');
        $this->assertStringContainsString('nowhere', (string) ProScenarioConverter::$lastRefusal);
    }

    public function test_non_latin_titles_are_told_apart(): void
    {
        $deck = $this->deck(1);
        foreach ($deck['slides'][1]['interaction']['nodes'] as $k => $n) {
            $deck['slides'][1]['interaction']['nodes'][$k]['title'] = 'दृश्य ' . ($k + 1) . ' खेत';
        }

        $this->assertSame([], ProScenarioValidator::check(ProScenarioConverter::convert($deck)['slides'], false, false));
    }
    public function test_a_long_lesson_is_condensed_to_the_limit_keeping_each_part_its_decision_and_every_answer(): void
    {
        $deck = $this->deck(5);
        $full = ProScenarioConverter::convert($deck, null);
        $short = ProScenarioConverter::convert($deck, 30);
        $blob = json_encode($short['slides']);

        $this->assertGreaterThan(30, count($full['slides']), 'the full conversion is over the limit');
        $this->assertLessThanOrEqual(30, count($short['slides']));
        $this->assertSame([], ProScenarioValidator::check($short['slides'], false, false));
        $kinds = array_count_values(array_map(fn ($s) => $s['interaction']['kind'] ?? 'plain', $short['slides']));
        $this->assertSame(5, $kinds['decision'], 'every part still has its decision');
        $this->assertSame(10, $kinds['outcome'], 'and an outcome for each answer');
        foreach (['It works out.', 'It half works.', 'Because of the idea.', 'The idea was missed.', 'Your mission.', 'An idea explained in the chapter.', 'Because the idea says so.'] as $text) {
            $this->assertStringContainsString($text, $blob);
        }
        $this->assertNotEmpty($short['taught_by'][7], 'the concept is still taught');
        $this->assertSame('summary', end($short['slides'])['slide_type']);
    }

    public function test_the_condensed_parts_follow_one_another_and_a_weaker_answer_still_offers_the_intelligent_path(): void
    {
        $short = ProScenarioConverter::convert($this->deck(2), 12);
        $by = array_column($short['slides'], null, 'n');
        $intro2 = array_values(array_filter($short['slides'], fn ($s) => $s['title'] === 'Topic 2 mission'))[0];
        $outcomes = array_values(array_filter($short['slides'], fn ($s) => ($s['interaction']['kind'] ?? '') === 'outcome'));

        // the first part's outcomes go on into the second part's introduction; the second part's into the closing summary
        $this->assertSame($intro2['n'], $outcomes[0]['next']);
        $this->assertSame($intro2['n'], $outcomes[1]['next']);
        $this->assertSame('summary', $by[$outcomes[3]['next']]['slide_type']);
        $weak = $outcomes[1];
        $this->assertContains('See the intelligent path', array_column($weak['interaction']['actions'], 'label'));
        $this->assertContains($intro2['n'], array_column($outcomes[0]['interaction']['actions'], 'goto'), 'the sound answer\'s Continue leads on too');
    }

    public function test_when_the_parts_are_many_the_fewest_explained_lose_their_introduction_slide_rather_than_the_cap_being_broken(): void
    {
        $deck = $this->deck(10);
        $short = ProScenarioConverter::convert($deck, 50);

        $this->assertLessThanOrEqual(50, count($short['slides']));
        $this->assertSame(10, array_count_values(array_map(fn ($s) => $s['interaction']['kind'] ?? 'plain', $short['slides']))['decision'], 'all ten decisions stay');
    }

    public function test_a_cap_that_cannot_be_met_refuses_the_conversion_instead_of_breaking_it(): void
    {
        $this->expectException(\RuntimeException::class);
        ProScenarioConverter::convert($this->deck(5), 5);
    }

    public function test_a_lesson_already_under_the_limit_is_not_condensed(): void
    {
        $deck = $this->deck(1);
        $full = ProScenarioConverter::convert($deck, null);

        $this->assertSame($full, ProScenarioConverter::convert($deck, 60));
        $this->assertStringContainsString('Body of s2b.', json_encode($full['slides']), 'every scene is there');
    }
}
