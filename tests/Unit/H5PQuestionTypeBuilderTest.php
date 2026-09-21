<?php

namespace Tests\Unit;

use App\Models\lms\h5p\H5pSingleChoiceOption;
use App\Models\lms\h5p\H5pSingleChoiceQuestion;
use App\Models\lms\h5p\H5pSingleChoiceSet;
use App\Models\lms\h5p\H5pTrueFalse;
use App\Models\lms\h5p\H5pTrueFalseQuestion;
use App\Services\lms\H5P\H5PSingleChoiceSetBuilder;
use App\Services\lms\H5P\H5PTrueFalseBuilder;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

/**
 * Covers the two builders added with Single Choice Set and True/False.
 *
 * No database. Models are built in memory with their relations set, which is
 * all a builder reads -- so these run anywhere, including a CI box with no
 * MySQL, and they fail for the reason they name rather than on a fixture.
 *
 * What is tested is deliberately narrow: the places where a format mismatch
 * between this schema and H5P's could corrupt an activity SILENTLY. Those are
 * the bugs that reach a classroom, because the page still renders and the
 * activity still scores -- it just scores the wrong thing.
 *
 * For these two types that is, above everything else:
 *
 *   - Single Choice Set stores the right answer as a FLAG and H5P stores it as
 *     POSITION ZERO. Every test below that looks at `answers[0]` is guarding
 *     that conversion in one direction or the other.
 *   - True/False stores the answer as a BOOLEAN and H5P stores it as the
 *     STRING "true"/"false". A comparison that gets that wrong inverts a whole
 *     class's marks with no visible symptom.
 */
class H5PQuestionTypeBuilderTest extends TestCase
{
    // =======================================================================
    // Single choice set
    // =======================================================================

    /**
     * A three-question set whose correct option is at a DIFFERENT position in
     * each question: second, first, third.
     *
     * Deliberately not "always first" -- a fixture like that passes whether
     * the builder reads the flag or just takes the first option, which makes
     * it worse than no fixture at all.
     */
    private function choiceSet(): H5pSingleChoiceSet
    {
        $set = new H5pSingleChoiceSet([
            'title' => 'Science recap',
            'task_description' => 'Five quick questions.',
            'auto_continue' => true,
            'timeout_correct_ms' => 1500,
            'timeout_wrong_ms' => 2500,
            'sound_effects' => false,
            'enable_retry' => true,
            'enable_show_solution' => true,
            'randomize_questions' => false,
            'randomize_answers' => true,
            'points_per_question' => 2,
            'pass_percentage' => 70,
            'show_progress' => true,
        ]);
        $set->id = 11;

        $questions = new Collection();

        $questions->push($this->question(101, 0, 'Which state keeps its volume but not its shape?', [
            ['Solid', false, 'A solid keeps its shape too.'],
            ['Liquid', true, null],
            ['Gas', false, 'A gas fills its container.'],
        ], 'Yes — a liquid flows to fit.', 'Think about pouring water.', 'Solid keeps both; gas keeps neither.'));

        $questions->push($this->question(102, 1, 'Which planet is closest to the Sun?', [
            ['Mercury', true, null],
            ['Venus', false, 'Venus is hottest, not closest.'],
        ], null, null, null));

        $questions->push($this->question(103, 2, 'What do plants take in to make food?', [
            ['Oxygen', false, 'That is what they give out.'],
            ['Nitrogen', false, null],
            ['Carbon dioxide', true, null],
            ['Hydrogen', false, null],
        ], null, null, null));

        $set->setRelation('questions', $questions);

        return $set;
    }

    /**
     * @param  list<array{0:string,1:bool,2:?string}>  $options
     */
    private function question(
        int $id,
        int $order,
        string $text,
        array $options,
        ?string $correctFeedback,
        ?string $incorrectFeedback,
        ?string $explanation
    ): H5pSingleChoiceQuestion {
        $question = new H5pSingleChoiceQuestion([
            'question_text' => $text,
            'feedback_correct' => $correctFeedback,
            'feedback_incorrect' => $incorrectFeedback,
            'explanation' => $explanation,
        ]);
        $question->id = $id;
        $question->sort_order = $order;

        $rows = new Collection();
        foreach ($options as $index => [$optionText, $isCorrect, $feedback]) {
            $row = new H5pSingleChoiceOption([
                'option_text' => $optionText,
                'is_correct' => $isCorrect,
                'feedback' => $feedback,
            ]);
            $row->id = $id * 10 + $index;
            $row->sort_order = $index;
            $rows->push($row);
        }
        $question->setRelation('options', $rows);

        return $question;
    }

