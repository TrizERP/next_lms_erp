<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parent-teacher meetings, as the two PTM tables actually record them.
 *
 * `ptm_time_slots_master` is the schedule: one row per from/to pair, carrying the
 * meeting's title, its date and the class it was opened for. `ptm_booking_master` is
 * what happened: one row per student booked into a slot, with the confirmation the
 * family gave and the attendance a teacher later recorded.
 *
 * The two are reported separately and deliberately so. A slot with no bookings is a
 * meeting nobody took up; a booking with no attendance status is a meeting whose
 * outcome has not been recorded yet. Folding them into one number would turn both of
 * those into "0 attended", which is a different and much worse claim.
 *
 * ATTENDANCE IS 'Yes' OR 'No' OR NOTHING
 *
 * `PTM_ATTENDED_STATUS` is the PTM register's own two values, and it stays empty until
 * a teacher saves the register. So "not recorded" is counted in its own bucket rather
 * than being read as an absence: the school has not said the parent failed to turn up,
 * only that nobody has written it down.
 *
 * SCOPING
 *
 * The institute comes from the caller's token, never from an argument. The slot table
 * carries `syear`, so the schedule is filtered by the caller's academic year when the
 * session has one; the booking table has no year column of its own and is reached
 * through its slot, which does.
 */
class PtmMeetingService
{
    /** The two values the PTM attendance register offers. Anything else is unrecognised. */
    private const ATTENDED_YES = 'yes';

    private const ATTENDED_NO = 'no';

