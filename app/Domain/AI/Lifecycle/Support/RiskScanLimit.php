<?php

namespace App\Domain\AI\Lifecycle\Support;

/** Extracts the requested result count without changing the agent's data scope. */
final class RiskScanLimit
{
    private const DEFAULT = 10;

    public static function fromQuestion(string $question, int $available): int
    {
        if ($available <= 0) {
            return 0;
        }

        if (preg_match('/\btop\s+(\d{1,2})\b/i', $question, $matches)) {
            return min(max((int) $matches[1], 1), $available);
        }

        return min(self::DEFAULT, $available);
    }
}
