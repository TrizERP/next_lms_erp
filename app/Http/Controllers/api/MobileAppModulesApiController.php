<?php

namespace App\Http\Controllers\api;

use App\Services\Access\MobileAppModules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Roles & Access -> Mobile app: which native app screens each role may open.
 *
 * Authentication, the admin-only rule and the token/school binding are inherited from
 * RoleAccessApiController::context(), so this screen is exactly as protected as the rest of
 * Roles & Access.
 */
class MobileAppModulesApiController extends RoleAccessApiController
{
    public function show(Request $request, int $profileId): JsonResponse
    {
        if ($response = $this->context($request)) {
            return $response;
        }
        $role = $this->profile($request, $profileId);
        if (! $role) {
            return $this->reply(0, 'That role was not found.', [], 404);
        }

        $audience = MobileAppModules::audienceFor($role->name);
        $granted = array_flip(MobileAppModules::grantedKeys($this->tenantId($request), $profileId));
        $groups = [];
        foreach ((array) config('mobile_app_modules.groups.'.$audience) as $group) {
            $groups[$group] = ['title' => $group, 'modules' => []];
        }
        foreach (MobileAppModules::catalog($audience) as $key => $m) {
            $groups[$m['group']]['modules'][] = ['key' => $key, 'title' => $m['title'], 'enabled' => isset($granted[$key])];
        }

        return $this->reply(1, 'Success', [
            'role' => ['id' => (int) $role->id, 'name' => trim($role->name), 'audience' => $audience],
            'enabled' => count($granted),
            'total' => count(MobileAppModules::catalog($audience)),
            'version' => MobileAppModules::versionFor($this->tenantId($request), $profileId),
            'groups' => array_values(array_filter($groups, fn ($g) => $g['modules'])),
        ]);
    }

    public function save(Request $request, int $profileId): JsonResponse
    {
        if ($response = $this->context($request)) {
            return $response;
        }
        $role = $this->profile($request, $profileId);
        if (! $role) {
            return $this->reply(0, 'That role was not found.', [], 404);
        }
        if (! is_array($request->input('module_keys'))) {
            return $this->reply(0, 'module_keys must be a list.', [], 422);
        }

        $tenant = $this->tenantId($request);
        $actor = (int) $request->attributes->get('role_access_actor')->id;
        $result = MobileAppModules::replace($tenant, $profileId, $role->name, array_map('strval', $request->input('module_keys')), $actor);

        Log::info('mobile app modules changed', ['tenant' => $tenant, 'profile' => $profileId, 'actor' => $actor] + $result);

        return $this->reply(1, 'Saved. The app picks this up the next time it opens or resumes.', $result + [
            'version' => MobileAppModules::versionFor($tenant, $profileId),
        ]);
    }

    private function tenantId(Request $request): int
    {
        return (int) $request->attributes->get('role_access_actor')->sub_institute_id;
    }

    private function profile(Request $request, int $profileId): ?object
    {
        return DB::table('tbluserprofilemaster')
            ->where('sub_institute_id', $this->tenantId($request))->where('id', $profileId)->where('status', 1)
            ->first(['id', 'name']);
    }

    private function reply(int $status, string $message, array $data, int $http = 200): JsonResponse
    {
        return response()->json(['status_code' => $status, 'message' => $message, 'data' => $data], $http);
    }
}
