<?php

namespace Tests\Unit\StudyDeck;

use App\Services\CoursePresentation\GenerationRun;
use App\Services\CoursePresentation\PedagogicalWorkflow;
use App\Services\StudyDeck\ConceptContextBuilder;
use App\Services\StudyDeck\DeckValidator;
use App\Services\StudyDeck\ImagePlanner;
use App\Services\StudyDeck\LearningPlanBuilder;
use App\Services\StudyDeck\QuestionSelector;
use App\Services\StudyDeck\SlideContentGenerator;
use App\Services\StudyDeck\SlideHtmlRenderer;
use App\Services\StudyDeck\SlidePlanner;
use App\Services\StudyDeck\StudyDeckService;
use App\Services\StudyDeck\WorkflowRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\CoursePresentation\CoursePresentationTestCase;

/**
 * Learning outcomes, competencies, the stored method context and the workflow stages: from the database rows to the prompt, and from the
 * model's reply to the validated, stored deck. A scripted model drives the real StudyDeckService (no network); the sqlite base is the
 * throwaway one of the Course Presentation tests, never the shared database.
 */
class WorkflowEnforcementTest extends CoursePresentationTestCase
{
    use StudyDeckFixture;

    protected bool $workflowPilot = true;

    /** The pilot chapter's real outcome and competency codes; the wording is test text. */
    private const OUTCOME_CODES = [
        'C5.2-LO-3', 'C5.2-LO-4', 'C5.2-LO-5', 'C5.2-LO-6', 'C5.2-LO-7', 'C7.1-LO-4', 'C7.1-LO-5', 'C7.1-LO-6', 'C7.1-LO-7', 'C7.1-LO-8',
        'C8.2-LO-6', 'C8.2-LO-7', 'C8.2-LO-8', 'C8.2-LO-9', 'C8.2-LO-10',
    ];
    private const COMPETENCY_CODES = ['C5.2', 'C7.1', 'C8.2'];

    /** @return array<int,array{type:string,code:string,description:string}> 15 outcomes and 3 competencies */
    private function outcomeRows(): array
    {
        $rows = [];
        foreach (self::COMPETENCY_CODES as $c) {
            $rows[] = ['type' => 'competency', 'code' => $c, 'description' => "Competency text for $c."];
        }
        foreach (self::OUTCOME_CODES as $c) {
            $rows[] = ['type' => 'learning_outcome', 'code' => $c, 'description' => "Learning outcome text for $c."];
        }

        return $rows;
    }

    /** A three-stage context for the fixture's six non-cover slides, with four outcomes of its own. @return array<string,mixed> */
    private function smallContext(array $rows = null): array
    {
        $w = new PedagogicalWorkflow();
        $ctx = $w->generationContext('collaborative', 'interactive_slides');
        $ctx['stages'] = array_slice($ctx['stages'], 0, 3);

        return $w->withOutcomes($ctx, $rows ?? [
            ['type' => 'competency', 'code' => 'C1', 'description' => 'Competency one.'],
            ['type' => 'learning_outcome', 'code' => 'C1-LO-1', 'description' => 'Outcome one.'],
            ['type' => 'learning_outcome', 'code' => 'C1-LO-2', 'description' => 'Outcome two.'],
        ]);
    }

    /** @return array{0:StudyDeckService,1:object} */
    private function service(array $replies): array
    {
        $c = $this->completer($replies);
        $images = new ImagePlanner($this->imageSearch($this->goodImage()), $this->memoryStore(), fn () => $this->png(), 2.0, null);

        return [new StudyDeckService(
            new ConceptContextBuilder(), new QuestionSelector(), new LearningPlanBuilder(),
            new SlidePlanner($c), new SlideContentGenerator($c, 8), $images,
            new SlideHtmlRenderer(), new DeckValidator(images: $images),
        ), $c];
    }

    /** The fixture plan typed for the collaborative method, with stages 1-3 and outcome refs on its non-cover slides. */
    private function stagedPlan(bool $withStages = true, array $refs = [['C1'], ['C1-LO-1'], ['C1-LO-1'], ['C1-LO-2'], [], []]): array
    {
        $p = $this->plan();
        // No planned picture: the fixture's picture stage already fails in this environment (TeachingMethodTest has the same failure), and this test is about stages and outcomes.
        $p['slides'][1]['visual'] = null;
        foreach ([3 => 'application', 4 => 'challenge', 5 => 'summary'] as $i => $type) {
            $p['slides'][$i]['slide_type'] = $type;
        }
        if ($withStages) {
            // Slides 2-3 are topic 10 (stages 1,2), slides 4-5 topic 11 (stages 2,3), then the two framing slides.
            foreach ([1 => 1, 2 => 2, 3 => 2, 4 => 3, 5 => 3, 6 => 3] as $i => $stage) {
                $p['slides'][$i]['workflow_stage'] = $stage;
                $p['slides'][$i]['outcome_refs'] = $refs[$i - 1];
            }
        }

        return $p;
    }

