<?php

namespace App\Services\Mcp;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Student engagement: three signals computed LIVE from attendance/homework/
 * assignment records (never stored — see each method below), plus, as of
 * 2026-10-10, a real per-event activity log read from `lms_student_engagement`.
 *
 * UPDATE 2026-10-10: that table is no longer the dead table this docblock
 * used to describe ("no engagement table exists in this estate" — see the
 * Engagement block in config/ai.php and check_ai_stack_coverage.php's
 * history). It has a real writer now, wired at three genuine student actions
 * (`palController::incrementContentVisit`, `assessmentQuestionController::
 * submitPractice`, `HomeworkSubmissionApiController::submit`), so this
 * service now reads it too — as a FOURTH, additive signal alongside the three
 * live-computed ones below, not a replacement for them. The three live
 * signals stay live-computed on purpose: they summarise records this service
 * does not own and was never going to duplicate into a second table.
 * `activity_log` is different — it IS this service's own vocabulary
 * ("content viewed", "concept practised", "homework submitted") captured as
 * it happens, so reading it back from where it was written is correct, not a
 * shortcut.
 *
 *   - `attendance_student.attendance_code` (P/A) — attendance rate.
 *   - `homework.completion_status` ('N' = not submitted, anything else = submitted) —
 *     homework completion rate.
 *   - `lms_assignments.progress` (0-100) — assignment completion.
 *   - `lms_student_engagement` — real per-event activity log (content views,
 *     practice sessions, homework submissions), counted and listed.
 *
 * A student with no rows in a window is reported as having no data for that signal, never
 * as 0% — silence is not the same as disengagement.
 */
class EngagementReportService
{
    public function studentSummary(McpRequestContext $context, array $args): array
    {
        $tenant = $context->selectedInstituteId;
        $studentId = isset($args['student_id']) ? (int) $args['student_id'] : null;
        $daysBack = (int) ($args['days_back'] ?? 30);
        $since = Carbon::now()->subDays($daysBack)->toDateString();

        if ($studentId === null) {
            return [
                'sub_institute_id' => $tenant,
                'note' => 'A student_id is required for a single-student summary. '
                    . 'Use engagement.students_needing_attention for a class-level read.',
            ];
        }

        return [
            'sub_institute_id' => $tenant,
            'student_id' => $studentId,
            'window_days' => $daysBack,
            'attendance' => $this->attendanceRate($tenant, [$studentId], $since)[$studentId] ?? null,
            'homework' => $this->homeworkRate($tenant, [$studentId], $since)[$studentId] ?? null,
            'assignments' => $this->assignmentRate($tenant, [$studentId], $since)[$studentId] ?? null,
            'activity_log' => $this->activityLog($tenant, $studentId, $daysBack),
        ];
    }