    public function test_the_correct_answer_is_written_first_whatever_its_authored_position(): void
    {
        $params = (new H5PSingleChoiceSetBuilder())->build($this->choiceSet());

        // This is the assertion the whole type rests on. H5P.SingleChoiceSet
        // has no `correct` key anywhere -- answers[0] IS the answer.
        $this->assertSame('<p>Liquid</p>', $params['choices'][0]['answers'][0]);
        $this->assertSame('<p>Mercury</p>', $params['choices'][1]['answers'][0]);
        $this->assertSame('<p>Carbon dioxide</p>', $params['choices'][2]['answers'][0]);
    }

    public function test_the_distractors_keep_their_authored_order_behind_the_answer(): void
    {
        $params = (new H5PSingleChoiceSetBuilder())->build($this->choiceSet());

        // Question 3: correct was authored third, so the other three follow in
        // author order behind it. An unstable partition would scramble these.
        $this->assertSame(
            ['<p>Carbon dioxide</p>', '<p>Oxygen</p>', '<p>Nitrogen</p>', '<p>Hydrogen</p>'],
            $params['choices'][2]['answers']
        );
    }

    public function test_bare_text_is_wrapped_and_existing_markup_is_left_alone(): void
    {
        $set = $this->choiceSet();
        $set->questions[0]->question_text = '<p>Already <em>marked up</em>.</p>';

        $params = (new H5PSingleChoiceSetBuilder())->build($set);

        $this->assertSame('<p>Already <em>marked up</em>.</p>', $params['choices'][0]['question']);
        // Question 2 was authored as a bare sentence.
        $this->assertSame('<p>Which planet is closest to the Sun?</p>', $params['choices'][1]['question']);
    }

    public function test_behaviour_maps_onto_the_library_and_the_rest_onto_the_extension(): void
    {
        $params = (new H5PSingleChoiceSetBuilder())->build($this->choiceSet());

        $this->assertSame(1500, $params['behaviour']['timeoutCorrect']);
        $this->assertSame(2500, $params['behaviour']['timeoutWrong']);
        $this->assertTrue($params['behaviour']['autoContinue']);
        $this->assertSame(70, $params['behaviour']['passPercentage']);

        // Things H5P has no field for live here and nowhere else.
        $this->assertTrue($params['eduerpSet']['randomizeAnswers']);
        $this->assertSame(2, $params['eduerpSet']['pointsPerQuestion']);
        $this->assertSame('Five quick questions.', $params['eduerpSet']['taskDescription']);
    }

    public function test_per_option_feedback_is_indexed_to_the_reordered_answers(): void
    {
        $params = (new H5PSingleChoiceSetBuilder())->build($this->choiceSet());

        // Question 1 was authored Solid / Liquid / Gas with feedback on the
        // two distractors. After reordering, index 0 is Liquid (no feedback),
        // 1 is Solid, 2 is Gas -- and the feedback must have moved with them.
        $this->assertSame(
            ['', 'A solid keeps its shape too.', 'A gas fills its container.'],
            $params['eduerpSet']['questions'][0]['answerFeedback']
        );
    }

    public function test_a_set_round_trips_through_parse_with_the_answer_intact(): void
    {
        $builder = new H5PSingleChoiceSetBuilder();
        $parsed = $builder->parse($builder->build($this->choiceSet()));

        $this->assertSame(70, $parsed['set']['pass_percentage']);
        $this->assertSame(2, $parsed['set']['points_per_question']);
        $this->assertTrue($parsed['set']['randomize_answers']);
        $this->assertCount(3, $parsed['questions']);

        // The answer survived the flag -> position -> flag conversion. It is
        // now FIRST rather than second, because that is what the package said
        // and the package is the only record a re-import has.
        $first = $parsed['questions'][0];
        $this->assertSame('<p>Liquid</p>', $first['options'][0]['option_text']);
        $this->assertTrue($first['options'][0]['is_correct']);
        $this->assertFalse($first['options'][1]['is_correct']);
        $this->assertFalse($first['options'][2]['is_correct']);

        // Exactly one correct option per question, every question. This is the
        // invariant the controller refuses a save over.
        foreach ($parsed['questions'] as $question) {
            $correct = array_filter($question['options'], fn (array $option) => $option['is_correct']);
            $this->assertCount(1, $correct);
        }

        // Per-question feedback came back attached to the right question.
        $this->assertSame('Yes — a liquid flows to fit.', $first['feedback_correct']);
        $this->assertNull($parsed['questions'][1]['feedback_correct']);
    }

