<?php

namespace Tests\Unit\StudyDeck;

use App\Services\StudyDeck\ConceptContextBuilder;
use App\Services\StudyDeck\DeckValidator;
use App\Services\StudyDeck\GroundedNumbers;
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
 * Run re27dc680740a passed every stage and then failed the finished deck's validator, where nothing could fix it: three slides over the word
 * limit (the raw Commons title of a photo counted as body text) and two numbers the chapter does not give (a conversion factor in a "steps" item,
 * a rounded 8640 on a diagram label). Each stage now checks its own numbers and uses its own repair round, and a caption is shortened.
 * A scripted model, no database, no network.
 */
class GroundedNumbersTest extends TestCase
{
    use StudyDeckFixture;

    private const BAD = '9,999';

    // ---- the rule itself ------------------------------------------------------------------------------------------------------------

    public function test_a_number_is_grounded_when_the_chapter_a_question_or_a_concept_has_those_digits(): void
    {
        $context = (new ConceptContextBuilder())->assemble($this->raw());
        $allowed = GroundedNumbers::allowedText($context);

        $this->assertSame([self::BAD, '12.5'], GroundedNumbers::ungrounded('It is ' . self::BAD . ' and 12.5 here.', $allowed));
        $this->assertSame([], GroundedNumbers::ungrounded('Slide 4: step 3 of the plan.', $allowed), 'slide and step numbers are navigation');
        $this->assertSame([], GroundedNumbers::ungrounded('anything 123', ''), 'with no allowed text nothing can be judged');
        $withQuestion = GroundedNumbers::allowedText($context, [1 => [['stem' => 'About ' . self::BAD . ' litres?', 'options' => [['text' => 'a'], ['text' => 'b']], 'explanation' => '', 'answer_text' => '']]]);
        $this->assertSame([], GroundedNumbers::ungrounded('about ' . self::BAD, $withQuestion), 'the validator accepts a number found in an attached bank question, so this must too');
    }

    public function test_wording_takes_the_labels_and_sentences_and_leaves_ids_and_flags_out(): void
    {
        $text = GroundedNumbers::wording(['kind' => 'steps', 'reason' => 'why 77', 'items' => [['id' => 'i1', 'label' => 'Measure', 'text' => 'Use 5 cm.'], ['id' => 'i2', 'sound' => true, 'text' => 'Then 6.']]]);

        $this->assertSame('Measure Use 5 cm. Then 6.', $text);
    }

    // ---- each stage repairs its own numbers ------------------------------------------------------------------------------------

    public function test_a_diagram_label_with_a_number_the_chapter_does_not_give_goes_back_to_the_planner_for_a_repair_round(): void
    {
        $context = (new ConceptContextBuilder())->assemble($this->raw());
        $sel = (new QuestionSelector())->select($this->raw()['questions']);
        $map = (new LearningPlanBuilder())->build($context, $sel['eligible'], 6, 8, ['repair_rounds' => 2]);
        $bad = $this->planWithRoom();
        $bad['slides'][2]['diagram']['nodes'] = ['Ignore ' . self::BAD . ' details', 'Keep the question'];

        $model = $this->completer([json_encode($bad), json_encode($this->planWithRoom())]);
        $result = (new SlidePlanner($model))->plan($context, $map, $sel['eligible']);

        $this->assertCount(2, $model->prompts, 'one repair round');
        $this->assertStringContainsString('the diagram uses the number(s) ' . self::BAD . ', which are not in the chapter text', $model->prompts[1]);
        $this->assertStringNotContainsString(self::BAD, json_encode($result['slides']), 'the repaired plan has no such number');
    }

    public function test_slide_text_with_a_number_the_chapter_does_not_give_is_sent_back_by_the_content_stage(): void
    {
        $context = (new ConceptContextBuilder())->assemble($this->raw());
        $sel = (new QuestionSelector())->select($this->raw()['questions']);
        $map = (new LearningPlanBuilder())->build($context, $sel['eligible'], 6, 8, ['repair_rounds' => 0]);
        $plan = (new SlidePlanner($this->completer([json_encode($this->planWithRoom())])))->plan($context, $map, $sel['eligible']);
        $text = $this->slideText();
        $text['slides'][1]['explanations'][0]['text'] .= ' It was measured at ' . self::BAD . ' units.';

        $model = $this->completer([json_encode($text), '{}']);
        (new SlideContentGenerator($model, 8))->groundedIn($sel['eligible'])->generate($context, $map, $plan);

        $this->assertCount(2, $model->prompts, 'the slide went back once');
        $this->assertStringContainsString('Slide 2: It uses the number(s) ' . self::BAD . ', which are not in the chapter text', $model->prompts[1]);
        $this->assertStringNotContainsString('Slide 3:', $model->prompts[1], 'only the slide with the number went back');
    }