    private function generate(array $replies, ?array $context): array
    {
        [$service, $model] = $this->service($replies);
        $options = ['min_slides' => 6, 'max_slides' => 8, 'teaching_method' => 'collaborative'] + ($context !== null ? ['workflow' => $context] : []);

        return [$service->fromRaw($this->raw(), $options), $model];
    }

    // ---- 1. outcomes and competencies reach the prompts -------------------------------------------------------------

    public function test_all_fifteen_outcomes_and_three_competencies_reach_every_prompt_kind(): void
    {
        $w = new PedagogicalWorkflow();
        $ctx = $w->withOutcomes($w->generationContext('inquiry_based', 'interactive_slides'), $this->outcomeRows());
        $this->assertCount(18, $ctx['outcomes']);
        $this->assertSame(3, count(array_filter($ctx['outcomes'], fn ($o) => $o['type'] === 'competency')));

        foreach (['planner', 'content', 'interaction', 'story'] as $kind) {
            $rule = PedagogicalWorkflow::rule($ctx, $kind);
            foreach ($this->outcomeRows() as $o) {
                $this->assertStringContainsString("- {$o['code']} ({$o['type']}): {$o['description']}", $rule, "$kind lacks {$o['code']}");
            }
            $this->assertStringContainsString('never invent', $rule);
        }
        $this->assertStringContainsString('outcome_refs', PedagogicalWorkflow::rule($ctx, 'planner'));
    }

    public function test_the_stored_outcomes_are_loaded_from_the_database_rows_of_the_chapter(): void
    {
        $this->seedPilotChapter(); // seeds 3 rows with no description: they must be left out, not invented into text
        if (!Schema::hasColumn('lms_learning_outcomes', 'description')) {
            Schema::table('lms_learning_outcomes', fn ($t) => $t->text('description')->nullable());
        }
        $id = 6000;
        foreach ($this->outcomeRows() as $o) {
            DB::table('lms_learning_outcomes')->insert(['id' => $id++, 'curriculum_id' => 82, 'extraction_id' => 52, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'chapter_id' => self::CHAPTER, 'type' => $o['type'], 'code' => $o['code'], 'description' => $o['description']]);
        }
        // Another chapter's outcome must not enter.
        DB::table('lms_learning_outcomes')->insert(['id' => 7000, 'curriculum_id' => 82, 'extraction_id' => 52, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'chapter_id' => 9999, 'type' => 'learning_outcome', 'code' => 'OTHER-1', 'description' => 'Another chapter.']);

        $w = new PedagogicalWorkflow();
        $method = new \ReflectionMethod(GenerationRun::class, 'withStoredOutcomes');
        $method->setAccessible(true);
        $ctx = $method->invoke(app(GenerationRun::class), $w, $w->generationContext('inquiry_based', 'interactive_slides'), self::CHAPTER, self::TENANT);

        $this->assertCount(18, $ctx['outcomes']);
        $this->assertEqualsCanonicalizing(array_merge(self::OUTCOME_CODES, self::COMPETENCY_CODES), array_column($ctx['outcomes'], 'ref'));
        $this->assertNull($ctx['outcomes_note']);
    }

    public function test_missing_outcomes_are_never_invented_and_the_deck_says_they_were_not_checked(): void
    {
        $w = new PedagogicalWorkflow();
        $none = $w->withOutcomes($w->generationContext('inquiry_based', 'interactive_slides'), []);
        $this->assertSame([], $none['outcomes']);
        $this->assertStringContainsString('not checked', (string) $none['outcomes_note']);
        $this->assertStringNotContainsString('LEARNING OUTCOMES AND COMPETENCIES', PedagogicalWorkflow::rule($none, 'planner'));
        $this->assertStringNotContainsString('outcome_refs', PedagogicalWorkflow::rule($none, 'planner'));

        // A row without a description or code is dropped, not given made-up text.
        $partial = $w->withOutcomes($w->generationContext('inquiry_based', 'interactive_slides'), [['type' => 'learning_outcome', 'code' => 'X', 'description' => ''], ['type' => 'learning_outcome', 'code' => '', 'description' => 'No code.'], ['type' => 'competency', 'code' => 'C1', 'description' => 'Real.']]);
        $this->assertSame(['C1'], array_column($partial['outcomes'], 'ref'));

        // End to end: stages are still enforced, outcome coverage is reported "not checked" with a warning, and the deck still passes.
        $ctx = $this->smallContext([]);
        [$r] = $this->generate([json_encode($this->stagedPlan(true, [[], [], [], [], [], []])), json_encode($this->slideText()), '{}', '{}'], $ctx);
        $v = $r['deck']['workflow']['validation'];
        $this->assertTrue($v['validated'], implode(' | ', $v['errors']));
        $this->assertFalse($v['outcomes_checked']);
        $this->assertContains((string) $ctx['outcomes_note'], $r['report']['warnings']);
    }

