<?php

namespace Tests\Unit\StudyDeck;

use App\Services\StudyDeck\ConceptContextBuilder;
use App\Services\StudyDeck\LearningPlanBuilder;
use App\Services\StudyDeck\QuestionSelector;
use App\Services\StudyDeck\SlidePlanner;
use App\Services\StudyDeck\WorkflowRules;
use Tests\TestCase;

/**
 * Two workflow faults a model keeps making and a repair round keeps failing to fix (run rc4a02e2a54e2: "stage 3 (Reflection) has no slide" and a
 * competency named only through its outcomes): what the code may fix without asking again, and what it must leave to the model. The repairs
 * are inferences, so each test also pins down where they must NOT act. No database, no network.
 */
class WorkflowStageRepairTest extends TestCase
{
    use StudyDeckFixture;

    private const STAGES = [['number' => 1, 'name' => 'Concrete experience'], ['number' => 2, 'name' => 'Reflection'], ['number' => 3, 'name' => 'Conceptualization']];

    /** @return array{0:array<string,mixed>,1:array<string,mixed>,2:array<int,array<int,array<string,mixed>>>} */
    private function mapFor(array $workflow): array
    {
        $context = (new ConceptContextBuilder())->assemble($this->raw());
        $sel = (new QuestionSelector())->select($this->raw()['questions']);
        $map = (new LearningPlanBuilder())->build($context, $sel['eligible'], 6, 8, ['repair_rounds' => 0]);
        $map['workflow'] = $workflow;

        return [$context, $map, $sel['eligible']];
    }

    /** The fixture's six slides after the cover: topic 10 = slides 1-2 and topic 11 = slides 3-4 (by position), then two slides with no concept. */
    private function planWithStages(array $stages, array $over = []): array
    {
        $plan = $this->plan();
        foreach ($stages as $i => $stage) {
            $plan['slides'][$i]['workflow_stage'] = $stage;
            $plan['slides'][$i]['question_ids'] = [];
        }
        foreach ($over as $i => $fields) {
            $plan['slides'][$i] = $fields + $plan['slides'][$i];
        }

        return $plan;
    }

    // ---- the pure rules -------------------------------------------------------------------------------------------------------------

    public function test_missing_stages_are_the_numbers_no_slide_carries_and_the_cover_does_not_count(): void
    {
        $wf = ['stages' => self::STAGES];
        $slides = [['slide_type' => 'cover', 'workflow_stage' => 2], ['slide_type' => 'concept_intro', 'workflow_stage' => 1], ['slide_type' => 'concept_intro', 'workflow_stage' => 3]];

        $this->assertSame([2], WorkflowRules::missingStages($wf, $slides));
        $this->assertSame([], WorkflowRules::missingStages(null, $slides), 'no workflow, nothing to check');
    }

    public function test_only_a_slide_of_a_fitting_type_that_keeps_its_own_stage_in_the_topic_and_the_order_can_take_a_stage(): void
    {
        $map = ['concepts' => [1 => ['topic_id' => 10], 2 => ['topic_id' => 10], 3 => ['topic_id' => 11]]];
        $s = fn (string $type, array $c, int $stage) => ['slide_type' => $type, 'concept_ids' => $c, 'workflow_stage' => $stage];
        $slides = [
            ['slide_type' => 'cover', 'concept_ids' => [], 'workflow_stage' => null],
            $s('concept_intro', [1], 1),   // 1: topic 10
            $s('relationship', [1], 1),    // 2: topic 10, a fitting type, and stage 1 appears twice in the topic -> candidate
            $s('concept_intro', [1], 3),   // 3: topic 10, not a fitting type
            $s('relationship', [2], 3),    // 4: topic 10, directly after a stage-3 slide: giving it stage 2 would go backwards
            $s('relationship', [3], 3),    // 5: topic 11, but its own stage 3 appears once there -> the topic would lose it
        ];

        $reflect = WorkflowRules::typesFittingStage('Reflection');
        $this->assertSame([[2, 10]], WorkflowRules::stageCandidates($slides, $map, 2, $reflect));
        $this->assertNotSame([], WorkflowRules::stageCandidates($slides, $map, 2), 'without the type rule a concept_intro slide would also qualify');
    }

    public function test_a_stage_name_that_does_not_say_which_slide_types_carry_it_is_left_to_the_model(): void
    {
        $this->assertNotNull(WorkflowRules::typesFittingStage('Reflection'));
        $this->assertNotNull(WorkflowRules::typesFittingStage('Experimentation'));
        $this->assertNull(WorkflowRules::typesFittingStage('Group formation'));
        $this->assertNull(WorkflowRules::typesFittingStage('Introduction'));
        $this->assertNull(WorkflowRules::typesFittingStage('Conceptualization'), 'a concept explanation is what the model plans; it is never inferred');
    }

