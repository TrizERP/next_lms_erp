<?php

namespace Tests\Unit\StudyDeck;

use App\Services\StudyDeck\QuestionSelector;
use PHPUnit\Framework\TestCase;

class QuestionSelectorTest extends TestCase
{
    private function row(string $stem, ?array $answer = null, int $id = 1, array $columns = []): array
    {
        return $columns + [
            'id' => $id, 'concept_id' => 7, 'question_title' => $stem, 'points' => 1, 'question_type' => 'multiple',
            'answer' => json_encode($answer ?? ['item_form' => 'mcq', 'correct_option' => 'A', 'model_answer' => 'A is right because it names the unit.', 'options' => [
                ['label' => 'A', 'text' => 'x', 'is_correct' => true], ['label' => 'B', 'text' => 'y', 'is_correct' => false]]]),
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dependent')]
    public function test_stems_that_need_missing_context_are_rejected(string $stem): void
    {
        $r = (new QuestionSelector())->select([$this->row($stem)]);

        $this->assertSame([], $r['eligible'], $stem);
        $this->assertStringContainsString('context', $r['excluded'][0]['reason'], $stem);
    }

    public static function dependent(): array
    {
        return array_map(fn ($s) => [$s], [
            'According to the passage, why is the sky blue?',
            'Based on the above paragraph, what follows?',
            'From the text above, pick the true statement.',
            'As mentioned earlier, which is correct?',
            'Why would the source not describe discovery as private?',
            'Refer to the figure given below.',
            'Look at the diagram shown above and name the part.',
            'Using the data in the table, find the mean.',
            // The review found these four slipping through.
            'Why is the detailed object on the left represented by the simplified model on the right?',
            'How should the terms be mapped to the concepts represented by X, Y, and Z in the diagram?',
            "Why is Person B's approach more testable than Person A's approach?",
            'Which statement describes the role of mathematics in Example 1.3?',
            // Labelled parts and other pictures.
            'Which organ is shown at point P?',
            'Name the part labelled A.',
            'What does the graph show about the speed?',
            'In the picture, which tool is used?',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('standalone')]
    public function test_self_contained_stems_are_kept(string $stem): void
    {
        $r = (new QuestionSelector())->select([$this->row($stem)]);

        $this->assertNotEmpty($r['eligible'], $stem . ' was excluded: ' . json_encode($r['excluded']));
    }

    public static function standalone(): array
    {
        return array_map(fn ($s) => [$s], [
            'Which unit measures mass?',
            'A car moves to the right at a steady speed. What is its direction of motion?',
            'Person A says the ball is heavy and Person B says it is light. Who is describing mass?',
            'A student measures the length of a table in handspans. Why is the result hard to compare?',
        ]);
    }

    public function test_an_embedded_table_counts_as_context_the_learner_has(): void
    {
        $withTable = 'Using the data below, what is the trend? <html><body><table><tr><td>Day</td><td>Rain</td></tr></table></body></html>';
        $r = (new QuestionSelector())->select([$this->row('Which unit measures mass?', null, 1), $this->row($withTable, null, 2)]);

        $this->assertCount(2, $r['eligible'][7]);
        $this->assertStringNotContainsString('<html>', $r['eligible'][7][1]['stem']);
    }

    public function test_an_option_that_points_at_missing_context_rejects_the_question(): void
    {
        $answer = ['item_form' => 'mcq', 'correct_option' => 'A', 'model_answer' => 'Because.', 'options' => [
            ['label' => 'A', 'text' => 'The part on the left of the diagram'], ['label' => 'B', 'text' => 'y']]];
        $r = (new QuestionSelector())->select([$this->row('Which part moves?', $answer)]);

        $this->assertSame([], $r['eligible']);
        $this->assertStringContainsString('an option depends on missing context', $r['excluded'][0]['reason']);
    }

    public function test_unusable_rows_are_excluded_with_a_reason(): void
    {
        $s = new QuestionSelector();
        $opts = [['label' => 'A', 'text' => 'x'], ['label' => 'B', 'text' => 'y']];
        $noKey = $this->row('Which is right?', ['item_form' => 'mcq', 'model_answer' => 'because', 'options' => $opts]);
        $figure = $this->row('Name the part.', ['item_form' => 'mcq', 'figure_required' => true, 'correct_option' => 'A', 'model_answer' => 'b', 'options' => $opts], 2);
        $long = $this->row('Discuss at length.', ['item_form' => 'long_answer', 'model_answer' => 'x'], 3);
        $noAnswer = $this->row('Define a law.', ['item_form' => 'short'], 4, ['question_type' => 'narrative']);
        $noWhy = $this->row('Which unit is it?', ['item_form' => 'mcq', 'correct_option' => 'A', 'options' => $opts], 5);

        $r = $s->select([$noKey, $figure, $long, $noAnswer, $noWhy]);

        $this->assertSame([], $r['eligible']);
        $reasons = array_column($r['excluded'], 'reason', 'id');
        $this->assertStringContainsString('answer key', $reasons[1]);
        $this->assertStringContainsString('figure', $reasons[2]);
        $this->assertStringContainsString('not allowed', $reasons[3]);
        $this->assertStringContainsString('model answer', $reasons[4]);
        $this->assertStringContainsString('no stored explanation', $reasons[5]);
    }

    public function test_a_narrative_row_with_no_recorded_form_is_excluded_because_the_player_cannot_ask_it(): void
    {
        // sub_type says "Short Answer", but the question-bank API reports no form for this row, so no player is chosen for it.
        $row = $this->row('Explain why a model ignores details.', ['sub_type' => 'Short Answer', 'model_answer' => 'Because it keeps the question answerable.'], 1, ['question_type' => 'narrative']);

        $r = (new QuestionSelector())->select([$row]);

        $this->assertSame([], $r['eligible']);
        $this->assertStringContainsString('no question form is recorded', $r['excluded'][0]['reason']);

        // The same row with a form recorded the way the API reads it is kept.
        $tagged = (new QuestionSelector())->select([$row + ['question_format_code' => 'short']]);
        $this->assertSame('short_answer', $tagged['eligible'][7][0]['form']);
        $sidecar = (new QuestionSelector())->select([$row + ['sidecar_code' => 'very_short']]);
        $this->assertSame('very_short_answer', $sidecar['eligible'][7][0]['form']);
    }

    public function test_a_choice_row_with_no_recorded_form_is_still_plain_multiple_choice(): void
    {
        $row = $this->row('Which unit measures mass?', ['correct_option' => 'A', 'model_answer' => 'Because.', 'options' => [['label' => 'A', 'text' => 'x'], ['label' => 'B', 'text' => 'y']]]);

        $this->assertSame('mcq', (new QuestionSelector())->select([$row])['eligible'][7][0]['form']);
    }

    public function test_a_question_whose_explanation_points_at_an_unseen_passage_is_excluded(): void
    {
        $answer = fn (string $why) => ['item_form' => 'mcq', 'correct_option' => 'A', 'model_answer' => $why, 'options' => [['label' => 'A', 'text' => 'x'], ['label' => 'B', 'text' => 'y']]];
        $s = new QuestionSelector();

        foreach (['This matches the passage: pupils study both the product and the process.', 'The text says that science keeps changing.', 'According to the author, models are simplified.', 'As mentioned earlier, units must agree.'] as $i => $why) {
            $r = $s->select([$this->row('Which unit measures mass?', $answer($why), $i + 1)]);
            $this->assertSame([], $r['eligible'], $why);
            $this->assertStringContainsString('its explanation depends on missing context', $r['excluded'][0]['reason'], $why);
        }

        // Talking about the chapter the learner is in is fine.
        $ok = $s->select([$this->row('Which unit measures mass?', $answer('A kilogram is the unit of mass, as this chapter explains.'))]);
        $this->assertNotEmpty($ok['eligible']);
    }

    public function test_a_narrative_model_answer_is_checked_the_same_way(): void
    {
        $row = $this->row('Why does a map ignore trees?', ['item_form' => 'short', 'model_answer' => 'The passage says a map keeps only what is needed.'], 1, ['question_type' => 'narrative']);

        $r = (new QuestionSelector())->select([$row]);

        $this->assertSame([], $r['eligible']);
        $this->assertStringContainsString('its explanation', $r['excluded'][0]['reason']);
    }

    public function test_the_banks_real_bloom_difficulty_and_dok_are_read_not_invented(): void
    {
        $r = (new QuestionSelector())->select([$this->row('Which unit measures mass?', null, 1, ['g_bloom' => 'Analyse', 'g_difficulty' => 'Hard', 'g_dok' => 3])]);
        $q = $r['eligible'][7][0];

        $this->assertSame('analyze', $q['bloom']);
        $this->assertSame('bank', $q['bloom_source']);
        $this->assertSame('hard', $q['difficulty']);
        $this->assertSame(3, $q['dok']);

        $none = (new QuestionSelector())->select([$this->row('Which unit measures mass?')])['eligible'][7][0];
        $this->assertSame('', $none['bloom']);
        $this->assertSame('none', $none['bloom_source']);
        $this->assertNull($none['dok']);
    }

    public function test_the_stored_rationale_travels_with_a_choice_question(): void
    {
        $q = (new QuestionSelector())->select([$this->row('Which unit measures mass?')])['eligible'][7][0];

        $this->assertSame('A is right because it names the unit.', $q['explanation']);
    }

    public function test_padded_wording_is_flagged_and_used_only_when_nothing_cleaner_exists(): void
    {
        $padded = $this->row('Identify the distinctly INCORRECT statement deeply regarding the fundamental nature of mathematics perfectly framed.', null, 1);
        $plain = $this->row('Which unit measures mass?', null, 2);

        $both = (new QuestionSelector())->select([$padded, $plain]);
        $this->assertSame([2], array_column($both['eligible'][7], 'id'));
        $this->assertSame(1, $both['flagged'][0]['id']);
        $this->assertStringContainsString('padded wording', $both['flagged'][0]['flags'][0]);

        $only = (new QuestionSelector())->select([$padded]);
        $this->assertSame([1], array_column($only['eligible'][7], 'id'), 'a flagged question is a last resort, not discarded');
        $this->assertNotEmpty($only['eligible'][7][0]['flags']);
    }

    public function test_duplicates_collapse_and_each_concept_is_capped(): void
    {
        $rows = [];
        foreach (range(1, 8) as $i) {
            $rows[] = $this->row("Question number $i about units?", null, $i);
        }
        $rows[] = $this->row('Question number 1 about units?', null, 99);

        $r = (new QuestionSelector())->select($rows, 3);

        $this->assertCount(3, $r['eligible'][7]);
        $this->assertContains(99, array_column($r['excluded'], 'id'));
    }
}
