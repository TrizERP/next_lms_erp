<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Stateless mobile HRMS API. It does not alter the legacy HRMS controllers. */
class HrmsMobileApiController extends Controller
{
    private function context(Request $request): array
    {
        return [(int) $request->session()->get('user_id'), (int) $request->session()->get('sub_institute_id')];
    }

    public function today(Request $request): JsonResponse
    {
        [$userId, $instituteId] = $this->context($request);
        $attendance = DB::table('hrms_attendances')->where('user_id', $userId)->where('sub_institute_id', $instituteId)->whereDate('day', Carbon::today())->first();
        return response()->json(['success' => true, 'data' => $this->attendanceData($attendance)]);
    }

    public function punch(Request $request): JsonResponse
    {
        $request->validate(['action' => 'required|in:in,out', 'address' => 'nullable|string|max:1000']);
        [$userId, $instituteId] = $this->context($request);
        $now = Carbon::now();
        $attendance = DB::table('hrms_attendances')->where('user_id', $userId)->where('sub_institute_id', $instituteId)->whereDate('day', $now->toDateString())->first();

        if ($request->input('action') === 'in') {
            if ($attendance && $attendance->punchin_time) return response()->json(['success' => false, 'message' => 'You have already checked in today.'], 422);
            if ($attendance) DB::table('hrms_attendances')->where('id', $attendance->id)->update(['punchin_time' => $now, 'ipaddress_in' => $request->input('address'), 'updated_at' => $now]);
            else DB::table('hrms_attendances')->insert(['user_id' => $userId, 'sub_institute_id' => $instituteId, 'client_id' => $request->session()->get('client_id') ?: 0, 'day' => $now->toDateString(), 'punchin_time' => $now, 'ipaddress_in' => $request->input('address'), 'created_at' => $now, 'updated_at' => $now]);
        } else {
            if (! $attendance || ! $attendance->punchin_time) return response()->json(['success' => false, 'message' => 'Check in before checking out.'], 422);
            if ($attendance->punchout_time) return response()->json(['success' => false, 'message' => 'You have already checked out today.'], 422);
            DB::table('hrms_attendances')->where('id', $attendance->id)->update(['punchout_time' => $now, 'ipaddress_out' => $request->input('address'), 'updated_at' => $now]);
        }
        return $this->today($request);
    }

    public function attendance(Request $request): JsonResponse
    {
        [$userId, $instituteId] = $this->context($request);
        $month = $request->input('month', Carbon::now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) return response()->json(['success' => false, 'message' => 'month must use YYYY-MM.'], 422);
        $rows = DB::table('hrms_attendances')->where('user_id', $userId)->where('sub_institute_id', $instituteId)->where('day', 'like', $month.'%')->orderByDesc('day')->get()->map(fn ($item) => $this->attendanceData($item));
        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function leaves(Request $request): JsonResponse
    {
        [$userId, $instituteId] = $this->context($request);
        $user = DB::table('tbluser')->select('department_id')->where('id', $userId)->where('sub_institute_id', $instituteId)->first();
        if (! $user) return response()->json(['success' => false, 'message' => 'HRMS is available to staff users only.'], 404);
        $year = Carbon::now()->year;
        $types = DB::table('hrms_leave_types as t')->leftJoin('hrms_leave_allocation as a', function ($join) use ($userId, $instituteId, $user, $year) {
            $join->on('a.leave_type_id', '=', 't.id')->where('a.sub_institute_id', $instituteId)->where('a.year', $year)->where('a.department_id', $user->department_id)->where(function ($q) use ($userId) { $q->where('a.employee_id', $userId)->orWhereNull('a.employee_id'); });
        })->where('t.sub_institute_id', $instituteId)->selectRaw('t.id, t.leave_type, COALESCE(MAX(a.value), 0) as total')->groupBy('t.id', 't.leave_type')->orderBy('t.sort_order')->get()->map(function ($type) use ($userId, $instituteId, $year) {
            $used = DB::table('hrms_emp_leaves')->where('user_id', $userId)->where('sub_institute_id', $instituteId)->where('leave_type_id', $type->id)->whereYear('from_date', $year)->whereNotIn('status', ['rejected', 'cancelled'])->sum('day_type');
            return ['leave_type' => $type->leave_type, 'total' => (float) $type->total, 'used' => (float) $used];
        });
        $total = $types->sum('total'); $used = $types->sum('used');
        return response()->json(['success' => true, 'data' => ['summary' => ['total' => $total, 'used' => $used, 'remaining' => max(0, $total - $used)], 'leave_types' => $types]]);
    }

    private function attendanceData($item): ?array
    {
        if (! $item) return null;
        return ['id' => $item->id, 'day' => $item->day, 'punchin_time' => $item->punchin_time, 'punchout_time' => $item->punchout_time, 'timestamp_diff' => $item->punchin_time && $item->punchout_time ? Carbon::parse($item->punchin_time)->diff(Carbon::parse($item->punchout_time))->format('%H:%I') : null];
    }
}
