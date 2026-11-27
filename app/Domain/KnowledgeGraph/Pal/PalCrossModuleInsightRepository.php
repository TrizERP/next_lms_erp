<?php

namespace App\Domain\KnowledgeGraph\Pal;

use App\Domain\KnowledgeGraph\GraphQueryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cross-module correlations for PAL's weak-mastery cohort — "PAL ↔ X in
 * another module", mirroring FeesCrossModuleInsightRepository's own
 * attendance/admissions pairing, read from the PAL side instead.
 *
 * Attendance is checked through the Decentralized KG the same way Fees
 * checks it (GraphQueryService::availableRelations(), entity 'student',
 * relation 'has_attendance', confirmed live: in_graph=0, i.e. SQL-
 * traversable) — the cohort aggregation itself is one bulk SQL query, not
 * a per-student loop.
 *
 * Admissions has no ontology entity, so it's the same loose mobile-number
 * match Fees uses — always reported as a phone-number match, never a
 * verified family relationship, and never allowed to read as high severity.
 */
class PalCrossModuleInsightRepository
{
    private const ATTENDANCE_RELATION = 'has_attendance';

    private const ATTENDANCE_TABLE = 'attendance_student';

    /** Below this mastery_estimate, a concept node counts as weak. */
    private const WEAK_MASTERY_FLOOR = 0.5;

    /** Admission-enquiry statuses that no longer need a reminder. */
    private const RESOLVED_ENQUIRY_STATUSES = ['approve', 'approved', 'converted', 'closed', 'cancel'];

    public function __construct(private readonly GraphQueryService $graphQueryService)
    {
    }

    /**
     * Students in this tenant with at least one weak node in
     * learner_node_state — the PAL-side equivalent of Fees' "fee-gap cohort".
     *
     * @return array<int, int>
     */
    public function weakMasteryStudentIds(int $tenant, int $maxSample = 10000): array
    {
        if (! Schema::hasTable('learner_node_state')) {
            return [];
        }

        return DB::table('learner_node_state')
            ->where('sub_institute_id', $tenant)
            ->where('mastery_estimate', '<', self::WEAK_MASTERY_FLOOR)
            ->distinct()
            ->limit($maxSample)
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int>  $weakIds
     */
    public function masteryAttendanceCorrelation(array $weakIds, int $tenant, ?string $syear): array
    {
        if (! $this->attendanceEdgeAvailable($tenant)) {
            return [
                'available' => false,
                'reason' => "No traversable 'has_attendance' edge is configured for student in this ontology.",
            ];
        }

        if (! Schema::hasTable(self::ATTENDANCE_TABLE)) {
            return [
                'available' => false,
                'reason' => sprintf('The %s table is not present on this installation.', self::ATTENDANCE_TABLE),
            ];
        }

        if ($weakIds === []) {
            return [
                'available' => true,
                'reason' => null,
                'weak_sampled' => 0,
                'attendance_checked' => 0,
                'weak_with_low_attendance' => 0,
            ];
        }

        $query = DB::table(self::ATTENDANCE_TABLE)
            ->whereIn('student_id', $weakIds)
            ->where('sub_institute_id', $tenant);

        if ($syear !== null) {
            $query->where('syear', $syear);
        }

        $rows = $query
            ->selectRaw("student_id, COUNT(*) AS total_days, SUM(CASE WHEN attendance_code = 'A' THEN 1 ELSE 0 END) AS absent_days")
            ->groupBy('student_id')
            ->get();

        $lowAttendance = $rows->filter(
            fn ($row) => $row->total_days > 0 && ($row->absent_days / $row->total_days) >= 0.2
        )->count();

        return [
            'available' => true,
            'reason' => null,
            'weak_sampled' => count($weakIds),
            'attendance_checked' => $rows->count(),
            'weak_with_low_attendance' => $lowAttendance,
        ];
    }

    private function attendanceEdgeAvailable(int $tenant): bool
    {
        foreach ($this->graphQueryService->availableRelations('student', $tenant) as $relation) {
            if ($relation['relation'] === self::ATTENDANCE_RELATION) {
                return $relation['traversable'];
            }
        }

        return false;
    }

    /**
     * @param  array<int, int>  $weakIds
     */
    public function masteryAdmissionsRiskCorrelation(array $weakIds, int $tenant): array
    {
        if (! Schema::hasTable('admission_enquiry') || ! Schema::hasColumn('tblstudent', 'mobile')) {
            return [
                'available' => false,
                'reason' => 'admission_enquiry or tblstudent.mobile is not present on this installation.',
            ];
        }

        if ($weakIds === []) {
            return [
                'available' => true,
                'reason' => null,
                'weak_sampled' => 0,
                'mobile_matchable' => 0,
                'weak_with_open_enquiry' => 0,
            ];
        }

        $weakMobiles = DB::table('tblstudent')
            ->whereIn('id', $weakIds)
            ->where('sub_institute_id', $tenant)
            ->pluck('mobile', 'id')
            ->map(fn ($mobile) => $this->normalizeMobile($mobile))
            ->filter();

        if ($weakMobiles->isEmpty()) {
            return [
                'available' => true,
                'reason' => null,
                'weak_sampled' => count($weakIds),
                'mobile_matchable' => 0,
                'weak_with_open_enquiry' => 0,
            ];
        }

        $openEnquiryMobiles = DB::table('admission_enquiry')
            ->where('sub_institute_id', $tenant)
            ->whereNotIn('status', self::RESOLVED_ENQUIRY_STATUSES)
            ->pluck('mobile')
            ->map(fn ($mobile) => $this->normalizeMobile($mobile))
            ->filter()
            ->unique();

        $matched = $weakMobiles->filter(fn ($mobile) => $openEnquiryMobiles->contains($mobile));

        return [
            'available' => true,
            'reason' => null,
            'weak_sampled' => count($weakIds),
            'mobile_matchable' => $weakMobiles->count(),
            'weak_with_open_enquiry' => $matched->count(),
        ];
    }

    /** Last 10 digits only, digits stripped — a practical Indian-mobile-number normalization. */
    private function normalizeMobile(?string $mobile): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $mobile);

        return strlen($digits) >= 10 ? substr($digits, -10) : null;
    }
}
