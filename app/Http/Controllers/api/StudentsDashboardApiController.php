<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Stateless aggregate for the Next.js Students (admin) dashboard.
 *
 * Modeled on FeesDashboardApiController: accepts tenant/year in the request
 * body (no browser session required). Active-enrollment scoping
 * (tblstudent_enrollment joined to tblstudent, whereNull end_date) mirrors
 * the proven query already used by RoleDashboardApiController::adminSummary
 * and FeesDashboardApiController's "students considered" logic.
 */
class StudentsDashboardApiController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sub_institute_id' => ['required'],
            'syear' => ['required'],
        ]);

        $subInstituteId = (string) $validated['sub_institute_id'];
        $syear = (string) $validated['syear'];

        $activeEnrollment = DB::table('tblstudent_enrollment as se')
            ->join('tblstudent as ts', 'ts.id', '=', 'se.student_id')
            ->where('se.sub_institute_id', $subInstituteId)
            ->where('se.syear', $syear)
            ->whereNull('se.end_date');

        $totalStudents = (int) (clone $activeEnrollment)->count();

        $inactiveThisYear = (int) DB::table('tblstudent_enrollment')
            ->where('sub_institute_id', $subInstituteId)
            ->where('syear', $syear)
            ->whereNotNull('end_date')
            ->count();

        $genderBreakdown = (clone $activeEnrollment)
            ->selectRaw("COALESCE(NULLIF(TRIM(ts.gender), ''), 'Unspecified') as gender, COUNT(*) as total")
            ->groupBy('ts.gender')
            ->get();

        $dropReasons = DB::table('tblstudent_enrollment')
            ->where('sub_institute_id', $subInstituteId)
            ->where('syear', $syear)
            ->whereNotNull('end_date')
            ->selectRaw("COALESCE(NULLIF(TRIM(drop_remarks), ''), 'Unspecified') as reason, COUNT(*) as total")
            ->groupBy('drop_remarks')
            ->get();

        // Students per class — same shape as RoleDashboardApiController's
        // admin-dashboard chart, so the two stay visually consistent.
        $studentsByClass = DB::table('tblstudent_enrollment as se')
            ->join('standard as s', 's.id', '=', 'se.standard_id')
            ->selectRaw('s.id as standard_id, s.name as standard_name, COUNT(se.student_id) as students')
            ->where('se.sub_institute_id', $subInstituteId)
            ->where('se.syear', $syear)
            ->whereNull('se.end_date')
            ->groupBy('s.id', 's.name', 's.sort_order')
            ->orderBy('s.sort_order')
            ->get();

        $recentEnrollments = (clone $activeEnrollment)
            ->join('standard as s', 's.id', '=', 'se.standard_id')
            ->leftJoin('division as d', 'd.id', '=', 'se.section_id')
            ->selectRaw("se.student_id, CONCAT_WS(' ', ts.first_name, ts.last_name) as student_name, s.name as standard_name, d.name as division_name, se.created_on")
            ->orderByDesc('se.created_on')
            ->limit(8)
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
                'total_students' => $totalStudents,
                'inactive_this_year' => $inactiveThisYear,
                'total_classes' => $studentsByClass->count(),
            ],
            'gender_breakdown' => $genderBreakdown,
            'drop_reasons' => $dropReasons,
            'students_by_class' => $studentsByClass,
            'recent_enrollments' => $recentEnrollments,
        ]);
    }
}