    /**
     * Real per-event activity history from `lms_student_engagement` — what the
     * student actually did, not a computed rate. Used by both the MCP summary
     * above and the K12 REST API
     * (`App\Http\Controllers\api\lms\StudentEngagementApiController`), which
     * is why `recent` carries full event detail rather than just counts.
     */
    public function activityLog(int $tenant, int $studentId, int $daysBack = 30, int $limit = 50): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('lms_student_engagement')) {
            return ['has_data' => false, 'total_events' => 0, 'by_type' => [], 'recent' => []];
        }

        $since = Carbon::now()->subDays($daysBack);

        $query = DB::table('lms_student_engagement')
            ->where('student_id', $studentId)
            ->where('created_at', '>=', $since)
            // Tenant-scoped when the row carries one; older/legacy rows with a
            // null sub_institute_id (written before 2026-10-10's tenant
            // column, or by a caller that could not resolve one) are not
            // silently hidden from the student's own history.
            ->where(function ($q) use ($tenant) {
                $q->where('sub_institute_id', $tenant)->orWhereNull('sub_institute_id');
            });

        $byType = (clone $query)
            ->selectRaw('activity_type, COUNT(*) as total')
            ->groupBy('activity_type')
            ->pluck('total', 'activity_type')
            ->map(fn ($n) => (int) $n)
            ->all();

        $recent = (clone $query)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'activity_type', 'activity_id', 'time_spent_seconds', 'interaction_count', 'metadata', 'started_at', 'completed_at', 'created_at'])
            ->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'activity_type' => $row->activity_type,
                    'activity_id' => $row->activity_id !== null ? (int) $row->activity_id : null,
                    'time_spent_seconds' => (int) ($row->time_spent_seconds ?? 0),
                    'interaction_count' => (int) ($row->interaction_count ?? 0),
                    'metadata' => $row->metadata ? (json_decode($row->metadata, true) ?: []) : [],
                    'started_at' => $row->started_at,
                    'completed_at' => $row->completed_at,
                    'occurred_at' => $row->created_at,
                ];
            })
            ->all();

        return [
            'has_data' => $byType !== [],
            'total_events' => array_sum($byType),
            'by_type' => $byType,
            'recent' => $recent,
        ];
    }

    public function studentsNeedingAttention(McpRequestContext $context, array $args): array
    {
        $tenant = $context->selectedInstituteId;
        $standardId = isset($args['standard_id']) ? (int) $args['standard_id'] : null;
        $threshold = (float) ($args['threshold_percent'] ?? 60);
        $daysBack = (int) ($args['days_back'] ?? 30);
        $limit = (int) ($args['limit'] ?? 50);
        $since = Carbon::now()->subDays($daysBack)->toDateString();

        $studentIds = DB::table('attendance_student')
            ->where('sub_institute_id', $tenant)
            ->where('attendance_date', '>=', $since)
            ->when($standardId, fn ($q) => $q->where('standard_id', $standardId))
            ->distinct()
            ->pluck('student_id')
            ->all();

        if ($studentIds === []) {
            return [
                'sub_institute_id' => $tenant,
                'window_days' => $daysBack,
                'threshold_percent' => $threshold,
                'students' => [],
                'note' => 'No attendance recorded in this window for the given filters.',
            ];
        }

        $attendance = $this->attendanceRate($tenant, $studentIds, $since);
        $homework = $this->homeworkRate($tenant, $studentIds, $since);
        $assignments = $this->assignmentRate($tenant, $studentIds, $since);

        $names = DB::table('tblstudent')
            ->whereIn('id', $studentIds)
            ->pluck(DB::raw("CONCAT(first_name, ' ', last_name)"), 'id');

        $flagged = [];

        foreach ($studentIds as $id) {
            $signals = array_filter([
                'attendance' => $attendance[$id] ?? null,
                'homework' => $homework[$id] ?? null,
                'assignments' => $assignments[$id] ?? null,
            ], fn ($v) => $v !== null);

            $below = array_filter($signals, fn ($v) => $v < $threshold);

            if ($below !== []) {
                $flagged[] = [
                    'student_id' => $id,
                    'student_name' => $names[$id] ?? null,
                    'attendance' => $attendance[$id] ?? null,
                    'homework' => $homework[$id] ?? null,
                    'assignments' => $assignments[$id] ?? null,
                    'below_threshold' => array_keys($below),
                ];
            }
        }

        usort($flagged, fn ($a, $b) => count($b['below_threshold']) <=> count($a['below_threshold']));

        return [
            'sub_institute_id' => $tenant,
            'window_days' => $daysBack,
            'threshold_percent' => $threshold,
            'students' => array_slice($flagged, 0, $limit),
            'total_flagged' => count($flagged),
        ];
    }

    /** @return array<int, float> student_id => percent P */
    private function attendanceRate(int $tenant, array $studentIds, string $since): array
    {
        $rows = DB::table('attendance_student')
            ->where('sub_institute_id', $tenant)
            ->whereIn('student_id', $studentIds)
            ->where('attendance_date', '>=', $since)
            ->selectRaw('student_id, COUNT(*) as total, SUM(CASE WHEN attendance_code = ? THEN 1 ELSE 0 END) as present', ['P'])
            ->groupBy('student_id')
            ->get();

        return $rows->mapWithKeys(fn ($r) => [
            (int) $r->student_id => $r->total > 0 ? round(($r->present / $r->total) * 100, 1) : 0.0,
        ])->all();
    }

    /** @return array<int, float> student_id => percent submitted (completion_status <> 'N') */
    private function homeworkRate(int $tenant, array $studentIds, string $since): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('homework')) {
            return [];
        }

        $rows = DB::table('homework')
            ->whereIn('student_id', $studentIds)
            ->where('date', '>=', $since)
            ->selectRaw("student_id, COUNT(*) as total, SUM(CASE WHEN completion_status <> 'N' THEN 1 ELSE 0 END) as done")
            ->groupBy('student_id')
            ->get();

        return $rows->mapWithKeys(fn ($r) => [
            (int) $r->student_id => $r->total > 0 ? round(($r->done / $r->total) * 100, 1) : 0.0,
        ])->all();
    }

    /** @return array<int, float> student_id => average progress (0-100) */
    private function assignmentRate(int $tenant, array $studentIds, string $since): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('lms_assignments')) {
            return [];
        }

        $rows = DB::table('lms_assignments')
            ->where('sub_institute_id', $tenant)
            ->whereIn('user_id', $studentIds)
            ->where('assigned_on', '>=', $since)
            ->whereNull('deleted_at')
            ->selectRaw('user_id, AVG(progress) as avg_progress')
            ->groupBy('user_id')
            ->get();

        return $rows->mapWithKeys(fn ($r) => [
            (int) $r->user_id => $r->avg_progress !== null ? round((float) $r->avg_progress, 1) : 0.0,
        ])->all();
    }
}
