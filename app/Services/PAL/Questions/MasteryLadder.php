<?php

namespace App\Services\PAL\Questions;

/**
 * Decides when a concept has been carried far enough up the Easy -> Medium ->
 * Hard ladder to count as mastered.
 *
 * ---------------------------------------------------------------------------
 * WHY THE LADDER IS MEASURED AGAINST STOCK, NOT AGAINST ALL THREE BANDS
 * ---------------------------------------------------------------------------
 * The brief is "Easy -> Medium -> Hard -> Mastery". Applied literally that
 * would require every concept to own questions in all three bands.
 *
 * Measured on vivek_erp, 2026-09-17, over the 401 concepts that have any
 * concept-tagged servable MCQ:
 *
 *   all three bands present ......... 121
 *   exactly two bands ............... 122
 *   exactly one band ................ 158
 *
 * So a literal three-band gate would leave roughly 70% of concepts permanently
 * short of mastery - not because the learner failed, but because the questions
 * to prove it with do not exist. The learner would climb to the top of the
 * available stock and then sit there for ever, with no action that could ever
 * clear the gate. That is a worse lie than the one it is trying to avoid.
 *
 * So the ladder runs over the bands that ACTUALLY HAVE STOCK for this concept.
 * A concept holding only easy items can be mastered on easy evidence - and
 * `bands_unavailable` records that it was, so the UI can say "mastered on easy
 * (no medium or hard questions exist for this concept)" instead of implying a
 * depth of evidence that was never gathered.
 *
 * This mirrors DifficultyBands::nearestAvailable(), which already refuses to
 * serve a band with no stock rather than showing the learner a blank screen.
 *
 * ---------------------------------------------------------------------------
 * WHAT COUNTS AS CLEARING A BAND
 * ---------------------------------------------------------------------------
 * Both a minimum number of attempts and a minimum accuracy, because either
 * alone is gameable: one lucky answer clears an accuracy-only gate, and a long
 * run of wrong answers clears an attempts-only one.
 *
 * Defaults come from config/pal_diagnostic.php and fall back to the values
 * AdaptiveLearningService has always used (5 attempts at 80%), so turning this
 * class on does not silently move the bar for anyone already practising.
 */
final class MasteryLadder
{
    /** @var array<string,int>|null */
    private ?array $config = null;

    /** Attempts needed in a band before its accuracy means anything. */
    public function minAttempts(): int
    {
        return (int) ($this->config()['min_attempts'] ?? 5);
    }

    /** Accuracy (percent) needed to clear a band. */
    public function minAccuracy(): float
    {
        return (float) ($this->config()['min_accuracy'] ?? 80.0);
    }

    /**
     * Bands this concept can actually be tested on, easiest first.
     *
     * @param  array<string,int>  $availability  band => count, from McqPool
     * @return array<int,string>
     */
    public function requiredBands(array $availability): array
    {
        $bands = [];

        foreach (DifficultyBands::BANDS as $band) {
            if ((int) ($availability[$band] ?? 0) > 0) {
                $bands[] = $band;
            }
        }

        return $bands;
    }

    /**
     * How many attempts actually clear a band, given what is on the shelf.
     *
     * ---------------------------------------------------------------------
     * WHY THIS IS CAPPED AT THE STOCK
     * ---------------------------------------------------------------------
     * pal_adaptive_response is upserted on (student, concept, question), so a
     * learner's attempt count in a band can never exceed the number of
     * DISTINCT questions that band holds. Demanding a flat 5 attempts from a
     * band that owns 1 question is therefore not a high bar, it is an
     * impossible one - the learner would answer everything there is, correctly,
     * and still be told they had not cleared it.
     *
     * Observed on concept 2298 ("Calculations with brackets"): easy 1,
     * medium 4, hard 1. Under a flat 5 only the medium band was even
     * theoretically clearable, and the concept could never be mastered.
     *
     * So the bar is min(configured, stock). The accuracy bar is untouched -
     * thin evidence still has to be RIGHT - and `bands_thin` records every band
     * graded on fewer than the configured number so the UI never implies more
     * evidence than was gathered.
     */
    public function attemptsNeeded(string $band, array $availability): int
    {
        $stock = (int) ($availability[$band] ?? 0);

        return max(1, min($this->minAttempts(), $stock));
    }