    public function test_a_competency_gets_its_own_ref_only_on_a_slide_that_already_lists_one_of_its_outcomes(): void
    {
        $wf = ['outcomes' => [['ref' => 'C1', 'type' => 'competency'], ['ref' => 'C1-LO-1', 'type' => 'learning_outcome'], ['ref' => 'C2', 'type' => 'competency'], ['ref' => 'C2-LO-1', 'type' => 'learning_outcome'], ['ref' => 'C3', 'type' => 'competency']]];
        $slides = [['outcome_refs' => ['C1-LO-1']], ['outcome_refs' => ['C1-LO-1', 'C2']], ['outcome_refs' => []]];

        [$out, $added] = WorkflowRules::withCompetencyRefs($wf, $slides);

        $this->assertSame(['C1' => 0], $added, 'C1 goes to the first slide that lists its outcome; C2 is listed already; C3 has no outcome listed anywhere');
        $this->assertSame(['C1-LO-1', 'C1'], $out[0]['outcome_refs']);
        $this->assertSame($slides[1], $out[1]);
        $this->assertSame([], $out[2]['outcome_refs']);
        $this->assertSame([[$slides[0]], []], [[['outcome_refs' => ['C1-LO-1']]], []], 'the input is not modified');
    }

    // ---- through the planner: one model reply, no repair round --------------------------------------------------------------------------

    public function test_a_missing_reflection_stage_is_given_to_a_fitting_slide_without_asking_the_model_again(): void
    {
        [$context, $map, $eligible] = $this->mapFor(['stages' => self::STAGES]);
        // Slides 1-2 are topic 10 (both stage 1), slides 3-4 topic 11 (both stage 3), the last two have no concept (stage 3). Stage 2 is missing.
        $plan = $this->planWithStages([1 => 1, 2 => 1, 3 => 3, 4 => 3, 5 => 3, 6 => 3]);
        $this->assertContains('Workflow stage 2 (Reflection) has no slide; every stage must appear.', WorkflowRules::errors($map['workflow'], $plan['slides'], $map));

        $model = $this->completer([json_encode($plan)]);
        $planner = new SlidePlanner($model);
        $result = $planner->plan($context, $map, $eligible);

        $this->assertCount(1, $model->prompts, 'no repair round was needed');
        $this->assertSame([1, 2, 3, 3, 3, 3], array_column(array_slice($result['slides'], 1), 'workflow_stage'), 'the relationship slide of topic 10 took the stage');
        $this->assertSame('relationship', $result['slides'][2]['slide_type']);
        $log = implode(' ', $planner->repairs);
        $this->assertStringContainsString('workflow stage 2 (Reflection) had no slide, so slide(s) 3 of a fitting type were given it', $log);
        $this->assertStringContainsString('inferred, not stated by the model', $log, 'the run log says this was inferred');
    }

    public function test_a_missing_stage_is_not_given_to_a_slide_that_does_not_fit_it(): void
    {
        [$context, $map, $eligible] = $this->mapFor(['stages' => self::STAGES]);
        // The same plan, but the two topic slides that could take the stage are plain concept explanations: nothing may be inferred.
        $plan = $this->planWithStages([1 => 1, 2 => 1, 3 => 3, 4 => 3, 5 => 3, 6 => 3], [2 => ['slide_type' => 'concept_intro', 'relationship' => null, 'taught_concept_ids' => [1]], 4 => ['slide_type' => 'concept_intro', 'relationship' => null, 'taught_concept_ids' => [3]]]);

        $planner = new SlidePlanner($this->completer([json_encode($plan), '{}']));
        try {
            $planner->plan($context, $map, $eligible);
            $this->fail('the plan has no reflection slide and must still be rejected');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Workflow stage 2 (Reflection) has no slide', $e->getMessage());
        }
        $this->assertStringNotContainsString('were given it', implode(' ', $planner->repairs));
    }

    public function test_a_competency_named_only_through_its_outcomes_is_completed_and_logged_as_inferred(): void
    {
        $workflow = ['stages' => self::STAGES, 'outcomes' => [['ref' => 'C1', 'type' => 'competency'], ['ref' => 'C1-LO-1', 'type' => 'learning_outcome']]];
        [$context, $map, $eligible] = $this->mapFor($workflow);
        $plan = $this->planWithStages([1 => 1, 2 => 2, 3 => 3, 4 => 3, 5 => 3, 6 => 3]);
        $plan['slides'][3]['outcome_refs'] = ['C1-LO-1'];
        $this->assertStringContainsString('C1', implode(' ', WorkflowRules::errors($map['workflow'], $plan['slides'], $map)));

        $model = $this->completer([json_encode($plan)]);
        $planner = new SlidePlanner($model);
        $result = $planner->plan($context, $map, $eligible);

        $this->assertCount(1, $model->prompts, 'no repair round was needed');
        $this->assertSame(['C1-LO-1', 'C1'], $result['slides'][3]['outcome_refs']);
        $this->assertStringContainsString('inferred from the outcome code', implode(' ', $planner->repairs));
    }

    public function test_a_competency_none_of_whose_outcomes_is_listed_is_still_sent_back_to_the_model(): void
    {
        $workflow = ['stages' => self::STAGES, 'outcomes' => [['ref' => 'C1', 'type' => 'competency'], ['ref' => 'C1-LO-1', 'type' => 'learning_outcome']]];
        [$context, $map, $eligible] = $this->mapFor($workflow);
        $plan = $this->planWithStages([1 => 1, 2 => 2, 3 => 3, 4 => 3, 5 => 3, 6 => 3]);

        $planner = new SlidePlanner($this->completer([json_encode($plan), '{}']));
        try {
            $planner->plan($context, $map, $eligible);
            $this->fail('nothing lists C1 or its outcome, so the plan must be rejected');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('C1', $e->getMessage());
        }
        $this->assertStringNotContainsString('competency ref', implode(' ', $planner->repairs));
    }
}
