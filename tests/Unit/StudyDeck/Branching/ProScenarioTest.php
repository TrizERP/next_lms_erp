<?php

namespace Tests\Unit\StudyDeck\Branching;

use App\Services\CoursePresentation\PresentationQuality;
use App\Services\StudyDeck\Branching\Pro\ProScenarioBuilder;
use App\Services\StudyDeck\Branching\Pro\ProScenarioFormat;
use App\Services\StudyDeck\Branching\Pro\ProScenarioGate;
use App\Services\StudyDeck\Branching\Pro\ProScenarioValidator;
use App\Services\StudyDeck\Branching\Pro\ProScenarioWriter;
use App\Services\StudyDeck\ConceptContextBuilder;
use App\Services\StudyDeck\Contracts\Completer;
use App\Services\StudyDeck\DeckValidator;
use App\Services\StudyDeck\LearningPlanBuilder;
use App\Services\StudyDeck\QuestionSelector;
use App\Services\StudyDeck\SlideHtmlRenderer;
use App\Services\StudyDeck\Story\StoryTemplate;
use PHPUnit\Framework\TestCase;
use Tests\Unit\StudyDeck\StudyDeckFixture;

/**
 * The pro Branching Scenario (explore / decide / outcome / watch slides), end to end with a scripted model and invented pictures.
 *
 * NO DATABASE, no network, no model: everything that is code runs for real (the writer's checks, the builder's numbering and links, the
 * renderer, the deck validator, the presentation-quality rules) and only the model's words and the pictures are canned.
 */
class ProScenarioTest extends TestCase
{
    use StudyDeckFixture;

    private const WORDS = ['model', 'details', 'map', 'roads', 'law', 'pattern', 'theory', 'explains', 'simple', 'system', 'keeps', 'needs', 'question', 'shows'];

    /** n words made only of words the fixture chapter uses, starting at a seed so the sentences differ. */
    private function words(int $n, int $seed = 0): string
    {
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = self::WORDS[($seed + $i * 3) % count(self::WORDS)];
        }

