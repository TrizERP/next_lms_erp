<?php

namespace Tests\Unit\StudyDeck;

use App\Services\StudyDeck\ConceptContextBuilder;
use App\Services\StudyDeck\LearningPlanBuilder;
use App\Services\StudyDeck\QuestionSelector;
use App\Services\StudyDeck\SlidePlanner;
use App\Services\StudyDeck\WorkflowRules;
use Tests\TestCase;

/**
 * A repair round costs a whole model reply, so the faults that are only deleting or reordering are fixed in code. A scripted model answers
 * once; every test asserts that it was asked ONCE (no repair round) or, where the fix must refuse, that the plan is still rejected.
 * No database, no network.
 */
class SlidePlannerMechanicalRepairTest extends TestCase
{
    use StudyDeckFixture;

    /** @return array{0:array<string,mixed>,1:array<string,mixed>,2:array<int,array<int,array<string,mixed>>>} context, map, eligible */
    private function mapFor(int $min, int $max, array $limits = []): array
    {
        $context = (new ConceptContextBuilder())->assemble($this->raw());
        $sel = (new QuestionSelector())->select($this->raw()['questions']);
        $map = (new LearningPlanBuilder())->build($context, $sel['eligible'], $min, $max, ['repair_rounds' => 0] + $limits);

        return [$context, $map, $sel['eligible']];
    }

    public function test_bank_questions_beyond_the_limits_are_dropped_without_asking_the_model_again(): void
    {
        [$context, $map, $eligible] = $this->mapFor(6, 8);
        $plan = $this->plan();
        $plan['slides'][1]['question_ids'] = [101];
        $plan['slides'][2]['question_ids'] = [103, 104]; // one per slide at most
        $plan['slides'][3]['question_ids'] = [102];      // not an eligible question
        $plan['slides'][4]['question_ids'] = [101];      // already used on slide 2
        $plan['slides'][0]['question_ids'] = [104];      // the cover is a framing slide

        $model = $this->completer([json_encode($plan)]);
        $planner = new SlidePlanner($model);
        $result = $planner->plan($context, $map, $eligible);

        $this->assertCount(1, $model->prompts, 'no repair round was needed');
        $this->assertSame([[], [101], [103], [], [], [], []], array_column($result['slides'], 'question_ids'));
        $this->assertStringContainsString('4 bank question placement(s)', implode(' ', $planner->repairs));
    }

    public function test_the_deck_wide_practice_cap_keeps_the_first_placements_in_slide_order(): void
    {
        [$context, $map, $eligible] = $this->mapFor(6, 8, ['max_practice' => 2]);
        $plan = $this->plan();
        $plan['slides'][1]['question_ids'] = [101];
        $plan['slides'][2]['question_ids'] = [103];
        $plan['slides'][4]['question_ids'] = [104];

        $model = $this->completer([json_encode($plan)]);
        $result = (new SlidePlanner($model))->plan($context, $map, $eligible);

        $this->assertCount(1, $model->prompts);
        $this->assertSame([101, 103], array_merge(...array_column($result['slides'], 'question_ids')));
    }

    public function test_a_plan_with_a_surplus_slide_loses_its_cheapest_slide_not_a_teaching_one(): void
    {
        [$context, $map, $eligible] = $this->mapFor(6, 7);
        $plan = $this->plan();
        $plan['slides'][] = ['section' => 'Topic', 'slide_type' => 'recall', 'title' => 'Quick recall', 'teaches' => 'Recall the first idea.',
            'concept_ids' => [1], 'taught_concept_ids' => [], 'relationship' => null, 'visual' => null, 'question_ids' => [], 'h5p_pattern' => null, 'uses_baseline' => 'improved'];
        $this->assertCount(8, $plan['slides']);

        $model = $this->completer([json_encode($plan)]);
        $planner = new SlidePlanner($model);
        $result = $planner->plan($context, $map, $eligible);

        $this->assertCount(1, $model->prompts, 'no repair round was needed');
        $titles = array_column($result['slides'], 'title');
        $this->assertCount(7, $titles);
        $this->assertNotContains('Quick recall', $titles, 'the last practice slide went first');
        $this->assertContains('What a model is', $titles);
        $this->assertStringContainsString('1 surplus slide(s)', implode(' ', $planner->repairs));
    }

    public function test_a_slide_is_never_removed_when_that_would_leave_a_concept_untaught(): void
    {
        // Five slides for a limit of four, and every non-cover slide is the only one teaching its concept: nothing may go.
        [$context, $map, $eligible] = $this->mapFor(4, 4);
        $plan = $this->plan();
        $plan['slides'] = [
            $plan['slides'][0],
            $this->plan()['slides'][1],
            $this->plan()['slides'][2],
            $this->plan()['slides'][3],
            $this->plan()['slides'][4],
        ];
        $plan['slides'][2]['question_ids'] = [];
        $plan['slides'][4]['question_ids'] = [];

        $model = $this->completer([json_encode($plan)]);
        try {
            (new SlidePlanner($model))->plan($context, $map, $eligible);
            $this->fail('A plan that cannot be trimmed safely was accepted.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Plan has 5 slides; it must have between 4 and 4.', $e->getMessage());
        }
        $this->assertCount(1, $model->prompts, 'repair_rounds is 0, so the model was asked once');
    }