    // ---- 2. coverage validation -------------------------------------------------------------------------------------

    public function test_the_rules_reject_missing_stage_wrong_order_unknown_and_uncovered_outcomes(): void
    {
        $ctx = $this->smallContext();
        $map = ['concepts' => [1 => ['topic_id' => 10], 2 => ['topic_id' => 10], 3 => ['topic_id' => 11]]];
        $s = fn (int $n, ?int $stage, array $refs, array $c = [1]) => ['n' => $n, 'slide_type' => 'concept_intro', 'concept_ids' => $c, 'workflow_stage' => $stage, 'outcome_refs' => $refs];
        $cover = ['n' => 1, 'slide_type' => 'cover', 'concept_ids' => []];

        $good = [$cover, $s(2, 1, ['C1']), $s(3, 2, ['C1-LO-1']), $s(4, 3, ['C1-LO-2'], [3])];
        $this->assertSame([], WorkflowRules::errors($ctx, $good, $map));
        $this->assertTrue(WorkflowRules::summary($ctx, $good, $map)['validated']);

        $this->assertStringContainsString('no valid "workflow_stage"', implode(' ', WorkflowRules::errors($ctx, [$cover, $s(2, null, ['C1']), $s(3, 2, ['C1-LO-1']), $s(4, 3, ['C1-LO-2'], [3])], $map)));
        $this->assertStringContainsString('stage 1', implode(' ', WorkflowRules::errors($ctx, [$cover, $s(2, 2, ['C1']), $s(3, 1, ['C1-LO-1']), $s(4, 3, ['C1-LO-2'], [3])], $map)), 'order inside one topic');
        // The stage sequence may start again in another topic.
        $this->assertSame([], WorkflowRules::errors($ctx, [$cover, $s(2, 1, ['C1']), $s(3, 3, ['C1-LO-1']), $s(4, 1, ['C1-LO-2'], [3]), $s(5, 2, [], [3]), $s(6, 3, [], [3])], $map));
        $this->assertStringContainsString('Workflow stage 3', implode(' ', WorkflowRules::errors($ctx, [$cover, $s(2, 1, ['C1']), $s(3, 2, ['C1-LO-1']), $s(4, 2, ['C1-LO-2'], [3])], $map)));
        $this->assertStringContainsString('"C9"', implode(' ', WorkflowRules::errors($ctx, [$cover, $s(2, 1, ['C1', 'C9']), $s(3, 2, ['C1-LO-1']), $s(4, 3, ['C1-LO-2'], [3])], $map)));

        $cov = WorkflowRules::summary($ctx, [$cover, $s(2, 1, ['C1']), $s(3, 2, ['C1-LO-1']), $s(4, 3, [], [3])], $map);
        $this->assertFalse($cov['validated']);
        $this->assertSame(['C1-LO-2'], $cov['missing_outcomes']);
        $this->assertSame([2], $cov['outcomes']['C1']);
        $this->assertStringContainsString('no slide addresses', implode(' ', $cov['errors']));
    }

    public function test_all_thirty_two_concepts_stay_mandatory_with_a_workflow_even_if_every_outcome_is_covered(): void
    {
        $concepts = [];
        $slides = [['slide_type' => 'cover', 'title' => 'Cover', 'teaches' => 'x', 'concept_ids' => [], 'taught_concept_ids' => []]];
        for ($id = 1; $id <= 32; $id++) {
            $concepts[$id] = ['name' => "Concept $id", 'topic_id' => 1, 'definition' => 'd'];
        }
        // 31 slides teaching concepts 1-31; concept 32 is taught by no slide.
        for ($id = 1; $id <= 31; $id++) {
            $slides[] = ['slide_type' => 'concept_intro', 'title' => "Slide $id", 'teaches' => 'x', 'concept_ids' => [$id], 'taught_concept_ids' => [$id], 'workflow_stage' => 1 + intdiv($id - 1, 11), 'outcome_refs' => ['C1', 'C1-LO-1', 'C1-LO-2']];
        }
        $map = ['concepts' => $concepts, 'target_slides' => ['min' => 1, 'max' => 40], 'workflow' => $this->smallContext()];
        $planner = new SlidePlanner($this->completer([]));
        $errors = $planner->validate(['slides' => $slides], $map, []);

        $this->assertSame([], WorkflowRules::errors($map['workflow'], $slides, $map), 'stages and outcomes are satisfied');
        $this->assertStringContainsString('Concepts that no slide teaches', implode(' ', $errors));
        $this->assertStringContainsString('Concept 32 (32)', implode(' ', $errors));
        $this->assertStringNotContainsString('Concept 31 (', implode(' ', $errors));
    }

