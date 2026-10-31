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

    /**
     * Enriched activity diagnostic profile for a cohort or list of flagged students.
     * Consumes Agent 1's flagged student list to diagnose specific participation barriers.
     */
    public function profileEngagement(McpRequestContext $context, array $args): array
    {
        $tenant = (int) $context->selectedInstituteId;
        $studentIds = (array) ($args['student_ids'] ?? []);
        $daysBack = (int) ($args['days_back'] ?? 30);
        $since = Carbon::now()->subDays($daysBack)->toDateString();

        if (empty($studentIds) && !empty($args['student_id'])) {
            $studentIds = [(int) $args['student_id']];
        }

        if (empty($studentIds)) {
            return [
                'sub_institute_id' => $tenant,
                'window_days' => $daysBack,
                'total_profiled' => 0,
                'profiles' => [],
            ];
        }

        $names = DB::table('tblstudent')
            ->whereIn('id', $studentIds)
            ->pluck(DB::raw("CONCAT(first_name, ' ', last_name)"), 'id');

        $attendance = $this->attendanceRate($tenant, $studentIds, $since);
        $homework = $this->homeworkRate($tenant, $studentIds, $since);
        $assignments = $this->assignmentRate($tenant, $studentIds, $since);

        // Activity log counts from lms_student_engagement
        $activityCounts = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('lms_student_engagement')) {
            $activityRows = DB::table('lms_student_engagement')
                ->whereIn('student_id', $studentIds)
                ->where('created_at', '>=', $since)
                ->where(function ($q) use ($tenant) {
                    $q->where('sub_institute_id', $tenant)->orWhereNull('sub_institute_id');
                })
                ->selectRaw('student_id, activity_type, COUNT(*) as c, SUM(time_spent_seconds) as total_time')
                ->groupBy('student_id', 'activity_type')
                ->get();

            foreach ($activityRows as $row) {
                $sid = (int) $row->student_id;
                $activityCounts[$sid][$row->activity_type] = (int) $row->c;
                $activityCounts[$sid]['total_time_spent'] = ($activityCounts[$sid]['total_time_spent'] ?? 0) + (int) $row->total_time;
                $activityCounts[$sid]['total_events'] = ($activityCounts[$sid]['total_events'] ?? 0) + (int) $row->c;
            }
        }

        // Doubt / Discussion participation from lms_doubt & lms_doubt_conversation
        $doubtsByStudent = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('lms_doubt')) {
            $doubtRows = DB::table('lms_doubt')
                ->where('sub_institute_id', $tenant)
                ->whereIn('user_id', $studentIds)
                ->where('created_at', '>=', $since)
                ->selectRaw('id, user_id, title, created_at')
                ->get();

            $doubtIds = $doubtRows->pluck('id')->all();
            $repliedDoubtIds = [];
            if (!empty($doubtIds) && \Illuminate\Support\Facades\Schema::hasTable('lms_doubt_conversation')) {
                $repliedDoubtIds = DB::table('lms_doubt_conversation')
                    ->whereIn('doubt_id', $doubtIds)
                    ->distinct()
                    ->pluck('doubt_id')
                    ->all();
            }

            foreach ($doubtRows as $d) {
                $sid = (int) $d->user_id;
                $hasReply = in_array($d->id, $repliedDoubtIds, true);
                $doubtsByStudent[$sid]['total_doubts'] = ($doubtsByStudent[$sid]['total_doubts'] ?? 0) + 1;
                if (!$hasReply) {
                    $doubtsByStudent[$sid]['unanswered_doubts'] = ($doubtsByStudent[$sid]['unanswered_doubts'] ?? 0) + 1;
                }
            }
        }

        $profiles = [];
        foreach ($studentIds as $id) {
            $att = $attendance[$id] ?? null;
            $hw = $homework[$id] ?? null;
            $assign = $assignments[$id] ?? null;
            $act = $activityCounts[$id] ?? ['total_events' => 0, 'total_time_spent' => 0];
            $dbt = $doubtsByStudent[$id] ?? ['total_doubts' => 0, 'unanswered_doubts' => 0];

            $deficits = [];
            if ($att !== null && $att < 75) {
                $deficits[] = "Attendance below benchmark ({$att}%)";
            }
            if ($hw !== null && $hw < 60) {
                $deficits[] = "Homework completion critical ({$hw}%)";
            }
            if ($assign !== null && $assign < 50) {
                $deficits[] = "Assignment progress lagging ({$assign}%)";
            }
            if (($act['total_events'] ?? 0) === 0) {
                $deficits[] = "Zero digital content activity recorded in window";
            }
            if (($dbt['unanswered_doubts'] ?? 0) > 0) {
                $deficits[] = "{$dbt['unanswered_doubts']} unanswered doubt(s) in discussion feed";
            }

            $primaryRisk = !empty($deficits) ? $deficits[0] : 'General engagement observation';

            $profiles[] = [
                'student_id' => $id,
                'student_name' => $names[$id] ?? "Student #{$id}",
                'attendance_percent' => $att,
                'homework_percent' => $hw,
                'assignments_percent' => $assign,
                'total_activity_events' => $act['total_events'] ?? 0,
                'total_time_spent_seconds' => $act['total_time_spent'] ?? 0,
                'activity_by_type' => array_filter($act, fn ($k) => !in_array($k, ['total_events', 'total_time_spent']), ARRAY_FILTER_USE_KEY),
                'total_doubts' => $dbt['total_doubts'] ?? 0,
                'unanswered_doubts' => $dbt['unanswered_doubts'] ?? 0,
                'detected_deficits' => $deficits,
                'primary_disengagement_vector' => $primaryRisk,
                'profiled_at' => Carbon::now()->toIso8601String(),
            ];
        }

        return [
            'sub_institute_id' => $tenant,
            'window_days' => $daysBack,
            'total_profiled' => count($profiles),
            'profiles' => $profiles,
        ];
    }

    /**
     * Drafts a neutral, factual re-engagement intervention note for educator review and approval.
     */
    public function draftIntervention(McpRequestContext $context, array $args): array
    {
        $tenant = (int) $context->selectedInstituteId;
        $studentId = (int) ($args['student_id'] ?? 0);
        $studentName = (string) ($args['student_name'] ?? '');

        if (!$studentName && $studentId > 0) {
            $resolved = DB::table('tblstudent')->where('id', $studentId)->first();
            if ($resolved) {
                $studentName = trim($resolved->first_name . ' ' . $resolved->last_name);
            }
        }
        if (!$studentName) {
            $studentName = "Student #{$studentId}";
        }

        $att = isset($args['attendance_percent']) && is_numeric($args['attendance_percent']) ? (float) $args['attendance_percent'] : null;
        $hw = isset($args['homework_percent']) && is_numeric($args['homework_percent']) ? (float) $args['homework_percent'] : null;
        $assign = isset($args['assignments_percent']) && is_numeric($args['assignments_percent']) ? (float) $args['assignments_percent'] : null;
        $unansweredDoubts = (int) ($args['unanswered_doubts_count'] ?? 0);
        $deficits = (array) ($args['deficiency_reasons'] ?? []);

        // Compose evidence narrative
        $evidencePoints = [];
        if ($att !== null) {
            $evidencePoints[] = "Attendance rate: {$att}%";
        }
        if ($hw !== null) {
            $evidencePoints[] = "Homework submission rate: {$hw}%";
        }
        if ($assign !== null) {
            $evidencePoints[] = "LMS assignment progress: {$assign}%";
        }
        if ($unansweredDoubts > 0) {
            $evidencePoints[] = "{$unansweredDoubts} unanswered question(s) posted in social discussions";
        }

        // Recommend educator action
        $recommendedActions = [];
        if ($hw !== null && $hw < 60) {
            $recommendedActions[] = "Schedule homework check-in to review barriers with recent assignments";
        }
        if ($att !== null && $att < 75) {
            $recommendedActions[] = "Consult class attendance coordinator to confirm verified absence reasons";
        }
        if ($unansweredDoubts > 0) {
            $recommendedActions[] = "Prompt subject teacher or assigned teaching assistant to reply to open doubts";
        }
        if (empty($recommendedActions)) {
            $recommendedActions[] = "Conduct informal check-in regarding recent learning module progress";
        }

        $evidenceText = implode('; ', $evidencePoints) ?: 'Engagement signals scanned over window';
        $actionsText = implode("\n- ", $recommendedActions);

        $draftBody = "Re-Engagement Intervention Proposal for {$studentName} (ID: {$studentId}):\n\n"
            . "Recorded Platform Evidence:\n"
            . "- {$evidenceText}\n\n"
            . "Recommended Educator Actions:\n"
            . "- {$actionsText}\n\n"
            . "Policy Compliance: Gated Draft Only. No automated communication has been sent to the student or family. Human educator review and approval required before execution.";

        return [
            'sub_institute_id' => $tenant,
            'student_id' => $studentId,
            'student_name' => $studentName,
            'category' => 're_engagement_intervention',
            'subject' => "Re-Engagement Plan: {$studentName}",
            'evidence_summary' => $evidenceText,
            'recommended_actions' => $recommendedActions,
            'draft_body' => $draftBody,
            'status' => 'draft_pending_human_approval',
            'max_verb' => 'recommend',
            'may_execute_actions' => 0,
            'created_at' => Carbon::now()->toIso8601String(),
        ];
    }
}
