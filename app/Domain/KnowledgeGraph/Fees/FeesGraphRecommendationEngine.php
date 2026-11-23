<?php

namespace App\Domain\KnowledgeGraph\Fees;

/**
 * Turns the repository's raw graph facts into the Fees "Recommendation"
 * panel's cards. Every card is {id, title, category, severity, available,
 * reason, evidence} — the same honesty-ladder shape PAL's
 * CoherenceRecommender uses: `available:false` always carries a specific,
 * numeric `reason`, never a generic placeholder, and a clean "0 issues"
 * result is never visually or structurally indistinguishable from "not
 * enough data to say".
 */
class FeesGraphRecommendationEngine
{
    /**
     * Below this many recorded payments for the scope, a per-standard
     * non-payment ratio or a fee/academic correlation is too noisy to
     * assert — the honesty gate shared by the two "reasoning" cards.
     */
    private const MIN_PAYMENT_SAMPLE = 30;

    /**
     * A non-payment pattern counts as "compounding" only once it spans at
     * least this many standards — mirrors CompoundingRootBlockerDetector's
     * "≥2 chapters" rule: a single standard's shortfall is local, not a
     * platform-wide pattern.
     */
    private const MIN_STANDARDS_FOR_COMPOUNDING = 2;

    /** A standard counts as a real non-payment concentration above this ratio. */
    private const NON_PAYMENT_RATIO_THRESHOLD = 0.5;

    public function __construct(private readonly FeesGraphRecommendationRepository $repository)
    {
    }

    public function cardsFor(int $tenant, string $syear): array
    {
        $coverage = $this->repository->platformCoverage($tenant, $syear);

        return [
            $this->coverageCard($coverage),
            $this->scheduleIntegrityCard($tenant, $syear),
            $this->unlinkedPaymentsCard($tenant, $syear),
            $this->rootBlockerCard($tenant, $syear, $coverage),
            $this->correlationCard($tenant, $syear, $coverage),
        ];
    }

    /**
     * Every card this engine can produce, individually marked unavailable —
     * for when the caller already knows Knowledge Graph is switched off for
     * Fees in this school (`KnowledgeGraphSettingsResolver::isEnabledFor()`)
     * and wants to say so without ever touching Neo4j. Deliberately not a
     * blanket failure response: the panel keeps its usual five-card shape,
     * each one honest about *why* it has nothing to show, exactly like a
     * thin-sample unavailable card would read.
     */
    public function disabledCards(): array
    {
        $reason = 'Knowledge Graph is switched off for Fees in this school.';

        return [
            $this->unavailable('fees.platform_coverage', 'Fee-graph coverage health', 'fee_payment', $reason),
            $this->unavailable('fees.schedule_integrity', 'Fee schedule integrity', 'fee_schedule', $reason),
            $this->unavailable('fees.unlinked_payments', 'Reconciliation: unlinked payments', 'fee_payment', $reason),
            $this->unavailable('fees.root_blocker', 'Fee collection root-blocker', 'fee_collection', $reason),
            $this->unavailable('fees.academic_correlation', 'Fee + academic correlation', 'cross_module', $reason),
        ];
    }

    private function coverageCard(array $coverage): array
    {
        $payments = $coverage['payments'];
        $enrolled = $coverage['enrolled_students'];
        $scheduleLines = $coverage['schedule_lines'];

        $description = sprintf(
            '%d payment(s) recorded from %d of %d enrolled student(s) this year, against %d live schedule line(s) across %d standard(s).',
            $payments,
            $coverage['paying_students'],
            $enrolled,
            $scheduleLines,
            $coverage['standards_scheduled']
        );

        return [
            'id' => 'fees.platform_coverage',
            'title' => 'Fee-graph coverage health',
            'category' => 'fee_payment',
            'severity' => $payments < self::MIN_PAYMENT_SAMPLE ? 'low' : 'info',
            'available' => true,
            'reason' => null,
            'evidence' => [
                'description' => $description,
                ...$coverage,
            ],
        ];
    }

