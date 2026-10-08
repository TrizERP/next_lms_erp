<?php

namespace Tests\Unit\StudyDeck;

use App\Services\StudyDeck\ConceptContextBuilder;
use App\Services\StudyDeck\DeckValidator;
use App\Services\StudyDeck\ImagePlanner;
use App\Services\StudyDeck\ImageRelevanceJudge;
use App\Services\StudyDeck\LearningPlanBuilder;
use App\Services\StudyDeck\QuestionSelector;
use App\Services\StudyDeck\SlideContentGenerator;
use App\Services\StudyDeck\SlideHtmlRenderer;
use App\Services\StudyDeck\SlidePlanner;
use App\Services\StudyDeck\StudyDeckService;
use Tests\TestCase;

/**
 * The study-deck pipeline end to end, with a scripted model and a synthetic chapter.
 * No database, no network, no model: every step that is code is exercised for real,
 * and only the two Claude replies are canned.
 */
class StudyDeckPipelineTest extends TestCase
{
    use StudyDeckFixture;

    private function service(array $replies, ?array $image = null, ?callable $judge = null): StudyDeckService
    {
        $c = $this->completer($replies);
        $images = new ImagePlanner($this->imageSearch($image ?? $this->goodImage()), $this->memoryStore(), fn () => $this->png(), 2.0, $judge);

        return new StudyDeckService(
            new ConceptContextBuilder(), new QuestionSelector(), new LearningPlanBuilder(),
            new SlidePlanner($c), new SlideContentGenerator($c, 8), $images,
            new SlideHtmlRenderer(), new DeckValidator(images: $images),
        );
    }

    private function deck(?array $planOver = null, array $textOver = [], ?array $image = null, ?callable $judge = null, ?array $raw = null): array
    {
        $service = $this->service([json_encode($planOver ?? $this->plan()), json_encode($this->slideText($textOver)), '{}', '{}'], $image, $judge);

        return $service->fromRaw($raw ?? $this->raw(), ['min_slides' => 6, 'max_slides' => 8]);
    }

    public function test_a_well_formed_deck_passes_and_maps_concepts_to_slides_and_questions(): void
    {
        $r = $this->deck();

        $this->assertSame([], $r['report']['errors']);
        $this->assertTrue($r['report']['ok']);
        $this->assertSame(7, $r['deck']['slide_count']);
        $this->assertSame([1 => [2, 3], 2 => [3], 3 => [4, 5], 4 => [5]], $r['deck']['concept_slides']);
        $this->assertSame([1 => [2], 2 => [3], 3 => [4], 4 => [5]], $r['deck']['taught_by']);
        $this->assertSame([1 => [101], 2 => [103], 3 => [104]], $r['deck']['concept_questions']);
        $this->assertSame(2, $r['deck']['version']);
    }

    public function test_output_uses_the_existing_design_system_markup(): void
    {
        $html = $this->deck()['html'];

        $this->assertStringContainsString('<section class="cover"', $html);
        $this->assertSame(6, substr_count($html, '<section class="slide">'));
        $this->assertStringContainsString('callout callout-try" data-block="check"', $html);
        $this->assertStringNotContainsString('style=', $html);
        $this->assertStringContainsString('<figure class="fig-md"', $html);
        $this->assertStringContainsString('Image credits', $html);
        $this->assertStringContainsString('A. Person, BY-SA 4.0', $html);
        $this->assertStringNotContainsString('Also covered', $html);
    }

    public function test_every_concept_gets_its_own_explanation_element_and_no_name_only_stub(): void
    {
        $html = $this->deck()['html'];

        foreach (['Models', 'Ignoring details', 'Laws', 'Theories'] as $name) {
            $this->assertMatchesRegularExpression('/<p data-block="explain" data-concept="' . preg_quote($name, '/') . '"/', $html, "no explanation for $name");
        }
    }

