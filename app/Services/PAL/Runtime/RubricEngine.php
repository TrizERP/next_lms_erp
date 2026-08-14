<?php

namespace App\Services\PAL\Runtime;

/**
 * Stream / Mountain / Sky — the HPC progression rubric, applied for real.
 *
 * Master Blueprint §3. The rubric awards a level across three dimensions, and
 * §3.2 is explicit that a level may only be awarded on OBSERVABLE EVIDENCE.
 * That constraint is what this class is built around, and it is why the output
 * is honest rather than flattering:
 *
 *   AWARENESS is derived. Mastery and fluency are direct evidence of cognition,
 *   so the band a learner reaches maps onto an Awareness level.
 *
 *   SENSITIVITY and CREATIVITY are NOT derived. Their triggers are peer
 *   interaction, self-revision, original solutions and collaboration — none of
 *   which a multiple-choice attempt can evidence. They return `null` with the
 *   reason, rather than a number copied from the Awareness column.
 *
 * Inferring all three from quiz accuracy would produce a complete-looking HPC
 * record built on evidence that does not exist, which is precisely the failure
 * the rubric's trigger rules are written to prevent.
 */
class RubricEngine
{
    public const LEVELS = ['stream', 'mountain', 'sky'];

    /**
     * @param array<int, array{key:string,level:string,signal:string,enabled:bool}> $triggers
     */
    public function __construct(
        private readonly array $triggers = []
    ) {}

    public static function fromSettings(array $triggers): self
    {
        return new self(array_values(array_filter(
            $triggers,
            static fn ($trigger) => is_array($trigger) && ! empty($trigger['enabled'])
        )));
    }

    /**
     * Rate one learning unit from its computed mastery and fluency.
     *
     * @param array{mastery:float, credited:bool, attempts:int, correct:int, wrong:int} $bkt
     * @param array{measured:bool, correct_per_min:?float, error_per_min:?float} $fluency
     * @param array{tier:string} $band
     *
     * @return array{
     *   awareness:?string, sensitivity:?string, creativity:?string,
     *   evidence: array<int,string>, withheld: array<string,string>
     * }
     */
    public function rate(array $bkt, array $fluency, array $band): array
    {
        $evidence = [];
        $awareness = null;

        if (! ($bkt['credited'] ?? false)) {
            // Below the minimum attempts the mastery estimate is not yet
            // creditable, so no level is awarded at all.
            return [
                'awareness' => null,
                'sensitivity' => null,
                'creativity' => null,
                'evidence' => [],
                'withheld' => [
                    'awareness' => sprintf(
                        'Only %d response(s) — below the minimum attempts required before mastery is credited.',
                        (int) ($bkt['attempts'] ?? 0)
                    ),
                    'sensitivity' => self::NO_SOCIAL_EVIDENCE,
                    'creativity' => self::NO_CREATIVE_EVIDENCE,
                ],
            ];
        }

        $tier = strtolower((string) ($band['tier'] ?? 'stream'));
        $awareness = in_array($tier, self::LEVELS, true) ? $tier : 'stream';

        $evidence[] = sprintf(
            'BKT mastery %.2f from %d response(s) (%d correct, %d wrong) → %s band.',
            (float) ($bkt['mastery'] ?? 0),
            (int) ($bkt['attempts'] ?? 0),
            (int) ($bkt['correct'] ?? 0),
            (int) ($bkt['wrong'] ?? 0),
            $awareness
        );

        // §3.2: guessing is a Stream signal regardless of the score it produced.
        if (! empty($fluency['measured'])) {
            $correctRate = (float) ($fluency['correct_per_min'] ?? 0);
            $errorRate = (float) ($fluency['error_per_min'] ?? 0);

            if ($errorRate > 0 && $correctRate < $errorRate * 0.35 && $awareness !== 'stream') {
                $awareness = 'stream';
                $evidence[] = 'Demoted to Stream: high error fluency with low correct fluency is the guessing signal.';
            } else {
                $evidence[] = sprintf(
                    'Fluency measured: %.2f correct/min against %.2f responses/min.',
                    $correctRate,
                    $errorRate
                );
            }
        }

        return [
            'awareness' => $awareness,
            'sensitivity' => null,
            'creativity' => null,
            'evidence' => $evidence,
            'withheld' => [
                'sensitivity' => self::NO_SOCIAL_EVIDENCE,
                'creativity' => self::NO_CREATIVE_EVIDENCE,
            ],
        ];
    }

    /** Distribution of awarded Awareness levels across many units. */
    public function distribution(array $ratings): array
    {
        $out = ['stream' => 0, 'mountain' => 0, 'sky' => 0, 'unrated' => 0];

        foreach ($ratings as $rating) {
            $level = $rating['awareness'] ?? null;
            if ($level !== null && isset($out[$level])) {
                $out[$level]++;
            } else {
                $out['unrated']++;
            }
        }

        return $out;
    }

    /** Which configured triggers a quiz attempt could ever evidence. */
    public function evidenceableTriggers(): array
    {
        $out = ['evidenceable' => [], 'not_evidenceable' => []];

        foreach ($this->triggers as $trigger) {
            $signal = strtolower((string) ($trigger['signal'] ?? ''));
            $isSocialOrCreative = preg_match(
                '/peer|collaborat|empath|conflict|advocat|original|creat|revis|self-determin|instruct/',
                $signal
            ) === 1;

            $bucket = $isSocialOrCreative ? 'not_evidenceable' : 'evidenceable';
            $out[$bucket][] = [
                'key' => (string) ($trigger['key'] ?? ''),
                'level' => (string) ($trigger['level'] ?? ''),
                'signal' => (string) ($trigger['signal'] ?? ''),
            ];
        }

        return $out;
    }

    private const NO_SOCIAL_EVIDENCE =
        'No socio-emotional evidence exists. Sensitivity triggers need peer interaction, collaboration or conflict data, which a quiz attempt cannot produce.';

    private const NO_CREATIVE_EVIDENCE =
        'No creative evidence exists. Creativity triggers need original solutions, self-determined rules or self-revision, which a multiple-choice attempt cannot produce.';
}
