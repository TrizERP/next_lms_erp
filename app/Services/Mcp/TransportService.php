<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * School transport, as the `transport_*` tables record it.
 *
 * WHAT THE FIVE TABLES ARE
 *
 *   `transport_route`        a named route with a start and end time
 *   `transport_route_stop`   which stops a route calls at, with pickup and drop times
 *   `transport_route_bus`    which vehicles run a route
 *   `transport_vehicle`      the vehicle, its seating capacity, driver and conductor
 *   `transport_map_student`  which student rides which bus from which stop, each way
 *
 * CAPACITY IS A SEAT COUNT AGAINST AN ASSIGNMENT COUNT, PER LEG
 *
 * `sitting_capacity` on the vehicle is a real number, and the assignments against it are
 * real rows, so "more students assigned than seats" is genuinely derivable — which makes
 * it the one judgement this module can make honestly.
 *
 * It is counted PER LEG and never once. `transport_map_student` holds `from_bus_id` and
 * `to_bus_id` separately because the morning and afternoon runs are different trips that
 * often use different vehicles. Adding them together would double-count every child who
 * rides the same bus both ways — which is most of them — and report a full bus as
 * catastrophically over capacity. So the morning and afternoon counts are reported
 * separately and are never summed.
 *
 * WHAT IS NOT RECORDED, AND SO IS NEVER REPORTED
 *
 *   · No attendance on a bus. Nobody is recorded as having boarded, so this module can
 *     never say who travelled, only who is assigned.
 *   · No GPS trace or live position. `gps_link` is a URL to somebody else's tracker; it is
 *     reported as present or absent and never followed.
 *   · No trip log, no departure or arrival time actually achieved, no delay, no breakdown,
 *     no incident. `from_time`/`to_time` on a route are the SCHEDULE.
 *   · No vehicle fitness, insurance, permit or licence expiry anywhere in these tables.
 *
 * So nothing here may describe a route as running late, a bus as on the road, a driver as
 * on duty, or a vehicle as roadworthy.
 *
 * `transport_vehicle_type` IS NOT JOINED, ON PURPOSE
 *
 * `transport_vehicle.vehicle_type` holds a free-text word — "Van" — not a foreign key,
 * and `transport_vehicle_type` carries no `sub_institute_id` at all, so joining it would
 * be both wrong and unscoped. The stored word is returned verbatim.
 *
 * SCOPING
 *
 * `sub_institute_id` from the caller's token on every read, and `syear` on the tables that
 * carry it — routes, stops, route-stop and route-bus links, and student mappings.
 * `transport_vehicle`, `transport_driver_detail` and `transport_school_shift` have no
 * year and are scoped by institute alone. Every join carries the institute so a route,
 * stop, bus or driver from another school can never appear on this school's answer.
 */
