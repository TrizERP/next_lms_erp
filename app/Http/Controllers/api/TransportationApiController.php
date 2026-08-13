<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TransportationApiController extends Controller
{
    use GetsJwtToken;

    private const MENU_LINKS = [
        'student-mappings' => ['map_student.index'],
        'drivers' => ['add_driver.index'],
        'vehicles' => ['add_vehicle.index'],
        'routes' => ['add_route.index'],
        'stops' => ['add_stop.index'],
        'rates' => ['transport_rate.index'],
        'route-buses' => ['map_route_bus.index'],
        'route-stops' => ['map_route_stop.index'],
        'shifts' => ['transport_shift.index'],
        'van-wise-report' => ['van_wise_report.index'],
        'van-summary-report' => ['van_wise_students_detail_report.index'],
    ];

    private function failure(string $message, int $status = 422, $errors = null)
    {
        return response()->json(['status_code' => 0, 'message' => $message, 'errors' => $errors, 'data' => []], $status);
    }

    private function context(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                return response()->json(['status_code' => 2, 'message' => 'Token Auth Failed', 'data' => []], 401);
            }
        } catch (\Exception $exception) {
            return response()->json(['status_code' => 2, 'message' => $exception->getMessage(), 'data' => []], 401);
        }

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'user_id' => 'required|integer',
            'syear' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return $this->failure($validator->messages()->first(), 422, $validator->errors());
        }

        $token = preg_replace('/^Bearer\s+/i', '', (string) $request->header('Authorization'));
        $parts = explode('.', $token);
        $payload = [];
        if (count($parts) === 3) {
            $decoded = base64_decode(strtr($parts[1], '-_', '+/'));
            $payload = json_decode($decoded ?: '{}', true) ?: [];
        }
        $actorId = (int) ($payload['id'] ?? 0);
        $tenantId = (int) ($payload['sub_institute_id'] ?? 0);
        if ($actorId !== $request->integer('user_id') || $tenantId !== $request->integer('sub_institute_id')) {
            return response()->json(['status_code' => 2, 'message' => 'Token context does not match the request.', 'data' => []], 403);
        }

        $actor = DB::table('tbluser as user')
            ->join('tbluserprofilemaster as profile', 'profile.id', '=', 'user.user_profile_id')
            ->select('user.id', 'user.user_profile_id', 'user.sub_institute_id', 'profile.name as profile_name')
            ->where('user.id', $actorId)
            ->where('user.sub_institute_id', $tenantId)
            ->where('user.status', 1)
            ->first();
        if (! $actor) {
            return response()->json(['status_code' => 2, 'message' => 'Active user context was not found.', 'data' => []], 403);
        }
        $request->attributes->set('transport_actor', $actor);

        return null;
    }

    private function guard(Request $request, string $module, string $action)
    {
        if ($response = $this->context($request)) {
            return $response;
        }
        if (! isset(self::MENU_LINKS[$module])) {
            return $this->failure('Unknown transportation module.', 404);
        }

        $actor = $request->attributes->get('transport_actor');
        if (in_array(strtolower((string) $actor->profile_name), ['admin', 'super admin'], true)) {
            return null;
        }
        $menuId = DB::table('tblmenumaster')->where('status', 1)->whereIn('link', self::MENU_LINKS[$module])->value('id');
        $individual = $menuId ? DB::table('tblindividual_rights')
            ->where('menu_id', $menuId)->where('profile_id', $actor->user_profile_id)
            ->where('user_id', $actor->id)->where('sub_institute_id', $actor->sub_institute_id)->first() : null;
        $rights = $individual ?: ($menuId ? DB::table('tblgroupwise_rights')
            ->where('menu_id', $menuId)->where('profile_id', $actor->user_profile_id)
            ->where('sub_institute_id', $actor->sub_institute_id)->first() : null);
        $column = ['view' => 'can_view', 'add' => 'can_add', 'edit' => 'can_edit', 'delete' => 'can_delete'][$action];
        if (! (bool) ($rights->{$column} ?? false)) {
            return $this->failure("You do not have permission to {$action} this transportation module.", 403);
        }

        return null;
    }

    private function options(Request $request): array
    {
        $tenantId = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');

        return [
            'drivers' => DB::table('transport_driver_detail')->select('id', DB::raw("concat_ws(' ', first_name, last_name) as name"))
                ->where('sub_institute_id', $tenantId)->where('type', 'Driver')->where('status', 'Active')->orderBy('first_name')->get(),
            'conductors' => DB::table('transport_driver_detail')->select('id', DB::raw("concat_ws(' ', first_name, last_name) as name"))
                ->where('sub_institute_id', $tenantId)->where('type', 'Conductor')->where('status', 'Active')->orderBy('first_name')->get(),
            'vehicles' => DB::table('transport_vehicle')->select('id', 'title as name', 'school_shift as parent_id', 'vehicle_number', 'sitting_capacity')
                ->where('sub_institute_id', $tenantId)->orderBy('title')->get(),
            'vehicle_types' => DB::table('transport_vehicle_type')->select('id', 'name')->orderBy('name')->get(),
            'routes' => DB::table('transport_route')->select('id', 'route_name as name')->where('sub_institute_id', $tenantId)->where('syear', $syear)->orderBy('route_name')->get(),
            'stops' => DB::table('transport_stop')->select('id', 'stop_name as name')->where('sub_institute_id', $tenantId)->where('syear', $syear)->orderBy('stop_name')->get(),
            'shifts' => DB::table('transport_school_shift')->select('id', 'shift_title as name', 'shift_rate', 'km_amount')->where('sub_institute_id', $tenantId)->orderBy('shift_title')->get(),
            'students' => DB::table('tblstudent as student')
                ->join('tblstudent_enrollment as enrollment', function ($join) use ($syear) {
                    $join->on('enrollment.student_id', '=', 'student.id')->where('enrollment.syear', $syear)->whereNull('enrollment.end_date');
                })
                ->select('student.id', DB::raw("concat_ws(' ', student.first_name, student.middle_name, student.last_name) as name"), 'student.enrollment_no')
                ->where('student.sub_institute_id', $tenantId)->orderBy('student.first_name')->get(),
            // Grade → standard → division, the same three chained dropdowns the
            // Blade student-mapping search renders through SearchChain().
            'grades' => DB::table('academic_section')->select('id', 'title as name')
                ->where('sub_institute_id', $tenantId)->orderBy('sort_order')->get(),
            'standards' => DB::table('standard')->select('id', 'name', 'grade_id as parent_id')
                ->where('sub_institute_id', $tenantId)->orderBy('sort_order')->get(),
            'divisions' => DB::table('division')->select('id', 'name')
                ->where('sub_institute_id', $tenantId)->orderBy('name')->get(),
        ];
    }

    /**
     * Stops each vehicle can serve, derived from route → bus and route → stop.
     *
     * The Blade screen fetched this one dropdown at a time from
     * `api/get-stop-list`; sending the whole map once lets the Next.js grid
     * cascade every row without a round trip per select.
     */
    private function vehicleStops(int $tenantId, int $syear)
    {
        return DB::table('transport_route_bus as route_bus')
            ->join('transport_route as route', 'route.id', '=', 'route_bus.route_id')
            ->join('transport_route_stop as route_stop', 'route_stop.route_id', '=', 'route.id')
            ->join('transport_stop as stop', 'stop.id', '=', 'route_stop.stop_id')
            ->join('transport_vehicle as vehicle', 'vehicle.id', '=', 'route_bus.bus_id')
            ->select('vehicle.id as vehicle_id', 'vehicle.school_shift as shift_id', 'stop.id as stop_id', 'stop.stop_name')
            ->where('vehicle.sub_institute_id', $tenantId)
            ->where('route.syear', $syear)
            ->distinct()
            ->orderBy('stop.stop_name')
            ->get();
    }

    /** Seats already taken per vehicle+shift, counting only enrolled students. */
    private function reservedSeats(int $tenantId, int $syear)
    {
        return DB::table('transport_map_student as mapping')
            ->join('tblstudent_enrollment as enrollment', function ($join) use ($syear) {
                $join->on('enrollment.student_id', '=', 'mapping.student_id')
                    ->where('enrollment.syear', $syear)->whereNull('enrollment.end_date');
            })
            ->select('mapping.from_bus_id as vehicle_id', 'mapping.from_shift_id as shift_id',
                DB::raw('count(distinct mapping.student_id) as reserved'))
            ->where('mapping.sub_institute_id', $tenantId)->where('mapping.syear', $syear)
            ->groupBy('mapping.from_bus_id', 'mapping.from_shift_id')
            ->get();
    }

    public function index(Request $request, string $module)
    {
        if ($response = $this->guard($request, $module, 'view')) {
            return $response;
        }
        $tenantId = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $records = [];

        switch ($module) {
            case 'drivers':
                $records = DB::table('transport_driver_detail')->where('sub_institute_id', $tenantId)->orderBy('first_name')->get();
                break;
            case 'vehicles':
                $records = DB::table('transport_vehicle as vehicle')
                    ->join('transport_school_shift as shift', 'shift.id', '=', 'vehicle.school_shift')
                    ->join('transport_driver_detail as driver', 'driver.id', '=', 'vehicle.driver')
                    ->leftJoin('transport_driver_detail as conductor', 'conductor.id', '=', 'vehicle.conductor')
                    ->select('vehicle.*', 'shift.shift_title', DB::raw("concat_ws(' ', driver.first_name, driver.last_name) as driver_name"), DB::raw("concat_ws(' ', conductor.first_name, conductor.last_name) as conductor_name"))
                    ->where('vehicle.sub_institute_id', $tenantId)->get();
                break;
            case 'routes':
                $records = DB::table('transport_route')->where('sub_institute_id', $tenantId)->where('syear', $syear)->orderBy('route_name')->get();
                break;
            case 'stops':
                $records = DB::table('transport_stop')->where('sub_institute_id', $tenantId)->where('syear', $syear)->orderBy('stop_name')->get();
                break;
            case 'rates':
                $records = DB::table('transport_kilometer_rate')->where('sub_institute_id', $tenantId)->where('syear', $syear)->orderByDesc('id')->get();
                break;
            case 'route-buses':
                $records = DB::table('transport_route_bus as mapping')
                    ->join('transport_route as route', 'route.id', '=', 'mapping.route_id')
                    ->join('transport_vehicle as vehicle', 'vehicle.id', '=', 'mapping.bus_id')
                    ->select('mapping.*', 'route.route_name', 'vehicle.title as vehicle_name', 'vehicle.vehicle_number')
                    ->where('mapping.sub_institute_id', $tenantId)->where('mapping.syear', $syear)->get();
                break;
            case 'route-stops':
                $records = DB::table('transport_route_stop as mapping')
                    ->join('transport_route as route', 'route.id', '=', 'mapping.route_id')
                    ->join('transport_stop as stop', 'stop.id', '=', 'mapping.stop_id')
                    ->select('mapping.*', 'route.route_name', 'stop.stop_name')
                    ->where('mapping.sub_institute_id', $tenantId)->where('mapping.syear', $syear)->get();
                break;
            case 'shifts':
                $records = DB::table('transport_school_shift')->where('sub_institute_id', $tenantId)->orderBy('shift_title')->get();
                break;
            case 'student-mappings':
                $records = $this->studentMappings($request, $tenantId, $syear);
                break;
            case 'van-wise-report':
                $records = $this->vanWise($request);
                break;
            case 'van-summary-report':
                $records = $this->vanSummary($tenantId, $syear);
                break;
        }

        $extra = [];
        if ($module === 'student-mappings') {
            // Everything the mapping grid needs to cascade shift → vehicle →
            // stop and to price a row, in one response.
            $extra = [
                'students' => $this->studentSearch($request, $tenantId, $syear),
                'vehicle_stops' => $this->vehicleStops($tenantId, $syear),
                'reserved_seats' => $this->reservedSeats($tenantId, $syear),
            ];
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Success',
            'data' => array_merge(['records' => $records], $this->options($request), $extra),
        ]);
    }

    /**
     * Students already mapped for the academic year.
     *
     * The joins are left joins on purpose: a shift, vehicle or stop deleted
     * after the mapping was made must not make the student silently disappear
     * from the list — that row is exactly the one an operator needs to fix.
     */
    private function studentMappings(Request $request, int $tenantId, int $syear)
    {
        $query = DB::table('transport_map_student as mapping')
            ->join('tblstudent as student', 'student.id', '=', 'mapping.student_id')
            ->leftJoin('tblstudent_enrollment as enrollment', function ($join) use ($syear) {
                $join->on('enrollment.student_id', '=', 'mapping.student_id')
                    ->where('enrollment.syear', $syear)->whereNull('enrollment.end_date');
            })
            ->leftJoin('standard', 'standard.id', '=', 'enrollment.standard_id')
            ->leftJoin('division', 'division.id', '=', 'enrollment.section_id')
            ->leftJoin('transport_school_shift as from_shift', 'from_shift.id', '=', 'mapping.from_shift_id')
            ->leftJoin('transport_vehicle as from_vehicle', 'from_vehicle.id', '=', 'mapping.from_bus_id')
            ->leftJoin('transport_stop as from_stop', 'from_stop.id', '=', 'mapping.from_stop')
            ->leftJoin('transport_school_shift as to_shift', 'to_shift.id', '=', 'mapping.to_shift_id')
            ->leftJoin('transport_vehicle as to_vehicle', 'to_vehicle.id', '=', 'mapping.to_bus_id')
            ->leftJoin('transport_stop as to_stop', 'to_stop.id', '=', 'mapping.to_stop')
            ->select('mapping.*', DB::raw("concat_ws(' ', student.first_name, student.middle_name, student.last_name) as student_name"),
                'student.enrollment_no', 'student.mobile', 'student.address',
                DB::raw("concat_ws(' / ', standard.name, division.name) as standard_division"),
                'enrollment.grade_id', 'enrollment.standard_id', 'enrollment.section_id',
                'from_shift.shift_title as from_shift_name', 'from_vehicle.title as from_vehicle_name',
                'from_stop.stop_name as from_stop_name', 'to_shift.shift_title as to_shift_name',
                'to_vehicle.title as to_vehicle_name', 'to_stop.stop_name as to_stop_name')
            ->where('mapping.sub_institute_id', $tenantId)->where('mapping.syear', $syear);

        $this->applyStudentFilters($query, $request);

        return $query->orderBy('student.first_name')->get();
    }

    /**
     * Students matching the search filters, each carrying the mapping already
     * on file. This is the Blade `map_student.create` grid, as data.
     *
     * At least one filter is required — an unfiltered search would return the
     * whole school.
     */
    private function studentSearch(Request $request, int $tenantId, int $syear)
    {
        $filters = ['grade', 'standard', 'division', 'name', 'grno', 'area', 'student_id'];
        if (! collect($filters)->contains(fn ($filter) => $request->filled($filter))) {
            return [];
        }

        $query = DB::table('tblstudent as student')
            ->join('tblstudent_enrollment as enrollment', function ($join) use ($syear) {
                $join->on('enrollment.student_id', '=', 'student.id')
                    ->where('enrollment.syear', $syear)->whereNull('enrollment.end_date');
            })
            ->leftJoin('standard', 'standard.id', '=', 'enrollment.standard_id')
            ->leftJoin('division', 'division.id', '=', 'enrollment.section_id')
            ->leftJoin('transport_map_student as mapping', function ($join) use ($syear, $tenantId) {
                $join->on('mapping.student_id', '=', 'student.id')
                    ->where('mapping.syear', $syear)->where('mapping.sub_institute_id', $tenantId);
            })
            ->leftJoin('transport_school_shift as from_shift', 'from_shift.id', '=', 'mapping.from_shift_id')
            ->select('student.id as student_id',
                DB::raw("concat_ws(' ', student.first_name, student.middle_name, student.last_name) as student_name"),
                'student.enrollment_no', 'student.mobile', 'student.address',
                DB::raw("concat_ws(' / ', standard.name, division.name) as standard_division"),
                'enrollment.grade_id', 'enrollment.standard_id', 'enrollment.section_id', 'enrollment.roll_no',
                'mapping.id as mapping_id', 'mapping.from_shift_id', 'mapping.from_bus_id', 'mapping.from_stop',
                'mapping.to_shift_id', 'mapping.to_bus_id', 'mapping.to_stop', 'mapping.distance', 'mapping.amount',
                'from_shift.shift_rate', 'from_shift.km_amount')
            ->where('student.sub_institute_id', $tenantId);

        $this->applyStudentFilters($query, $request);

        if ($request->filled('student_id')) {
            $query->where('student.id', $request->integer('student_id'));
        }

        return $query->orderByRaw('standard.sort_order, division.id, enrollment.roll_no')->get();
    }

    /** Grade / standard / division / name / GR number / area filters. */
    private function applyStudentFilters($query, Request $request): void
    {
        foreach (['grade' => 'enrollment.grade_id', 'standard' => 'enrollment.standard_id', 'division' => 'enrollment.section_id'] as $input => $column) {
            if ($request->filled($input)) {
                $query->where($column, $request->integer($input));
            }
        }
        if ($request->filled('grno')) {
            $query->where('student.enrollment_no', $request->input('grno'));
        }
        if ($request->filled('name')) {
            $name = '%'.$request->input('name').'%';
            $query->where(function ($inner) use ($name) {
                $inner->where('student.first_name', 'like', $name)
                    ->orWhere('student.middle_name', 'like', $name)
                    ->orWhere('student.last_name', 'like', $name);
            });
        }
        // "Area" means the pickup stop the student is already mapped to.
        if ($request->filled('area')) {
            $query->where('mapping.from_stop', $request->integer('area'));
        }
    }

    private function vanWise(Request $request)
    {
        $tenantId = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $direction = $request->input('pickup') === 'drop' ? 'to' : 'from';
        $query = DB::table('transport_map_student as mapping')
            ->join('tblstudent as student', 'student.id', '=', 'mapping.student_id')
            ->join('tblstudent_enrollment as enrollment', function ($join) use ($syear) {
                $join->on('enrollment.student_id', '=', 'student.id')->where('enrollment.syear', $syear)->whereNull('enrollment.end_date');
            })
            ->join('standard', 'standard.id', '=', 'enrollment.standard_id')
            ->join('division', 'division.id', '=', 'enrollment.section_id')
            ->join('transport_vehicle as vehicle', "vehicle.id", '=', "mapping.{$direction}_bus_id")
            ->join('transport_school_shift as shift', "shift.id", '=', "mapping.{$direction}_shift_id")
            ->join('transport_stop as selected_stop', "selected_stop.id", '=', "mapping.{$direction}_stop")
            ->join('transport_stop as from_stop', 'from_stop.id', '=', 'mapping.from_stop')
            ->join('transport_stop as to_stop', 'to_stop.id', '=', 'mapping.to_stop')
            ->leftJoin('transport_route_bus as route_bus', function ($join) use ($syear) {
                $join->on('route_bus.bus_id', '=', 'vehicle.id')->where('route_bus.syear', $syear);
            })
            ->leftJoin('transport_route as route', 'route.id', '=', 'route_bus.route_id')
            ->join('transport_driver_detail as driver', 'driver.id', '=', 'vehicle.driver')
            ->leftJoin('transport_driver_detail as conductor', 'conductor.id', '=', 'vehicle.conductor')
            ->select('student.id as student_id', DB::raw("concat_ws(' ', student.first_name, student.middle_name, student.last_name) as student_name"),
                'student.enrollment_no', 'student.mobile', 'student.address', DB::raw("concat(standard.name, '/', division.name) as standard_division"),
                'route.route_name', 'shift.shift_title', 'vehicle.title as vehicle_name', 'from_stop.stop_name as from_stop_name',
                'to_stop.stop_name as to_stop_name', DB::raw("concat_ws(' ', driver.first_name, driver.last_name) as driver_name"),
                DB::raw("concat_ws(' ', conductor.first_name, conductor.last_name) as conductor_name"), 'mapping.amount')
            ->where('mapping.sub_institute_id', $tenantId)->where('mapping.syear', $syear);

        foreach (['van' => "mapping.{$direction}_bus_id", 'shift' => "mapping.{$direction}_shift_id", 'route' => 'route.id'] as $input => $column) {
            if ($request->filled($input)) {
                $query->where($column, $request->integer($input));
            }
        }
        if ($request->filled('stop')) {
            $query->where('selected_stop.id', $request->integer('stop'));
        }
        if ($request->filled('grno')) {
            $query->where('student.enrollment_no', $request->input('grno'));
        }

        return $query->distinct()->orderBy('student.first_name')->get();
    }

    private function vanSummary(int $tenantId, int $syear)
    {
        return DB::table('transport_vehicle as vehicle')
            ->join('transport_school_shift as shift', 'shift.id', '=', 'vehicle.school_shift')
            ->join('transport_map_student as mapping', function ($join) {
                $join->on('mapping.from_shift_id', '=', 'shift.id')->on('mapping.from_bus_id', '=', 'vehicle.id');
            })
            ->join('tblstudent_enrollment as enrollment', function ($join) use ($syear) {
                $join->on('enrollment.student_id', '=', 'mapping.student_id')->where('enrollment.syear', $syear)->whereNull('enrollment.end_date');
            })
            ->select('vehicle.id as vehicle_id', 'shift.id as shift_id', 'vehicle.title as vehicle_name', 'shift.shift_title', DB::raw('count(distinct mapping.student_id) as student_count'))
            ->where('vehicle.sub_institute_id', $tenantId)->where('mapping.syear', $syear)
            ->groupBy('vehicle.id', 'shift.id', 'vehicle.title', 'shift.shift_title')->orderBy('vehicle.title')->get();
    }

    public function store(Request $request, string $module)
    {
        if ($response = $this->guard($request, $module, 'add')) {
            return $response;
        }
        return $this->save($request, $module);
    }

    public function update(Request $request, string $module, int $id)
    {
        if ($response = $this->guard($request, $module, 'edit')) {
            return $response;
        }
        return $this->save($request, $module, $id);
    }

    private function save(Request $request, string $module, ?int $id = null)
    {
        $tenantId = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $rules = $this->rules($module, $tenantId);
        if ($rules === null) {
            return $this->failure('This transportation module is read-only.', 405);
        }
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->failure($validator->messages()->first(), 422, $validator->errors());
        }
        $values = $this->values($request, $module, $tenantId, $syear);

        if ($module === 'student-mappings') {
            // A student holds at most one mapping per academic year.
            $existing = DB::table('transport_map_student')
                ->where('sub_institute_id', $tenantId)->where('syear', $syear)
                ->where('student_id', $request->integer('student_id'))
                ->when($id, fn ($query) => $query->where('id', '<>', $id))->exists();
            if ($existing) {
                return $this->failure('This student is already mapped for the selected academic year. Edit the existing mapping instead.');
            }
            if ($capacityError = $this->capacityError($request, $id)) {
                return $this->failure($capacityError);
            }
        }
        if ($module === 'rates') {
            if ($duplicate = $this->rateDuplicate($request, $tenantId, $syear, $id)) {
                return $this->failure($duplicate);
            }
            // The list screen sorts on created_on, so an edited slab surfaces.
            $values['created_on'] = now();
        }
        if (in_array($module, ['shifts', 'routes', 'stops'], true)) {
            $duplicateColumn = ['shifts' => 'shift_title', 'routes' => 'route_name', 'stops' => 'stop_name'][$module];
            $table = ['shifts' => 'transport_school_shift', 'routes' => 'transport_route', 'stops' => 'transport_stop'][$module];
            $duplicate = DB::table($table)->where('sub_institute_id', $tenantId)
                ->whereRaw("UPPER({$duplicateColumn}) = ?", [strtoupper($request->input($duplicateColumn))])
                ->when($module !== 'shifts', fn ($query) => $query->where('syear', $syear))
                ->when($id, fn ($query) => $query->where('id', '<>', $id))->exists();
            if ($duplicate) {
                return $this->failure(ucfirst(str_replace('-', ' ', $module)).' already exists.');
            }
        }

        $table = $this->table($module);
        if ($id) {
            $updated = DB::table($table)->where('id', $id)->where('sub_institute_id', $tenantId)
                ->when(in_array($module, ['routes', 'stops', 'rates', 'route-buses', 'route-stops', 'student-mappings'], true), fn ($query) => $query->where('syear', $syear))
                ->update(array_merge($values, $this->hasTimestamps($module) ? ['updated_at' => now()] : []));
            if (! $updated && ! DB::table($table)->where('id', $id)->where('sub_institute_id', $tenantId)->exists()) {
                return $this->failure('Record not found.', 404);
            }
        } else {
            DB::table($table)->insert(array_merge($values, $this->hasTimestamps($module) ? ['created_at' => now(), 'updated_at' => now()] : []));
        }

        return response()->json(['status_code' => 1, 'message' => $id ? 'Data Updated Successfully.' : 'Data Added Successfully.', 'data' => []]);
    }

    private function rules(string $module, int $tenantId): ?array
    {
        $exists = fn (string $table) => Rule::exists($table, 'id')->where(fn ($query) => $query->where('sub_institute_id', $tenantId));
        return [
            'drivers' => ['first_name' => 'required|string|max:50', 'last_name' => 'required|string|max:50', 'mobile' => 'required|string|max:15', 'driver_type' => 'required|in:Driver,Conductor', 'status' => 'required|in:Active,Inactive'],
            'vehicles' => ['title' => 'required|string|max:50', 'vehicle_number' => 'required|string|max:50', 'vehicle_type' => 'required|string|max:50', 'sitting_capacity' => 'required|integer|min:1', 'school_shift' => ['required', 'integer', $exists('transport_school_shift')], 'vehicle_identity_number' => 'required|string|max:50', 'driver' => ['required', 'integer', $exists('transport_driver_detail')], 'conductor' => ['nullable', 'integer', $exists('transport_driver_detail')]],
            'routes' => ['route_name' => 'required|string|max:50', 'from_time' => 'required|date_format:H:i', 'to_time' => 'required|date_format:H:i|after:from_time'],
            'stops' => ['stop_name' => 'required|string|max:50'],
            'rates' => ['distance_from_school' => 'required|string', 'from_distance' => 'required|numeric|min:0', 'to_distance' => 'required|numeric|gte:from_distance', 'rick_old' => 'nullable|numeric|min:0', 'rick_new' => 'nullable|numeric|min:0', 'van_old' => 'nullable|numeric|min:0', 'van_new' => 'nullable|numeric|min:0'],
            'route-buses' => ['route_id' => ['required', 'integer', $exists('transport_route')], 'bus_id' => ['required', 'integer', $exists('transport_vehicle')]],
            'route-stops' => ['route_id' => ['required', 'integer', $exists('transport_route')], 'stop_id' => ['required', 'integer', $exists('transport_stop')], 'pickuptime' => 'nullable|date_format:H:i', 'droptime' => 'nullable|date_format:H:i'],
            'shifts' => ['shift_title' => 'required|string|max:50', 'shift_rate' => 'nullable|integer|min:0', 'km_amount' => 'nullable|integer|min:0'],
            'student-mappings' => ['student_id' => ['required', 'integer', $exists('tblstudent')], 'from_shift_id' => ['required', 'integer', $exists('transport_school_shift')], 'from_bus_id' => ['required', 'integer', $exists('transport_vehicle')], 'from_stop' => ['required', 'integer', $exists('transport_stop')], 'to_shift_id' => ['required', 'integer', $exists('transport_school_shift')], 'to_bus_id' => ['required', 'integer', $exists('transport_vehicle')], 'to_stop' => ['required', 'integer', $exists('transport_stop')], 'distance' => 'nullable|numeric|min:0', 'amount' => 'nullable|numeric|min:0'],
        ][$module] ?? null;
    }

    private function values(Request $request, string $module, int $tenantId, int $syear): array
    {
        $values = [
            'drivers' => ['first_name' => $request->input('first_name'), 'last_name' => $request->input('last_name'), 'mobile' => $request->input('mobile'), 'type' => $request->input('driver_type'), 'status' => $request->input('status')],
            'vehicles' => $request->only(['title', 'vehicle_number', 'vehicle_type', 'sitting_capacity', 'school_shift', 'vehicle_identity_number', 'driver', 'conductor']),
            'routes' => $request->only(['route_name', 'from_time', 'to_time']),
            'stops' => $request->only(['stop_name']),
            'rates' => $request->only(['distance_from_school', 'from_distance', 'to_distance', 'rick_old', 'rick_new', 'van_old', 'van_new']),
            'route-buses' => $request->only(['route_id', 'bus_id']),
            'route-stops' => $request->only(['route_id', 'stop_id', 'pickuptime', 'droptime']),
            'shifts' => $request->only(['shift_title', 'shift_rate', 'km_amount']),
            'student-mappings' => $request->only(['student_id', 'from_shift_id', 'from_bus_id', 'from_stop', 'to_shift_id', 'to_bus_id', 'to_stop', 'distance', 'amount']),
        ][$module];
        $values['sub_institute_id'] = $tenantId;
        if (in_array($module, ['routes', 'stops', 'rates', 'route-buses', 'route-stops', 'student-mappings'], true)) {
            $values['syear'] = $syear;
        }
        if ($module === 'rates' && ! isset($values['created_on'])) {
            $values['created_on'] = now();
        }
        return $values;
    }

    /**
     * A rate slab clashes when its label is reused, or when its distance band
     * overlaps another band for the same institute and academic year — an
     * overlap makes the fare for a distance ambiguous.
     */
    private function rateDuplicate(Request $request, int $tenantId, int $syear, ?int $id): ?string
    {
        $scoped = fn () => DB::table('transport_kilometer_rate')
            ->where('sub_institute_id', $tenantId)->where('syear', $syear)
            ->when($id, fn ($query) => $query->where('id', '<>', $id));

        $label = trim((string) $request->input('distance_from_school'));
        if ($scoped()->whereRaw('UPPER(distance_from_school) = ?', [strtoupper($label)])->exists()) {
            return 'A rate with this distance from school already exists for the selected academic year.';
        }

        $from = (float) $request->input('from_distance');
        $to = (float) $request->input('to_distance');
        $overlap = $scoped()
            ->whereRaw('CAST(from_distance AS DECIMAL(12,2)) <= ?', [$to])
            ->whereRaw('CAST(to_distance AS DECIMAL(12,2)) >= ?', [$from])
            ->first();
        if ($overlap) {
            return "Distance range {$from}-{$to} overlaps the existing range {$overlap->from_distance}-{$overlap->to_distance}.";
        }

        return null;
    }

    /**
     * Null when the pickup vehicle can still take one more student.
     *
     * Seats are counted per student rather than per row, and only for students
     * still enrolled this year — a dropped student must not hold a seat.
     */
    private function capacityError(Request $request, ?int $id, ?int $studentId = null): ?string
    {
        $tenantId = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $shiftId = $request->integer('from_shift_id');
        $vehicle = DB::table('transport_vehicle')->where('id', $request->integer('from_bus_id'))
            ->where('school_shift', $shiftId)->where('sub_institute_id', $tenantId)->first();
        if (! $vehicle) {
            return 'The selected pickup vehicle does not belong to the selected shift.';
        }
        $capacity = (int) $vehicle->sitting_capacity;
        if ($capacity <= 0) {
            return null;
        }
        $studentId = $studentId ?: $request->integer('student_id');
        $reserved = DB::table('transport_map_student as mapping')
            ->join('tblstudent_enrollment as enrollment', function ($join) use ($syear) {
                $join->on('enrollment.student_id', '=', 'mapping.student_id')
                    ->where('enrollment.syear', $syear)->whereNull('enrollment.end_date');
            })
            ->where('mapping.from_bus_id', $vehicle->id)->where('mapping.from_shift_id', $shiftId)
            ->where('mapping.sub_institute_id', $tenantId)->where('mapping.syear', $syear)
            ->when($id, fn ($query) => $query->where('mapping.id', '<>', $id))
            ->when($studentId, fn ($query) => $query->where('mapping.student_id', '<>', $studentId))
            ->distinct()->count('mapping.student_id');

        return $reserved >= $capacity ? 'The selected vehicle has no remaining capacity.' : null;
    }

    private function table(string $module): string
    {
        return [
            'drivers' => 'transport_driver_detail', 'vehicles' => 'transport_vehicle', 'routes' => 'transport_route',
            'stops' => 'transport_stop', 'rates' => 'transport_kilometer_rate', 'route-buses' => 'transport_route_bus',
            'route-stops' => 'transport_route_stop', 'shifts' => 'transport_school_shift', 'student-mappings' => 'transport_map_student',
        ][$module];
    }

    private function hasTimestamps(string $module): bool
    {
        return ! in_array($module, ['rates', 'shifts'], true);
    }

    public function destroy(Request $request, string $module, int $id)
    {
        if ($response = $this->guard($request, $module, 'delete')) {
            return $response;
        }
        if (in_array($module, ['van-wise-report', 'van-summary-report'], true)) {
            return $this->failure('Reports are read-only.', 405);
        }
        $table = $this->table($module);
        $deleted = DB::table($table)->where('id', $id)->where('sub_institute_id', $request->integer('sub_institute_id'))
            ->when($this->isYearScoped($module), fn ($query) => $query->where('syear', $request->integer('syear')))
            ->delete();
        if (! $deleted) {
            return $this->failure('Record not found.', 404);
        }
        return response()->json(['status_code' => 1, 'message' => 'Data Deleted Successfully.', 'data' => []]);
    }

    private function isYearScoped(string $module): bool
    {
        return in_array($module, ['routes', 'stops', 'rates', 'route-buses', 'route-stops', 'student-mappings'], true);
    }

    /**
     * Bulk upsert of the student-mapping grid.
     *
     * Mirrors the Blade screen: every submitted row replaces that student's
     * mapping for the academic year. Rows are validated independently so one
     * bad row does not discard the rest of the grid; the response reports what
     * was saved and what was skipped.
     */
    public function bulkStore(Request $request)
    {
        if ($response = $this->guard($request, 'student-mappings', 'add')) {
            return $response;
        }
        $tenantId = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $rows = $request->input('mappings');
        if (! is_array($rows) || empty($rows)) {
            return $this->failure('No student mapping was submitted.');
        }
        if (count($rows) > 500) {
            return $this->failure('Save at most 500 students at a time.');
        }

        $rules = $this->rules('student-mappings', $tenantId);
        $saved = 0;
        $skipped = [];
        // Seats taken by earlier rows of this same submission, so a batch
        // cannot overfill a vehicle one row at a time.
        $claimed = [];

        foreach ($rows as $index => $row) {
            $row = is_array($row) ? $row : [];
            $label = $row['student_name'] ?? ('Row '.($index + 1));
            $validator = Validator::make($row, $rules);
            if ($validator->fails()) {
                $skipped[] = "{$label}: ".$validator->messages()->first();
                continue;
            }

            $seatKey = $row['from_bus_id'].'-'.$row['from_shift_id'];
            $capacityRequest = new Request(array_merge($row, [
                'sub_institute_id' => $tenantId,
                'syear' => $syear,
            ]));
            $capacityError = $this->capacityError($capacityRequest, null, (int) $row['student_id']);
            if (! $capacityError && ($claimed[$seatKey] ?? 0) > 0) {
                $capacityError = $this->batchCapacityError($tenantId, $syear, $row, $claimed[$seatKey]);
            }
            if ($capacityError) {
                $skipped[] = "{$label}: {$capacityError}";
                continue;
            }
            $claimed[$seatKey] = ($claimed[$seatKey] ?? 0) + 1;

            $values = array_merge($this->values($capacityRequest, 'student-mappings', $tenantId, $syear), [
                'updated_at' => now(),
            ]);

            DB::transaction(function () use ($tenantId, $syear, $row, $values) {
                DB::table('transport_map_student')
                    ->where('sub_institute_id', $tenantId)->where('syear', $syear)
                    ->where('student_id', (int) $row['student_id'])->delete();
                DB::table('transport_map_student')->insert($values + ['created_at' => now()]);
            });
            $saved++;
        }

        if ($saved === 0) {
            return $this->failure($skipped ? implode(' ', $skipped) : 'No student mapping was saved.');
        }

        $message = "{$saved} student(s) mapped successfully.";
        if ($skipped) {
            $message .= ' Skipped: '.implode(' ', $skipped);
        }

        return response()->json([
            'status_code' => 1,
            'message' => $message,
            'data' => ['saved' => $saved, 'skipped' => $skipped],
        ]);
    }

    /** Capacity check that also accounts for seats claimed earlier in the batch. */
    private function batchCapacityError(int $tenantId, int $syear, array $row, int $claimed): ?string
    {
        $vehicle = DB::table('transport_vehicle')->where('id', (int) $row['from_bus_id'])
            ->where('school_shift', (int) $row['from_shift_id'])->where('sub_institute_id', $tenantId)->first();
        if (! $vehicle) {
            return 'The selected pickup vehicle does not belong to the selected shift.';
        }
        $capacity = (int) $vehicle->sitting_capacity;
        if ($capacity <= 0) {
            return null;
        }
        $reserved = DB::table('transport_map_student as mapping')
            ->join('tblstudent_enrollment as enrollment', function ($join) use ($syear) {
                $join->on('enrollment.student_id', '=', 'mapping.student_id')
                    ->where('enrollment.syear', $syear)->whereNull('enrollment.end_date');
            })
            ->where('mapping.from_bus_id', (int) $row['from_bus_id'])
            ->where('mapping.from_shift_id', (int) $row['from_shift_id'])
            ->where('mapping.sub_institute_id', $tenantId)->where('mapping.syear', $syear)
            ->where('mapping.student_id', '<>', (int) $row['student_id'])
            ->distinct()->count('mapping.student_id');

        return ($reserved + $claimed) >= $capacity ? 'The selected vehicle has no remaining capacity.' : null;
    }

    /** Bulk unmap students for the current academic year. */
    public function bulkDestroy(Request $request)
    {
        if ($response = $this->guard($request, 'student-mappings', 'delete')) {
            return $response;
        }
        $students = $request->input('student_ids');
        if (! is_array($students) || empty($students)) {
            return $this->failure('No student was selected.');
        }
        $students = array_values(array_filter(array_map('intval', $students)));
        if (empty($students)) {
            return $this->failure('No student was selected.');
        }

        $deleted = DB::table('transport_map_student')
            ->where('sub_institute_id', $request->integer('sub_institute_id'))
            ->where('syear', $request->integer('syear'))
            ->whereIn('student_id', $students)->delete();

        if (! $deleted) {
            return $this->failure('No mapping was found for the selected student(s).', 404);
        }

        return response()->json([
            'status_code' => 1,
            'message' => "{$deleted} student(s) unmapped successfully.",
            'data' => ['deleted' => $deleted],
        ]);
    }
}