    public function test_a_bank_check_shows_the_answer_then_the_stored_explanation_with_a_varied_label(): void
    {
        $html = $this->deck()['html'];

        $this->assertStringContainsString('<strong>Answer:</strong> B. Second choice', $html);
        $this->assertStringContainsString('<strong>Why:</strong> The second choice is right because a model keeps only what the question needs.', $html);
        preg_match_all('#<span class="callout-label">([^<]+)</span>#', $html, $m);
        $labels = array_unique(array_diff($m[1], ['Worked example', 'Common misconception', 'How these connect', 'Image credits']));
        $this->assertGreaterThan(1, count($labels), 'activity labels should vary');
        foreach ($labels as $label) {
            $this->assertContains($label, ['Try it', 'Apply', 'Explain', 'Check', 'Think about it']);
        }
    }

    public function test_the_banks_own_bloom_value_is_kept_on_the_check(): void
    {
        $html = $this->deck()['html'];

        // q101 is Apply, q103 Understand and q104 Remember in the bank - not the slide's level.
        $this->assertMatchesRegularExpression('/data-block="check" data-concept="Models" data-bloom="apply"/', $html);
        $this->assertMatchesRegularExpression('/data-block="check" data-concept="Ignoring details" data-bloom="understand"/', $html);
        $this->assertMatchesRegularExpression('/data-block="check" data-concept="Laws" data-bloom="remember"/', $html);
    }

    public function test_questions_that_need_missing_context_are_excluded_not_rewritten(): void
    {
        $r = $this->deck();
        $excluded = array_column($r['selection']['excluded'], 'reason', 'id');

        $this->assertArrayHasKey(102, $excluded);
        $this->assertArrayHasKey(105, $excluded);
        $this->assertStringContainsString('missing context', $excluded[102]);
        $this->assertStringNotContainsString('According to the passage', $r['html']);
        $this->assertStringNotContainsString('above paragraph', $r['html']);
    }

    public function test_bank_question_text_is_inserted_verbatim(): void
    {
        $html = $this->deck()['html'];

        $this->assertStringContainsString('Which describes a scientific model?', $html);
        $this->assertStringContainsString('A. First choice', $html);
    }

    public function test_the_learning_map_orders_prerequisites_first(): void
    {
        $context = (new ConceptContextBuilder())->assemble($this->raw());
        $context['topics'][10]['concept_ids'] = [2, 1];
        $map = (new LearningPlanBuilder())->build($context, []);

        $this->assertSame([1, 2, 3, 4], $map['sequence']);
        $this->assertSame([1], $map['concepts'][2]['requires']);
        $this->assertContains('branching', $map['concepts'][2]['pattern_candidates']);
        $this->assertSame($context['concepts'][1]['relationships'][0]['target_id'], 2);
    }

    public function test_a_plan_that_never_teaches_a_concept_is_sent_back_twice_then_rejected(): void
    {
        $bad = $this->plan();
        $bad['slides'][4]['taught_concept_ids'] = [];

        $c = $this->completer([json_encode($bad), json_encode($bad), json_encode($bad)]);
        $context = (new ConceptContextBuilder())->assemble($this->raw());
        $sel = (new QuestionSelector())->select($this->raw()['questions']);
        $map = (new LearningPlanBuilder())->build($context, $sel['eligible'], 6, 8);

        try {
            (new SlidePlanner($c))->plan($context, $map, $sel['eligible']);
            $this->fail('An invalid plan was accepted.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Concepts that no slide teaches', $e->getMessage());
            $this->assertStringContainsString('Theories', $e->getMessage());
        }

        $this->assertCount(3, $c->prompts);
        $this->assertStringContainsString('was rejected', $c->prompts[1]);
    }

    public function test_numbers_inside_a_bank_question_table_are_not_mistaken_for_invented_ones(): void
    {
        // "<td>40</td><td>10</td>" stripped of tags is "4010"; a check that reads that hides both numbers.
        $raw = $this->raw();
        $raw['questions'][0]['question_title'] = 'Using the data below, which describes a scientific model? <html><body><table><tr><td>40</td><td>10</td></tr><tr><td>85</td><td>22</td></tr></table></body></html>';

        $r = $this->deck(null, [], null, null, $raw);

        $this->assertStringContainsString('40 | 10', $r['html']);
        $this->assertSame([], $r['report']['errors']);
    }