    /** The fixture deck plus $extra recall slides: with its exit ticket that is $extra + 1 practice slides. */
    private function planWithRecalls(int $extra): array
    {
        $plan = $this->plan();
        foreach (range(1, $extra) as $k) {
            $plan['slides'][] = ['section' => 'Topic', 'slide_type' => 'recall', 'title' => "Recall $k", 'teaches' => "Recall idea $k.",
                'concept_ids' => [$k], 'taught_concept_ids' => [], 'relationship' => null, 'visual' => null, 'question_ids' => [], 'h5p_pattern' => null, 'uses_baseline' => 'improved'];
        }

        return $plan;
    }

    public function test_more_than_three_practice_slides_loses_the_last_one_without_a_repair_round(): void
    {
        [$context, $map, $eligible] = $this->mapFor(6, 12);
        $model = $this->completer([json_encode($this->planWithRecalls(3))]); // 3 recalls + the exit ticket = 4 practice slides
        $planner = new SlidePlanner($model);
        $result = $planner->plan($context, $map, $eligible);

        $this->assertCount(1, $model->prompts, 'no repair round was needed');
        $titles = array_column($result['slides'], 'title');
        $this->assertCount(9, $titles);
        $this->assertNotContains('Recall 3', $titles);
        $this->assertContains('Recall 1', $titles);
        $this->assertStringContainsString('1 surplus practice slide(s)', implode(' ', $planner->repairs));
    }

