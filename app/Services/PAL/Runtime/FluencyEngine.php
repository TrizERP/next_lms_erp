<?php

namespace App\Services\PAL\Runtime;

/**
 * Fluency — accuracy × speed, the blueprint's primary mastery index.
 *
 * Master Blueprint §4.3. A learner who scores 8/10 in two minutes is
 * demonstrably different from one who scores 8/10 in twelve, and score alone
 * cannot tell them apart:
 *
 *   correct fluency = correct responses ÷ time (seconds)
 *   error fluency   = total responses   ÷ time (seconds)
 *   net fluency     = correct fluency − (error fluency × weight)
 *   fluency delta   = net fluency − rolling average
 *
 * Rates are reported PER MINUTE rather than per second. Per second every real
 * value rounds to 0.0x and the interpretation table below becomes unreadable;
 * the ratio the blueprint defines is unchanged by the scale factor.
 *
 * Time is measured from the response timestamps in the evidence, because
 * `lms_online_exam.avg_time` is null on older PAL attempts. When a unit has
 * fewer than two timestamped responses there is no elapsed time to divide by,
 * and every rate is returned as null — never zero, which would read as
 * "infinitely slow" instead of "not measured".
 */
class FluencyEngine
{
    public function __construct(
        private readonly float $errorWeight = 0.5
    ) {}

    public static function fromSettings(array $fluency): self
    {
        return new self(max(0.0, min(1.0, (float) ($fluency['error_weight'] ?? 0.5))));
    }

    /**
     * Fluency across a learner's whole history for one unit.
     *
     * Measured PER SESSION and then averaged, never across the full span. A
     * learner who answered 54 questions over four months has an elapsed time of
     * months; dividing by that yields a rate of ~0.00/min for everyone and makes
     * the metric useless. Speed is a within-session property, so each attempt
     * (`exam_id`) is measured on its own and the session rates are averaged,
     * weighted by the responses in each.
     *
     * @param array<int, array{correct:bool, at:string, exam_id?:int}> $responses chronological
     */
    public function measureAcrossSessions(array $responses): array
    {
        $sessions = [];
        foreach ($responses as $response) {
            $sessions[(string) ($response['exam_id'] ?? 'single')][] = $response;
        }

        $measured = [];
        foreach ($sessions as $session) {
            $result = $this->measure($session);
            if ($result['measured']) {
                $measured[] = ['result' => $result, 'weight' => count($session)];
            }
        }

        if ($measured === []) {
            return [
                'correct_per_min' => null,
                'error_per_min' => null,
                'net' => null,
                'elapsed_seconds' => null,
                'measured' => false,
                'sessions' => count($sessions),
                'measured_sessions' => 0,
                'interpretation' => count($sessions) === 1
                    ? 'A single attempt with no elapsed time — speed was not recorded.'
                    : 'No attempt recorded enough timing to measure speed.',
            ];
        }

        $totalWeight = array_sum(array_column($measured, 'weight'));
        $correct = 0.0;
        $error = 0.0;
        $elapsed = 0;
        $accuracyCorrect = 0;
        $accuracyTotal = 0;

        foreach ($measured as $entry) {
            $weight = $entry['weight'] / $totalWeight;
            $correct += (float) $entry['result']['correct_per_min'] * $weight;
            $error += (float) $entry['result']['error_per_min'] * $weight;
            $elapsed += (int) $entry['result']['elapsed_seconds'];
        }

        foreach ($responses as $response) {
            $accuracyTotal++;
            if (! empty($response['correct'])) {
                $accuracyCorrect++;
            }
        }

        return [
            'correct_per_min' => round($correct, 3),
            'error_per_min' => round($error, 3),
            'net' => round($correct - ($error * $this->errorWeight), 3),
            'elapsed_seconds' => $elapsed,
            'measured' => true,
            'sessions' => count($sessions),
            'measured_sessions' => count($measured),
            'interpretation' => $this->interpret(
                $correct,
                $error,
                $accuracyTotal === 0 ? 0 : $accuracyCorrect / $accuracyTotal
            ),
        ];
    }

    /**
     * One session's fluency.
     *
     * @param array<int, array{correct:bool, at:string}> $responses chronological
     * @return array{
     *   correct_per_min:?float, error_per_min:?float, net:?float,
     *   elapsed_seconds:?int, measured:bool, interpretation:string
     * }
     */
    public function measure(array $responses): array
    {
        $total = count($responses);
        $correct = 0;
        $timestamps = [];

        foreach ($responses as $response) {
            if (! empty($response['correct'])) {
                $correct++;
            }
            $at = strtotime((string) ($response['at'] ?? ''));
            if ($at !== false && $at > 0) {
                $timestamps[] = $at;
            }
        }

        if (count($timestamps) < 2) {
            return [
                'correct_per_min' => null,
                'error_per_min' => null,
                'net' => null,
                'elapsed_seconds' => null,
                'measured' => false,
                'interpretation' => 'Not enough timestamped responses to measure speed.',
            ];
        }

        $elapsed = max($timestamps) - min($timestamps);

        // Every response landed in the same second — a bulk-scored submission,
        // not a measured session. Reporting a rate here would be fiction.
        if ($elapsed <= 0) {
            return [
                'correct_per_min' => null,
                'error_per_min' => null,
                'net' => null,
                'elapsed_seconds' => 0,
                'measured' => false,
                'interpretation' => 'All responses share one timestamp, so no elapsed time was recorded.',
            ];
        }

        $minutes = $elapsed / 60;
        $correctRate = $correct / $minutes;
        $errorRate = $total / $minutes;
        $net = $correctRate - ($errorRate * $this->errorWeight);

        return [
            'correct_per_min' => round($correctRate, 3),
            'error_per_min' => round($errorRate, 3),
            'net' => round($net, 3),
            'elapsed_seconds' => $elapsed,
            'measured' => true,
            'interpretation' => $this->interpret($correctRate, $errorRate, $total === 0 ? 0 : $correct / $total),
        ];
    }

    /** The blueprint's interpretation guide, applied to measured rates. */
    private function interpret(float $correctRate, float $errorRate, float $accuracy): string
    {
        if ($accuracy >= 0.85 && $correctRate > 0) {
            return 'High correct fluency — mastery with automaticity.';
        }

        if ($accuracy < 0.4 && $errorRate > $correctRate * 2) {
            return 'High error fluency with low correct fluency — guessing or an active misconception. Trigger.';
        }

        if ($accuracy >= 0.4 && $accuracy < 0.7) {
            return 'Partial fluency — the concept is forming but not automatic.';
        }

        return 'Low throughput — deliberate work rather than recall.';
    }

    public function errorWeight(): float
    {
        return $this->errorWeight;
    }
}
