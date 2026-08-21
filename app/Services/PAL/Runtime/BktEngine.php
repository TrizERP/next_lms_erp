<?php

namespace App\Services\PAL\Runtime;

/**
 * Bayesian Knowledge Tracing — the real implementation.
 *
 * Master Blueprint §5 Layer 4. Four parameters per learning unit:
 *
 *   P(L₀)  prior probability the learner already knows it
 *   P(T)   probability an attempt moves unknown → known
 *   P(S)   slip — knows it, answers wrong
 *   P(G)   guess — does not know it, answers right
 *
 * After each response the posterior is computed, then the learning transition
 * is applied:
 *
 *   correct:  P(Lₙ|obs) = P(L)(1−P(S)) / [ P(L)(1−P(S)) + (1−P(L))P(G) ]
 *   wrong:    P(Lₙ|obs) = P(L)P(S)     / [ P(L)P(S)     + (1−P(L))(1−P(G)) ]
 *   then      P(Lₙ₊₁)   = P(Lₙ|obs) + (1 − P(Lₙ|obs))·P(T)
 *
 * The parameters come from Administration (config/pal_architecture.php merged
 * with the institute's overrides), so tuning them there genuinely changes what
 * this engine computes — that is what makes the Mastery Model subsystem live
 * rather than decorative.
 *
 * Two guards the blueprint calls for and this implements:
 *
 *   - `min_attempts_for_mastery` gates the CREDITED band, not the probability.
 *     A learner who answers one lucky question has a high posterior and an
 *     uncredited band, which is the honest reading of one data point.
 *   - P(S) + P(G) ≥ 1 makes the update degenerate (a wrong answer would raise
 *     mastery). The engine clamps rather than producing nonsense.
 */
class BktEngine
{
    /**
     * @param array{p_init:float,p_transit:float,p_slip:float,p_guess:float,min_attempts_for_mastery:int} $params
     */
    public function __construct(
        private readonly array $params
    ) {}

    /** Build from the Administration settings for `mastery-model.bkt`. */
    public static function fromSettings(array $bkt): self
    {
        return new self([
            'p_init' => self::clamp((float) ($bkt['p_init'] ?? 0.15), 0.001, 0.999),
            'p_transit' => self::clamp((float) ($bkt['p_transit'] ?? 0.20), 0.001, 0.999),
            'p_slip' => self::clamp((float) ($bkt['p_slip'] ?? 0.10), 0.001, 0.499),
            'p_guess' => self::clamp((float) ($bkt['p_guess'] ?? 0.25), 0.001, 0.499),
            'min_attempts_for_mastery' => max(1, (int) ($bkt['min_attempts_for_mastery'] ?? 3)),
        ]);
    }

    /**
     * Trace one ordered response sequence.
     *
     * @param array<int, array{correct:bool}> $responses in chronological order
     * @return array{
     *   mastery:float, prior:float, attempts:int, correct:int, wrong:int,
     *   credited:bool, trajectory:array<int,float>, delta:float
     * }
     */
    public function trace(array $responses): array
    {
        $slip = $this->params['p_slip'];
        $guess = $this->params['p_guess'];

        // Degenerate parameterisation: a wrong answer must never increase
        // mastery. Pull them back to a well-formed pair rather than emitting
        // numbers that read plausible and are backwards.
        if ($slip + $guess >= 1.0) {
            $scale = 0.98 / ($slip + $guess);
            $slip *= $scale;
            $guess *= $scale;
        }

        $transit = $this->params['p_transit'];
        $prior = $this->params['p_init'];

        $mastery = $prior;
        $trajectory = [];
        $correct = 0;
        $wrong = 0;

        foreach ($responses as $response) {
            $isCorrect = ! empty($response['correct']);
            $isCorrect ? $correct++ : $wrong++;

            $posterior = $isCorrect
                ? ($mastery * (1 - $slip)) / max(1e-9, $mastery * (1 - $slip) + (1 - $mastery) * $guess)
                : ($mastery * $slip) / max(1e-9, $mastery * $slip + (1 - $mastery) * (1 - $guess));

            $mastery = $posterior + (1 - $posterior) * $transit;
            $mastery = self::clamp($mastery, 0.0, 1.0);

            $trajectory[] = round($mastery, 4);
        }

        $attempts = count($responses);

        return [
            'mastery' => round($mastery, 4),
            'prior' => round($prior, 4),
            'attempts' => $attempts,
            'correct' => $correct,
            'wrong' => $wrong,
            'credited' => $attempts >= $this->params['min_attempts_for_mastery'],
            'trajectory' => $trajectory,
            'delta' => round($mastery - $prior, 4),
        ];
    }

    /**
     * The band a mastery value falls in, from the Administration `bands` table.
     *
     * A per-concept `mastery_gate` (pal_concept_metadata) overrides the band
     * boundary for crediting mastery — a tagged concept sets its own bar.
     *
     * @param array<int, array{key:string,min:float,max:float,tier:string,serves:string,review_interval_days:int}> $bands
     */
    public function band(float $mastery, array $bands, ?float $conceptGate = null): array
    {
        foreach ($bands as $band) {
            $min = (float) ($band['min'] ?? 0);
            $max = (float) ($band['max'] ?? 1);

            if ($mastery >= $min && $mastery <= $max) {
                return [
                    'key' => (string) ($band['key'] ?? ''),
                    'tier' => (string) ($band['tier'] ?? 'stream'),
                    'serves' => (string) ($band['serves'] ?? ''),
                    'review_interval_days' => (int) ($band['review_interval_days'] ?? 1),
                    'meets_concept_gate' => $conceptGate === null ? null : $mastery >= $conceptGate,
                ];
            }
        }

        // Above the top band's max (or the table has a gap) — report the
        // highest band rather than inventing one.
        $last = end($bands) ?: [];

        return [
            'key' => (string) ($last['key'] ?? 'unbanded'),
            'tier' => (string) ($last['tier'] ?? 'stream'),
            'serves' => (string) ($last['serves'] ?? ''),
            'review_interval_days' => (int) ($last['review_interval_days'] ?? 1),
            'meets_concept_gate' => $conceptGate === null ? null : $mastery >= $conceptGate,
        ];
    }

    public function parameters(): array
    {
        return $this->params;
    }

    private static function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }
}
