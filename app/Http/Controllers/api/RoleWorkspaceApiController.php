<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * One endpoint behind the role dashboards that are not Admin / Teacher / Student
 * (Principal, Clerk, Librarian, Store Keeper, Exam Department, Trustee, Peon ...).
 *
 * Which role the caller is — and therefore which figures they get — is decided ONLY from the
 * JWT-hydrated session (`user_profile_name`) and config/role_dashboards.php, never from the
 * request. A Peon cannot ask for the Principal's figures: the widget list is fixed per role
 * here, on the server.
 *
 * A widget that cannot be computed (table missing, query error) is returned as null so the page
 * shows "no data" for it; nothing is ever made up.
 */
class RoleWorkspaceApiController extends Controller
{
    /**
     * Widgets each role receives. Anything not listed here is never computed for that role.
     * The `fees` panel (collected vs outstanding, payment modes) is a separate lazy endpoint gated by FEES_ROLES.
     * A per-student defaulter list is deliberately NOT here: the Fees defaulter report takes ~20 minutes on a school this size.
     */
    private const WIDGETS = [
        'admin' => ['payroll', 'signals', 'intelligence_loop', 'progress', 'history', 'chronic_absentees', 'substitutes_needed', 'parent_msg_age', 'task_aging', 'homework_rate', 'strength_changes', 'transport_summary', 'hostel_summary', 'students_total', 'staff_total', 'attendance_today', 'attendance_trend', 'class_attendance', 'absent_today', 'staff_on_leave_today', 'staff_on_leave_list', 'leaves_pending', 'fees_today', 'fees_period', 'fees_month', 'fee_trend', 'recent_fee_receipts', 'admissions_year', 'admissions_period', 'admission_funnel', 'followups_due', 'recent_enquiries', 'visitors_today', 'visitors_period', 'complaints', 'tasks_pending_all', 'parent_msgs_pending', 'pending_approvals', 'recent_circulars', 'upcoming_events', 'birthdays', 'upcoming_exams', 'exam_results', 'students_by_class', 'gender_split'],
        'assistant_admin' => ['signals', 'intelligence_loop', 'progress', 'history', 'chronic_absentees', 'substitutes_needed', 'parent_msg_age', 'task_aging', 'strength_changes', 'students_total', 'staff_total', 'attendance_today', 'attendance_trend', 'class_attendance', 'absent_today', 'staff_on_leave_list', 'fees_today', 'fees_period', 'fees_month', 'admissions_year', 'admissions_period', 'admission_funnel', 'followups_due', 'recent_enquiries', 'visitors_today', 'visitors_period', 'complaints', 'tasks_pending_all', 'parent_msgs_pending', 'pending_approvals', 'recent_circulars', 'staff_on_leave_today', 'upcoming_events', 'birthdays', 'gender_split'],
        'principal' => ['payroll', 'signals', 'intelligence_loop', 'progress', 'history', 'chronic_absentees', 'substitutes_needed', 'parent_msg_age', 'task_aging', 'homework_rate', 'strength_changes', 'transport_summary', 'hostel_summary', 'students_total', 'staff_total', 'attendance_today', 'attendance_trend', 'class_attendance', 'absent_today', 'staff_on_leave_today', 'staff_on_leave_list', 'leaves_pending', 'fees_today', 'fees_period', 'fees_month', 'fee_trend', 'admissions_year', 'admissions_period', 'admission_funnel', 'parent_msgs_pending', 'complaints', 'pending_approvals', 'recent_circulars', 'upcoming_events', 'birthdays', 'upcoming_exams', 'exam_results', 'students_by_class', 'gender_split'],
        'vice_principal' => ['signals', 'intelligence_loop', 'progress', 'history', 'chronic_absentees', 'substitutes_needed', 'parent_msg_age', 'task_aging', 'homework_rate', 'strength_changes', 'transport_summary', 'hostel_summary', 'students_total', 'staff_total', 'attendance_today', 'attendance_trend', 'class_attendance', 'absent_today', 'staff_on_leave_today', 'staff_on_leave_list', 'leaves_pending', 'fees_today', 'fees_period', 'fees_month', 'fee_trend', 'admissions_year', 'admissions_period', 'admission_funnel', 'parent_msgs_pending', 'complaints', 'pending_approvals', 'recent_circulars', 'upcoming_events', 'birthdays', 'upcoming_exams', 'exam_results', 'students_by_class', 'gender_split'],
        'counsellor' => ['signals', 'progress', 'chronic_absentees', 'parent_msg_age', 'students_total', 'attendance_today', 'absent_today', 'parent_msgs_pending', 'recent_parent_messages', 'recent_circulars', 'tasks_pending_me', 'upcoming_exams', 'exam_results', 'upcoming_events', 'birthdays'],
        'clerk' => ['signals', 'progress', 'students_total', 'fees_today', 'fees_period', 'fees_month', 'fee_trend', 'recent_fee_receipts', 'admissions_year', 'admissions_period', 'admission_funnel', 'followups_due', 'recent_enquiries', 'visitors_today', 'visitors_period', 'inward_month', 'outward_month', 'petty_cash_month', 'tasks_pending_me', 'recent_circulars', 'upcoming_events', 'birthdays'],
        'assistant_clerk' => ['signals', 'progress', 'fees_today', 'fees_period', 'fees_month', 'fee_trend', 'recent_fee_receipts', 'admissions_year', 'admissions_period', 'admission_funnel', 'followups_due', 'recent_enquiries', 'visitors_today', 'visitors_period', 'inward_month', 'tasks_pending_me', 'recent_circulars', 'upcoming_events', 'birthdays'],
        'librarian' => ['signals', 'tasks_pending_me', 'my_leaves_pending', 'library_overdue', 'recent_circulars', 'upcoming_events', 'birthdays'],
        'store_keeper' => ['signals', 'requisitions', 'po_status', 'items_total', 'vendors_total', 'purchases_month', 'stock_reorder', 'recent_requisitions', 'tasks_pending_me', 'recent_circulars', 'upcoming_events'],
        'exam_department' => ['signals', 'upcoming_exams', 'exam_evaluation', 'exam_results', 'students_total', 'attendance_today', 'tasks_pending_me', 'recent_circulars', 'upcoming_events'],
        'trustee' => ['health', 'year_compare', 'payroll', 'decisions_for_me', 'management_delivery', 'data_trust', 'signals', 'intelligence_loop', 'progress', 'history', 'strength_changes', 'transport_summary', 'hostel_summary', 'students_total', 'staff_total', 'attendance_today', 'attendance_trend', 'class_attendance', 'fees_today', 'fees_period', 'fees_month', 'fee_trend', 'admissions_year', 'admissions_period', 'admission_funnel', 'students_by_class', 'gender_split', 'exam_results', 'complaints', 'recent_circulars', 'upcoming_events'],
        'peon' => ['recent_circulars', 'tasks_pending_me', 'my_leaves_pending', 'visitors_today', 'visitors_period', 'upcoming_events', 'birthdays'],
        // Teacher and Student see only their own classes / their own record.
        'teacher' => ['signals', 'progress', 'my_classes', 'students_total', 'attendance_today', 'attendance_trend', 'today_schedule', 'attendance_pending', 'homework_pending', 'assignments_to_grade', 'support_students', 'teaching_impact', 'class_mastery', 'absent_today', 'chronic_absentees', 'class_circulars', 'birthdays', 'upcoming_events', 'tasks_pending_me', 'my_leaves_pending', 'history'],
        'student' => ['today_schedule', 'my_work', 'my_exams', 'my_mastery', 'my_next', 'my_streak', 'my_insights', 'progress', 'class_circulars', 'upcoming_events', 'history'],
        'workspace' => ['tasks_pending_me', 'my_leaves_pending', 'recent_circulars', 'upcoming_events'],
    ];

    /** Roles that may open the fees panel (school-wide money figures). */
    private const FEES_ROLES = ['admin', 'assistant_admin', 'principal', 'vice_principal', 'trustee', 'clerk', 'assistant_clerk'];

    /** Roles that may look a student up by name (class and a parent's number come back). */
    private const FIND_ROLES = ['admin', 'assistant_admin', 'principal', 'vice_principal', 'counsellor', 'clerk', 'assistant_clerk'];

    /** Roles that may turn a signal into a task for someone. Trustees instruct through management, not through the task list. */
    private const FOLLOWUP_ROLES = ['admin', 'assistant_admin', 'principal', 'vice_principal'];

    /** Roles that may set the school's targets. */
    private const TARGET_ROLES = ['trustee', 'admin', 'principal'];

    /** What the school can set a target for, with the range that makes sense. `higher` = bigger is better. */
    private const TARGET_METRICS = [
        'fees_collection_rate' => ['label' => 'Fees collected', 'unit' => '%', 'min' => 1, 'max' => 100],
        'attendance_rate' => ['label' => 'Student attendance', 'unit' => '%', 'min' => 1, 'max' => 100],
        'admissions_target' => ['label' => 'Admissions this year', 'unit' => 'count', 'min' => 1, 'max' => 100000],
        'retention_rate' => ['label' => 'Students retained', 'unit' => '%', 'min' => 1, 'max' => 100],
        'delivery_rate' => ['label' => 'Management actions delivered on time', 'unit' => '%', 'min' => 1, 'max' => 100],
    ];

    /** Profiles whose tasks count as management instructions. */
    private const MANAGEMENT_PROFILES = ['admin', 'school admin', 'super admin', 'principal', 'vice principal', 'assistant admin', 'trustee'];

    /** Period choices for the headline money / enquiry / visitor figures. */
    private const PERIODS = ['today', 'week', 'month', 'year'];

    private int $tenant;

    private $syear;

    private $userId;

    private string $today;

    private string $period = 'today';

    private string $role = 'workspace';

    /** 0 = every class. Narrows the student, attendance and homework figures to one class. */
    private int $standardId = 0;

    /** @var array<int, array{0:int,1:int}>|null (standard, division) pairs a teacher teaches */
    private ?array $pairs = null;

    private $enrolment = false;

    /** Resolve a profile name to a dashboard key; unknown names get the plain workspace. */
    public static function roleKeyFor(string $profileName): string
    {
        $name = strtolower(trim($profileName));
        foreach (config('role_dashboards.roles', []) as $key => $role) {
            if (in_array($name, $role['profiles'], true)) {
                return $key;
            }
        }

        // Free-text profile names ("Sr. Clerk", "Account Clerk"): match on the role word.
        if (preg_match('/\bvice[\s-]*principal\b/', $name)) {
            return 'vice_principal';
        }
        if (preg_match('/\bprincipal\b/', $name)) {
            return 'principal';
        }
        if (preg_match('/\bclerk\b/', $name)) {
            return 'clerk';
        }
        if (preg_match('/\b(librarian|libr[a-z]*ian)\b/', $name)) {
            return 'librarian';
        }
        if (preg_match('/\bstore[\s-]*keeper\b/', $name)) {
            return 'store_keeper';
        }
        // "Class Teacher", "Senior Teacher", "HOD", "Faculty" are teachers; anything with "student" is a student.
        if (preg_match('/\b(teacher|hod|faculty)\b/', $name)) {
            return 'teacher';
        }
        if (preg_match('/\bstudent\b/', $name)) {
            return 'student';
        }

        return 'workspace';
    }

    public function summary(Request $request): JsonResponse
    {
        $this->tenant = (int) session()->get('sub_institute_id');
        $this->syear = session()->get('syear');
        $this->userId = session()->get('user_id');
        $this->today = Carbon::today()->toDateString();
        $this->period = in_array($request->input('period'), self::PERIODS, true) ? $request->input('period') : 'today';
        $this->standardId = max((int) $request->input('standard_id', 0), 0);

        $profile = (string) session()->get('user_profile_name');
        $role = self::roleKeyFor($profile);
        // A student opens the student dashboard and nothing else; staff never open it.
        if ((bool) session()->get('is_student') !== ($role === 'student')) {
            return response()->json(['status' => '0', 'message' => 'This dashboard is not available for your role.'], 403);
        }
        $this->role = $role;
        $widgets = self::WIDGETS[$role] ?? self::WIDGETS['workspace'];

        $data = [];
        foreach ($widgets as $widget) {
            try {
                $data[$widget] = $this->{'w_'.$widget}();
            } catch (\Throwable $e) {
                $data[$widget] = null;
            }
        }

        return response()->json([
            'status' => '1',
            'message' => 'Success',
            'role' => $role,
            'label' => config("role_dashboards.roles.{$role}.label", 'My workspace'),
            'profile_name' => $profile,
            'period' => $this->period,
            'can_see_fees' => in_array($role, self::FEES_ROLES, true),
            'can_find_student' => in_array($role, self::FIND_ROLES, true),
            'can_assign_followup' => in_array($role, self::FOLLOWUP_ROLES, true),
            'can_set_targets' => in_array($role, self::TARGET_ROLES, true),
            'can_decide' => in_array($role, ['trustee', 'principal', 'vice_principal', 'admin'], true),
            'standard_id' => $this->standardId,
            'standards' => $this->standardsList($role),
            'explore_topics' => self::exploreTopicsFor($role),
            'widgets' => $data,
        ]);
    }

    /**
     * The subjects a role has something to open for ("more like this"): those with at least one of their first cards available.
     * The page uses this so a click only opens a section when there is something in it, and otherwise does what it always did.
     */
    public static function exploreTopicsFor(string $role): array
    {
        $library = \App\Services\Dashboard\WidgetLibrary::forRole(self::askableFor($role));
        $out = [];
        foreach (\App\Services\Dashboard\WidgetLibrary::TOPICS as $topic => $def) {
            if (array_intersect($def['core'], array_keys($library))) {
                $out[] = $topic;
            }
        }

        return $out;
    }

    /** The widget names a role is given. */
    public static function widgetsFor(string $role): array
    {
        return self::WIDGETS[$role] ?? self::WIDGETS['workspace'];
    }

    /** Leadership roles that may ask for any school-wide figure the admin dashboard has, apart from the lists below. */
    private const LEADERSHIP_ASKABLE = ['assistant_admin', 'principal', 'vice_principal', 'trustee'];

    /** Widgets that exist only to be asked for (never computed for the dashboard itself): class-level counts, no names. */
    private const ASKABLE_ONLY = [
        'absent_by_class', 'chronic_by_class', 'unmarked_classes', 'fees_by_mode', 'fees_by_class', 'enquiries_by_month', 'enquiries_by_source', 'enquiries_by_class',
        'students_joined_by_month', 'staff_by_role', 'leaves_by_type', 'homework_by_month', 'homework_by_class', 'complaints_by_month', 'library_issues_by_month', 'items_by_category',
    ];

    /** Not offered to those roles on request unless their own dashboard already has them: lists of named people and money lines. */
    private const ASKABLE_EXCLUDED = ['payroll', 'recent_fee_receipts', 'absent_today', 'chronic_absentees', 'staff_on_leave_list', 'substitutes_needed', 'followups_due', 'recent_enquiries', 'recent_parent_messages', 'library_overdue'];

    /**
     * What a role may ADD to its own dashboard through the assistant: its own widgets, and for leadership roles the
     * school-wide figures of the admin dashboard too (so a trustee can ask for birthdays or visitor counts), minus the
     * lists of named people and money lines in ASKABLE_EXCLUDED. This is what the assistant offers; the dashboard itself
     * still computes only WIDGETS[role].
     */
    public static function askableFor(string $role): array
    {
        $own = self::widgetsFor($role);
        if ($role === 'admin') {
            return array_values(array_unique(array_merge($own, self::ASKABLE_ONLY)));
        }
        if (! in_array($role, self::LEADERSHIP_ASKABLE, true)) {
            return $own;
        }

        return array_values(array_unique(array_merge($own, array_diff(self::WIDGETS['admin'], self::ASKABLE_EXCLUDED), self::ASKABLE_ONLY)));
    }

