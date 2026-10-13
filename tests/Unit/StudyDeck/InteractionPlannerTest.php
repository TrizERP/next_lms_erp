<?php

namespace Tests\Unit\StudyDeck;

use App\Services\StudyDeck\ConceptContextBuilder;
use App\Services\StudyDeck\DeckValidator;
use App\Services\StudyDeck\DiagramRenderer;
use App\Services\StudyDeck\ImagePlanner;
use App\Services\StudyDeck\InteractionPlanner;
use App\Services\StudyDeck\LearningPlanBuilder;
use App\Services\StudyDeck\QuestionSelector;
use App\Services\StudyDeck\SlideContentGenerator;
use App\Services\StudyDeck\SlideHtmlRenderer;
use App\Services\StudyDeck\SlidePlanner;
use App\Services\StudyDeck\StudyDeckService;
use Tests\TestCase;

/**
 * Stage 3: which slides earn a hotspot, scenario or reveal interaction, and that a bad one costs
 * a slide its extra and never the deck. No database, no model: the completer is scripted.
 */
class InteractionPlannerTest extends TestCase
{
    use StudyDeckFixture;

    /** @param array<int,string> $interactionReplies replies for stage 3, in order */
    private function build(array $interactionReplies, ?array $plan = null): array
    {
        $c = $this->completer(array_merge([json_encode($plan ?? $this->planWithRoom()), json_encode($this->slideText())], $interactionReplies));
        $images = new ImagePlanner($this->imageSearch($this->goodImage()), $this->memoryStore(), fn () => $this->png());
        $service = new StudyDeckService(
            new ConceptContextBuilder(), new QuestionSelector(), new LearningPlanBuilder(),
            new SlidePlanner($c), new SlideContentGenerator($c, 8), $images,
            new SlideHtmlRenderer(), new DeckValidator(images: $images), interactionPlanner: new InteractionPlanner($c),
        );
        $r = $service->fromRaw($this->raw(), ['min_slides' => 6, 'max_slides' => 8]);
        $r['prompts'] = $c->prompts;

        return $r;
    }

    private function slide(array $result, int $n): array
    {
        return $result['deck']['slides'][$n - 1];
    }

    public function test_each_kind_lands_on_its_slide_and_the_deck_still_validates(): void
    {
        $r = $this->build([json_encode($this->interactionReply())]);

        $this->assertSame([], $r['report']['errors']);
        $this->assertSame('compare', $this->slide($r, 2)['interaction']['kind']);
        $this->assertSame('hotspots', $this->slide($r, 3)['interaction']['kind']);
        $this->assertSame('reveal', $this->slide($r, 4)['interaction']['kind']);
        $this->assertSame('scenario', $this->slide($r, 5)['interaction']['kind']);
        $this->assertSame('steps', $this->slide($r, 6)['interaction']['kind']);
        $this->assertSame('match', $this->slide($r, 7)['interaction']['kind']);
        $this->assertSame(['compare' => 1, 'hotspots' => 1, 'reveal' => 1, 'scenario' => 1, 'steps' => 1, 'match' => 1], $r['report']['stats']['interactions']);
        $this->assertSame(6, $r['report']['stats']['interactive_slides']);
        $this->assertSame(6, $r['report']['stats']['learning_slides'], 'every learning slide has one');
        $this->assertStringNotContainsString('Slides with no interaction', implode(' ', $r['report']['warnings']));
    }

    public function test_a_slide_left_plain_keeps_the_reason_so_the_review_can_list_it(): void
    {
        $r = $this->build([json_encode($this->interactionReply())]);

        $r = $this->build(['{}']);

        $this->assertSame('the title slide only opens the chapter', $r['interactions'][1]['reason']);
        $this->assertSame('left to plain teaching', $this->slide($r, 2)['interaction_reason']);
        $this->assertStringContainsString('Slides with no interaction (6): 2, 3, 4, 5, 6, 7', implode(' ', $r['report']['warnings']));
    }

    public function test_hotspots_sit_where_the_drawn_labels_sit(): void
    {
        $r = $this->build([json_encode($this->interactionReply())]);
        $spots = $this->slide($r, 3)['interaction']['spots'];
        $anchors = DiagramRenderer::anchors($r['plan']['slides'][2]['diagram']);

        $this->assertSame(array_column($anchors, 'label'), array_column($spots, 'label'));
        $this->assertSame(array_column($anchors, 'x'), array_column($spots, 'x'));
        $this->assertSame(array_column($anchors, 'y'), array_column($spots, 'y'));
        $this->assertSame('diagram', $this->slide($r, 3)['image']['type']);
    }

