<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hostel occupancy, allocation and free rooms, from the hostel tables themselves.
 *
 * THE SHAPE OF A HOSTEL IN THIS ESTATE
 *
 *   hostel_type_master -> hostel_master -> hostel_building_master
 *                                       -> hostel_floor_master -> hostel_room_master
 *
 * and `hostel_room_allocation` points at a hostel and a room for one occupant in one
 * academic year. Every one of those tables carries `sub_institute_id`, and the joins
 * below carry it through every hop, exactly as `hostel_reportController` does - a room
 * on another school's floor is not this school's room even if the ids happen to line up.
 *
 * A ROOM HAS NO BED COUNT, SO CAPACITY IS NEVER REPORTED
 *
 * `hostel_room_master` holds an id, a floor and a name. There is no capacity column
 * anywhere in the schema, and `hostel_room_allocation.bed_no` is a label the warden
 * typed, not a seat in a known total. So this service reports how many people are
 * allocated to a room and whether a room has anyone in it at all. It does not report
 * "3 of 4 beds used", because the 4 does not exist and inventing it would put a
 * fabricated capacity onto an occupancy report somebody plans a term around.
 *
 * "AVAILABLE" MEANS UNALLOCATED THIS YEAR
 *
 * `availableRooms()` mirrors `hostel_reportController::roomReport()`: a room with no
 * allocation row for this institute and this academic year. That is the estate's own
 * definition of a free room and the one the existing report already prints.
 *
 * OCCUPANTS MAY BE STUDENTS OR STAFF
 *
 * `hostel_room_allocation.user_group_id` is a `tbluserprofilemaster` id, and the
 * existing report branches on whether that profile is Student. The allocation rows here
 * resolve a student name when the occupant is a student and a staff name when they are
 * not, and say which - rather than joining blindly to `tblstudent` and silently
 * dropping every warden and matron in the building.
 */
