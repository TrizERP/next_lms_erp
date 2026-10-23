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
 * Inquiry-based learning and Experiential / learning by doing must produce genuinely different lessons from the same chapter, not the
 * same lesson under two labels: a different sequence in the prompts the model is given, a different plan accepted, different things
 * the WRITTEN slides must contain, and a deck that follows one fails the other. Scripted model, no network.
 */
class TeachingMethodDifferenceTest extends CoursePresentationTestCase
{
    use StudyDeckFixture;

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

    private function planWith(array $types): array
    {
        $p = $this->plan();
        foreach ($types as $i => $type) {
            $p['slides'][$i]['slide_type'] = $type;
        }

        return $p;
    }

    private function inquiryText(): array
    {
        return $this->slideText([
            2 => ['body' => 'What do you think a model is, and why? Look closely at the evidence and compare what you notice.'],
            6 => ['body' => 'Conclusion: what can we say now? The evidence shows how models help us state laws.'],
        ]);
    }

    private function experientialText(): array
    {
        return $this->slideText([
            2 => ['body' => 'You will need paper, a pencil and a ruler. Get ready to draw a model of your desk.'],
            3 => ['body' => 'First, draw your desk. Then, leave out one detail. Next, show a partner.'],
            4 => ['body' => 'Record what you notice: write down which detail you left out.'],
            6 => ['body' => 'Where do you do this in daily life? At home, a plan of your room is a model.'],
        ]);
    }

    public function test_the_two_methods_give_the_model_different_instructions_for_the_same_chapter(): void
    {
        $inquiry = [TeachingMethodProfile::plannerRule('inquiry_based'), TeachingMethodProfile::contentRule('inquiry_based'), TeachingMethodProfile::interactionRule('inquiry_based')];
        $doing = [TeachingMethodProfile::plannerRule('experiential'), TeachingMethodProfile::contentRule('experiential'), TeachingMethodProfile::interactionRule('experiential')];

        foreach ([0, 1, 2] as $i) {
            $this->assertNotSame($inquiry[$i], $doing[$i], "rule $i differs");
        }
        $in = mb_strtolower(implode(' ', $inquiry));
        $ex = mb_strtolower(implode(' ', $doing));
        // Inquiry: question, hypothesis, evidence, comparison, conclusion, reflection; no telling first.
        foreach (['hypothesis', 'evidence', 'investigable question', 'conclusion', 'reflect', 'what do you think'] as $word) {
            $this->assertStringContainsString($word, $in, "inquiry says $word");
        }
        // Experiential: materials, steps, recording, discussion, real life.
        foreach (['materials', 'step', 'record', 'discuss', 'real life', 'you will need'] as $word) {
            $this->assertStringContainsString($word, $ex, "experiential says $word");
        }
        // ...and each does not borrow the other's defining move.
        $this->assertStringNotContainsString('hypothesis', $ex);
        $this->assertStringNotContainsString('you will need', $in);
        $this->assertNotSame(TeachingMethodProfile::get('inquiry_based')['phases'], TeachingMethodProfile::get('experiential')['phases']);
        $this->assertSame(['Observe and question', 'Hypothesis', 'Investigate and gather evidence', 'Compare observations', 'Conclusion', 'Reflect'], TeachingMethodProfile::get('inquiry_based')['phases']);
        $this->assertSame(['Task and materials', 'Do it, step by step', 'Record observations', 'Discuss the result', 'Apply to real life'], TeachingMethodProfile::get('experiential')['phases']);
    }

    public function test_the_prompts_a_run_really_sends_differ_by_method(): void
    {
        $prompts = [];
        foreach (['inquiry_based' => [$this->planWith([1 => 'concept_visual', 2 => 'scenario', 3 => 'misconception']), $this->inquiryText()],
            'experiential' => [$this->planWith([1 => 'concept_visual', 2 => 'scenario', 3 => 'application']), $this->experientialText()]] as $method => [$plan, $text]) {
            [$service, $model] = $this->service([json_encode($plan), json_encode($text), '{}', '{}']);
            $service->fromRaw($this->raw(), ['min_slides' => 6, 'max_slides' => 8, 'teaching_method' => $method]);
            $prompts[$method] = implode("\n", $model->prompts);
        }

        $this->assertStringContainsString('Hypothesis', $prompts['inquiry_based']);
        $this->assertStringNotContainsString('Hypothesis', $prompts['experiential']);
        $this->assertStringContainsString('Task and materials', $prompts['experiential']);
        $this->assertStringNotContainsString('Task and materials', $prompts['inquiry_based']);
        $this->assertStringContainsString('TEACHING METHOD: Inquiry-based learning', $prompts['inquiry_based']);
        $this->assertStringContainsString('TEACHING METHOD: Experiential / learning by doing', $prompts['experiential']);
        $this->assertStringNotContainsString('Experiential', $prompts['inquiry_based']);
    }

