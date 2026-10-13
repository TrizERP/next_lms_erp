<?php

namespace Tests\Unit\StudyDeck;

use App\Services\StudyDeck\ActivityPlanner;
use PHPUnit\Framework\TestCase;

/**
 * How a bank question is asked on a slide. The planner emits specs only; these tests
 * pin the selection rules: by what the concept and question ARE, never by subject.
 */
class ActivityPlannerTest extends TestCase
{
    private function q(int $id, string $form, array $over = []): array
    {
        return $over + [
            'id' => $id, 'concept_id' => 1, 'form' => $form, 'bloom' => 'understand', 'difficulty' => 'medium', 'dok' => 2,
            'answer_text' => $form === 'short_answer' ? 'A repeated pattern.' : '', 'stem' => 'x', 'options' => [],
        ];
    }

    private function concept(array $over = []): array
    {
        return $over + ['id' => 1, 'name' => 'Laws', 'difficulty' => 'Easy', 'dok' => [2], 'blooms' => ['understand'], 'real_world' => [], 'misconceptions' => [], 'relationships' => []];
    }

    private function slide(array $over = []): array
    {
        return $over + ['slide_type' => 'concept_intro', 'concept_ids' => [1], 'h5p_pattern' => null, 'relationship' => null];
    }

    private function plan(array $slide, array $questions, array $concept = [], ?int $level = 9): array
    {
        return (new ActivityPlanner())->plan($slide, $questions, [1 => $concept + $this->concept(), 2 => $this->concept(['id' => 2])], $level);
    }

    public function test_each_form_defaults_to_the_target_the_runtime_gives_it(): void
    {
        $acts = $this->plan($this->slide(), [$this->q(1, 'mcq'), $this->q(2, 'true_false'), $this->q(3, 'short_answer', ['answer_text' => str_repeat('long answer words ', 6)])]);

        $this->assertSame(['single_choice_set', 'true_false', 'essay'], array_column($acts, 'as'));
        $this->assertSame(['single_choice_set', 'true_false', 'essay'], array_column($acts, 'default_as'));
    }

    public function test_a_pattern_shapes_an_activity_only_where_it_really_fits(): void
    {
        $flash = $this->plan($this->slide(['h5p_pattern' => ['type' => 'flashcards', 'reason' => 'terms']]), [$this->q(1, 'mcq', ['bloom' => 'remember'])]);
        $this->assertSame('flashcards', $flash[0]['as']);
        $this->assertSame('flashcards', $flash[0]['pattern']);

        // An understand-level question is not a recall card; the pattern is not claimed.
        $notRecall = $this->plan($this->slide(['h5p_pattern' => ['type' => 'flashcards', 'reason' => 'terms']]), [$this->q(1, 'mcq', ['bloom' => 'analyze'])]);
        $this->assertSame('single_choice_set', $notRecall[0]['as']);
        $this->assertNull($notRecall[0]['pattern']);

        $blank = $this->plan($this->slide(['h5p_pattern' => ['type' => 'fill_blanks', 'reason' => 'wording']]), [$this->q(1, 'short_answer')]);
        $this->assertSame('fill_in_the_blanks', $blank[0]['as']);

        // A hard, conceptual idea is explained in writing, not typed into a blank.
        $hard = $this->plan($this->slide(['h5p_pattern' => ['type' => 'fill_blanks', 'reason' => 'wording']]), [$this->q(1, 'short_answer')], $this->concept(['difficulty' => 'Hard']));
        $this->assertSame('essay', $hard[0]['as']);

        $tf = $this->plan($this->slide(['h5p_pattern' => ['type' => 'true_false', 'reason' => 'misconception']]), [$this->q(1, 'mcq'), $this->q(2, 'true_false')]);
        $this->assertSame(['single_choice_set', 'true_false'], array_column($tf, 'as'));
        $this->assertSame([null, 'true_false'], array_column($tf, 'pattern'));
    }

    public function test_matching_and_drag_drop_are_never_forced_onto_other_forms(): void
    {
        $acts = $this->plan($this->slide(['h5p_pattern' => ['type' => 'matching', 'reason' => 'compare']]), [$this->q(1, 'mcq')]);
        $this->assertSame('single_choice_set', $acts[0]['as']);

        $match = $this->plan($this->slide(['h5p_pattern' => ['type' => 'matching', 'reason' => 'compare']]), [$this->q(1, 'match_following')]);
        $this->assertSame('memory_game', $match[0]['as']);

        $drag = $this->plan($this->slide(['h5p_pattern' => ['type' => 'drag_drop', 'reason' => 'sort']]), [$this->q(1, 'drag_drop')]);
        $this->assertSame('drag_drop', $drag[0]['as']);
    }

