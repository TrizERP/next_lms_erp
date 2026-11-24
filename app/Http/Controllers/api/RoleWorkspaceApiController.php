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
        'admin' => ['students_total', 'staff_total', 'attendance_today', 'attendance_trend', 'class_attendance', 'absent_today', 'staff_on_leave_today', 'staff_on_leave_list', 'leaves_pending', 'fees_today', 'fees_period', 'fees_month', 'fee_trend', 'recent_fee_receipts', 'admissions_year', 'admissions_period', 'admission_funnel', 'followups_due', 'recent_enquiries', 'visitors_today', 'visitors_period', 'complaints', 'tasks_pending_all', 'parent_msgs_pending', 'pending_approvals', 'recent_circulars', 'upcoming_events', 'birthdays', 'upcoming_exams', 'exam_results', 'students_by_class', 'gender_split'],
        'assistant_admin' => ['students_total', 'staff_total', 'attendance_today', 'attendance_trend', 'class_attendance', 'absent_today', 'staff_on_leave_list', 'fees_today', 'fees_period', 'fees_month', 'admissions_year', 'admissions_period', 'admission_funnel', 'followups_due', 'recent_enquiries', 'visitors_today', 'visitors_period', 'complaints', 'tasks_pending_all', 'parent_msgs_pending', 'pending_approvals', 'recent_circulars', 'staff_on_leave_today', 'upcoming_events', 'birthdays', 'gender_split'],
        'principal' => ['students_total', 'staff_total', 'attendance_today', 'attendance_trend', 'class_attendance', 'absent_today', 'staff_on_leave_today', 'staff_on_leave_list', 'leaves_pending', 'fees_today', 'fees_period', 'fees_month', 'fee_trend', 'admissions_year', 'admissions_period', 'admission_funnel', 'parent_msgs_pending', 'complaints', 'pending_approvals', 'recent_circulars', 'upcoming_events', 'birthdays', 'upcoming_exams', 'exam_results', 'students_by_class', 'gender_split'],
        'vice_principal' => ['students_total', 'staff_total', 'attendance_today', 'attendance_trend', 'class_attendance', 'absent_today', 'staff_on_leave_today', 'staff_on_leave_list', 'leaves_pending', 'fees_today', 'fees_period', 'fees_month', 'fee_trend', 'admissions_year', 'admissions_period', 'admission_funnel', 'parent_msgs_pending', 'complaints', 'pending_approvals', 'recent_circulars', 'upcoming_events', 'birthdays', 'upcoming_exams', 'exam_results', 'students_by_class', 'gender_split'],
        'counsellor' => ['students_total', 'attendance_today', 'absent_today', 'parent_msgs_pending', 'recent_parent_messages', 'recent_circulars', 'tasks_pending_me', 'upcoming_exams', 'exam_results', 'upcoming_events', 'birthdays'],
        'clerk' => ['students_total', 'fees_today', 'fees_period', 'fees_month', 'fee_trend', 'recent_fee_receipts', 'admissions_year', 'admissions_period', 'admission_funnel', 'followups_due', 'recent_enquiries', 'visitors_today', 'visitors_period', 'inward_month', 'outward_month', 'petty_cash_month', 'tasks_pending_me', 'recent_circulars', 'upcoming_events', 'birthdays'],
        'assistant_clerk' => ['fees_today', 'fees_period', 'fees_month', 'fee_trend', 'recent_fee_receipts', 'admissions_year', 'admissions_period', 'admission_funnel', 'followups_due', 'recent_enquiries', 'visitors_today', 'visitors_period', 'inward_month', 'tasks_pending_me', 'recent_circulars', 'upcoming_events', 'birthdays'],
        'librarian' => ['tasks_pending_me', 'my_leaves_pending', 'library_overdue', 'recent_circulars', 'upcoming_events', 'birthdays'],
        'store_keeper' => ['requisitions', 'po_status', 'items_total', 'vendors_total', 'purchases_month', 'stock_reorder', 'recent_requisitions', 'tasks_pending_me', 'recent_circulars', 'upcoming_events'],
        'exam_department' => ['upcoming_exams', 'exam_evaluation', 'exam_results', 'students_total', 'attendance_today', 'tasks_pending_me', 'recent_circulars', 'upcoming_events'],
        'trustee' => ['students_total', 'staff_total', 'attendance_today', 'attendance_trend', 'class_attendance', 'fees_today', 'fees_period', 'fees_month', 'fee_trend', 'admissions_year', 'admissions_period', 'admission_funnel', 'students_by_class', 'gender_split', 'exam_results', 'complaints', 'recent_circulars', 'upcoming_events'],
        'peon' => ['recent_circulars', 'tasks_pending_me', 'my_leaves_pending', 'visitors_today', 'visitors_period', 'upcoming_events', 'birthdays'],
        'workspace' => ['tasks_pending_me', 'my_leaves_pending', 'recent_circulars', 'upcoming_events'],
    ];

    /** Roles that may open the fees panel (school-wide money figures). */
    private const FEES_ROLES = ['admin', 'assistant_admin', 'principal', 'vice_principal', 'trustee', 'clerk', 'assistant_clerk'];

    /** Period choices for the headline money / enquiry / visitor figures. */
    private const PERIODS = ['today', 'week', 'month', 'year'];

    private int $tenant;

    private $syear;

    private $userId;

    private string $today;

    private string $period = 'today';

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

        return 'workspace';
    }

    public function summary(Request $request): JsonResponse
    {
        if (session()->get('is_student')) {
            return response()->json(['status' => '0', 'message' => 'This dashboard is for staff only.'], 403);
        }

        $this->tenant = (int) session()->get('sub_institute_id');
        $this->syear = session()->get('syear');
        $this->userId = session()->get('user_id');
        $this->today = Carbon::today()->toDateString();
        $this->period = in_array($request->input('period'), self::PERIODS, true) ? $request->input('period') : 'today';

        $profile = (string) session()->get('user_profile_name');
        $role = self::roleKeyFor($profile);
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
            'widgets' => $data,
        ]);
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

    private function enrolled()
    {
        return DB::table('tblstudent_enrollment as se')
            ->join('tblstudent as ts', 'ts.id', '=', 'se.student_id')
            ->where('se.sub_institute_id', $this->tenant)
            ->where('se.syear', $this->syear)
            ->whereNull('se.end_date');
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
            'items' => (clone $q)->selectRaw('ID as id, TASK_TITLE as title, TASK_DATE as date, STATUS as status')->orderByDesc('TASK_DATE')->limit(6)->get()
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