    public function test_the_static_presentation_is_unchanged_by_interactions(): void
    {
        $with = $this->build([json_encode($this->interactionReply())]);
        $without = $this->build(['{}']);

        $this->assertSame($without['html'], $with['html'], 'the PPT/HTML stays the static representation of the lesson');
    }

    public function test_a_label_not_on_the_diagram_is_sent_back_and_the_repair_is_used(): void
    {
        $bad = $this->interactionReply();
        $bad['slides'][1]['interaction']['spots'][0]['label'] = 'Something invented';
        $fixed = $this->interactionReply();

        $r = $this->build([json_encode($bad), json_encode(['slides' => [$fixed['slides'][1]]])]);

        $this->assertSame('hotspots', $this->slide($r, 3)['interaction']['kind']);
        $this->assertStringContainsString('is not a label on the diagram', end($r['prompts']));
        $this->assertSame([], $r['report']['errors']);
    }

    public function test_an_interaction_that_stays_invalid_is_dropped_with_its_reason_and_the_deck_survives(): void
    {
        $bad = $this->interactionReply();
        $bad['slides'][1]['interaction']['spots'] = [['label' => 'Ignore details', 'text' => 'Too short.']];

        $r = $this->build([json_encode($bad), json_encode(['slides' => [$bad['slides'][1]]])]);

        $this->assertNull($this->slide($r, 3)['interaction']);
        $this->assertStringContainsString('dropped', $r['interactions'][3]['reason']);
        $this->assertSame([], $r['report']['errors']);
        $this->assertSame('reveal', $this->slide($r, 4)['interaction']['kind'], 'one bad slide does not cost the others');
    }

    public function test_hotspots_need_a_drawn_diagram(): void
    {
        $reply = $this->interactionReply();
        // Slide 2 has a photograph, not a diagram.
        $reply['slides'][0] = ['n' => 2, 'reason' => 'parts of the picture', 'interaction' => $reply['slides'][1]['interaction']];

        $r = $this->build([json_encode($reply), '{}']);

        $this->assertNull($this->slide($r, 2)['interaction']);
        $this->assertStringContainsString('need a drawn diagram', $r['interactions'][2]['reason']);
    }

    public function test_a_scenario_needs_a_slide_that_is_a_real_situation(): void
    {
        $reply = $this->interactionReply();
        // Slide 4 is a plain concept_intro slide.
        $reply['slides'][2] = ['n' => 4, 'reason' => 'a decision', 'interaction' => $reply['slides'][3]['interaction']];

        $r = $this->build([json_encode($reply), '{}']);

        $this->assertNull($this->slide($r, 4)['interaction']);
        $this->assertStringContainsString('A scenario needs a slide of type', implode(' ', $r['prompts']));
    }

    public function test_scenario_links_only_go_forward_and_every_decision_is_reachable(): void
    {
        $back = $this->interactionReply();
        $back['slides'][3]['interaction']['nodes'][1]['choices'][0]['next'] = 'n1';
        $r = $this->build([json_encode($back), '{}']);
        $this->assertNull($this->slide($r, 5)['interaction']);
        $this->assertStringContainsString('leads backwards', implode(' ', $r['prompts']));

        $orphan = $this->interactionReply();
        $orphan['slides'][3]['interaction']['nodes'][0]['choices'][0]['next'] = null;
        $r = $this->build([json_encode($orphan), '{}']);
        $this->assertNull($this->slide($r, 5)['interaction']);
        $this->assertStringContainsString('can never be reached', implode(' ', $r['prompts']));

        $noSound = $this->interactionReply();
        foreach ($noSound['slides'][3]['interaction']['nodes'][0]['choices'] as $i => $ch) {
            $noSound['slides'][3]['interaction']['nodes'][0]['choices'][$i]['sound'] = false;
        }
        $r = $this->build([json_encode($noSound), '{}']);
        $this->assertStringContainsString('needs at least one sound choice', implode(' ', $r['prompts']));
    }

    public function test_a_reply_that_is_not_json_means_plain_teaching_not_a_crash(): void
    {
        $r = $this->build(['I think slide 3 could be interactive.', 'still not json']);

        $this->assertSame([], $r['report']['errors']);
        $this->assertSame([], array_filter(array_column($r['deck']['slides'], 'interaction')));
        $this->assertContains('discussion_prompts', array_keys($r['report']['stats']));
    }

    public function test_scenarios_and_reveals_are_capped(): void
    {
        $planner = new InteractionPlanner($this->completer([]));
        $cap = new \ReflectionMethod($planner, 'capped');

        $out = [];
        for ($n = 1; $n <= InteractionPlanner::MAX_SCENARIOS + 2; $n++) {
            $out[$n] = ['interaction' => ['kind' => 'scenario'], 'reason' => 'x'];
        }
        $out = $cap->invoke($planner, $out, []);

        $kept = array_filter($out, fn ($d) => $d['interaction'] !== null);
        $this->assertCount(InteractionPlanner::MAX_SCENARIOS, $kept);
        $this->assertStringContainsString('already has', $out[InteractionPlanner::MAX_SCENARIOS + 1]['reason']);
    }

