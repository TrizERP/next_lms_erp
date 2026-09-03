<?php

namespace App\CareerIntelligence;

/**
 * Presentation-layer interpretation of a matchPercentage already calculated
 * by KnowledgeMatchService — this class computes NOTHING new, it only labels
 * an existing number. Thresholds come entirely from
 * config/career_recommendation.php; no occupation, band label wording, or
 * cutoff is hardcoded anywhere else.
 */
class AlignmentBandClassifier
{
    public const STRONG = 'Strong Match';
    public const PARTIAL = 'Partial Match';
    public const WEAK = 'Weak Match';

    /**
     * @param float $matchPercentage 0-100
     * @return array{band: string, strong_threshold: float, partial_threshold: float}
     */
    public function classify(float $matchPercentage): array
    {
        $strongThreshold = (float) config('career_recommendation.strong_match_threshold') * 100;
        $partialThreshold = (float) config('career_recommendation.partial_match_threshold') * 100;

        $band = match (true) {
            $matchPercentage >= $strongThreshold => self::STRONG,
            $matchPercentage >= $partialThreshold => self::PARTIAL,
            default => self::WEAK,
        };

        return [
            'band' => $band,
            'strong_threshold' => $strongThreshold,
            'partial_threshold' => $partialThreshold,
        ];
    }
}
