<?php

namespace App\Domain\KnowledgeGraph\Fees;

/**
 * Turns the Fees graph into the Recommendation panel's "Graph Reasoning"
 * section — relationships discovered by walking/aggregating the graph,
 * never ones stated as a single direct edge.
 *
 * Three of its four cards are re-tagged, not reimplemented:
 * FeesGraphRecommendationEngine's own schedule_integrity/unlinked_payments/
 * root_blocker cards are already multi-row reasoning over FeeRecord/
 * FeePayment, not single-fact lookups, so this engine pulls them from
 * there verbatim (same honesty-ladder shape, same thresholds) rather than
 * duplicating their logic. Only the transitive non-payment chain below is
 * genuinely new: it chains two already-scoped, property-indexed aggregate
 * queries in PHP — standardNonPaymentConcentration() and
 * standardsSharingFeeType() — rather than a fresh multi-hop Cypher MATCH,
 * per FeesGraphRecommendationRepository's own documented perf lesson.
 */
class FeesGraphReasoningEngine
{
    private const TRANSITIVE_CHAIN_ID = 'fees.transitive_nonpayment_chain';

    private const REUSED_CARD_IDS = [
        'fees.schedule_integrity',
        'fees.unlinked_payments',
        'fees.root_blocker',
    ];

    /**
     * Each standard on either side of a cross-standard non-payment chain
     * must independently have at least this many enrolled students before
     * it counts toward the chain — chaining two noisy aggregates together
     * must not manufacture a confident-sounding claim that neither side
     * could support alone.
     */
    private const MIN_CHAIN_LINK_SAMPLE = 30;

    /** Mirrors FeesGraphRecommendationEngine's own non-payment concentration bar. */
    private const NON_PAYMENT_RATIO_THRESHOLD = 0.5;

    public function __construct(
        private readonly FeesGraphRecommendationEngine $recommendationEngine,
        private readonly FeesGraphRecommendationRepository $repository
    ) {
    }

    public function reasoningFor(int $tenant, string $syear): array
    {
        $existingCards = $this->recommendationEngine->cardsFor($tenant, $syear);

        $reusedCards = array_values(array_map(
            fn (array $card) => [...$card, 'category' => 'graph_reasoning'],
            array_filter($existingCards, fn (array $card) => in_array($card['id'], self::REUSED_CARD_IDS, true))
        ));

        return [...$reusedCards, $this->transitiveChainCard($tenant, $syear)];
    }

    private function transitiveChainCard(int $tenant, string $syear): array
    {
        $coverage = $this->repository->platformCoverage($tenant, $syear);

        // Same platform-wide floor FeesGraphRecommendationEngine::rootBlockerCard()
        // already applies to this exact concentration data — this card asserts a
        // stronger (cross-standard) claim on top of it, so it cannot use a looser
        // gate than the card it's chained from without contradicting it.
        if ($coverage['payments'] < self::MIN_CHAIN_LINK_SAMPLE) {
            return $this->unavailable(sprintf(
                'Only %d payment(s) are projected into Neo4j for %s against %d enrolled student(s) — too thin a sample to chain non-payment concentration across standards.',
                $coverage['payments'],
                $syear,
                $coverage['enrolled_students']
            ));
        }

        $concentration = $this->repository->standardNonPaymentConcentration($tenant, $syear);
        $concentrated = $this->concentratedStandardIds($concentration['standards']);

        if (count($concentrated) < 2) {
            return [
                'id' => self::TRANSITIVE_CHAIN_ID,
                'title' => 'Cross-standard non-payment chain',
                'category' => 'graph_reasoning',
                'severity' => 'positive',
                'available' => true,
                'reason' => null,
                'evidence' => [
                    'description' => sprintf(
                        'Fewer than two standards independently clear both the %d-student sample floor and the %d%% non-payment threshold for %s — no chain to report.',
                        self::MIN_CHAIN_LINK_SAMPLE,
                        (int) (self::NON_PAYMENT_RATIO_THRESHOLD * 100),
                        $syear
                    ),
                ],
            ];
        }

        $standardsByFeeType = $this->repository->standardsSharingFeeType($tenant, $syear);

        // Grouped by standard-PAIR, not by fee-type: a chain between two
        // standards sharing a dozen fee heads is one finding ("treat these
        // two as a unit"), not a dozen near-identical rows repeating the
        // same pair once per shared fee head.
        $pairs = [];
        foreach ($standardsByFeeType as $feeTypeId => $standardIds) {
            $linked = array_values(array_unique(array_intersect($standardIds, $concentrated)));
            sort($linked);

            for ($i = 0; $i < count($linked); $i++) {
                for ($j = $i + 1; $j < count($linked); $j++) {
                    $key = $linked[$i].'-'.$linked[$j];
                    $pairs[$key]['standard_ids'] ??= [$linked[$i], $linked[$j]];
                    $pairs[$key]['fee_type_ids'][] = $feeTypeId;
                }
            }
        }

        $chains = array_values(array_map(fn (array $pair) => [
            'standard_ids' => $pair['standard_ids'],
            'shared_fee_type_ids' => array_values(array_unique($pair['fee_type_ids'])),
            'shared_fee_type_count' => count(array_unique($pair['fee_type_ids'])),
        ], $pairs));

        usort($chains, fn (array $a, array $b) => $b['shared_fee_type_count'] <=> $a['shared_fee_type_count']);

        if ($chains === []) {
            return [
                'id' => self::TRANSITIVE_CHAIN_ID,
                'title' => 'Cross-standard non-payment chain',
                'category' => 'graph_reasoning',
                'severity' => 'positive',
                'available' => true,
                'reason' => null,
                'evidence' => [
                    'description' => sprintf(
                        '%d standard(s) are individually non-payment-concentrated for %s, but none share a fee head with another concentrated standard — no transitive chain to report.',
                        count($concentrated),
                        $syear
                    ),
                ],
            ];
        }

        return [
            'id' => self::TRANSITIVE_CHAIN_ID,
            'title' => 'Cross-standard non-payment chain',
            'category' => 'graph_reasoning',
            'severity' => 'high',
            'available' => true,
            'reason' => null,
            'evidence' => [
                'why' => sprintf(
                    '%d standard(s) are individually non-payment-concentrated for %s and share a fee head (FeeRecord.fee_type_id) with at least one other concentrated standard — this relationship is not a direct edge, it is inferred by grouping standards on a shared fee-type property.',
                    count($concentrated),
                    $syear
                ),
                'expected_impact' => 'A reminder run targeted at only one standard in a chain is likely to miss the knock-on non-payment in the standard(s) it shares a fee head with — plan the run across the whole chain.',
                'chains' => $chains,
            ],
        ];
    }

    /** @return array<int, int> */
    private function concentratedStandardIds(array $standards): array
    {
        $qualifying = array_filter($standards, function (array $s) {
            if ($s['enrolled'] < self::MIN_CHAIN_LINK_SAMPLE || $s['non_paying'] <= 0) {
                return false;
            }

            return ($s['non_paying'] / $s['enrolled']) >= self::NON_PAYMENT_RATIO_THRESHOLD;
        });

        return array_values(array_map(fn (array $s) => $s['standard_id'], $qualifying));
    }

    private function unavailable(string $reason): array
    {
        return [
            'id' => self::TRANSITIVE_CHAIN_ID,
            'title' => 'Cross-standard non-payment chain',
            'category' => 'graph_reasoning',
            'severity' => 'info',
            'available' => false,
            'reason' => $reason,
            'evidence' => [],
        ];
    }
}