    public function test_practice_slides_are_not_removed_below_the_slide_minimum(): void
    {
        // 10 slides for a minimum of 10: taking one out would break the minimum, so the plan stays as the model wrote it and is rejected.
        [$context, $map, $eligible] = $this->mapFor(10, 12);
        $model = $this->completer([json_encode($this->planWithRecalls(3))]);
        try {
            (new SlidePlanner($model))->plan($context, $map, $eligible);
            $this->fail('A plan with too many practice slides was accepted.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('4 recall/practice/exit_ticket slides; at most 3', $e->getMessage());
        }
    }

    public function test_a_slide_is_not_removed_when_that_would_only_swap_one_fault_for_another(): void
    {
        // Four practice slides (one too many), and each is the ONLY slide of its workflow stage. Taking any out cures the practice fault
        // but leaves a stage with no slide, which is another fault: nothing may be removed, whatever the two counts add up to.
        [$context, $map, $eligible] = $this->mapFor(6, 12);
        $map['workflow'] = ['stages' => array_map(fn ($n) => ['number' => $n, 'name' => "Stage $n"], range(1, 5))];
        $plan = $this->planWithRecalls(3);
        foreach ($plan['slides'] as $i => $slide) {
            $plan['slides'][$i]['question_ids'] = [];
            if ($i > 0) {
                // The exit ticket (slide 7) is stage 2, the recalls (slides 8-10) stages 3-5, every other slide stage 1.
                $plan['slides'][$i]['workflow_stage'] = match (true) {
                    $slide['slide_type'] === 'exit_ticket' => 2,
                    $slide['slide_type'] === 'recall' => 2 + (int) substr($slide['title'], -1),
                    default => 1,
                };
            }
        }
        $this->assertSame([], array_filter(WorkflowRules::errors($map['workflow'], $plan['slides'], $map), fn ($e) => str_contains($e, 'no slide')), 'every stage has a slide before the repair');

        $model = $this->completer([json_encode($plan)]);
        $planner = new SlidePlanner($model);
        try {
            $planner->plan($context, $map, $eligible);
            $this->fail('A plan whose only fix would drop a stage was accepted.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('4 recall/practice/exit_ticket slides; at most 3', $e->getMessage());
            $this->assertStringNotContainsString('has no slide', $e->getMessage(), 'no stage was lost to the repair');
        }
        $this->assertSame([], $planner->repairs);
    }

    public function test_slides_out_of_workflow_stage_order_inside_a_topic_are_put_back_in_order(): void
    {
        [$context, $map, $eligible] = $this->mapFor(6, 8);
        $map['workflow'] = ['stages' => [['number' => 1, 'name' => 'Engage'], ['number' => 2, 'name' => 'Explore'], ['number' => 3, 'name' => 'Explain']]];
        $plan = $this->plan();
        // Topic 10 (slides 2-3) arrives as stage 2 then 1; topic 11 (slides 4-5) is in order; the two last slides belong to no topic.
        foreach ([1 => 2, 2 => 1, 3 => 1, 4 => 2, 5 => 3, 6 => 3] as $i => $stage) {
            $plan['slides'][$i]['workflow_stage'] = $stage;
            $plan['slides'][$i]['question_ids'] = [];
        }
        $this->assertNotSame([], WorkflowRules::errors($map['workflow'], $plan['slides'], $map), 'the fixture really is out of order');

        $model = $this->completer([json_encode($plan)]);
        $planner = new SlidePlanner($model);
        $result = $planner->plan($context, $map, $eligible);

        $this->assertCount(1, $model->prompts, 'no repair round was needed');
        $this->assertSame(['Why models ignore details', 'What a model is'], array_column(array_slice($result['slides'], 1, 2), 'title'));
        $this->assertStringContainsString('workflow stage order', implode(' ', $planner->repairs));
    }

    public function test_a_reply_with_a_damaged_diagram_spec_is_read_without_the_diagram_instead_of_costing_a_repair_round(): void
    {
        [$context, $map, $eligible] = $this->mapFor(6, 8);
        // The model once wrote `Vnodes:` for `"nodes":` inside a diagram; the rest of the reply was fine.
        $reply = str_replace('"slide_type":"cover"', '"slide_type":"cover","diagram":{"layout":"flow","title":"A } brace","Vnodes":["a"],s:["b"]}', json_encode($this->plan()), $n);
        $this->assertSame(1, $n);
        $this->assertNull(json_decode($reply), 'the fixture reply really is invalid JSON');

        $model = $this->completer([$reply]);
        $planner = new SlidePlanner($model);
        $result = $planner->plan($context, $map, $eligible);

        $this->assertCount(1, $model->prompts, 'no repair round was needed');
        $this->assertStringContainsString('damaged diagram specs', implode(' ', $planner->repairs));
        $this->assertCount(7, $result['slides']);
    }

    public function test_withStagesInOrder_relabels_the_slide_that_breaks_the_order_and_keeps_every_slide_in_place(): void
    {
        $map = ['workflow' => ['stages' => array_map(fn ($n) => ['number' => $n, 'name' => "S$n"], range(1, 6))], 'concepts' => [1 => ['topic_id' => 10], 2 => ['topic_id' => 10], 3 => ['topic_id' => 11]]];
        $s = fn (string $t, array $c, ?int $stage) => ['slide_type' => $t, 'title' => $t . $stage, 'concept_ids' => $c, 'workflow_stage' => $stage];
        $slides = [
            $s('cover', [], null),
            $s('concept_visual', [1], 3),
            $s('concept_intro', [1], 4),
            $s('misconception', [1], 6),   // labelled Evaluate in the middle of the topic
            $s('concept_intro', [2], 4),
            $s('concept_intro', [3], 2),   // another topic: untouched
        ];

        $out = WorkflowRules::withStagesInOrder($slides, $map);

        $this->assertSame(array_column($slides, 'title'), array_column($out, 'title'), 'no slide moved');
        $this->assertSame([null, 3, 4, 4, 4, 2], array_column($out, 'workflow_stage'));
        $this->assertSame([], array_values(array_filter(WorkflowRules::errors($map['workflow'], $out, $map), fn ($e) => str_contains($e, 'follows stage'))));
    }

    public function test_orderViolations_names_each_slide_the_method_wants_earlier(): void
    {
        $s = fn (string $t, array $c) => ['slide_type' => $t, 'concept_ids' => $c];
        $slides = [$s('cover', []), $s('concept_intro', [5]), $s('misconception', [5]), $s('concept_intro', [6])];

        // inquiry_based: misconception before concept_intro. The misconception (position 2) must go before the intro (position 1).
        $this->assertSame([[2, 1]], \App\Services\StudyDeck\TeachingMethodProfile::orderViolations('inquiry_based', $slides));
        $this->assertSame([], \App\Services\StudyDeck\TeachingMethodProfile::orderViolations(null, $slides));
    }

    public function test_inStageOrder_only_moves_slides_inside_their_own_topic_and_never_across_a_slide_it_cannot_place(): void
    {
        $map = ['concepts' => [1 => ['topic_id' => 10], 2 => ['topic_id' => 10], 3 => ['topic_id' => 11]]];
        $s = fn (string $t, array $c, ?int $stage) => ['slide_type' => $t, 'title' => $t . implode('', $c) . $stage, 'concept_ids' => $c, 'workflow_stage' => $stage];
        $slides = [
            $s('cover', [], null),
            $s('concept_intro', [1], 3),
            $s('concept_intro', [2], 1),
            $s('concept_intro', [1], 1),      // same stage as the one before: keeps its place after it
            $s('hook', [], 2),                // no concept: stays, and nothing moves across it
            $s('concept_intro', [2], 2),
            $s('concept_intro', [1], 1),
            $s('concept_intro', [3], 2),      // another topic
            $s('concept_intro', [3], 1),
            $s('concept_intro', [3], null),   // no valid stage: stays, ends the stretch
            $s('concept_intro', [3], 1),
        ];

        // Titles are type + concept + stage, so 'concept_intro13' is a concept-1 slide at stage 3.
        $this->assertSame([
            'cover',
            'concept_intro21', 'concept_intro11', 'concept_intro13', // topic 10 up to the hook, by stage, ties in their old order
            'hook2',
            'concept_intro11', 'concept_intro22',                    // topic 10 after the hook, sorted on its own
            'concept_intro31', 'concept_intro32',                    // topic 11 up to the slide without a stage
            'concept_intro3',                                        // no stage: stays where it was
            'concept_intro31',
        ], array_column(WorkflowRules::inStageOrder($slides, $map), 'title'));
    }
}