    public function test_branching_is_dropped_when_there_is_no_decision_to_branch_on(): void
    {
        $plan = $this->plan();
        $plan['slides'][2]['slide_type'] = 'scenario';
        $plan['slides'][2]['h5p_pattern'] = ['type' => 'branching', 'reason' => 'a choice with consequences'];

        $r = $this->deck($plan);
        $slide = $r['deck']['slides'][2];

        $this->assertNull($slide['h5p_pattern'], 'the deck must not claim a pattern it does not use');
        $this->assertSame('branching', $slide['pattern_dropped']['type']);
        $this->assertStringContainsString('recall-level concept has no decision to make', $slide['pattern_dropped']['reason']);
        $this->assertFalse($slide['activities'][0]['decision']);
        $this->assertSame([], $r['report']['errors']);
    }

    public function test_the_cover_slide_carries_no_question_even_if_the_model_wrote_one(): void
    {
        $r = $this->deck(null, [1 => ['check' => ['question' => 'Which symbol stands for careful observation?', 'answer' => 'A magnifying glass.']]]);

        $this->assertSame([], $r['deck']['slides'][0]['activities']);
        $this->assertStringNotContainsString('careful observation', $r['html']);
        $this->assertSame([], $r['report']['errors']);
    }

    public function test_a_pattern_no_activity_realises_is_dropped_but_the_slide_progression_is_kept(): void
    {
        $plan = $this->plan();
        // The bank has no match-the-following question here, so "matching" cannot be played.
        $plan['slides'][1]['h5p_pattern'] = ['type' => 'matching', 'reason' => 'compare two ideas'];
        $plan['slides'][3]['h5p_pattern'] = ['type' => 'course_presentation', 'reason' => 'step through the idea'];

        $slides = $this->deck($plan)['deck']['slides'];

        $this->assertNull($slides[1]['h5p_pattern']);
        $this->assertSame('matching', $slides[1]['pattern_dropped']['type']);
        $this->assertStringContainsString('the bank has no question of the form it needs', $slides[1]['pattern_dropped']['reason']);
        $this->assertSame('course_presentation', $slides[3]['h5p_pattern']['type']);
        $this->assertNull($slides[3]['pattern_dropped']);
        $this->assertSame('flashcards', $slides[4]['h5p_pattern']['type'], 'a pattern an activity really takes is kept');
    }

    public function test_alt_text_never_carries_garbled_tags(): void
    {
        $planner = new ImagePlanner($this->imageSearch($this->goodImage()), $this->memoryStore(), fn () => $this->png());
        $alt = $planner->altFor(['title' => 'File:Two_pan_balance.jpg', 'tags' => ['balance', 'dvouramennu00e1', '2012', 'x', 'weighing']]);

        $this->assertSame('Photograph: Two pan balance. Tags: balance, weighing.', $alt);
    }

    public function test_a_concept_slide_must_name_its_concepts(): void
    {
        $context = (new ConceptContextBuilder())->assemble($this->raw());
        $sel = (new QuestionSelector())->select($this->raw()['questions']);
        $map = (new LearningPlanBuilder())->build($context, $sel['eligible'], 6, 8);
        $planner = new SlidePlanner($this->completer([]));

        $p = $this->plan();
        $p['slides'][3]['slide_type'] = 'misconception';
        $p['slides'][3]['concept_ids'] = [];
        $p['slides'][3]['taught_concept_ids'] = [];
        $joined = implode("\n", $planner->validate($p, $map, $sel['eligible']));

        $this->assertStringContainsString('is a misconception slide and must list the concept_ids it is about', $joined);
        // Framing and review slides are about the whole chapter, not one concept.
        $this->assertStringNotContainsString('Slide 6 is a concept_map', $joined);
    }

