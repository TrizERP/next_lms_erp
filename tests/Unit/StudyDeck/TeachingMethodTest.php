<?php

namespace Tests\Unit\StudyDeck;

use App\Services\StudyDeck\ConceptContextBuilder;
use App\Services\StudyDeck\DeckValidator;
use App\Services\StudyDeck\ImagePlanner;
use App\Services\StudyDeck\LearningPlanBuilder;
use App\Services\StudyDeck\QuestionSelector;
use App\Services\StudyDeck\SlideContentGenerator;
use App\Services\StudyDeck\SlideHtmlRenderer;
use App\Services\StudyDeck\SlidePlanner;
use App\Services\StudyDeck\StudyDeckService;
use App\Services\StudyDeck\TeachingMethodProfile;
use Tests\Feature\CoursePresentation\CoursePresentationTestCase;

/**
 * The teaching method changes the deck: the planner is told the method, a plan that ignores it is rejected and sent
 * back, the slide-text and interaction prompts carry it, and a deck cannot pass validation while ignoring it. With no
 * method named nothing changes. Scripted model, synthetic chapter: no network, no model. It boots on the throwaway
 * sqlite base of the Course Presentation tests, never the shared database (TestsTestCase would read it at boot).
 */
class TeachingMethodTest extends CoursePresentationTestCase
{
    use StudyDeckFixture;

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

    /** The fixture's plan with slide types changed, so the plan has what a method needs. */
    private function planWith(array $types): array
    {
        $p = $this->plan();
        foreach ($types as $i => $type) {
            $p['slides'][$i]['slide_type'] = $type;
        }

        return $p;
    }

    /** The written slides of an inquiry deck: a prediction prompt, evidence to look at, and a conclusion that cites it. */
    private function inquiryText(): array
    {
        return $this->slideText([
            2 => ['body' => 'What do you think a model is, and why? Look closely at the evidence and compare what you notice.'],
            6 => ['body' => 'Conclusion: what can we say now? The evidence shows how models help us state laws.'],
        ]);
    }

    public function test_with_no_method_the_prompt_and_the_result_are_unchanged(): void
    {
        [$service, $model] = $this->service([json_encode($this->plan()), json_encode($this->slideText()), '{}', '{}']);
        $r = $service->fromRaw($this->raw(), ['min_slides' => 6, 'max_slides' => 8]);

        foreach ($model->prompts as $prompt) {
            $this->assertStringNotContainsString('TEACHING METHOD', $prompt);
        }
        $this->assertArrayNotHasKey('teaching_method', $r['map']);
        $this->assertArrayNotHasKey('teaching_method', $r['plan']);
        $this->assertArrayNotHasKey('teaching_method', $r['report']);
        $this->assertTrue($r['report']['ok'], implode(' | ', $r['report']['errors']));
    }

    public function test_a_plan_that_ignores_the_method_is_sent_back_with_the_reason_and_the_corrected_plan_is_used(): void
    {
        // Inquiry needs a scenario, a concept_visual and a misconception slide; the fixture plan has none.
        $bad = $this->plan();
        $good = $this->planWith([1 => 'concept_visual', 2 => 'scenario', 3 => 'misconception']);
        [$service, $model] = $this->service([json_encode($bad), json_encode($good), json_encode($this->inquiryText()), '{}', '{}']);

        $r = $service->fromRaw($this->raw(), ['min_slides' => 6, 'max_slides' => 8, 'teaching_method' => 'inquiry_based']);

        $this->assertStringContainsString('TEACHING METHOD: Inquiry-based learning', $model->prompts[0]);
        $this->assertStringContainsString('Observe and question -> Hypothesis -> Investigate and gather evidence -> Compare observations -> Conclusion -> Reflect', $model->prompts[0]);
        $this->assertStringContainsString('Your previous plan was rejected', $model->prompts[1]);
        $this->assertStringContainsString('needs at least 1 "scenario"', $model->prompts[1]);
        $this->assertStringContainsString('needs at least 1 "concept_visual"', $model->prompts[1]);
        $this->assertSame('inquiry_based', $r['plan']['teaching_method']);
        $this->assertSame(['concept_visual', 'scenario'], [$r['plan']['slides'][1]['slide_type'], $r['plan']['slides'][2]['slide_type']]);
        $this->assertTrue($r['report']['teaching_method']['followed']);
        // The slide-text prompt carries the method too.
        $content = array_values(array_filter($model->prompts, fn ($p) => str_contains($p, 'Write the slide text')))[0];
        $this->assertStringContainsString('TEACHING METHOD: Inquiry-based learning', $content);
        $this->assertStringContainsString('investigable question', $content);
    }

