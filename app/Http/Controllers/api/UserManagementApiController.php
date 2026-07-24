<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserManagementApiController extends Controller
{
    use GetsJwtToken;

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
        ]);
        if ($validator->fails()) {
            return response()->json(['status_code' => 0, 'message' => $validator->messages()->first(), 'data' => []], 422);
        }

        $authorization = (string) $request->header('Authorization');
        $token = preg_replace('/^Bearer\s+/i', '', $authorization);
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

        $actor = DB::table('tbluser as u')
            ->join('tbluserprofilemaster as p', 'p.id', '=', 'u.user_profile_id')
            ->select('u.id', 'u.user_profile_id', 'u.sub_institute_id', 'p.name as profile_name')
            ->where('u.id', $actorId)
            ->where('u.sub_institute_id', $tenantId)
            ->where('u.status', 1)
            ->first();
        if (! $actor) {
            return response()->json(['status_code' => 2, 'message' => 'Active user context was not found.', 'data' => []], 403);
        }

        $request->attributes->set('users_actor', $actor);
        return null;
    }

    private function permissions(Request $request, string $routeName): array
    {
        $actor = $request->attributes->get('users_actor');
        if (in_array(strtolower((string) $actor->profile_name), ['admin', 'super admin'], true)) {
            return ['view' => true, 'add' => true, 'edit' => true, 'delete' => true, 'admin' => true];
        }

        $menuId = DB::table('tblmenumaster')->where('status', 1)->where('link', $routeName)->value('id');
        $rights = null;
        if ($menuId) {
            $rights = DB::table('tblindividual_rights')
                ->where('menu_id', $menuId)->where('profile_id', $actor->user_profile_id)
                ->where('user_id', $actor->id)->where('sub_institute_id', $actor->sub_institute_id)->first();
            if (! $rights) {
                $rights = DB::table('tblgroupwise_rights')
                    ->where('menu_id', $menuId)->where('profile_id', $actor->user_profile_id)
                    ->where('sub_institute_id', $actor->sub_institute_id)->first();
            }
        }
        return [
            'view' => (bool) ($rights->can_view ?? false),
            'add' => (bool) ($rights->can_add ?? false),
            'edit' => (bool) ($rights->can_edit ?? false),
            'delete' => (bool) ($rights->can_delete ?? false),
            'admin' => false,
        ];
    }

    private function authorizeAction(Request $request, string $routeName, string $action)
    {
        $permissions = $this->permissions($request, $routeName);
        if (! ($permissions[$action] ?? false)) {
            return response()->json(['status_code' => 0, 'message' => 'You do not have permission to '.$action.' this resource.', 'data' => []], 403);
        }
        return null;
    }

    private function bootstrap(int $tenantId): array
    {
        $customFields = DB::table('tblcustom_fields')
            ->where('sub_institute_id', $tenantId)->where('status', 1)
            ->where('table_name', 'tbluser')->where('user_type', '')
            ->orderBy('sort_order')->get();
        $fieldIds = $customFields->pluck('id');
        $customOptions = DB::table('tblfields_data')->whereIn('field_id', $fieldIds)->get()->groupBy('field_id');

        return [
            'profiles' => DB::table('tbluserprofilemaster')->select('id', 'parent_id', 'name', 'description', 'sort_order', 'status')
                ->where('sub_institute_id', $tenantId)->orderBy('sort_order')->get(),
            'subjects' => DB::table('subject')->select('id', 'subject_name as name')->where('sub_institute_id', $tenantId)->orderBy('subject_name')->get(),
            'departments' => DB::table('hrms_departments')->select('id', 'department as name')->where('sub_institute_id', $tenantId)->where('status', 1)->orderBy('department')->get(),
            'job_titles' => DB::table('hrms_job_titles')->select('id', 'title as name')->where('sub_institute_id', $tenantId)->orderBy('title')->get(),
            'employees' => DB::table('tbluser')->select('id', DB::raw('concat_ws(" ", first_name, middle_name, last_name) as name'))
                ->where('sub_institute_id', $tenantId)->where('status', 1)->orderBy('first_name')->get(),
            'custom_fields' => $customFields,
            'custom_options' => $customOptions,
            'name_suffixes' => ['Mr.', 'Mrs.', 'Miss.'],
            'new_employee_no' => ((int) DB::table('tbluser')->where('sub_institute_id', $tenantId)->whereNotNull('employee_no')->max(DB::raw('CAST(employee_no AS UNSIGNED)'))) + 1,
        ];
    }

    public function index(Request $request)
    {
        if ($response = $this->context($request)) return $response;
        if ($response = $this->authorizeAction($request, 'add_user.index', 'view')) return $response;
        $actor = $request->attributes->get('users_actor');
        $permissions = $this->permissions($request, 'add_user.index');
        $query = DB::table('tbluser as u')->join('tbluserprofilemaster as p', 'p.id', '=', 'u.user_profile_id')
            ->select('u.id', 'u.user_name', 'u.name_suffix', 'u.first_name', 'u.middle_name', 'u.last_name', 'u.email', 'u.mobile',
                'u.gender', 'u.birthdate', 'u.address', 'u.city', 'u.state', 'u.pincode', 'u.user_profile_id', 'u.join_year',
                'u.total_lecture', 'u.subject_ids', 'u.jobtitle_id', 'u.department_id', 'u.employee_no', 'u.qualification',
                'u.occupation', 'u.status', 'p.name as profile_name')
            ->where('u.sub_institute_id', $actor->sub_institute_id);
        if (! $permissions['admin']) $query->where('u.id', $actor->id);
        return response()->json(['status_code' => 1, 'message' => 'Success', 'data' => [
            'users' => $query->orderBy('u.first_name')->get(),
            'bootstrap' => $this->bootstrap($actor->sub_institute_id),
            'permissions' => $permissions,
        ]]);
    }

    public function show(Request $request, $id)
    {
        if ($response = $this->context($request)) return $response;
        if ($response = $this->authorizeAction($request, 'add_user.index', 'view')) return $response;
        $actor = $request->attributes->get('users_actor');
        $permissions = $this->permissions($request, 'add_user.index');
        if (! $permissions['admin'] && (int) $id !== (int) $actor->id) {
            return response()->json(['status_code' => 0, 'message' => 'You may only view your own user record.', 'data' => []], 403);
        }
        $user = DB::table('tbluser')->where('id', $id)->where('sub_institute_id', $actor->sub_institute_id)->first();
        if (! $user) return response()->json(['status_code' => 0, 'message' => 'User not found.', 'data' => []], 404);
        return response()->json(['status_code' => 1, 'message' => 'Success', 'data' => ['user' => $user, 'bootstrap' => $this->bootstrap($actor->sub_institute_id), 'permissions' => $permissions]]);
    }

    private function rules(int $tenantId, ?int $id = null): array
    {
        return [
            'name_suffix' => 'required|string|max:50',
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'user_name' => ['required', 'string', 'max:100', Rule::unique('tbluser')->where(fn ($query) => $query->where('sub_institute_id', $tenantId))->ignore($id)],
            'email' => ['required', 'email', 'max:100', Rule::unique('tbluser')->where(fn ($query) => $query->where('sub_institute_id', $tenantId))->ignore($id)],
            'mobile' => 'required|string|max:50',
            'gender' => 'required|in:M,F',
            'birthdate' => 'required|date',
            'address' => 'required|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:50',
            'user_profile_id' => ['required', 'integer', Rule::exists('tbluserprofilemaster', 'id')->where(fn ($query) => $query->where('sub_institute_id', $tenantId)->where('status', 1))],
            'join_year' => 'required|string|max:50',
            'password' => $id ? 'nullable|string|min:6|max:100' : 'required|string|min:6|max:100',
            'subject_ids' => 'nullable|array',
            'subject_ids.*' => 'integer',
            'total_lecture' => 'nullable|integer|min:0',
            'jobtitle_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
            'employee_no' => 'nullable|string|max:50',
            'qualification' => 'nullable|string|max:255',
            'occupation' => 'nullable|string|max:255',
        ];
    }

    private function values(Request $request): array
    {
        $keys = ['name_suffix','first_name','middle_name','last_name','user_name','email','mobile','gender','birthdate','address','city','state','pincode',
            'user_profile_id','join_year','total_lecture','jobtitle_id','department_id','employee_no','qualification','occupation'];
        $values = $request->only($keys);
        $values['subject_ids'] = implode(',', $request->input('subject_ids', []));
        $values['status'] = 1;
        if ($request->filled('password')) {
            $values['password'] = Hash::make($request->input('password'));
            $values['plain_password'] = $request->input('password');
        }
        return $values;
    }

    public function store(Request $request)
    {
        if ($response = $this->context($request)) return $response;
        if ($response = $this->authorizeAction($request, 'add_user.index', 'add')) return $response;
        $actor = $request->attributes->get('users_actor');
        $validator = Validator::make($request->all(), $this->rules($actor->sub_institute_id));
        if ($validator->fails()) return response()->json(['status_code' => 0, 'message' => $validator->messages()->first(), 'errors' => $validator->errors(), 'data' => []], 422);
        $values = $this->values($request);
        $values['sub_institute_id'] = $actor->sub_institute_id;
        $values['client_id'] = 0;
        $id = DB::table('tbluser')->insertGetId($values);
        return response()->json(['status_code' => 1, 'message' => 'User created successfully.', 'data' => ['id' => $id]], 201);
    }

    public function update(Request $request, $id)
    {
        if ($response = $this->context($request)) return $response;
        if ($response = $this->authorizeAction($request, 'add_user.index', 'edit')) return $response;
        $actor = $request->attributes->get('users_actor');
        $record = DB::table('tbluser')->where('id', $id)->where('sub_institute_id', $actor->sub_institute_id)->first();
        if (! $record) return response()->json(['status_code' => 0, 'message' => 'User not found.', 'data' => []], 404);
        $validator = Validator::make($request->all(), $this->rules($actor->sub_institute_id, (int) $id));
        if ($validator->fails()) return response()->json(['status_code' => 0, 'message' => $validator->messages()->first(), 'errors' => $validator->errors(), 'data' => []], 422);
        DB::table('tbluser')->where('id', $id)->where('sub_institute_id', $actor->sub_institute_id)->update($this->values($request));
        return response()->json(['status_code' => 1, 'message' => 'User updated successfully.', 'data' => ['id' => (int) $id]]);
    }

    public function destroy(Request $request, $id)
    {
        if ($response = $this->context($request)) return $response;
        if ($response = $this->authorizeAction($request, 'add_user.index', 'delete')) return $response;
        $actor = $request->attributes->get('users_actor');
        if ((int) $id === (int) $actor->id) return response()->json(['status_code' => 0, 'message' => 'You cannot deactivate your own account.', 'data' => []], 422);
        $updated = DB::table('tbluser')->where('id', $id)->where('sub_institute_id', $actor->sub_institute_id)->update(['status' => 0]);
        if (! $updated) return response()->json(['status_code' => 0, 'message' => 'User not found.', 'data' => []], 404);
        return response()->json(['status_code' => 1, 'message' => 'User deactivated successfully.', 'data' => []]);
    }

    public function profiles(Request $request)
    {
        if ($response = $this->context($request)) return $response;
        if ($response = $this->authorizeAction($request, 'add_user_profile.index', 'view')) return $response;
        $actor = $request->attributes->get('users_actor');
        return response()->json(['status_code' => 1, 'message' => 'Success', 'data' => [
            'profiles' => DB::table('tbluserprofilemaster')->where('sub_institute_id', $actor->sub_institute_id)->orderBy('sort_order')->get(),
            'permissions' => $this->permissions($request, 'add_user_profile.index'),
        ]]);
    }

    public function storeProfile(Request $request)
    {
        if ($response = $this->context($request)) return $response;
        if ($response = $this->authorizeAction($request, 'add_user_profile.index', 'add')) return $response;
        return $this->saveProfile($request);
    }

    public function updateProfile(Request $request, $id)
    {
        if ($response = $this->context($request)) return $response;
        if ($response = $this->authorizeAction($request, 'add_user_profile.index', 'edit')) return $response;
        return $this->saveProfile($request, (int) $id);
    }

    private function saveProfile(Request $request, ?int $id = null)
    {
        $actor = $request->attributes->get('users_actor');
        $validator = Validator::make($request->all(), [
            'name' => ['required','string','max:255', Rule::unique('tbluserprofilemaster')->where(fn ($q) => $q->where('sub_institute_id', $actor->sub_institute_id))->ignore($id)],
            'description' => 'required|string|max:255', 'parent_id' => 'required|integer|min:0', 'sort_order' => 'required|integer|min:0',
        ]);
        if ($validator->fails()) return response()->json(['status_code' => 0, 'message' => $validator->messages()->first(), 'errors' => $validator->errors(), 'data' => []], 422);
        if ($id && $request->integer('parent_id') === $id) return response()->json(['status_code' => 0, 'message' => 'A profile cannot be its own parent.', 'data' => []], 422);
        $values = $request->only('name','description','parent_id','sort_order') + ['status' => 1, 'sub_institute_id' => $actor->sub_institute_id];
        if ($id) DB::table('tbluserprofilemaster')->where('id', $id)->where('sub_institute_id', $actor->sub_institute_id)->update($values);
        else $id = DB::table('tbluserprofilemaster')->insertGetId($values);
        return response()->json(['status_code' => 1, 'message' => 'User profile saved successfully.', 'data' => ['id' => $id]], $id ? 200 : 201);
    }

    public function destroyProfile(Request $request, $id)
    {
        if ($response = $this->context($request)) return $response;
        if ($response = $this->authorizeAction($request, 'add_user_profile.index', 'delete')) return $response;
        $actor = $request->attributes->get('users_actor');
        if (DB::table('tbluser')->where('sub_institute_id', $actor->sub_institute_id)->where('user_profile_id', $id)->exists()) {
            return response()->json(['status_code' => 0, 'message' => 'This profile is assigned to users and cannot be deleted.', 'data' => []], 409);
        }
        DB::table('tbluserprofilemaster')->where('id', $id)->where('sub_institute_id', $actor->sub_institute_id)->delete();
        return response()->json(['status_code' => 1, 'message' => 'User profile deleted successfully.', 'data' => []]);
    }

    public function reportBootstrap(Request $request)
    {
        if ($response = $this->context($request)) return $response;
        if ($response = $this->authorizeAction($request, 'user_report.index', 'view')) return $response;
        $actor = $request->attributes->get('users_actor');
        $fields = DB::table('tblcustom_fields')->where('status', 1)->where('is_deleted', '!=', 'Y')->where('user_type', 'staff')
            ->where(fn ($q) => $q->where('common_to_all', 1)->orWhere('sub_institute_id', $actor->sub_institute_id))
            ->select('id','column_header','field_name','field_label','table_name')->orderBy('tab_sort_order')->orderBy('sort_order')->get();
        return response()->json(['status_code' => 1, 'message' => 'Success', 'data' => [
            'fields' => $fields,
            'profiles' => DB::table('tbluserprofilemaster')->select('id','name')->where('sub_institute_id', $actor->sub_institute_id)->orderBy('sort_order')->get(),
            'employees' => DB::table('tbluser')->select('id', DB::raw('concat_ws(" ", first_name, middle_name, last_name) as name'))->where('sub_institute_id', $actor->sub_institute_id)->orderBy('first_name')->get(),
            'permissions' => $this->permissions($request, 'user_report.index'),
        ]]);
    }

    public function report(Request $request)
    {
        if ($response = $this->context($request)) return $response;
        if ($response = $this->authorizeAction($request, 'user_report.index', 'view')) return $response;
        $validator = Validator::make($request->all(), ['field_ids' => 'required|array|min:1', 'field_ids.*' => 'integer', 'status' => 'required|in:0,1', 'profile_ids' => 'array', 'employee_ids' => 'array']);
        if ($validator->fails()) return response()->json(['status_code' => 0, 'message' => $validator->messages()->first(), 'errors' => $validator->errors(), 'data' => []], 422);
        $actor = $request->attributes->get('users_actor');
        $fields = DB::table('tblcustom_fields')->whereIn('id', $request->input('field_ids'))->where('status', 1)->where('is_deleted', '!=', 'Y')->where('user_type', 'staff')
            ->where(fn ($q) => $q->where('common_to_all', 1)->orWhere('sub_institute_id', $actor->sub_institute_id))->get();
        if ($fields->count() !== count(array_unique($request->input('field_ids')))) return response()->json(['status_code' => 0, 'message' => 'One or more report fields are invalid.', 'data' => []], 422);
        $allowedTables = ['tbluser','tbluserprofilemaster','hrms_departments','tbluser_past_education'];
        $selects = []; $headers = [];
        foreach ($fields as $field) {
            if (! in_array($field->table_name, $allowedTables, true) || ! preg_match('/^[A-Za-z0-9_]+$/', $field->field_name)) continue;
            $alias = 'field_'.$field->id;
            $selects[] = $field->field_name === 'user_name' ? DB::raw('concat_ws(" ", tbluser.first_name, tbluser.middle_name, tbluser.last_name) as '.$alias) : $field->table_name.'.'.$field->field_name.' as '.$alias;
            $headers[] = ['key' => $alias, 'label' => $field->field_label];
        }
        if (! $selects) return response()->json(['status_code' => 0, 'message' => 'No safe report fields were selected.', 'data' => []], 422);
        $query = DB::table('tbluser')->select($selects)->join('tbluserprofilemaster', 'tbluser.user_profile_id', '=', 'tbluserprofilemaster.id')
            ->leftJoin('hrms_departments','hrms_departments.id','=','tbluser.department_id')->leftJoin('tbluser_past_education','tbluser_past_education.user_id','=','tbluser.id')
            ->where('tbluser.sub_institute_id', $actor->sub_institute_id)->where('tbluser.status', $request->integer('status'));
        if ($request->input('profile_ids')) $query->whereIn('tbluser.user_profile_id', $request->input('profile_ids'));
        if ($request->input('employee_ids')) $query->whereIn('tbluser.id', $request->input('employee_ids'));
        return response()->json(['status_code' => 1, 'message' => 'Success', 'data' => ['headers' => $headers, 'rows' => $query->get()]]);
    }
}
