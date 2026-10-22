<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff leave requests, as `hrms_emp_leaves` records them.
 *
 * THIS IS STAFF LEAVE, NOT STUDENT LEAVE
 *
 * Students' leave applications live in `leave_applications` and belong to the Front Desk
 * module. Nothing here reads that table, and the payload says so, so a question about a
 * pupil's absence is not answered from a teacher's leave book.
 *
 * WHAT THE TABLE DOES NOT SUPPORT
 *
 * `day_type` holds thirteen spellings in this estate ("1", "0.5", "half", "full", "2" … "11")
 * and `LeaveAnalyticsService` is the only place the entitlement rules live. So this reports
 * the date span of each request and its status, and does NOT compute a balance or a count
 * of days taken: that needs the allocation rules, and a figure invented here would
 * disagree with the Leave Summary report.
 *
 * SCOPING
 *
 * `sub_institute_id` from the caller's token on the leave row, the leave type, the
 * department and the user. Soft-deleted rows are excluded everywhere.
 */
class StaffLeaveService
{
    /**
     * Leave requests, newest first.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function requests(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('hrms_emp_leaves')) {
            return ['count' => 0, 'requests' => [], 'note' => 'Staff leave is not recorded in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $query = $this->query($context);
        $this->applyFilters($query, $filters);

        $total = (clone $query)->count();
        $byStatus = $this->byStatus(clone $query);

        $rows = $query
            ->selectRaw($this->columns())
            ->orderByDesc('l.from_date')
            ->orderByDesc('l.id')
            ->limit($limit)
            ->get();

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'pending' => $byStatus['pending'] ?? 0,
            'approved' => ($byStatus['approved'] ?? 0) + ($byStatus['approved_lwp'] ?? 0),
            'rejected' => $byStatus['rejected'] ?? 0,
            'cancelled' => $byStatus['cancelled'] ?? 0,
            'figures_cover' => 'every leave request matching these filters, not only the rows listed',
            'requests' => $rows->map(fn ($row) => $this->map($row))->all(),
            'rule' => $this->rule(),
        ];
    }

    /**
     * Requests counted by status, by leave type and by department.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('hrms_emp_leaves')) {
            return ['count' => 0, 'by_status' => [], 'note' => 'Staff leave is not recorded in this estate.'];
        }

        $query = $this->query($context);
        $this->applyFilters($query, $filters);

        $total = (clone $query)->count();

        $byStatus = (clone $query)
            ->selectRaw("COALESCE(NULLIF(TRIM(l.status), ''), '(no status recorded)') AS status, COUNT(*) AS requests")
            ->groupByRaw("COALESCE(NULLIF(TRIM(l.status), ''), '(no status recorded)')")
            ->orderByDesc('requests')
            ->get()
            ->map(static fn ($row) => ['status' => $row->status, 'requests' => (int) $row->requests])
            ->all();

        $byType = (clone $query)
            ->selectRaw("COALESCE(t.leave_type, '(leave type not on file)') AS leave_type, COUNT(*) AS requests")
            ->groupByRaw("COALESCE(t.leave_type, '(leave type not on file)')")
            ->orderByDesc('requests')
            ->limit(20)
            ->get()
            ->map(static fn ($row) => ['leave_type' => $row->leave_type, 'requests' => (int) $row->requests])
            ->all();

        $byDepartment = (clone $query)
            ->selectRaw("COALESCE(d.department, '(no department)') AS department, COUNT(*) AS requests")
            ->groupByRaw("COALESCE(d.department, '(no department)')")
            ->orderByDesc('requests')
            ->limit(20)
            ->get()
            ->map(static fn ($row) => ['department' => $row->department, 'requests' => (int) $row->requests])
            ->all();

        return [
            'count' => $total,
            'by_status' => $byStatus,
            'by_leave_type' => $byType,
            'by_department' => $byDepartment,
            'rule' => $this->rule(),
        ];
    }

    private function rule(): string
    {
        return 'One row is one STAFF leave request; students\' leave applications are a different module and '
            .'are not here. Status is one of pending, approved, approved_lwp (approved without pay), rejected '
            .'or cancelled. This reports the dates requested and the status only: it does NOT compute leave '
            .'balances or days taken, because entitlement depends on allocation rules held elsewhere, so never '
            .'state a balance or a number of days remaining. No reason text is returned; do not guess why '
            .'leave was requested or refused.';
    }

    /** @param  array<string, mixed>  $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        $status = strtolower(trim((string) ($filters['status'] ?? 'any')));

        if ($status !== '' && $status !== 'any') {
            $query->where('l.status', $status);
        }

        if (! empty($filters['staff_id'])) {
            $query->where('l.user_id', (int) $filters['staff_id']);
        }

        if (! empty($filters['department_id'])) {
            $query->where('l.department_id', (int) $filters['department_id']);
        }

        if (! empty($filters['leave_type_id'])) {
            $query->where('l.leave_type_id', (int) $filters['leave_type_id']);
        }

        // A request overlaps the window when it ends on or after the window starts and
        // starts on or before it ends — not only when it starts inside it.
        $from = trim((string) ($filters['from_date'] ?? ''));
        $to = trim((string) ($filters['to_date'] ?? ''));

        if ($from !== '') {
            $query->whereDate('l.to_date', '>=', $from);
        }

        if ($to !== '') {
            $query->whereDate('l.from_date', '<=', $to);
        }
    }

    /** @return array<string, int> */
    private function byStatus(Builder $query): array
    {
        return $query->selectRaw('l.status AS status, COUNT(*) AS n')
            ->groupBy('l.status')
            ->get()
            ->mapWithKeys(static fn ($row) => [strtolower(trim((string) $row->status)) => (int) $row->n])
            ->all();
    }

    private function query(McpRequestContext $context): Builder
    {
        $institute = $context->selectedInstituteId;

        return DB::table('hrms_emp_leaves as l')
            ->leftJoin('tbluser as u', function ($join) use ($institute) {
                $join->on('u.id', '=', 'l.user_id')->where('u.sub_institute_id', '=', $institute);
            })
            ->leftJoin('hrms_leave_types as t', function ($join) use ($institute) {
                $join->on('t.id', '=', 'l.leave_type_id')
                    ->where('t.sub_institute_id', '=', $institute)
                    ->whereNull('t.deleted_at');
            })
            ->leftJoin('hrms_departments as d', function ($join) use ($institute) {
                $join->on('d.id', '=', 'l.department_id')
                    ->where('d.sub_institute_id', '=', $institute)
                    ->whereNull('d.deleted_at');
            })
            ->where('l.sub_institute_id', $institute)
            ->whereNull('l.deleted_at');
    }

    private function columns(): string
    {
        return "l.id AS request_id, l.user_id, l.status, l.day_type, l.from_date, l.to_date,
                DATEDIFF(l.to_date, l.from_date) + 1 AS date_span_days,
                t.leave_type, d.department,
                CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS staff_name,
                l.created_at AS applied_on";
    }

    /** @return array<string, mixed> */
    private function map(object $row): array
    {
        return [
            'request_id' => (int) $row->request_id,
            'staff_id' => $row->user_id === null ? null : (int) $row->user_id,
            'staff_name' => trim((string) $row->staff_name) !== '' ? trim((string) $row->staff_name) : null,
            'department' => $row->department,
            'leave_type' => $row->leave_type,
            'status' => $row->status,
            'day_type' => $row->day_type,
            'from_date' => $row->from_date,
            'to_date' => $row->to_date,
            'date_span_days' => $row->date_span_days === null ? null : (int) $row->date_span_days,
            'applied_on' => $row->applied_on,
        ];
    }
}