        return ucfirst(implode(' ', $out)) . '.';
    }

    /** @return array<string,mixed> what the model replies for one part: valid against every rule */
    private function reply(int $k, array $over = []): array
    {
        $ordinal = ['first', 'second', 'third', 'fourth', 'fifth'][$k];

        return array_replace_recursive([
            'scene' => [
                'title' => "The $ordinal map walk", 'situation' => $this->words(30, $k), 'intro' => 'Select a marker to see what matters.',
                'parts' => [
                    ['label' => 'A model', 'text' => $this->words(16, $k + 1)],
                    ['label' => 'Details', 'text' => $this->words(16, $k + 2)],
                    ['label' => 'The map', 'text' => $this->words(16, $k + 3)],
                ],
            ],
            'decision' => [
                'title' => "A decision for the $ordinal team", 'kicker' => 'Scenario · Map walk', 'question' => 'What should the team ' . $this->words(12, $k + 4),
                'options' => [
                    ['text' => $this->words(9, $k + 5), 'sound' => false, 'title' => "Copy every detail $ordinal", 'what' => $this->words(22, $k + 6), 'why' => $this->words(22, $k + 7)],
                    ['text' => $this->words(9, $k + 6), 'sound' => true, 'title' => "Keep only what is needed $ordinal", 'what' => $this->words(22, $k + 8), 'why' => $this->words(22, $k + 9)],
                    ['text' => $this->words(9, $k + 7), 'sound' => false, 'title' => "Ask for a bigger map $ordinal", 'what' => $this->words(22, $k + 10), 'why' => $this->words(22, $k + 11)],
                ],
            ],
            'flow' => ['title' => "Watch the $ordinal path", 'intro' => 'Watch how the sound choice works.', 'steps' => ['Study the system', 'Keep what the question needs', 'Leave out the rest'], 'wrapup' => $this->words(12, $k)],
            'conclusion' => $this->words(10, $k + 2),
        ], $over);
    }

    private function completerFor(callable $reply): Completer
    {
        return new class($reply) implements Completer {
            public int $calls = 0;

            public function __construct(private $reply)
            {
            }

            public function complete(string $system, string $prompt, int $maxTokens = 16000): string
            {
                return json_encode(($this->reply)($this->calls++, $prompt));
            }
        };
    }

    private function image(string $name, array $extra = []): array
    {
        return $extra + [
            'type' => 'photo', 'url' => 'study-deck-image:' . crc32($name), 'alt' => "A picture of $name", 'caption' => null, 'width' => 1200, 'height' => 800,
            'licence' => 'CC BY 4.0', 'source_url' => 'https://example.org/' . $name, 'creator' => 'A. Person', 'attribution' => 'Photo: A. Person (CC BY 4.0)',
            'attribution_required' => true, 'match_level' => 'related',
        ];
    }

    /** @return array{0:array,1:array,2:array} context, map, config */
    private function chapter(): array
    {
        $raw = $this->raw();
        $context = (new ConceptContextBuilder())->assemble($raw);
        $selection = (new QuestionSelector())->select($raw['questions'], 3, ['mcq', 'true_false']);
        $map = (new LearningPlanBuilder())->build($context, $selection['eligible'], 6, 12, [], null);
        $config = StoryTemplate::defaults();
        // The chapter's own definitions: what the explore slide explains (a concept with none is refused by the deck validator).
        foreach ($map['concepts'] as $id => $c) {
            $map['concepts'][$id]['definition'] = $c['definition'] ?: 'A ' . strtolower($c['name']) . ' is something the model keeps to explain the system.';
        }

        return [$context, $map, $config];
    }

    /** @return array<string,mixed> the build for the fixture chapter (two topics and the closing mission) */
    protected function built(?callable $pictures = null, ?callable $diagram = null, ?callable $reply = null, ?int $maxSlides = null): array
    {
        [$context, $map, $config] = $this->chapter();
        $pictures ??= function (array $nodes): array {
            // The first part's explore picture comes with parts the picture check found; every other picture has none.
            foreach ($nodes as $k => $n) {
                $extra = $n['id'] === 'explore' && str_contains($n['title'], 'first') ? ['hotspots' => [
                    ['id' => 'h1', 'label' => 'Roads', 'x' => 30.0, 'y' => 40.0, 'w' => 20.0, 'h' => 20.0, 'text' => 'The roads are drawn as simple lines.'],
                    ['id' => 'h2', 'label' => 'Trees', 'x' => 70.0, 'y' => 60.0, 'w' => 20.0, 'h' => 20.0, 'text' => 'The trees are left out of the map.'],
                ]] : [];
                $nodes[$k]['image'] = $this->image($n['id'] . $n['title'], $extra);
            }

            return $nodes;
        };
        $diagram ??= fn (array $spec): array => [
            'type' => 'diagram', 'url' => 'study-deck-image:' . crc32(json_encode($spec)), 'width' => 1280, 'height' => 720,
            'alt' => \App\Services\StudyDeck\DiagramRenderer::describe($spec), 'caption' => $spec['title'], 'texts' => \App\Services\StudyDeck\DiagramRenderer::texts($spec),
        ];
        $writer = new ProScenarioWriter($this->completerFor($reply ?? fn (int $k) => $this->reply($k)));
        $builder = new ProScenarioBuilder($writer, $pictures, $diagram, $maxSlides);

        return $builder->build($context, $map, $config, null) + ['context' => $context, 'map' => $map, 'builder' => $builder];
    }

    /** The deck as the renderer makes it (the real renderer and validator). @return array{deck:array,report:array,html:string} */
    protected function rendered(array $built): array
    {
        $map = $built['map'];
        $map['story_mode'] = true;
        $map['pro_mode'] = true;
        $map['target_slides'] = ['min' => 3, 'max' => count($built['plan']['slides']) + 4];
        $rendered = (new SlideHtmlRenderer())->render($built['context'], $map, $built['plan'], $built['content'], $built['images'], [], [], $built['interactions']);
        $report = (new DeckValidator())->validate($built['context'], $map, [], $rendered);

        return ['deck' => $rendered['deck'], 'report' => $report, 'html' => $rendered['html']];
    }

    // ---- the gate ------------------------------------------------------------------------------------------------------------------------

    public function test_only_standard_8_science_chapter_1_takes_the_new_format(): void
    {
        $config = ['enabled' => true, 'standards' => ['8'], 'subjects' => ['Science'], 'chapter_numbers' => [1]];
        $chapter = ['standard_name' => '8', 'subject_name' => 'Science', 'sort_order' => 1];

        $this->assertTrue(ProScenarioGate::applies($chapter, $config));
        $this->assertTrue(ProScenarioGate::applies(['standard_name' => 'Standard 8', 'subject_name' => 'science', 'sort_order' => '1'] , $config), 'the same standard and subject by another spelling');
        $this->assertFalse(ProScenarioGate::applies(['standard_name' => '9'] + $chapter, $config), 'another standard');
        $this->assertFalse(ProScenarioGate::applies(['subject_name' => 'Mathematics'] + $chapter, $config), 'another subject');
        $this->assertFalse(ProScenarioGate::applies(['sort_order' => 2] + $chapter, $config), 'another chapter');
        $this->assertFalse(ProScenarioGate::applies([], $config), 'a chapter that says nothing');
        $this->assertFalse(ProScenarioGate::applies($chapter, ['enabled' => false] + $config), 'switched off');
        $this->assertFalse(ProScenarioGate::applies($chapter, ['standards' => [], 'subjects' => [], 'chapter_numbers' => []] + $config), 'nothing listed, nothing matches');
    }

    // ---- the words ---------------------------------------------------------------------------------------------------------------------

    public function test_a_reply_that_meets_the_rules_is_accepted(): void
    {
        $w = ProScenarioWriter::normalise($this->reply(0));

        $this->assertSame([], ProScenarioWriter::check($w));
    }

    public function test_the_writer_refuses_what_would_break_the_scenario_and_asks_again_once(): void
    {
        $bad = fn (int $k) => $k === 0
            ? $this->reply(0, ['decision' => ['options' => [1 => ['sound' => false]]]])   // no sound answer at all
            : $this->reply(0);
        $completer = $this->completerFor(fn (int $k) => $bad($k));
        [$context, $map, $config] = $this->chapter();
        $act = \App\Services\StudyDeck\Story\StoryActs::plan($map, $config)[0];

        $result = (new ProScenarioWriter($completer))->write($context, $map, $act);

        $this->assertSame(2, $completer->calls, 'the second answer was asked for with the findings');
        $this->assertNotEmpty($result['notes']);

        $this->expectException(\RuntimeException::class);
        (new ProScenarioWriter($this->completerFor(fn () => $this->reply(0, ['decision' => ['options' => [1 => ['sound' => false]]]]))))->write($context, $map, $act);
    }

    public function test_the_checks_name_each_kind_of_fault(): void
    {
        $w = ProScenarioWriter::normalise($this->reply(0));
        $find = fn (array $change, string $needle) => $this->assertStringContainsString($needle, implode(' ', ProScenarioWriter::check(array_replace_recursive($w, $change))));

        $find(['decision' => ['options' => [0 => ['sound' => true]]]], 'Exactly one');
        $find(['decision' => ['options' => [0 => ['text' => 'Always keep it']]]], 'gives itself away');
        $find(['decision' => ['question' => 'Which of the following should the team keep for the map walk?']], 'quiz');
        $find(['scene' => ['situation' => 'Too short.']], 'scene.situation');
        $find(['flow' => ['steps' => ['One', 'Two']]], 'flow.steps');

        $invented = array_replace_recursive($w, ['decision' => ['options' => [0 => ['what' => $w['decision']['options'][0]['what'] . ' It takes 777 minutes.']]]]);
        [$context, $map] = $this->chapter();
        $this->assertStringContainsString('777', implode(' ', ProScenarioWriter::grounding($invented, $context, $map)));
    }

    // ---- the structure -----------------------------------------------------------------------------------------------------------------

    public function test_every_answer_has_its_own_outcome_and_every_link_leads_somewhere(): void
    {
        $built = $this->built();
        $deck = $this->rendered($built)['deck'];
        $by = array_column($deck['slides'], null, 'n');

        $this->assertSame([], ProScenarioValidator::check($deck['slides']));
        $kinds = array_count_values(array_map(fn ($s) => $s['interaction']['kind'] ?? 'none', $deck['slides']));
        $this->assertSame(3, $kinds['hotspots'], 'one explore slide per part (two topics and the mission)');
        $this->assertSame(3, $kinds['decision']);
        $this->assertSame(9, $kinds['outcome'], 'three answers each');
        $this->assertSame(3, $kinds['flow']);

        foreach ($deck['slides'] as $s) {
            if (($s['interaction']['kind'] ?? '') !== 'decision') {
                continue;
            }
            $this->assertSame(-1, $s['next'], 'Continue waits for an answer');
            $tones = [];
            foreach ($s['interaction']['options'] as $o) {
                $target = $by[$o['goto']];
                $this->assertSame('outcome', $target['interaction']['kind']);
                $this->assertSame($o['id'], $target['interaction']['choice']['id']);
                $this->assertContains($s['n'], array_column($target['interaction']['actions'], 'goto'), 'a way back to choose again');
                $tones[] = $target['interaction']['tone'];
            }
            sort($tones);
            $this->assertSame(['caution', 'caution', 'sound'], $tones, 'exactly one sound answer');
        }
    }

    public function test_the_sound_answer_is_not_always_the_same_letter(): void
    {
        $deck = $this->rendered($this->built())['deck'];
        $letters = [];
        foreach ($deck['slides'] as $s) {
            if (($s['interaction']['kind'] ?? '') === 'outcome' && $s['interaction']['tone'] === 'sound') {
                $letters[] = $s['interaction']['choice']['id'];
            }
        }

        $this->assertSame(['b', 'c', 'a'], $letters);
    }

    public function test_a_picture_without_parts_gets_a_drawn_diagram_whose_hotspots_sit_on_its_labels(): void
    {
        $deck = $this->rendered($this->built())['deck'];
        $explore = array_values(array_filter($deck['slides'], fn ($s) => ($s['interaction']['kind'] ?? '') === 'hotspots'));

        $this->assertTrue($explore[0]['image']['annotated'], 'a photograph whose parts the picture check found keeps exactly those parts');
        $this->assertSame('Roads', $explore[0]['interaction']['spots'][0]['label']);
        $this->assertSame('diagram', $explore[1]['image']['type'], 'no parts were found, so a diagram was drawn');
        $this->assertSame(['A model', 'Details', 'The map'], array_column($explore[1]['interaction']['spots'], 'label'));
        foreach ($explore[1]['interaction']['spots'] as $spot) {
            $this->assertGreaterThan(0, $spot['x']);
            $this->assertLessThanOrEqual(100, $spot['x'] + $spot['w'] / 2);
        }
    }

    public function test_the_finished_deck_passes_the_deck_validator_and_the_presentation_rules(): void
    {
        $out = $this->rendered($this->built());

        $this->assertSame([], $out['report']['errors']);
        $this->assertTrue($out['report']['ok']);
        $this->assertSame('professional', $out['deck']['theme'], 'the theme that makes decisions and outcomes full-screen');
        $this->assertTrue(StoryTemplate::isStory($out['deck']), 'filed, named and checked as a branching scenario');
        $this->assertTrue(ProScenarioFormat::isPro($out['deck']));

        $blocking = array_filter((new PresentationQuality())->check($out['deck']), fn ($p) => in_array($p['code'], ['repeated_index', 'empty_slide', 'broken_interaction', 'bad_image', 'missing_concept', 'generic_label', 'broken_story'], true));
        $this->assertSame([], array_values($blocking));
    }

    public function test_a_slide_without_a_next_key_is_left_as_it_was(): void
    {
        $out = $this->rendered($this->built());
        $cover = $out['deck']['slides'][0];

        $this->assertArrayNotHasKey('next', $cover);
    }

    // ---- what the validator refuses ----------------------------------------------------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    private function slides(): array
    {
        return $this->rendered($this->built())['deck']['slides'];
    }

    private function firstOf(array $slides, string $kind): int
    {
        foreach ($slides as $i => $s) {
            if (($s['interaction']['kind'] ?? '') === $kind) {
                return $i;
            }
        }
        $this->fail("no $kind slide");
    }

    public function test_an_answer_that_opens_a_slide_that_does_not_exist_is_refused(): void
    {
        $slides = $this->slides();
        $d = $this->firstOf($slides, 'decision');
        $slides[$d]['interaction']['options'][0]['goto'] = 999;

        $this->assertStringContainsString('slide 999, which does not exist', implode(' ', ProScenarioValidator::check($slides)));
    }

    public function test_an_answer_that_opens_something_other_than_its_outcome_is_refused(): void
    {
        $slides = $this->slides();
        $d = $this->firstOf($slides, 'decision');
        $slides[$d]['interaction']['options'][0]['goto'] = $slides[$d]['n'] - 1;   // the explore slide

        $this->assertStringContainsString('not an outcome slide', implode(' ', ProScenarioValidator::check($slides)));
    }

    public function test_two_answers_sharing_one_outcome_are_refused(): void
    {
        $slides = $this->slides();
        $d = $this->firstOf($slides, 'decision');
        $slides[$d]['interaction']['options'][1]['goto'] = $slides[$d]['interaction']['options'][0]['goto'];

        $this->assertStringContainsString('open the same slide', implode(' ', ProScenarioValidator::check($slides)));
    }

    public function test_a_decision_with_no_sound_answer_is_refused(): void
    {
        $slides = $this->slides();
        foreach ($slides as $i => $s) {
            if (($s['interaction']['tone'] ?? '') === 'sound') {
                $slides[$i]['interaction']['tone'] = 'caution';
            }
        }

        $this->assertStringContainsString('none of the answers leads to a sound outcome', implode(' ', ProScenarioValidator::check($slides)));
    }

    public function test_an_outcome_with_no_way_back_to_the_question_is_refused(): void
    {
        $slides = $this->slides();
        $o = $this->firstOf($slides, 'outcome');
        $slides[$o]['interaction']['actions'] = [array_values(array_filter($slides[$o]['interaction']['actions'], fn ($a) => $a['label'] !== 'Choose again'))[0] ?? ['label' => 'On', 'goto' => $slides[$o]['n'] + 1]];

        $this->assertStringContainsString('no way back to the question', implode(' ', ProScenarioValidator::check($slides)));
    }

    public function test_a_slide_that_nobody_can_reach_is_refused(): void
    {
        $slides = $this->slides();
        $first = $this->firstOf($slides, 'flow');
        $slides[$first]['next'] = $slides[$first]['n'];   // the watch slide loops on itself: the rest can never be reached

        $found = implode(' ', ProScenarioValidator::check($slides));
        $this->assertMatchesRegularExpression('/can never (be reached|reach the end)/', $found);
    }

    public function test_a_picture_less_slide_and_a_hotspot_outside_its_picture_are_refused(): void
    {
        $slides = $this->slides();
        $d = $this->firstOf($slides, 'decision');
        $slides[$d]['image'] = null;
        $h = $this->firstOf($slides, 'hotspots');
        $slides[$h]['interaction']['spots'][0]['x'] = 99.0;

        $found = implode(' ', ProScenarioValidator::check($slides));
        $this->assertStringContainsString('has no readable picture', $found);
        $this->assertStringContainsString('is outside its picture', $found);
    }

    public function test_a_deck_with_no_picture_for_a_scene_fails_the_deck_validator_naming_the_slide(): void
    {
        $built = $this->built(fn (array $nodes) => $nodes);   // the picture step found nothing
        $out = $this->rendered($built);

        $this->assertFalse($out['report']['ok']);
        $this->assertStringContainsString('has no readable picture', implode(' ', $out['report']['errors']));
    }

    public function test_the_older_story_deck_is_not_taken_for_a_pro_scenario(): void
    {
        $story = ['slides' => [['n' => 1, 'interaction' => ['kind' => 'scenario', 'template' => StoryTemplate::KEY, 'nodes' => []]]]];

        $this->assertTrue(StoryTemplate::isStory($story));
        $this->assertFalse(ProScenarioFormat::isPro($story));
        $this->assertFalse(ProScenarioFormat::isPro(['slides' => [['n' => 1, 'interaction' => ['kind' => 'hotspots']]]]));
    }
    public function test_a_generic_stock_picture_is_replaced_by_the_parts_own_picture(): void
    {
        $own = $this->image('plough', ['match_level' => 'related']);
        $generic = $this->image('lab coat', ['match_level' => 'generic']);
        $out = ProScenarioBuilder::withoutGeneric(['explore' => $own, 'decide' => $generic, 'o1' => $generic, 'o2' => null]);

        $this->assertSame($own['url'], $out['decide']['url']);
        $this->assertSame($own['url'], $out['o1']['url']);
        $this->assertNull($out['o2']);
        $this->assertSame(['x' => $generic], ProScenarioBuilder::withoutGeneric(['x' => $generic]), 'with nothing better the generic picture stays, so it can be reported');
    }
    public function test_a_lesson_over_its_slide_limit_is_grouped_into_fewer_parts_and_still_teaches_every_concept(): void
    {
        $uncapped = $this->built();
        $capped = $this->built(null, null, null, 15);   // room for a cover, objectives, a summary and two parts

        $this->assertSame(20, count($uncapped['plan']['slides']), 'two topics and the mission: three parts');
        $this->assertSame(14, count($capped['plan']['slides']), 'the two topics share one part');
        $out = $this->rendered($capped);
        $this->assertSame([], $out['report']['errors']);
        $taught = array_unique(array_merge(...array_column($out['deck']['slides'], 'taught_concept_ids')));
        sort($taught);
        $this->assertSame([1, 2, 3, 4], $taught, 'every concept of the chapter is still taught');
    }

    public function test_the_deck_validator_refuses_a_lesson_over_the_limit(): void
    {
        $built = $this->built();
        $map = $built['map'];
        $map['story_mode'] = true;
        $map['pro_mode'] = true;
        $map['pro_max_slides'] = 10;
        $map['target_slides'] = ['min' => 3, 'max' => 40];
        $rendered = (new SlideHtmlRenderer())->render($built['context'], $map, $built['plan'], $built['content'], $built['images'], [], [], $built['interactions']);
        $report = (new DeckValidator())->validate($built['context'], $map, [], $rendered);

        $this->assertFalse($report['ok']);
        $this->assertStringContainsString('at most 10', implode(' ', $report['errors']));
    }
}
