<?php

namespace Tests\Unit;

use App\Models\lms\h5p\H5pTextActivity;
use App\Models\lms\h5p\H5pTextActivityBlank;
use App\Services\lms\H5P\H5PTextActivityBuilder;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

/**
 * Covers the two things that can silently corrupt a text activity: the markup
 * grammar every answer key is derived from, and the per-library flattening
 * that export does on the way out.
 *
 * Both are places where being wrong looks like being right -- a passage that
 * parses to the wrong answers still renders, and a package that drops an
 * alternative still opens. Neither shows up until a learner is marked down.
 *
 * No database. Models are built in memory with their relations set, which is
 * all the builder reads -- so these run anywhere, including a CI box with no
 * MySQL, and they fail for the reason they name rather than on a fixture.
 */
class H5PTextActivityBuilderTest extends TestCase
{
    private function builder(): H5PTextActivityBuilder
    {
        return new H5PTextActivityBuilder();
    }

    /** @param array<string,mixed> $attributes */
    private function activity(string $contentType, string $passage, array $attributes = []): H5pTextActivity
    {
        $activity = new H5pTextActivity(array_merge([
            'content_type' => $contentType,
            'title' => 'Sample',
            'task_description' => 'Do the thing.',
            'passage' => $passage,
            'distractors' => '',
            'enable_retry' => true,
            'enable_show_solution' => true,
            'enable_check' => true,
            'case_sensitive' => false,
            'accept_spelling_errors' => false,
            'instant_feedback' => false,
            'show_score_points' => true,
            'separate_lines' => false,
            'solution_requires_input' => true,
            'points_per_blank' => 1,
            'pass_percentage' => 100,
        ], $attributes));
        $activity->id = 42;

        $blanks = [];
        foreach ($this->builder()->parseAnswerKey($contentType, $activity->passage, $activity->distractors) as $slot) {
            $blank = new H5pTextActivityBlank($slot);
            $blanks[] = $blank;
        }
        $activity->setRelation('blanks', new Collection($blanks));

        return $activity;
    }

    // -----------------------------------------------------------------------
    // The markup grammar
    // -----------------------------------------------------------------------

    public function test_it_reads_solution_alternatives_and_tip_out_of_one_slot(): void
    {
        $slots = $this->builder()->parsePassage('Oslo is the capital of *Norway/Noreg:It is Nordic*.');

        $this->assertCount(1, $slots);
        $this->assertSame('Norway', $slots[0]['solution']);
        $this->assertSame(['Noreg'], $slots[0]['alternatives']);
        $this->assertSame('It is Nordic', $slots[0]['tip']);
    }

    public function test_it_numbers_slots_in_reading_order(): void
    {
        $slots = $this->builder()->parsePassage('The *dog* chased the *cat* past the *fox*.');

        $this->assertSame([0, 1, 2], array_column($slots, 'blank_index'));
        $this->assertSame(['dog', 'cat', 'fox'], array_column($slots, 'solution'));
    }

    /**
     * An escaped asterisk is prose, not a delimiter. Without this, a passage
     * about multiplication ("3 \* 4") turns the text between two of them into
     * an answer and the activity silently asks the wrong question.
     */
    public function test_it_ignores_escaped_asterisks(): void
    {
        $slots = $this->builder()->parsePassage('Work out 3 \\* 4 and write *12* in the box.');

        $this->assertCount(1, $slots);
        $this->assertSame('12', $slots[0]['solution']);
    }

    /**
     * An unbalanced asterisk must yield nothing rather than swallowing to the
     * next one -- a half-typed passage should look empty, not wrong.
     */
    public function test_an_unclosed_marker_produces_no_slot(): void
    {
        $this->assertSame([], $this->builder()->parsePassage('The *dog chased the cat.'));
    }

    public function test_an_empty_marker_is_not_an_answer(): void
    {
        $slots = $this->builder()->parsePassage('The ** chased the *cat*.');

        $this->assertCount(1, $slots);
        $this->assertSame('cat', $slots[0]['solution']);
        // The surviving slot is renumbered from zero: the empty one never
        // existed as far as the answer key is concerned.
        $this->assertSame(0, $slots[0]['blank_index']);
    }

    public function test_the_tip_is_taken_from_the_last_colon_so_a_solution_may_contain_one(): void
    {
        $slots = $this->builder()->parsePassage('The ratio is *3:4:both are integers*.');

        $this->assertSame('3:4', $slots[0]['solution']);
        $this->assertSame('both are integers', $slots[0]['tip']);
    }