    public function test_a_reply_that_is_not_json_is_a_repair_round_not_a_crash(): void
    {
        $good = json_encode($this->plan());
        // A closing brace dropped inside a nested object, as a model really did: the rest of the plan is lost.
        $broken = preg_replace('/"question_ids":\[101\]/', '"question_ids":[101', $good, 1);
        $this->assertNotSame($good, $broken);
        $this->assertNull(json_decode($broken, true));

        $c = $this->completer([$broken, $good]);
        $context = (new ConceptContextBuilder())->assemble($this->raw());
        $sel = (new QuestionSelector())->select($this->raw()['questions']);
        $map = (new LearningPlanBuilder())->build($context, $sel['eligible'], 6, 8);

        $plan = (new SlidePlanner($c))->plan($context, $map, $sel['eligible']);

        $this->assertCount(7, $plan['slides']);
        $this->assertCount(2, $c->prompts);
        $this->assertStringContainsString('was not valid JSON', $c->prompts[1]);
        $this->assertStringContainsString('strictly valid JSON', $c->prompts[1]);
    }

    public function test_plan_rules(): void
    {
        $context = (new ConceptContextBuilder())->assemble($this->raw());
        $sel = (new QuestionSelector())->select($this->raw()['questions']);
        $map = (new LearningPlanBuilder())->build($context, $sel['eligible'], 6, 8);
        $planner = new SlidePlanner($this->completer([]));
        $errors = fn (array $plan) => implode("\n", $planner->validate($plan, $map, $sel['eligible']));

        $p = $this->plan();
        $p['slides'][2]['question_ids'] = [101];
        $this->assertStringContainsString('Question 101 is used on slides', $errors($p));

        $p = $this->plan();
        $p['slides'][1]['question_ids'] = [102];
        $this->assertStringContainsString('not in the eligible list', $errors($p));

        $p = $this->plan();
        $p['slides'][2]['h5p_pattern'] = ['type' => 'hologram', 'reason' => 'x'];
        $this->assertStringContainsString('h5p_pattern needs a valid type', $errors($p));

        $p = $this->plan();
        $p['slides'] = array_slice($p['slides'], 0, 5);
        $this->assertStringContainsString('must have between 6 and 8', $errors($p));

        $p = $this->plan();
        $p['slides'][0]['slide_type'] = 'hook';
        $this->assertStringContainsString('Slide 1 must have slide_type "cover"', $errors($p));

        $p = $this->plan();
        $p['slides'][3]['title'] = $p['slides'][1]['title'];
        $this->assertStringContainsString('repeats the title', $errors($p));

        // Branching is for a real decision, never a recall slide.
        $p = $this->plan();
        $p['slides'][3]['h5p_pattern'] = ['type' => 'branching', 'reason' => 'x'];
        $this->assertStringContainsString('a branching pattern needs a scenario, application or worked_example slide', $errors($p));

        // Framing slides teach nothing and carry no bank question.
        $p = $this->plan();
        $p['slides'][0]['taught_concept_ids'] = [1];
        $this->assertStringContainsString('must not claim to teach a concept', $errors($p));

        $p = $this->plan();
        $p['slides'][5]['slide_type'] = 'objectives';
        $p['slides'][5]['question_ids'] = [103];
        $p['slides'][2]['question_ids'] = [];
        $this->assertStringContainsString('no bank question may sit on it', $errors($p));

        // Two bank questions per slide at most.
        $p = $this->plan();
        $p['slides'][1]['question_ids'] = [101, 103, 104];
        $p['slides'][2]['question_ids'] = [];
        $p['slides'][4]['question_ids'] = [];
        $this->assertStringContainsString('the limit is 2', $errors($p));

        // Every concept needs its own explanation: not three crammed onto one slide.
        $p = $this->plan();
        $p['slides'][4]['concept_ids'] = [3, 4, 1];
        $p['slides'][4]['taught_concept_ids'] = [3, 4, 1];
        $this->assertStringContainsString('at most 2 so each gets a real explanation', $errors($p));

        // A visual needs a role; a diagram needs a drawable spec.
        $p = $this->plan();
        unset($p['slides'][1]['visual']['role']);
        $this->assertStringContainsString('a visual needs a role', $errors($p));
        $p = $this->plan();
        $p['slides'][1]['visual']['role'] = 'diagram';
        $p['slides'][1]['diagram'] = ['layout' => 'flow', 'title' => 'x', 'nodes' => ['only one']];
        $this->assertStringContainsString('flow needs 2-6 nodes', $errors($p));

        $p = $this->plan();
        $p['slides'][3]['diagram'] = ['layout' => 'flow', 'title' => 'x', 'nodes' => ['a', 'b']];
        $this->assertStringContainsString('a diagram needs a visual alongside it', $errors($p));
    }