    // ---- 3 and 4. the real execution path ---------------------------------------------------------------------------

    public function test_a_plan_without_stages_is_sent_back_and_the_repaired_plan_carries_stages_and_outcomes_into_the_stored_deck(): void
    {
        $ctx = $this->smallContext();
        [$r, $model] = $this->generate([json_encode($this->stagedPlan(false)), json_encode($this->stagedPlan(true)), json_encode($this->slideText()), '{}', '{}'], $ctx);

        // The first plan broke the rules: it was returned with the reasons, and the corrected plan was used.
        $this->assertStringContainsString('Your previous plan was rejected', $model->prompts[1]);
        $this->assertStringContainsString('no valid "workflow_stage"', $model->prompts[1]);
        $this->assertTrue($r['report']['ok'], implode(' | ', $r['report']['errors']));

        // What the planner and the writer were told.
        $this->assertStringContainsString('PEDAGOGICAL WORKFLOW', $model->prompts[0]);
        $this->assertStringContainsString('- C1-LO-2 (learning_outcome): Outcome two.', $model->prompts[0]);
        // The reply TEMPLATE lists the new fields (a real model followed the template and left them out when it did not), and a repair says to keep them.
        $this->assertStringContainsString('"workflow_stage": a whole number from 1 to 3', $model->prompts[0]);
        $this->assertStringContainsString('"outcome_refs": [refs, copied exactly', $model->prompts[0]);
        $this->assertStringContainsString('Keep "workflow_stage" and "outcome_refs" on every slide', $model->prompts[1]);
        $content = array_values(array_filter($model->prompts, fn ($p) => str_contains($p, 'Write the slide text')))[0];
        $this->assertStringContainsString('"workflow_stage":2', $content);
        $this->assertStringContainsString('"outcome_refs":["C1-LO-1"]', $content);

        // What was stored: every non-cover slide names its stage and outcomes, in order, and the deck proves it with the same rules.
        $deck = json_decode((string) json_encode($r['deck']), true);
        $slides = $deck['slides'];
        $this->assertNull($slides[0]['workflow_stage'], 'the cover belongs to no stage');
        $this->assertSame([1, 2, 2, 3, 3, 3], array_column(array_slice($slides, 1), 'workflow_stage'));
        $this->assertSame(
            ['Group formation', 'Shared learning goal', 'Shared learning goal', 'Collaborative activity', 'Collaborative activity', 'Collaborative activity'],
            array_column(array_slice($slides, 1), 'workflow_stage_name')
        );
        $this->assertSame(['C1'], $slides[1]['outcome_refs']);
        $w = $deck['workflow'];
        $this->assertSame('collaborative', $w['method']['key']);
        $this->assertCount(3, $w['stages']);
        $this->assertTrue($w['validation']['validated']);
        $this->assertTrue($w['validation']['outcomes_checked']);
        $this->assertSame([], $w['validation']['missing_outcomes']);
        $this->assertSame([2], $w['validation']['outcomes']['C1']);
        $this->assertSame(['validated' => true], array_intersect_key($r['report']['workflow'], ['validated' => 1]));
    }

