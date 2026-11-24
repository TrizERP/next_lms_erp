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
    /** Widgets each role receives. Anything not listed here is never computed for that role. */
    private const WIDGETS = [
        'assistant_admin' => ['students_total', 'staff_total', 'attendance_today', 'attendance_trend', 'class_attendance', 'fees_today', 'fees_month', 'admissions_year', 'admission_funnel', 'recent_enquiries', 'visitors_today', 'complaints', 'tasks_pending_all', 'parent_msgs_pending', 'recent_circulars', 'staff_on_leave_today', 'gender_split'],
        'principal' => ['students_total', 'staff_total', 'attendance_today', 'attendance_trend', 'class_attendance', 'staff_on_leave_today', 'leaves_pending', 'fees_today', 'fees_month', 'fee_trend', 'admissions_year', 'admission_funnel', 'parent_msgs_pending', 'complaints', 'recent_circulars', 'upcoming_exams', 'students_by_class', 'gender_split'],
        'vice_principal' => ['students_total', 'staff_total', 'attendance_today', 'attendance_trend', 'class_attendance', 'staff_on_leave_today', 'leaves_pending', 'fees_today', 'fees_month', 'fee_trend', 'admissions_year', 'admission_funnel', 'parent_msgs_pending', 'complaints', 'recent_circulars', 'upcoming_exams', 'students_by_class', 'gender_split'],
        'counsellor' => ['students_total', 'attendance_today', 'parent_msgs_pending', 'recent_parent_messages', 'recent_circulars', 'tasks_pending_me', 'upcoming_exams'],
        'clerk' => ['students_total', 'fees_today', 'fees_month', 'fee_trend', 'recent_fee_receipts', 'admissions_year', 'admission_funnel', 'recent_enquiries', 'visitors_today', 'inward_month', 'outward_month', 'petty_cash_month', 'tasks_pending_me', 'recent_circulars'],
        'assistant_clerk' => ['fees_today', 'fees_month', 'fee_trend', 'recent_fee_receipts', 'admissions_year', 'admission_funnel', 'recent_enquiries', 'visitors_today', 'inward_month', 'tasks_pending_me', 'recent_circulars'],
        'librarian' => ['tasks_pending_me', 'my_leaves_pending', 'recent_circulars'],
        'store_keeper' => ['requisitions', 'po_status', 'items_total', 'vendors_total', 'purchases_month', 'recent_requisitions', 'tasks_pending_me', 'recent_circulars'],
        'exam_department' => ['upcoming_exams', 'exam_evaluation', 'students_total', 'attendance_today', 'tasks_pending_me', 'recent_circulars'],
        'trustee' => ['students_total', 'staff_total', 'attendance_today', 'attendance_trend', 'class_attendance', 'fees_today', 'fees_month', 'fee_trend', 'admissions_year', 'admission_funnel', 'students_by_class', 'gender_split', 'complaints', 'recent_circulars'],
        'peon' => ['recent_circulars', 'tasks_pending_me', 'my_leaves_pending', 'visitors_today'],
        'workspace' => ['tasks_pending_me', 'my_leaves_pending', 'recent_circulars'],
    ];

    private int $tenant;

    private $syear;

    private $userId;

    private string $today;

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
            'widgets' => $data,
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