    public function test_content_generator_flags_thin_missing_and_repeating_slides(): void
    {
        $gen = new SlideContentGenerator($this->completer([]));
        $planSlides = [];
        foreach ($this->plan()['slides'] as $i => $s) {
            $planSlides[$i + 1] = $s;
        }
        $text = $this->slideText();
        $content = [];
        foreach ($text['slides'] as $s) {
            $content[$s['n']] = $s;
        }

        $this->assertSame([], $gen->problems($planSlides, $content));

        $content[2]['explanations'] = [['concept_id' => 1, 'text' => 'A model.']];
        $this->assertStringContainsString('too thin', implode(' ', $gen->problems($planSlides, $content)[2]));

        $content = [];
        foreach ($text['slides'] as $s) {
            $content[$s['n']] = $s;
        }
        $content[3]['explanations'] = [];
        $this->assertStringContainsString('must explain concept 2', implode(' ', $gen->problems($planSlides, $content)[3]));

        $content[3]['explanations'] = [['concept_id' => 2, 'text' => 'A model ignores some details on purpose.']];
        $content[3]['example'] = 'A model ignores some details on purpose, always.';
        $this->assertStringContainsString('only repeats the explanation', implode(' ', $gen->problems($planSlides, $content)[3]));

        $content[3]['example'] = null;
        $content[4]['check'] = null;
        $this->assertStringContainsString('no check', implode(' ', $gen->problems($planSlides, $content)[4]));

        $content[4]['check'] = ['question' => 'q', 'answer' => 'a'];
        $content[4]['bullets'] = [str_repeat('word ', 60)];
        $this->assertStringContainsString('words of body text', implode(' ', $gen->problems($planSlides, $content)[4]));
    }

    public function test_validator_rejects_unsupported_numbers_missing_checks_and_long_slides(): void
    {
        $r = $this->deck(null, [
            2 => ['explanations' => [['concept_id' => 1, 'text' => 'A scientific model is a simplified representation of a real system used by 73 scientists.']]],
            4 => ['check' => null],
            5 => ['bullets' => [str_repeat('word ', 60)]],
        ]);
        $joined = implode("\n", $r['report']['errors']);

        $this->assertFalse($r['report']['ok']);
        $this->assertStringContainsString('Numbers in slide text that are not in the chapter text: 73', $joined);
        $this->assertStringContainsString('has no check block', $joined);
        $this->assertStringContainsString('words of body text', $joined);
    }

    public function test_validator_requires_concepts_to_be_taught_not_named(): void
    {
        $r = $this->deck(null, [4 => ['explanations' => []]]);
        $this->assertStringContainsString('Concepts with no explanation of their own (named or summarised only): Laws', implode("\n", $r['report']['errors']));

        $r = $this->deck(null, [4 => ['explanations' => [['concept_id' => 3, 'text' => 'Laws.']]]]);
        $this->assertStringContainsString('too thin to teach', implode("\n", $r['report']['errors']));
    }

    public function test_validator_rejects_a_stub_a_repeated_example_and_a_missing_explanation_in_the_bank(): void
    {
        $r = $this->deck(null, [3 => ['example' => 'A model ignores some details on purpose and keeps others.']]);
        $this->assertStringContainsString('the worked example only repeats the explanation', implode("\n", $r['report']['errors']));

        $r = $this->deck();
        $r['html'] = str_replace('</section>', '<section class="callout callout-key" data-block="summary" data-concept="Laws"><span class="callout-label">Also covered</span><p>Laws</p></section></section>', $r['html']);
        $validator = new DeckValidator();
        $report = $validator->validate($r['context'], $r['map'], $r['selection']['eligible'], ['html' => $r['html'], 'deck' => $r['deck']]);
        $this->assertStringContainsString('name-only "Also covered" stub', implode("\n", $report['errors']));

        $eligible = $r['selection']['eligible'];
        $eligible[1][0]['explanation'] = '';
        $eligible[1][0]['answer_text'] = '';
        $report = $validator->validate($r['context'], $r['map'], $eligible, ['html' => $r['html'], 'deck' => $r['deck']]);
        $this->assertStringContainsString('no stored explanation or model answer', implode("\n", $report['errors']));
    }