class HostelOccupancyService
{
    /**
     * Every hostel, with how many rooms it has and how many are occupied this year.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function occupancy(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('hostel_master')) {
            return [
                'count' => 0,
                'hostels' => [],
                'note' => 'Hostels are not recorded in this estate.',
            ];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);

        $query = DB::table('hostel_master as h')
            ->leftJoin('hostel_type_master as t', function ($join) {
                $join->on('t.id', '=', 'h.hostel_type_id')
                    ->on('t.sub_institute_id', '=', 'h.sub_institute_id');
            })
            ->where('h.sub_institute_id', $context->selectedInstituteId);

        if (! empty($filters['hostel_id'])) {
            $query->where('h.id', (int) $filters['hostel_id']);
        }

        if (! empty($filters['hostel_name'])) {
            $query->where('h.name', 'like', '%'.$filters['hostel_name'].'%');
        }

        $total = (clone $query)->count();

        $rows = $query
            ->selectRaw('h.id, h.code, h.name, h.description, h.warden, h.warden_contact, t.hostel_type')
            ->orderBy('h.name')
            ->limit($limit)
            ->get();

        $hostelIds = $rows->pluck('id')->map('intval')->all();
        $rooms = $this->roomTotalsByHostel($context, $hostelIds);
        $allocated = $this->allocationTotalsByHostel($context, $hostelIds);

        $hostels = [];
        $unresolvedRooms = 0;

        foreach ($rows as $row) {
            $hostelId = (int) $row->id;
            $roomCount = $rooms[$hostelId]['rooms'] ?? 0;
            $occupiedRooms = $allocated[$hostelId]['rooms_occupied'] ?? 0;
            $strayRooms = $allocated[$hostelId]['rooms_not_in_this_institute'] ?? 0;
            $unresolvedRooms += $strayRooms;

            $hostels[] = [
                'hostel_id' => $hostelId,
                'code' => $row->code,
                'name' => $row->name,
                'description' => $row->description,
                'hostel_type' => $row->hostel_type,
                'warden' => $row->warden,
                'warden_contact' => $row->warden_contact,
                'rooms' => $roomCount,
                'rooms_occupied' => $occupiedRooms,
                // Subtraction of two figures counted the same way. `rooms_occupied` counts
                // only rooms this institute owns, so this can never come out negative and
                // never reports more rooms full than the hostel has.
                'rooms_unoccupied' => max($roomCount - $occupiedRooms, 0),
                'occupants_allocated' => $allocated[$hostelId]['occupants'] ?? 0,
                // Allocations pointing at a room that is not on this institute's own floors.
                // Reported rather than counted into occupancy or dropped: it is a real state
                // in this estate's data and the warden is the person who can fix it.
                'allocations_to_rooms_not_in_this_institute' => $allocated[$hostelId]['occupants_unresolved_room'] ?? 0,
            ];
        }

        return [
            'count' => $total,
            'row_count' => count($hostels),
            'academic_year' => $context->academicYear,
            'hostels' => $hostels,
            'rule' => 'Rooms have no recorded bed capacity in this estate, so occupancy is reported as '
                .'rooms occupied and people allocated. No percentage of capacity is calculated. '
                .'`rooms_occupied` counts only rooms belonging to this institute; an allocation naming '
                .'a room on another institute\'s floor is reported separately and never counted as '
                .'occupancy here.',
            'data_note' => $unresolvedRooms === 0
                ? null
                : 'Some allocations name a room that is not on this institute\'s own floors. Those rooms '
                    .'are excluded from the occupancy counts and reported per hostel instead.',
        ];
    }

    /**
     * Who is allocated where, one row per allocation.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function allocations(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('hostel_room_allocation')) {
            return [
                'count' => 0,
                'allocations' => [],
                'note' => 'Hostel room allocation is not recorded in this estate.',
            ];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);

        $query = $this->allocationQuery($context);

        foreach ([
            'hostel_id' => 'a.hostel_id',
            'room_id' => 'a.room_id',
            'admission_category_id' => 'a.admission_category_id',
        ] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, (int) $filters[$filter]);
            }
        }

        if (! empty($filters['student_id'])) {
            // `user_id` is the occupant. For a student allocation it is `tblstudent.id`.
            $query->where('a.user_id', (int) $filters['student_id']);
        }

        $total = (clone $query)->count();

        $rows = $query
            ->selectRaw(
                "a.id, a.user_id, a.user_group_id, a.admission_category_id, a.hostel_id, a.room_id,
                 a.bed_no, a.locker_no, a.table_no, a.bedsheet_no, a.syear, a.created_on,
                 h.name AS hostel_name, h.code AS hostel_code,
                 r.room_name, f.floor_name, b.building_name,
                 c.title AS admission_category,
                 p.name AS occupant_profile,
                 CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                 s.enrollment_no, s.gender,
                 CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS staff_name"
            )
            ->orderBy('h.name')
            ->orderBy('r.room_name')
            ->limit($limit)
            ->get();

        $allocations = [];

        foreach ($rows as $row) {
            $studentName = trim((string) $row->student_name);
            $staffName = trim((string) $row->staff_name);

            // `user_group_id` is the profile the allocation was made under, and it is what
            // `hostel_reportController` itself branches on. It decides first, because
            // `tblstudent.id` and `tbluser.id` are separate sequences and an id present in
            // both would otherwise resolve to whichever name was selected first - reporting
            // a warden under a child's name, or the reverse.
            $profile = strtolower(trim((string) $row->occupant_profile));
            $isStudent = $profile === ''
                ? $studentName !== ''
                : (str_contains($profile, 'student') && $studentName !== '');

            $allocations[] = [
                'allocation_id' => (int) $row->id,
                'occupant_id' => (int) $row->user_id,
                'occupant_name' => $isStudent ? $studentName : ($staffName ?: null),
                'occupant_kind' => $isStudent ? 'student' : ($staffName !== '' ? 'staff' : 'unresolved'),
                'occupant_profile' => $row->occupant_profile,
                'enrollment_no' => $isStudent ? $row->enrollment_no : null,
                'gender' => $isStudent ? $row->gender : null,
                'hostel_id' => (int) $row->hostel_id,
                'hostel_code' => $row->hostel_code,
                'hostel_name' => $row->hostel_name,
                'building_name' => $row->building_name,
                'floor_name' => $row->floor_name,
                'room_id' => (int) $row->room_id,
                'room_name' => $row->room_name,
                'bed_no' => $row->bed_no,
                'locker_no' => $row->locker_no,
                'table_no' => $row->table_no,
                'bedsheet_no' => $row->bedsheet_no,
                'admission_category' => $row->admission_category,
                'academic_year' => $row->syear === null ? null : (int) $row->syear,
                'allocated_on' => $row->created_on,
            ];
        }

        return [
            'count' => $total,
            'row_count' => count($allocations),
            'academic_year' => $context->academicYear,
            'allocations' => $allocations,
            'rule' => 'An occupant is reported as `unresolved` when the allocation names a user id that '
                .'is neither a student nor a staff record in this institute, rather than being dropped.',
        ];
    }

    /**
     * Rooms with nobody allocated to them this academic year.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function availableRooms(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('hostel_room_master')) {
            return [
                'count' => 0,
                'rooms' => [],
                'note' => 'Hostel rooms are not recorded in this estate.',
            ];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);

        $query = DB::table('hostel_room_master as r')
            ->join('hostel_floor_master as f', function ($join) {
                $join->on('f.id', '=', 'r.floor_id')
                    ->on('f.sub_institute_id', '=', 'r.sub_institute_id');
            })
            ->join('hostel_building_master as b', function ($join) {
                $join->on('b.id', '=', 'f.building_id')
                    ->on('b.sub_institute_id', '=', 'f.sub_institute_id');
            })
            ->join('hostel_master as h', function ($join) {
                $join->on('h.id', '=', 'b.hostel_id')
                    ->on('h.sub_institute_id', '=', 'b.sub_institute_id');
            })
            ->leftJoin('hostel_type_master as t', function ($join) {
                $join->on('t.id', '=', 'h.hostel_type_id')
                    ->on('t.sub_institute_id', '=', 'h.sub_institute_id');
            })
            ->where('r.sub_institute_id', $context->selectedInstituteId);

        if (! empty($filters['hostel_id'])) {
            $query->where('h.id', (int) $filters['hostel_id']);
        }

        // The estate's own definition of a free room, from `roomReport()`: no allocation
        // row for this institute in this academic year. Expressed as a correlated NOT
        // EXISTS with bound values rather than an interpolated subquery.
        if (Schema::hasTable('hostel_room_allocation')) {
            $query->whereNotExists(function ($inner) use ($context) {
                $inner->selectRaw('1')
                    ->from('hostel_room_allocation as a')
                    ->whereColumn('a.room_id', 'r.id')
                    ->where('a.sub_institute_id', $context->selectedInstituteId);

                if ($context->academicYear !== null) {
                    $inner->where('a.syear', $context->academicYear);
                }
            });
        }

        $total = (clone $query)->count();

        $rows = $query
            ->selectRaw(
                'r.id, r.room_name, f.floor_name, b.building_name,
                 h.id AS hostel_id, h.name AS hostel_name, h.code AS hostel_code,
                 h.warden, h.warden_contact, t.hostel_type'
            )
            ->orderBy('h.name')
            ->orderBy('b.building_name')
            ->orderBy('f.floor_name')
            ->orderBy('r.room_name')
            ->limit($limit)
            ->get();

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'academic_year' => $context->academicYear,
            'rooms' => $rows->map(static fn ($row) => [
                'room_id' => (int) $row->id,
                'room_name' => $row->room_name,
                'floor_name' => $row->floor_name,
                'building_name' => $row->building_name,
                'hostel_id' => (int) $row->hostel_id,
                'hostel_code' => $row->hostel_code,
                'hostel_name' => $row->hostel_name,
                'hostel_type' => $row->hostel_type,
                'warden' => $row->warden,
                'warden_contact' => $row->warden_contact,
            ])->all(),
            'rule' => 'A room is available when it has no allocation for this institute in this academic '
                .'year. Rooms carry no bed capacity in this estate, so a partly filled room is not '
                .'reported as available.',
        ];
    }

    /**
     * Rooms per hostel, walked through building and floor.
     *
     * @param  array<int, int>  $hostelIds
     * @return array<int, array{rooms:int}>
     */
    private function roomTotalsByHostel(McpRequestContext $context, array $hostelIds): array
    {
        if ($hostelIds === [] || ! Schema::hasTable('hostel_room_master')) {
            return [];
        }

        $rows = DB::table('hostel_room_master as r')
            ->join('hostel_floor_master as f', function ($join) {
                $join->on('f.id', '=', 'r.floor_id')
                    ->on('f.sub_institute_id', '=', 'r.sub_institute_id');
            })
            ->join('hostel_building_master as b', function ($join) {
                $join->on('b.id', '=', 'f.building_id')
                    ->on('b.sub_institute_id', '=', 'f.sub_institute_id');
            })
            ->where('r.sub_institute_id', $context->selectedInstituteId)
            ->whereIn('b.hostel_id', $hostelIds)
            ->selectRaw('b.hostel_id, COUNT(*) AS rooms')
            ->groupBy('b.hostel_id')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[(int) $row->hostel_id] = ['rooms' => (int) $row->rooms];
        }

