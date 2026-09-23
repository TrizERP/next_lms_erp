<?php

namespace App\Services\Mcp;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Student engagement, computed live from real attendance, homework and assignment
 * records — never stored.
 *
 * No `engagement` table exists in this estate (see the Engagement block in config/ai.php
 * and check_ai_stack_coverage.php's history). Rather than inventing one, this service
 * reads the three real signals that already exist for every student:
 *
 *   - `attendance_student.attendance_code` (P/A) — attendance rate.
 *   - `homework.completion_status` ('N' = not submitted, anything else = submitted) —
 *     homework completion rate.
 *   - `lms_assignments.progress` (0-100) — assignment completion.
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