    /**
     * Grade one concept's practice history against the ladder.
     *
     * @param  array<string,int>  $availability  band => count of servable MCQs
     * @param  array<string,array{attempted:int,correct:int,accuracy:float}>  $byDifficulty  from AdaptiveLearningService::progress()
     * @return array{
     *     mastered: bool,
     *     bands_required: array<int,string>,
     *     bands_cleared: array<int,string>,
     *     bands_unavailable: array<int,string>,
     *     bands_thin: array<int,string>,
     *     next_band: ?string,
     *     progress_pct: float,
     *     reason: string
     * }
     */
    public function evaluate(array $availability, array $byDifficulty): array
    {
        $required = $this->requiredBands($availability);
        $unavailable = array_values(array_diff(DifficultyBands::BANDS, $required));

        // Bands that exist but hold less than a full run of questions. Graded
        // on what they have, and reported so the claim stays honest.
        $thin = array_values(array_filter(
            $required,
            fn ($band) => (int) ($availability[$band] ?? 0) < $this->minAttempts()
        ));

        // No stock at all. Not mastered, but not the learner's failure either -
        // there is nothing here that could ever be answered.
        if ($required === []) {
            return [
                'mastered' => false,
                'bands_required' => [],
                'bands_cleared' => [],
                'bands_unavailable' => $unavailable,
                'bands_thin' => [],
                'next_band' => null,
                'progress_pct' => 0.0,
                'reason' => 'This concept has no practice questions yet, so mastery cannot be measured.',
            ];
        }

        $cleared = [];
        $next = null;

        foreach ($required as $band) {
            if ($this->bandCleared($byDifficulty[$band] ?? null, $this->attemptsNeeded($band, $availability))) {
                $cleared[] = $band;

                continue;
            }

            // The ladder is ordered, so the first band not yet cleared is the
            // one to work on. Later bands are not reported as "next" even if
            // they happen to have evidence already.
            $next ??= $band;
        }

        $mastered = count($cleared) === count($required);
        $progress = count($required) > 0 ? round(count($cleared) / count($required) * 100, 2) : 0.0;

        return [
            'mastered' => $mastered,
            'bands_required' => $required,
            'bands_cleared' => $cleared,
            'bands_unavailable' => $unavailable,
            'bands_thin' => $thin,
            'next_band' => $mastered ? null : $next,
            'progress_pct' => $progress,
            'reason' => $this->explain($mastered, $required, $cleared, $unavailable, $thin, $next, $availability),
        ];
    }

    /** @param array{attempted:int,correct:int,accuracy:float}|null $stats */
    private function bandCleared(?array $stats, int $attemptsNeeded): bool
    {
        if ($stats === null) {
            return false;
        }

        return (int) ($stats['attempted'] ?? 0) >= $attemptsNeeded
            && (float) ($stats['accuracy'] ?? 0) >= $this->minAccuracy();
    }

    /**
     * A sentence a learner can act on, not a status code.
     *
     * @param  array<int,string>  $required
     * @param  array<int,string>  $cleared
     * @param  array<int,string>  $unavailable
     * @param  array<int,string>  $thin
     * @param  array<string,int>  $availability
     */
    private function explain(
        bool $mastered,
        array $required,
        array $cleared,
        array $unavailable,
        array $thin,
        ?string $next,
        array $availability
    ): string {
        if ($mastered) {
            $on = $this->list($cleared);

            // Saying "mastered" without these caveats would overstate the
            // evidence on the ~70% of concepts that cannot fill three bands.
            $caveats = [];

            if ($unavailable !== []) {
                // "questions" stays plural either way: "No hard questions
                // exist" and "No medium or hard questions exist" are both
                // correct, whereas agreeing with the band count is not.
                $caveats[] = sprintf(
                    'No %s questions exist for this concept yet.',
                    $this->list($unavailable, 'or')
                );
            }

            if ($thin !== []) {
                $caveats[] = sprintf(
                    'Judged on every %s question available, which is fewer than the usual %d.',
                    $this->list($thin),
                    $this->minAttempts()
                );
            }

            return trim(sprintf('Mastered on %s. %s', $on, implode(' ', $caveats)));
        }

        $band = $next ?? DifficultyBands::EASY;

        return sprintf(
            'Cleared %d of %d level%s. Next: %s, which needs %d question%s at %d%% or better.',
            count($cleared),
            count($required),
            count($required) === 1 ? '' : 's',
            $band,
            $this->attemptsNeeded($band, $availability),
            $this->attemptsNeeded($band, $availability) === 1 ? '' : 's',
            (int) $this->minAccuracy()
        );
    }

    /**
     * "easy", "easy and medium", "easy, medium and hard" - this text is read by
     * a learner, so it has to be a sentence rather than a joined array.
     *
     * @param  array<int,string>  $items
     */
    private function list(array $items, string $conjunction = 'and'): string
    {
        $items = array_values($items);

        if (count($items) <= 1) {
            return $items[0] ?? '';
        }

        $last = array_pop($items);

        return implode(', ', $items) . ' ' . $conjunction . ' ' . $last;
    }

    /** @return array<string,mixed> */
    private function config(): array
    {
        return $this->config ??= (array) config('pal_diagnostic.mastery', []);
    }
}
