<?php

namespace App\Services\PAL\Adaptive;

use App\Services\PAL\Questions\DifficultyBands;

/**
 * Decides which difficulty to serve next for one learner on one concept.
 *
 * Pure by design: no database, no models, no clock. Everything it reasons from
 * arrives in $signals. That is what lets every branch below be tested directly,
 * and it is why the caller can ask it the same question twice - once to pick
 * the question, once to record what was picked - and get the same answer.
 *
 * ---------------------------------------------------------------------------
 * THE ORDER OF THE RULES IS THE DESIGN
 * ---------------------------------------------------------------------------
 * 1. No diagnostic and no history -> easy. The adaptive screen is reachable
 *    without sitting a diagnostic, and refusing to serve anything would be a
 *    dead end. Starting easy is recoverable within two questions; starting
 *    hard is not.
 *
 * 2. Once there is real practice history, RECENCY BEATS THE DIAGNOSTIC. A
 *    diagnostic is one 15-question snapshot of a whole chapter; five recent
 *    answers on this specific concept are newer and narrower evidence. A
 *    learner who has since understood the idea should not be held at easy by a
 *    score from last week.
 *
 * 3. Otherwise fall back to the diagnostic baseline, adjusted by how this
 *    particular concept went - but only when the diagnostic actually probed
 *    it at least twice. One question is not evidence about a concept.
 *
 * 4. The availability clamp runs LAST, always. Every rule above reasons about
 *    what the learner needs; this one is the only one that knows what the
 *    chapter can supply. Roughly 177 of the 466 reachable concepts do not
 *    carry all three bands, so without this the engine would routinely pick a
 *    band and hand back an empty screen.
 */
class AdaptiveDifficultyRule
{
    /** Consecutive correct answers at one band before moving up. */
    public const ESCALATE_STREAK = 3;

    /** Consecutive wrong answers at one band before dropping down. */
    public const DEESCALATE_STREAK = 2;

    /** Below this many practice rows, the diagnostic still leads. */
    public const HISTORY_THRESHOLD = 3;

    /** Diagnostic items on a concept before its score may override the baseline. */
    public const MIN_CONCEPT_PROBES = 2;

    private const WEAK_CUT = 40.0;
    private const STRONG_CUT = 70.0;

    /**
     * @param  array{
     *     diagnostic_level?: ?string,
     *     baseline_difficulty?: ?string,
     *     concept_diagnostic_pct?: ?float,
     *     concept_diagnostic_served?: int,
     *     last_served?: ?string,
     *     recent?: array<int,bool>,
     *     practice_count?: int,
     *     available?: array<string,int>
     * }  $signals
     * @return array{difficulty: ?string, rule_fired: string, rationale: string}
     */
    public function decide(array $signals): array
    {
        $available = $signals['available'] ?? [];
        $level = $signals['diagnostic_level'] ?? null;
        $practiceCount = (int) ($signals['practice_count'] ?? 0);

        [$band, $rule, $why] = $this->choose($signals, $level, $practiceCount);

        return $this->clamp($band, $rule, $why, $available);
    }