    /**
     * One widget's raw figures for the signed-in user, for the dashboard assistant's widgets. Only a widget this role is
     * given can be asked for, and it is worked out by the same code, with the same role limits, as on the dashboard itself.
     * Null when the role does not get it or it cannot be worked out ("no data", never a made-up zero).
     */
    public function widgetValue(string $role, string $widget)
    {
        if (! in_array($widget, self::askableFor($role), true) || ! method_exists($this, 'w_'.$widget)) {
            return null;
        }
        $this->tenant = (int) session()->get('sub_institute_id');
        $this->syear = session()->get('syear');
        $this->userId = session()->get('user_id');
        $this->today = Carbon::today()->toDateString();
        $this->period = 'month';
        $this->role = $role;
        $this->standardId = 0;

        try {
            return $this->{'w_'.$widget}();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** A request for the existing Fees controllers. They read the session off the request, so it must carry the live one. */
    private function internalRequest(array $body): Request
    {
        $request = Request::create('', 'POST', $body);
        $request->setLaravelSession(app('session.store'));

        return $request;
    }

    /**
     * Fees panel: collected vs outstanding and payment modes. Loaded on its
     * own, and refused outright for
     * roles that are not meant to see money or parents' phone numbers. The figures come from the
     * existing Fees dashboard, so they match that screen exactly.
     */
    public function fees(Request $request): JsonResponse
    {
        $role = self::roleKeyFor((string) session()->get('user_profile_name'));
        if (session()->get('is_student') || ! in_array($role, self::FEES_ROLES, true)) {
            return response()->json(['status' => '0', 'message' => 'The fees summary is not available for your role.'], 403);
        }

        $tenant = (int) session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $body = ['sub_institute_id' => $tenant, 'syear' => $syear, 'type' => 'JSON'];

        $summary = json_decode(app(FeesDashboardApiController::class)->summary($this->internalRequest($body))->getContent(), true) ?: [];

        return response()->json([
            'status' => '1',
            'message' => 'Success',
            'summary' => $summary['summary'] ?? null,
            'payment_mode_mix' => $summary['payment_mode_mix'] ?? [],
        ]);
    }

    /* ---------------------------------------------------------------- people */

    /** The (standard, division) pairs a teacher teaches: classes they are class teacher of, plus every class on their timetable. */
    private function teachingPairs(): array
    {
        if ($this->pairs !== null) {
            return $this->pairs;
        }
        $scope = fn ($q) => $q->where('sub_institute_id', $this->tenant)->where('syear', $this->syear)->where('teacher_id', $this->userId);
        $rows = $scope(DB::table('class_teacher'))->get(['standard_id', 'division_id'])
            ->concat($scope(DB::table('timetable'))->select('standard_id', 'division_id')->distinct()->get());

        return $this->pairs = $rows->map(fn ($r) => [(int) $r->standard_id, (int) $r->division_id])
            ->unique(fn ($p) => $p[0].'-'.$p[1])->values()->all();
    }

    /** Narrow a query to the teacher's classes, or to the class chosen in the filter. */
    private function scopeClasses($q, string $standardColumn, string $divisionColumn)
    {
        if ($this->role === 'teacher') {
            $pairs = $this->teachingPairs();
            if (! $pairs) {
                return $q->whereRaw('1 = 0');
            }

            return $q->where(function ($w) use ($pairs, $standardColumn, $divisionColumn) {
                foreach ($pairs as [$std, $div]) {
                    $w->orWhere(fn ($x) => $x->where($standardColumn, $std)->where($divisionColumn, $div));
                }
            });
        }

        return $q->when($this->standardId > 0, fn ($x) => $x->where($standardColumn, $this->standardId));
    }

    private function myEnrolment(): ?object
    {
        if ($this->enrolment === false) {
            $this->enrolment = DB::table('tblstudent_enrollment')->where('student_id', $this->userId)->where('sub_institute_id', $this->tenant)
                ->where('syear', $this->syear)->whereNull('end_date')->first(['standard_id', 'section_id']);
        }

        return $this->enrolment;
    }

    private function enrolled()
    {
        return $this->scopeClasses(DB::table('tblstudent_enrollment as se')
            ->join('tblstudent as ts', 'ts.id', '=', 'se.student_id')
            ->where('se.sub_institute_id', $this->tenant)
            ->where('se.syear', $this->syear)
            ->whereNull('se.end_date'), 'se.standard_id', 'se.section_id');
    }

    private function w_students_total(): int
    {
        return (int) $this->enrolled()->count();
    }

    private function w_staff_total(): int
    {
        return (int) DB::table('tbluser')->where('sub_institute_id', $this->tenant)->where('status', '1')->count();
    }

    private function w_students_by_class(): array
    {
        return DB::table('tblstudent_enrollment as se')
            ->join('standard as s', 's.id', '=', 'se.standard_id')
            ->selectRaw('s.id as standard_id, s.name as standard_name, COUNT(se.student_id) as students')
            ->where('se.sub_institute_id', $this->tenant)->where('se.syear', $this->syear)->whereNull('se.end_date')
            ->groupBy('s.id', 's.name', 's.sort_order')->orderBy('s.sort_order')->get()->all();
    }

    /* ------------------------------------------------------------ attendance */

    private function attendanceByDay(string $from)
    {
        return DB::table('attendance_student')
            ->selectRaw("DATE(attendance_date) as day, SUM(attendance_code = 'P') as present, SUM(attendance_code = 'A') as absent")
            ->where('sub_institute_id', $this->tenant)->where('syear', $this->syear)
            ->when(true, fn ($q) => $this->scopeClasses($q, 'standard_id', 'section_id'))
            ->whereDate('attendance_date', '>=', $from)
            ->groupBy('day')->get()->keyBy('day');
    }

    private function w_attendance_today(): array
    {
        $row = $this->attendanceByDay($this->today)[$this->today] ?? null;
        $present = (int) ($row->present ?? 0);
        $absent = (int) ($row->absent ?? 0);
        $total = $this->w_students_total();

        return ['present' => $present, 'absent' => $absent, 'not_marked' => max(0, $total - $present - $absent), 'total_students' => $total];
    }

    private function w_attendance_trend(): array
    {
        $from = Carbon::today()->subDays(29)->toDateString();
        $by = $this->attendanceByDay($from);
        $out = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i)->toDateString();
            $p = (int) ($by[$day]->present ?? 0);
            $a = (int) ($by[$day]->absent ?? 0);
            // null, not 0, for a day nobody marked — a holiday is not a 0% day.
            $out[] = ['date' => $day, 'label' => Carbon::parse($day)->format('D'), 'present' => $p, 'absent' => $a, 'rate' => ($p + $a) > 0 ? round($p / ($p + $a) * 100, 1) : null];
        }

        return $out;
    }

    private function w_staff_on_leave_today(): int
    {
        return (int) DB::table('hrms_emp_leaves')
            ->where('sub_institute_id', $this->tenant)->whereNull('deleted_at')
            ->whereIn('status', ['approved', 'approved_lwp'])
            ->whereDate('from_date', '<=', $this->today)->whereDate('to_date', '>=', $this->today)->count();
    }

    private function w_leaves_pending(): int
    {
        return (int) DB::table('hrms_emp_leaves')
            ->where('sub_institute_id', $this->tenant)->whereNull('deleted_at')->where('status', 'pending')->count();
    }

    private function w_my_leaves_pending(): int
    {
        return (int) DB::table('hrms_emp_leaves')
            ->where('sub_institute_id', $this->tenant)->whereNull('deleted_at')
            ->where('user_id', $this->userId)->where('status', 'pending')->count();
    }

    /**
     * Attendance by class on the latest day (within 30 days) that anyone marked it, lowest rate first,
     * so the classes that need a call are at the top.
     */
    private function w_class_attendance(): ?array
    {
        $day = DB::table('attendance_student')
            ->where('sub_institute_id', $this->tenant)->where('syear', $this->syear)
            ->whereDate('attendance_date', '<=', $this->today)
            ->whereDate('attendance_date', '>=', Carbon::today()->subDays(29)->toDateString())
            ->max('attendance_date');
        if (! $day) {
            return ['date' => null, 'rows' => []];
        }

        $rows = DB::table('attendance_student as a')
            ->join('standard as s', 's.id', '=', 'a.standard_id')
            ->where('a.sub_institute_id', $this->tenant)->where('a.syear', $this->syear)
            ->whereDate('a.attendance_date', substr((string) $day, 0, 10))
            ->selectRaw("s.id as standard_id, s.name as standard_name, SUM(a.attendance_code = 'P') as present, SUM(a.attendance_code = 'A') as absent")
            ->groupBy('s.id', 's.name', 's.sort_order')->get()
            ->map(function ($r) {
                $marked = (int) $r->present + (int) $r->absent;

                return ['standard_id' => (int) $r->standard_id, 'standard_name' => (string) $r->standard_name, 'present' => (int) $r->present, 'absent' => (int) $r->absent, 'rate' => $marked > 0 ? round($r->present / $marked * 100, 1) : null];
            })
            ->filter(fn ($r) => $r['rate'] !== null)->sortBy('rate')->values()->all();

        return ['date' => substr((string) $day, 0, 10), 'rows' => $rows];
    }

    /** Gender as recorded. The column holds M / F / Male / Female plus blanks, dashes and stray invisible characters. */
    private function w_gender_split(): array
    {
        $out = ['Male' => 0, 'Female' => 0, 'Not recorded' => 0];
        foreach ($this->enrolled()->selectRaw('ts.gender as g, COUNT(*) as n')->groupBy('ts.gender')->get() as $r) {
            $clean = strtolower(preg_replace('/[^A-Za-z]/', '', (string) $r->g));
            $key = in_array($clean, ['m', 'male'], true) ? 'Male' : (in_array($clean, ['f', 'female'], true) ? 'Female' : 'Not recorded');
            $out[$key] += (int) $r->n;
        }

        return $out;
    }

    /** Admission pipeline: enquiries -> registrations -> admitted (admission_status = YES). */
    private function w_admission_funnel(): array
    {
        $reg = DB::table('admission_registration')->where('sub_institute_id', $this->tenant);

        return [
            'enquiries' => $this->w_admissions_year(),
            'registered' => (int) (clone $reg)->count(),
            'admitted' => (int) (clone $reg)->where('admission_status', 'YES')->count(),
        ];
    }

    /* ------------------------------------------------- period figures (today / week / month / year) */

    /**
     * The chosen period up to today, and the period before it covering the same number of days
     * (so "this week so far" is compared with the same days of last week, not a full week).
     *
     * @return array{0:Carbon,1:Carbon,2:Carbon,3:Carbon,4:string}
     */
    private function range(): array
    {
        $to = Carbon::today();
        [$from, $prevFrom, $label] = match ($this->period) {
            'week' => [$to->copy()->startOfWeek(), $to->copy()->startOfWeek()->subWeek(), 'This week'],
            'month' => [$to->copy()->startOfMonth(), $to->copy()->startOfMonth()->subMonthNoOverflow(), 'This month'],
            'year' => [$to->copy()->startOfYear(), $to->copy()->startOfYear()->subYear(), 'This year'],
            default => [$to->copy(), $to->copy()->subDay(), 'Today'],
        };
        $prevTo = $prevFrom->copy()->addDays($from->diffInDays($to));

        return [$from, $to, $prevFrom, $prevTo, $label];
    }

    private function feesBetween(Carbon $from, Carbon $to): float
    {
        $scope = ['sub_institute_id' => $this->tenant, 'syear' => $this->syear, 'is_deleted' => 'N'];
        $a = (float) DB::table('fees_collect')->where($scope)->whereDate('receiptdate', '>=', $from->toDateString())->whereDate('receiptdate', '<=', $to->toDateString())->sum('amount');
        $b = (float) DB::table('fees_paid_other')->where($scope)->whereDate('receiptdate', '>=', $from->toDateString())->whereDate('receiptdate', '<=', $to->toDateString())->sum('actual_amountpaid');

        return round($a + $b, 2);
    }

    private function w_fees_period(): array
    {
        [$from, $to, $pf, $pt, $label] = $this->range();

        return ['amount' => $this->feesBetween($from, $to), 'previous' => $this->feesBetween($pf, $pt), 'label' => $label];
    }

    private function w_admissions_period(): array
    {
        [$from, $to, $pf, $pt, $label] = $this->range();
        $count = fn (Carbon $a, Carbon $b) => (int) DB::table('admission_enquiry')->where('sub_institute_id', $this->tenant)->whereNull('deleted_at')
            ->whereDate('created_on', '>=', $a->toDateString())->whereDate('created_on', '<=', $b->toDateString())->count();

        return ['count' => $count($from, $to), 'previous' => $count($pf, $pt), 'label' => $label];
    }

    private function w_visitors_period(): array
    {
        [$from, $to, $pf, $pt, $label] = $this->range();
        $count = fn (Carbon $a, Carbon $b) => (int) DB::table('visitor_master')->where('sub_institute_id', $this->tenant)
            ->whereDate('meet_date', '>=', $a->toDateString())->whereDate('meet_date', '<=', $b->toDateString())->count();

        return ['count' => $count($from, $to), 'previous' => $count($pf, $pt), 'label' => $label];
    }

    /* ----------------------------------------------------- people who need a call today */

    /** Latest day (within 30 days) that anyone marked attendance. */
    private function latestMarkedDay(): ?string
    {
        $day = DB::table('attendance_student')
            ->where('sub_institute_id', $this->tenant)->where('syear', $this->syear)
            ->whereDate('attendance_date', '<=', $this->today)
            ->whereDate('attendance_date', '>=', Carbon::today()->subDays(29)->toDateString())
            ->max('attendance_date');

        return $day ? substr((string) $day, 0, 10) : null;
    }

    /** Students marked absent on the latest marked day, with the parent's number to ring. */
    private function w_absent_today(): array
    {
        $day = $this->latestMarkedDay();
        if (! $day) {
            return ['date' => null, 'total' => 0, 'items' => []];
        }

        $q = DB::table('attendance_student as a')
            ->join('tblstudent as s', 's.id', '=', 'a.student_id')
            ->leftJoin('standard as st', 'st.id', '=', 'a.standard_id')
            ->leftJoin('division as d', 'd.id', '=', 'a.section_id')
            ->where('a.sub_institute_id', $this->tenant)->where('a.syear', $this->syear)
            ->when(true, fn ($q) => $this->scopeClasses($q, 'a.standard_id', 'a.section_id'))
            ->whereDate('a.attendance_date', $day)->where('a.attendance_code', 'A');

        return [
            'date' => $day,
            'total' => (int) (clone $q)->count(),
            'items' => (clone $q)
                ->selectRaw("s.id, CONCAT_WS(' ', s.first_name, s.last_name) as name, CONCAT_WS(' - ', st.name, d.name) as class_name, NULLIF(TRIM(COALESCE(s.mobile, s.mother_mobile, s.student_mobile, '')), '') as phone")
                ->orderBy('st.sort_order')->orderBy('s.first_name')->limit(8)->get()
                ->map(fn ($r) => ['id' => (int) $r->id, 'name' => trim((string) $r->name), 'class_name' => (string) $r->class_name, 'phone' => $r->phone])->all(),
        ];
    }

    private function w_staff_on_leave_list(): array
    {
        $q = DB::table('hrms_emp_leaves as l')
            ->join('tbluser as u', 'u.id', '=', 'l.user_id')
            ->where('l.sub_institute_id', $this->tenant)->whereNull('l.deleted_at')
            ->whereIn('l.status', ['approved', 'approved_lwp'])
            ->whereDate('l.from_date', '<=', $this->today)->whereDate('l.to_date', '>=', $this->today);

        return [
            'total' => (int) (clone $q)->count(),
            'items' => (clone $q)->selectRaw("l.id, CONCAT_WS(' ', u.first_name, u.last_name) as name, l.to_date")->orderBy('u.first_name')->limit(8)->get()
                ->map(fn ($r) => ['id' => (int) $r->id, 'name' => trim((string) $r->name), 'until' => $r->to_date])->all(),
        ];
    }

    /** Birthdays in the next 7 days (today included). Month-day matching, so December to January works. */
    private function w_birthdays(): array
    {
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[Carbon::today()->addDays($i)->format('m-d')] = $i;
        }
        $keys = array_keys($days);
        $pack = function ($rows, string $dobField) use ($days) {
            return $rows->map(function ($r) use ($days, $dobField) {
                $md = substr((string) $r->{$dobField}, 5, 5);

                return ['id' => (int) $r->id, 'name' => trim((string) $r->name), 'detail' => $r->detail ?? null, 'in_days' => $days[$md] ?? 0, 'date' => Carbon::today()->addDays($days[$md] ?? 0)->toDateString()];
            })->sortBy('in_days')->values()->all();
        };

        $students = $this->enrolled()->whereIn(DB::raw("DATE_FORMAT(ts.dob, '%m-%d')"), $keys)
            ->selectRaw("ts.id, CONCAT_WS(' ', ts.first_name, ts.last_name) as name, ts.dob, '' as detail")->limit(40)->get();
        $staff = DB::table('tbluser')->where('sub_institute_id', $this->tenant)->where('status', '1')
            ->whereIn(DB::raw("DATE_FORMAT(birthdate, '%m-%d')"), $keys)
            ->selectRaw("id, CONCAT_WS(' ', first_name, last_name) as name, birthdate, '' as detail")->limit(40)->get();