    public function test_a_plan_that_still_ignores_the_method_after_two_repairs_fails(): void
    {
        $bad = json_encode($this->plan());
        [$service] = $this->service([$bad, $bad, $bad]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('needs at least 1 "scenario"');
        $service->fromRaw($this->raw(), ['min_slides' => 6, 'max_slides' => 8, 'teaching_method' => 'inquiry_based']);
    }

    public function test_different_methods_ask_for_different_things_of_the_same_plan(): void
    {
        $slides = $this->plan()['slides'];
        $inquiry = TeachingMethodProfile::planErrors('inquiry_based', $slides);
        $gamified = TeachingMethodProfile::planErrors('gamified', $slides);
        $explicit = TeachingMethodProfile::planErrors('explicit_instruction', $slides);

        $this->assertNotSame([], $inquiry);
        $this->assertStringContainsString('"scenario"', implode(' ', $inquiry));
        $this->assertStringContainsString('"challenge"', implode(' ', $gamified));
        $this->assertStringNotContainsString('"challenge"', implode(' ', $inquiry));
        // The fixture plan is an explanation-first plan, which is what direct instruction asks for.
        $this->assertSame([], array_values(array_filter($explicit, fn ($e) => str_contains($e, 'concept_intro'))));
    }

    public function test_the_order_within_a_concept_is_enforced(): void
    {
        $s = fn (string $type, array $concepts) => ['slide_type' => $type, 'concept_ids' => $concepts];
        // Inquiry: for concept 1 the observation (concept_visual) must come before the explanation (concept_intro).
        $wrong = [$s('cover', []), $s('concept_intro', [1]), $s('concept_visual', [1]), $s('scenario', [2]), $s('summary', [])];
        $right = [$s('cover', []), $s('concept_visual', [1]), $s('concept_intro', [1]), $s('scenario', [2]), $s('misconception', [3]), $s('summary', [])];

        $errors = TeachingMethodProfile::planErrors('inquiry_based', $wrong);
        $this->assertStringContainsString('must be concept_visual then concept_intro', implode(' ', $errors));
        $this->assertSame([], TeachingMethodProfile::planErrors('inquiry_based', $right));
        // Concepts that have only one of the two slides are not constrained.
        $this->assertSame([], TeachingMethodProfile::planErrors('inquiry_based', [$s('cover', []), $s('concept_intro', [1]), $s('concept_visual', [2]), $s('scenario', [3]), $s('misconception', [4])]));
    }

    public function test_the_final_deck_is_held_to_the_method_even_if_the_plan_check_was_passed(): void
    {
        // replay() judges a saved plan by the current rules: a plan that ignores the method fails the deck report.
        [$first] = $this->service([json_encode($this->plan()), json_encode($this->slideText()), '{}', '{}']);
        $made = $first->fromRaw($this->raw(), ['min_slides' => 6, 'max_slides' => 8]);
        $this->assertTrue($made['report']['ok'], implode(' | ', $made['report']['errors']));

        [$service] = $this->service([]);
        $saved = ['plan' => $made['plan'], 'content' => $made['content'], 'images' => $made['images'], 'interactions' => $made['interactions']];
        $r = $service->replay($this->raw(), $saved, ['min_slides' => 6, 'max_slides' => 8, 'teaching_method' => 'inquiry_based']);

        $this->assertFalse($r['report']['ok']);
        $this->assertFalse($r['report']['teaching_method']['followed']);
        $this->assertStringContainsString('Inquiry-based learning', implode(' ', $r['report']['errors']));
    }

    public function test_every_profile_names_only_slide_types_the_planner_can_produce(): void
    {
        foreach (TeachingMethodProfile::all() as $key => $p) {
            $types = array_merge(array_keys($p['min']), array_merge(...array_map(fn ($m) => $m[0], $p['min_any'] ?: [[[]]])), array_merge(...($p['order'] ?: [[]])));
            foreach ($types as $type) {
                $this->assertContains($type, SlidePlanner::SLIDE_TYPES, "$key asks for $type");
            }
            $this->assertNotSame('', TeachingMethodProfile::plannerRule($key), $key);
            $this->assertNotSame('', TeachingMethodProfile::contentRule($key), $key);
            $this->assertNotSame('', TeachingMethodProfile::interactionRule($key), $key);
        }
        $this->assertSame(['explicit_instruction', 'inquiry_based', 'problem_based', 'project_based', 'experiential', 'collaborative', 'socratic', 'flipped_classroom', 'differentiated_mastery', 'gamified'], TeachingMethodProfile::keys());
        $this->assertSame('', TeachingMethodProfile::plannerRule(null));
        $this->assertSame([], TeachingMethodProfile::planErrors(null, []));
    }
}