    public function test_a_stock_package_with_no_extension_still_imports(): void
    {
        // What Lumi or h5p.org produces: choices, behaviour, no eduerpSet.
        $parsed = (new H5PSingleChoiceSetBuilder())->parse([
            'choices' => [
                ['question' => '<p>Is this a stock package?</p>', 'answers' => ['<p>Yes</p>', '<p>No</p>']],
            ],
            'behaviour' => ['enableRetry' => false, 'autoContinue' => false, 'passPercentage' => 80],
            'overallFeedback' => [['from' => 0, 'to' => 100, 'feedback' => 'Done.']],
        ]);

        $this->assertCount(1, $parsed['questions']);
        // The format's rule applied in the absence of anything else to go on.
        $this->assertTrue($parsed['questions'][0]['options'][0]['is_correct']);
        $this->assertFalse($parsed['set']['enable_retry']);
        $this->assertFalse($parsed['set']['auto_continue']);
        $this->assertSame(80, $parsed['set']['pass_percentage']);
        // Defaults for everything the package could not say.
        $this->assertSame(1, $parsed['set']['points_per_question']);
    }

    public function test_a_question_with_too_few_options_is_skipped_and_reported(): void
    {
        $parsed = (new H5PSingleChoiceSetBuilder())->parse([
            'choices' => [
                ['question' => '<p>Good one</p>', 'answers' => ['<p>A</p>', '<p>B</p>']],
                ['question' => '<p>Broken one</p>', 'answers' => ['<p>Only answer</p>']],
            ],
        ]);

        // Dropped, not repaired: inventing a distractor would put words in an
        // author's mouth that a class is then marked against.
        $this->assertCount(1, $parsed['questions']);
        $this->assertCount(1, $parsed['warnings']);
        $this->assertStringContainsString('Question 2', $parsed['warnings'][0]);

        // And the survivor was renumbered, so sort_order has no hole in it.
        $this->assertSame(0, $parsed['questions'][0]['sort_order']);
    }

    public function test_a_package_with_no_usable_question_is_refused(): void
    {
        $this->expectException(\RuntimeException::class);

        (new H5PSingleChoiceSetBuilder())->parse(['choices' => [
            ['question' => '<p>Broken</p>', 'answers' => ['<p>Only answer</p>']],
        ]]);
    }

    public function test_the_export_caveat_names_only_what_is_actually_lost(): void
    {
        $builder = new H5PSingleChoiceSetBuilder();

        // The fixture has per-option feedback, shuffling and 2 points each.
        $caveat = $builder->exportCaveat($this->choiceSet());
        $this->assertNotNull($caveat);
        $this->assertStringContainsString('feedback', $caveat);
        $this->assertStringContainsString('randomised order', $caveat);
        $this->assertStringContainsString('points per question', $caveat);

        // A plain set loses nothing, and says nothing. A warning on every
        // export teaches authors to ignore warnings.
        $plain = $this->choiceSet();
        $plain->randomize_questions = false;
        $plain->randomize_answers = false;
        $plain->points_per_question = 1;
        foreach ($plain->questions as $question) {
            $question->feedback_correct = null;
            $question->feedback_incorrect = null;
            $question->explanation = null;
            foreach ($question->options as $option) {
                $option->feedback = null;
            }
        }

        $this->assertNull($builder->exportCaveat($plain));
    }

    public function test_max_score_counts_questions_times_points(): void
    {
        $this->assertSame(6, $this->choiceSet()->maxScore());
    }

    // =======================================================================
    // True / false
    // =======================================================================

    /** A four-statement pool: true, false, true, false. */
    private function truefalse(): H5pTrueFalse
    {
        $item = new H5pTrueFalse([
            'title' => 'General knowledge',
            'task_description' => 'Decide true or false.',
            'enable_retry' => true,
            'enable_show_solution' => true,
            'enable_check_button' => false,
            'auto_check' => true,
            'confirm_check_dialog' => false,
            'confirm_retry_dialog' => false,
            'randomize_questions' => true,
            'questions_to_ask' => 2,
            'points_per_question' => 5,
            'pass_percentage' => 50,
            'show_progress' => true,
        ]);
        $item->id = 21;

        $questions = new Collection();
        $rows = [
            ['The Pacific is the largest ocean.', true, 'A third of the surface.', null],
            ['Lightning never strikes twice.', false, 'A saying, not a fact.', null],
            ['The Sun is a star.', true, 'An ordinary one, very close.', 'https://cdn.example.test/sun.png'],
            ['Bats are blind.', false, 'Every species can see.', null],
        ];

        foreach ($rows as $index => [$text, $correct, $explanation, $image]) {
            $question = new H5pTrueFalseQuestion([
                'question_text' => $text,
                'correct_answer' => $correct,
                'feedback_correct' => 'Correct.',
                'feedback_incorrect' => 'Not quite.',
                'explanation' => $explanation,
                'media_image' => $image,
                'media_alt' => $image !== null ? 'The Sun.' : null,
            ]);
            $question->id = 200 + $index;
            $question->sort_order = $index;
            $questions->push($question);
        }

        $item->setRelation('questions', $questions);

        return $item;
    }