    public function test_a_plan_that_suits_one_method_is_refused_by_the_other(): void
    {
        $inquiryPlan = $this->planWith([1 => 'concept_visual', 2 => 'scenario', 3 => 'misconception'])['slides'];
        $doingPlan = $this->planWith([1 => 'concept_visual', 2 => 'scenario', 3 => 'application'])['slides'];

        $this->assertSame([], TeachingMethodProfile::planErrors('inquiry_based', $inquiryPlan));
        $this->assertSame([], TeachingMethodProfile::planErrors('experiential', $doingPlan));
        $this->assertNotSame([], TeachingMethodProfile::planErrors('experiential', $inquiryPlan), 'an inquiry plan has no hands-on application stage');
        $this->assertStringContainsString('"application"', implode(' ', TeachingMethodProfile::planErrors('experiential', $inquiryPlan)));
        $this->assertNotSame([], TeachingMethodProfile::planErrors('inquiry_based', $doingPlan), 'an experiential plan has no misconception test');
        $this->assertStringContainsString('"misconception"', implode(' ', TeachingMethodProfile::planErrors('inquiry_based', $doingPlan)));
    }

    public function test_written_slides_must_show_the_methods_own_sequence(): void
    {
        $texts = fn (array $deck) => array_map(fn ($s) => mb_strtolower(json_encode($s, JSON_UNESCAPED_UNICODE)), $deck['slides']);
        $inquiry = $texts($this->inquiryText());
        $doing = $texts($this->experientialText());
        $plain = $texts($this->slideText());

        $this->assertSame([], TeachingMethodProfile::contentErrors('inquiry_based', $inquiry));
        $this->assertSame([], TeachingMethodProfile::contentErrors('experiential', $doing));
        // Each deck fails the OTHER method, and a plain explanation fails both.
        $this->assertStringContainsString('hypothesis or prediction', implode(' ', TeachingMethodProfile::contentErrors('inquiry_based', $doing)));
        $this->assertStringContainsString('materials and preparation', implode(' ', TeachingMethodProfile::contentErrors('experiential', $inquiry)));
        $this->assertNotSame([], TeachingMethodProfile::contentErrors('inquiry_based', $plain));
        $this->assertNotSame([], TeachingMethodProfile::contentErrors('experiential', $plain));
        // Methods with no content markers, and no method, ask nothing extra.
        $this->assertSame([], TeachingMethodProfile::contentErrors('explicit_instruction', $plain));
        $this->assertSame([], TeachingMethodProfile::contentErrors(null, $plain));
    }

    public function test_a_finished_deck_for_one_method_fails_validation_when_it_was_written_the_other_way(): void
    {
        // Same plan type-wise acceptable to inquiry, but its slides were written as a plain explanation: the report must say the method was not followed.
        $plan = $this->planWith([1 => 'concept_visual', 2 => 'scenario', 3 => 'misconception']);
        [$service] = $this->service([json_encode($plan), json_encode($this->slideText()), '{}', '{}']);
        $r = $service->fromRaw($this->raw(), ['min_slides' => 6, 'max_slides' => 8, 'teaching_method' => 'inquiry_based']);

        $this->assertFalse($r['report']['ok']);
        $this->assertFalse($r['report']['teaching_method']['followed']);
        $this->assertStringContainsString('hypothesis or prediction', implode(' ', $r['report']['errors']));

        // The same plan with its slides written as an inquiry passes.
        [$service] = $this->service([json_encode($plan), json_encode($this->inquiryText()), '{}', '{}']);
        $ok = $service->fromRaw($this->raw(), ['min_slides' => 6, 'max_slides' => 8, 'teaching_method' => 'inquiry_based']);
        $this->assertTrue($ok['report']['teaching_method']['followed'], implode(' | ', $ok['report']['teaching_method']['errors']));
    }

    public function test_a_run_can_require_a_picture_or_diagram_on_every_concept_slide(): void
    {
        $planner = new SlidePlanner($this->completer([]));
        $slides = $this->plan()['slides'];
        $map = ['target_slides' => ['min' => 1, 'max' => 40], 'concepts' => [1 => ['name' => 'Models'], 2 => ['name' => 'Ignoring details'], 3 => ['name' => 'Laws'], 4 => ['name' => 'Theories']], 'limits' => ['require_concept_visuals' => 1, 'max_visuals' => 24]];

        $this->assertSame(1, SlidePlanner::limits($map)['require_concept_visuals']);
        $this->assertSame(0, SlidePlanner::limits(['limits' => []])['require_concept_visuals'], 'off by default: a default run is unchanged');

        // A plan is not refused for forgetting a visual (a weaker model does): the pipeline adds a search for each concept slide's own concept.
        $filled = $planner->withConceptVisuals(['slides' => $slides], $map)['slides'];
        $concept = array_values(array_filter($filled, fn ($s) => in_array($s['slide_type'], ['concept_intro', 'concept_visual'], true)));
        $this->assertNotEmpty($concept);
        foreach ($concept as $s) {
            $this->assertTrue($s['visual']['required'], 'slide ' . $s['title']);
            $this->assertNotSame('', $s['visual']['query']);
            $this->assertLessThanOrEqual(5, count(explode(' ', $s['visual']['query'])));
        }
        // Framing slides get none, and a run that does not ask for it is unchanged.
        $this->assertNull($filled[0]['visual'] ?? null);
        $this->assertSame(['slides' => $slides], $planner->withConceptVisuals(['slides' => $slides], ['limits' => ['max_visuals' => 24]] + $map));
        // Never more than the cap.
        $capped = $planner->withConceptVisuals(['slides' => $slides], ['limits' => ['require_concept_visuals' => 1, 'max_visuals' => 1]] + $map)['slides'];
        $this->assertCount(1, array_filter($capped, fn ($s) => !empty($s['visual']['required'])));
    }
}
