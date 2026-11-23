<?php

namespace App\Domain\KnowledgeGraph\Fees;

use App\Domain\KnowledgeGraph\GraphQueryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cross-module correlations for the fee-gap cohort — "fees ↔ X in another
 * module", for X this engine doesn't already have a real edge to.
 *
 * Attendance is read through the Decentralized KG: GraphQueryService's
 * ontology tells us whether `fee_student`'s `has_attendance` edge is
 * configured/enabled at all (`availableRelations()`), and if so which
 * table/column it resolves to — but the actual cohort-wide aggregation is
 * one bulk SQL query, not a loop of `GraphQueryService::neighbours()` calls
 * (one per student). That primitive is built for "this one record's
 * neighbours"; looping it over a whole fee-gap cohort (hundreds of
 * students) would repeat the exact per-student N+1 pattern
 * FeesArrearsService already had to be memoized against.
 *
 * Admissions has no ontology entity at all, so it's plain SQL, matched on
 * normalized guardian mobile number — a real but deliberately loose join
 * (phone numbers can be reassigned, shared, or typo'd), so this pairing is
 * always reported as a mobile-number match, never as a verified family
 * relationship, and is never allowed to claim high severity.
 */
class FeesCrossModuleInsightRepository
{
    private const ATTENDANCE_RELATION = 'has_attendance';

    private const ATTENDANCE_TABLE = 'attendance_student';

    /** Admission-enquiry statuses that no longer need a reminder. */
    private const RESOLVED_ENQUIRY_STATUSES = ['approve', 'approved', 'converted', 'closed', 'cancel'];

    public function __construct(
        private readonly GraphQueryService $graphQueryService,
        private readonly FeesGraphRecommendationRepository $feesRepository,
    ) {
    }

    /**
     * The fee-gap cohort for this tenant/year — delegates to
     * FeesGraphRecommendationRepository (the Neo4j-side owner of "who has
     * a fee gap"), computed once by the caller and handed to both
     * feeAttendanceCorrelation() and feeAdmissionsRiskCorrelation() rather
     * than each independently paying for the same two Neo4j queries.
     *
     * @return array<int, int>
     */
    public function gapStudentIds(int $tenant, string $syear, int $maxGapSample = 10000): array
    {
        return $this->feesRepository->gapStudentIds($tenant, $syear, $maxGapSample);
    }

    public function feeAttendanceCorrelation(array $gapIds, int $tenant, string $syear): array
    {
        if (! $this->attendanceEdgeAvailable($tenant)) {
            return [
                'available' => false,
                'reason' => "No traversable 'has_attendance' edge is configured for fee_student in this ontology.",
            ];
        }

        if (! Schema::hasTable(self::ATTENDANCE_TABLE)) {
            return [
                'available' => false,
                'reason' => sprintf('The %s table is not present on this installation.', self::ATTENDANCE_TABLE),
            ];
        }

        if ($gapIds === []) {
            return [
                'available' => true,
                'reason' => null,
                'gap_sampled' => 0,
                'attendance_checked' => 0,
                'gap_with_low_attendance' => 0,
            ];
        }

        // Scoped by tenant AND syear: attendance_student holds every year a
        // student has ever attended (4M+ rows platform-wide on at least one
        // real tenant), so an unscoped whereIn() here would both be slow
        // (years of rows per student instead of one) and wrong (mixing a
        // student's attendance from unrelated years into "current" signal).
        $rows = DB::table(self::ATTENDANCE_TABLE)
            ->whereIn('student_id', $gapIds)
            ->where('sub_institute_id', $tenant)
            ->where('syear', $syear)
            ->selectRaw("student_id, COUNT(*) AS total_days, SUM(CASE WHEN attendance_code = 'A' THEN 1 ELSE 0 END) AS absent_days")
            ->groupBy('student_id')
            ->get();

        $lowAttendance = $rows->filter(function ($row) {
            return $row->total_days > 0 && ($row->absent_days / $row->total_days) >= 0.2;
        })->count();

        return [
            'available' => true,
            'reason' => null,
            'gap_sampled' => count($gapIds),
            'attendance_checked' => $rows->count(),
            'gap_with_low_attendance' => $lowAttendance,
        ];
    }

    private function attendanceEdgeAvailable(int $tenant): bool
    {
        foreach ($this->graphQueryService->availableRelations('fee_student', $tenant) as $relation) {
            if ($relation['relation'] === self::ATTENDANCE_RELATION) {
                return $relation['traversable'];
            }
        }

        return false;
    }

    public function feeAdmissionsRiskCorrelation(array $gapIds, int $tenant): array
    {
        if (! Schema::hasTable('admission_enquiry') || ! Schema::hasColumn('tblstudent', 'mobile')) {
            return [
                'available' => false,
                'reason' => 'admission_enquiry or tblstudent.mobile is not present on this installation.',
            ];
        }

        if ($gapIds === []) {
            return [
                'available' => true,
                'reason' => null,
                'gap_sampled' => 0,
                'mobile_matchable' => 0,
                'gap_with_open_enquiry' => 0,
            ];
        }

        $gapMobiles = DB::table('tblstudent')
            ->whereIn('id', $gapIds)
            ->where('sub_institute_id', $tenant)
            ->pluck('mobile', 'id')
            ->map(fn ($mobile) => $this->normalizeMobile($mobile))
            ->filter();

        if ($gapMobiles->isEmpty()) {
            return [
                'available' => true,
                'reason' => null,
                'gap_sampled' => count($gapIds),
                'mobile_matchable' => 0,
                'gap_with_open_enquiry' => 0,
            ];
        }

        $openEnquiryMobiles = DB::table('admission_enquiry')
            ->where('sub_institute_id', $tenant)
            ->whereNotIn('status', self::RESOLVED_ENQUIRY_STATUSES)
            ->pluck('mobile')
            ->map(fn ($mobile) => $this->normalizeMobile($mobile))
            ->filter()
            ->unique();

        $matched = $gapMobiles->filter(fn ($mobile) => $openEnquiryMobiles->contains($mobile));

        return [
            'available' => true,
            'reason' => null,
            'gap_sampled' => count($gapIds),
            'mobile_matchable' => $gapMobiles->count(),
            'gap_with_open_enquiry' => $matched->count(),
        ];
    }

    /** Last 10 digits only, digits stripped — a practical Indian-mobile-number normalization. */
    private function normalizeMobile(?string $mobile): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $mobile);

        return strlen($digits) >= 10 ? substr($digits, -10) : null;
    }
}
