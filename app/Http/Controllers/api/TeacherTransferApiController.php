<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TeacherTransferApiController extends Controller
{
    use GetsJwtToken;

    private function authorizeRequest(Request $request, string $action)
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
            'syear' => 'required|integer',
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

        if (strtolower((string) $actor->profile_name) === 'super admin') {
            return null;
        }

        $menuId = DB::table('tblmenumaster')
            ->where('status', 1)
            ->where('link', 'teachertransfer.index')
            ->value('id');
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

        $allowed = $action === 'view'
            ? (bool) ($rights->can_view ?? false)
            : (bool) ($rights->can_add ?? false);
        if (! $allowed) {
            return response()->json([
                'status_code' => 0,
                'message' => 'You do not have permission to '.$action.' teacher transfers.',
                'data' => [],
            ], 403);
        }

        return null;
    }

    private function teachers(int $tenantId, int $syear)
    {
        return DB::table('tbluser as u')
            ->join('tbluserprofilemaster as p', 'p.id', '=', 'u.user_profile_id')
            ->leftJoin('timetable as t', function ($join) use ($tenantId, $syear) {
                $join->on('t.teacher_id', '=', 'u.id')
                    ->where('t.sub_institute_id', $tenantId)
                    ->where('t.syear', $syear);
            })
            ->select(
                'u.id',
                DB::raw('trim(concat_ws(" ", u.first_name, u.middle_name, u.last_name)) as name'),
                DB::raw('count(t.id) as timetable_count')
            )
            ->where('u.sub_institute_id', $tenantId)
            ->where('u.status', 1)
            ->whereRaw('lower(p.name) = ?', ['teacher'])
            ->groupBy('u.id', 'u.first_name', 'u.middle_name', 'u.last_name')
            ->orderBy('u.first_name')
            ->orderBy('u.last_name')
            ->get();
    }

    public function index(Request $request)
    {
        if ($response = $this->authorizeRequest($request, 'view')) {
            return $response;
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Success',
            'data' => [
                'teachers' => $this->teachers(
                    $request->integer('sub_institute_id'),
                    $request->integer('syear')
                ),
            ],
        ]);
    }

    public function store(Request $request)
    {
        if ($response = $this->authorizeRequest($request, 'create')) {
            return $response;
        }

        $tenantId = $request->integer('sub_institute_id');
        $validator = Validator::make($request->all(), [
            'left_teacher_id' => [
                'required',
                'integer',
                'different:new_teacher_id',
                Rule::exists('tbluser', 'id')->where(function ($query) use ($tenantId) {
                    $query->where('sub_institute_id', $tenantId)->where('status', 1);
                }),
            ],
            'new_teacher_id' => [
                'required',
                'integer',
                'different:left_teacher_id',
                Rule::exists('tbluser', 'id')->where(function ($query) use ($tenantId) {
                    $query->where('sub_institute_id', $tenantId)->where('status', 1);
                }),
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

        $teacherIds = [$request->integer('left_teacher_id'), $request->integer('new_teacher_id')];
        $validTeacherCount = DB::table('tbluser as u')
            ->join('tbluserprofilemaster as p', 'p.id', '=', 'u.user_profile_id')
            ->where('u.sub_institute_id', $tenantId)
            ->where('u.status', 1)
            ->whereRaw('lower(p.name) = ?', ['teacher'])
            ->whereIn('u.id', $teacherIds)
            ->distinct()
            ->count('u.id');
        if ($validTeacherCount !== 2) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Both selected users must be active teachers in this institute.',
                'data' => [],
            ], 422);
        }

        $affected = DB::transaction(function () use ($request, $tenantId) {
            return DB::table('timetable')
                ->where('teacher_id', $request->integer('left_teacher_id'))
                ->where('sub_institute_id', $tenantId)
                ->where('syear', $request->integer('syear'))
                ->update(['teacher_id' => $request->integer('new_teacher_id')]);
        });

        if ($affected === 0) {
            return response()->json([
                'status_code' => 0,
                'message' => 'The selected teacher has no timetable entries in the current academic year.',
                'data' => ['affected_rows' => 0],
            ], 409);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Teacher transferred successfully.',
            'data' => ['affected_rows' => $affected],
        ]);
    }
}