    public function test_validator_enforces_question_counts_framing_slides_and_activity_specs(): void
    {
        $r = $this->deck();
        $validator = new DeckValidator();
        $run = fn (array $deck) => implode("\n", $validator->validate($r['context'], $r['map'], $r['selection']['eligible'], ['html' => $r['html'], 'deck' => $deck])['errors']);

        $deck = $r['deck'];
        $deck['slides'][1]['question_ids'] = [101, 103, 104];
        $this->assertStringContainsString('the limit is 2', $run($deck));

        $deck = $r['deck'];
        $deck['slides'][0]['question_ids'] = [101];
        $this->assertStringContainsString('must carry no bank question', $run($deck));

        $deck = $r['deck'];
        $deck['slides'][1]['activities'][0]['as'] = 'hologram';
        $this->assertStringContainsString('which the player cannot render', $run($deck));

        $deck = $r['deck'];
        $deck['slides'][1]['activities'][0]['label'] = 'Quiz time';
        $this->assertStringContainsString('is not one of Try it, Apply, Explain, Check, Think about it', $run($deck));

        $deck = $r['deck'];
        $deck['slides'][1]['h5p_pattern'] = ['type' => 'branching', 'reason' => 'x'];
        $this->assertStringContainsString('branching must be justified by a real decision', $run($deck));
    }

    public function test_deck_with_the_wrong_slide_count_fails(): void
    {
        $r = $this->deck();
        $map = $r['map'];
        $map['target_slides'] = ['min' => 30, 'max' => 35];
        $report = (new DeckValidator())->validate($r['context'], $map, $r['selection']['eligible'], ['html' => $r['html'], 'deck' => $r['deck']]);

        $this->assertStringContainsString('the target is 30-35', implode("\n", $report['errors']));
    }

    public function test_alt_text_comes_from_the_chosen_image_never_from_the_plan(): void
    {
        $r = $this->deck();
        $img = $r['deck']['slides'][1]['image'];

        $this->assertSame('Photograph: Map as a simplified scientific model. Tags: map, model.', $img['alt']);
        $this->assertNotSame($r['plan']['slides'][1]['visual']['purpose'], $img['alt']);
        $this->assertStringContainsString('alt="Photograph: Map as a simplified scientific model. Tags: map, model."', $r['html']);
        $this->assertSame('Map as a simplified scientific model', $img['caption']);
    }

    public function test_image_rules(): void
    {
        $find = fn (array $image, string $q = 'scientific model simplified representation') => (new ImagePlanner($this->imageSearch($image), $this->memoryStore(), fn () => $this->png()))->find($q);

        $ok = $find($this->goodImage());
        $this->assertArrayNotHasKey('missing', $ok);
        $this->assertSame('BY-SA 4.0', $ok['licence']);
        $this->assertTrue($ok['attribution_required']);
        $this->assertNotEmpty($ok['sha1']);
        $this->assertGreaterThanOrEqual(1, $ok['match']['shared']);

        $this->assertStringContainsString('not accepted', $find($this->goodImage(['license' => 'BY-NC 4.0']))['missing']);
        $this->assertStringContainsString('not accepted', $find($this->goodImage(['license' => 'BY-ND 2.0']))['missing']);
        $this->assertStringContainsString('not accepted', $find($this->goodImage(['license' => null]))['missing']);
        $this->assertStringContainsString('requires attribution', $find($this->goodImage(['attribution' => null, 'creator' => null]))['missing']);
        $this->assertStringContainsString('do not match enough of the query', $find($this->goodImage(['title' => 'Cat on a sofa', 'tags' => ['cat']]))['missing']);
        $this->assertArrayNotHasKey('missing', $find($this->goodImage(['license' => 'CC0 1.0', 'attribution' => null, 'creator' => null])));

        // A title like "IMG_0770" cannot support any true statement about the picture.
        $this->assertStringContainsString('does not say what the picture shows', $find($this->goodImage(['title' => 'IMG_0770']))['missing']);

        $none = (new ImagePlanner($this->imageSearch($this->goodImage()), $this->memoryStore(), fn () => null))->find('scientific model');
        $this->assertSame('image could not be downloaded', $none['missing']);
    }