    private function scheduleIntegrityCard(int $tenant, string $syear): array
    {
        $data = $this->repository->scheduleIntegrity($tenant, $syear);

        if ($data['total_schedule_lines'] === 0) {
            return $this->unavailable(
                'fees.schedule_integrity',
                'Fee schedule integrity',
                'fee_schedule',
                sprintf('No fee schedule (:FeeRecord) is projected into Neo4j for %s yet.', $syear)
            );
        }

        $gaps = $data['gaps'];
        $conflicts = $data['conflicts'];

        if ($gaps === [] && $conflicts === []) {
            return [
                'id' => 'fees.schedule_integrity',
                'title' => 'Fee schedule integrity',
                'category' => 'fee_schedule',
                'severity' => 'positive',
                'available' => true,
                'reason' => null,
                'evidence' => [
                    'description' => sprintf(
                        'Every one of %d standard(s) has a fee schedule for %s, with no conflicting amounts found.',
                        $data['standards_total'],
                        $syear
                    ),
                ],
            ];
        }

        $parts = [];
        if ($gaps !== []) {
            $parts[] = sprintf('%d of %d standard(s) have no fee schedule mapped — define it before the next billing cycle.', count($gaps), $data['standards_total']);
        }
        if ($conflicts !== []) {
            $parts[] = sprintf('%d schedule line(s) carry conflicting amounts for the same standard/month/fee-type — resolve before the next collection run.', count($conflicts));
        }

        return [
            'id' => 'fees.schedule_integrity',
            'title' => 'Fee schedule integrity',
            'category' => 'fee_schedule',
            'severity' => $conflicts !== [] ? 'critical' : 'medium',
            'available' => true,
            'reason' => null,
            'evidence' => [
                'description' => implode(' ', $parts),
                'standards_total' => $data['standards_total'],
                'gaps' => $data['gaps'],
                'conflicts' => $data['conflicts'],
            ],
        ];
    }

    private function unlinkedPaymentsCard(int $tenant, string $syear): array
    {
        $data = $this->repository->unlinkedPayments($tenant, $syear);

        if ($data['total_payments'] === 0) {
            return $this->unavailable(
                'fees.unlinked_payments',
                'Reconciliation: unlinked payments',
                'fee_payment',
                sprintf('No payment (:FeePayment) is projected into Neo4j for %s yet.', $syear)
            );
        }

        $orphans = $data['orphans'];

        if ($orphans === []) {
            return [
                'id' => 'fees.unlinked_payments',
                'title' => 'Reconciliation: unlinked payments',
                'category' => 'fee_payment',
                'severity' => 'positive',
                'available' => true,
                'reason' => null,
                'evidence' => [
                    'description' => sprintf('All %d recorded payment(s) for %s trace back to an enrolled student in the graph.', $data['total_payments'], $syear),
                ],
            ];
        }

        return [
            'id' => 'fees.unlinked_payments',
            'title' => 'Reconciliation: unlinked payments',
            'category' => 'fee_payment',
            'severity' => 'medium',
            'available' => true,
            'reason' => null,
            'evidence' => [
                'description' => sprintf('%d of %d recorded payment(s) for %s have no reachable enrollment in the graph — review these receipts.', count($orphans), $data['total_payments'], $syear),
                'total_payments' => $data['total_payments'],
                'orphans' => $orphans,
            ],
        ];
    }

    private function rootBlockerCard(int $tenant, string $syear, array $coverage): array
    {
        if ($coverage['payments'] < self::MIN_PAYMENT_SAMPLE) {
            return $this->unavailable(
                'fees.root_blocker',
                'Fee collection root-blocker',
                'fee_collection',
                sprintf(
                    'Only %d payment(s) are projected into Neo4j for %s against %d enrolled student(s) — too thin a sample to assert a widespread non-payment pattern.',
                    $coverage['payments'],
                    $syear,
                    $coverage['enrolled_students']
                )
            );
        }

        $data = $this->repository->standardNonPaymentConcentration($tenant, $syear);
        $standards = $data['standards'];

        $concentrated = array_values(array_filter($standards, function ($s) {
            if ($s['enrolled'] === 0) {
                return false;
            }

            return ($s['non_paying'] / $s['enrolled']) >= self::NON_PAYMENT_RATIO_THRESHOLD && $s['non_paying'] > 0;
        }));

        if (count($concentrated) < self::MIN_STANDARDS_FOR_COMPOUNDING) {
            return [
                'id' => 'fees.root_blocker',
                'title' => 'Fee collection root-blocker',
                'category' => 'fee_collection',
                'severity' => 'positive',
                'available' => true,
                'reason' => null,
                'evidence' => [
                    'description' => sprintf(
                        'Non-payment is not concentrated across standards for %s — fewer than %d standard(s) cross the %d%% non-payment threshold.',
                        $syear,
                        self::MIN_STANDARDS_FOR_COMPOUNDING,
                        (int) (self::NON_PAYMENT_RATIO_THRESHOLD * 100)
                    ),
                    'standards_checked' => count($standards),
                ],
            ];
        }

        $topStandards = array_slice($concentrated, 0, 10);

        return [
            'id' => 'fees.root_blocker',
            'title' => 'Fee collection root-blocker',
            'category' => 'fee_collection',
            'severity' => 'high',
            'available' => true,
            'reason' => null,
            'evidence' => [
                'why' => sprintf(
                    'Non-payment is concentrated at or above %d%% of enrollment in %d standard(s) for %s — a pattern spanning multiple standards, not one noisy outlier.',
                    (int) (self::NON_PAYMENT_RATIO_THRESHOLD * 100),
                    count($concentrated),
                    $syear
                ),
                'expected_impact' => 'A bulk reminder/intervention targeted at these standards would reach the largest concentration of non-paying families for the least effort.',
                'standards' => $topStandards,
            ],
        ];
    }

