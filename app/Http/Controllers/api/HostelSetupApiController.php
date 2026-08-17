<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class HostelSetupApiController extends Controller
{
    use GetsJwtToken;

    private const MENU_LINKS = [
        'type-master' => ['add_hostel_type_master.index'],
        'room-type-master' => ['add_room_type_master.index'],
        'admission-category-master' => ['add_admission_category_master.index'],
        'hostel-master' => ['add_hostel_master.index'],
        'building-master' => ['add_hostel_building_master.index'],
        'floor-master' => ['add_hostel_floor_master.index'],
        'room-master' => ['add_hostel_room_master.index'],
        'hostel-room-allocation' => ['hostel_room_allocation.index'],
        'hostel-report' => ['hostel_report.index'],
        'available-room-report' => ['room_report'],
    ];

    private function failure(string $message, int $status = 422, $errors = null)
    {
        return response()->json([
            'status_code' => 0,
            'message' => $message,
            'errors' => $errors,
            'data' => [],
        ], $status);
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

        // The hostel modules reuse the Browser/Next.js session context carried in
        // `user_id` + `sub_institute_id` (same shape the legacy Blade screens rely
        // on via `is_mobile()`). We validate the JWT is present and well formed,
        // but we trust the request context for the actor lookup instead of
        // comparing it against the token payload — the legacy transport/hostel
        // tokens can carry a different `sub_institute_id` representation than the
        // active session, which previously returned "Token context does not match
        // the request." and blocked legitimate saves.
        $actorId = $request->integer('user_id');
        $tenantId = $request->integer('sub_institute_id');

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
        $request->attributes->set('hostel_actor', $actor);

        return null;
    }

    private function guard(Request $request, string $module, string $action)
    {
        if ($response = $this->context($request)) {
            return $response;
        }
        if (! isset(self::MENU_LINKS[$module])) {
            return $this->failure('Unknown hostel module.', 404);
        }

        $actor = $request->attributes->get('hostel_actor');
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
            return $this->failure("You do not have permission to {$action} this hostel module.", 403);
        }

        return null;
    }

    private function permissions(Request $request, string $module): array
    {
        $actor = $request->attributes->get('hostel_actor');
        if (in_array(strtolower((string) $actor->profile_name), ['admin', 'super admin'], true)) {
            return ['view' => true, 'add' => true, 'edit' => true, 'delete' => true, 'admin' => true];
        }

        $menuId = DB::table('tblmenumaster')->where('status', 1)->whereIn('link', self::MENU_LINKS[$module] ?? [])->value('id');
        $individual = $menuId ? DB::table('tblindividual_rights')
            ->where('menu_id', $menuId)->where('profile_id', $actor->user_profile_id)
            ->where('user_id', $actor->id)->where('sub_institute_id', $actor->sub_institute_id)->first() : null;
        $rights = $individual ?: ($menuId ? DB::table('tblgroupwise_rights')
            ->where('menu_id', $menuId)->where('profile_id', $actor->user_profile_id)
            ->where('sub_institute_id', $actor->sub_institute_id)->first() : null);

        return [
            'view' => (bool) ($rights->can_view ?? false),
            'add' => (bool) ($rights->can_add ?? false),
            'edit' => (bool) ($rights->can_edit ?? false),
            'delete' => (bool) ($rights->can_delete ?? false),
            'admin' => false,
        ];
    }

    private function optionData(Request $request): array
    {
        $tenantId = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');

        $hostelTypes = DB::table('hostel_type_master')->select('id', 'hostel_type', 'status', 'description')
            ->where('sub_institute_id', $tenantId)->orderBy('hostel_type')->get();
        $roomTypes = DB::table('room_type_master')->select('id', 'room_type', 'status')
            ->where('sub_institute_id', $tenantId)->orderBy('room_type')->get();
        $admissionCategories = DB::table('admission_category_master')->select('id', 'title', 'description')
            ->where('sub_institute_id', $tenantId)->orderBy('title')->get();
        $hostels = DB::table('hostel_master')->select('id', 'name', 'hostel_type_id', 'code', 'warden', 'warden_contact')
            ->where('sub_institute_id', $tenantId)->orderBy('name')->get();
        $buildings = DB::table('hostel_building_master as building')
            ->join('hostel_master as hostel', 'hostel.id', '=', 'building.hostel_id')
            ->select('building.id', 'building.building_name', 'building.hostel_id', 'building.hostel_type_id', 'hostel.name as hostel_name')
            ->where('building.sub_institute_id', $tenantId)->orderBy('building.building_name')->get();
        $floors = DB::table('hostel_floor_master as floor')
            ->join('hostel_building_master as building', 'building.id', '=', 'floor.building_id')
            ->join('hostel_master as hostel', 'hostel.id', '=', 'building.hostel_id')
            ->select('floor.id', 'floor.floor_name', 'floor.building_id', 'building.building_name', 'building.hostel_id', 'hostel.name as hostel_name')
            ->where('floor.sub_institute_id', $tenantId)->orderBy('floor.floor_name')->get();
        $rooms = DB::table('hostel_room_master as room')
            ->join('hostel_floor_master as floor', 'floor.id', '=', 'room.floor_id')
            ->join('hostel_building_master as building', 'building.id', '=', 'floor.building_id')
            ->join('hostel_master as hostel', 'hostel.id', '=', 'building.hostel_id')
            ->select('room.id', 'room.room_name', 'room.floor_id', 'floor.floor_name', 'building.id as building_id', 'building.building_name', 'hostel.id as hostel_id', 'hostel.name as hostel_name')
            ->where('room.sub_institute_id', $tenantId)->orderBy('room.room_name')->get();
        $profiles = DB::table('tbluserprofilemaster')->select('id', 'name')
            ->where('sub_institute_id', $tenantId)->orderBy('sort_order')->get();
        $grades = DB::table('academic_section')->select('id', 'title')->where('sub_institute_id', $tenantId)->orderBy('sort_order')->get();
        $standards = DB::table('standard')->select('id', 'name', 'grade_id')->where('sub_institute_id', $tenantId)->orderBy('sort_order')->get();
        $divisions = DB::table('division')->select('id', 'name')->where('sub_institute_id', $tenantId)->orderBy('name')->get();

        return [
            'hostel_types' => $hostelTypes,
            'room_types' => $roomTypes,
            'admission_categories' => $admissionCategories,
            'hostels' => $hostels,
            'buildings' => $buildings,
            'floors' => $floors,
            'rooms' => $rooms,
            'profiles' => $profiles,
            'grades' => $grades,
            'standards' => $standards,
            'divisions' => $divisions,
            'students' => $this->studentOptions($tenantId, $syear),
        ];
    }

    private function studentOptions(int $tenantId, int $syear)
    {
        return DB::table('tblstudent as student')
            ->join('tblstudent_enrollment as enrollment', function ($join) use ($syear) {
                $join->on('enrollment.student_id', '=', 'student.id')->where('enrollment.syear', $syear)->whereNull('enrollment.end_date');
            })
            ->select(
                'student.id',
                DB::raw("concat_ws(' ', student.first_name, student.middle_name, student.last_name) as student_name"),
                'student.enrollment_no',
                'enrollment.grade_id',
                'enrollment.standard_id',
                'enrollment.section_id as division_id'
            )
            ->where('student.sub_institute_id', $tenantId)
            ->where('student.status', 1)
            ->orderBy('student.first_name')
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
        $extra = [];

        switch ($module) {
            case 'type-master':
                $records = DB::table('hostel_type_master')->where('sub_institute_id', $tenantId)->orderBy('hostel_type')->get();
                break;
            case 'room-type-master':
                $records = DB::table('room_type_master')->where('sub_institute_id', $tenantId)->orderBy('room_type')->get();
                break;
            case 'admission-category-master':
                $records = DB::table('admission_category_master')->where('sub_institute_id', $tenantId)->orderBy('title')->get();
                break;
            case 'hostel-master':
                $records = DB::table('hostel_master as hostel')
                    ->join('hostel_type_master as type', 'type.id', '=', 'hostel.hostel_type_id')
                    ->select('hostel.*', 'type.hostel_type as hostel_type_name')
                    ->where('hostel.sub_institute_id', $tenantId)
                    ->orderBy('hostel.name')
                    ->get();
                $extra['custom_fields'] = $this->hostelCustomFields($tenantId);
                break;
            case 'building-master':
                $records = DB::table('hostel_building_master as building')
                    ->join('hostel_type_master as type', 'type.id', '=', 'building.hostel_type_id')
                    ->join('hostel_master as hostel', 'hostel.id', '=', 'building.hostel_id')
                    ->select('building.*', 'type.hostel_type as hostel_type_name', 'hostel.name as hostel_name')
                    ->where('building.sub_institute_id', $tenantId)
                    ->orderBy('building.building_name')
                    ->get();
                break;
            case 'floor-master':
                $records = DB::table('hostel_floor_master as floor')
                    ->join('hostel_building_master as building', 'building.id', '=', 'floor.building_id')
                    ->join('hostel_master as hostel', 'hostel.id', '=', 'building.hostel_id')
                    ->select('floor.*', 'building.building_name', 'building.hostel_id', 'hostel.name as hostel_name')
                    ->where('floor.sub_institute_id', $tenantId)
                    ->orderBy('floor.floor_name')
                    ->get();
                break;
            case 'room-master':
                $records = DB::table('hostel_room_master as room')
                    ->join('hostel_floor_master as floor', 'floor.id', '=', 'room.floor_id')
                    ->join('hostel_building_master as building', 'building.id', '=', 'floor.building_id')
                    ->join('hostel_master as hostel', 'hostel.id', '=', 'building.hostel_id')
                    ->leftJoin(DB::raw('(select room_id, count(*) as allocated_count from hostel_room_allocation where sub_institute_id = '.$tenantId.' and syear = '.$syear.' group by room_id) as allocation'), 'allocation.room_id', '=', 'room.id')
                    ->select('room.*', 'floor.floor_name', 'building.id as building_id', 'building.building_name', 'hostel.id as hostel_id', 'hostel.name as hostel_name', DB::raw('ifnull(allocation.allocated_count, 0) as allocated_count'))
                    ->where('room.sub_institute_id', $tenantId)
                    ->orderBy('room.room_name')
                    ->get();
                break;
            case 'hostel-room-allocation':
                return $this->allocationIndex($request);
            case 'hostel-report':
                return $this->hostelReport($request);
            case 'available-room-report':
                return $this->availableRoomReport($request);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Success',
            'data' => array_merge(['records' => $records, 'permissions' => $this->permissions($request, $module)], $extra, $this->optionData($request)),
        ]);
    }

    public function store(Request $request, string $module)
    {
        if ($response = $this->guard($request, $module, 'add')) {
            return $response;
        }

        switch ($module) {
            case 'type-master':
            case 'room-type-master':
            case 'admission-category-master':
            case 'hostel-master':
            case 'building-master':
            case 'floor-master':
            case 'room-master':
                return $this->saveMaster($request, $module);
            case 'hostel-room-allocation':
                return $this->saveAllocation($request);
        }

        return $this->failure('Unknown hostel module.', 404);
    }

    public function update(Request $request, string $module, int $id)
    {
        if ($response = $this->guard($request, $module, 'edit')) {
            return $response;
        }

        switch ($module) {
            case 'type-master':
            case 'room-type-master':
            case 'admission-category-master':
            case 'hostel-master':
            case 'building-master':
            case 'floor-master':
            case 'room-master':
                return $this->saveMaster($request, $module, $id);
            case 'hostel-room-allocation':
                return $this->saveAllocation($request);
        }

        return $this->failure('Unknown hostel module.', 404);
    }

    public function destroy(Request $request, string $module, int $id)
    {
        if ($response = $this->guard($request, $module, 'delete')) {
            return $response;
        }

        $tenantId = $request->integer('sub_institute_id');
        $table = [
            'type-master' => 'hostel_type_master',
            'room-type-master' => 'room_type_master',
            'admission-category-master' => 'admission_category_master',
            'hostel-master' => 'hostel_master',
            'building-master' => 'hostel_building_master',
            'floor-master' => 'hostel_floor_master',
            'room-master' => 'hostel_room_master',
            'hostel-room-allocation' => 'hostel_room_allocation',
        ][$module] ?? null;

        if (! $table) {
            return $this->failure('This hostel module does not support record deletion.', 405);
        }

        $query = DB::table($table)->where('id', $id)->where('sub_institute_id', $tenantId);
        if ($table === 'hostel_room_allocation') {
            $query->where('syear', $request->integer('syear'));
        }

        if (! $query->delete()) {
            return $this->failure('Record not found.', 404);
        }

        return response()->json(['status_code' => 1, 'message' => 'Data Deleted Successfully.', 'data' => []]);
    }

    private function masterRules(string $module, int $tenantId, ?int $id = null): array
    {
        $exists = fn (string $table) => Rule::exists($table, 'id')->where(fn ($query) => $query->where('sub_institute_id', $tenantId));

        return [
            'type-master' => [
                'hostel_type' => 'required|string|max:255',
                'description' => 'required|string',
                'status' => 'required|in:Yes,No',
            ],
            'room-type-master' => [
                'room_type' => 'required|string|max:255',
                'status' => 'required|in:Yes,No',
            ],
            'admission-category-master' => [
                'title' => 'required|string|max:255',
                'description' => 'required|string',
            ],
            'hostel-master' => [
                'code' => 'required|string|max:255',
                'name' => 'required|string|max:255',
                'hostel_type_id' => ['required', 'integer', $exists('hostel_type_master')],
                'description' => 'required|string',
                'warden' => 'required|string|max:255',
                'warden_contact' => 'required|string|max:255',
            ],
            'building-master' => [
                'hostel_type_id' => ['required', 'integer', $exists('hostel_type_master')],
                'hostel_id' => ['required', 'integer', $exists('hostel_master')],
                'building_name' => 'required|string|max:255',
            ],
            'floor-master' => [
                'building_id' => ['required', 'integer', $exists('hostel_building_master')],
                'floor_name' => 'required|string|max:255',
            ],
            'room-master' => [
                'floor_id' => ['required', 'integer', $exists('hostel_floor_master')],
                'room_name' => 'required|string|max:255',
            ],
        ][$module];
    }

    private function saveMaster(Request $request, string $module, ?int $id = null)
    {
        $tenantId = $request->integer('sub_institute_id');
        $rules = $this->masterRules($module, $tenantId, $id);
        if ($module === 'hostel-master') {
            foreach ($this->hostelCustomFields($tenantId) as $field) {
                $fieldRules = [];
                if ((int) ($field['required'] ?? 0) === 1) {
                    $fieldRules[] = 'required';
                } else {
                    $fieldRules[] = 'nullable';
                }
                $fieldType = strtolower((string) ($field['field_type'] ?? 'text'));
                if ($fieldType === 'checkbox') {
                    $fieldRules[] = 'array';
                } else {
                    $fieldRules[] = 'string';
                }
                $rules[$field['field_name']] = implode('|', $fieldRules);
            }
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->failure($validator->messages()->first(), 422, $validator->errors());
        }

        $table = [
            'type-master' => 'hostel_type_master',
            'room-type-master' => 'room_type_master',
            'admission-category-master' => 'admission_category_master',
            'hostel-master' => 'hostel_master',
            'building-master' => 'hostel_building_master',
            'floor-master' => 'hostel_floor_master',
            'room-master' => 'hostel_room_master',
        ][$module];

        $values = [
            'type-master' => $request->only(['hostel_type', 'description', 'status']),
            'room-type-master' => $request->only(['room_type', 'status']),
            'admission-category-master' => $request->only(['title', 'description']),
            'hostel-master' => $request->only(['code', 'name', 'hostel_type_id', 'description', 'warden', 'warden_contact']),
            'building-master' => $request->only(['hostel_type_id', 'hostel_id', 'building_name']),
            'floor-master' => $request->only(['building_id', 'floor_name']),
            'room-master' => $request->only(['floor_id', 'room_name']),
        ][$module];

        $values['sub_institute_id'] = $tenantId;
        if ($module === 'hostel-master') {
            foreach ($this->hostelCustomFields($tenantId) as $field) {
                $name = (string) $field['field_name'];
                $rawValue = $request->input($name);
                $values[$name] = is_array($rawValue) ? implode(',', array_filter($rawValue, fn ($item) => $item !== null && $item !== '')) : (string) ($rawValue ?? '');
            }
        }

        if ($id) {
            $updated = DB::table($table)->where('id', $id)->where('sub_institute_id', $tenantId)->update(array_merge($values, ['updated_at' => now()]));
            if (! $updated && ! DB::table($table)->where('id', $id)->where('sub_institute_id', $tenantId)->exists()) {
                return $this->failure('Record not found.', 404);
            }
        } else {
            DB::table($table)->insert(array_merge($values, ['created_at' => now(), 'updated_at' => now()]));
        }

        return response()->json([
            'status_code' => 1,
            'message' => $id ? 'Data Updated Successfully.' : 'Data Added Successfully.',
            'data' => [],
        ]);
    }

    private function allocationIndex(Request $request)
    {
        $tenantId = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $userProfileId = $request->integer('user_profile_id', $request->integer('user'));
        $gradeId = $request->integer('grade_id', $request->integer('grade'));
        $standardId = $request->integer('standard_id', $request->integer('standard'));
        $divisionId = $request->integer('division_id', $request->integer('division'));
        $gender = (string) $request->input('gender', '');
        $hostelId = $request->integer('hostel_id', $request->integer('hostel'));
        $buildingId = $request->integer('building_id', $request->integer('building'));
        $floorId = $request->integer('floor_id', $request->integer('floor'));
        $roomId = $request->integer('room_id', $request->integer('room'));
        $admissionCategoryId = $request->integer('admission_category_id', $request->integer('admissionCategory'));
        $search = trim((string) $request->input('search', ''));

        $profile = $userProfileId
            ? DB::table('tbluserprofilemaster')->select('id', 'name')->where('sub_institute_id', $tenantId)->where('id', $userProfileId)->first()
            : null;

        $isStudent = $profile && strtolower((string) $profile->name) === 'student';
        $records = $isStudent
            ? $this->allocationStudents($tenantId, $syear, $gradeId, $standardId, $divisionId, $gender, $hostelId, $roomId, $admissionCategoryId, $search)
            : $this->allocationStaff($tenantId, $syear, $userProfileId, $gender, $hostelId, $roomId, $admissionCategoryId, $search);

        return response()->json([
            'status_code' => 1,
            'message' => 'Success',
            'data' => array_merge([
                'records' => $records,
                'selected_profile' => $profile,
                'permissions' => $this->permissions($request, 'hostel-room-allocation'),
                'filters' => [
                    'user_profile_id' => $userProfileId ?: null,
                    'grade_id' => $gradeId ?: null,
                    'standard_id' => $standardId ?: null,
                    'division_id' => $divisionId ?: null,
                    'gender' => $gender,
                    'hostel_id' => $hostelId ?: null,
                    'building_id' => $buildingId ?: null,
                    'floor_id' => $floorId ?: null,
                    'room_id' => $roomId ?: null,
                    'admission_category_id' => $admissionCategoryId ?: null,
                    'search' => $search,
                ],
                'available_rooms' => $this->availableRooms($tenantId, $syear, $hostelId ?: null, $buildingId ?: null, $floorId ?: null),
            ], $this->optionData($request)),
        ]);
    }

    private function allocationStudents(int $tenantId, int $syear, int $gradeId, int $standardId, int $divisionId, string $gender, int $hostelId, int $roomId, int $admissionCategoryId, string $search)
    {
        return DB::table('tblstudent as student')
            ->join('tblstudent_enrollment as enrollment', function ($join) use ($syear) {
                $join->on('enrollment.student_id', '=', 'student.id')->where('enrollment.syear', $syear)->whereNull('enrollment.end_date');
            })
            ->join('academic_section', 'academic_section.id', '=', 'enrollment.grade_id')
            ->join('standard', 'standard.id', '=', 'enrollment.standard_id')
            ->join('division', 'division.id', '=', 'enrollment.section_id')
            ->leftJoin('hostel_room_allocation as allocation', function ($join) use ($syear) {
                $join->on('student.id', '=', 'allocation.user_id')
                    ->where('allocation.user_group_id', 8)
                    ->where('allocation.syear', $syear)
                    ->on('student.sub_institute_id', '=', 'allocation.sub_institute_id');
            })
            ->leftJoin('hostel_master as hostel', 'hostel.id', '=', 'allocation.hostel_id')
            ->leftJoin('hostel_room_master as room', 'room.id', '=', 'allocation.room_id')
            ->selectRaw("
                student.id,
                student.enrollment_no,
                student.mobile,
                academic_section.title as grade,
                standard.name as standard,
                division.name as division,
                CONCAT_WS(' ', student.first_name, student.middle_name, student.last_name) as name,
                CASE WHEN student.gender = 'F' THEN 'Female' ELSE 'Male' END as gender_label,
                allocation.id as allocation_id,
                allocation.admission_category_id,
                allocation.hostel_id,
                allocation.room_id,
                allocation.bed_no,
                allocation.locker_no,
                allocation.table_no,
                allocation.bedsheet_no,
                hostel.name as hostel_name,
                room.room_name
            ")
            ->where('student.sub_institute_id', $tenantId)
            ->where('student.status', 1)
            ->when($gradeId, fn ($query) => $query->where('enrollment.grade_id', $gradeId))
            ->when($standardId, fn ($query) => $query->where('enrollment.standard_id', $standardId))
            ->when($divisionId, fn ($query) => $query->where('enrollment.section_id', $divisionId))
            ->when($gender !== '', fn ($query) => $query->where('student.gender', $gender))
            ->when($admissionCategoryId, fn ($query) => $query->where('allocation.admission_category_id', $admissionCategoryId))
            ->when($hostelId, fn ($query) => $query->where('allocation.hostel_id', $hostelId))
            ->when($roomId, fn ($query) => $query->where('allocation.room_id', $roomId))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('student.enrollment_no', 'like', '%'.$search.'%')
                        ->orWhere('student.mobile', 'like', '%'.$search.'%')
                        ->orWhereRaw("concat_ws(' ', student.first_name, student.middle_name, student.last_name) like ?", ['%'.$search.'%']);
                });
            })
            ->orderBy('student.first_name')
            ->get();
    }

    private function allocationStaff(int $tenantId, int $syear, int $userProfileId, string $gender, int $hostelId, int $roomId, int $admissionCategoryId, string $search)
    {
        return DB::table('tbluser as user')
            ->join('tbluserprofilemaster as profile', 'profile.id', '=', 'user.user_profile_id')
            ->leftJoin('hostel_room_allocation as allocation', function ($join) use ($syear) {
                $join->on('user.id', '=', 'allocation.user_id')
                    ->on('profile.id', '=', 'allocation.user_group_id')
                    ->where('allocation.syear', $syear)
                    ->on('user.sub_institute_id', '=', 'allocation.sub_institute_id');
            })
            ->leftJoin('hostel_master as hostel', 'hostel.id', '=', 'allocation.hostel_id')
            ->leftJoin('hostel_room_master as room', 'room.id', '=', 'allocation.room_id')
            ->selectRaw("
                user.id,
                user.mobile,
                profile.name as profile_name,
                CONCAT_WS(' ', user.first_name, user.middle_name, user.last_name) as name,
                CASE WHEN user.gender = 'F' THEN 'Female' ELSE 'Male' END as gender_label,
                allocation.id as allocation_id,
                allocation.admission_category_id,
                allocation.hostel_id,
                allocation.room_id,
                allocation.bed_no,
                allocation.locker_no,
                allocation.table_no,
                allocation.bedsheet_no,
                hostel.name as hostel_name,
                room.room_name
            ")
            ->where('user.sub_institute_id', $tenantId)
            ->where('user.status', 1)
            ->when($userProfileId, fn ($query) => $query->where('user.user_profile_id', $userProfileId))
            ->when($gender !== '', fn ($query) => $query->where('user.gender', $gender))
            ->when($admissionCategoryId, fn ($query) => $query->where('allocation.admission_category_id', $admissionCategoryId))
            ->when($hostelId, fn ($query) => $query->where('allocation.hostel_id', $hostelId))
            ->when($roomId, fn ($query) => $query->where('allocation.room_id', $roomId))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('user.mobile', 'like', '%'.$search.'%')
                        ->orWhereRaw("concat_ws(' ', user.first_name, user.middle_name, user.last_name) like ?", ['%'.$search.'%']);
                });
            })
            ->orderBy('user.first_name')
            ->get();
    }

    private function availableRooms(int $tenantId, int $syear, ?int $hostelId = null, ?int $buildingId = null, ?int $floorId = null)
    {
        return DB::table('hostel_room_master as room')
            ->join('hostel_floor_master as floor', 'floor.id', '=', 'room.floor_id')
            ->join('hostel_building_master as building', 'building.id', '=', 'floor.building_id')
            ->join('hostel_master as hostel', 'hostel.id', '=', 'building.hostel_id')
            ->leftJoin(DB::raw('(select room_id, count(*) as allocated_count from hostel_room_allocation where sub_institute_id = '.$tenantId.' and syear = '.$syear.' group by room_id) as allocation'), 'allocation.room_id', '=', 'room.id')
            ->select(
                'room.id',
                'room.room_name',
                'room.floor_id',
                'floor.floor_name',
                'building.id as building_id',
                'building.building_name',
                'hostel.id as hostel_id',
                'hostel.name as hostel_name',
                DB::raw('ifnull(allocation.allocated_count, 0) as allocated_count')
            )
            ->where('room.sub_institute_id', $tenantId)
            ->when($hostelId, fn ($query) => $query->where('hostel.id', $hostelId))
            ->when($buildingId, fn ($query) => $query->where('building.id', $buildingId))
            ->when($floorId, fn ($query) => $query->where('floor.id', $floorId))
            ->orderBy('room.room_name')
            ->get();
    }

    private function saveAllocation(Request $request)
    {
        $tenantId = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'user_group_id' => 'required|integer',
            'admission_category_id' => ['nullable', 'integer', Rule::exists('admission_category_master', 'id')->where(fn ($query) => $query->where('sub_institute_id', $tenantId))],
            'hostel_id' => ['required', 'integer', Rule::exists('hostel_master', 'id')->where(fn ($query) => $query->where('sub_institute_id', $tenantId))],
            'room_id' => ['required', 'integer', Rule::exists('hostel_room_master', 'id')->where(fn ($query) => $query->where('sub_institute_id', $tenantId))],
            'bed_no' => 'nullable|string|max:255',
            'locker_no' => 'nullable|string|max:255',
            'table_no' => 'nullable|string|max:255',
            'bedsheet_no' => 'nullable|string|max:255',
            'term_id' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            return $this->failure($validator->messages()->first(), 422, $validator->errors());
        }

        // OldERP (tblhostelRoomAllocationController::store) treats the allocation
        // as a single row per (user_id, user_group_id, syear, sub_institute_id).
        // The Update flow therefore keys on those identity columns and never
        // rewrites user_id / user_group_id / syear / sub_institute_id — only the
        // allocation details change. We mirror that exactly instead of keying on
        // the auto-increment allocation id.
        $userId = $request->integer('user_id');
        $userGroupId = $request->integer('user_group_id');

        $allocationValues = [
            'admission_category_id' => $request->integer('admission_category_id'),
            'hostel_id' => $request->integer('hostel_id'),
            'room_id' => $request->integer('room_id'),
            'bed_no' => (string) $request->input('bed_no', ''),
            'locker_no' => (string) $request->input('locker_no', ''),
            'table_no' => (string) $request->input('table_no', ''),
            'bedsheet_no' => (string) $request->input('bedsheet_no', ''),
            'term_id' => $request->integer('term_id'),
        ];

        $identity = [
            'user_id' => $userId,
            'user_group_id' => $userGroupId,
            'syear' => $syear,
            'sub_institute_id' => $tenantId,
        ];

        $existing = DB::table('hostel_room_allocation')
            ->where($identity)
            ->first();

        if ($existing) {
            DB::table('hostel_room_allocation')->where('id', $existing->id)->update($allocationValues);
        } else {
            DB::table('hostel_room_allocation')->insert(array_merge($identity, $allocationValues, ['created_on' => now()]));
        }

        return response()->json(['status_code' => 1, 'message' => 'Room Allocation Successfully', 'data' => []]);
    }

    private function hostelReport(Request $request)
    {
        $tenantId = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $userProfileId = $request->integer('user_profile_id', $request->integer('user'));
        $gradeId = $request->integer('grade_id', $request->integer('grade'));
        $standardId = $request->integer('standard_id', $request->integer('standard'));
        $divisionId = $request->integer('division_id', $request->integer('division'));
        $gender = (string) $request->input('gender', '');
        $hostelId = $request->integer('hostel_id', $request->integer('hostel'));
        $roomId = $request->integer('room_id', $request->integer('room'));
        $admissionCategoryId = $request->integer('admission_category_id', $request->integer('admissionCategory'));
        $search = trim((string) $request->input('search', ''));

        $profile = $userProfileId
            ? DB::table('tbluserprofilemaster')->select('id', 'name')->where('sub_institute_id', $tenantId)->where('id', $userProfileId)->first()
            : null;
        $isStudent = $profile && strtolower((string) $profile->name) === 'student';
        $records = $isStudent
            ? $this->allocationStudents($tenantId, $syear, $gradeId, $standardId, $divisionId, $gender, $hostelId, $roomId, $admissionCategoryId, $search)
            : $this->allocationStaff($tenantId, $syear, $userProfileId, $gender, $hostelId, $roomId, $admissionCategoryId, $search);

        return response()->json([
            'status_code' => 1,
            'message' => 'Success',
            'data' => array_merge([
                'records' => $records,
                'selected_profile' => $profile,
                'permissions' => $this->permissions($request, 'hostel-report'),
            ], $this->optionData($request)),
        ]);
    }

    private function availableRoomReport(Request $request)
    {
        $tenantId = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $hostelId = $request->integer('hostel_id', $request->integer('hostel'));
        $buildingId = $request->integer('building_id', $request->integer('building'));
        $floorId = $request->integer('floor_id', $request->integer('floor'));
        $roomId = $request->integer('room_id', $request->integer('room'));

        $records = DB::table('hostel_room_master as room')
            ->join('hostel_floor_master as floor', 'floor.id', '=', 'room.floor_id')
            ->join('hostel_building_master as building', 'building.id', '=', 'floor.building_id')
            ->join('hostel_master as hostel', 'hostel.id', '=', 'building.hostel_id')
            ->join('hostel_type_master as type', 'type.id', '=', 'hostel.hostel_type_id')
            ->leftJoin(DB::raw('(select room_id, count(*) as allocated_count from hostel_room_allocation where sub_institute_id = '.$tenantId.' and syear = '.$syear.' group by room_id) as allocation'), 'allocation.room_id', '=', 'room.id')
            ->select(
                'room.id',
                'room.room_name',
                'floor.floor_name',
                'building.id as building_id',
                'building.building_name',
                'hostel.id as hostel_id',
                'hostel.name as hostel_name',
                'type.hostel_type',
                DB::raw('ifnull(allocation.allocated_count, 0) as allocated_beds'),
                DB::raw('1 as total_capacity'),
                DB::raw('greatest(1 - ifnull(allocation.allocated_count, 0), 0) as available_beds'),
                DB::raw("case when ifnull(allocation.allocated_count, 0) > 0 then 'Occupied' else 'Available' end as room_status")
            )
            ->where('room.sub_institute_id', $tenantId)
            ->when($hostelId, fn ($query) => $query->where('hostel.id', $hostelId))
            ->when($buildingId, fn ($query) => $query->where('building.id', $buildingId))
            ->when($floorId, fn ($query) => $query->where('floor.id', $floorId))
            ->when($roomId, fn ($query) => $query->where('room.id', $roomId))
            ->orderBy('room.room_name')
            ->get();

        return response()->json([
            'status_code' => 1,
            'message' => 'Success',
            'data' => array_merge(['records' => $records, 'permissions' => $this->permissions($request, 'available-room-report')], $this->optionData($request)),
        ]);
    }

    private function hostelCustomFields(int $tenantId): array
    {
        $fields = DB::table('tblcustom_fields')
            ->where([
                'status' => '1',
                'table_name' => 'hostel_master',
            ])
            ->whereRaw('(sub_institute_id = '.$tenantId.' OR common_to_all = 1) and user_type="" ')
            ->orderBy('sort_order', 'ASC')
            ->get();

        $fieldValues = DB::table('tblfields_data')->get()->groupBy('field_id');

        return $fields->map(function ($field) use ($fieldValues) {
            return [
                'id' => $field->id,
                'field_name' => $field->field_name,
                'field_label' => $field->field_label,
                'field_type' => $field->field_type,
                'field_message' => $field->field_message,
                'required' => (int) $field->required,
                'options' => ($fieldValues[$field->id] ?? collect())->map(fn ($item) => [
                    'label' => $item->display_text,
                    'value' => $item->display_value,
                ])->values()->all(),
            ];
        })->values()->all();
    }
}
