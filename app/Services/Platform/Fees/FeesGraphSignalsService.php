<?php

namespace App\Services\Platform\Fees;

use App\Domain\KnowledgeGraph\Fees\FeesGraphRecommendationEngine;
use App\Domain\KnowledgeGraph\KnowledgeGraphSettingsResolver;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Cross-links the Fees Intelligence banner to the *existing*, already-real
 * Neo4j surface (FeesGraphRecommendationEngine, `FeeRecord`/`FeePayment`
 * data that is genuinely kept in sync) rather than inventing new graph
 * entities for family/concession/TC data that was never synced — see
 * FeesIntelligenceService's own docblock for why sibling detection in
 * particular stays SQL.
 *
 * Only three of that engine's five cards are surfaced here: platform
 * coverage, schedule integrity and the non-payment "root blocker" — the
 * three that speak to arrears/escalation context. `unlinked_payments` and
 * `academic_correlation` are left for the Recommendation panel itself; this
 * banner is a cross-link, not a second copy of that screen.
 */
class FeesGraphSignalsService
{
    private const CACHE_MINUTES = 15;

    private const WANTED_CARD_IDS = [
        'fees.platform_coverage',
        'fees.schedule_integrity',
        'fees.root_blocker',
    ];

    public function __construct(
        private readonly FeesGraphRecommendationEngine $engine,
        private readonly KnowledgeGraphSettingsResolver $settings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function signals(int $tenantId, ?string $syear): array
    {
        if ($syear === null) {
            return ['available' => false, 'reason' => 'No academic year resolved for this institute.'];
        }

        if (! $this->settings->isEnabledFor('fees', $tenantId)) {
            return ['available' => false, 'reason' => 'Knowledge Graph is switched off for Fees in this school.'];
        }

        return Cache::remember(
            "platform.fees_intelligence.v2.{$tenantId}.graph.{$syear}",
            now()->addMinutes(self::CACHE_MINUTES),
            fn () => $this->compute($tenantId, $syear)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function compute(int $tenantId, string $syear): array
    {
        try {
            $cards = $this->engine->cardsFor($tenantId, $syear);
        } catch (Throwable) {
            return ['available' => false, 'reason' => 'The Knowledge Graph could not be reached right now.'];
        }

        $wanted = [];
        foreach ($cards as $card) {
            if (in_array($card['id'] ?? null, self::WANTED_CARD_IDS, true)) {
                $wanted[$card['id']] = [
                    'title' => $card['title'],
                    'severity' => $card['severity'],
                    'available' => $card['available'],
                    'reason' => $card['reason'],
                    'description' => $card['evidence']['description'] ?? null,
                ];
            }
        }

        return [
            'available' => true,
            'basis' => 'Read-only cross-link to the Fees Recommendation panel\'s own graph engine — nothing here is written to Neo4j, and this does not replace or recompute arrears.',
            'cards' => $wanted,
        ];
    }
}