    /**
     * @return array{0: ?string, 1: string, 2: string}
     */
    private function choose(array $signals, ?string $level, int $practiceCount): array
    {
        // 1. Nothing known about this learner at all.
        if ($level === null && $practiceCount === 0) {
            return [
                DifficultyBands::EASY,
                'no_diagnostic_default_easy',
                'No diagnostic and no practice history yet, so practice opens at easy.',
            ];
        }

        // 2. Enough recent evidence on this concept to lead with it.
        $lastServed = $signals['last_served'] ?? null;

        if ($practiceCount >= self::HISTORY_THRESHOLD && DifficultyBands::isBand($lastServed)) {
            $recent = array_values($signals['recent'] ?? []);
            $streak = $this->leadingStreak($recent);

            if ($streak['correct'] >= self::ESCALATE_STREAK) {
                return [
                    DifficultyBands::stepUp($lastServed),
                    'streak3_correct_escalate',
                    sprintf('%d correct in a row at %s, so the next set steps up.', $streak['correct'], $lastServed),
                ];
            }

            if ($streak['wrong'] >= self::DEESCALATE_STREAK) {
                return [
                    DifficultyBands::stepDown($lastServed),
                    'streak2_wrong_deescalate',
                    sprintf('%d wrong in a row at %s, so the next set steps down.', $streak['wrong'], $lastServed),
                ];
            }

            // No rationale on purpose. This branch fires when NOTHING changed -
            // no streak either way - so there is nothing the learner needs
            // told, and narrating the engine holding still ("practice is
            // holding at medium until a streak forms") is meta-commentary
            // about the rule rather than help with the work. `rule_fired`
            // still records the branch, so the audit trail is unchanged.
            return [
                $lastServed,
                'hold_current_band',
                '',
            ];
        }

        // 3. Lead with the diagnostic, adjusted for this concept.
        $baseline = $signals['baseline_difficulty'] ?? $this->baselineFor($level);
        $pct = $signals['concept_diagnostic_pct'] ?? null;
        $served = (int) ($signals['concept_diagnostic_served'] ?? 0);

        if ($pct !== null && $served >= self::MIN_CONCEPT_PROBES) {
            if ($pct < self::WEAK_CUT) {
                return [
                    DifficultyBands::stepDown($baseline),
                    'concept_weak_step_down',
                    sprintf('The diagnostic showed %.0f%% on this concept, below the overall level, so practice starts a band lower.', $pct),
                ];
            }

            if ($pct >= self::STRONG_CUT) {
                return [
                    DifficultyBands::stepUp($baseline),
                    'concept_strong_step_up',
                    sprintf('The diagnostic showed %.0f%% on this concept, above the overall level, so practice starts a band higher.', $pct),
                ];
            }

            return [
                $baseline,
                'concept_matches_baseline',
                sprintf('This concept tracked the overall diagnostic level (%s).', $level ?? 'unknown'),
            ];
        }

        return [
            $baseline,
            'baseline_from_level',
            sprintf('Starting from the overall diagnostic level (%s); this concept was not probed enough to adjust.', $level ?? 'unknown'),
        ];
    }

    /**
     * Walk the newest-first run and report how long the leading streak is.
     *
     * Only ONE of the two can be non-zero - the run ends at the first
     * disagreement - which is what makes the two checks above unambiguous.
     *
     * @param  array<int,bool>  $recent  newest first
     * @return array{correct:int, wrong:int}
     */
    private function leadingStreak(array $recent): array
    {
        $correct = $wrong = 0;

        foreach ($recent as $isCorrect) {
            if ($isCorrect) {
                if ($wrong > 0) {
                    break;
                }
                $correct++;
            } else {
                if ($correct > 0) {
                    break;
                }
                $wrong++;
            }
        }

        return ['correct' => $correct, 'wrong' => $wrong];
    }

    private function baselineFor(?string $level): string
    {
        return match ($level) {
            'beginner' => DifficultyBands::EASY,
            'developing' => DifficultyBands::MEDIUM,
            'proficient', 'advanced' => DifficultyBands::HARD,
            default => DifficultyBands::EASY,
        };
    }

    /**
     * Hold the decision to what the chapter can actually supply.
     *
     * The chosen band is kept whenever it has stock. Otherwise the nearest
     * stocked band wins, preferring easier - being under-stretched costs a
     * learner some time, being over-stretched costs them the answer. The
     * clamp is appended to rule_fired rather than replacing it, so the audit
     * trail still shows what the engine wanted before the bank overruled it.
     *
     * @param  array<string,int>  $available
     * @return array{difficulty: ?string, rule_fired: string, rationale: string}
     */
    private function clamp(?string $band, string $rule, string $why, array $available): array
    {
        if ($available === []) {
            return ['difficulty' => $band, 'rule_fired' => $rule, 'rationale' => $why];
        }

        if ($band !== null && ($available[$band] ?? 0) > 0) {
            return ['difficulty' => $band, 'rule_fired' => $rule, 'rationale' => $why];
        }

        $fallback = DifficultyBands::nearestAvailable($band ?? DifficultyBands::EASY, $available);

        if ($fallback === null) {
            return [
                'difficulty' => null,
                'rule_fired' => 'no_items',
                'rationale' => 'This concept has no practice questions left.',
            ];
        }

        return [
            'difficulty' => $fallback,
            'rule_fired' => $rule . '|clamp:' . $fallback,
            // trim() because $why is legitimately empty for hold_current_band -
            // without it the clamp sentence would arrive with a leading space.
            'rationale' => trim($why . ' ' . sprintf('No %s questions are available here, so %s was served instead.', $band, $fallback)),
        ];
    }
}
