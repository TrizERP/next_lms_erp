<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Read-only "fee dues for my class" aggregate for the Teacher self-service
 * page. Runs behind the `api.session` middleware (App\Http\Middleware\
 * ApiSessionHydrator) exactly like RoleDashboardApiController — identity
 * comes only from session(), never a client-supplied user_id, so a valid
 * token for one teacher can never be used to pull another teacher's or
 * another role's data.
 *
 * Class scoping copies RoleDashboardApiController::teacherSummary()'s
 * `class_teacher` query verbatim (standard/division pairs where this user is
 * the class teacher for the current syear).
 *
 * The fee-due amount per student reuses the same demand/paid model as the
 * legacy Fees module rather than inventing new SQL:
 *   - Demand ("total fee"): `fees_breackoff` is a per (grade_id, standard_id,
 *     quota, admission_year, syear, sub_institute_id) fee structure — NOT
 *     per-student — joined the same way as
 *     fees\fees_collect\fees_collect_controller.php::getBk() (~line 2091-2094)
 *     and FeesDashboardApiController::summary() (~line 57-63). `fees_breakoff_other`
 *     carries any additional per-student demand (~line 65-82 of the same file).
 *   - Paid: `fees_collect.amount` (+ `fees_discount`, treated as satisfied
 *     demand) and `fees_paid_other.actual_amountpaid` (+ `fees_discount`),
 *     scoped by `is_deleted = 'N'`, sub_institute_id and syear — the same
 *     receipt scope as FeesDashboardApiController::summary() (~line 89-101)
 *     and fees_collect_controller.php::getBk()'s paid sub-query (~line
 *     2119-2158).
 *   - Due = demand - paid, only returned when > 0 (students without a due
 *     amount are simply omitted — this page is for following up with
 *     defaulters, not a full fee statement).
 *
 * This endpoint is intentionally read-only: no write/collect action exists
 * here. Fee collection stays admin-only under fees\fees_collect.
 */
class TeacherFeeDuesApiController extends Controller
{
    private function unauthorized(string $message): JsonResponse
    {
        return response()->json(['status' => '0', 'message' => $message], 403);
    }

