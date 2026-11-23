<?php

namespace App\Domain\KnowledgeGraph\Fees;

/**
 * Turns the Fees graph/SQL into the Recommendation panel's "Cross-Module
 * Intelligence" section — insights that need a second module's data, not
 * just Fees' own. Three pairs, each gated independently (attendance data
 * is far denser than the fee-payment graph, so it does not inherit
 * FeesGraphRecommendationEngine's MIN_PAYMENT_SAMPLE floor verbatim):
 *   - fees_attendance: Decentralized KG (GraphQueryService + ontology)
 *   - fees_admissions: plain SQL, mobile-number match (no ontology entity
 *     for admissions exists) — always capped at medium severity, since a
 *     phone-number match is evidence of a possible family link, not proof
 *     of one
 *   - fees_pal: reuses FeesGraphRecommendationEngine's own
 *     fees.academic_correlation card verbatim (already cross_module, no
 *     need to recompute it)
 */
class FeesCrossModuleInsightEngine
{
    /** Below this many fee-gap students sampled, an attendance/admissions correlation is too noisy to assert. */
    private const MIN_GAP_SAMPLE = 20;

    public function __construct(
        private readonly FeesCrossModuleInsightRepository $repository,
        private readonly FeesGraphRecommendationEngine $recommendationEngine
    ) {
    }

    public function insightsFor(int $tenant, string $syear): array
    {
        // Computed once and handed to both correlations below, rather than
        // each independently re-deriving the same cohort via its own pair
        // of Neo4j queries.
        $gapIds = $this->repository->gapStudentIds($tenant, $syear);

        return [
            $this->attendanceCard($gapIds, $tenant, $syear),
            $this->admissionsCard($gapIds, $tenant, $syear),
            $this->palCard($tenant, $syear),
        ];
    }

    private function attendanceCard(array $gapIds, int $tenant, string $syear): array
    {
        $data = $this->repository->feeAttendanceCorrelation($gapIds, $tenant, $syear);

        if (! $data['available']) {
            return $this->unavailable('fees.cross_module.attendance', 'Fee gap + attendance correlation', $data['reason']);
        }

        if ($data['gap_sampled'] < self::MIN_GAP_SAMPLE) {
            return $this->unavailable(
                'fees.cross_module.attendance',
                'Fee gap + attendance correlation',
                sprintf('Only %d student(s) have a fee gap for %s — too thin to correlate with attendance.', $data['gap_sampled'], $syear)
            );
        }

        if ($data['attendance_checked'] === 0) {
            return $this->unavailable(
                'fees.cross_module.attendance',
                'Fee gap + attendance correlation',
                sprintf('%d student(s) have a fee gap for %s, but none have an attendance record on this installation to correlate against.', $data['gap_sampled'], $syear)
            );
        }

        $ratio = $data['gap_with_low_attendance'] / $data['attendance_checked'];

        return [
            'id' => 'fees.cross_module.attendance',
            'title' => 'Fee gap + attendance correlation',
            'category' => 'cross_module',
            'severity' => $ratio >= 0.3 ? 'medium' : 'low',
            'available' => true,
            'reason' => null,
            'evidence' => [
                'source' => 'sql',
                'why' => sprintf(
                    'Of %d student(s) with a fee gap for %s who have an attendance record, %d (%.0f%%) show at least 20%% absenteeism — correlation only, not a causal claim.',
                    $data['attendance_checked'],
                    $syear,
                    $data['gap_with_low_attendance'],
                    $ratio * 100
                ),
                'expected_impact' => 'Students showing both a fee gap and low attendance are a reasonable shortlist for a joint counselor/front-office review before a reminder is sent.',
                'gap_sampled' => $data['gap_sampled'],
                'attendance_checked' => $data['attendance_checked'],
                'gap_with_low_attendance' => $data['gap_with_low_attendance'],
            ],
        ];
    }

    private function admissionsCard(array $gapIds, int $tenant, string $syear): array
    {
        $data = $this->repository->feeAdmissionsRiskCorrelation($gapIds, $tenant);

        if (! $data['available']) {
            return $this->unavailable('fees.cross_module.admissions', 'Fee gap + admissions enquiry correlation', $data['reason']);
        }

        if ($data['gap_sampled'] < self::MIN_GAP_SAMPLE) {
            return $this->unavailable(
                'fees.cross_module.admissions',
                'Fee gap + admissions enquiry correlation',
                sprintf('Only %d student(s) have a fee gap for %s — too thin to correlate with admissions enquiries.', $data['gap_sampled'], $syear)
            );
        }

        if ($data['mobile_matchable'] === 0) {
            return $this->unavailable(
                'fees.cross_module.admissions',
                'Fee gap + admissions enquiry correlation',
                sprintf('%d student(s) have a fee gap for %s, but none have a usable contact number on file to match against admission enquiries.', $data['gap_sampled'], $syear)
            );
        }

        if ($data['gap_with_open_enquiry'] === 0) {
            return [
                'id' => 'fees.cross_module.admissions',
                'title' => 'Fee gap + admissions enquiry correlation',
                'category' => 'cross_module',
                'severity' => 'positive',
                'available' => true,
                'reason' => null,
                'evidence' => [
                    'description' => sprintf('None of %d fee-gap student(s) for %s share a contact number with an open admission enquiry.', $data['mobile_matchable'], $syear),
                ],
            ];
        }

        return [
            'id' => 'fees.cross_module.admissions',
            'title' => 'Fee gap + admissions enquiry correlation',
            'category' => 'cross_module',
            // Capped at medium: a mobile-number match is evidence of a possible
            // family link, never proof of one (numbers get reassigned, shared,
            // or mistyped) — this card must not be allowed to read as "high".
            'severity' => 'medium',
            'available' => true,
            'reason' => null,
            'evidence' => [
                'source' => 'sql_mobile_match',
                'why' => sprintf(
                    '%d of %d fee-gap student(s) for %s share a contact number with a still-open admission enquiry — a phone-number match, not a verified family relationship.',
                    $data['gap_with_open_enquiry'],
                    $data['mobile_matchable'],
                    $syear
                ),
                'expected_impact' => 'A front-office call about the open enquiry is a natural moment to also raise the existing fee gap — one call instead of two uncoordinated ones.',
                'mobile_matchable' => $data['mobile_matchable'],
                'gap_with_open_enquiry' => $data['gap_with_open_enquiry'],
            ],
        ];
    }

    private function palCard(int $tenant, string $syear): array
    {
        $cards = $this->recommendationEngine->cardsFor($tenant, $syear);

        foreach ($cards as $card) {
            if ($card['id'] === 'fees.academic_correlation') {
                return $card;
            }
        }

        return $this->unavailable('fees.academic_correlation', 'Fee + academic correlation', 'Not computed.');
    }

    private function unavailable(string $id, string $title, string $reason): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'category' => 'cross_module',
            'severity' => 'info',
            'available' => false,
            'reason' => $reason,
            'evidence' => [],
        ];
    }
}