    public function test_the_prompt_tells_the_model_most_slides_need_no_interaction_and_never_to_quiz(): void
    {
        $r = $this->build([json_encode($this->interactionReply())]);
        $prompt = end($r['prompts']);

        $this->assertStringContainsString('EVERY slide listed below should let the student DO something that teaches', $prompt);
        $this->assertStringContainsString('never like a quiz', $prompt);
        $this->assertStringContainsString('NEVER invent one for a factual concept', $prompt);
        $this->assertStringContainsString('Do not give every slide the same kind.', $prompt);
        $this->assertStringContainsString('"labels_you_may_use"', $prompt);
        $this->assertStringNotContainsString('_anchors', $prompt, 'coordinates are the system\'s business');
    }

    public function test_the_validator_checks_interactions_in_the_finished_deck(): void
    {
        $r = $this->build([json_encode($this->interactionReply())]);
        $validator = new DeckValidator();
        $check = fn (array $deck) => implode("\n", $validator->validate($r['context'], $r['map'], $r['selection']['eligible'], ['html' => $r['html'], 'deck' => $deck])['errors']);

        $deck = $r['deck'];
        $deck['slides'][2]['interaction']['spots'][0]['label'] = 'Not drawn';
        $this->assertStringContainsString('is not a label on the diagram', $check($deck));

        $deck = $r['deck'];
        $deck['slides'][3]['interaction'] = $deck['slides'][4]['interaction'];
        $this->assertStringContainsString('a scenario needs a slide that is a real situation', $check($deck));

        $deck = $r['deck'];
        $deck['slides'][0]['interaction'] = $deck['slides'][3]['interaction'];
        $this->assertStringContainsString('title slide carries no interaction', $check($deck));

        $deck = $r['deck'];
        $deck['slides'][4]['interaction']['reason'] = '';
        $this->assertStringContainsString('does not say why', $check($deck));

        $deck = $r['deck'];
        $deck['slides'][4]['interaction']['nodes'][0]['choices'][0]['next'] = 'ghost';
        $this->assertStringContainsString('leads to a decision that does not exist', $check($deck));
    }

    public function test_the_wording_of_an_interaction_is_held_to_the_chapter_text(): void
    {
        $reply = $this->interactionReply();
        $reply['slides'][2]['interaction']['items'][0]['text'] = 'A law was first written down by 4712 observers in one afternoon.';

        $r = $this->build([json_encode($reply)]);

        $this->assertFalse($r['report']['ok']);
        $this->assertStringContainsString('4712', implode("\n", $r['report']['errors']));
    }

    public function test_practice_is_capped_and_an_unplaced_concept_question_is_no_longer_an_error(): void
    {
        $r = $this->build([json_encode($this->interactionReply())]);
        $validator = new DeckValidator();

        $deck = $r['deck'];
        $deck['concept_questions'] = [];
        $deck['slides'][1]['question_ids'] = [];
        $deck['slides'][1]['activities'] = [];
        $report = $validator->validate($r['context'], $r['map'], $r['selection']['eligible'], ['html' => $r['html'], 'deck' => $deck]);
        $this->assertStringNotContainsString('eligible bank questions but none', implode("\n", $report['errors']));
    }

    public function test_a_slide_without_a_practice_question_gets_a_discussion_prompt_not_a_quiz(): void
    {
        $r = $this->build(['{}']);

        $this->assertStringContainsString('<span class="callout-label">Discuss</span><p>What does a law describe?</p><p><strong>Answer:</strong> A repeated pattern.</p>', $r['html']);
        $this->assertSame([], $this->slide($r, 4)['activities']);
        $this->assertSame('What does a law describe?', $this->slide($r, 4)['content']['discussion']['prompt']);
    }