        return $totals;
    }

    /**
     * Occupants and distinct occupied rooms per hostel, for this academic year.
     *
     * `rooms_occupied` counts only rooms that resolve to a room record belonging to this
     * institute, because it is compared against a room total counted the same way. This
     * estate holds allocations that name rooms on another institute's floors, and counting
     * those would produce "2 of 0 rooms occupied" - a figure that is not wrong so much as
     * impossible, on a screen a warden plans a term from. They are counted separately as
     * `occupants_unresolved_room` and reported rather than hidden.
     *
     * @param  array<int, int>  $hostelIds
     * @return array<int, array{occupants:int, rooms_occupied:int, occupants_unresolved_room:int, rooms_not_in_this_institute:int}>
     */
    private function allocationTotalsByHostel(McpRequestContext $context, array $hostelIds): array
    {
        if ($hostelIds === [] || ! Schema::hasTable('hostel_room_allocation')) {
            return [];
        }

        $query = DB::table('hostel_room_allocation as a')
            ->leftJoin('hostel_room_master as r', function ($join) {
                $join->on('r.id', '=', 'a.room_id')
                    ->on('r.sub_institute_id', '=', 'a.sub_institute_id');
            })
            ->where('a.sub_institute_id', $context->selectedInstituteId)
            ->whereIn('a.hostel_id', $hostelIds);

        if ($context->academicYear !== null) {
            $query->where('a.syear', $context->academicYear);
        }

        $rows = $query
            ->selectRaw(
                'a.hostel_id,
                 COUNT(*) AS occupants,
                 COUNT(DISTINCT r.id) AS rooms_occupied,
                 SUM(CASE WHEN r.id IS NULL THEN 1 ELSE 0 END) AS occupants_unresolved_room,
                 COUNT(DISTINCT CASE WHEN r.id IS NULL THEN a.room_id END) AS rooms_not_in_this_institute'
            )
            ->groupBy('a.hostel_id')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[(int) $row->hostel_id] = [
                'occupants' => (int) $row->occupants,
                'rooms_occupied' => (int) $row->rooms_occupied,
                'occupants_unresolved_room' => (int) $row->occupants_unresolved_room,
                'rooms_not_in_this_institute' => (int) $row->rooms_not_in_this_institute,
            ];
        }

        return $totals;
    }

    /**
     * The allocation join, scoped to the caller's institute at every hop.
     *
     * `tblstudent` and `tbluser` are both joined as left joins on the same `user_id`,
     * because the column means "the occupant" and the estate allocates rooms to staff as
     * well as to children. Both are scoped to this institute so an id collision across
     * schools cannot resolve a name from the wrong one.
     */
    private function allocationQuery(McpRequestContext $context): Builder
    {
        $institute = $context->selectedInstituteId;

        $query = DB::table('hostel_room_allocation as a')
            ->leftJoin('hostel_master as h', function ($join) {
                $join->on('h.id', '=', 'a.hostel_id')
                    ->on('h.sub_institute_id', '=', 'a.sub_institute_id');
            })
            ->leftJoin('hostel_room_master as r', function ($join) {
                $join->on('r.id', '=', 'a.room_id')
                    ->on('r.sub_institute_id', '=', 'a.sub_institute_id');
            })
            ->leftJoin('hostel_floor_master as f', function ($join) {
                $join->on('f.id', '=', 'r.floor_id')
                    ->on('f.sub_institute_id', '=', 'r.sub_institute_id');
            })
            ->leftJoin('hostel_building_master as b', function ($join) {
                $join->on('b.id', '=', 'f.building_id')
                    ->on('b.sub_institute_id', '=', 'f.sub_institute_id');
            })
            ->leftJoin('admission_category_master as c', function ($join) {
                $join->on('c.id', '=', 'a.admission_category_id')
                    ->on('c.sub_institute_id', '=', 'a.sub_institute_id');
            })
            ->leftJoin('tbluserprofilemaster as p', 'p.id', '=', 'a.user_group_id')
            ->leftJoin('tblstudent as s', function ($join) use ($institute) {
                $join->on('s.id', '=', 'a.user_id')
                    ->where('s.sub_institute_id', '=', $institute);
            })
            ->leftJoin('tbluser as u', function ($join) use ($institute) {
                $join->on('u.id', '=', 'a.user_id')
                    ->where('u.sub_institute_id', '=', $institute);
            })
            ->where('a.sub_institute_id', $institute);

        if ($context->academicYear !== null) {
            $query->where('a.syear', $context->academicYear);
        }

        return $query;
    }
}