    public function test_the_boolean_answer_becomes_the_string_h5p_expects(): void
    {
        $params = (new H5PTrueFalseBuilder())->build($this->truefalse());

        // The library's own field, for the first question.
        $this->assertSame('true', $params['correct']);
        $this->assertIsString($params['correct']);

        // And every question in the pool, which must agree with it.
        $this->assertSame(
            ['true', 'false', 'true', 'false'],
            array_column($params['eduerpPool']['questions'], 'correct')
        );
    }

    public function test_the_first_question_is_what_a_stock_host_will_run(): void
    {
        $params = (new H5PTrueFalseBuilder())->build($this->truefalse());

        $this->assertSame('<p>The Pacific is the largest ocean.</p>', $params['question']);
        // Its feedback is lifted onto `behaviour`, which is where the library
        // looks for it -- the pool keeps its own copy per question.
        $this->assertSame('Correct.', $params['behaviour']['feedbackOnCorrect']);
        $this->assertSame('Not quite.', $params['behaviour']['feedbackOnWrong']);
        $this->assertTrue($params['behaviour']['autoCheck']);
        $this->assertFalse($params['behaviour']['enableCheckButton']);
    }

    public function test_an_image_becomes_an_h5p_image_node_in_both_places(): void
    {
        $params = (new H5PTrueFalseBuilder())->build($this->truefalse());

        // The third statement carries the picture, so the library's own
        // `media` slot (which holds the FIRST question) is empty.
        $this->assertNull($params['media']['type']);

        $third = $params['eduerpPool']['questions'][2]['media'];
        $this->assertSame('H5P.Image 1.1', $third['library']);
        $this->assertSame('https://cdn.example.test/sun.png', $third['params']['file']['path']);
        $this->assertSame('image/png', $third['params']['file']['mime']);
        $this->assertSame('The Sun.', $third['params']['alt']);

        // A statement with no picture has no node at all, rather than an empty
        // one -- an empty H5P.Image renders as a broken image box.
        $this->assertNull($params['eduerpPool']['questions'][0]['media']);
    }

    public function test_a_pool_round_trips_through_parse(): void
    {
        $builder = new H5PTrueFalseBuilder();
        $parsed = $builder->parse($builder->build($this->truefalse()));

        $this->assertCount(4, $parsed['questions']);
        $this->assertSame(
            [true, false, true, false],
            array_column($parsed['questions'], 'correct_answer')
        );

        $this->assertSame(2, $parsed['item']['questions_to_ask']);
        $this->assertSame(5, $parsed['item']['points_per_question']);
        $this->assertTrue($parsed['item']['randomize_questions']);
        $this->assertTrue($parsed['item']['auto_check']);
        $this->assertFalse($parsed['item']['enable_check_button']);

        // The picture and its description came back on the statement they
        // belong to, not on the first one.
        $this->assertNull($parsed['questions'][0]['media_image']);
        $this->assertSame('https://cdn.example.test/sun.png', $parsed['questions'][2]['media_image']);
        $this->assertSame('The Sun.', $parsed['questions'][2]['media_alt']);
    }

    public function test_a_stock_one_question_package_imports_as_a_pool_of_one(): void
    {
        $parsed = (new H5PTrueFalseBuilder())->parse([
            'question' => '<p>Is this a stock package?</p>',
            'correct' => 'false',
            'behaviour' => [
                'enableRetry' => false,
                'autoCheck' => true,
                'feedbackOnCorrect' => 'Right.',
                'feedbackOnWrong' => 'Wrong.',
            ],
            'media' => ['type' => ['params' => ['file' => ['path' => 'images/diagram.png'], 'alt' => 'A diagram.']]],
            'overallFeedback' => [['from' => 0, 'to' => 100, 'feedback' => 'Done.']],
        ]);

        $this->assertCount(1, $parsed['questions']);
        $this->assertFalse($parsed['questions'][0]['correct_answer']);
        // The library keeps its feedback on `behaviour`; a pool keeps it on
        // the question, so it has to be lifted across on the way in.
        $this->assertSame('Right.', $parsed['questions'][0]['feedback_correct']);
        $this->assertSame('images/diagram.png', $parsed['questions'][0]['media_image']);
        $this->assertSame('A diagram.', $parsed['questions'][0]['media_alt']);
        $this->assertFalse($parsed['item']['enable_retry']);
        $this->assertTrue($parsed['item']['auto_check']);
    }

