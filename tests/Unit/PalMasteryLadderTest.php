<?php

namespace Tests\Unit;

use App\Services\PAL\Questions\MasteryLadder;
use Tests\TestCase;

/**
 * The Easy -> Medium -> Hard mastery gate.
 *
 * The cases that matter here are the thin ones. On this estate only 121 of the
 * 401 concepts holding tagged MCQs own questions in all three bands, so most of
 * these assertions are about the majority case, not an edge case.
 */
class PalMasteryLadderTest extends TestCase
{
    private function ladder(): MasteryLadder
    {
        config()->set('pal_diagnostic.mastery', ['min_attempts' => 5, 'min_accuracy' => 80.0]);

        return new MasteryLadder();
    }

    /** @return array<string,array{attempted:int,correct:int,accuracy:float}> */
    private function practice(array $spec): array
    {
        $out = [];

        foreach ($spec as $band => [$attempted, $correct]) {
            $out[$band] = [
                'attempted' => $attempted,
                'correct' => $correct,
                'accuracy' => $attempted > 0 ? round($correct / $attempted * 100, 2) : 0.0,
            ];
        }

        return $out;
    }

    public function test_the_ladder_only_requires_bands_that_have_questions(): void
    {
        $result = $this->ladder()->evaluate(
            ['easy' => 12, 'medium' => 0, 'hard' => 0],
            $this->practice(['easy' => [5, 5]])
        );

        $this->assertTrue($result['mastered']);
        $this->assertSame(['easy'], $result['bands_required']);
        $this->assertSame(['medium', 'hard'], $result['bands_unavailable']);
    }

    public function test_mastery_on_a_partial_ladder_says_so(): void
    {
        $result = $this->ladder()->evaluate(
            ['easy' => 12, 'medium' => 9, 'hard' => 0],
            $this->practice(['easy' => [5, 5], 'medium' => [5, 5]])
        );

        $this->assertTrue($result['mastered']);
        $this->assertStringContainsString('Mastered on easy and medium', $result['reason']);
        $this->assertStringContainsString('No hard questions exist', $result['reason']);
    }

    /**
     * The defect this class was written to close.
     *
     * pal_adaptive_response upserts on (student, concept, question), so a band
     * holding one question can never yield five attempts. A flat bar would make
     * such a concept permanently unmasterable however well the learner did.
     */
    public function test_a_band_with_less_stock_than_the_bar_is_still_clearable(): void
    {
        $ladder = $this->ladder();

        $this->assertSame(1, $ladder->attemptsNeeded('easy', ['easy' => 1]));
        $this->assertSame(4, $ladder->attemptsNeeded('medium', ['medium' => 4]));
        $this->assertSame(5, $ladder->attemptsNeeded('hard', ['hard' => 40]));

        // The real shape of concept 2298, "Calculations with brackets".
        $result = $ladder->evaluate(
            ['easy' => 1, 'medium' => 4, 'hard' => 1],
            $this->practice(['easy' => [1, 1], 'medium' => [4, 4], 'hard' => [1, 1]])
        );

        $this->assertTrue($result['mastered']);
        $this->assertSame(['easy', 'medium', 'hard'], $result['bands_thin']);
        $this->assertStringContainsString('fewer than the usual 5', $result['reason']);
    }

    public function test_thin_stock_still_has_to_be_answered_correctly(): void
    {
        $result = $this->ladder()->evaluate(
            ['easy' => 1, 'medium' => 4, 'hard' => 1],
            $this->practice(['easy' => [1, 0]])
        );

        $this->assertFalse($result['mastered']);
        $this->assertSame('easy', $result['next_band']);
        $this->assertSame([], $result['bands_cleared']);
    }

    public function test_the_next_band_is_the_first_uncleared_one_in_order(): void
    {
        $result = $this->ladder()->evaluate(
            ['easy' => 20, 'medium' => 20, 'hard' => 20],
            // Hard happens to be cleared, easy is not: the ladder is ordered,
            // so it still sends the learner back to medium.
            $this->practice(['easy' => [5, 5], 'medium' => [5, 2], 'hard' => [5, 5]])
        );

        $this->assertFalse($result['mastered']);
        $this->assertSame('medium', $result['next_band']);
        $this->assertSame(['easy', 'hard'], $result['bands_cleared']);
        $this->assertEqualsWithDelta(66.67, $result['progress_pct'], 0.01);
    }

    public function test_accuracy_alone_does_not_clear_a_band(): void
    {
        $result = $this->ladder()->evaluate(
            ['easy' => 20],
            $this->practice(['easy' => [2, 2]]) // 100%, but only two of twenty
        );

        $this->assertFalse($result['mastered']);
        $this->assertStringContainsString('needs 5 questions', $result['reason']);
    }

    public function test_a_concept_with_no_questions_is_not_mastered_and_says_why(): void
    {
        $result = $this->ladder()->evaluate(['easy' => 0, 'medium' => 0, 'hard' => 0], []);

        $this->assertFalse($result['mastered']);
        $this->assertSame([], $result['bands_required']);
        $this->assertNull($result['next_band']);
        $this->assertStringContainsString('no practice questions yet', $result['reason']);
    }

    public function test_a_full_ladder_reports_no_caveat(): void
    {
        $result = $this->ladder()->evaluate(
            ['easy' => 20, 'medium' => 20, 'hard' => 20],
            $this->practice(['easy' => [5, 5], 'medium' => [5, 4], 'hard' => [6, 5]])
        );

        $this->assertTrue($result['mastered']);
        $this->assertSame([], $result['bands_thin']);
        $this->assertSame([], $result['bands_unavailable']);
        $this->assertSame('Mastered on easy, medium and hard.', $result['reason']);
    }
}
