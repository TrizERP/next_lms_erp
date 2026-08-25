<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Stateless aggregates for the three role-based Next.js dashboards
 * (Admin / Teacher / Student). Runs behind the `api.session` middleware
 * (App\Http\Middleware\ApiSessionHydrator), which validates the JWT and
 * hydrates session() with user_id, sub_institute_id, syear, is_admin,
 * is_student, user_profile_name and (for teachers) classTeacherStdArr /
 * classTeacherDivArr — the same values the legacy web dashboardController
 * reads. Every method trusts ONLY session() for identity — never a
 * client-supplied user_id — so a valid token for one role can never be used
 * to pull another role's or another person's data.
 */
class RoleDashboardApiController extends Controller
{
    private function unauthorized(string $message): JsonResponse
    {
        return response()->json(['status' => '0', 'message' => $message], 403);
    }

    private function isAdminProfile(?string $profileName, $isAdmin, $profileParentId): bool
    {
        if ((int) $isAdmin === 1 || (int) $isAdmin === 2) {
            return true;
        }

        if ((int) $profileParentId === 1) {
            return true;
        }

        $normalized = strtolower(trim((string) $profileName));

        return in_array($normalized, ['super admin', 'admin', 'school admin'], true);
    }

    public function adminSummary(Request $request): JsonResponse
    {
        $subInstituteId = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $isAdmin = session()->get('is_admin');
        $profileName = session()->get('user_profile_name');
        $profileParentId = session()->get('profile_parent_id');

        if (! $this->isAdminProfile($profileName, $isAdmin, $profileParentId)) {
            return $this->unauthorized('This dashboard is available to administrators only.');
        }

        $today = Carbon::today()->toDateString();

        $totalStudents = (int) DB::table('tblstudent_enrollment as se')
            ->join('tblstudent as ts', 'ts.id', '=', 'se.student_id')
            ->where('se.sub_institute_id', $subInstituteId)
            ->where('se.syear', $syear)
            ->whereNull('se.end_date')
            ->count();

        $totalStaff = (int) DB::table('tbluser')
            ->where('sub_institute_id', $subInstituteId)
            ->where('status', '1')
            ->count();

        $totalClasses = (int) DB::table('standard')
            ->where('sub_institute_id', $subInstituteId)
            ->count();

        $feesToday = (float) DB::table('fees_collect')
            ->where(['sub_institute_id' => $subInstituteId, 'syear' => $syear, 'is_deleted' => 'N'])
            ->whereDate('receiptdate', $today)
            ->sum('amount');

        $otherFeesToday = (float) DB::table('fees_paid_other')
            ->where(['sub_institute_id' => $subInstituteId, 'syear' => $syear, 'is_deleted' => 'N'])
            ->whereDate('receiptdate', $today)
            ->sum('actual_amountpaid');

        $admissionsThisYear = (int) DB::table('admission_enquiry')
            ->where('sub_institute_id', $subInstituteId)
            ->where('syear', $syear)
            ->count();

        $homeworkToday = (int) DB::table('homework')
            ->where('sub_institute_id', $subInstituteId)
            ->whereDate('date', $today)
            ->count();

        $circularsToday = (int) DB::table('circular')
            ->where('sub_institute_id', $subInstituteId)
            ->whereDate('date_', $today)
            ->count();

        $pendingCommunications = (int) DB::table('parent_communication')
            ->where('sub_institute_id', $subInstituteId)
            ->where(function ($q) {
                $q->whereNull('reply')->orWhereRaw("TRIM(reply) = ''");
            })
            ->count();

        $upcomingBirthdays = DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as ts', function ($join) use ($syear) {
                $join->on('s.id', '=', 'ts.student_id')->where('ts.syear', $syear);
            })
            ->join('standard as st', 'ts.standard_id', '=', 'st.id')
            ->join('division as d', 'ts.section_id', '=', 'd.id')
            ->selectRaw("CONCAT_WS(' ', s.first_name, s.last_name) as student_name, st.name as standard_name, d.name as division_name, DATE_FORMAT(s.dob, '%d-%m-%Y') as dob")
            ->where('s.sub_institute_id', $subInstituteId)
            ->whereNull('ts.end_date')
            ->whereRaw("DATE_FORMAT(s.dob, '%m-%d') >= DATE_FORMAT(NOW(), '%m-%d') and DATE_FORMAT(s.dob, '%m-%d') <= DATE_FORMAT((NOW() + INTERVAL 7 DAY), '%m-%d')")
            ->limit(5)
            ->get();

        $recentFeeReceipts = DB::table('fees_collect as fc')
            ->join('tblstudent as ts', 'ts.id', '=', 'fc.student_id')
            ->selectRaw("fc.receipt_no, fc.amount, fc.receiptdate, CONCAT_WS(' ', ts.first_name, ts.last_name) as student_name")
            ->where(['fc.sub_institute_id' => $subInstituteId, 'fc.syear' => $syear, 'fc.is_deleted' => 'N'])
            ->orderByDesc('fc.receiptdate')
            ->limit(5)
            ->get();

        // Fee collection trend — last 7 days, for the collection chart.
        $startDate = Carbon::today()->subDays(6)->toDateString();
        $regularByDay = DB::table('fees_collect')
            ->selectRaw("DATE(receiptdate) as day, SUM(amount) as amount")
            ->where(['sub_institute_id' => $subInstituteId, 'syear' => $syear, 'is_deleted' => 'N'])
            ->whereDate('receiptdate', '>=', $startDate)
            ->groupBy('day')
            ->pluck('amount', 'day');
        $otherByDay = DB::table('fees_paid_other')
            ->selectRaw("DATE(receiptdate) as day, SUM(actual_amountpaid) as amount")
            ->where(['sub_institute_id' => $subInstituteId, 'syear' => $syear, 'is_deleted' => 'N'])
            ->whereDate('receiptdate', '>=', $startDate)
            ->groupBy('day')
            ->pluck('amount', 'day');

        $feeCollectionTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i)->toDateString();
            $feeCollectionTrend[] = [
                'date' => $day,
                'label' => Carbon::parse($day)->format('D'),
                'amount' => round((float) ($regularByDay[$day] ?? 0) + (float) ($otherByDay[$day] ?? 0), 2),
            ];
        }

        // Students per class, for the enrollment distribution chart.
        $studentsByClass = DB::table('tblstudent_enrollment as se')
            ->join('standard as s', 's.id', '=', 'se.standard_id')
            ->selectRaw('s.id as standard_id, s.name as standard_name, COUNT(se.student_id) as students')
            ->where('se.sub_institute_id', $subInstituteId)
            ->where('se.syear', $syear)
            ->whereNull('se.end_date')
            ->groupBy('s.id', 's.name', 's.sort_order')
            ->orderBy('s.sort_order')
            ->get();

        return response()->json([
            'status' => '1',
            'message' => 'Success',
            'summary' => [
                'total_students' => $totalStudents,
                'total_staff' => $totalStaff,
                'total_classes' => $totalClasses,
                'fees_collected_today' => round($feesToday + $otherFeesToday, 2),
                'admissions_this_year' => $admissionsThisYear,
                'homework_today' => $homeworkToday,
                'circulars_today' => $circularsToday,
                'pending_parent_communications' => $pendingCommunications,
            ],
            'upcoming_birthdays' => $upcomingBirthdays,
            'recent_fee_receipts' => $recentFeeReceipts,
            'fee_collection_trend' => $feeCollectionTrend,
            'students_by_class' => $studentsByClass,
        ]);
    }

    public function teacherSummary(Request $request): JsonResponse
    {
        $subInstituteId = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $userId = session()->get('user_id');
        $profileName = strtolower(trim((string) session()->get('user_profile_name')));
        $isStudent = (bool) session()->get('is_student');

        if ($isStudent || ! in_array($profileName, ['teacher', 'lms teacher'], true)) {
            return $this->unauthorized('This dashboard is available to teachers only.');
        }

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

        $myStudentsCount = empty($standardIds) ? 0 : (int) DB::table('tblstudent_enrollment')
            ->where('sub_institute_id', $subInstituteId)
            ->where('syear', $syear)
            ->whereNull('end_date')
            ->whereIn('standard_id', $standardIds)
            ->whereIn('section_id', $divisionIds)
            ->count();

        $mySubjects = DB::table('subject')
            ->select('id', 'subject_code', 'subject_name', 'short_name', 'subject_type')
            ->where('sub_institute_id', $subInstituteId)
            ->where('status', '1')
            ->get();

        $homeworkToReview = empty($standardIds) ? 0 : (int) DB::table('homework')
            ->where('sub_institute_id', $subInstituteId)
            ->whereIn('standard_id', $standardIds)
            ->whereIn('division_id', $divisionIds)
            ->where('completion_status', 'N')
            ->count();

        $assignmentsToGrade = DB::table('lms_assignment')
            ->select('id', 'title', 'standard_id', 'division_id', 'subject_id', 'student_id', 'student_submitted_date')
            ->where('teacher_id', $userId)
            ->where('sub_institute_id', $subInstituteId)
            ->where('syear', $syear)
            ->where('student_submission_status', 'Y')
            ->where('teacher_submission_status', 'N')
            ->orderByDesc('student_submitted_date')
            ->limit(10)
            ->get();

        $recentCirculars = empty($standardIds) ? collect() : DB::table('circular')
            ->select('id', 'title', 'date_', 'standard_id', 'division_id')
            ->where('sub_institute_id', $subInstituteId)
            ->where('syear', $syear)
            ->whereIn('standard_id', $standardIds)
            ->orderByDesc('date_')
            ->limit(5)
            ->get();

        // Per-class enrollment, for the "students per class" chart — counted
        // per exact (standard_id, division_id) pair, not the flattened
        // whereIn() used for myStudentsCount above.
        $studentsByClass = $myClasses->map(function ($class) use ($subInstituteId, $syear) {
            $count = (int) DB::table('tblstudent_enrollment')
                ->where('sub_institute_id', $subInstituteId)
                ->where('syear', $syear)
                ->whereNull('end_date')
                ->where('standard_id', $class->standard_id)
                ->where('section_id', $class->division_id)
                ->count();

            return [
                'label' => "{$class->standard_name} - {$class->division_name}",
                'students' => $count,
            ];
        })->values();

        return response()->json([
            'status' => '1',
            'message' => 'Success',
            'summary' => [
                'total_classes' => $myClasses->count(),
                'total_students' => $myStudentsCount,
                'total_subjects' => $mySubjects->count(),
                'homework_to_review' => $homeworkToReview,
                'assignments_to_grade' => $assignmentsToGrade->count(),
            ],
            'my_classes' => $myClasses,
            'my_subjects' => $mySubjects,
            'assignments_to_grade' => $assignmentsToGrade,
            'recent_circulars' => $recentCirculars,
            'students_by_class' => $studentsByClass,
        ]);
    }

    public function studentSummary(Request $request): JsonResponse
    {
        $subInstituteId = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $studentId = session()->get('user_id');
        $isStudent = (bool) session()->get('is_student');

        if (! $isStudent) {
            return $this->unauthorized('This dashboard is available to students only.');
        }

        $enrollment = DB::table('tblstudent_enrollment as se')
            ->join('standard as s', 's.id', '=', 'se.standard_id')
            ->join('division as d', 'd.id', '=', 'se.section_id')
            ->selectRaw('se.syear, se.standard_id, s.name as standard_name, se.section_id, d.name as section_name')
            ->where('se.student_id', $studentId)
            ->where('se.sub_institute_id', $subInstituteId)
            ->where('se.syear', $syear)
            ->whereNull('se.end_date')
            ->first();

        $subjectIds = DB::table('student_optional_subject')
            ->where('student_id', $studentId)
            ->where('sub_institute_id', $subInstituteId)
            ->pluck('subject_id')
            ->toArray();

        $mySubjects = empty($subjectIds) ? collect() : DB::table('subject')
            ->select('id', 'subject_code', 'subject_name', 'short_name', 'subject_type')
            ->whereIn('id', $subjectIds)
            ->where('sub_institute_id', $subInstituteId)
            ->where('status', '1')
            ->get();

        $pendingHomework = DB::table('homework')
            ->select('id', 'title', 'subject_id', 'date', 'submission_date')
            ->where('student_id', $studentId)
            ->where('sub_institute_id', $subInstituteId)
            ->where('completion_status', 'N')
            ->orderByDesc('date')
            ->limit(10)
            ->get();

        $pendingAssignments = DB::table('lms_assignment')
            ->select('id', 'title', 'subject_id', 'submission_date', 'exam_id')
            ->where('student_id', $studentId)
            ->where('sub_institute_id', $subInstituteId)
            ->where('syear', $syear)
            ->where('student_submission_status', 'N')
            ->orderByDesc('submission_date')
            ->limit(10)
            ->get();

        $recentCirculars = $enrollment ? DB::table('circular')
            ->select('id', 'title', 'date_')
            ->where('sub_institute_id', $subInstituteId)
            ->where('syear', $syear)
            ->where('standard_id', $enrollment->standard_id)
            ->where(function ($q) use ($enrollment) {
                $q->where('division_id', $enrollment->section_id)->orWhereNull('division_id');
            })
            ->orderByDesc('date_')
            ->limit(5)
            ->get() : collect();

        $homeworkCompleted = (int) DB::table('homework')
            ->where('student_id', $studentId)
            ->where('sub_institute_id', $subInstituteId)
            ->where('completion_status', 'Y')
            ->count();

        $assignmentsSubmitted = (int) DB::table('lms_assignment')
            ->where('student_id', $studentId)
            ->where('sub_institute_id', $subInstituteId)
            ->where('syear', $syear)
            ->where('student_submission_status', 'Y')
            ->count();

        $taskStatus = [
            ['label' => 'Homework done', 'value' => $homeworkCompleted],
            ['label' => 'Homework pending', 'value' => $pendingHomework->count()],
            ['label' => 'Assignments submitted', 'value' => $assignmentsSubmitted],
            ['label' => 'Assignments pending', 'value' => $pendingAssignments->count()],
        ];

        return response()->json([
            'status' => '1',
            'message' => 'Success',
            'summary' => [
                'total_subjects' => $mySubjects->count(),
                'pending_homework' => $pendingHomework->count(),
                'pending_assignments' => $pendingAssignments->count(),
            ],
            'enrollment' => $enrollment,
            'my_subjects' => $mySubjects,
            'pending_homework' => $pendingHomework,
            'pending_assignments' => $pendingAssignments,
            'recent_circulars' => $recentCirculars,
            'task_status' => $taskStatus,
            // No gamification/badges table is wired to a live route yet
            // (App\Services\PAL\Gamification exists but is unrouted) —
            // left empty rather than mocked until that's confirmed live.
            'achievements' => [],
        ]);
    }
}