class TransportService
{
    /**
     * Routes, with their stops and the vehicles that run them.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function routes(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('transport_route')) {
            return ['count' => 0, 'routes' => [], 'note' => 'Transport routes are not recorded in this estate.'];
        }

        $institute = $context->selectedInstituteId;
        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);

        $query = DB::table('transport_route as r')->where('r.sub_institute_id', $institute);

        $this->applyYear($query, $context, 'r.syear');

        if (! empty($filters['route_id'])) {
            $query->where('r.id', (int) $filters['route_id']);
        }

        $search = trim((string) ($filters['search_text'] ?? ''));

        if ($search !== '') {
            $query->where('r.route_name', 'like', '%'.$search.'%');
        }

        $total = (clone $query)->count();

        $rows = $query
            ->select('r.id', 'r.route_name', 'r.from_time', 'r.to_time', 'r.syear')
            ->orderBy('r.route_name')
            ->limit($limit)
            ->get();

        $routeIds = $rows->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $stops = $this->stopsByRoute($context, $routeIds);
        $buses = $this->busesByRoute($context, $routeIds);

        $routes = $rows->map(static fn ($row) => [
            'route_id' => (int) $row->id,
            'route_name' => $row->route_name,
            // The SCHEDULE, not times anything actually achieved. Nothing records that.
            'scheduled_from' => $row->from_time ?: null,
            'scheduled_to' => $row->to_time ?: null,
            'academic_year' => $row->syear === null ? null : (int) $row->syear,
            'stops' => $stops[(int) $row->id] ?? [],
            'stop_count' => count($stops[(int) $row->id] ?? []),
            'vehicles' => $buses[(int) $row->id] ?? [],
            'vehicle_count' => count($buses[(int) $row->id] ?? []),
        ])->all();

        return [
            'count' => $total,
            'row_count' => count($routes),
            'academic_year' => $context->academicYear,
            'routes' => $routes,
            'rule' => 'A route is a named schedule with stops and the vehicles assigned to run it. '
                .'`scheduled_from` and `scheduled_to` are the PLANNED times; this system records no '
                .'departure or arrival that actually happened, no delay and no live position, so no route '
                .'may be described as running, on time or late.',
        ];
    }

    /**
     * Vehicles, with seats against students assigned — morning and afternoon separately.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function vehicles(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('transport_vehicle')) {
            return ['count' => 0, 'vehicles' => [], 'note' => 'Transport vehicles are not recorded in this estate.'];
        }

        $institute = $context->selectedInstituteId;
        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);

        $query = DB::table('transport_vehicle as v')
            ->leftJoin('transport_driver_detail as dr', function ($join) use ($institute) {
                $join->on('dr.id', '=', 'v.driver')->where('dr.sub_institute_id', '=', $institute);
            })
            ->leftJoin('transport_driver_detail as cd', function ($join) use ($institute) {
                $join->on('cd.id', '=', 'v.conductor')->where('cd.sub_institute_id', '=', $institute);
            })
            ->where('v.sub_institute_id', $institute);

        if (Schema::hasTable('transport_school_shift')) {
            $query->leftJoin('transport_school_shift as sh', function ($join) use ($institute) {
                $join->on('sh.id', '=', 'v.school_shift')->where('sh.sub_institute_id', '=', $institute);
            });
        }

        if (! empty($filters['vehicle_id'])) {
            $query->where('v.id', (int) $filters['vehicle_id']);
        }

        $search = trim((string) ($filters['search_text'] ?? ''));

        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(static function ($inner) use ($needle): void {
                $inner->where('v.title', 'like', $needle)->orWhere('v.vehicle_number', 'like', $needle);
            });
        }

        $total = (clone $query)->count();

        $shift = Schema::hasTable('transport_school_shift') ? 'sh.shift_title' : 'NULL';

        $rows = $query
            ->selectRaw("v.id, v.title, v.vehicle_number, v.vehicle_type, v.sitting_capacity,
                v.vehicle_identity_number, v.gps_link, v.school_shift, v.driver, v.conductor,
                {$shift} AS shift_title,
                CONCAT_WS(' ', dr.first_name, dr.last_name) AS driver_name, dr.mobile AS driver_mobile,
                dr.status AS driver_status,
                CONCAT_WS(' ', cd.first_name, cd.last_name) AS conductor_name")
            ->orderBy('v.title')
            ->limit($limit)
            ->get();

        $vehicleIds = $rows->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $assigned = $this->assignmentCounts($context, $vehicleIds);
        $routeNames = $this->routeNamesByVehicle($context, $vehicleIds);

        $overCapacity = 0;
        $vehicles = [];

        foreach ($rows as $row) {
            $id = (int) $row->id;
            $seats = (int) $row->sitting_capacity;
            $morning = $assigned['morning'][$id] ?? 0;
            $afternoon = $assigned['afternoon'][$id] ?? 0;

            // Only a positive seat count can be exceeded. A vehicle with no capacity
            // recorded is reported as unknown rather than as over capacity, which is what
            // a zero would otherwise make every one of them.
            $over = $seats > 0 && ($morning > $seats || $afternoon > $seats);

            if ($over) {
                $overCapacity++;
            }

            $vehicles[] = [
                'vehicle_id' => $id,
                'title' => $row->title,
                'vehicle_number' => $row->vehicle_number,
                // Free text on this table, not a lookup. Returned as stored.
                'vehicle_type' => $row->vehicle_type ?: null,
                'identity_number' => $row->vehicle_identity_number ?: null,
                'sitting_capacity' => $seats > 0 ? $seats : null,
                'shift_id' => $row->school_shift === null ? null : (int) $row->school_shift,
                'shift' => $row->shift_title,
                'driver_id' => $row->driver === null ? null : (int) $row->driver,
                // Null when the driver belongs to another institute — a record to correct,
                // not a name to borrow from elsewhere.
                'driver_name' => trim((string) ($row->driver_name ?? '')) ?: null,
                'driver_mobile' => $row->driver_mobile ?: null,
                'conductor_name' => trim((string) ($row->conductor_name ?? '')) ?: null,
                // Counted per leg and NEVER summed. See the class note.
                'students_assigned_morning' => $morning,
                'students_assigned_afternoon' => $afternoon,
                'seats_free_morning' => $seats > 0 ? $seats - $morning : null,
                'seats_free_afternoon' => $seats > 0 ? $seats - $afternoon : null,
                'over_capacity' => $seats > 0 ? $over : null,
                'routes' => $routeNames[$id] ?? [],
                // Whether a tracker URL is on file. It is not followed and nothing here
                // knows where the vehicle is.
                'gps_link_recorded' => trim((string) ($row->gps_link ?? '')) !== '',
            ];
        }

        return [
            'count' => $total,
            'row_count' => count($vehicles),
            'academic_year' => $context->academicYear,
            // Deliberately named for its scope. Unlike every other figure in this class it
            // covers only the vehicles listed, because deciding it needs the per-vehicle
            // assignment counts, and those are fetched for the page. Calling it
            // `over_capacity` beside a whole-fleet `count` would read as "3 of 268 buses
            // are overfull" when it means "3 of the 50 shown".
            'over_capacity_in_listed_rows' => $overCapacity,
            'vehicles' => $vehicles,
            'rule' => 'Seats come from `sitting_capacity` on the vehicle and assignments from the student '
                .'mapping table. The morning and afternoon counts are SEPARATE TRIPS and must never be '
                .'added together — most children ride the same bus both ways, so summing them doubles '
                .'every one of them. `over_capacity` is null where no seat count is recorded, which is not '
                .'the same as a bus with no seats, and `over_capacity_in_listed_rows` counts only the '
                .'vehicles listed rather than the whole fleet. This system records NO boarding, attendance, live '
                .'position, trip log, delay, or vehicle fitness, insurance or permit expiry, so no vehicle '
                .'may be described as running, on the road, roadworthy or overdue for anything.',
        ];
    }

    /**
     * Students assigned to transport, with their bus and stop each way.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function assignments(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('transport_map_student')) {
            return ['count' => 0, 'assignments' => [], 'note' => 'Transport assignments are not recorded in this estate.'];
        }

        $institute = $context->selectedInstituteId;
        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);

        $query = DB::table('transport_map_student as m')
            ->leftJoin('tblstudent as s', function ($join) use ($institute) {
                $join->on('s.id', '=', 'm.student_id')->where('s.sub_institute_id', '=', $institute);
            })
            ->leftJoin('transport_vehicle as fb', function ($join) use ($institute) {
                $join->on('fb.id', '=', 'm.from_bus_id')->where('fb.sub_institute_id', '=', $institute);
            })
            ->leftJoin('transport_vehicle as tb', function ($join) use ($institute) {
                $join->on('tb.id', '=', 'm.to_bus_id')->where('tb.sub_institute_id', '=', $institute);
            })
            ->leftJoin('transport_stop as fs', function ($join) use ($institute) {
                $join->on('fs.id', '=', 'm.from_stop')->where('fs.sub_institute_id', '=', $institute);
            })
            ->leftJoin('transport_stop as ts', function ($join) use ($institute) {
                $join->on('ts.id', '=', 'm.to_stop')->where('ts.sub_institute_id', '=', $institute);
            })
            ->where('m.sub_institute_id', $institute);

        $this->applyYear($query, $context, 'm.syear');

        foreach ([
            'student_id' => 'm.student_id',
            'vehicle_id' => 'm.from_bus_id',
            'stop_id' => 'm.from_stop',
        ] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, (int) $filters[$filter]);
            }
        }

        $search = trim((string) ($filters['search_text'] ?? ''));

        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(static function ($inner) use ($needle): void {
                $inner->where('s.first_name', 'like', $needle)
                    ->orWhere('s.last_name', 'like', $needle)
                    ->orWhere('s.enrollment_no', 'like', $needle);
            });
        }

        // Counted before the limit. One live institute holds seventeen thousand of these
        // rows, so a page read as a total would be wrong by orders of magnitude.
        $total = (clone $query)->count();

        // Mappings pointing at a student this institute does not have a record for. One
        // live institute has exactly one of these out of 4,596 — a dangling id left behind
        // when a student record went. The name comes back null rather than borrowed from
        // elsewhere, and the count is reported so the office can see the gap is the
        // record's and not the reader's.
        $danglingStudents = (clone $query)->whereNull('s.id')->count();

        $rows = $query
            ->selectRaw("m.id, m.student_id, m.from_bus_id, m.to_bus_id, m.from_stop, m.to_stop,
                m.distance, m.amount, m.syear,
                CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                s.enrollment_no,
                fb.title AS from_bus_title, fb.vehicle_number AS from_bus_number,
                tb.title AS to_bus_title, tb.vehicle_number AS to_bus_number,
                fs.stop_name AS from_stop_name, ts.stop_name AS to_stop_name")
            ->orderBy('fb.title')
            ->orderBy('s.first_name')
            ->limit($limit)
            ->get();

        $assignments = $rows->map(static fn ($row) => [
            'assignment_id' => (int) $row->id,
            'student_id' => $row->student_id === null ? null : (int) $row->student_id,
            // Null when the student is not of this institute — a record to correct, not a
            // name to borrow from elsewhere.
            'student_name' => trim((string) ($row->student_name ?? '')) ?: null,
            'enrollment_no' => $row->enrollment_no ?: null,
            'morning_bus_id' => $row->from_bus_id === null ? null : (int) $row->from_bus_id,
            'morning_bus' => $row->from_bus_title,
            'morning_bus_number' => $row->from_bus_number,
            'morning_stop' => $row->from_stop_name,
            'afternoon_bus_id' => $row->to_bus_id === null ? null : (int) $row->to_bus_id,
            'afternoon_bus' => $row->to_bus_title,
            'afternoon_bus_number' => $row->to_bus_number,
            'afternoon_stop' => $row->to_stop_name,
            'distance' => $row->distance === null || $row->distance === '' ? null : (float) $row->distance,
            // The transport fee recorded on the mapping. The Fees module is where a fee is
            // actually collected; nothing here knows whether it was paid.
            'amount' => $row->amount === null || $row->amount === '' ? null : round((float) $row->amount, 2),
            'academic_year' => $row->syear === null ? null : (int) $row->syear,
        ])->all();

        return [
            'count' => $total,
            'row_count' => count($assignments),
            'academic_year' => $context->academicYear,
            'assignments_to_students_not_in_this_institute' => $danglingStudents,
            'figures_cover' => 'every assignment matching these filters, not only the rows listed',
            'assignments' => $assignments,
            'rule' => 'One row is one student\'s transport assignment for the year, with a morning and an '
                .'afternoon leg that may use different buses and stops. An assignment is a plan, not a '
                .'journey: nothing records that a child boarded, and no bus attendance exists anywhere in '
                .'this system. `amount` is the transport fee written on the mapping — whether it has been '
                .'paid is a Fees question and is not readable here. A null `student_name` means this '
                .'institute has no student record for that id; the name is never borrowed from another '
                .'school, and `assignments_to_students_not_in_this_institute` is how many such rows '
                .'matched.',
        ];
    }

    /**
     * Students assigned per vehicle, counted separately for each leg.
     *
     * @param  array<int, int>  $vehicleIds
     * @return array{morning: array<int, int>, afternoon: array<int, int>}
     */
    private function assignmentCounts(McpRequestContext $context, array $vehicleIds): array
    {
        $empty = ['morning' => [], 'afternoon' => []];

        if ($vehicleIds === [] || ! Schema::hasTable('transport_map_student')) {
            return $empty;
        }

        $counts = $empty;

        foreach (['morning' => 'from_bus_id', 'afternoon' => 'to_bus_id'] as $leg => $column) {
            $query = DB::table('transport_map_student')
                ->where('sub_institute_id', $context->selectedInstituteId)
                ->whereIn($column, $vehicleIds);

            $this->applyYear($query, $context, 'syear');

            // Distinct students, so a student mapped twice for the same leg — which the
            // table does not forbid — is one seat and not two.
            $rows = $query
                ->selectRaw($column.' AS vehicle_id, COUNT(DISTINCT student_id) AS students')
                ->groupBy($column)
                ->get();

            foreach ($rows as $row) {
                $counts[$leg][(int) $row->vehicle_id] = (int) $row->students;
            }
        }

        return $counts;
    }

