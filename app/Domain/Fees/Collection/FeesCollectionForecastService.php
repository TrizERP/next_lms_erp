<?php

namespace App\Domain\Fees\Collection;

use App\Domain\AI\Signals\SignalStore;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Carbon;

/**
 * A transparent, deterministic trend over a student's own recorded
 * `fees_outstanding_balance` history — not an ML model. Every time
 * FeeArrearsDetector/FeesAgent runs, it leaves a dated score behind in
 * `ai_signals` (SignalStore::store()); this reads that same trail back
 * and asks one question: is the balance trending up, flat, or down, and
 * how fast — rather than computing anything new from the fee ledger
 * itself. Payment-side history (due dates, late-payment counts) is not
 * cleanly exposed by FeesPendingService/FeesArrearsService without
 * reaching into the legacy getBk() controller's undocumented PENDING row
 * shape, so this deliberately reuses a trail the governed agent pipeline
 * already produces, rather than a second, riskier data source.
 *
 * Confidence is capped by how many historical points exist, independent
 * of how steep the trend looks — the same discipline FeesAgent::riskLevel()
 * already applies to severity vs. evidence count. A two-point trend is
 * real data, but it is weak evidence of a sustained pattern, so it can
 * never claim more than MAX_CONFIDENCE.
 *
 * NOTE: `ai_signals` only accumulates a *second* distinct point for the
 * same student once the first one resolves or a new academic year/term
 * starts (SignalStore::findOpen() updates the same open row in place
 * otherwise) — so a freshly registered install will report "not enough
 * history" for every student until the governed sweep has actually run
 * across more than one such boundary. That is the correct, honest answer
 * for a trend capability with no trend to show yet, not a bug.
 */
class FeesCollectionForecastService
{
    private const MIN_HISTORY_POINTS = 2;

    private const CONFIDENCE_FOR_POINTS = [2 => 0.45, 3 => 0.6, 4 => 0.75];

    private const MAX_CONFIDENCE = 0.85;

    public function __construct(private readonly SignalStore $signals)
    {
    }

    public function forecastFor(McpRequestContext $scope, int $studentId): array
    {
        $history = collect($this->signals->historyFor('student', $studentId, $scope, 50))
            ->filter(fn (array $row) => $row['signal_key'] === FeeArrearsDetector::KEY && $row['score'] !== null)
            ->sortBy('detected_at')
            ->values();

        if ($history->count() < self::MIN_HISTORY_POINTS) {
            return [
                'available' => false,
                'reason' => sprintf(
                    'Only %d historical %s sweep(s) are recorded for this student — at least %d are needed to compute a trend.',
                    $history->count(),
                    FeeArrearsDetector::KEY,
                    self::MIN_HISTORY_POINTS
                ),
            ];
        }

        $first = $history->first();
        $last = $history->last();

        $days = max(1, Carbon::parse($first['detected_at'])->diffInDays(Carbon::parse($last['detected_at'])));
        $ratePerDay = ((float) $last['score'] - (float) $first['score']) / $days;

        $trend = match (true) {
            $ratePerDay > 0.01 => 'increasing',
            $ratePerDay < -0.01 => 'decreasing',
            default => 'flat',
        };

        $confidence = self::CONFIDENCE_FOR_POINTS[$history->count()] ?? self::MAX_CONFIDENCE;

        return [
            'available' => true,
            'reason' => null,
            'history_points' => $history->count(),
            'first_detected_at' => $first['detected_at'],
            'last_detected_at' => $last['detected_at'],
            'first_score' => (float) $first['score'],
            'last_score' => (float) $last['score'],
            'trend' => $trend,
            'rate_per_day' => round($ratePerDay, 4),
            'projected_30d_change' => round($ratePerDay * 30, 2),
            'confidence' => $confidence,
        ];
    }
}