    /**
     * The meetings that have been scheduled, newest first, with their take-up.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function meetings(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('ptm_time_slots_master')) {
            return [
                'count' => 0,
                'meetings' => [],
                'note' => 'Parent-teacher meetings are not recorded in this estate.',
            ];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);

        $query = DB::table('ptm_time_slots_master as slot')
            ->leftJoin('standard as std', 'std.id', '=', 'slot.standard_id')
            ->leftJoin('division as div', 'div.id', '=', 'slot.division_id')
            ->where('slot.sub_institute_id', $context->selectedInstituteId);

        if ($context->academicYear !== null) {
            $query->where('slot.syear', $context->academicYear);
        }

        foreach (['standard_id' => 'slot.standard_id', 'division_id' => 'slot.division_id'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, (int) $filters[$filter]);
            }
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('slot.ptm_date', '>=', (string) $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('slot.ptm_date', '<=', (string) $filters['to_date']);
        }

        if (! empty($filters['title'])) {
            $query->where('slot.title', 'like', '%'.$filters['title'].'%');
        }

        // Counted before the limit, so a page of fifty is never read as the whole term's
        // programme. The same rule `students.directory` follows.
        $total = (clone $query)->count();

        $rows = $query
            ->selectRaw(
                'slot.id, slot.title, slot.ptm_date, slot.from_time, slot.to_time,
                 slot.standard_id, slot.division_id,
                 std.name AS standard_name, div.name AS division_name'
            )
            ->orderByDesc('slot.ptm_date')
            ->orderBy('slot.from_time')
            ->limit($limit)
            ->get();

        $bookings = $this->bookingTotalsBySlot($context, $rows->pluck('id')->all());
        $empty = ['booked' => 0, 'attended' => 0, 'did_not_attend' => 0, 'not_recorded' => 0];

        $meetings = [];

        foreach ($rows as $row) {
            $counts = $bookings[(int) $row->id] ?? $empty;

            $meetings[] = [
                'slot_id' => (int) $row->id,
                'title' => $row->title,
                'ptm_date' => $row->ptm_date,
                'from_time' => $row->from_time,
                'to_time' => $row->to_time,
                'standard_id' => $row->standard_id === null ? null : (int) $row->standard_id,
                'standard_name' => $row->standard_name,
                'division_id' => $row->division_id === null ? null : (int) $row->division_id,
                'division_name' => $row->division_name,
                'students_booked' => $counts['booked'],
                'attended' => $counts['attended'],
                'did_not_attend' => $counts['did_not_attend'],
                // Its own bucket. See the note at the top: an unsaved register is not a
                // parent who stayed away.
                'attendance_not_recorded' => $counts['not_recorded'],
            ];
        }

        return [
            'count' => $total,
            'row_count' => count($meetings),
            'academic_year' => $context->academicYear,
            'meetings' => $meetings,
            'rule' => 'Attendance is counted only where a teacher recorded it. A booking with no '
                .'status recorded is reported as not recorded, never as a parent who did not attend.',
        ];
    }

    /**
     * The individual bookings, with the family, the slot and what was recorded.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function bookings(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('ptm_booking_master') || ! Schema::hasTable('ptm_time_slots_master')) {
            return [
                'count' => 0,
                'bookings' => [],
                'note' => 'Parent-teacher meeting bookings are not recorded in this estate.',
            ];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);

        $query = $this->bookingQuery($context);

        if (! empty($filters['slot_id'])) {
            $query->where('booking.TIME_SLOT_ID', (int) $filters['slot_id']);
        }

        if (! empty($filters['student_id'])) {
            $query->where('booking.STUDENT_ID', (int) $filters['student_id']);
        }

        foreach (['standard_id' => 'slot.standard_id', 'division_id' => 'slot.division_id'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, (int) $filters[$filter]);
            }
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('booking.DATE', '>=', (string) $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('booking.DATE', '<=', (string) $filters['to_date']);
        }

        // 'yes' | 'no' | 'not_recorded' - the three real states, named rather than left
        // to the caller to reconstruct from a nullable column.
        $attended = strtolower(trim((string) ($filters['attended'] ?? '')));

        if ($attended === self::ATTENDED_YES || $attended === self::ATTENDED_NO) {
            $query->whereRaw('LOWER(TRIM(COALESCE(booking.PTM_ATTENDED_STATUS, ?))) = ?', ['', $attended]);
        } elseif ($attended === 'not_recorded') {
            $query->whereRaw('TRIM(COALESCE(booking.PTM_ATTENDED_STATUS, ?)) = ?', ['', '']);
        }

        $total = (clone $query)->count();

        $rows = $query
            ->selectRaw(
                "booking.ID, booking.DATE, booking.STUDENT_ID, booking.TIME_SLOT_ID,
                 booking.CONFIRM_STATUS, booking.PTM_ATTENDED_STATUS, booking.PTM_ATTENDED_REMARKS,
                 booking.PTM_ATTENDED_ENTRY_DATE,
                 slot.title AS slot_title, slot.from_time, slot.to_time, slot.ptm_date,
                 std.name AS standard_name, div.name AS division_name,
                 CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                 s.enrollment_no, s.mobile"
            )
            ->orderByDesc('booking.DATE')
            ->orderByDesc('booking.ID')
            ->limit($limit)
            ->get();

        $bookings = [];
        $attendedCount = 0;
        $missedCount = 0;
        $unrecordedCount = 0;

        foreach ($rows as $row) {
            $status = strtolower(trim((string) $row->PTM_ATTENDED_STATUS));

            if ($status === self::ATTENDED_YES) {
                $attendedCount++;
            } elseif ($status === self::ATTENDED_NO) {
                $missedCount++;
            } else {
                $unrecordedCount++;
            }

            $bookings[] = [
                'booking_id' => (int) $row->ID,
                'student_id' => $row->STUDENT_ID === null ? null : (int) $row->STUDENT_ID,
                'student_name' => trim((string) $row->student_name) ?: null,
                'enrollment_no' => $row->enrollment_no,
                'mobile' => $row->mobile,
                'standard_name' => $row->standard_name,
                'division_name' => $row->division_name,
                'slot_id' => $row->TIME_SLOT_ID === null ? null : (int) $row->TIME_SLOT_ID,
                'slot_title' => $row->slot_title,
                'meeting_date' => $row->DATE ?? $row->ptm_date,
                'from_time' => $row->from_time,
                'to_time' => $row->to_time,
                'confirm_status' => $row->CONFIRM_STATUS,
                // Null rather than 'No'. The column is empty until somebody saves.
                'attended_status' => trim((string) $row->PTM_ATTENDED_STATUS) ?: null,
                'attended_remarks' => $row->PTM_ATTENDED_REMARKS,
                'attendance_recorded_on' => $row->PTM_ATTENDED_ENTRY_DATE,
            ];
        }

        return [
            'count' => $total,
            'row_count' => count($bookings),
            'attended' => $attendedCount,
            'did_not_attend' => $missedCount,
            'attendance_not_recorded' => $unrecordedCount,
            'academic_year' => $context->academicYear,
            'bookings' => $bookings,
            'rule' => 'The three counts describe the rows returned. `count` is every booking matching '
                .'the filters before the limit was applied.',
        ];
    }

    /**
     * Booking totals per slot, for the schedule view.
     *
     * One grouped query rather than one per meeting: the schedule commonly returns fifty
     * slots, and fifty round trips to count four numbers each is how a tab that reads two
     * hundred rows takes six seconds.
     *
     * @param  array<int, mixed>  $slotIds
     * @return array<int, array{booked:int, attended:int, did_not_attend:int, not_recorded:int}>
     */
    private function bookingTotalsBySlot(McpRequestContext $context, array $slotIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $slotIds)));

        if ($ids === [] || ! Schema::hasTable('ptm_booking_master')) {
            return [];
        }

        $rows = DB::table('ptm_booking_master as booking')
            ->where('booking.SUB_INSTITUTE_ID', $context->selectedInstituteId)
            ->whereIn('booking.TIME_SLOT_ID', $ids)
            ->selectRaw(
                'booking.TIME_SLOT_ID,
                 COUNT(*) AS booked,
                 SUM(CASE WHEN LOWER(TRIM(COALESCE(booking.PTM_ATTENDED_STATUS, ?))) = ? THEN 1 ELSE 0 END) AS attended,
                 SUM(CASE WHEN LOWER(TRIM(COALESCE(booking.PTM_ATTENDED_STATUS, ?))) = ? THEN 1 ELSE 0 END) AS did_not_attend,
                 SUM(CASE WHEN TRIM(COALESCE(booking.PTM_ATTENDED_STATUS, ?)) = ? THEN 1 ELSE 0 END) AS not_recorded',
                ['', self::ATTENDED_YES, '', self::ATTENDED_NO, '', '']
            )
            ->groupBy('booking.TIME_SLOT_ID')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[(int) $row->TIME_SLOT_ID] = [
                'booked' => (int) $row->booked,
                'attended' => (int) $row->attended,
                'did_not_attend' => (int) $row->did_not_attend,
                'not_recorded' => (int) $row->not_recorded,
            ];
        }

        return $totals;
    }

    /**
     * The booking join, scoped to the caller's institute on both tables.
     *
     * Scoped twice on purpose: `ptm_booking_master` carries its own `SUB_INSTITUTE_ID`
     * and so does the slot it belongs to, and a booking whose slot belongs to another
     * school is a row this tenant must not see whichever of the two columns disagrees.
     */
    private function bookingQuery(McpRequestContext $context): Builder
    {
        $query = DB::table('ptm_booking_master as booking')
            ->join('ptm_time_slots_master as slot', 'slot.id', '=', 'booking.TIME_SLOT_ID')
            ->leftJoin('tblstudent as s', 's.id', '=', 'booking.STUDENT_ID')
            ->leftJoin('standard as std', 'std.id', '=', 'slot.standard_id')
            ->leftJoin('division as div', 'div.id', '=', 'slot.division_id')
            ->where('booking.SUB_INSTITUTE_ID', $context->selectedInstituteId)
            ->where('slot.sub_institute_id', $context->selectedInstituteId);

        if ($context->academicYear !== null) {
            $query->where('slot.syear', $context->academicYear);
        }

        return $query;
    }
}