    public function test_younger_learners_get_a_card_instead_of_written_reasoning(): void
    {
        $long = ['answer_text' => str_repeat('a longer model answer ', 5)];
        $this->assertSame('essay', $this->plan($this->slide(), [$this->q(1, 'short_answer', $long)], [], 9)[0]['as']);
        $this->assertSame('flashcards', $this->plan($this->slide(), [$this->q(1, 'short_answer', $long)], [], 4)[0]['as']);
    }

    public function test_branching_needs_a_real_decision_a_choice_question_and_a_consequence(): void
    {
        $p = new ActivityPlanner();
        $mcq = [$this->q(1, 'mcq')];
        $rich = $this->concept(['real_world' => ['a map'], 'blooms' => ['apply']]);

        $ok = $p->branchingEligible($this->slide(['slide_type' => 'scenario']), $mcq, [1 => $rich]);
        $this->assertTrue($ok['ok'], $ok['reason']);

        $this->assertFalse($p->branchingEligible($this->slide(['slide_type' => 'concept_intro']), $mcq, [1 => $rich])['ok']);
        $this->assertFalse($p->branchingEligible($this->slide(['slide_type' => 'scenario']), [$this->q(1, 'short_answer')], [1 => $rich])['ok']);
        $this->assertFalse($p->branchingEligible($this->slide(['slide_type' => 'scenario']), $mcq, [1 => $this->concept(['blooms' => ['apply']])])['ok'], 'no consequence to branch on');
        $this->assertFalse($p->branchingEligible($this->slide(['slide_type' => 'scenario']), $mcq, [1 => $this->concept(['real_world' => ['x'], 'dok' => [1], 'blooms' => ['remember']])])['ok'], 'recall has no decision');
    }

    public function test_an_eligible_branching_slide_plays_its_choice_question_as_a_decision(): void
    {
        $rich = $this->concept(['real_world' => ['a map'], 'blooms' => ['apply']]);
        $slide = $this->slide(['slide_type' => 'scenario', 'h5p_pattern' => ['type' => 'branching', 'reason' => 'a choice with consequences']]);

        $acts = $this->plan($slide, [$this->q(1, 'mcq')], $rich);
        $this->assertTrue($acts[0]['decision']);
        $this->assertSame('branching', $acts[0]['pattern']);
        $this->assertSame('single_choice_set', $acts[0]['as'], 'there is no multi-path branching player; it is asked as a decision question');

        // Ineligible: the pattern is not claimed and nothing is a decision.
        $recall = $this->plan($this->slide(['slide_type' => 'concept_intro', 'h5p_pattern' => ['type' => 'branching', 'reason' => 'x']]), [$this->q(1, 'mcq')], $rich);
        $this->assertFalse($recall[0]['decision']);
        $this->assertNull($recall[0]['pattern']);
    }

    public function test_labels_come_from_the_five_allowed_and_vary_with_what_is_asked(): void
    {
        $p = new ActivityPlanner();

        $this->assertSame('Try it', $p->label('create', 'mcq', false, 0));
        $this->assertSame('Apply', $p->label('apply', 'mcq', false, 0));
        $this->assertSame('Think about it', $p->label('evaluate', 'mcq', false, 0));
        $this->assertSame('Explain', $p->label('understand', 'short_answer', false, 0));
        $this->assertSame('Check', $p->label('remember', 'mcq', false, 0));
        $this->assertSame('Think about it', $p->label('remember', 'mcq', true, 0), 'a question that connects another concept');
        $this->assertNotSame($p->label('', 'mcq', false, 0), $p->label('', 'mcq', false, 1), 'unlabelled questions alternate');

        foreach (['', 'remember', 'understand', 'apply', 'analyze', 'evaluate', 'create'] as $bloom) {
            foreach (['mcq', 'true_false', 'short_answer'] as $form) {
                $this->assertContains($p->label($bloom, $form, false, 1), ActivityPlanner::LABELS);
            }
        }
    }

    public function test_a_question_on_a_slide_for_another_concept_is_marked_as_connecting_it(): void
    {
        $acts = $this->plan($this->slide(['concept_ids' => [1, 2]]), [$this->q(1, 'mcq', ['concept_id' => 2])]);

        $this->assertSame(2, $acts[0]['connects_concept']);
        $this->assertSame('Think about it', $acts[0]['label']);
    }

    public function test_every_target_the_planner_can_name_is_one_the_runtime_has(): void
    {
        // lib/h5p/question-bank-h5p-map.ts H5pTargetKind. A drift here would ask a player to render a type it lacks.
        $runtime = ['single_choice_set', 'true_false', 'fill_in_the_blanks', 'drag_text', 'mark_the_words', 'memory_game', 'flashcards', 'course_presentation', 'essay', 'drag_drop'];

        $this->assertEqualsCanonicalizing($runtime, ActivityPlanner::TARGETS);
        $this->assertNotContains('branching', ActivityPlanner::TARGETS);
    }
}