    // -----------------------------------------------------------------------
    // Distractors
    // -----------------------------------------------------------------------

    public function test_distractors_are_accepted_as_a_plain_list_or_as_markup(): void
    {
        $plain = $this->builder()->parseDistractors('ribosome, mitochondrion', 3);
        $marked = $this->builder()->parseDistractors('*ribosome* *mitochondrion*', 3);

        foreach ([$plain, $marked] as $slots) {
            $this->assertSame(['ribosome', 'mitochondrion'], array_column($slots, 'solution'));
            // Numbered after the passage's own slots, and flagged, so max
            // score counts them out.
            $this->assertSame([3, 4], array_column($slots, 'blank_index'));
            $this->assertSame([true, true], array_column($slots, 'is_distractor'));
        }
    }

    public function test_only_drag_the_words_gets_distractors(): void
    {
        $key = $this->builder()->parseAnswerKey('mark_the_words', 'The *dog* ran.', 'cat, fox');

        $this->assertCount(1, $key);
        $this->assertSame('dog', $key[0]['solution']);
    }

    // -----------------------------------------------------------------------
    // Build: each library's own shape
    // -----------------------------------------------------------------------

    public function test_blanks_params_put_the_passage_in_a_questions_list(): void
    {
        $params = $this->builder()->build(
            $this->activity('fill_in_the_blanks', 'Oslo is in *Norway*.', ['case_sensitive' => true])
        );

        $this->assertIsArray($params['questions']);
        $this->assertCount(1, $params['questions']);
        $this->assertStringContainsString('*Norway*', $params['questions'][0]);
        $this->assertTrue($params['behaviour']['caseSensitive']);
        $this->assertArrayNotHasKey('textField', $params);
    }

    public function test_drag_text_params_use_text_field_and_carry_distractors(): void
    {
        $activity = $this->activity('drag_text', 'Oslo is in *Norway*.', ['distractors' => 'Sweden, Denmark']);
        $params = $this->builder()->build($activity);

        $this->assertStringContainsString('*Norway*', $params['textField']);
        $this->assertSame('*Sweden* *Denmark*', $params['distractors']);
        $this->assertArrayNotHasKey('questions', $params);
    }

    public function test_mark_the_words_params_use_its_own_button_key_names(): void
    {
        $params = $this->builder()->build($this->activity('mark_the_words', 'The *dog* ran.'));

        $this->assertArrayHasKey('checkAnswerButton', $params);
        $this->assertArrayHasKey('showSolutionButton', $params);
        // The un-suffixed spellings belong to the other two libraries; a
        // package carrying them is one MarkTheWords will not label correctly.
        $this->assertArrayNotHasKey('checkAnswer', $params);
    }

    public function test_overall_feedback_always_covers_the_whole_range(): void
    {
        $params = $this->builder()->build($this->activity('mark_the_words', 'The *dog* ran.'));

        $this->assertSame([['from' => 0, 'to' => 100]], $params['overallFeedback']);
    }

    // -----------------------------------------------------------------------
    // Flattening: what each library cannot express
    // -----------------------------------------------------------------------

    /**
     * Blanks renders the full grammar, so nothing is dropped and the exporter
     * has nothing to warn about.
     */
    public function test_blanks_keeps_alternatives_and_tips_untouched(): void
    {
        $builder = $this->builder();
        $params = $builder->build(
            $this->activity('fill_in_the_blanks', 'Oslo is in *Norway/Noreg:Nordic*.')
        );

        $this->assertStringContainsString('*Norway/Noreg:Nordic*', $params['questions'][0]);
        $this->assertSame([], $builder->notes());
    }

    public function test_drag_text_drops_alternatives_keeps_tips_and_says_so(): void
    {
        $builder = $this->builder();
        $params = $builder->build(
            $this->activity('drag_text', 'Oslo is in *Norway/Noreg:Nordic*.')
        );

        // DragText has no `/` syntax, so the alternative cannot survive -- but
        // the author is told rather than left to discover it in a marked class.
        $this->assertStringContainsString('*Norway:Nordic*', $params['textField']);
        $this->assertStringNotContainsString('Noreg', $params['textField']);
        $this->assertNotEmpty($builder->notes());
        $this->assertStringContainsString('Noreg', implode(' ', $builder->notes()));
    }

