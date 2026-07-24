<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserLogReportApiController extends Controller
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

        $request->attributes->set('report_actor', $actor);

        return null;
    }

    private function canView(Request $request): bool
    {
        $actor = $request->attributes->get('report_actor');
        if (in_array(strtolower((string) $actor->profile_name), ['admin', 'super admin'], true)) {
            return true;
        }

        $menuId = DB::table('tblmenumaster')->where('status', 1)->where('link', 'user_log.index')->value('id');
        if (! $menuId) {
            return false;
        }

        $individual = DB::table('tblindividual_rights')
            ->where('menu_id', $menuId)
            ->where('profile_id', $actor->user_profile_id)
            ->where('user_id', $actor->id)
            ->where('sub_institute_id', $actor->sub_institute_id)
            ->first();
        $rights = $individual ?: DB::table('tblgroupwise_rights')
            ->where('menu_id', $menuId)
            ->where('profile_id', $actor->user_profile_id)
            ->where('sub_institute_id', $actor->sub_institute_id)
            ->first();

        return (bool) ($rights->can_view ?? false);
    }

    private function authorizeReport(Request $request)
    {
        if (! $this->canView($request)) {
            return response()->json(['status_code' => 0, 'message' => 'You do not have permission to view this report.', 'data' => []], 403);
        }

        return null;
    }

    public function bootstrap(Request $request)
    {
        if ($response = $this->context($request)) {
            return $response;
        }
        if ($response = $this->authorizeReport($request)) {
            return $response;
        }

        $actor = $request->attributes->get('report_actor');
        $users = DB::table('tbluser')
            ->select('id', DB::raw("concat_ws(' ', first_name, middle_name, last_name) as name"))
            ->where('sub_institute_id', $actor->sub_institute_id)
            ->where('status', 1)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return response()->json([
            'status_code' => 1,
            'message' => 'Success',
            'data' => ['users' => $users],
        ]);
    }

    public function search(Request $request)
    {
        if ($response = $this->context($request)) {
            return $response;
        }
        if ($response = $this->authorizeReport($request)) {
            return $response;
        }

        $actor = $request->attributes->get('report_actor');
        $validator = Validator::make($request->all(), [
            'from_date' => 'required|date_format:Y-m-d',
            'to_date' => 'required|date_format:Y-m-d|after_or_equal:from_date',
            'selected_user_id' => [
                'nullable',
                'integer',
                Rule::exists('tbluser', 'id')->where(fn ($query) => $query->where('sub_institute_id', $actor->sub_institute_id)),
            ],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 0,
                'message' => $validator->messages()->first(),
                'errors' => $validator->errors(),
                'data' => [],
            ], 422);
        }

        $query = DB::table('access_log_route as log')
            ->join('tbluser as u', 'log.user_id', '=', 'u.id')
            ->select(
                'log.id',
                'log.url',
                'log.module',
                'log.action',
                'log.created_at',
                DB::raw("concat_ws(' ', u.first_name, u.middle_name, u.last_name) as user_name")
            )
            ->where('log.sub_institute_id', $actor->sub_institute_id)
            ->whereDate('log.created_at', '>=', $request->input('from_date'))
            ->whereDate('log.created_at', '<=', $request->input('to_date'));

        if ($request->filled('selected_user_id')) {
            $query->where('log.user_id', $request->integer('selected_user_id'));
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Success',
            'data' => ['logs' => $query->orderByDesc('log.created_at')->get()],
        ]);
    }
}