    public function test_an_unreadable_correct_value_is_treated_as_true(): void
    {
        // What a hand-written or third-party package plausibly contains. Only
        // an explicit falsehood is false -- see the builder's header.
        $parsed = (new H5PTrueFalseBuilder())->parse([
            'eduerpPool' => ['questions' => [
                ['question' => 'Boolean true', 'correct' => true],
                ['question' => 'Integer one', 'correct' => 1],
                ['question' => 'String one', 'correct' => '1'],
                ['question' => 'Nonsense', 'correct' => 'perhaps'],
                ['question' => 'String false', 'correct' => 'false'],
                ['question' => 'Integer zero', 'correct' => 0],
            ]],
        ]);

        $this->assertSame(
            [true, true, true, true, false, false],
            array_column($parsed['questions'], 'correct_answer')
        );
    }

    public function test_a_blank_statement_is_skipped_and_reported(): void
    {
        $parsed = (new H5PTrueFalseBuilder())->parse([
            'eduerpPool' => ['questions' => [
                ['question' => 'A real statement.', 'correct' => 'true'],
                ['question' => '   ', 'correct' => 'false'],
                ['question' => 'Another real one.', 'correct' => 'false'],
            ]],
        ]);

        $this->assertCount(2, $parsed['questions']);
        $this->assertCount(1, $parsed['warnings']);
        $this->assertStringContainsString('Statement 2', $parsed['warnings'][0]);
        // Renumbered, so the pool has no hole in its order.
        $this->assertSame([0, 1], array_column($parsed['questions'], 'sort_order'));
    }

    public function test_questions_to_ask_is_clamped_to_the_pool_that_survived_import(): void
    {
        $parsed = (new H5PTrueFalseBuilder())->parse([
            'eduerpPool' => [
                'questionsToAsk' => 10,
                'questions' => [
                    ['question' => 'One.', 'correct' => 'true'],
                    ['question' => 'Two.', 'correct' => 'false'],
                ],
            ],
        ]);

        // Saving "ask 10" against a pool of 2 would report every attempt as a
        // one-fifth failure.
        $this->assertSame(2, $parsed['item']['questions_to_ask']);
    }

    public function test_the_export_caveat_counts_the_statements_a_foreign_host_will_drop(): void
    {
        $builder = new H5PTrueFalseBuilder();

        $caveat = $builder->exportCaveat($this->truefalse());
        $this->assertNotNull($caveat);
        $this->assertStringContainsString('4 statements', $caveat);
        $this->assertStringContainsString('The Pacific is the largest ocean.', $caveat);

        // A single statement exports losslessly and says nothing.
        $single = $this->truefalse();
        $single->setRelation('questions', new Collection([$single->questions->first()]));
        $this->assertNull($builder->exportCaveat($single));
    }

    public function test_max_score_counts_the_questions_asked_not_the_pool(): void
    {
        $item = $this->truefalse();

        // Four in the pool, two asked, five points each.
        $this->assertSame(2, $item->questionsPerAttempt());
        $this->assertSame(10, $item->maxScore());

        // 0 means the whole pool.
        $item->questions_to_ask = 0;
        $this->assertSame(4, $item->questionsPerAttempt());
        $this->assertSame(20, $item->maxScore());

        // Asking for more than there is asks for what there is.
        $item->questions_to_ask = 99;
        $this->assertSame(4, $item->questionsPerAttempt());
    }

    public function test_an_empty_pool_builds_without_faulting(): void
    {
        // build() runs on every save, including the save that creates an empty
        // draft. It must not fault before publish has had a chance to refuse.
        $item = new H5pTrueFalse(['title' => 'Empty draft', 'points_per_question' => 1]);
        $item->id = 31;
        $item->setRelation('questions', new Collection());

        $params = (new H5PTrueFalseBuilder())->build($item);

        $this->assertSame('', $params['question']);
        $this->assertSame('true', $params['correct']);
        $this->assertSame([], $params['eduerpPool']['questions']);
    }
}
