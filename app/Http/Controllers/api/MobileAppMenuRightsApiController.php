<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MobileAppMenuRightsApiController extends Controller
{
    use GetsJwtToken;

    private const ROUTE_NAME = 'add_mobileapp_menu_rights.index';
    private const CONFIG_PROFILES = ['Admin', 'Teacher', 'Student'];

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

        $request->attributes->set('mobile_actor', $actor);

        return null;
    }

    private function permissions(Request $request): array
    {
        $actor = $request->attributes->get('mobile_actor');
        if (in_array(strtolower((string) $actor->profile_name), ['admin', 'super admin'], true)) {
            return ['view' => true, 'add' => true, 'edit' => true, 'delete' => true, 'admin' => true];
        }

        $menuId = DB::table('tblmenumaster')->where('status', 1)->where('link', self::ROUTE_NAME)->value('id');
        $rights = null;
        if ($menuId) {
            $rights = DB::table('tblindividual_rights')
                ->where('menu_id', $menuId)
                ->where('profile_id', $actor->user_profile_id)
                ->where('user_id', $actor->id)
                ->where('sub_institute_id', $actor->sub_institute_id)
                ->first();

            if (! $rights) {
                $rights = DB::table('tblgroupwise_rights')
                    ->where('menu_id', $menuId)
                    ->where('profile_id', $actor->user_profile_id)
                    ->where('sub_institute_id', $actor->sub_institute_id)
                    ->first();
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

    private function authorizeAction(Request $request, string $action)
    {
        $permissions = $this->permissions($request);
        if (! ($permissions[$action] ?? false)) {
            return $this->failure('You do not have permission to ' . $action . ' this resource.', 403);
        }

        return null;
    }

    private function activeProfile(Request $request, int $profileId)
    {
        $actor = $request->attributes->get('mobile_actor');

        return DB::table('tbluserprofilemaster')
            ->select('id', 'name', 'status', 'sort_order')
            ->where('sub_institute_id', $actor->sub_institute_id)
            ->where('id', $profileId)
            ->where('status', 1)
            ->first();
    }

    private function supportedProfileName(?string $name): ?string
    {
        $normalized = trim((string) $name);
        return in_array($normalized, self::CONFIG_PROFILES, true) ? $normalized : null;
    }

    private function tableForProfileName(string $profileName): string
    {
        return $profileName === 'Student' ? 'mobile_homescreen' : 'teacher_mobile_homescreen';
    }

    private function defaultRightsRows(string $profileName): array
    {
        $query = DB::table($this->tableForProfileName($profileName))
            ->where('sub_institute_id', 1)
            ->orderByRaw('main_sort_order,sub_title_sort_order');

        if ($profileName !== 'Student') {
            $query->where('user_profile_name', $profileName);
        }

        return $query->get()->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'user_profile_name' => (string) ($row->user_profile_name ?? ''),
                'main_title' => (string) ($row->main_title ?? ''),
                'menu_type' => (string) ($row->menu_type ?? ''),
                'main_title_color_code' => (string) ($row->main_title_color_code ?? ''),
                'main_title_background_image' => (string) ($row->main_title_background_image ?? ''),
                'sub_title_of_main' => (string) ($row->sub_title_of_main ?? ''),
                'sub_title_icon' => (string) ($row->sub_title_icon ?? ''),
                'sub_title_api' => (string) ($row->sub_title_api ?? ''),
                'sub_title_api_param' => (string) ($row->sub_title_api_param ?? ''),
                'main_sort_order' => (int) ($row->main_sort_order ?? 0),
                'sub_title_sort_order' => (int) ($row->sub_title_sort_order ?? 0),
                'screen_name' => (string) ($row->screen_name ?? ''),
                'status' => (string) ($row->status ?? ''),
            ];
        })->all();
    }

    private function configQuery(Request $request, string $profileName, bool $includeInactive)
    {
        $actor = $request->attributes->get('mobile_actor');
        $query = DB::table($this->tableForProfileName($profileName) . ' as mh')
            ->join('tbluserprofilemaster as up', function ($join) {
                $join->whereRaw('up.id = mh.user_profile_id AND up.sub_institute_id = mh.sub_institute_id AND up.name = mh.user_profile_name');
            })
            ->selectRaw('mh.*')
            ->where('mh.sub_institute_id', $actor->sub_institute_id)
            ->where('up.name', $profileName)
            ->orderByRaw('mh.main_sort_order,mh.sub_title_sort_order');

        if (! $includeInactive) {
            $query->where('mh.status', 'Yes');
        }

        return $query;
    }

    public function bootstrap(Request $request)
    {
        if ($response = $this->context($request)) return $response;
        if ($response = $this->authorizeAction($request, 'view')) return $response;

        $actor = $request->attributes->get('mobile_actor');

        $profiles = DB::table('tbluserprofilemaster')
            ->select('id', 'name', 'status', 'sort_order')
            ->where('sub_institute_id', $actor->sub_institute_id)
            ->where('status', 1)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'status_code' => 1,
            'message' => 'Success',
            'data' => [
                'profiles' => $profiles,
                'config_profiles' => self::CONFIG_PROFILES,
                'permissions' => $this->permissions($request),
            ],
        ]);
    }

    public function rights(Request $request, int $profileId)
    {
        if ($response = $this->context($request)) return $response;
        if ($response = $this->authorizeAction($request, 'view')) return $response;

        $actor = $request->attributes->get('mobile_actor');
        $profile = $this->activeProfile($request, $profileId);
        if (! $profile) {
            return $this->failure('User profile not found.', 404);
        }

        $profileName = $this->supportedProfileName($profile->name ?? null);
        if (! $profileName) {
            return response()->json([
                'status_code' => 1,
                'message' => 'Success',
                'data' => [
                    'profile' => $profile,
                    'rows' => [],
                    'selected' => [],
                ],
            ]);
        }

        $table = $this->tableForProfileName($profileName);
        $rows = $this->defaultRightsRows($profileName);
        $selected = DB::table($table)
            ->where('sub_institute_id', $actor->sub_institute_id)
            ->where('user_profile_id', $profileId)
            ->when($profileName !== 'Student', function ($query) use ($profileName) {
                $query->where('user_profile_name', $profileName);
            })
            ->pluck('screen_name')
            ->filter()
            ->values()
            ->all();

        return response()->json([
            'status_code' => 1,
            'message' => 'Success',
            'data' => [
                'profile' => $profile,
                'rows' => $rows,
                'selected' => $selected,
            ],
        ]);
    }

    public function saveRights(Request $request)
    {
        if ($response = $this->context($request)) return $response;
        if ($response = $this->authorizeAction($request, 'add')) return $response;

        $actor = $request->attributes->get('mobile_actor');
        $validator = Validator::make($request->all(), [
            'profile_id' => [
                'required',
                'integer',
                Rule::exists('tbluserprofilemaster', 'id')->where(function ($query) use ($actor) {
                    $query->where('sub_institute_id', $actor->sub_institute_id)->where('status', 1);
                }),
            ],
            'selected' => 'array',
        ]);
        if ($validator->fails()) {
            return $this->failure($validator->messages()->first(), 422, $validator->errors());
        }

        $profile = $this->activeProfile($request, $request->integer('profile_id'));
        if (! $profile) {
            return $this->failure('User profile not found.', 404);
        }

        $profileName = $this->supportedProfileName($profile->name ?? null);
        if (! $profileName) {
            return $this->failure('The selected profile is not supported by the Mobile App Menu Rights module.', 422);
        }

        $selected = $request->input('selected', []);
        $selectedScreens = collect(is_array($selected) ? $selected : [])
            ->filter(fn ($value) => (bool) $value)
            ->keys()
            ->map(fn ($value) => (string) $value)
            ->filter()
            ->values()
            ->all();

        $table = $this->tableForProfileName($profileName);
        $defaults = collect($this->defaultRightsRows($profileName))->keyBy('screen_name');

        foreach ($selectedScreens as $screenName) {
            if (! $defaults->has($screenName)) {
                return $this->failure('One or more selected mobile app screens are invalid.', 422);
            }
        }

        if (count($selectedScreens) > 0) {
            DB::transaction(function () use ($actor, $profile, $profileName, $table, $selectedScreens, $defaults) {
                DB::table($table)
                    ->where('sub_institute_id', $actor->sub_institute_id)
                    ->where('user_profile_id', $profile->id)
                    ->when($profileName !== 'Student', function ($query) use ($profileName) {
                        $query->where('user_profile_name', $profileName);
                    })
                    ->whereNotIn('screen_name', $selectedScreens)
                    ->delete();

                foreach ($selectedScreens as $screenName) {
                    $default = $defaults->get($screenName);
                    if (! $default) {
                        continue;
                    }

                    $exists = DB::table($table)
                        ->where('sub_institute_id', $actor->sub_institute_id)
                        ->where('user_profile_id', $profile->id)
                        ->where('user_profile_name', $profileName)
                        ->where('screen_name', $screenName)
                        ->exists();

                    if ($exists) {
                        DB::table($table)
                            ->where('sub_institute_id', $actor->sub_institute_id)
                            ->where('user_profile_id', $profile->id)
                            ->where('user_profile_name', $profileName)
                            ->where('screen_name', $screenName)
                            ->update(['updated_on' => now()]);
                        continue;
                    }

                    DB::table($table)->insert([
                        'user_profile_name' => $profileName,
                        'user_profile_id' => $profile->id,
                        'sub_institute_id' => $actor->sub_institute_id,
                        'main_title' => $default['main_title'],
                        'menu_type' => $default['menu_type'],
                        'main_title_color_code' => $default['main_title_color_code'],
                        'main_title_background_image' => $default['main_title_background_image'],
                        'sub_title_of_main' => $default['sub_title_of_main'],
                        'sub_title_icon' => $default['sub_title_icon'],
                        'sub_title_api' => $default['sub_title_api'],
                        'sub_title_api_param' => $default['sub_title_api_param'],
                        'main_sort_order' => $default['main_sort_order'],
                        'sub_title_sort_order' => $default['sub_title_sort_order'],
                        'status' => $default['status'],
                        'screen_name' => $default['screen_name'],
                        'created_on' => now(),
                    ]);
                }
            });
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Mobile App Menu Rights Added Successfully',
            'data' => [],
        ]);
    }

    public function configIndex(Request $request)
    {
        if ($response = $this->context($request)) return $response;
        if ($response = $this->authorizeAction($request, 'view')) return $response;

        $validator = Validator::make($request->all(), [
            'profile_name' => ['required', Rule::in(self::CONFIG_PROFILES)],
            'include_inactive' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return $this->failure($validator->messages()->first(), 422, $validator->errors());
        }

        $profileName = (string) $request->input('profile_name');
        $includeInactive = $request->boolean('include_inactive');
        $records = $this->configQuery($request, $profileName, $includeInactive)->get();

        return response()->json([
            'status_code' => 1,
            'message' => 'Success',
            'data' => [
                'profile_name' => $profileName,
                'include_inactive' => $includeInactive,
                'records' => $records,
            ],
        ]);
    }

    public function updateConfig(Request $request, int $id)
    {
        if ($response = $this->context($request)) return $response;
        if ($response = $this->authorizeAction($request, 'edit')) return $response;

        $validator = Validator::make($request->all(), [
            'profile_name' => ['required', Rule::in(self::CONFIG_PROFILES)],
            'main_title' => 'required|string',
            'main_title_color_code' => 'nullable|string',
            'main_title_background_image' => 'nullable|string',
            'main_sort_order' => 'nullable|integer',
            'sub_title_of_main' => 'nullable|string',
            'sub_title_icon' => 'nullable|string',
            'sub_title_sort_order' => 'nullable|integer',
            'status' => ['required', Rule::in(['Yes', 'No'])],
        ]);
        if ($validator->fails()) {
            return $this->failure($validator->messages()->first(), 422, $validator->errors());
        }

        $actor = $request->attributes->get('mobile_actor');
        $profileName = (string) $request->input('profile_name');
        $table = $this->tableForProfileName($profileName);
        $record = DB::table($table)
            ->where('id', $id)
            ->where('sub_institute_id', $actor->sub_institute_id)
            ->when($profileName !== 'Student', function ($query) use ($profileName) {
                $query->where('user_profile_name', $profileName);
            })
            ->first();

        if (! $record) {
            return $this->failure('Mobile app menu record not found.', 404);
        }

        $updatedOn = now();
        $updatedBy = (int) $actor->id;
        $updatedIp = (string) $request->ip();

        $mainData = [
            'updated_on' => $updatedOn,
            'updated_by' => $updatedBy,
            'updated_ip_address' => $updatedIp,
        ];

        $subData = [
            'sub_title_of_main' => (string) $request->input('sub_title_of_main', ''),
            'sub_title_icon' => (string) $request->input('sub_title_icon', ''),
            'sub_title_sort_order' => (int) $request->input('sub_title_sort_order', 0),
            'status' => (string) $request->input('status'),
            'updated_on' => $updatedOn,
            'updated_by' => $updatedBy,
            'updated_ip_address' => $updatedIp,
        ];

        $scope = function ($query) use ($actor, $profileName) {
            $query->where('sub_institute_id', $actor->sub_institute_id);
            if ($profileName !== 'Student') {
                $query->where('user_profile_name', $profileName);
            }
        };

        DB::transaction(function () use ($table, $record, $request, $mainData, $subData, $scope, $id) {
            $mainTitle = (string) $request->input('main_title');
            $mainTitleColorCode = (string) $request->input('main_title_color_code', '');
            $mainTitleBackgroundImage = (string) $request->input('main_title_background_image', '');
            $mainSortOrder = (int) $request->input('main_sort_order', 0);

            if ($mainTitle !== (string) ($record->main_title ?? '')) {
                DB::table($table)
                    ->where('main_title', (string) ($record->main_title ?? ''))
                    ->where($scope)
                    ->update($mainData + ['main_title' => $mainTitle]);
            }

            if ($mainTitleColorCode !== (string) ($record->main_title_color_code ?? '')) {
                DB::table($table)
                    ->where('main_title_color_code', (string) ($record->main_title_color_code ?? ''))
                    ->where('main_title', $mainTitle)
                    ->where($scope)
                    ->update($mainData + ['main_title_color_code' => $mainTitleColorCode]);
            }

            if ($mainTitleBackgroundImage !== (string) ($record->main_title_background_image ?? '')) {
                DB::table($table)
                    ->where('main_title_background_image', (string) ($record->main_title_background_image ?? ''))
                    ->where('main_title', $mainTitle)
                    ->where('main_title_color_code', $mainTitleColorCode)
                    ->where($scope)
                    ->update($mainData + ['main_title_background_image' => $mainTitleBackgroundImage]);
            }

            if ($mainSortOrder !== (int) ($record->main_sort_order ?? 0)) {
                DB::table($table)
                    ->where('main_sort_order', (int) ($record->main_sort_order ?? 0))
                    ->where('main_title', $mainTitle)
                    ->where('main_title_color_code', $mainTitleColorCode)
                    ->where('main_title_background_image', $mainTitleBackgroundImage)
                    ->where($scope)
                    ->update($mainData + ['main_sort_order' => $mainSortOrder]);
            }

            DB::table($table)->where('id', $id)->update($subData);
        });

        return response()->json([
            'status_code' => 1,
            'message' => 'Mobile App Menu Rights Updated Successfully',
            'data' => [],
        ]);
    }
}