    public function test_the_relevance_judge_can_reject_every_candidate_before_any_download(): void
    {
        $downloads = 0;
        $rejectAll = fn () => ['accepted' => [], 'reasons' => [], 'reason' => 'shows a different subject'];
        $planner = new ImagePlanner($this->imageSearch($this->goodImage()), $this->memoryStore(), function () use (&$downloads) {
            $downloads++;

            return $this->png();
        }, 2.0, $rejectAll);

        $r = $planner->find('scientific model simplified representation', [], 'a map as a model');

        $this->assertStringContainsString('rejected on relevance review: shows a different subject', $r['missing']);
        $this->assertSame(0, $downloads);
    }

    public function test_the_judge_is_told_what_the_slide_teaches_so_a_prop_photo_can_be_refused(): void
    {
        $seen = null;
        $judge = function ($q, $p, $candidates, $teaches) use (&$seen) {
            $seen = $teaches;

            return ['accepted' => [], 'reasons' => [], 'reason' => 'a mask is only an example'];
        };
        (new ImagePlanner($this->imageSearch($this->goodImage()), $this->memoryStore(), fn () => $this->png(), 2.0, $judge))
            ->find('scientific model simplified representation', [], 'a prop', 'Branches of science are human divisions.');

        $this->assertSame('Branches of science are human divisions.', $seen);

        $client = $this->completer(['{"accepted": [], "reason": "prop"}']);
        (new ImageRelevanceJudge($client))('q', 'p', [$this->goodImage()], 'Branches are divisions');
        $this->assertStringContainsString('"slide_teaches":"Branches are divisions"', $client->prompts[0]);
        $this->assertStringContainsString('merely an example, a prop', $client->prompts[0]);
    }

    public function test_the_planner_takes_the_candidate_the_judge_prefers_not_the_top_ranked(): void
    {
        $top = $this->goodImage(['title' => 'Model of a scientific model', 'url' => 'https://upload.example.org/top.png']);
        $second = $this->goodImage(['title' => 'Simplified scientific model map', 'url' => 'https://upload.example.org/second.png']);
        $seen = null;
        $judge = function ($q, $p, $candidates) use (&$seen) {
            $seen = array_column($candidates, 'title');

            return ['accepted' => [1], 'reasons' => [1 => 'a map is the clearer simplified model'], 'reason' => ''];
        };

        $r = (new ImagePlanner($this->imageSearch($top, [$second]), $this->memoryStore(), fn () => $this->png(), 2.0, $judge))
            ->find('scientific model simplified representation', [], 'a map as a model');

        $this->assertSame(['Model of a scientific model', 'Simplified scientific model map'], $seen);
        $this->assertSame('https://upload.example.org/second.png', $r['source_image_url']);
        $this->assertSame('a map is the clearer simplified model', $r['review']['reason']);
    }

    public function test_a_failed_download_falls_through_to_the_next_accepted_candidate(): void
    {
        $a = $this->goodImage(['url' => 'https://upload.example.org/a.png', 'thumbnail_url' => null]);
        $b = $this->goodImage(['url' => 'https://upload.example.org/b.png', 'thumbnail_url' => null]);
        $judge = fn () => ['accepted' => [0, 1], 'reasons' => [], 'reason' => ''];
        $download = fn (string $url) => str_ends_with($url, 'a.png') ? null : $this->png();

        $r = (new ImagePlanner($this->imageSearch($a, [$b]), $this->memoryStore(), $download, 2.0, $judge))
            ->find('scientific model simplified representation', [], 'x');

        $this->assertSame('https://upload.example.org/b.png', $r['source_image_url']);
    }