    public function test_mark_the_words_drops_both_and_says_so_twice(): void
    {
        $builder = $this->builder();
        $params = $builder->build(
            $this->activity('mark_the_words', 'The *dog/hound:a pet* ran.')
        );

        $this->assertStringContainsString('*dog*', $params['textField']);
        $this->assertStringNotContainsString('hound', $params['textField']);
        $this->assertStringNotContainsString('a pet', $params['textField']);
        $this->assertCount(2, $builder->notes());
    }

    /**
     * Rewriting happens right-to-left so each replacement leaves the earlier
     * offsets valid. Left-to-right would corrupt every slot after the first
     * whose replacement changed length -- which is every flattened slot.
     */
    public function test_flattening_several_slots_keeps_them_all_correct(): void
    {
        $params = $this->builder()->build(
            $this->activity('mark_the_words', 'A *cat/feline:pet* met a *dog/hound:pet* and a *fox*.')
        );

        $this->assertSame('A *cat* met a *dog* and a *fox*.', $params['textField']);
    }

    // -----------------------------------------------------------------------
    // Parse (import)
    // -----------------------------------------------------------------------

    public function test_parsing_blanks_params_joins_several_questions_into_one_passage(): void
    {
        $parsed = $this->builder()->parse([
            'text' => '<p>Fill these in.</p>',
            'questions' => ['First is *one*.', 'Second is *two*.'],
            'behaviour' => ['caseSensitive' => true, 'enableRetry' => false],
        ], 'fill_in_the_blanks');

        $this->assertSame("First is *one*.\nSecond is *two*.", $parsed['activity']['passage']);
        $this->assertTrue($parsed['activity']['case_sensitive']);
        $this->assertFalse($parsed['activity']['enable_retry']);
        $this->assertSame(['one', 'two'], array_column($parsed['blanks'], 'solution'));
    }

    /**
     * DragText writes `instantFeedback` and Blanks writes `autoCheck` for the
     * same behaviour. One column holds whichever the package used, so a
     * round trip through either library keeps the author's setting.
     */
    public function test_instant_feedback_is_read_from_either_spelling(): void
    {
        $fromDragText = $this->builder()->parse(
            ['textField' => 'a *b*', 'behaviour' => ['instantFeedback' => true]],
            'drag_text'
        );
        $fromBlanks = $this->builder()->parse(
            ['questions' => ['a *b*'], 'behaviour' => ['autoCheck' => true]],
            'fill_in_the_blanks'
        );

        $this->assertTrue($fromDragText['activity']['instant_feedback']);
        $this->assertTrue($fromBlanks['activity']['instant_feedback']);
    }

    public function test_a_drag_text_round_trip_keeps_the_passage_and_the_distractors(): void
    {
        $original = $this->activity('drag_text', 'Oslo is in *Norway*.', ['distractors' => 'Sweden, Denmark']);

        $parsed = $this->builder()->parse($this->builder()->build($original), 'drag_text');

        $this->assertSame('Oslo is in *Norway*.', $parsed['activity']['passage']);
        $this->assertSame(
            ['Norway', 'Sweden', 'Denmark'],
            array_column($parsed['blanks'], 'solution')
        );
        $this->assertSame([false, true, true], array_column($parsed['blanks'], 'is_distractor'));
    }

    public function test_the_pass_mark_is_inferred_from_the_lowest_positive_feedback_band(): void
    {
        $parsed = $this->builder()->parse([
            'textField' => 'a *b*',
            'overallFeedback' => [
                ['from' => 0, 'to' => 59, 'feedback' => 'Try again'],
                ['from' => 60, 'to' => 100, 'feedback' => 'Passed'],
            ],
        ], 'mark_the_words');

        $this->assertSame(60, $parsed['activity']['pass_percentage']);
    }

    public function test_a_single_full_range_band_means_no_pass_mark_was_set(): void
    {
        $parsed = $this->builder()->parse([
            'textField' => 'a *b*',
            'overallFeedback' => [['from' => 0, 'to' => 100]],
        ], 'mark_the_words');

        $this->assertSame(100, $parsed['activity']['pass_percentage']);
    }

    // -----------------------------------------------------------------------
    // Library identity
    // -----------------------------------------------------------------------

    public function test_each_type_resolves_to_its_official_machine_name(): void
    {
        $builder = $this->builder();

        $this->assertSame('H5P.DragText', $builder->machineName('drag_text'));
        $this->assertSame('H5P.Blanks', $builder->machineName('fill_in_the_blanks'));
        $this->assertSame('H5P.MarkTheWords', $builder->machineName('mark_the_words'));
    }

    public function test_an_unknown_type_is_refused_rather_than_guessed(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->builder()->libraryKey('crossword');
    }
}
