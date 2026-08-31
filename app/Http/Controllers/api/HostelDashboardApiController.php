<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Stateless aggregate for the Next.js Hostel dashboard.
 *
 * Modeled on FeesDashboardApiController: accepts tenant/year in the request
 * body (no browser session required).
 *
 * Table/column usage confirmed from
 * App\Http\Controllers\hostel_management\hostel_reportController — only
 * hostel_room_allocation carries both sub_institute_id and syear; hostel_master,
 * hostel_room_master etc. are tenant-scoped only (no academic year), so
 * "occupancy" is computed as allocations-for-this-year against rooms-total.
 * `user_id`/`user_group_id` on hostel_room_allocation can point at either a
 * student or a staff member (per user_group_id → tbluserprofilemaster), so
 * this deliberately does not resolve occupant names — see
 * hostel_reportController::studentsForAllocation/staffForAllocation for how
 * that profile-aware join actually works if that is added later.
 */
class HostelDashboardApiController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sub_institute_id' => ['required'],
            'syear' => ['required'],
        ]);

        $subInstituteId = (string) $validated['sub_institute_id'];
        $syear = (string) $validated['syear'];

        $totalHostels = (int) DB::table('hostel_master')
            ->where('sub_institute_id', $subInstituteId)
            ->count();

        $totalRooms = (int) DB::table('hostel_room_master')
            ->where('sub_institute_id', $subInstituteId)
            ->count();

        $allocations = DB::table('hostel_room_allocation')
            ->where('sub_institute_id', $subInstituteId)
            ->where('syear', $syear);

        $totalAllocations = (int) (clone $allocations)->count();

        $allocationsByHostel = DB::table('hostel_room_allocation as hra')
            ->join('hostel_room_master as hrm', function ($join) {
                $join->on('hrm.id', '=', 'hra.room_id')->whereColumn('hrm.sub_institute_id', '=', 'hra.sub_institute_id');
            })
            ->join('hostel_floor_master as hfm', function ($join) {
                $join->on('hfm.id', '=', 'hrm.floor_id')->whereColumn('hfm.sub_institute_id', '=', 'hrm.sub_institute_id');
            })
            ->join('hostel_building_master as hbm', function ($join) {
                $join->on('hbm.id', '=', 'hfm.building_id')->whereColumn('hbm.sub_institute_id', '=', 'hfm.sub_institute_id');
            })
            ->join('hostel_master as hm', function ($join) {
                $join->on('hm.id', '=', 'hbm.hostel_id')->whereColumn('hm.sub_institute_id', '=', 'hbm.sub_institute_id');
            })
            ->where('hra.sub_institute_id', $subInstituteId)
            ->where('hra.syear', $syear)
            ->selectRaw('hm.id as hostel_id, hm.name as hostel_name, COUNT(*) as total')
            ->groupBy('hm.id', 'hm.name')
            ->get();

        $allocationsByCategory = DB::table('hostel_room_allocation as hra')
            ->leftJoin('admission_category_master as acm', function ($join) {
                $join->on('acm.id', '=', 'hra.admission_category_id')->whereColumn('acm.sub_institute_id', '=', 'hra.sub_institute_id');
            })
            ->where('hra.sub_institute_id', $subInstituteId)
            ->where('hra.syear', $syear)
            ->selectRaw("COALESCE(NULLIF(TRIM(acm.title), ''), 'Unspecified') as category, COUNT(*) as total")
            ->groupBy('acm.title')
            ->get();

        return response()->json([
            'status' => '1',
            'message' => 'Success',
            'context' => [
                'sub_institute_id' => (int) $subInstituteId,
                'syear' => (int) $syear,
                'generated_at' => now()->toIso8601String(),
            ],
            'summary' => [
                'total_hostels' => $totalHostels,
                'total_rooms' => $totalRooms,
                'total_allocations' => $totalAllocations,
                'occupancy_rate' => $totalRooms > 0 ? round(($totalAllocations / $totalRooms) * 100, 1) : 0,
            ],
            'allocations_by_hostel' => $allocationsByHostel,
            'allocations_by_category' => $allocationsByCategory,
        ]);
    }
}
