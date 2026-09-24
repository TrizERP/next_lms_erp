<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Assignment, Worksheet and Project status, read from the one table that carries all
 * three.
 *
 * Confirmed against `2026_09_19_130000_add_work_type_to_lms_assignment_table.php`:
 * Worksheet and Project are `lms_assignment` rows with `work_type = 'worksheet'` /
 * `'project'` — "assigned, submitted, annotated and graded exactly the way assignments
 * are — same screens, same student inbox, same flow" — not separate tables. `work_type`
 * defaults to `'assignment'`, which is also what Assignment Submission and Annotate
 * Assignment read, via `LmsAssignmentApiController::index()` /
 * `::submissionList()` / `::annotateList()`. This service mirrors that controller's own
 * scope and column names rather than inventing new ones.
 *
 * `student_submission_status` and `teacher_submission_status` are the legacy `char(4)`
 * Y/N flags those methods already filter on — carried through unchanged, not replaced
 * with a guess at what "submitted" means.
 *
 * `work_type` IS NOT ASSUMED TO EXIST. That migration is `Pending` on at least one real
 * estate (confirmed via `php artisan migrate:status`, not assumed from the migration
 * file) — an unrelated feature's schema change this service has no business running on
 * its own authority. Every read below checks `Schema::hasColumn()` first and, when it is
 * absent, reports everything under the single `assignment` bucket the column's own
 * default value would produce — never a query against a column that is not there.
 */
class AssignmentReportService
{
    /**
     * @param  array<string, mixed>  $filters  standard_id, division_id, subject_id,
     *                                         student_id, work_type, limit.
     * @return array<string, mixed>
     */
    public function status(McpRequestContext $context, array $filters = []): array
    {
        if (! Schema::hasTable('lms_assignment')) {
            return ['total' => 0, 'by_work_type' => [], 'note' => 'No assignments, worksheets or projects are recorded in this estate.'];
        }

        $hasWorkType = Schema::hasColumn('lms_assignment', 'work_type');

        if (! $hasWorkType && ! empty($filters['work_type']) && $filters['work_type'] !== 'assignment') {
            // The estate has not run the migration that lets a row be anything other than
            // an assignment, so a worksheet/project filter can only ever match nothing —
            // said honestly rather than silently returning an assignment's rows for it.
            return ['total' => 0, 'by_work_type' => [], 'not_submitted' => [],
                'note' => "This estate has not yet enabled Worksheet/Project as a work type; every row on file is an 'assignment'."];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);

        $base = $this->scoped($context, $filters, $hasWorkType);

        $workTypeExpr = $hasWorkType ? "COALESCE(a.work_type, 'assignment')" : "'assignment'";

        $totals = (clone $base)
            ->selectRaw("{$workTypeExpr} AS work_type,
                COUNT(*) AS total,
                SUM(CASE WHEN a.student_submission_status = 'Y' THEN 1 ELSE 0 END) AS submitted,
                SUM(CASE WHEN a.student_submission_status = 'Y' AND a.teacher_submission_status = 'Y' THEN 1 ELSE 0 END) AS reviewed,
                SUM(CASE WHEN a.student_submission_status = 'Y' AND (a.teacher_submission_status IS NULL OR a.teacher_submission_status <> 'Y') THEN 1 ELSE 0 END) AS pending_review,
                SUM(CASE WHEN a.student_submission_status IS NULL OR a.student_submission_status <> 'Y' THEN 1 ELSE 0 END) AS not_submitted")
            ->groupBy('work_type')
            ->get()
            ->map(fn ($row) => [
                'work_type' => $row->work_type,
                'total' => (int) $row->total,
                'submitted' => (int) $row->submitted,
                'reviewed' => (int) $row->reviewed,
                'pending_review' => (int) $row->pending_review,
                'not_submitted' => (int) $row->not_submitted,
            ])
            ->all();

        $notSubmitted = (clone $base)
            ->where(function ($query) {
                $query->whereNull('a.student_submission_status')->orWhere('a.student_submission_status', '<>', 'Y');
            })
            ->leftJoin('tblstudent as s', 's.id', '=', 'a.student_id')
            ->leftJoin('subject as sub', 'sub.id', '=', 'a.subject_id')
            ->selectRaw("a.id, a.student_id,
                TRIM(CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name)) AS student_name,
                a.title, {$workTypeExpr} AS work_type, sub.subject_name,
                a.standard_id, a.division_id, a.created_date, a.submission_date")
            ->orderByDesc('a.created_date')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'assignment_id' => (int) $row->id,
                'student_id' => $row->student_id ? (int) $row->student_id : null,
                'student_name' => trim((string) $row->student_name) ?: null,
                'title' => $row->title,
                'work_type' => $row->work_type,
                'subject_name' => $row->subject_name,
                'standard_id' => $row->standard_id ? (int) $row->standard_id : null,
                'division_id' => $row->division_id ? (int) $row->division_id : null,
                'assigned_on' => $row->created_date,
                'due_on' => $row->submission_date,
            ])
            ->all();

        return [
            'total' => array_sum(array_column($totals, 'total')),
            'by_work_type' => $totals,
            'not_submitted' => $notSubmitted,
            'rule' => "Submitted means student_submission_status = 'Y'; reviewed means the teacher has additionally "
                . "set teacher_submission_status = 'Y'. Worksheet and Project are the same table filtered by "
                . 'work_type, not separate records.',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function scoped(McpRequestContext $context, array $filters, bool $hasWorkType)
    {
        return DB::table('lms_assignment as a')
            ->where('a.sub_institute_id', $context->selectedInstituteId)
            ->when($context->academicYear !== null, fn ($query) => $query->where('a.syear', $context->academicYear))
            ->when(! empty($filters['standard_id']), fn ($query) => $query->where('a.standard_id', (int) $filters['standard_id']))
            ->when(! empty($filters['division_id']), fn ($query) => $query->where('a.division_id', (int) $filters['division_id']))
            ->when(! empty($filters['subject_id']), fn ($query) => $query->where('a.subject_id', (int) $filters['subject_id']))
            ->when(! empty($filters['student_id']), fn ($query) => $query->where('a.student_id', (int) $filters['student_id']))
            // Guarded by $hasWorkType, not just a truthy filter: status() already refused a
            // non-'assignment' filter above when the column is absent, but a filter of
            // exactly 'assignment' must still reach every row rather than being applied
            // against a column that does not exist.
            ->when($hasWorkType && ! empty($filters['work_type']), fn ($query) => $query->where('a.work_type', (string) $filters['work_type']));
    }
}
