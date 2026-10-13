<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff punch-in / punch-out records, as `hrms_attendances` holds them.
 *
 * STAFF, NOT STUDENTS
 *
 * Student attendance is a different module over different tables. Nothing here reads it.
 *
 * WHAT THE TABLE DOES NOT SUPPORT
 *
 * A row is a punch: a day, a punch-in time and, when the person signed out, a punch-out
 * time. The table records no shift, no expected start and no leave link, so "late",
 * "absent" and "on leave" cannot be derived from it and are never reported. A person with
 * no row for a day may have been on leave, on a holiday, or never punched. The only gap it
 * can state truthfully is a row with a punch-in and NO punch-out.
 *
 * The 584k-row table is always read inside a date window (default: the last 30 days) so a
 * question never scans the whole history.
 *
 * SCOPING
 *
 * `sub_institute_id` from the caller's token on the punch and on the user. Soft-deleted
 * rows are excluded.
 */
class StaffAttendanceService
{
    private const DEFAULT_WINDOW_DAYS = 30;

    /**
     * Punch records, newest first.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function records(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('hrms_attendances')) {
            return ['count' => 0, 'records' => [], 'note' => 'Staff attendance is not recorded in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        [$from, $to] = $this->window($filters);
        $query = $this->query($context, $from, $to);
        $this->applyFilters($query, $filters);

        $total = (clone $query)->count();
        $missingOut = (clone $query)->whereNull('a.punchout_time')->count();

        $rows = $query
            ->selectRaw("a.id AS punch_id, a.user_id, a.day, a.punchin_time, a.punchout_time,
                a.overtime, a.timestamp_diff AS worked,
                CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS staff_name")
            ->orderByDesc('a.day')
            ->orderByDesc('a.id')
            ->limit($limit)
            ->get();

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'from_date' => $from,
            'to_date' => $to,
            'without_punch_out' => $missingOut,
            'figures_cover' => 'every punch in the date window matching these filters, not only the rows listed',
            'records' => $rows->map(static fn ($row) => [
                'punch_id' => (int) $row->punch_id,
                'staff_id' => (int) $row->user_id,
                'staff_name' => trim((string) $row->staff_name) !== '' ? trim((string) $row->staff_name) : null,
                'day' => $row->day,
                'punch_in' => $row->punchin_time,
                'punch_out' => $row->punchout_time,
                'punch_out_recorded' => $row->punchout_time !== null,
                'time_worked' => $row->worked,
                'overtime' => $row->overtime,
            ])->all(),
            'rule' => $this->rule(),
        ];
    }

    /**
     * Punches per day: how many people punched in and how many have no punch-out.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('hrms_attendances')) {
            return ['count' => 0, 'by_day' => [], 'note' => 'Staff attendance is not recorded in this estate.'];
        }

        [$from, $to] = $this->window($filters);
        $query = $this->query($context, $from, $to);
        $this->applyFilters($query, $filters);

        $byDay = (clone $query)
            ->selectRaw('a.day AS day, COUNT(DISTINCT a.user_id) AS staff_punched_in,
                SUM(a.punchout_time IS NULL) AS without_punch_out')
            ->groupBy('a.day')
            ->orderByDesc('a.day')
            ->limit(62)
            ->get()
            ->map(static fn ($row) => [
                'day' => $row->day,
                'staff_punched_in' => (int) $row->staff_punched_in,
                'without_punch_out' => (int) $row->without_punch_out,
            ])
            ->all();

        $totals = (clone $query)
            ->selectRaw('COUNT(*) AS punches, COUNT(DISTINCT a.user_id) AS distinct_staff,
                COUNT(DISTINCT a.day) AS days_with_punches, SUM(a.punchout_time IS NULL) AS without_punch_out')
            ->first();

        return [
            'from_date' => $from,
            'to_date' => $to,
            'punches' => (int) ($totals->punches ?? 0),
            'distinct_staff' => (int) ($totals->distinct_staff ?? 0),
            'days_with_punches' => (int) ($totals->days_with_punches ?? 0),
            'without_punch_out' => (int) ($totals->without_punch_out ?? 0),
            'by_day' => $byDay,
            'rule' => $this->rule(),
        ];
    }

    private function rule(): string
    {
        return 'One row is one STAFF punch (students\' attendance is a different module). The table records no '
            .'shift, expected start time or leave link, so never say a person was late, absent or on leave: '
            .'no row for a day only means no punch was recorded. The one gap it can state is a punch-in with '
            .'no punch-out, which means no sign-out was recorded, not that the person is still on site.';
    }

    /** @return array{0:string,1:string} */
    private function window(array $filters): array
    {
        $to = trim((string) ($filters['to_date'] ?? ''));
        $from = trim((string) ($filters['from_date'] ?? ''));
        $to = $to !== '' ? $to : date('Y-m-d');
        $from = $from !== '' ? $from : date('Y-m-d', strtotime($to.' -'.self::DEFAULT_WINDOW_DAYS.' days'));

        return [$from, $to];
    }

    /** @param  array<string, mixed>  $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['staff_id'])) {
            $query->where('a.user_id', (int) $filters['staff_id']);
        }

        if (! empty($filters['only_without_punch_out'])) {
            $query->whereNull('a.punchout_time');
        }
    }

    private function query(McpRequestContext $context, string $from, string $to): Builder
    {
        $institute = $context->selectedInstituteId;

        return DB::table('hrms_attendances as a')
            ->leftJoin('tbluser as u', function ($join) use ($institute) {
                $join->on('u.id', '=', 'a.user_id')->where('u.sub_institute_id', '=', $institute);
            })
            ->where('a.sub_institute_id', $institute)
            ->whereNull('a.deleted_at')
            ->whereBetween('a.day', [$from, $to]);
    }
}