    /**
     * The stops on each of the given routes, in the order the route calls at them.
     *
     * @param  array<int, int>  $routeIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function stopsByRoute(McpRequestContext $context, array $routeIds): array
    {
        if ($routeIds === [] || ! Schema::hasTable('transport_route_stop') || ! Schema::hasTable('transport_stop')) {
            return [];
        }

        $institute = $context->selectedInstituteId;

        $query = DB::table('transport_route_stop as rs')
            ->leftJoin('transport_stop as st', function ($join) use ($institute) {
                $join->on('st.id', '=', 'rs.stop_id')->where('st.sub_institute_id', '=', $institute);
            })
            ->where('rs.sub_institute_id', $institute)
            ->whereIn('rs.route_id', $routeIds);

        $this->applyYear($query, $context, 'rs.syear');

        $byRoute = [];

        foreach ($query->select('rs.route_id', 'rs.stop_id', 'rs.pickuptime', 'rs.droptime', 'st.stop_name')
            ->orderBy('rs.pickuptime')->orderBy('st.stop_name')->get() as $row) {
            $byRoute[(int) $row->route_id][] = [
                'stop_id' => $row->stop_id === null ? null : (int) $row->stop_id,
                'stop_name' => $row->stop_name,
                'pickup_time' => $row->pickuptime ?: null,
                'drop_time' => $row->droptime ?: null,
            ];
        }

        return $byRoute;
    }

    /**
     * The vehicles assigned to each of the given routes.
     *
     * @param  array<int, int>  $routeIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function busesByRoute(McpRequestContext $context, array $routeIds): array
    {
        if ($routeIds === [] || ! Schema::hasTable('transport_route_bus') || ! Schema::hasTable('transport_vehicle')) {
            return [];
        }

        $institute = $context->selectedInstituteId;

        $query = DB::table('transport_route_bus as rb')
            ->leftJoin('transport_vehicle as v', function ($join) use ($institute) {
                $join->on('v.id', '=', 'rb.bus_id')->where('v.sub_institute_id', '=', $institute);
            })
            ->where('rb.sub_institute_id', $institute)
            ->whereIn('rb.route_id', $routeIds);

        $this->applyYear($query, $context, 'rb.syear');

        $byRoute = [];

        foreach ($query->select('rb.route_id', 'rb.bus_id', 'v.title', 'v.vehicle_number', 'v.sitting_capacity')->get() as $row) {
            $byRoute[(int) $row->route_id][] = [
                'vehicle_id' => $row->bus_id === null ? null : (int) $row->bus_id,
                'title' => $row->title,
                'vehicle_number' => $row->vehicle_number,
                'sitting_capacity' => (int) $row->sitting_capacity > 0 ? (int) $row->sitting_capacity : null,
            ];
        }

        return $byRoute;
    }

    /**
     * The route names each of the given vehicles runs.
     *
     * @param  array<int, int>  $vehicleIds
     * @return array<int, array<int, string>>
     */
    private function routeNamesByVehicle(McpRequestContext $context, array $vehicleIds): array
    {
        if ($vehicleIds === [] || ! Schema::hasTable('transport_route_bus') || ! Schema::hasTable('transport_route')) {
            return [];
        }

        $institute = $context->selectedInstituteId;

        $query = DB::table('transport_route_bus as rb')
            ->join('transport_route as r', function ($join) use ($institute) {
                $join->on('r.id', '=', 'rb.route_id')->where('r.sub_institute_id', '=', $institute);
            })
            ->where('rb.sub_institute_id', $institute)
            ->whereIn('rb.bus_id', $vehicleIds);

        $this->applyYear($query, $context, 'rb.syear');

        $byVehicle = [];

        foreach ($query->select('rb.bus_id', 'r.route_name')->distinct()->get() as $row) {
            $byVehicle[(int) $row->bus_id][] = (string) $row->route_name;
        }

        return $byVehicle;
    }

    /**
     * Apply the caller's academic year to a column, where the caller carries one.
     *
     * Factored out because five of the tables here carry `syear` and three do not, and the
     * difference is easy to get wrong in the direction that leaks a previous year's rows.
     */
    private function applyYear(Builder $query, McpRequestContext $context, string $column): void
    {
        if ($context->academicYear !== null) {
            $query->where($column, $context->academicYear);
        }
    }
}