    public function test_the_judge_reads_the_model_verdict_and_fails_closed(): void
    {
        $cands = [$this->goodImage(), $this->goodImage(['title' => 'Cat'])];
        $yes = new ImageRelevanceJudge($this->completer(['{"accepted": [1, 0, 7], "reasons": {"1": "best"}, "reason": ""}']));
        $none = new ImageRelevanceJudge($this->completer(['{"accepted": [], "reason": "all portraits"}']));
        $junk = new ImageRelevanceJudge($this->completer(['not json at all']));

        $ok = $yes('q', 'p', $cands);
        $this->assertSame([1, 0], $ok['accepted']);          // out-of-range index 7 is dropped
        $this->assertSame('best', $ok['reasons'][1]);
        $verdict = $none('q', 'p', $cands);
        $this->assertSame([], $verdict['accepted']);
        $this->assertSame('all portraits', $verdict['reason']);
        $this->assertSame([], $junk('q', 'p', $cands)['accepted']);
    }

    public function test_a_planned_diagram_is_drawn_and_described_from_its_spec(): void
    {
        $plan = $this->plan();
        $plan['slides'][1]['visual'] = ['required' => true, 'role' => 'diagram', 'query' => 'unused', 'purpose' => 'Shows how a model is built'];
        $plan['slides'][1]['diagram'] = ['layout' => 'flow', 'title' => 'From a real system to a model', 'nodes' => ['A real system', 'Ignore some details', 'A simplified model']];
        $r = $this->deck($plan);
        $img = $r['deck']['slides'][1]['image'];

        $this->assertSame([], $r['report']['errors']);
        $this->assertSame('diagram', $img['type']);
        $this->assertSame('Diagram: From a real system to a model. Steps in order: A real system, then Ignore some details, then A simplified model.', $img['alt']);
        $this->assertNull($img['licence']);
        $this->assertStringNotContainsString('Image credits', $r['html']);
    }

    public function test_when_no_photo_fits_a_diagram_spec_is_the_fallback(): void
    {
        $plan = $this->plan();
        $plan['slides'][1]['visual'] = ['required' => true, 'role' => 'object', 'query' => 'scientific model simplified representation', 'purpose' => 'x'];
        $plan['slides'][1]['diagram'] = ['layout' => 'hub', 'title' => 'Ideas around a model', 'center' => 'A model', 'nodes' => ['Ignore details', 'Keep the question']];
        $reject = fn () => ['accepted' => [], 'reasons' => [], 'reason' => 'a prop'];
        $r = $this->deck($plan, [], null, $reject);
        $img = $r['deck']['slides'][1]['image'];

        $this->assertSame('diagram', $img['type']);
        $this->assertStringContainsString('rejected on relevance review', $img['photo_missing']);
    }

    public function test_the_deck_carries_the_structure_the_player_needs(): void
    {
        $d = $this->deck()['deck'];

        $this->assertSame('Ideas in exploration', $d['chapter']['name']);
        $this->assertSame([['topic_id' => 10, 'name' => 'Models', 'concept_ids' => [1, 2]], ['topic_id' => 11, 'name' => 'Laws and theories', 'concept_ids' => [3, 4]]], $d['outline']);
        $this->assertSame('Models', $d['concepts'][1]['name']);
        $this->assertSame([2 => 1], array_combine([2], [$d['concepts'][2]['requires'][0]]));

        $s = $d['slides'][1];
        $this->assertSame('A scientific model is a simplified representation of a real system.', $s['content']['explanations'][0]['text']);
        $this->assertSame(101, $s['activities'][0]['question_id']);
        $this->assertSame('single_choice_set', $s['activities'][0]['as']);
        $this->assertSame('apply', $s['activities'][0]['bloom']);

        $authored = $d['slides'][3]['activities'][0];
        $this->assertSame('authored', $authored['source']);
        $this->assertSame('short', $authored['question']['question_type_code']);
        $this->assertLessThan(0, $authored['question']['id']);
    }
}
