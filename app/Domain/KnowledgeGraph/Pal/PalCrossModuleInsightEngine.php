<?php

namespace App\Domain\KnowledgeGraph\Pal;

use App\Domain\KnowledgeGraph\Fees\FeesGraphRecommendationEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns PAL's weak-mastery cohort into the Recommendation panel's
 * "Cross-Module Intelligence" section for Teach/Learn — mirrors
 * FeesCrossModuleInsightEngine's own three-pair shape, read from the PAL
 * side:
 *   - pal_attendance: Decentralized KG (GraphQueryService + ontology)
 *   - pal_admissions: plain SQL, mobile-number match — always capped at
 *     medium severity, same reasoning as the Fees-side equivalent
 *   - pal_fees: reuses FeesGraphRecommendationEngine's own
 *     fees.academic_correlation card verbatim (it is already cross_module
 *     and already correlates a fee gap with "no mastery signal" — no need
 *     to recompute it from this side)
 *
 * `priority`/`priority_reason` are additive to the existing `severity`
 * field (which keeps driving the shared UI's colour/tone) — computed from
 * the same real counts each card already carries, never an invented score.
 */
class PalCrossModuleInsightEngine
{
    /** Below this many weak-mastery students sampled, a correlation is too noisy to assert. */
    private const MIN_GAP_SAMPLE = 20;

    public function __construct(
        private readonly PalCrossModuleInsightRepository $repository,
        private readonly FeesGraphRecommendationEngine $feesRecommendationEngine,
    ) {
    }

    public function insightsFor(int $tenant, ?string $syear): array
    {
        $weakIds = $this->repository->weakMasteryStudentIds($tenant);

        return [
            $this->attendanceCard($weakIds, $tenant, $syear),
            $this->admissionsCard($weakIds, $tenant),
            $this->feesCard($tenant, $syear),
        ];
    }

    private function attendanceCard(array $weakIds, int $tenant, ?string $syear): array
    {
        $data = $this->repository->masteryAttendanceCorrelation($weakIds, $tenant, $syear);

        if (! $data['available']) {
            return $this->unavailable('pal.cross_module.attendance', 'PAL weak-mastery + attendance correlation', $data['reason']);
        }

        if ($data['weak_sampled'] < self::MIN_GAP_SAMPLE) {
            return $this->unavailable(
                'pal.cross_module.attendance',
                'PAL weak-mastery + attendance correlation',
                sprintf(
                    'Only %d student(s) have a weak-mastery PAL concept in learner_node_state for this school — below the %d-student floor needed to correlate with attendance.',
                    $data['weak_sampled'],
                    self::MIN_GAP_SAMPLE
                )
            );
        }

        if ($data['attendance_checked'] === 0) {
            return $this->unavailable(
                'pal.cross_module.attendance',
                'PAL weak-mastery + attendance correlation',
                sprintf('%d student(s) show weak PAL mastery, but none have an attendance record on this installation to correlate against.', $data['weak_sampled'])
            );
        }

        $ratio = $data['weak_with_low_attendance'] / $data['attendance_checked'];

        return [
            'id' => 'pal.cross_module.attendance',
            'title' => 'PAL weak-mastery + attendance correlation',
            'category' => 'cross_module',
            'severity' => $ratio >= 0.3 ? 'medium' : 'low',
            'available' => true,
            'reason' => null,
            ...$this->priority($ratio, self::MIN_GAP_SAMPLE, $data['weak_sampled']),
            'evidence' => [
                'source' => 'sql',
                'why' => sprintf(
                    'Of %d student(s) with weak PAL mastery who have an attendance record, %d (%.0f%%) show at least 20%% absenteeism — correlation only, not a causal claim.',
                    $data['attendance_checked'],
                    $data['weak_with_low_attendance'],
                    $ratio * 100
                ),
                'expected_impact' => 'Students showing both weak PAL mastery and low attendance are a reasonable shortlist for a joint teacher/counselor review before a remediation plan is drafted.',
                'weak_sampled' => $data['weak_sampled'],
                'attendance_checked' => $data['attendance_checked'],
                'weak_with_low_attendance' => $data['weak_with_low_attendance'],
            ],
        ];
    }

    private function admissionsCard(array $weakIds, int $tenant): array
    {
        $data = $this->repository->masteryAdmissionsRiskCorrelation($weakIds, $tenant);

        if (! $data['available']) {
            return $this->unavailable('pal.cross_module.admissions', 'PAL weak-mastery + admissions enquiry correlation', $data['reason']);
        }

        if ($data['weak_sampled'] < self::MIN_GAP_SAMPLE) {
            return $this->unavailable(
                'pal.cross_module.admissions',
                'PAL weak-mastery + admissions enquiry correlation',
                sprintf(
                    'Only %d student(s) have a weak-mastery PAL concept for this school — below the %d-student floor needed to correlate with admissions enquiries.',
                    $data['weak_sampled'],
                    self::MIN_GAP_SAMPLE
                )
            );
        }

        if ($data['mobile_matchable'] === 0) {
            return $this->unavailable(
                'pal.cross_module.admissions',
                'PAL weak-mastery + admissions enquiry correlation',
                sprintf('%d student(s) show weak PAL mastery, but none have a usable contact number on file to match against admission enquiries.', $data['weak_sampled'])
            );
        }

        if ($data['weak_with_open_enquiry'] === 0) {
            return [
                'id' => 'pal.cross_module.admissions',
                'title' => 'PAL weak-mastery + admissions enquiry correlation',
                'category' => 'cross_module',
                'severity' => 'positive',
                'available' => true,
                'reason' => null,
                'priority' => 'Minor',
                'priority_reason' => 'No weak-mastery student shares a contact number with an open admission enquiry — nothing to flag.',
                'evidence' => [
                    'description' => sprintf('None of %d weak-mastery student(s) share a contact number with an open admission enquiry.', $data['mobile_matchable']),
                ],
            ];
        }

        return [
            'id' => 'pal.cross_module.admissions',
            'title' => 'PAL weak-mastery + admissions enquiry correlation',
            'category' => 'cross_module',
            // Capped at medium: a mobile-number match is evidence of a
            // possible family link, never proof of one.
            'severity' => 'medium',
            'available' => true,
            'reason' => null,
            // Always Medium, regardless of ratio — a loose phone-number join
            // must never be allowed to read as Major.
            'priority' => 'Medium',
            'priority_reason' => 'Capped at Medium: a mobile-number match is a possible family link, not a verified one.',
            'evidence' => [
                'source' => 'sql_mobile_match',
                'why' => sprintf(
                    '%d of %d weak-mastery student(s) share a contact number with a still-open admission enquiry — a phone-number match, not a verified family relationship.',
                    $data['weak_with_open_enquiry'],
                    $data['mobile_matchable']
                ),
                'expected_impact' => 'A front-office call about the open enquiry is a natural moment to also raise the existing PAL weak-mastery concern — one call instead of two uncoordinated ones.',
                'mobile_matchable' => $data['mobile_matchable'],
                'weak_with_open_enquiry' => $data['weak_with_open_enquiry'],
            ],
        ];
    }

    /**
     * Resolves a syear from the Fees side's own academic_year table when the
     * caller's context has one, rather than guessing a "current" year — PAL
     * itself is not academic-year-scoped, so there is no PAL-native syear to
     * fall back to.
     */
    private function feesCard(int $tenant, ?string $syear): array
    {
        if ($syear === null) {
            return $this->unavailable(
                'fees.academic_correlation',
                'Fee + PAL academic correlation',
                'No academic year is set on this request, so the Fees-side correlation (syear-scoped) cannot be resolved.'
            );
        }

        $cards = $this->feesRecommendationEngine->cardsFor($tenant, $syear);

        foreach ($cards as $card) {
            if ($card['id'] === 'fees.academic_correlation') {
                // Reused verbatim — already category:'cross_module' and
                // already carries its own severity; add priority on top
                // using the same ratio language it already computed.
                $card['priority'] = $card['severity'] === 'medium' ? 'Medium' : ($card['severity'] === 'low' ? 'Minor' : 'Minor');
                $card['priority_reason'] = 'Inherited from the Fees-side fee+academic correlation card (same evidence, viewed from PAL).';

                return $card;
            }
        }

        return $this->unavailable('fees.academic_correlation', 'Fee + PAL academic correlation', 'Not computed.');
    }

    /**
     * Major when the correlated-weak cohort is a substantial share of the
     * sample (and the sample clears the honesty floor); Medium for a
     * moderate share; Minor otherwise.
     */
    private function priority(float $ratio, int $floor, int $sample): array
    {
        if ($sample < $floor) {
            return ['priority' => 'Minor', 'priority_reason' => 'Sample below the honesty floor — informational only.'];
        }

        if ($ratio >= 0.3) {
            return ['priority' => 'Major', 'priority_reason' => sprintf('%.0f%% of the correlated cohort is affected — at or above the 30%% Major threshold.', $ratio * 100)];
        }

        if ($ratio >= 0.1) {
            return ['priority' => 'Medium', 'priority_reason' => sprintf('%.0f%% of the correlated cohort is affected — in the 10-30%% Medium band.', $ratio * 100)];
        }

        return ['priority' => 'Minor', 'priority_reason' => sprintf('%.0f%% of the correlated cohort is affected — below the 10%% Medium threshold.', $ratio * 100)];
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
            'priority' => 'Minor',
            'priority_reason' => 'Not available — see reason.',
            'evidence' => [],
        ];
    }
}