    public function summary(Request $request): JsonResponse
    {
        $subInstituteId = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $userId = session()->get('user_id');
        $profileName = strtolower(trim((string) session()->get('user_profile_name')));
        $isStudent = (bool) session()->get('is_student');

        if ($isStudent || ! in_array($profileName, ['teacher', 'lms teacher'], true)) {
            return $this->unauthorized('Fee dues are available to teachers only.');
        }

        // Class scoping — copied from RoleDashboardApiController::teacherSummary().
        $myClasses = DB::table('class_teacher as ct')
            ->join('standard as s', 's.id', '=', 'ct.standard_id')
            ->join('division as d', 'd.id', '=', 'ct.division_id')
            ->selectRaw('ct.standard_id, s.name as standard_name, ct.division_id, d.name as division_name')
            ->where('ct.teacher_id', $userId)
            ->where('ct.sub_institute_id', $subInstituteId)
            ->where('ct.syear', $syear)
            ->get();

        $standardIds = $myClasses->pluck('standard_id')->unique()->values()->all();
        $divisionIds = $myClasses->pluck('division_id')->unique()->values()->all();

        if (empty($standardIds) || empty($divisionIds)) {
            return response()->json([
                'status' => '1',
                'message' => 'Success',
                'summary' => ['total_due_students' => 0, 'total_due_amount' => 0],
                'students' => [],
            ]);
        }

        // Active, currently-enrolled students in this teacher's classes.
        $students = DB::table('tblstudent_enrollment as se')
            ->join('tblstudent as s', 's.id', '=', 'se.student_id')
            ->join('standard as st', 'st.id', '=', 'se.standard_id')
            ->join('division as d', 'd.id', '=', 'se.section_id')
            ->selectRaw("s.id as student_id, CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) as student_name, s.enrollment_no, se.standard_id, st.name as standard_name, se.section_id as division_id, d.name as division_name")
            ->where('se.sub_institute_id', $subInstituteId)
            ->where('se.syear', $syear)
            ->whereNull('se.end_date')
            ->where('s.status', 1)
            ->whereIn('se.standard_id', $standardIds)
            ->whereIn('se.section_id', $divisionIds)
            ->get();

        if ($students->isEmpty()) {
            return response()->json([
                'status' => '1',
                'message' => 'Success',
                'summary' => ['total_due_students' => 0, 'total_due_amount' => 0],
                'students' => [],
            ]);
        }

        $studentIds = $students->pluck('student_id')->unique()->values()->all();

        // Demand — the fee structure applicable to each student's
        // (admission_year, quota, grade_id, standard_id) cohort.
        $demandByStudent = DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as se', function ($join) use ($syear) {
                $join->on('se.student_id', '=', 's.id')->where('se.syear', $syear)->whereNull('se.end_date');
            })
            ->join('fees_breackoff as fb', function ($join) use ($syear, $subInstituteId) {
                $join->on('fb.admission_year', '=', 's.admission_year')
                    ->on('fb.quota', '=', 'se.student_quota')
                    ->on('fb.grade_id', '=', 'se.grade_id')
                    ->on('fb.standard_id', '=', 'se.standard_id')
                    ->where('fb.syear', $syear)
                    ->where('fb.sub_institute_id', $subInstituteId);
            })
            ->where('s.sub_institute_id', $subInstituteId)
            ->whereIn('s.id', $studentIds)
            ->groupBy('s.id')
            ->selectRaw('s.id as student_id, SUM(fb.amount) as amount')
            ->pluck('amount', 'student_id');

        $otherDemandByStudent = DB::table('fees_breakoff_other as fbo')
            ->where('fbo.sub_institute_id', $subInstituteId)
            ->where('fbo.syear', $syear)
            ->whereIn('fbo.student_id', $studentIds)
            ->groupBy('fbo.student_id')
            ->selectRaw('fbo.student_id, SUM(fbo.amount) as amount')
            ->pluck('amount', 'student_id');

        // Paid — same receipt scope as FeesDashboardApiController::summary().
        $paidByStudent = DB::table('fees_collect as fc')
            ->where('fc.sub_institute_id', $subInstituteId)
            ->where('fc.syear', $syear)
            ->where('fc.is_deleted', 'N')
            ->whereIn('fc.student_id', $studentIds)
            ->groupBy('fc.student_id')
            ->selectRaw('fc.student_id, SUM(fc.amount) + SUM(fc.fees_discount) as amount')
            ->pluck('amount', 'student_id');

        $otherPaidByStudent = DB::table('fees_paid_other as fpo')
            ->where('fpo.sub_institute_id', $subInstituteId)
            ->where('fpo.syear', $syear)
            ->where('fpo.is_deleted', 'N')
            ->whereIn('fpo.student_id', $studentIds)
            ->groupBy('fpo.student_id')
            ->selectRaw('fpo.student_id, SUM(fpo.actual_amountpaid) + SUM(fpo.fees_discount) as amount')
            ->pluck('amount', 'student_id');

        $result = [];
        $totalDueAmount = 0.0;

        foreach ($students as $student) {
            $totalFee = (float) ($demandByStudent[$student->student_id] ?? 0) + (float) ($otherDemandByStudent[$student->student_id] ?? 0);
            $paidAmount = (float) ($paidByStudent[$student->student_id] ?? 0) + (float) ($otherPaidByStudent[$student->student_id] ?? 0);
            $dueAmount = round($totalFee - $paidAmount, 2);

            if ($dueAmount <= 0) {
                continue;
            }

            $totalDueAmount += $dueAmount;

            $result[] = [
                'student_id' => $student->student_id,
                'student_name' => trim(preg_replace('/\s+/', ' ', (string) $student->student_name)),
                'enrollment_no' => $student->enrollment_no,
                'standard_name' => $student->standard_name,
                'division_name' => $student->division_name,
                'total_fee' => round($totalFee, 2),
                'paid_amount' => round($paidAmount, 2),
                'due_amount' => $dueAmount,
            ];
        }

        usort($result, fn ($a, $b) => $b['due_amount'] <=> $a['due_amount']);

        return response()->json([
            'status' => '1',
            'message' => 'Success',
            'summary' => [
                'total_due_students' => count($result),
                'total_due_amount' => round($totalDueAmount, 2),
            ],
            'students' => $result,
        ]);
    }
}
