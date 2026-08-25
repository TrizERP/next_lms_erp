<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Stateless aggregate for the Next.js Transportation dashboard.
 *
 * Modeled on FeesDashboardApiController: accepts tenant/year in the request
 * body (no browser session required). Query patterns (vehicle/shift/mapping
 * joins, "only currently enrolled students count as reserved seats") are
 * copied from the proven App\Http\Controllers\api\TransportationApiController
 * (vanSummary/reservedSeats), not guessed.
 */
class TransportationDashboardApiController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sub_institute_id' => ['required'],
            'syear' => ['required'],
        ]);

        $subInstituteId = (string) $validated['sub_institute_id'];
        $syear = (string) $validated['syear'];

        $totalRoutes = (int) DB::table('transport_route')
            ->where('sub_institute_id', $subInstituteId)
            ->where('syear', $syear)
            ->count();

        $vehicles = DB::table('transport_vehicle')
            ->where('sub_institute_id', $subInstituteId);

        $totalVehicles = (int) (clone $vehicles)->count();
        $totalCapacity = (int) (clone $vehicles)->sum('sitting_capacity');

        // Only students still enrolled this year hold a seat — same rule as
        // TransportationApiController::reservedSeats.
        $totalMapped = (int) DB::table('transport_map_student as mapping')
            ->join('tblstudent_enrollment as enrollment', function ($join) use ($syear) {
                $join->on('enrollment.student_id', '=', 'mapping.student_id')
                    ->where('enrollment.syear', $syear)->whereNull('enrollment.end_date');
            })
            ->where('mapping.sub_institute_id', $subInstituteId)
            ->where('mapping.syear', $syear)
            ->distinct('mapping.student_id')
            ->count('mapping.student_id');

        $vanSummary = DB::table('transport_vehicle as vehicle')
            ->join('transport_school_shift as shift', 'shift.id', '=', 'vehicle.school_shift')
            ->join('transport_map_student as mapping', function ($join) {
                $join->on('mapping.from_shift_id', '=', 'shift.id')->on('mapping.from_bus_id', '=', 'vehicle.id');
            })
            ->join('tblstudent_enrollment as enrollment', function ($join) use ($syear) {
                $join->on('enrollment.student_id', '=', 'mapping.student_id')->where('enrollment.syear', $syear)->whereNull('enrollment.end_date');
            })
            ->select('vehicle.id as vehicle_id', 'shift.id as shift_id', 'vehicle.title as vehicle_name', 'shift.shift_title', DB::raw('count(distinct mapping.student_id) as student_count'))
            ->where('vehicle.sub_institute_id', $subInstituteId)->where('mapping.syear', $syear)
            ->groupBy('vehicle.id', 'shift.id', 'vehicle.title', 'shift.shift_title')->orderBy('vehicle.title')->get();

        return response()->json([
            'status' => '1',
            'message' => 'Success',
            'context' => [
                'sub_institute_id' => (int) $subInstituteId,
                'syear' => (int) $syear,
                'generated_at' => now()->toIso8601String(),
            ],
            'summary' => [
                'total_routes' => $totalRoutes,
                'total_vehicles' => $totalVehicles,
                'total_students_mapped' => $totalMapped,
                'capacity_utilization' => $totalCapacity > 0 ? round(($totalMapped / $totalCapacity) * 100, 1) : 0,
            ],
            'van_summary' => $vanSummary,
        ]);
    }
}