    private function deckWith(array $reveal): array
    {
        $c = $this->completer(array_merge([json_encode($this->planWithRoom()), json_encode($this->slideText())], $reveal));
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

    /** The fixture's interaction reply with one number the chapter does not give in the reveal on slide 4. */
    private function replyWithBadNumber(): array
    {
        $reply = $this->interactionReply();
        foreach ($reply['slides'] as $i => $s) {
            if ($s['n'] === 4) {
                $reply['slides'][$i]['interaction']['items'][0]['text'] = 'A law describes a repeated pattern, here ' . self::BAD . ' times over.';
            }
        }

        return $reply;
    }

    /** @return array<string,mixed> */
    private function slideNumber(array $result, int $n): array
    {
        foreach ($result['deck']['slides'] as $s) {
            if ($s['n'] === $n) {
                return $s;
            }
        }
        $this->fail("No slide $n in the deck.");
    }

    /**
     * The shared harness cannot source its photo (InteractionPlannerTest fails the same way, with "1 of 2 planned visuals could not be sourced"),
     * so the deck's error list is never empty here: what matters is that the final validator has no number complaint left to make.
     */
    private function numberComplaints(array $result): array
    {
        return array_values(array_filter($result['report']['errors'], fn ($e) => str_contains($e, 'Numbers in') || str_contains($e, self::BAD)));
    }

    public function test_an_interaction_with_such_a_number_is_corrected_in_its_own_repair_round_and_the_deck_still_validates(): void
    {
        $fixed = $this->interactionReply();
        $r = $this->deckWith([json_encode($this->replyWithBadNumber()), json_encode(['slides' => array_values(array_filter($fixed['slides'], fn ($s) => $s['n'] === 4))])]);

        $slide = $this->slideNumber($r, 4);
        $this->assertSame('reveal', $slide['interaction']['kind'], 'the corrected interaction is kept');
        $this->assertStringNotContainsString(self::BAD, json_encode($slide['interaction']));
        $this->assertSame([], $this->numberComplaints($r), 'nothing is left for the final validator to reject');
        $this->assertStringContainsString('Slide 4: It uses the number(s) ' . self::BAD, implode("\n", $r['prompts']));
    }

    public function test_an_interaction_that_still_has_the_number_is_dropped_with_a_reason_and_never_reaches_the_validator(): void
    {
        $r = $this->deckWith([json_encode($this->replyWithBadNumber()), '{}']);

        $slide = $this->slideNumber($r, 4);
        $this->assertNull($slide['interaction'], 'that slide teaches plainly');
        $this->assertStringContainsString('not in the chapter text', (string) $slide['interaction_reason']);
        $this->assertSame([], $this->numberComplaints($r), 'the deck is not failed for it');
        $this->assertSame('compare', $this->slideNumber($r, 2)['interaction']['kind'], 'the other interactions are untouched');
    }

    // ---- the word limit ------------------------------------------------------------------------------------------------------------

    public function test_a_caption_is_shortened_to_six_words_and_a_creator_to_four(): void
    {
        $this->assertSame('US Navy 060215-N-5330L-577 Students at Farrier…', SlideHtmlRenderer::firstWords('US Navy 060215-N-5330L-577 Students at Farrier Firefighting School, Naval Station Norfolk', 6));
        $this->assertSame('A short title', SlideHtmlRenderer::firstWords('  A   short title ', 6), 'a short one is left as it is');
        $this->assertSame('Nenad Stojkovic from Srbija…', SlideHtmlRenderer::firstWords('Nenad Stojkovic from Srbija and others', 4));
    }

    public function test_a_photo_with_a_forty_word_title_no_longer_pushes_its_slide_over_the_word_limit(): void
    {
        $long = trim(str_repeat('Students at Farrier Firefighting School follow one another into the training simulator. ', 4));
        $c = $this->completer([json_encode($this->planWithRoom()), json_encode($this->slideText())]);
        $images = new ImagePlanner($this->imageSearch($this->goodImage()), $this->memoryStore(), fn () => $this->png());
        $validator = new DeckValidator(images: $images);
        $service = new StudyDeckService(
            new ConceptContextBuilder(), new QuestionSelector(), new LearningPlanBuilder(),
            new SlidePlanner($c), new SlideContentGenerator($c, 8), $images,
            new SlideHtmlRenderer(), $validator,
        );
        $r = $service->fromRaw($this->raw(), ['min_slides' => 6, 'max_slides' => 8]);

        // Slide 3 gets a photo with the raw Commons-style title as its caption, and a body that fits the limit only if the caption is cut.
        $content = $r['content'];
        $content[3]['explanations'][0]['text'] = trim(str_repeat('A model ignores some details on purpose so that it stays useful. ', 3));
        $pictures = $r['images'];
        $pictures[3] = [
            'type' => 'photo', 'title' => $long, 'caption' => $long, 'creator' => 'Nenad Stojkovic from Srbija and others', 'licence' => 'CC BY-SA 4.0',
            'attribution' => '', 'source_url' => 'https://commons.example.org/File:Students.jpg',
        ] + $pictures[3];
        $rendered = (new SlideHtmlRenderer())->render($r['context'], $r['map'], $r['plan'], $content, $pictures, $r['selection']['eligible']);

        $this->assertGreaterThanOrEqual(40, str_word_count($long), 'the title alone would not fit beside the text');
        $this->assertStringContainsString('<figcaption>Students at Farrier Firefighting School follow…. Nenad Stojkovic from Srbija…, CC BY-SA 4.0</figcaption>', $rendered['html'], 'the caption shows only the start of the title');
        $this->assertStringContainsString('Slide 3: ' . $long, $rendered['html'], 'the full title stays in the image credits, which are not body text');
        $errors = $validator->validate($r['context'], $r['map'], $r['selection']['eligible'], ['html' => $rendered['html'], 'deck' => $rendered['deck']])['errors'];
        $this->assertSame([], array_values(array_filter($errors, fn ($e) => str_contains($e, 'words of body text'))), 'no slide is over the limit because of its caption');
    }
}