        return [
            'students' => array_slice($pack($students, 'dob'), 0, 8),
            'staff' => array_slice($pack($staff, 'birthdate'), 0, 8),
            'student_total' => $students->count(),
            'staff_total' => $staff->count(),
        ];
    }

    /** School calendar events and holidays from today onwards. */
    private function w_upcoming_events(): array
    {
        $events = DB::table('calendar_events')->where('sub_institute_id', $this->tenant)->whereDate('school_date', '>=', $this->today)
            ->selectRaw("id, title, school_date as date, event_type as kind")->orderBy('school_date')->limit(8)->get()
            ->map(fn ($r) => ['id' => 'e'.$r->id, 'title' => (string) $r->title, 'date' => substr((string) $r->date, 0, 10), 'kind' => (string) ($r->kind ?: 'Event')]);
        $holidays = DB::table('hrms_holidays')->where('sub_institute_id', $this->tenant)->whereNull('deleted_at')->whereDate('to_date', '>=', $this->today)
            ->selectRaw('id, holiday_name as title, from_date as date')->orderBy('from_date')->limit(8)->get()
            ->map(fn ($r) => ['id' => 'h'.$r->id, 'title' => (string) $r->title, 'date' => substr((string) $r->date, 0, 10), 'kind' => 'Holiday']);

        return $events->concat($holidays)->sortBy('date')->values()->take(8)->all();
    }

    /** Enquiries whose follow-up is due today or overdue (last 30 days) and that have not registered yet. */
    private function w_followups_due(): array
    {
        $q = DB::table('admission_enquiry as e')
            ->leftJoin('admission_registration as r', 'r.enquiry_id', '=', 'e.id')
            ->where('e.sub_institute_id', $this->tenant)->whereNull('e.deleted_at')->whereNull('r.id')
            ->whereDate('e.followup_date', '<=', $this->today)
            ->whereDate('e.followup_date', '>=', Carbon::today()->subDays(30)->toDateString());

        return [
            'total' => (int) (clone $q)->count(),
            'items' => (clone $q)->selectRaw("e.id, CONCAT_WS(' ', e.first_name, e.last_name) as name, e.enquiry_no, e.mobile, e.followup_date")->orderBy('e.followup_date')->limit(6)->get()
                ->map(fn ($r) => ['id' => (int) $r->id, 'name' => trim((string) $r->name), 'detail' => $r->enquiry_no, 'phone' => $r->mobile, 'date' => substr((string) $r->followup_date, 0, 10)])->all(),
        ];
    }

    /** Everything waiting on one person's decision, in one place. */
    private function w_pending_approvals(): array
    {
        $leaves = $this->w_leaves_pending();
        $req = $this->w_requisitions();
        $po = $this->w_po_status();

        return ['leaves' => $leaves, 'requisitions' => $req['pending'], 'purchase_orders' => $po['awaiting'], 'total' => $leaves + $req['pending'] + $po['awaiting']];
    }

    /* ---------------------------------------------------------- library, stock, exams */

    private function w_library_overdue(): array
    {
        $q = DB::table('library_book_circulations as c')
            ->join('tblstudent as s', 's.id', '=', 'c.student_id')
            ->join('library_books as b', 'b.id', '=', 'c.book_id')
            ->where('c.sub_institute_id', $this->tenant)->where('c.syear', $this->syear)
            ->whereRaw("(c.return_date IS NULL OR c.return_date LIKE '0000-00%')")
            ->whereDate('c.due_date', '<', $this->today);

        return [
            'total' => (int) (clone $q)->count(),
            'items' => (clone $q)->selectRaw("c.id, b.title as book, CONCAT_WS(' ', s.first_name, s.last_name) as student, c.due_date, DATEDIFF(CURDATE(), c.due_date) as days")->orderBy('c.due_date')->limit(8)->get()
                ->map(fn ($r) => ['id' => (int) $r->id, 'title' => (string) $r->book, 'detail' => trim((string) $r->student), 'days' => (int) $r->days, 'date' => substr((string) $r->due_date, 0, 10)])->all(),
        ];
    }

    /**
     * Items whose RECORDED opening stock is at or below the reorder level. This estate keeps no running
     * stock balance (issues never reduce opening_stock), so this is a prompt to check, not a stock count.
     */
    private function w_stock_reorder(): array
    {
        $q = DB::table('inventory_item_master')->where('sub_institute_id', $this->tenant)
            ->whereRaw('COALESCE(minimum_stock, 0) > 0')->whereRaw('COALESCE(opening_stock, 0) <= COALESCE(minimum_stock, 0)');

        return [
            'total' => (int) (clone $q)->count(),
            'items' => (clone $q)->selectRaw('id, title, opening_stock, minimum_stock')->orderBy('title')->limit(8)->get()
                ->map(fn ($r) => ['id' => (int) $r->id, 'title' => (string) $r->title, 'recorded' => (float) $r->opening_stock, 'reorder_at' => (float) $r->minimum_stock])->all(),
        ];
    }

    /** Average and best percentage per class for the most recent exam that has marks. Absent students are left out. */
    private function w_exam_results(): ?array
    {
        $base = DB::table('result_marks')->where('sub_institute_id', $this->tenant)->whereNotNull('per')->where('per', '<>', '')
            ->where(fn ($w) => $w->whereNull('is_absent')->orWhere('is_absent', '<>', 1));
        $exam = (clone $base)->orderByDesc('created_at')->orderByDesc('id')->value('exam_title');
        if (! $exam) {
            return ['exam' => null, 'rows' => []];
        }

        $rows = (clone $base)->where('exam_title', $exam)
            ->selectRaw('standard_name, COUNT(DISTINCT student_id) as students, AVG(CAST(per AS DECIMAL(7,2))) as avg_per, MAX(CAST(per AS DECIMAL(7,2))) as best')
            ->groupBy('standard_name')->orderByDesc('avg_per')->limit(10)->get()
            ->map(fn ($r) => ['standard_name' => (string) $r->standard_name, 'students' => (int) $r->students, 'average' => round((float) $r->avg_per, 1), 'best' => round((float) $r->best, 1)])->all();

        return ['exam' => (string) $exam, 'rows' => $rows];
    }

    /* ------------------------------------------------------------------- the board view (trustee) */

    /** Tasks that management gave to someone else. */
    private function managementTasks()
    {
        return DB::table('task as t')
            ->join('tbluser as u', 'u.id', '=', 't.TASK_ALLOCATED')->join('tbluserprofilemaster as p', 'p.id', '=', 'u.user_profile_id')
            ->where('t.sub_institute_id', $this->tenant)->whereNull('t.deleted_at')->whereColumn('t.TASK_ALLOCATED', '<>', 't.TASK_ALLOCATED_TO')
            ->whereIn(DB::raw('LOWER(TRIM(p.name))'), self::MANAGEMENT_PROFILES);
    }

    /**
     * Is the school healthy? Each indicator is a figure, the target the school set for it, and a verdict. With no
     * target set the verdict is "no target": the dashboard does not judge a figure against a number it made up.
     */
    private function w_health(): array
    {
        $targets = DB::table('dashboard_targets')->where('sub_institute_id', $this->tenant)->where('syear', (int) $this->syear)->pluck('value', 'metric')->map(fn ($v) => (float) $v)->all();
        $out = [];
        $add = function (string $key, ?float $value, string $detail) use (&$out, $targets) {
            $meta = self::TARGET_METRICS[$key];
            $target = $targets[$key] ?? null;
            $status = $value === null ? 'no_data' : ($target === null ? 'no_target' : ($value >= $target ? 'good' : ($value >= $target * 0.9 ? 'watch' : 'act')));
            $out[] = ['key' => $key, 'label' => $meta['label'], 'unit' => $meta['unit'], 'value' => $value === null ? null : round($value, 1), 'target' => $target, 'status' => $status, 'detail' => $detail];
        };

        $s = json_decode(app(FeesDashboardApiController::class)->summary($this->internalRequest(['sub_institute_id' => $this->tenant, 'syear' => $this->syear, 'type' => 'JSON']))->getContent(), true)['summary'] ?? null;
        $add('fees_collection_rate', $s && ($s['demand_amount'] ?? 0) > 0 ? (float) $s['collection_rate'] : null, $s && ($s['demand_amount'] ?? 0) > 0 ? '₹'.number_format($s['collected_amount']).' of ₹'.number_format($s['demand_amount']) : 'No fee demand on record');

        $rates = collect($this->w_attendance_trend())->pluck('rate')->filter(fn ($r) => $r !== null)->take(-14)->values();
        $add('attendance_rate', $rates->isNotEmpty() ? (float) $rates->avg() : null, $rates->isNotEmpty() ? 'Average of the last '.$rates->count().' marked days' : 'No attendance marked in the last 30 days');

        $f = $this->w_admission_funnel();
        $add('admissions_target', (float) $f['admitted'], "{$f['admitted']} admitted, from {$f['enquiries']} enquiries");

        $enr = DB::table('tblstudent_enrollment')->where('sub_institute_id', $this->tenant)->where('syear', $this->syear);
        $strength = (int) (clone $enr)->count();
        $left = (int) (clone $enr)->whereNotNull('end_date')->count();
        $add('retention_rate', $strength > 0 ? 100 - $left / $strength * 100 : null, "{$left} left, out of ".number_format($strength).' enrolled this year');

        $tasks = $this->managementTasks();
        $done = (int) (clone $tasks)->whereIn('t.STATUS', ['COMPLETED', 'COMPLETE'])->count();
        $late = (int) (clone $tasks)->whereNotIn('t.STATUS', ['COMPLETED', 'COMPLETE'])->whereDate('t.TASK_DATE', '<', $this->today)->count();
        $add('delivery_rate', ($done + $late) > 0 ? $done / ($done + $late) * 100 : null, "{$done} delivered, {$late} overdue");

        return $out;
    }

    /**
     * This academic year beside the one before it, as plain numbers. No verdict is drawn from them here: this school's
     * older years can be incomplete (a sharp difference may be missing records rather than a real change), so the
     * card shows both figures and says so when they differ enough to be suspect.
     */
    private function w_year_compare(): array
    {
        $now = (int) $this->syear;
        $before = $now - 1;
        $enrol = fn (int $y) => (int) DB::table('tblstudent_enrollment')->where('sub_institute_id', $this->tenant)->where('syear', $y)->count();
        $fees = fn (int $y) => round((float) DB::table('fees_collect')->where(['sub_institute_id' => $this->tenant, 'syear' => $y, 'is_deleted' => 'N'])->sum('amount')
            + (float) DB::table('fees_paid_other')->where(['sub_institute_id' => $this->tenant, 'syear' => $y, 'is_deleted' => 'N'])->sum('actual_amountpaid'), 2);
        $enq = fn (int $y) => (int) DB::table('admission_enquiry')->where('sub_institute_id', $this->tenant)->where('syear', $y)->whereNull('deleted_at')->count();

        $items = [
            ['label' => 'Students enrolled', 'unit' => 'count', 'current' => $enrol($now), 'previous' => $enrol($before)],
            ['label' => 'Fees collected', 'unit' => 'rupees', 'current' => $fees($now), 'previous' => $fees($before)],
            ['label' => 'Admission enquiries', 'unit' => 'count', 'current' => $enq($now), 'previous' => $enq($before)],
        ];
        $suspect = collect($items)->contains(fn ($i) => $i['previous'] === 0 || ($i['previous'] > 0 && abs($i['current'] - $i['previous']) / $i['previous'] > 0.25));

        return [
            'previous_syear' => $before,
            'items' => $items,
            'caution' => $suspect ? 'These years differ sharply, or last year has no record at all. That can mean missing older records rather than a real change, so check them before relying on the comparison.' : null,
        ];
    }

    /**
     * What the school paid its staff, in total, for the months payroll was actually processed. Totals only: no name,
     * no individual figure ever leaves the server. Payroll is often run for part of the staff, so the card always
     * carries how many people the total covers, and says so when that is well short of the active staff.
     */
    private function w_payroll(): ?array
    {
        $rows = DB::table('employee_monthly_salary_data')
            ->where('sub_institute_id', $this->tenant)->where('year', '>', 0)
            ->selectRaw("year, month, STR_TO_DATE(CONCAT('1 ', month, ' ', year), '%d %b %Y') as month_start, COUNT(DISTINCT employee_id) as staff, SUM(total_payment) as paid, SUM(total_deduction) as deducted")
            ->groupBy('year', 'month')->havingRaw('month_start IS NOT NULL AND paid > 0')->orderByDesc('month_start')->limit(12)->get();
        if ($rows->isEmpty()) {
            return null;
        }
        // Stray old rows (a single month from a past year) are not a trend: keep the run of recent months only.
        $cutoff = strtotime('-6 months', strtotime($rows[0]->month_start));
        $rows = $rows->filter(fn ($r) => strtotime($r->month_start) >= $cutoff)->values();

        $label = fn ($r) => date('M Y', strtotime($r->month_start));
        $latest = $rows[0];
        $previous = $rows[1] ?? null;
        $active = $this->w_staff_total();
        $coverage = $active > 0 ? round(100 * (int) $latest->staff / $active, 1) : null;

        return [
            'latest' => ['label' => $label($latest), 'paid' => round((float) $latest->paid, 2), 'deducted' => round((float) $latest->deducted, 2), 'staff' => (int) $latest->staff],
            'previous' => $previous ? ['label' => $label($previous), 'paid' => round((float) $previous->paid, 2)] : null,
            'active_staff' => $active,
            'coverage_pct' => $coverage,
            'months' => $rows->reverse()->values()->map(fn ($r) => ['label' => date('M', strtotime($r->month_start)), 'paid' => round((float) $r->paid, 2)])->all(),
            'caution' => ($coverage !== null && $coverage < 90)
                ? "Payroll was processed for {$latest->staff} of {$active} active staff, so this is not the school's full salary cost."
                : null,
        ];
    }

    /** Recommendations waiting for a decision, most urgent first. Deciding stays in the Brain's own permissioned flow. */
    private function w_decisions_for_me(): array
    {
        $base = DB::table('hpbrain_recommendations')->where('tenant_id', (string) $this->tenant)->where('status', 'pending')->where('id', 'not like', 'demo-%');
        $items = (clone $base)->orderByRaw("FIELD(urgency, 'high', 'normal', 'low')")->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")->orderByDesc('confidence')->limit(5)
            ->get(['id', 'title', 'description', 'priority', 'urgency', 'impact', 'confidence'])
            ->map(function ($r) {
                $why = preg_match('/Why:\s*(.+?)(?:\n\s*\n|Basis:|$)/s', (string) $r->description, $m) ? trim($m[1]) : null;

                return ['id' => (string) $r->id, 'title' => (string) $r->title, 'why' => $why ? mb_substr($why, 0, 240) : null, 'impact' => $r->impact ? (string) $r->impact : null,
                    'urgency' => (string) $r->urgency, 'priority' => (string) $r->priority, 'confidence' => round((float) $r->confidence, 2)];
            })->all();

        return ['total' => (int) (clone $base)->count(), 'urgent' => (int) (clone $base)->where('urgency', 'high')->count(), 'items' => $items];
    }

    /** What management asked for, and whether it is getting done: overdue first, and who is carrying the overdue work. */
    private function w_management_delivery(): array
    {
        $base = $this->managementTasks();
        $open = (clone $base)->whereNotIn('t.STATUS', ['COMPLETED', 'COMPLETE']);
        $overdue = (clone $open)->whereDate('t.TASK_DATE', '<', $this->today);
        $done = (int) (clone $base)->whereIn('t.STATUS', ['COMPLETED', 'COMPLETE'])->count();
        $overdueCount = (int) (clone $overdue)->count();

        return [
            'total' => (int) (clone $base)->count(),
            'open' => (int) (clone $open)->count(),
            'overdue' => $overdueCount,
            'done' => $done,
            'delivered_rate' => ($done + $overdueCount) > 0 ? round($done / ($done + $overdueCount) * 100, 1) : null,
            'items' => (clone $overdue)->leftJoin('tbluser as o', 'o.id', '=', 't.TASK_ALLOCATED_TO')
                ->selectRaw("t.ID as id, t.TASK_TITLE as title, CONCAT_WS(' ', o.first_name, o.last_name) as owner, t.TASK_DATE as due, DATEDIFF(CURDATE(), t.TASK_DATE) as days_late")
                ->orderByDesc('days_late')->limit(6)->get()
                ->map(fn ($r) => ['id' => (int) $r->id, 'title' => (string) $r->title, 'owner' => trim((string) $r->owner) ?: 'Unassigned', 'due' => substr((string) $r->due, 0, 10), 'days_late' => (int) $r->days_late])->all(),
            'by_owner' => (clone $overdue)->leftJoin('tbluser as o', 'o.id', '=', 't.TASK_ALLOCATED_TO')
                ->selectRaw("CONCAT_WS(' ', o.first_name, o.last_name) as owner, COUNT(*) as n")->groupBy('o.id', 'o.first_name', 'o.last_name')->orderByDesc('n')->limit(5)->get()
                ->map(fn ($r) => ['owner' => trim((string) $r->owner) ?: 'Unassigned', 'overdue' => (int) $r->n])->all(),
        ];
    }

    /** Can these numbers be trusted? The Brain's own high-severity data-quality and adoption findings, most severe first. */
    private function w_data_trust(): array
    {
        $base = DB::table('hpbrain_signals')->where('tenant_id', (string) $this->tenant)->whereIn('status', ['new', 'reasoned'])->whereIn('severity', ['critical', 'high'])
            ->whereIn('classification', ['data_quality', 'process_adoption']);

        return [
            'total' => (int) (clone $base)->count(),
            'items' => (clone $base)->orderByRaw("FIELD(severity, 'critical', 'high')")->orderByDesc('confidence')->limit(4)->get(['id', 'severity', 'metadata', 'rule_key', 'source'])
                ->map(fn ($s) => ['id' => (string) $s->id, 'severity' => (string) $s->severity, 'title' => (string) (json_decode((string) $s->metadata, true)['title'] ?? $this->humanise((string) $s->rule_key)), 'source' => $this->humanise((string) $s->source)])->all(),
        ];
    }

    /** Set (or change) one target. Only the roles that own the school's goals may. */
    public function setTarget(Request $request): JsonResponse
    {
        $role = self::roleKeyFor((string) session()->get('user_profile_name'));
        if (session()->get('is_student') || ! in_array($role, self::TARGET_ROLES, true)) {
            return response()->json(['status' => '0', 'message' => 'Setting targets is not available for your role.'], 403);
        }
        $data = $request->validate(['metric' => ['required', 'string'], 'value' => ['required', 'numeric']]);
        $meta = self::TARGET_METRICS[$data['metric']] ?? null;
        if (! $meta) {
            return response()->json(['status' => '0', 'message' => 'That is not something a target can be set for.'], 422);
        }
        $value = (float) $data['value'];
        if ($value < $meta['min'] || $value > $meta['max']) {
            return response()->json(['status' => '0', 'message' => "A target for “{$meta['label']}” must be between {$meta['min']} and {$meta['max']}."], 422);
        }
        $key = ['sub_institute_id' => (int) session()->get('sub_institute_id'), 'syear' => (int) session()->get('syear'), 'metric' => $data['metric']];
        $existing = DB::table('dashboard_targets')->where($key)->first();
        $row = ['value' => $value, 'updated_by' => (int) session()->get('user_id'), 'updated_at' => now()];
        $existing ? DB::table('dashboard_targets')->where($key)->update($row) : DB::table('dashboard_targets')->insert($key + $row + ['created_at' => now()]);

        return response()->json(['status' => '1', 'message' => 'Target saved', 'metric' => $data['metric'], 'value' => $value]);
    }

    /* ------------------------------------------------------- teacher and student: their own day */

    private function todayLetter(): ?string
    {
        return ['1' => 'M', '2' => 'T', '3' => 'W', '4' => 'H', '5' => 'F', '6' => 'S'][Carbon::today()->format('N')] ?? null;
    }

    /** Today's periods: the teacher's own, or the student's class. */
    private function w_today_schedule(): array
    {
        $letter = $this->todayLetter();
        if ($letter === null) {
            return ['total' => 0, 'items' => []];
        }
        $q = DB::table('timetable as t')
            ->leftJoin('standard as st', 'st.id', '=', 't.standard_id')->leftJoin('division as d', 'd.id', '=', 't.division_id')
            ->leftJoin('subject as sj', 'sj.id', '=', 't.subject_id')->leftJoin('period as p', 'p.id', '=', 't.period_id')
            ->where('t.sub_institute_id', $this->tenant)->where('t.syear', $this->syear)->where('t.week_day', $letter);
        if ($this->role === 'student') {
            $e = $this->myEnrolment();
            if (! $e) {
                return ['total' => 0, 'items' => []];
            }
            $q->where('t.standard_id', $e->standard_id)->where('t.division_id', $e->section_id);
        } else {
            $q->where('t.teacher_id', $this->userId);
        }
        $rows = $q->selectRaw("t.id, p.title as period, p.sort_order, TIME_FORMAT(p.start_time, '%H:%i') as starts, TIME_FORMAT(p.end_time, '%H:%i') as ends, CONCAT_WS(' - ', st.name, d.name) as class_name, sj.subject_name as subject")
            ->orderBy('p.sort_order')->get();

        return [
            'total' => $rows->count(),
            'items' => $rows->map(fn ($r) => [
                'id' => (int) $r->id,
                'title' => trim(implode(' · ', array_filter([(string) $r->subject ?: null, $this->role === 'teacher' ? ((string) $r->class_name ?: null) : null]))) ?: 'Period',
                'detail' => (string) ($r->period ?: ''),
                'time' => $r->starts ? $r->starts.($r->ends ? '–'.$r->ends : '') : null,
            ])->all(),
        ];
    }

    private function w_my_classes(): array
    {
        $pairs = $this->teachingPairs();
        $classes = collect($pairs)->map(function ($p) {
            $name = DB::table('standard as s')->join('division as d', 'd.id', '=', DB::raw((int) $p[1]))->where('s.id', $p[0])->selectRaw("CONCAT_WS(' - ', s.name, d.name) as n")->value('n');
            $count = (int) DB::table('tblstudent_enrollment')->where('sub_institute_id', $this->tenant)->where('syear', $this->syear)->whereNull('end_date')->where('standard_id', $p[0])->where('section_id', $p[1])->count();

            return ['label' => (string) $name, 'students' => $count];
        })->all();

        return ['classes' => count($classes), 'students' => $this->w_students_total(), 'items' => array_slice($classes, 0, 12)];
    }

    /** Classes the teacher is class teacher of, where nobody has marked attendance today. */
    private function w_attendance_pending(): array
    {
        if ($this->todayLetter() === null) {
            return ['total' => 0, 'items' => []];
        }
        $marked = DB::table('attendance_student')->where('sub_institute_id', $this->tenant)->where('syear', $this->syear)->whereDate('attendance_date', $this->today)
            ->selectRaw('DISTINCT standard_id, section_id')->get()->map(fn ($r) => $r->standard_id.'-'.$r->section_id)->all();
        $mine = DB::table('class_teacher as ct')->join('standard as s', 's.id', '=', 'ct.standard_id')->join('division as d', 'd.id', '=', 'ct.division_id')
            ->where('ct.sub_institute_id', $this->tenant)->where('ct.syear', $this->syear)->where('ct.teacher_id', $this->userId)
            ->selectRaw("ct.standard_id, ct.division_id, CONCAT_WS(' - ', s.name, d.name) as label")->get();
        $open = $mine->filter(fn ($r) => ! in_array($r->standard_id.'-'.$r->division_id, $marked, true));

        return ['total' => $open->count(), 'items' => $open->map(fn ($r) => ['label' => (string) $r->label])->values()->all()];
    }

    private function w_homework_pending(): array
    {
        $q = $this->scopeClasses(DB::table('homework as h')->where('h.sub_institute_id', $this->tenant)->where('h.syear', $this->syear)->where('h.completion_status', 'N'), 'h.standard_id', 'h.division_id');

        return ['total' => (int) (clone $q)->count(), 'students' => (int) (clone $q)->distinct()->count('h.student_id')];
    }

    private function w_assignments_to_grade(): array
    {
        $q = DB::table('lms_assignment as a')->leftJoin('subject as s', 's.id', '=', 'a.subject_id')
            ->where('a.teacher_id', $this->userId)->where('a.sub_institute_id', $this->tenant)->where('a.syear', $this->syear)
            ->where('a.student_submission_status', 'Y')->where('a.teacher_submission_status', 'N');

        return [
            'count' => (int) (clone $q)->count(),
            'items' => (clone $q)->selectRaw('a.id, a.title, s.subject_name as subject, a.student_submitted_date as submitted')->orderByDesc('a.student_submitted_date')->limit(6)->get()
                ->map(fn ($r) => ['id' => (int) $r->id, 'title' => (string) $r->title, 'detail' => $r->subject, 'date' => $r->submitted ? substr((string) $r->submitted, 0, 10) : null])->all(),
        ];
    }

    private function w_class_circulars(): array
    {
        $q = DB::table('circular')->where('sub_institute_id', $this->tenant)->where('syear', $this->syear);
        if ($this->role === 'student') {
            $e = $this->myEnrolment();
            if (! $e) {
                return [];
            }
            $q->where('standard_id', $e->standard_id)->where(fn ($w) => $w->where('division_id', $e->section_id)->orWhereNull('division_id'));
        } else {
            $standards = collect($this->teachingPairs())->pluck(0)->unique()->all();
            if (! $standards) {
                return [];
            }
            $q->whereIn('standard_id', $standards);
        }

        return $q->select('id', 'title', 'date_')->orderByDesc('date_')->orderByDesc('id')->limit(6)->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'title' => (string) $r->title, 'detail' => null, 'date' => $r->date_])->all();
    }

    /** Names of LMS concepts, for PAL mastery rows that only carry the concept id. */
    private function conceptNames(array $ids): array
    {
        return $ids ? DB::table('lms_concept')->whereIn('id', $ids)->pluck('name', 'id')->all() : [];
    }

    /**
     * Students in this teacher's classes who need support, with the reasons in plain words and a suggested next step.
     * A student is listed when at least one of these is true: absent on 5+ of the last 10 marked days, 3+ homework items
     * pending in 30 days, a score under 40% in the last 90 days, or low PAL mastery on concepts they have tried at least 3 times.
     */
    private function w_support_students(): array
    {
        $ids = $this->enrolled()->pluck('ts.id')->map(fn ($v) => (int) $v)->all();
        if (! $ids) {
            return ['total' => 0, 'items' => []];
        }
        $reasons = [];
        $steps = [];
        $add = function (int $id, string $reason, string $step) use (&$reasons, &$steps) {
            $reasons[$id][] = $reason;
            $steps[$id][$step] = true;
        };

        $days = DB::table('attendance_student')->where('sub_institute_id', $this->tenant)->where('syear', $this->syear)
            ->when(true, fn ($q) => $this->scopeClasses($q, 'standard_id', 'section_id'))
            ->whereDate('attendance_date', '<=', $this->today)->selectRaw('DATE(attendance_date) as d')->distinct()->orderByDesc('d')->limit(10)->pluck('d')->all();
        if (count($days) >= 5) {
            DB::table('attendance_student')->where('sub_institute_id', $this->tenant)->whereIn('student_id', $ids)->whereIn(DB::raw('DATE(attendance_date)'), $days)
                ->selectRaw("student_id, SUM(attendance_code = 'A') as a")->groupBy('student_id')->havingRaw("SUM(attendance_code = 'A') >= 5")->get()
                ->each(fn ($r) => $add((int) $r->student_id, "absent on {$r->a} of the last ".count($days).' days', "Ring the parent to find out why they are missing school"));
        }

        DB::table('homework')->where('sub_institute_id', $this->tenant)->whereIn('student_id', $ids)->where('completion_status', 'N')
            ->whereDate('date', '>=', Carbon::today()->subDays(30)->toDateString())->selectRaw('student_id, COUNT(*) as n')->groupBy('student_id')->havingRaw('COUNT(*) >= 3')->get()
            ->each(fn ($r) => $add((int) $r->student_id, "{$r->n} homework items still pending", 'Find out what is blocking the work and agree a catch-up plan'));

        DB::table('result_marks')->where('sub_institute_id', $this->tenant)->whereIn('student_id', $ids)->whereNotNull('per')->where('per', '<>', '')
            ->where(fn ($w) => $w->whereNull('is_absent')->orWhere('is_absent', '<>', 1))->whereRaw('CAST(per AS DECIMAL(7,2)) < 40')
            ->whereDate('created_at', '>=', Carbon::today()->subDays(90)->toDateString())->orderByDesc('created_at')->get(['student_id', 'per', 'subject_name', 'exam_title'])->unique('student_id')
            ->each(fn ($r) => $add((int) $r->student_id, 'scored '.round((float) $r->per).'%'.($r->subject_name ? ' in '.$r->subject_name : ''), 'Plan a short revision of the weak topic'));

        $weak = DB::table('pal_concept_mastery')->where('sub_institute_id', $this->tenant)->whereIn('learner_id', $ids)->where('attempts', '>=', 3)->where('p_mastery', '<', 0.4)
            ->selectRaw('learner_id, COUNT(*) as n')->groupBy('learner_id')->get();
        $weak->each(fn ($r) => $add((int) $r->learner_id, "low mastery on {$r->n} concept".($r->n == 1 ? '' : 's'), 'Assign targeted practice on the weak concept'));

        if (! $reasons) {
            return ['total' => 0, 'items' => []];
        }
        uasort($reasons, fn ($a, $b) => count($b) <=> count($a));
        $top = array_slice(array_keys($reasons), 0, 8);
        $people = DB::table('tblstudent as s')
            ->leftJoin('tblstudent_enrollment as se', function ($j) {
                $j->on('se.student_id', '=', 's.id')->where('se.syear', $this->syear)->whereNull('se.end_date');
            })
            ->leftJoin('standard as st', 'st.id', '=', 'se.standard_id')->leftJoin('division as d', 'd.id', '=', 'se.section_id')
            ->whereIn('s.id', $top)
            ->selectRaw("s.id, CONCAT_WS(' ', s.first_name, s.last_name) as name, CONCAT_WS(' - ', st.name, d.name) as class_name, NULLIF(TRIM(COALESCE(s.mobile, s.mother_mobile, s.student_mobile, '')), '') as phone")->get()->keyBy('id');

        return [
            'total' => count($reasons),
            'items' => collect($top)->map(function ($id) use ($reasons, $steps, $people) {
                $p = $people[$id] ?? null;
                $why = $reasons[$id];

                return [
                    'id' => 's'.$id,
                    'title' => trim(($p->name ?? 'Student').($p && $p->class_name ? ' · '.$p->class_name : '')),
                    'why' => ucfirst(implode('; ', $why)).'.',
                    'next' => implode('. ', array_slice(array_keys($steps[$id]), 0, 2)).'.',
                    'severity' => count($why) >= 3 ? 'high' : 'medium',
                    'chips' => $why,
                    'phone' => $p->phone ?? null,
                ];
            })->values()->all(),
        ];
    }

    /** Is the teaching helping? Homework completion and attendance in the teacher's classes, now against the period before. */
    private function w_teaching_impact(): array
    {
        $hw = function (Carbon $from, Carbon $to) {
            $r = $this->scopeClasses(DB::table('homework as h')->where('h.sub_institute_id', $this->tenant)->where('h.syear', $this->syear), 'h.standard_id', 'h.division_id')
                ->whereDate('h.date', '>=', $from->toDateString())->whereDate('h.date', '<=', $to->toDateString())
                ->selectRaw("COUNT(*) as n, SUM(h.completion_status = 'Y') as done")->first();

            return ['rate' => $r && (int) $r->n > 0 ? round($r->done / $r->n * 100, 1) : null, 'n' => (int) ($r->n ?? 0)];
        };
        $today = Carbon::today();

        $dates = DB::table('attendance_student')->where('sub_institute_id', $this->tenant)->where('syear', $this->syear)
            ->when(true, fn ($q) => $this->scopeClasses($q, 'standard_id', 'section_id'))
            ->whereDate('attendance_date', '<=', $this->today)->selectRaw('DATE(attendance_date) as d')->distinct()->orderByDesc('d')->limit(28)->pluck('d')->all();
        $att = function (array $d) {
            if (! $d) {
                return null;
            }
            $r = DB::table('attendance_student')->where('sub_institute_id', $this->tenant)->where('syear', $this->syear)
                ->when(true, fn ($q) => $this->scopeClasses($q, 'standard_id', 'section_id'))
                ->whereIn(DB::raw('DATE(attendance_date)'), $d)->selectRaw("SUM(attendance_code = 'P') as p, SUM(attendance_code = 'A') as a")->first();
            $t = (int) $r->p + (int) $r->a;

            return $t > 0 ? round($r->p / $t * 100, 1) : null;
        };

        return [
            'homework' => ['current' => $hw($today->copy()->subDays(29), $today), 'previous' => $hw($today->copy()->subDays(59), $today->copy()->subDays(30))],
            'attendance' => ['current' => $att(array_slice($dates, 0, 14)), 'previous' => $att(array_slice($dates, 14, 14))],
        ];
    }

    /** PAL mastery across the teacher's students: how many sit in each band, and the concepts they find hardest. */
    private function w_class_mastery(): array
    {
        $ids = $this->enrolled()->pluck('ts.id')->all();
        $empty = ['learners' => 0, 'bands' => ['stream' => 0, 'mountain_approach' => 0, 'mountain_mastery' => 0, 'sky_mastery' => 0], 'hardest' => []];
        if (! $ids) {
            return $empty;
        }
        $base = DB::table('pal_concept_mastery')->where('sub_institute_id', $this->tenant)->whereIn('learner_id', $ids);
        $bands = (clone $base)->selectRaw('band, COUNT(*) as n')->groupBy('band')->pluck('n', 'band');
        $hard = (clone $base)->selectRaw('concept_ref_id, AVG(p_mastery) as avg_p, COUNT(DISTINCT learner_id) as learners')->groupBy('concept_ref_id')->orderBy('avg_p')->limit(5)->get();
        $names = $this->conceptNames($hard->pluck('concept_ref_id')->all());

        return [
            'learners' => (int) (clone $base)->distinct()->count('learner_id'),
            'bands' => array_merge($empty['bands'], $bands->map(fn ($v) => (int) $v)->all()),
            'hardest' => $hard->map(fn ($r) => ['concept' => (string) ($names[$r->concept_ref_id] ?? 'Concept '.$r->concept_ref_id), 'mastery' => round((float) $r->avg_p * 100), 'students' => (int) $r->learners])->all(),
        ];
    }

    /* ---- student */

    private function w_my_work(): array
    {
        $today = $this->today;
        $hw = DB::table('homework as h')->leftJoin('subject as s', 's.id', '=', 'h.subject_id')
            ->where('h.student_id', $this->userId)->where('h.sub_institute_id', $this->tenant)->where('h.completion_status', 'N');
        $as = DB::table('lms_assignment as a')->leftJoin('subject as s', 's.id', '=', 'a.subject_id')
            ->where('a.student_id', $this->userId)->where('a.sub_institute_id', $this->tenant)->where('a.syear', $this->syear)->where('a.student_submission_status', 'N');

        $items = (clone $hw)->selectRaw("h.id, h.title, s.subject_name as subject, h.submission_date as due, 'Homework' as kind")->orderByRaw('h.submission_date IS NULL, h.submission_date')->limit(8)->get()
            ->concat((clone $as)->selectRaw("a.id, a.title, s.subject_name as subject, a.submission_date as due, 'Assignment' as kind")->orderByRaw('a.submission_date IS NULL, a.submission_date')->limit(8)->get())
            ->map(fn ($r) => [
                'id' => $r->kind[0].$r->id, 'title' => (string) $r->title, 'detail' => trim($r->kind.($r->subject ? ' · '.$r->subject : '')),
                'date' => $r->due ? substr((string) $r->due, 0, 10) : null, 'overdue' => $r->due && substr((string) $r->due, 0, 10) < $today,
            ])->sortBy(fn ($i) => $i['date'] ?? '9999')->values();

        $homework = (int) (clone $hw)->count();
        $assignments = (int) (clone $as)->count();

        return [
            'homework' => $homework, 'assignments' => $assignments, 'total' => $homework + $assignments,
            'overdue' => (int) (clone $hw)->whereDate('h.submission_date', '<', $today)->count() + (int) (clone $as)->whereDate('a.submission_date', '<', $today)->count(),
            'items' => $items->take(8)->all(),
        ];
    }

    private function w_my_exams(): array
    {
        $e = $this->myEnrolment();
        if (! $e) {
            return ['count' => 0, 'items' => []];
        }
        $q = DB::table('exam_schedule')->where('sub_institute_id', $this->tenant)->where('standard_id', $e->standard_id)
            ->where(fn ($w) => $w->where('division_id', $e->section_id)->orWhereNull('division_id'))->whereDate('date_', '>=', $this->today);

        return [
            'count' => (int) (clone $q)->count(),
            'items' => (clone $q)->selectRaw('id, title, date_')->orderBy('date_')->limit(6)->get()->map(fn ($r) => ['id' => (int) $r->id, 'title' => (string) $r->title, 'detail' => null, 'date' => $r->date_])->all(),
        ];
    }

    /** What the student has mastered, what is in progress, and where they are making mistakes (lowest mastery first). */
    private function w_my_mastery(): array
    {
        $rows = DB::table('pal_concept_mastery')->where('sub_institute_id', $this->tenant)->where('learner_id', $this->userId)->get(['concept_ref_id', 'p_mastery', 'band', 'attempts', 'correct']);
        $mastered = $rows->filter(fn ($r) => in_array($r->band, ['mountain_mastery', 'sky_mastery'], true))->count();
        $needs = $rows->filter(fn ($r) => $r->band === 'stream' || (float) $r->p_mastery < 0.4);
        $weakest = $rows->sortBy('p_mastery')->take(5)->values();
        $names = $this->conceptNames($weakest->pluck('concept_ref_id')->all());

        return [
            'total' => $rows->count(), 'mastered' => $mastered, 'needs_work' => $needs->count(), 'in_progress' => $rows->count() - $mastered - $needs->count(),
            'items' => $weakest->map(fn ($r) => [
                'concept' => (string) ($names[$r->concept_ref_id] ?? 'Concept '.$r->concept_ref_id), 'mastery' => round((float) $r->p_mastery * 100),
                'attempts' => (int) $r->attempts, 'correct' => (int) $r->correct,
            ])->all(),
        ];
    }

    /** The next thing to do: the learning engine's open recommendation, else the weakest concept, else the nearest-due work. */
    private function w_my_next(): ?array
    {
        $rec = DB::table('pal_recommendation_log')->where('learner_id', $this->userId)->where('sub_institute_id', $this->tenant)->whereNull('completed_at')->orderByDesc('shown_at')->first();
        if ($rec) {
            $name = $this->conceptNames([$rec->concept_id])[$rec->concept_id] ?? null;

            return ['kind' => 'learning', 'title' => $name ? 'Practise “'.$name.'”' : 'Continue your recommended activity', 'detail' => $rec->content_format ? ucfirst(str_replace('_', ' ', (string) $rec->content_format)).' · suggested for you' : 'Suggested for you'];
        }
        $weak = $this->w_my_mastery()['items'][0] ?? null;
        if ($weak && $weak['mastery'] < 60) {
            return ['kind' => 'learning', 'title' => 'Revisit “'.$weak['concept'].'”', 'detail' => 'Your mastery is '.$weak['mastery'].'% — a little practice will move it'];
        }
        $work = $this->w_my_work()['items'][0] ?? null;

        return $work ? ['kind' => 'work', 'title' => $work['title'], 'detail' => $work['detail'].($work['date'] ? ' · due '.$work['date'] : '')] : null;
    }

    private function w_my_streak(): array
    {
        $s = DB::table('pal_learner_streaks')->where('learner_id', $this->userId)->first();
        $badges = DB::table('pal_learner_badges as lb')->leftJoin('pal_badges as b', 'b.badge_id', '=', 'lb.badge_id')->where('lb.learner_id', $this->userId)->whereNull('lb.revoked_at')
            ->orderByDesc('lb.awarded_at')->get(['b.name', 'lb.awarded_at']);

        return [
            'current' => (int) ($s->current_streak ?? 0), 'longest' => (int) ($s->longest_streak ?? 0), 'active_days' => (int) ($s->total_active_days ?? 0),
            'last_active' => $s && $s->last_active_date ? substr((string) $s->last_active_date, 0, 10) : null,
            'badges' => $badges->take(5)->map(fn ($b) => ['name' => (string) ($b->name ?: 'Badge'), 'date' => substr((string) $b->awarded_at, 0, 10)])->all(),
            'badge_total' => $badges->count(),
        ];
    }

    /** Plain-language things worth the student's attention, each with what to do about it. Every line comes from their own record. */
    private function w_my_insights(): array
    {
        $out = [];
        $work = $this->w_my_work();
        if ($work['overdue'] > 0) {
            $out[] = ['id' => 'overdue', 'title' => $work['overdue'].' piece'.($work['overdue'] == 1 ? '' : 's').' of work past the due date', 'why' => 'Late work builds up and makes the next topic harder to follow.', 'next' => 'Do the oldest one first, and ask your teacher if you are stuck.', 'severity' => 'high', 'chips' => ["{$work['homework']} homework", "{$work['assignments']} assignments"], 'phone' => null];
        }
        $m = $this->w_my_mastery();
        if ($m['needs_work'] > 0 && ! empty($m['items'])) {
            $w = $m['items'][0];
            $out[] = ['id' => 'weak', 'title' => 'You are finding “'.$w['concept'].'” hard', 'why' => 'You have answered '.$w['correct'].' of '.$w['attempts'].' questions on it correctly, so your mastery is '.$w['mastery'].'%.', 'next' => 'Try the practice activity for this concept, then check again tomorrow.', 'severity' => 'medium', 'chips' => [$m['needs_work'].' concept'.($m['needs_work'] == 1 ? '' : 's').' need work'], 'phone' => null];
        }
        $att = DB::table('attendance_student')->where('student_id', $this->userId)->where('sub_institute_id', $this->tenant)->where('syear', $this->syear)
            ->whereDate('attendance_date', '>=', Carbon::today()->startOfMonth()->toDateString())->selectRaw("SUM(attendance_code = 'P') as p, SUM(attendance_code = 'A') as a")->first();
        $marked = (int) ($att->p ?? 0) + (int) ($att->a ?? 0);
        if ($marked >= 5 && $att->p / $marked < 0.85) {
            $out[] = ['id' => 'attendance', 'title' => 'Your attendance this month is '.round($att->p / $marked * 100).'%', 'why' => 'You have missed '.(int) $att->a.' of '.$marked.' school days this month.', 'next' => 'Try not to miss more days, and catch up on the lessons you missed.', 'severity' => 'medium', 'chips' => [(int) $att->a.' days absent'], 'phone' => null];
        }
        $streak = $this->w_my_streak();
        if ($streak['current'] > 0 && $streak['last_active'] && $streak['last_active'] < Carbon::yesterday()->toDateString()) {
            $out[] = ['id' => 'streak', 'title' => 'Your '.$streak['current'].'-day learning streak is at risk', 'why' => 'You were last active on '.$streak['last_active'].'.', 'next' => 'Do one short activity today to keep it going.', 'severity' => 'low', 'chips' => ['Longest: '.$streak['longest'].' days'], 'phone' => null];
        }
        $soon = DB::table('exam_schedule')->where('sub_institute_id', $this->tenant)->where('standard_id', $this->myEnrolment()->standard_id ?? 0)->whereDate('date_', '>=', $this->today)->whereDate('date_', '<=', Carbon::today()->addDays(7)->toDateString())->count();
        if ($soon > 0) {
            $out[] = ['id' => 'exams', 'title' => $soon.' exam'.($soon == 1 ? '' : 's').' in the next 7 days', 'why' => 'Revising a little each day works better than the night before.', 'next' => 'Open your weakest concepts and practise them first.', 'severity' => 'medium', 'chips' => ['Next 7 days'], 'phone' => null];
        }

        return ['total' => count($out), 'items' => $out];
    }

    /** Progress for a teacher or a student, against the things that are theirs. */
    private function personalProgress(): array
    {
        $items = [];
        $add = function (string $key, string $label, ?float $value, string $detail) use (&$items) {
            if ($value !== null) {
                $items[] = ['key' => $key, 'label' => $label, 'value' => round(max(0, min(100, $value)), 1), 'detail' => $detail];
            }
        };
        if ($this->role === 'student') {
            $att = DB::table('attendance_student')->where('student_id', $this->userId)->where('sub_institute_id', $this->tenant)->where('syear', $this->syear)
                ->whereDate('attendance_date', '>=', Carbon::today()->startOfMonth()->toDateString())->selectRaw("SUM(attendance_code = 'P') as p, SUM(attendance_code = 'A') as a")->first();
            $marked = (int) ($att->p ?? 0) + (int) ($att->a ?? 0);
            $add('attendance', 'Attendance this month', $marked > 0 ? $att->p / $marked * 100 : null, (int) ($att->p ?? 0).' of '.$marked.' days present');
            $hw = DB::table('homework')->where('student_id', $this->userId)->where('sub_institute_id', $this->tenant)->selectRaw("COUNT(*) as n, SUM(completion_status = 'Y') as done")->first();
            $add('homework', 'Homework completed', (int) $hw->n > 0 ? $hw->done / $hw->n * 100 : null, (int) $hw->done.' of '.(int) $hw->n);
            $as = DB::table('lms_assignment')->where('student_id', $this->userId)->where('sub_institute_id', $this->tenant)->where('syear', $this->syear)->selectRaw("COUNT(*) as n, SUM(student_submission_status = 'Y') as done")->first();
            $add('assignments', 'Assignments submitted', (int) $as->n > 0 ? $as->done / $as->n * 100 : null, (int) $as->done.' of '.(int) $as->n);
            $m = $this->w_my_mastery();
            $add('mastery', 'Concepts mastered', $m['total'] > 0 ? $m['mastered'] / $m['total'] * 100 : null, $m['mastered'].' of '.$m['total'].' concepts');

            return $items;
        }
        $impact = $this->w_teaching_impact();
        $add('homework', 'Homework completed in my classes, last 30 days', $impact['homework']['current']['rate'], $impact['homework']['current']['n'].' assignments');
        $add('attendance', 'Attendance in my classes, last 14 days', $impact['attendance']['current'], 'Marked days only');
        $g = DB::table('lms_assignment')->where('teacher_id', $this->userId)->where('sub_institute_id', $this->tenant)->where('syear', $this->syear)->where('student_submission_status', 'Y')
            ->selectRaw("COUNT(*) as n, SUM(teacher_submission_status = 'Y') as done")->first();
        $add('graded', 'Submitted assignments I have graded', (int) $g->n > 0 ? $g->done / $g->n * 100 : null, (int) $g->done.' of '.(int) $g->n);
        $tasks = DB::table('task')->where('sub_institute_id', $this->tenant)->where('TASK_ALLOCATED_TO', $this->userId);
        $total = (int) (clone $tasks)->count();
        $add('tasks', 'My tasks completed', $total > 0 ? (clone $tasks)->whereIn('STATUS', ['COMPLETED', 'COMPLETE'])->count() / $total * 100 : null, (clone $tasks)->whereIn('STATUS', ['COMPLETED', 'COMPLETE'])->count().' of '.$total);

        return $items;
    }

    private function personalHistory(): array
    {
        if ($this->role === 'student') {
            $badges = DB::table('pal_learner_badges as lb')->leftJoin('pal_badges as b', 'b.badge_id', '=', 'lb.badge_id')->where('lb.learner_id', $this->userId)->whereNull('lb.revoked_at')
                ->orderByDesc('lb.awarded_at')->limit(5)->get(['b.name', 'lb.awarded_at', 'lb.badge_id'])
                ->map(fn ($b) => ['id' => 'b'.$b->badge_id, 'kind' => 'badge', 'title' => (string) ($b->name ?: 'Badge earned'), 'detail' => 'Badge earned', 'status' => 'earned', 'result' => null, 'date' => substr((string) $b->awarded_at, 0, 10)]);
            $done = DB::table('homework as h')->leftJoin('subject as s', 's.id', '=', 'h.subject_id')->where('h.student_id', $this->userId)->where('h.sub_institute_id', $this->tenant)->where('h.completion_status', 'Y')
                ->orderByDesc('h.date')->limit(4)->get(['h.id', 'h.title', 's.subject_name', 'h.date'])
                ->map(fn ($h) => ['id' => 'h'.$h->id, 'kind' => 'task', 'title' => (string) $h->title, 'detail' => 'Homework completed'.($h->subject_name ? ' · '.$h->subject_name : ''), 'status' => 'completed', 'result' => null, 'date' => substr((string) $h->date, 0, 10)]);

            return $badges->concat($done)->sortByDesc('date')->values()->take(8)->all();
        }

        return DB::table('task')->where('sub_institute_id', $this->tenant)->where('TASK_ALLOCATED_TO', $this->userId)->whereIn('STATUS', ['COMPLETED', 'COMPLETE'])
            ->orderByDesc('TASK_DATE')->limit(8)->selectRaw('ID as id, TASK_TITLE as title, TASK_DATE as date')->get()
            ->map(fn ($r) => ['id' => 't'.$r->id, 'kind' => 'task', 'title' => (string) $r->title, 'detail' => 'Task completed', 'status' => 'completed', 'result' => null, 'date' => substr((string) $r->date, 0, 10)])->all();
    }

    /* ----------------------------------------------- intelligence: signals, loop, progress, history */

    private const SEVERITY_RANK = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];

    /** "vivek_erp.fees_collect" -> "Fees collect". Table names are a poor label, but a truthful one. */
    private function humanise(string $text): string
    {
        $text = str_contains($text, '.') ? substr($text, strrpos($text, '.') + 1) : $text;
        $text = ucfirst(trim(str_replace(['_', '-'], ' ', preg_replace('/^tbl/i', '', $text))));

        return $text === '' ? 'Unknown' : $text;
    }

    /**
     * The signals this role should see, most important first, each carrying what the stakeholder asks of it:
     * what was detected, why it matters, the evidence and confidence behind it, what is recommended, who
     * decided, and what happened afterwards. Everything comes from the Enterprise Brain's own records
     * (signal → case → reasoning → recommendation → decision → outcome); nothing is written here.
     * Rows seeded as demo data (ids starting "demo-") are left out everywhere: a dashboard must not present
     * a sample decision as something management actually decided.
     */
    private function w_signals(): array
    {
        $rule = config("role_dashboards.signals.{$this->role}");
        if (! $rule) {
            return ['total' => 0, 'by_severity' => [], 'items' => []];
        }

        $minRank = self::SEVERITY_RANK[$rule['min_severity'] ?? 'low'] ?? 1;
        $severities = array_keys(array_filter(self::SEVERITY_RANK, fn ($r) => $r >= $minRank));
        $tenant = (string) $this->tenant;

        $base = DB::table('hpbrain_signals')->where('tenant_id', $tenant)->whereIn('status', ['new', 'reasoned'])->whereIn('severity', $severities)
            ->when(! empty($rule['topics']), function ($q) use ($rule) {
                $q->where(function ($w) use ($rule) {
                    foreach ($rule['topics'] as $topic) {
                        $w->orWhere('classification', 'like', '%'.$topic.'%');
                    }
                });
            });

        $bySeverity = (clone $base)->selectRaw('severity, COUNT(*) as n')->groupBy('severity')->pluck('n', 'severity')->map(fn ($n) => (int) $n)->all();
        $signals = (clone $base)->orderByRaw("FIELD(severity, 'critical', 'high', 'medium', 'low')")->orderByDesc('confidence')->orderByDesc('created_date')->limit(40)->get();
        if ($signals->isEmpty()) {
            return ['total' => 0, 'by_severity' => $bySeverity, 'items' => []];
        }

        $ids = $signals->pluck('id')->all();
        $steps = DB::table('hpbrain_reasoning_steps')->where('tenant_id', $tenant)->whereIn('signal_id', $ids)->orderBy('step_order')->get(['id', 'signal_id', 'description']);
        $recs = DB::table('hpbrain_recommendations')->where('tenant_id', $tenant)->where('id', 'not like', 'demo-%')->whereIn('reasoning_step_id', $steps->pluck('id')->all())
            ->get(['id', 'reasoning_step_id', 'title', 'description', 'status', 'urgency', 'priority']);
        $decisions = DB::table('hpbrain_decisions')->where('tenant_id', $tenant)->where('id', 'not like', 'demo-%')->whereIn('recommendation_id', $recs->pluck('id')->all())
            ->get(['id', 'recommendation_id', 'status', 'decided_by', 'approved_by', 'approval_note', 'rationale', 'created_date']);
        $outcomes = DB::table('hpbrain_outcomes')->where('tenant_id', $tenant)->where('id', 'not like', 'demo-%')->whereIn('decision_id', $decisions->pluck('id')->all())->get(['decision_id', 'result', 'feedback']);
        $evidence = DB::table('hpbrain_evidence')->where('tenant_id', $tenant)->whereIn('signal_id', $ids)->selectRaw('signal_id, COUNT(*) as n')->groupBy('signal_id')->pluck('n', 'signal_id');
        $people = DB::table('tbluser')->whereIn('id', $decisions->pluck('decided_by')->merge($decisions->pluck('approved_by'))->filter(fn ($v) => is_numeric($v))->unique()->all())
            ->selectRaw("id, CONCAT_WS(' ', first_name, last_name) as name")->pluck('name', 'id');

        // A follow-up is a Task Management task whose description carries "[signal:<id>]".
        $followUps = collect();
        if ($ids) {
            $followUps = DB::table('task as t')->leftJoin('tbluser as u', 'u.id', '=', 't.TASK_ALLOCATED_TO')
                ->where('t.sub_institute_id', $this->tenant)->whereNull('t.deleted_at')
                ->where(function ($w) use ($ids) {
                    foreach ($ids as $id) {
                        $w->orWhere('t.TASK_DESCRIPTION', 'like', '%[signal:'.$id.']%');
                    }
                })
                ->selectRaw("t.ID as id, t.TASK_DESCRIPTION as d, t.TASK_DATE as due, t.STATUS as status, CONCAT_WS(' ', u.first_name, u.last_name) as owner")->orderByDesc('t.ID')->get();
        }
        $stepsBySignal = $steps->groupBy('signal_id');
        $recByStep = $recs->keyBy('reasoning_step_id');
        $decisionByRec = $decisions->sortByDesc('created_date')->keyBy('recommendation_id');
        $outcomeByDecision = $outcomes->keyBy('decision_id');

        $items = $signals->map(function ($s) use ($stepsBySignal, $recByStep, $decisionByRec, $outcomeByDecision, $evidence, $people, $followUps) {
            $meta = json_decode((string) $s->metadata, true) ?: [];
            $steps = $stepsBySignal->get($s->id, collect());
            $rec = null;
            foreach ($steps as $step) {
                if ($recByStep->has($step->id)) {
                    $rec = $recByStep->get($step->id);
                    break;
                }
            }
            $decision = $rec ? $decisionByRec->get($rec->id) : null;
            $outcome = $decision ? $outcomeByDecision->get($decision->id) : null;

            // "Why: … Basis: …" is how the Brain writes its reason; fall back to the reasoning conclusion.
            $why = null;
            if ($rec && preg_match('/Why:\s*(.+?)(?:\n\s*\n|Basis:|$)/s', (string) $rec->description, $m)) {
                $why = trim($m[1]);
            }
            $why ??= $steps->isNotEmpty() ? trim((string) $steps->last()->description) : null;

            $handled = $decision && $decision->status === 'approved';
            $score = (self::SEVERITY_RANK[$s->severity] ?? 1) * 100 + (float) $s->confidence * 10 + ($s->status === 'new' ? 5 : 0)
                + ($rec && $rec->status === 'pending' ? 8 : 0) - ($handled ? 40 : 0);
            $who = $decision ? ($people[(int) ($decision->approved_by ?: $decision->decided_by)] ?? null) : null;

            return [
                'score' => $score,
                'id' => (string) $s->id,
                'title' => (string) ($meta['title'] ?? $this->humanise((string) $s->rule_key)),
                'topic' => $this->humanise((string) $s->classification),
                'source' => $this->humanise((string) $s->source),
                'severity' => (string) $s->severity,
                'confidence' => round((float) $s->confidence, 2),
                'raised' => substr((string) $s->created_date, 0, 10),
                'affected' => isset($meta['affectedCount']) ? (int) $meta['affectedCount'] : null,
                'total' => isset($meta['totalCount']) ? (int) $meta['totalCount'] : null,
                'unit' => isset($meta['unit']) ? (string) $meta['unit'] : null,
                'evidence' => (int) ($evidence[$s->id] ?? 0),
                'why' => $why ? mb_substr($why, 0, 280) : null,
                'recommendation' => $rec ? ['title' => (string) $rec->title, 'status' => (string) $rec->status, 'urgency' => (string) $rec->urgency] : null,
                'decision' => $decision ? ['status' => (string) $decision->status, 'by' => $who, 'date' => substr((string) $decision->created_date, 0, 10), 'note' => $decision->approval_note ?: $decision->rationale] : null,
                'outcome' => $outcome ? ['result' => (string) $outcome->result, 'feedback' => $outcome->feedback] : null,
                'follow_up' => ($f = $followUps->first(fn ($t) => str_contains((string) $t->d, '[signal:'.$s->id.']')))
                    ? ['task_id' => (int) $f->id, 'owner' => trim((string) $f->owner) ?: null, 'due' => $f->due ? substr((string) $f->due, 0, 10) : null, 'status' => (string) $f->status]
                    : null,
            ];
        })->sortByDesc('score')->values()->take(6)->map(function ($i) {
            unset($i['score']);

            return $i;
        })->all();

        return ['total' => (int) array_sum($bySeverity), 'by_severity' => $bySeverity, 'items' => $items];
    }

    /** Where the Brain's loop stands across the school: noticed, recommended, decided, and what came of it. */
    private function w_intelligence_loop(): array
    {
        $t = (string) $this->tenant;
        $recs = DB::table('hpbrain_recommendations')->where('tenant_id', $t)->where('id', 'not like', 'demo-%')->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
        $outcomes = DB::table('hpbrain_outcomes')->where('tenant_id', $t)->where('id', 'not like', 'demo-%')->selectRaw('result, COUNT(*) as n')->groupBy('result')->pluck('n', 'result');

        return [
            'signals' => (int) DB::table('hpbrain_signals')->where('tenant_id', $t)->whereIn('status', ['new', 'reasoned'])->count(),
            'recommendations' => (int) $recs->sum(),
            'awaiting_decision' => (int) ($recs['pending'] ?? 0),
            'decided' => (int) DB::table('hpbrain_decisions')->where('tenant_id', $t)->where('id', 'not like', 'demo-%')->count(),
            'improved' => (int) ($outcomes['improved'] ?? 0),
            'partial' => (int) ($outcomes['partial'] ?? 0),
            'worsened' => (int) (($outcomes['worsened'] ?? 0) + ($outcomes['no_change'] ?? 0)),
        ];
    }

    /**
     * Progress against the things this role is responsible for, each as a plain ratio 0–100 with the numbers
     * behind it. A measure whose source holds nothing yet is left out rather than shown as 0%.
     */
    private function w_progress(): array
    {
        if (in_array($this->role, ['teacher', 'student'], true)) {
            return $this->personalProgress();
        }

        $role = $this->role;
        $lead = ['admin', 'assistant_admin', 'principal', 'vice_principal', 'trustee'];
        $in = fn (array $roles) => in_array($role, $roles, true);
        $items = [];
        $add = function (string $key, string $label, ?float $value, string $detail) use (&$items) {
            if ($value !== null) {
                $items[] = ['key' => $key, 'label' => $label, 'value' => round(max(0, min(100, $value)), 1), 'detail' => $detail];
            }
        };

        if ($in([...$lead, 'counsellor'])) {
            $a = $this->w_attendance_today();
            $marked = $a['present'] + $a['absent'];
            $add('attendance', 'Attendance today', $marked > 0 ? $a['present'] / $marked * 100 : null, "{$a['present']} of {$marked} marked students present");
        }
        if ($in(self::FEES_ROLES)) {
            $s = json_decode(app(FeesDashboardApiController::class)->summary($this->internalRequest(['sub_institute_id' => $this->tenant, 'syear' => $this->syear, 'type' => 'JSON']))->getContent(), true)['summary'] ?? null;
            if ($s && ($s['demand_amount'] ?? 0) > 0) {
                $add('fees', 'Fees collected this year', (float) $s['collection_rate'], '₹'.number_format($s['collected_amount']).' of ₹'.number_format($s['demand_amount']));
            }
        }
        if ($in([...$lead, 'clerk', 'assistant_clerk'])) {
            $f = $this->w_admission_funnel();
            $add('admissions', 'Enquiries that became admissions', $f['enquiries'] > 0 ? $f['admitted'] / $f['enquiries'] * 100 : null, "{$f['admitted']} of {$f['enquiries']} enquiries");
        }
        if ($in([...$lead, 'counsellor'])) {
            $total = (int) DB::table('parent_communication')->where('sub_institute_id', $this->tenant)->count();
            $pending = $this->w_parent_msgs_pending();
            $add('parents', 'Parent messages answered', $total > 0 ? ($total - $pending) / $total * 100 : null, ($total - $pending).' of '.$total.' messages');
        }
        if ($in(['admin', 'principal', 'vice_principal'])) {
            $h = $this->w_homework_rate();
            $add('homework', 'Homework completed, last 30 days', $h['overall'], ($h['total'] > 0 ? $h['total'] : 0).' assignments');
        }
        $tasks = DB::table('task')->where('sub_institute_id', $this->tenant)->when(! $in($lead), fn ($q) => $q->where('TASK_ALLOCATED_TO', $this->userId));
        $taskTotal = (int) (clone $tasks)->count();
        $taskDone = (int) (clone $tasks)->whereIn('STATUS', ['COMPLETED', 'COMPLETE'])->count();
        $add('tasks', $in($lead) ? 'Tasks completed' : 'My tasks completed', $taskTotal > 0 ? $taskDone / $taskTotal * 100 : null, "{$taskDone} of {$taskTotal} tasks");
        if ($in($lead)) {
            $loop = $this->w_intelligence_loop();
            $decided = (int) DB::table('hpbrain_decisions')->where('tenant_id', (string) $this->tenant)->where('id', 'not like', 'demo-%')->count();
            $add('decisions', 'Recommendations decided', $loop['recommendations'] > 0 ? $decided / $loop['recommendations'] * 100 : null, "{$decided} of {$loop['recommendations']} recommendations");
        }

        return $items;
    }

    /** What was decided and what came of it, newest first, with finished tasks alongside. */
    private function w_history(): array
    {
        if (in_array($this->role, ['teacher', 'student'], true)) {
            return $this->personalHistory();
        }

        $t = (string) $this->tenant;
        $decisions = DB::table('hpbrain_decisions as d')
            ->leftJoin('hpbrain_recommendations as r', 'r.id', '=', 'd.recommendation_id')
            ->leftJoin('hpbrain_outcomes as o', 'o.decision_id', '=', 'd.id')
            ->where('d.tenant_id', $t)->where('d.id', 'not like', 'demo-%')->orderByDesc('d.created_date')->limit(6)
            ->selectRaw('d.id, r.title, d.status, d.decided_by, d.approved_by, d.approval_note, d.rationale, d.created_date, o.result, o.feedback')->get();
        $people = DB::table('tbluser')->whereIn('id', $decisions->pluck('decided_by')->merge($decisions->pluck('approved_by'))->filter(fn ($v) => is_numeric($v))->unique()->all())
            ->selectRaw("id, CONCAT_WS(' ', first_name, last_name) as name")->pluck('name', 'id');

        $fromDecisions = $decisions->map(fn ($d) => [
            'id' => (string) $d->id, 'kind' => 'decision',
            'title' => (string) ($d->title ?: 'Decision'),
            'detail' => trim(($people[(int) ($d->approved_by ?: $d->decided_by)] ?? 'Management').' · '.($d->approval_note ?: $d->rationale ?: ucfirst((string) $d->status))),
            'status' => (string) $d->status, 'result' => $d->result, 'date' => substr((string) $d->created_date, 0, 10),
        ]);

        $fromTasks = DB::table('task')->where('sub_institute_id', $this->tenant)->whereIn('STATUS', ['COMPLETED', 'COMPLETE'])
            ->orderByDesc('TASK_DATE')->limit(4)->selectRaw('ID as id, TASK_TITLE as title, TASK_DATE as date')->get()
            ->map(fn ($r) => ['id' => 't'.$r->id, 'kind' => 'task', 'title' => (string) $r->title, 'detail' => 'Task completed', 'status' => 'completed', 'result' => null, 'date' => substr((string) $r->date, 0, 10)]);

        return $fromDecisions->concat($fromTasks)->sortByDesc('date')->values()->take(8)->all();
    }

    /* ------------------------------------------------ patterns that need a person to act */

    /** Classes for the filter. Only roles that look at students get the list. */
    private function standardsList(string $role): array
    {
        if (! in_array($role, ['admin', 'assistant_admin', 'principal', 'vice_principal', 'counsellor', 'exam_department'], true)) {
            return [];
        }

        return DB::table('standard')->where('sub_institute_id', $this->tenant)->orderBy('sort_order')->get(['id', 'name'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'name' => (string) $r->name])->all();
    }

    /**
     * Students absent on at least 5 of the last 10 days attendance was marked. A single absence is noise;
     * this is the pattern a counsellor or class teacher should ring about.
     */
    /* ---- breakdowns that exist only to be asked for ("more like this"): counts and sums, never names ---- */

    /** Rows per month for the last 6 months, gaps filled with 0 once the school recorded anything at all in the window. */
    private function monthRows($query, string $column): array
    {
        $from = Carbon::today()->startOfMonth()->subMonthsNoOverflow(5);
        $found = (clone $query)->whereDate($column, '>=', $from->toDateString())->whereDate($column, '<=', $this->today)
            ->selectRaw("DATE_FORMAT({$column}, '%Y-%m') as k, COUNT(*) as v")->groupBy('k')->pluck('v', 'k')->all();
        if (! $found) {
            return [];
        }
        $rows = [];
        for ($c = $from->copy(); $c->lte(Carbon::today()); $c->addMonthNoOverflow()) {
            $rows[] = ['label' => $c->format('M Y'), 'value' => (int) ($found[$c->format('Y-m')] ?? 0)];
        }

        return $rows;
    }

    private function labelRows($rows): array
    {
        return collect($rows)->map(fn ($r) => ['label' => (string) $r->label, 'value' => round((float) $r->v, 2)])->values()->all();
    }

    private function w_fees_by_mode(): ?array
    {
        return ['rows' => $this->labelRows(DB::table('fees_collect')->where(['sub_institute_id' => $this->tenant, 'syear' => $this->syear, 'is_deleted' => 'N'])
            ->selectRaw("COALESCE(NULLIF(TRIM(payment_mode), ''), 'Not recorded') as label, SUM(amount) as v")->groupBy('label')->orderByDesc('v')->limit(8)->get())];
    }

    private function w_fees_by_class(): ?array
    {
        return ['rows' => $this->labelRows(DB::table('fees_collect as f')->leftJoin('standard as s', 's.id', '=', 'f.standard_id')
            ->where(['f.sub_institute_id' => $this->tenant, 'f.syear' => $this->syear, 'f.is_deleted' => 'N'])
            ->selectRaw("COALESCE(s.name, 'Class not recorded') as label, SUM(f.amount) as v")->groupBy('s.id', 's.name')->orderByDesc('v')->limit(12)->get())];
    }

    private function w_enquiries_by_month(): ?array
    {
        return ['rows' => $this->monthRows(DB::table('admission_enquiry')->where('sub_institute_id', $this->tenant)->whereNull('deleted_at'), 'created_on')];
    }

    private function w_enquiries_by_source(): ?array
    {
        return ['rows' => $this->labelRows(DB::table('admission_enquiry')->where(['sub_institute_id' => $this->tenant, 'syear' => $this->syear])->whereNull('deleted_at')
            ->selectRaw("COALESCE(NULLIF(TRIM(source_of_enquiry), ''), 'Not recorded') as label, COUNT(*) as v")->groupBy('label')->orderByDesc('v')->limit(10)->get())];
    }

    private function w_enquiries_by_class(): ?array
    {
        return ['rows' => $this->labelRows(DB::table('admission_enquiry as e')
            ->leftJoin('standard as s', function ($j) {
                $j->on('s.id', '=', 'e.admission_standard')->where('s.sub_institute_id', '=', $this->tenant);
            })
            ->where(['e.sub_institute_id' => $this->tenant, 'e.syear' => $this->syear])->whereNull('e.deleted_at')
            ->selectRaw("COALESCE(s.name, NULLIF(TRIM(e.admission_standard), ''), 'Not recorded') as label, COUNT(*) as v")->groupBy('label')->orderByDesc('v')->limit(12)->get())];
    }

    private function w_students_joined_by_month(): ?array
    {
        return ['rows' => $this->monthRows(DB::table('tblstudent_enrollment')->where(['sub_institute_id' => $this->tenant, 'syear' => $this->syear]), 'start_date')];
    }

    private function w_staff_by_role(): ?array
    {
        return ['rows' => $this->labelRows(DB::table('tbluser as u')->leftJoin('tbluserprofilemaster as p', 'p.id', '=', 'u.user_profile_id')
            ->where(['u.sub_institute_id' => $this->tenant, 'u.status' => '1'])
            ->selectRaw("COALESCE(p.name, 'No role recorded') as label, COUNT(*) as v")->groupBy('p.id', 'p.name')->orderByDesc('v')->limit(12)->get())];
    }

    private function w_leaves_by_type(): ?array
    {
        return ['rows' => $this->labelRows(DB::table('hrms_emp_leaves as l')->leftJoin('hrms_leave_types as t', 't.id', '=', 'l.leave_type_id')
            ->where('l.sub_institute_id', $this->tenant)->whereNull('l.deleted_at')->where('l.status', '!=', 'cancelled')
            ->whereDate('l.from_date', '>=', Carbon::today()->subDays(365)->toDateString())
            ->selectRaw("COALESCE(t.leave_type, 'Other') as label, COUNT(*) as v")->groupBy('t.id', 't.leave_type')->orderByDesc('v')->limit(10)->get())];
    }

    /** One homework given to a class on a day counts once, however many students it was handed to. */
    private function homeworkDistinct(): string
    {
        return "COUNT(DISTINCT CONCAT_WS('|', h.standard_id, h.division_id, h.subject_id, h.title, h.`date`))";
    }

    private function w_homework_by_month(): ?array
    {
        $from = Carbon::today()->startOfMonth()->subMonthsNoOverflow(5);
        $found = DB::table('homework as h')->where('h.sub_institute_id', $this->tenant)->whereDate('h.date', '>=', $from->toDateString())->whereDate('h.date', '<=', $this->today)
            ->selectRaw("DATE_FORMAT(h.`date`, '%Y-%m') as k, ".$this->homeworkDistinct().' as v')->groupBy('k')->pluck('v', 'k')->all();
        $rows = [];
        if ($found) {
            for ($c = $from->copy(); $c->lte(Carbon::today()); $c->addMonthNoOverflow()) {
                $rows[] = ['label' => $c->format('M Y'), 'value' => (int) ($found[$c->format('Y-m')] ?? 0)];
            }
        }

        return ['rows' => $rows];
    }

    private function w_homework_by_class(): ?array
    {
        return ['rows' => $this->labelRows(DB::table('homework as h')->leftJoin('standard as s', 's.id', '=', 'h.standard_id')
            ->where(['h.sub_institute_id' => $this->tenant, 'h.syear' => $this->syear])
            ->selectRaw("COALESCE(s.name, 'Class not recorded') as label, ".$this->homeworkDistinct().' as v')->groupBy('s.id', 's.name', 's.sort_order')->orderBy('s.sort_order')->get())];
    }

    private function w_complaints_by_month(): ?array
    {
        return ['rows' => $this->monthRows(DB::table('complaint')->where('SUB_INSTITUTE_ID', $this->tenant), 'DATE')];
    }

    private function w_library_issues_by_month(): ?array
    {
        return ['rows' => $this->monthRows(DB::table('library_book_circulations')->where('sub_institute_id', $this->tenant), 'issued_date')];
    }

    private function w_items_by_category(): ?array
    {
        return ['rows' => $this->labelRows(DB::table('inventory_item_master as m')->leftJoin('inventory_item_category_master as c', 'c.id', '=', 'm.category_id')
            ->where('m.sub_institute_id', $this->tenant)
            ->selectRaw("COALESCE(c.title, 'No category') as label, COUNT(*) as v")->groupBy('c.id', 'c.title')->orderByDesc('v')->limit(12)->get())];
    }
    /** Absent students on the latest marked day, counted per class. Numbers only, no names. */
    private function w_absent_by_class(): ?array
    {
        $day = $this->latestMarkedDay();
        if (! $day) {
            return null;
        }
        $rows = DB::table('attendance_student as a')
            ->leftJoin('standard as st', 'st.id', '=', 'a.standard_id')
            ->where('a.sub_institute_id', $this->tenant)->where('a.syear', $this->syear)
            ->whereDate('a.attendance_date', $day)
            ->selectRaw("COALESCE(st.name, 'Class not recorded') as label, SUM(a.attendance_code = 'A') as absent, COUNT(*) as marked")
            ->groupBy('st.id', 'st.name', 'st.sort_order')->orderByRaw('st.sort_order IS NULL, st.sort_order')->get()
            ->filter(fn ($r) => (int) $r->absent > 0)
            ->map(fn ($r) => ['label' => (string) $r->label, 'value' => (int) $r->absent, 'marked' => (int) $r->marked])->values()->all();

        return ['date' => $day, 'rows' => $rows];
    }

    /** Students absent on at least 5 of the last 10 marked days, counted per class. Numbers only, no names. */
    private function w_chronic_by_class(): ?array
    {
        $days = DB::table('attendance_student')->where('sub_institute_id', $this->tenant)->where('syear', $this->syear)
            ->whereDate('attendance_date', '<=', $this->today)->whereDate('attendance_date', '>=', Carbon::today()->subDays(45)->toDateString())
            ->selectRaw('DATE(attendance_date) as d')->distinct()->orderByDesc('d')->limit(10)->pluck('d')->all();
        $threshold = 5;
        if (count($days) < $threshold) {
            return ['days' => count($days), 'threshold' => $threshold, 'rows' => []];
        }
        $per = DB::table('attendance_student as a')
            ->where('a.sub_institute_id', $this->tenant)->where('a.syear', $this->syear)
            ->whereIn(DB::raw('DATE(a.attendance_date)'), $days)
            ->selectRaw("a.student_id, MAX(a.standard_id) as standard_id, SUM(a.attendance_code = 'A') as absences")
            ->groupBy('a.student_id')->havingRaw("SUM(a.attendance_code = 'A') >= ?", [$threshold]);
        $rows = DB::query()->fromSub($per, 't')->leftJoin('standard as st', 'st.id', '=', 't.standard_id')
            ->selectRaw("COALESCE(st.name, 'Class not recorded') as label, COUNT(*) as n")
            ->groupBy('st.id', 'st.name', 'st.sort_order')->orderByRaw('st.sort_order IS NULL, st.sort_order')->get()
            ->map(fn ($r) => ['label' => (string) $r->label, 'value' => (int) $r->n])->values()->all();

        return ['days' => count($days), 'threshold' => $threshold, 'rows' => $rows];
    }

    /** Classes that have not marked attendance today, out of the classes that have students. */
    private function w_unmarked_classes(): array
    {
        $classes = DB::table('tblstudent_enrollment as se')
            ->join('standard as s', 's.id', '=', 'se.standard_id')
            ->leftJoin('division as d', 'd.id', '=', 'se.section_id')
            ->where('se.sub_institute_id', $this->tenant)->where('se.syear', $this->syear)->whereNull('se.end_date')
            ->selectRaw("se.standard_id, se.section_id, CONCAT_WS(' - ', s.name, d.name) as label")
            ->groupBy('se.standard_id', 'se.section_id', 's.name', 'd.name', 's.sort_order')->orderBy('s.sort_order')->get();
        $marked = DB::table('attendance_student')->where('sub_institute_id', $this->tenant)->where('syear', $this->syear)->whereDate('attendance_date', $this->today)
            ->selectRaw('DISTINCT standard_id, section_id')->get()->map(fn ($r) => $r->standard_id.'-'.$r->section_id)->all();
        $open = $classes->filter(fn ($c) => ! in_array($c->standard_id.'-'.$c->section_id, $marked, true))->values();

        return ['total' => $open->count(), 'of' => $classes->count(), 'items' => $open->take(14)->map(fn ($c) => ['label' => (string) $c->label])->all()];
    }

    private function w_chronic_absentees(): array
    {
        $days = DB::table('attendance_student')->where('sub_institute_id', $this->tenant)->where('syear', $this->syear)
            ->whereDate('attendance_date', '<=', $this->today)->whereDate('attendance_date', '>=', Carbon::today()->subDays(45)->toDateString())
            ->selectRaw('DATE(attendance_date) as d')->distinct()->orderByDesc('d')->limit(10)->pluck('d')->all();
        $threshold = 5;
        if (count($days) < $threshold) {
            return ['days' => count($days), 'threshold' => $threshold, 'total' => 0, 'items' => []];
        }

        $base = DB::table('attendance_student as a')
            ->where('a.sub_institute_id', $this->tenant)->where('a.syear', $this->syear)
            ->when(true, fn ($q) => $this->scopeClasses($q, 'a.standard_id', 'a.section_id'))
            ->whereIn(DB::raw('DATE(a.attendance_date)'), $days)
            ->selectRaw("a.student_id, SUM(a.attendance_code = 'A') as absences")
            ->groupBy('a.student_id')->havingRaw("SUM(a.attendance_code = 'A') >= ?", [$threshold]);

        $total = (int) DB::query()->fromSub($base, 't')->count();
        $items = DB::query()->fromSub($base, 't')
            ->join('tblstudent as s', 's.id', '=', 't.student_id')
            ->leftJoin('tblstudent_enrollment as se', function ($j) {
                $j->on('se.student_id', '=', 's.id')->where('se.syear', $this->syear)->whereNull('se.end_date');
            })
            ->leftJoin('standard as st', 'st.id', '=', 'se.standard_id')
            ->leftJoin('division as d', 'd.id', '=', 'se.section_id')
            ->selectRaw("s.id, CONCAT_WS(' ', s.first_name, s.last_name) as name, CONCAT_WS(' - ', st.name, d.name) as class_name, t.absences, NULLIF(TRIM(COALESCE(s.mobile, s.mother_mobile, s.student_mobile, '')), '') as phone")
            ->orderByDesc('t.absences')->limit(8)->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'name' => trim((string) $r->name), 'class_name' => (string) $r->class_name, 'absences' => (int) $r->absences, 'phone' => $r->phone])->all();

        return ['days' => count($days), 'threshold' => $threshold, 'total' => $total, 'items' => $items];
    }

    /**
     * Periods today whose teacher is on approved leave and for which no proxy has been arranged.
     * Timetable week_day is a single letter: M T W H(Thu) F S.
     */
    private function w_substitutes_needed(): array
    {
        $letter = ['1' => 'M', '2' => 'T', '3' => 'W', '4' => 'H', '5' => 'F', '6' => 'S'][Carbon::today()->format('N')] ?? null;
        if ($letter === null) {
            return ['teachers' => 0, 'periods' => 0, 'uncovered' => 0, 'items' => []];
        }

        $onLeave = DB::table('hrms_emp_leaves')->where('sub_institute_id', $this->tenant)->whereNull('deleted_at')
            ->whereIn('status', ['approved', 'approved_lwp'])
            ->whereDate('from_date', '<=', $this->today)->whereDate('to_date', '>=', $this->today)->pluck('user_id')->unique()->all();
        if (! $onLeave) {
            return ['teachers' => 0, 'periods' => 0, 'uncovered' => 0, 'items' => []];
        }

        $rows = DB::table('timetable as t')
            ->join('tbluser as u', 'u.id', '=', 't.teacher_id')
            ->leftJoin('standard as st', 'st.id', '=', 't.standard_id')
            ->leftJoin('division as d', 'd.id', '=', 't.division_id')
            ->leftJoin('subject as sj', 'sj.id', '=', 't.subject_id')
            ->leftJoin('period as p', 'p.id', '=', 't.period_id')
            ->leftJoin('proxy_master as pm', function ($j) {
                $j->on('pm.timetable_id', '=', 't.id')->whereDate('pm.proxy_date', $this->today);
            })
            ->where('t.sub_institute_id', $this->tenant)->where('t.syear', $this->syear)->where('t.week_day', $letter)
            ->whereIn('t.teacher_id', $onLeave)
            ->selectRaw("t.id, CONCAT_WS(' ', u.first_name, u.last_name) as teacher, CONCAT_WS(' - ', st.name, d.name) as class_name, sj.subject_name as subject, p.title as period, p.sort_order, pm.id as proxy_id")
            ->orderBy('p.sort_order')->get();

        $open = $rows->filter(fn ($r) => $r->proxy_id === null);

        return [
            'teachers' => $rows->pluck('teacher')->unique()->count(),
            'periods' => $rows->count(),
            'uncovered' => $open->count(),
            'items' => $open->take(8)->map(fn ($r) => ['id' => (int) $r->id, 'title' => trim((string) $r->teacher), 'detail' => trim(implode(' · ', array_filter([(string) $r->period, (string) $r->class_name, (string) $r->subject])))])->values()->all(),
        ];
    }

    /** How long parent messages have been waiting: the oldest, and how many are under 3 days, 3–7 days, over a week. */
    private function w_parent_msg_age(): array
    {
        $q = DB::table('parent_communication')->where('sub_institute_id', $this->tenant)
            ->where(fn ($w) => $w->whereNull('reply')->orWhereRaw("TRIM(reply) = ''"));
        $row = (clone $q)->selectRaw('COUNT(*) as total, MIN(date_) as oldest, SUM(DATEDIFF(CURDATE(), date_) < 3) as lt3, SUM(DATEDIFF(CURDATE(), date_) BETWEEN 3 AND 7) as d3_7, SUM(DATEDIFF(CURDATE(), date_) > 7) as gt7')->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'oldest_days' => $row && $row->oldest ? (int) Carbon::parse($row->oldest)->diffInDays(Carbon::today()) : null,
            'lt3' => (int) ($row->lt3 ?? 0), 'd3_7' => (int) ($row->d3_7 ?? 0), 'gt7' => (int) ($row->gt7 ?? 0),
        ];
    }

    /**
     * Open tasks by how late they are. `task.TASK_DATE` is the due date (the Task Management module and the Brain's
     * TaskIntelligence both treat it so); a task with no due date can never be late and is counted as not yet due.
     */
    private function w_task_aging(): array
    {
        $row = $this->openTasks()->selectRaw('COUNT(*) as total, MIN(CASE WHEN TASK_DATE < CURDATE() THEN TASK_DATE END) as oldest_due, SUM(TASK_DATE IS NULL OR TASK_DATE >= CURDATE()) as not_late, SUM(TASK_DATE < CURDATE() AND DATEDIFF(CURDATE(), TASK_DATE) <= 7) as late_7, SUM(DATEDIFF(CURDATE(), TASK_DATE) BETWEEN 8 AND 30) as late_30, SUM(DATEDIFF(CURDATE(), TASK_DATE) > 30) as late_over')->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'oldest_days' => $row && $row->oldest_due ? (int) Carbon::parse($row->oldest_due)->diffInDays(Carbon::today()) : null,
            'not_late' => (int) ($row->not_late ?? 0), 'late_7' => (int) ($row->late_7 ?? 0), 'late_30' => (int) ($row->late_30 ?? 0), 'late_over' => (int) ($row->late_over ?? 0),
        ];
    }

    /** Share of homework given in the last 30 days that is marked completed (completion_status = Y), by class, lowest first. */
    private function w_homework_rate(): array
    {
        $rows = DB::table('homework as h')->join('standard as s', 's.id', '=', 'h.standard_id')
            ->where('h.sub_institute_id', $this->tenant)->where('h.syear', $this->syear)
            ->when(true, fn ($q) => $this->scopeClasses($q, 'h.standard_id', 'h.division_id'))
            ->whereDate('h.date', '>=', Carbon::today()->subDays(30)->toDateString())->whereDate('h.date', '<=', $this->today)
            ->selectRaw("s.id, s.name, COUNT(*) as total, SUM(h.completion_status = 'Y') as done")
            ->groupBy('s.id', 's.name', 's.sort_order')->get();

        $total = (int) $rows->sum('total');
        $done = (int) $rows->sum('done');

        return [
            'days' => 30,
            'total' => $total,
            'overall' => $total > 0 ? round($done / $total * 100, 1) : null,
            'rows' => $rows->map(fn ($r) => ['standard_name' => (string) $r->name, 'total' => (int) $r->total, 'done' => (int) $r->done, 'rate' => (int) $r->total > 0 ? round($r->done / $r->total * 100, 1) : null])
                ->filter(fn ($r) => $r['rate'] !== null)->sortBy('rate')->values()->take(10)->all(),
        ];
    }

    /** Joined this month / this year, left this year (an end date on the enrolment), and transfer certificates issued. */
    private function w_strength_changes(): array
    {
        $enr = DB::table('tblstudent_enrollment')->where('sub_institute_id', $this->tenant)->where('syear', $this->syear);
        $month = Carbon::today()->startOfMonth()->toDateString();

        return [
            'joined_month' => (int) (clone $enr)->whereDate('start_date', '>=', $month)->count(),
            'joined_year' => (int) (clone $enr)->whereYear('start_date', Carbon::today()->year)->count(),
            'left_year' => (int) (clone $enr)->whereNotNull('end_date')->count(),
            'tc_year' => (int) DB::table('tblstudent_tc_details')->where('sub_institute_id', $this->tenant)->where('syear', $this->syear)->count(),
        ];
    }

    /** Students on transport and how full each bus is against its seating. */
    private function w_transport_summary(): array
    {
        $buses = DB::table('transport_map_student as m')->join('transport_vehicle as v', 'v.id', '=', 'm.from_bus_id')
            ->where('m.sub_institute_id', $this->tenant)->where('m.syear', $this->syear)
            ->selectRaw('v.id, v.title, v.sitting_capacity as capacity, COUNT(*) as students')
            ->groupBy('v.id', 'v.title', 'v.sitting_capacity')->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'title' => (string) $r->title, 'students' => (int) $r->students, 'capacity' => (int) $r->capacity, 'fill' => (int) $r->capacity > 0 ? round($r->students / $r->capacity * 100) : null]);

        return [
            'students' => (int) $buses->sum('students'),
            'buses' => $buses->count(),
            'over_capacity' => $buses->filter(fn ($b) => $b['fill'] !== null && $b['fill'] > 100)->count(),
            'top' => $buses->sortByDesc('fill')->values()->take(6)->all(),
        ];
    }

    private function w_hostel_summary(): array
    {
        return [
            'hostels' => (int) DB::table('hostel_master')->where('sub_institute_id', $this->tenant)->count(),
            'rooms' => (int) DB::table('hostel_room_master')->where('sub_institute_id', $this->tenant)->count(),
            'rooms_in_use' => (int) DB::table('hostel_room_allocation')->where('sub_institute_id', $this->tenant)->distinct()->count('room_id'),
            'residents' => (int) DB::table('hostel_room_allocation')->where('sub_institute_id', $this->tenant)->count(),
        ];
    }

    /** Staff to hand a follow-up to: name search, active accounts of this school only, at most 8. */
    public function staffSearch(Request $request): JsonResponse
    {
        $role = self::roleKeyFor((string) session()->get('user_profile_name'));
        if (session()->get('is_student') || ! in_array($role, self::FOLLOWUP_ROLES, true)) {
            return response()->json(['status' => '0', 'message' => 'Assigning follow-ups is not available for your role.'], 403);
        }
        $term = trim((string) $request->input('q'));
        if (mb_strlen($term) < 2) {
            return response()->json(['status' => '1', 'message' => 'Success', 'items' => []]);
        }
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
        $items = DB::table('tbluser as u')->leftJoin('tbluserprofilemaster as p', 'p.id', '=', 'u.user_profile_id')
            ->where('u.sub_institute_id', (int) session()->get('sub_institute_id'))->where('u.status', '1')
            ->whereRaw("CONCAT_WS(' ', u.first_name, u.last_name) LIKE ?", [$like])
            ->selectRaw("u.id, CONCAT_WS(' ', u.first_name, u.last_name) as name, p.name as role")->orderBy('u.first_name')->limit(8)->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'name' => trim((string) $r->name), 'role' => $r->role]);

        return response()->json(['status' => '1', 'message' => 'Success', 'items' => $items]);
    }

    /**
     * Turn a signal into a task for a named person, with a due date. It is an ordinary Task Management task, so the
     * assignee sees it in My Tasks, and the signal card shows who owns it and how it is going. Only the signal and
     * the person are taken from the request; the task text is built here from the Brain's own record of the signal.
     */
    public function followUp(Request $request): JsonResponse
    {
        $role = self::roleKeyFor((string) session()->get('user_profile_name'));
        if (session()->get('is_student') || ! in_array($role, self::FOLLOWUP_ROLES, true)) {
            return response()->json(['status' => '0', 'message' => 'Assigning follow-ups is not available for your role.'], 403);
        }
        $data = $request->validate([
            'signal_id' => ['required', 'string', 'max:64'],
            'assignee_id' => ['required', 'integer'],
            'due_days' => ['required', 'integer', 'min:1', 'max:90'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $tenant = (int) session()->get('sub_institute_id');
        $me = (int) session()->get('user_id');

        $signal = DB::table('hpbrain_signals')->where('tenant_id', (string) $tenant)->where('id', $data['signal_id'])->first();
        $assignee = DB::table('tbluser')->where('id', $data['assignee_id'])->where('sub_institute_id', $tenant)->where('status', '1')->first(['id', 'first_name', 'last_name']);
        if (! $signal || ! $assignee) {
            return response()->json(['status' => '0', 'message' => 'That signal or person could not be found.'], 404);
        }
        $marker = '[signal:'.$signal->id.']';
        $open = DB::table('task')->where('sub_institute_id', $tenant)->whereNull('deleted_at')->where('TASK_DESCRIPTION', 'like', '%'.$marker.'%')
            ->whereNotIn('STATUS', ['COMPLETED', 'COMPLETE'])->first(['ID', 'TASK_ALLOCATED_TO']);
        if ($open) {
            return response()->json(['status' => '0', 'message' => 'This signal already has an open follow-up.', 'task_id' => (int) $open->ID], 409);
        }

        $meta = json_decode((string) $signal->metadata, true) ?: [];
        $title = (string) ($meta['title'] ?? $this->humanise((string) $signal->rule_key));
        $step = DB::table('hpbrain_reasoning_steps')->where('tenant_id', (string) $tenant)->where('signal_id', $signal->id)->orderByDesc('step_order')->first(['id']);
        $rec = $step ? DB::table('hpbrain_recommendations')->where('tenant_id', (string) $tenant)->where('reasoning_step_id', $step->id)->first(['title']) : null;
        $due = Carbon::today()->addDays((int) $data['due_days'])->toDateString();
        $body = "Follow up on this signal: {$title}.".($rec ? "\n\nRecommended: {$rec->title}" : '').(! empty($data['note']) ? "\n\nNote from the assigner: {$data['note']}" : '')
            ."\n\nReview on {$due}, and record what changed.\n\n{$marker}";

        $id = DB::table('task')->insertGetId([
            'TASK_TITLE' => mb_substr('Follow up: '.$title, 0, 150),
            'TASK_DESCRIPTION' => $body,
            'TASK_DATE' => $due,
            'planned_start_date' => Carbon::today()->toDateString(),
            'task_type' => in_array($signal->severity, ['critical', 'high'], true) ? 'High' : 'Medium',
            'STATUS' => 'PENDING',
            'TASK_ALLOCATED' => $me,
            'TASK_ALLOCATED_TO' => (int) $assignee->id,
            'CREATED_BY' => $me,
            'CREATED_IP_ADDRESS' => $request->ip(),
            'SYEAR' => (int) session()->get('syear'),
            'MARKING_PERIOD_ID' => 0,
            'sub_institute_id' => $tenant,
        ], 'ID');

        return response()->json([
            'status' => '1', 'message' => 'Follow-up created', 'task_id' => (int) $id,
            'owner' => trim($assignee->first_name.' '.$assignee->last_name), 'due' => $due,
        ]);
    }

    /**
     * Quick student lookup for the front office: name or admission number in, class and a parent's number out.
     * Limited to FIND_ROLES, to the current year's enrolled students, and to 8 results.
     */
    public function findStudent(Request $request): JsonResponse
    {
        $role = self::roleKeyFor((string) session()->get('user_profile_name'));
        if (session()->get('is_student') || ! in_array($role, self::FIND_ROLES, true)) {
            return response()->json(['status' => '0', 'message' => 'Student lookup is not available for your role.'], 403);
        }

        $term = trim((string) $request->input('q'));
        if (mb_strlen($term) < 3) {
            return response()->json(['status' => '1', 'message' => 'Success', 'items' => []]);
        }
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

        $items = DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as se', 'se.student_id', '=', 's.id')
            ->leftJoin('standard as st', 'st.id', '=', 'se.standard_id')
            ->leftJoin('division as d', 'd.id', '=', 'se.section_id')
            ->where('se.sub_institute_id', (int) session()->get('sub_institute_id'))->where('se.syear', session()->get('syear'))->whereNull('se.end_date')
            ->where(fn ($w) => $w->where('s.enrollment_no', 'like', $like)->orWhereRaw("CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) LIKE ?", [$like]))
            ->selectRaw("s.id, CONCAT_WS(' ', s.first_name, s.last_name) as name, s.enrollment_no, CONCAT_WS(' - ', st.name, d.name) as class_name, NULLIF(TRIM(COALESCE(s.mobile, s.mother_mobile, s.student_mobile, '')), '') as phone")
            ->orderBy('s.first_name')->limit(8)->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'name' => trim((string) $r->name), 'enrollment_no' => $r->enrollment_no, 'class_name' => (string) $r->class_name, 'phone' => $r->phone]);

        return response()->json(['status' => '1', 'message' => 'Success', 'items' => $items]);
    }

    /* ------------------------------------------------------------------ fees */

    /** Collected so far this month, against the same days of last month. */
    private function w_fees_month(): array
    {
        $today = Carbon::today();
        $sum = function (Carbon $from, Carbon $to) {
            $scope = ['sub_institute_id' => $this->tenant, 'syear' => $this->syear, 'is_deleted' => 'N'];
            $a = (float) DB::table('fees_collect')->where($scope)->whereDate('receiptdate', '>=', $from->toDateString())->whereDate('receiptdate', '<=', $to->toDateString())->sum('amount');
            $b = (float) DB::table('fees_paid_other')->where($scope)->whereDate('receiptdate', '>=', $from->toDateString())->whereDate('receiptdate', '<=', $to->toDateString())->sum('actual_amountpaid');

            return round($a + $b, 2);
        };
        $lastStart = $today->copy()->subMonthNoOverflow()->startOfMonth();
        $lastEnd = $lastStart->copy()->day(min($today->day, $lastStart->daysInMonth));

        return [
            'this_month' => $sum($today->copy()->startOfMonth(), $today),
            'last_month_same_days' => $sum($lastStart, $lastEnd),
            'month_label' => $today->format('F'),
            'last_month_label' => $lastStart->format('F'),
        ];
    }


    private function w_fees_today(): float
    {
        $a = (float) DB::table('fees_collect')->where(['sub_institute_id' => $this->tenant, 'syear' => $this->syear, 'is_deleted' => 'N'])->whereDate('receiptdate', $this->today)->sum('amount');
        $b = (float) DB::table('fees_paid_other')->where(['sub_institute_id' => $this->tenant, 'syear' => $this->syear, 'is_deleted' => 'N'])->whereDate('receiptdate', $this->today)->sum('actual_amountpaid');

        return round($a + $b, 2);
    }

    private function w_fee_trend(): array
    {
        $from = Carbon::today()->subDays(29)->toDateString();
        $scope = ['sub_institute_id' => $this->tenant, 'syear' => $this->syear, 'is_deleted' => 'N'];
        $reg = DB::table('fees_collect')->selectRaw('DATE(receiptdate) as day, SUM(amount) as amount')->where($scope)->whereDate('receiptdate', '>=', $from)->groupBy('day')->pluck('amount', 'day');
        $oth = DB::table('fees_paid_other')->selectRaw('DATE(receiptdate) as day, SUM(actual_amountpaid) as amount')->where($scope)->whereDate('receiptdate', '>=', $from)->groupBy('day')->pluck('amount', 'day');
        $out = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i)->toDateString();
            $out[] = ['date' => $day, 'label' => Carbon::parse($day)->format('D'), 'amount' => round((float) ($reg[$day] ?? 0) + (float) ($oth[$day] ?? 0), 2)];
        }

        return $out;
    }

    private function w_recent_fee_receipts(): array
    {
        return DB::table('fees_collect as fc')
            ->join('tblstudent as ts', 'ts.id', '=', 'fc.student_id')
            ->selectRaw("MIN(fc.id) as id, fc.receipt_no, SUM(fc.amount) as amount, MAX(fc.receiptdate) as receiptdate, CONCAT_WS(' ', ts.first_name, ts.last_name) as student_name")
            ->where(['fc.sub_institute_id' => $this->tenant, 'fc.syear' => $this->syear, 'fc.is_deleted' => 'N'])
            ->groupBy('fc.syear', 'fc.receipt_no', 'fc.student_id', 'ts.first_name', 'ts.last_name')
            ->orderByDesc(DB::raw('MAX(fc.receiptdate)'))->orderByDesc(DB::raw('MIN(fc.id)'))->limit(6)->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'receipt_no' => (string) $r->receipt_no, 'amount' => round((float) $r->amount, 2), 'date' => $r->receiptdate, 'student_name' => $r->student_name])->all();
    }

    /* -------------------------------------------------------- admissions etc. */

    private function w_admissions_year(): int
    {
        return (int) DB::table('admission_enquiry')->where('sub_institute_id', $this->tenant)->where('syear', $this->syear)->whereNull('deleted_at')->count();
    }

    private function w_recent_enquiries(): array
    {
        return DB::table('admission_enquiry')
            ->selectRaw("id, enquiry_no, CONCAT_WS(' ', first_name, last_name) as student_name, created_on")
            ->where('sub_institute_id', $this->tenant)->where('syear', $this->syear)->whereNull('deleted_at')
            ->orderByDesc('created_on')->orderByDesc('id')->limit(6)->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'title' => trim((string) $r->student_name), 'detail' => $r->enquiry_no, 'date' => $r->created_on])->all();
    }

    private function w_parent_msgs_pending(): int
    {
        return (int) DB::table('parent_communication')->where('sub_institute_id', $this->tenant)
            ->where(fn ($q) => $q->whereNull('reply')->orWhereRaw("TRIM(reply) = ''"))->count();
    }

    private function w_recent_parent_messages(): array
    {
        return DB::table('parent_communication as pc')
            ->leftJoin('tblstudent as s', 's.id', '=', 'pc.student_id')
            ->selectRaw("pc.id, pc.title, pc.date_, (pc.reply IS NOT NULL AND TRIM(pc.reply) <> '') as replied, CONCAT_WS(' ', s.first_name, s.last_name) as student_name")
            ->where('pc.sub_institute_id', $this->tenant)->orderByDesc('pc.date_')->orderByDesc('pc.id')->limit(6)->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'title' => (string) $r->title, 'detail' => trim((string) $r->student_name), 'date' => $r->date_, 'done' => (bool) $r->replied])->all();
    }

    private function w_recent_circulars(): array
    {
        return DB::table('circular')->select('id', 'title', 'date_')
            ->where('sub_institute_id', $this->tenant)->where('syear', $this->syear)
            ->orderByDesc('date_')->orderByDesc('id')->limit(6)->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'title' => (string) $r->title, 'detail' => null, 'date' => $r->date_])->all();
    }

    /* ------------------------------------------------------- front desk, tasks */

    private function w_visitors_today(): array
    {
        $q = DB::table('visitor_master')->where('sub_institute_id', $this->tenant)->whereDate('meet_date', $this->today);
        $total = (clone $q)->count();
        $inside = (clone $q)->where(fn ($w) => $w->whereNull('out_time')->orWhere('out_time', ''))->count();

        return ['total' => (int) $total, 'inside' => (int) $inside];
    }

    private function w_complaints(): array
    {
        $q = DB::table('complaint')->where('SUB_INSTITUTE_ID', $this->tenant);
        $total = (clone $q)->count();
        $open = (clone $q)->where(fn ($w) => $w->whereNull('COMPLAINT_SOLUTION')->orWhere('COMPLAINT_SOLUTION', ''))->count();

        return ['total' => (int) $total, 'open' => (int) $open];
    }

    private function openTasks()
    {
        return DB::table('task')->where('sub_institute_id', $this->tenant)->whereIn('STATUS', ['PENDING', 'IN-PROGRESS']);
    }

    private function w_tasks_pending_all(): int
    {
        return (int) $this->openTasks()->count();
    }

    private function w_tasks_pending_me(): array
    {
        $q = $this->openTasks()->where('TASK_ALLOCATED_TO', $this->userId);

        return [
            'count' => (int) (clone $q)->count(),
            'items' => (clone $q)->selectRaw('ID as id, TASK_TITLE as title, TASK_DATE as date, STATUS as status')->orderByRaw('TASK_DATE IS NULL, TASK_DATE')->limit(6)->get()
                ->map(fn ($r) => ['id' => (int) $r->id, 'title' => (string) $r->title, 'detail' => $r->status, 'date' => $r->date])->all(),
        ];
    }

    private function w_inward_month(): int
    {
        return (int) DB::table('inward')->where('sub_institute_id', $this->tenant)->whereYear('inward_date', Carbon::today()->year)->whereMonth('inward_date', Carbon::today()->month)->count();
    }

    private function w_outward_month(): int
    {
        return (int) DB::table('outward')->where('sub_institute_id', $this->tenant)->whereYear('outward_date', Carbon::today()->year)->whereMonth('outward_date', Carbon::today()->month)->count();
    }

    private function w_petty_cash_month(): float
    {
        return round((float) DB::table('petty_cash')->where('sub_institute_id', $this->tenant)->whereYear('created_on', Carbon::today()->year)->whereMonth('created_on', Carbon::today()->month)->sum('amount'), 2);
    }

    /* ------------------------------------------------------------- inventory */

    /** requisition_status 1 PENDING, 2 APPROVED, 3 REJECT, 4 ON HOLD (inventory_requisition_status_master). */
    private function w_requisitions(): array
    {
        $rows = DB::table('inventory_requisition_details')->where('sub_institute_id', $this->tenant)->where('syear', $this->syear)
            ->selectRaw('requisition_status as s, COUNT(DISTINCT requisition_no) as n')->groupBy('requisition_status')->pluck('n', 's');

        return ['pending' => (int) ($rows[1] ?? 0), 'approved' => (int) ($rows[2] ?? 0), 'rejected' => (int) ($rows[3] ?? 0), 'on_hold' => (int) ($rows[4] ?? 0)];
    }

    private function w_recent_requisitions(): array
    {
        return DB::table('inventory_requisition_details as r')
            ->leftJoin('inventory_item_master as i', 'i.id', '=', 'r.item_id')
            ->selectRaw('MIN(r.id) as id, r.requisition_no, MAX(r.requisition_date) as d, MIN(r.requisition_status) as st, COUNT(*) as items')
            ->where('r.sub_institute_id', $this->tenant)->where('r.syear', $this->syear)
            ->groupBy('r.requisition_no')->orderByDesc('d')->limit(6)->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'title' => 'Requisition '.$r->requisition_no, 'detail' => $r->items.' item(s) · '.([1 => 'Pending', 2 => 'Approved', 3 => 'Rejected', 4 => 'On hold'][(int) $r->st] ?? ''), 'date' => $r->d])->all();
    }

    /** po_approval_status: 1 and 2 are the two decided states, null is not yet decided. */
    private function w_po_status(): array
    {
        $rows = DB::table('inventory_generate_po_details')->where('sub_institute_id', $this->tenant)->where('syear', $this->syear)
            ->selectRaw('po_approval_status as s, COUNT(DISTINCT po_number) as n')->groupBy('po_approval_status')->get();
        $out = ['awaiting' => 0, 'decided' => 0];
        foreach ($rows as $r) {
            $out[$r->s === null ? 'awaiting' : 'decided'] += (int) $r->n;
        }

        return $out;
    }

    private function w_items_total(): int
    {
        return (int) DB::table('inventory_item_master')->where('sub_institute_id', $this->tenant)->count();
    }

    private function w_vendors_total(): int
    {
        return (int) DB::table('inventory_vendor_master')->where('sub_institute_id', $this->tenant)->count();
    }

    private function w_purchases_month(): array
    {
        $q = DB::table('inventory_item_direct_purchase')->where('sub_institute_id', $this->tenant)
            ->whereYear('bill_date', Carbon::today()->year)->whereMonth('bill_date', Carbon::today()->month);

        return ['count' => (int) (clone $q)->count(), 'amount' => round((float) (clone $q)->sum('amount'), 2)];
    }

    /* ------------------------------------------------------------------ exams */

    private function w_upcoming_exams(): array
    {
        $q = DB::table('exam_schedule as e')
            ->leftJoin('standard as s', 's.id', '=', 'e.standard_id')
            ->where('e.sub_institute_id', $this->tenant)->whereDate('e.date_', '>=', $this->today);

        return [
            'count' => (int) (clone $q)->count(),
            'items' => (clone $q)->selectRaw('e.id, e.title, e.date_, s.name as standard_name')->orderBy('e.date_')->limit(6)->get()
                ->map(fn ($r) => ['id' => (int) $r->id, 'title' => (string) $r->title, 'detail' => $r->standard_name, 'date' => $r->date_])->all(),
        ];
    }

    private function w_exam_evaluation(): array
    {
        $q = DB::table('exam_evaluation_batch')->where('sub_institute_id', $this->tenant)->where('syear', $this->syear);

        return [
            'batches' => (int) (clone $q)->count(),
            'sheets' => (int) (clone $q)->sum('total_sheets'),
            'evaluated' => (int) (clone $q)->sum('evaluated_sheets'),
            'approved' => (int) (clone $q)->sum('approved_sheets'),
        ];
    }
}