    private function correlationCard(int $tenant, string $syear, array $coverage): array
    {
        if ($coverage['payments'] < self::MIN_PAYMENT_SAMPLE) {
            return $this->unavailable(
                'fees.academic_correlation',
                'Fee + academic correlation',
                'cross_module',
                sprintf(
                    'Only %d payment(s) are projected into Neo4j for %s — too thin to correlate fee gaps with engagement/mastery signal.',
                    $coverage['payments'],
                    $syear
                )
            );
        }

        $data = $this->repository->feeAcademicCorrelation($tenant, $syear);

        if ($data['gap_sampled'] === 0) {
            return [
                'id' => 'fees.academic_correlation',
                'title' => 'Fee + academic correlation',
                'category' => 'cross_module',
                'severity' => 'positive',
                'available' => true,
                'reason' => null,
                'evidence' => [
                    'description' => sprintf('Every enrolled student for %s has at least one recorded payment — no fee gap to correlate.', $syear),
                ],
            ];
        }

        $withNeither = $data['gap_sampled'] - max($data['gap_with_engagement'], $data['gap_with_mastery_signal']);
        $weakSignalRatio = $data['gap_sampled'] > 0 ? $withNeither / $data['gap_sampled'] : 0;

        if ($data['gap_with_engagement'] === 0 && $data['gap_with_mastery_signal'] === 0) {
            return $this->unavailable(
                'fees.academic_correlation',
                'Fee + academic correlation',
                'cross_module',
                sprintf(
                    '%d student(s) have a fee gap for %s, but none show any engagement or mastery signal in the graph at all — the academic-signal side is not populated enough to correlate, not that these students show no engagement.',
                    $data['gap_sampled'],
                    $syear
                )
            );
        }

        return [
            'id' => 'fees.academic_correlation',
            'title' => 'Fee + academic correlation',
            'category' => 'cross_module',
            'severity' => $weakSignalRatio >= 0.5 ? 'medium' : 'low',
            'available' => true,
            'reason' => null,
            'evidence' => [
                'why' => sprintf(
                    'Of %d student(s) with a fee gap for %s, %d show no engagement signal and %d show no mastery signal in the graph — correlation only, not a causal claim.',
                    $data['gap_sampled'],
                    $syear,
                    $data['gap_sampled'] - $data['gap_with_engagement'],
                    $data['gap_sampled'] - $data['gap_with_mastery_signal']
                ),
                'expected_impact' => 'Students showing both a fee gap and a weak academic signal are a reasonable shortlist for a joint counselor/front-office review.',
                'enrolled_total' => $data['enrolled_total'],
                'paying_total' => $data['paying_total'],
                'gap_total' => $data['gap_total'],
                'gap_sampled' => $data['gap_sampled'],
                'gap_with_engagement' => $data['gap_with_engagement'],
                'gap_with_mastery_signal' => $data['gap_with_mastery_signal'],
            ],
        ];
    }

    private function unavailable(string $id, string $title, string $category, string $reason): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'category' => $category,
            'severity' => 'info',
            'available' => false,
            'reason' => $reason,
            'evidence' => [],
        ];
    }
}