    public function test_order_and_timeline_are_accepted_when_well_formed(): void
    {
        $reply = $this->interactionReply();
        $reply['slides'][4] = ['n' => 6, 'reason' => 'a real sequence', 'interaction' => [
            'kind' => 'order', 'intro' => 'Put the steps in order.',
            'items' => [['text' => 'Build a model'], ['text' => 'State a law'], ['text' => 'Give a theory']],
        ]];
        $reply['slides'][5] = ['n' => 7, 'reason' => 'dated events', 'interaction' => [
            'kind' => 'timeline', 'intro' => 'Select an event.',
            'items' => [
                ['when' => '1687', 'label' => 'Laws of motion', 'text' => 'Newton gave three laws of motion that describe repeated patterns.'],
                ['when' => '1905', 'label' => 'A new theory', 'text' => 'A theory explains why a pattern occurs, and can replace an older one.'],
                ['when' => '1915', 'label' => 'Tested', 'text' => 'A theory is kept only while its predictions agree with what is measured.'],
            ],
        ]];

        $r = $this->build([json_encode($reply)]);

        $this->assertSame('order', $this->slide($r, 6)['interaction']['kind']);
        $this->assertSame(['o1', 'o2', 'o3'], array_column($this->slide($r, 6)['interaction']['items'], 'id'));
        $this->assertSame('timeline', $this->slide($r, 7)['interaction']['kind']);
        $this->assertSame('1687', $this->slide($r, 7)['interaction']['items'][0]['when']);
        // The dates are claims: the grounding check holds them to the chapter text.
        $this->assertStringContainsString('Numbers in slide text that are not in the chapter text', implode("\n", $r['report']['errors']));
    }

    public function test_each_new_kind_is_dropped_when_it_breaks_its_own_rules(): void
    {
        $bad = [
            'steps with only two steps' => ['kind' => 'steps', 'intro' => 'Select a step.', 'items' => [
                ['label' => 'One', 'text' => 'The first step is to build a simple model of the system.'],
                ['label' => 'Two', 'text' => 'The second step is to state the pattern that the model shows.'],
            ]],
            'compare with no line on how they compare' => ['kind' => 'compare', 'intro' => 'Select one.', 'items' => [
                ['label' => 'A model', 'text' => 'A model is a simplified representation that keeps only what matters.'],
                ['label' => 'A copy', 'text' => 'A copy would keep every detail, which a model ignores on purpose.'],
            ]],
            'match with two pairs' => ['kind' => 'match', 'intro' => 'Pair them.', 'pairs' => [
                ['term' => 'Model', 'meaning' => 'A simplified representation of a real system.'],
                ['term' => 'Law', 'meaning' => 'Describes a repeated pattern.'],
            ]],
            'timeline event with no date' => ['kind' => 'timeline', 'intro' => 'Select one.', 'items' => [
                ['label' => 'One', 'text' => 'The first event happened before the second one did here.'],
                ['label' => 'Two', 'text' => 'The second event happened after the first one did here.'],
                ['label' => 'Three', 'text' => 'The third event happened after the second one did here.'],
            ]],
        ];

        foreach ($bad as $why => $interaction) {
            $reply = $this->interactionReply();
            $reply['slides'][4] = ['n' => 6, 'reason' => 'x', 'interaction' => $interaction];
            $r = $this->build([json_encode($reply), '{}']);

            $this->assertNull($this->slide($r, 6)['interaction'], $why);
            $this->assertStringContainsString('dropped', $r['interactions'][6]['reason'], $why);
            $this->assertSame([], $r['report']['errors'], "$why: a dropped interaction never fails the deck");
        }
    }

    public function test_the_key_idea_travels_with_the_slide(): void
    {
        $r = $this->build([json_encode($this->interactionReply())]);

        $this->assertSame('A law describes a repeated pattern.', $this->slide($r, 4)['content']['key_idea']);
        $this->assertNull($this->slide($r, 3)['content']['key_idea']);
    }

    public function test_a_slide_that_comes_back_plain_gets_one_more_chance_with_a_firmer_instruction(): void
    {
        $first = $this->interactionReply();
        $first['slides'][5] = ['n' => 7, 'interaction' => null, 'reason' => 'nothing to do here'];
        $second = ['slides' => [$this->interactionReply()['slides'][5]]];

        $r = $this->build([json_encode($first), json_encode($second)]);

        $this->assertSame('match', $this->slide($r, 7)['interaction']['kind'], 'the second pass filled it');
        $this->assertStringContainsString('These slides came back with no interaction', end($r['prompts']));
        $this->assertSame([], $r['report']['errors']);
    }

    public function test_a_slide_that_still_cannot_have_one_stays_plain_and_says_so(): void
    {
        $first = $this->interactionReply();
        $first['slides'][5] = ['n' => 7, 'interaction' => null, 'reason' => 'nothing to do here'];
        $again = ['slides' => [['n' => 7, 'interaction' => null, 'reason' => 'a closing line with nothing to explore']]];

        $r = $this->build([json_encode($first), json_encode($again)]);

        $this->assertNull($this->slide($r, 7)['interaction']);
        $this->assertStringContainsString('nothing to do here', $r['interactions'][7]['reason']);
        $this->assertStringContainsString('Slides with no interaction (1): 7', implode(' ', $r['report']['warnings']));
        $this->assertSame([], $r['report']['errors'], 'a plain slide is a warning, never a failed deck');
    }
}