    public function test_a_plan_that_never_fixes_its_stages_fails_the_run_instead_of_claiming_the_workflow(): void
    {
        $bad = json_encode($this->stagedPlan(false));
        [$service] = $this->service([$bad, $bad, $bad]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no valid "workflow_stage"');
        $service->fromRaw($this->raw(), ['min_slides' => 6, 'max_slides' => 8, 'teaching_method' => 'collaborative', 'workflow' => $this->smallContext()]);
    }

    public function test_the_final_deck_is_judged_again_and_a_saved_plan_with_a_gap_fails_validation(): void
    {
        $ctx = $this->smallContext();
        [$made] = $this->generate([json_encode($this->stagedPlan(true)), json_encode($this->slideText()), '{}', '{}'], $ctx);
        $this->assertTrue($made['report']['ok'], implode(' | ', $made['report']['errors']));

        // Replay the saved plan with one outcome no longer addressed and one slide's stage removed.
        $plan = $made['plan'];
        $plan['slides'][4]['outcome_refs'] = [];
        unset($plan['slides'][2]['workflow_stage']);
        [$service] = $this->service([]);
        $r = $service->replay($this->raw(), ['plan' => $plan, 'content' => $made['content'], 'images' => $made['images'], 'interactions' => $made['interactions']], ['min_slides' => 6, 'max_slides' => 8, 'teaching_method' => 'collaborative', 'workflow' => $ctx]);

        $this->assertFalse($r['report']['ok']);
        $this->assertFalse($r['report']['workflow']['validated']);
        $text = implode(' ', $r['report']['errors']);
        $this->assertStringContainsString('no valid "workflow_stage"', $text);
        $this->assertStringContainsString('C1-LO-2', $text);
    }

    public function test_a_story_lesson_is_reported_as_not_validated_for_stages_and_outcomes(): void
    {
        $r = new \ReflectionMethod(SlideHtmlRenderer::class, 'workflowBlock');
        $r->setAccessible(true);
        $out = $r->invoke(new SlideHtmlRenderer(), $this->smallContext(), [], ['story_mode' => true, 'concepts' => []]);

        $this->assertFalse($out['workflow']['validation']['validated']);
        $this->assertStringContainsString('not validated', $out['workflow']['validation']['reason']);
    }

    // ---- 5. the stored method context reaches the right prompts -----------------------------------------------------

    public function test_every_stored_method_field_reaches_the_prompts_that_need_it(): void
    {
        $w = new PedagogicalWorkflow();
        $ctx = $w->generationContext('inquiry_based', 'interactive_slides');
        $ctx['method'] = ['definition' => 'DEF-X', 'philosophy' => 'PHIL-X', 'principles' => ['PRIN-X'], 'strategies' => ['STRAT-X'], 'teacher_role' => 'TEACH-X', 'student_role' => 'STUD-X',
            'activity_types' => ['ACT-X'], 'interaction_types' => ['INT-X'], 'questioning' => ['QUEST-X'], 'assessment' => 'ASSESS-X', 'ai_instructions' => 'AI-X'] + $ctx['method'];

        $expect = [
            'planner' => ['DEF-X', 'PHIL-X', 'PRIN-X', 'STRAT-X', 'ACT-X', 'ASSESS-X', 'AI-X'],
            'content' => ['PHIL-X', 'PRIN-X', 'TEACH-X', 'STUD-X', 'ACT-X', 'QUEST-X', 'ASSESS-X', 'AI-X'],
            'interaction' => ['STUD-X', 'ACT-X', 'INT-X', 'QUEST-X', 'AI-X'],
            'story' => ['DEF-X', 'PHIL-X', 'PRIN-X', 'STRAT-X', 'TEACH-X', 'STUD-X', 'ACT-X', 'INT-X', 'QUEST-X', 'ASSESS-X', 'AI-X'],
        ];
        foreach ($expect as $kind => $markers) {
            $rule = PedagogicalWorkflow::rule($ctx, $kind);
            foreach ($markers as $marker) {
                $this->assertStringContainsString($marker, $rule, "$kind lacks $marker");
            }
        }
    }

    // ---- 6. everything outside the pilot is unchanged ---------------------------------------------------------------

    public function test_without_a_workflow_nothing_about_stages_or_outcomes_appears_anywhere(): void
    {
        $this->assertSame([], WorkflowRules::errors(null, [['slide_type' => 'concept_intro']], []));
        $this->assertSame('', PedagogicalWorkflow::rule(null, 'planner'));

        [$r, $model] = $this->generate([json_encode($this->stagedPlan(false)), json_encode($this->slideText()), '{}', '{}'], null);
        foreach ($model->prompts as $p) {
            $this->assertStringNotContainsString('PEDAGOGICAL WORKFLOW', $p);
            $this->assertStringNotContainsString('workflow_stage', $p);
            $this->assertStringNotContainsString('outcome_refs', $p);
        }
        $this->assertArrayNotHasKey('workflow', $r['deck']);
        $this->assertArrayNotHasKey('workflow', $r['report']);
        $this->assertArrayNotHasKey('workflow', $r['map']);
        foreach ($r['deck']['slides'] as $slide) {
            $this->assertArrayNotHasKey('workflow_stage', $slide);
            $this->assertArrayNotHasKey('outcome_refs', $slide);
        }
        $this->assertTrue($r['report']['ok'], implode(' | ', $r['report']['errors']));
    }
}
