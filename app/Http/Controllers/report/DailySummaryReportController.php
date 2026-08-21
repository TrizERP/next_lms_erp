<?php

namespace App\Http\Controllers\report;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use function App\Helpers\is_mobile;

/**
 * "Today at a glance" - module wise counts for the logged in sub-institute.
 *
 * Every figure below is scoped to session('sub_institute_id') and to the
 * current server date; there is no date filter on the page by design.
 * Fees / cancellation figures are deliberately NOT filtered by syear - money
 * received today belongs to today's report irrespective of the academic year
 * the receipt was raised against.
 */
class DailySummaryReportController extends Controller
{
    public function index(Request $request)
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $today = Carbon::today()->toDateString();

        $modules = [
            $this->fees($sub_institute_id, $today),
            $this->admission($sub_institute_id, $today),
            $this->studentAttendance($sub_institute_id, $syear, $today),
            $this->studentLeave($sub_institute_id, $today),
            $this->staffAttendance($sub_institute_id, $today),
            $this->staffLeave($sub_institute_id, $today),
            $this->taskManagement($sub_institute_id, $today),
            $this->visitorManagement($sub_institute_id, $today),
            $this->complaintManagement($sub_institute_id, $today),
            $this->parentCommunication($sub_institute_id, $today),
            $this->calendar($sub_institute_id, $today),
            $this->inventory($sub_institute_id, $today),
            $this->library($sub_institute_id, $today),
        ];

        $data = [
            'report_date' => $today,
            'sub_institute_id' => $sub_institute_id,
            'institute_name' => DB::table('school_setup')->where('Id', $sub_institute_id)->value('SchoolName'),
            'modules' => $modules,
            'printed_by' => $this->loggedInUserName(),
            'printed_on' => Carbon::now()->format('d-m-Y h:i A'),
        ];

        $type = "web";

        return is_mobile($type, "reports/daily_summary_report", $data, "view");
    }

    /**
     * "First Last" of the logged in user, for the print footer.
     *
     * loginController stores it as session('name'), but not every login path
     * goes through that branch, so fall back to tbluser when it is missing.
     */
    private function loggedInUserName(): string
    {
        $name = trim((string) session()->get('name'));

        if ($name === '') {
            $user = DB::table('tbluser')->where('id', session()->get('user_id'))->first();

            if ($user) {
                $name = trim($user->first_name . ' ' . $user->last_name);
            }
        }

        return $name !== '' ? $name : (string) session()->get('user_name');
    }

    /**
     * Shape one module block for the view.
     *
     * @param array $rows each row: ['label' => string, 'count' => int, 'amount' => float|null]
     */
    private function module(string $name, string $icon, array $rows, $total, ?float $totalAmount = null): array
    {
        return [
            'name' => $name,
            'icon' => $icon,
            'rows' => $rows,
            'total' => $total,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * @param string|null $reportRoute name of the drill-down report this count belongs to
     */
    private function row(string $label, $count, ?float $amount = null, bool $child = false, ?string $reportRoute = null): array
    {
        return [
            'label' => $label,
            'count' => $count,
            'amount' => $amount,
            'child' => $child,
            'url' => $this->reportUrl($reportRoute),
        ];
    }

    /**
     * Resolve a report route to a URL. Returns null when the route is not
     * registered, so a renamed or removed report degrades to a plain number
     * instead of blowing up the whole page.
     */
    private function reportUrl(?string $reportRoute): ?string
    {
        if (! $reportRoute || ! Route::has($reportRoute)) {
            return null;
        }

        return route($reportRoute);
    }

    /* ------------------------------------------------------------------ 1 */

    private function fees($sub_institute_id, string $today): array
    {
        // Regular fees + other fees, bucketed by payment mode. Anything that is
        // not cash or a cheque/DD is reported under "Online" (Online, POS, ...).
        $modeBucket = "CASE
                WHEN LOWER(TRIM(payment_mode)) = 'cash' THEN 'Cash'
                WHEN LOWER(TRIM(payment_mode)) IN ('cheque', 'dd', 'cheque/dd') THEN 'Cheque'
                ELSE 'Online'
            END";

        $collected = [
            'Cash' => ['count' => 0, 'amount' => 0.0],
            'Cheque' => ['count' => 0, 'amount' => 0.0],
            'Online' => ['count' => 0, 'amount' => 0.0],
        ];

        $regular = DB::table('fees_collect')
            ->selectRaw("$modeBucket as mode, COUNT(*) as cnt, SUM(amount) as amt")
            ->where('sub_institute_id', $sub_institute_id)
            ->where('is_deleted', 'N')
            ->whereDate('receiptdate', $today)
            ->groupBy('mode')
            ->get();

        $other = DB::table('fees_paid_other')
            ->selectRaw("$modeBucket as mode, COUNT(*) as cnt, SUM(actual_amountpaid) as amt")
            ->where('sub_institute_id', $sub_institute_id)
            ->where('is_deleted', 'N')
            ->whereDate('receiptdate', $today)
            ->groupBy('mode')
            ->get();

        foreach ($regular->concat($other) as $r) {
            $collected[$r->mode]['count'] += (int) $r->cnt;
            $collected[$r->mode]['amount'] += (float) $r->amt;
        }

        $cancelRegular = DB::table('fees_cancel')
            ->selectRaw('COUNT(*) as cnt, SUM(amountpaid) as amt')
            ->where('sub_institute_id', $sub_institute_id)
            ->whereDate('cancel_date', $today)
            ->first();

        $cancelOther = DB::table('fees_other_cancel')
            ->selectRaw('COUNT(*) as cnt, SUM(cancellation_amount) as amt')
            ->where('sub_institute_id', $sub_institute_id)
            ->whereDate('cancellation_date', $today)
            ->first();

        $cancelCount = (int) $cancelRegular->cnt + (int) $cancelOther->cnt;
        $cancelAmount = (float) $cancelRegular->amt + (float) $cancelOther->amt;

        $totalCount = $collected['Cash']['count'] + $collected['Cheque']['count'] + $collected['Online']['count'];
        $totalAmount = $collected['Cash']['amount'] + $collected['Cheque']['amount'] + $collected['Online']['amount'];

        return $this->module('Fees', 'fa-indian-rupee-sign', [
            $this->row('Total Fees Collected', $totalCount, $totalAmount, false, 'fees_collection_report.index'),
            $this->row('Cash', $collected['Cash']['count'], $collected['Cash']['amount'], true, 'fees_collection_report.index'),
            $this->row('Cheque', $collected['Cheque']['count'], $collected['Cheque']['amount'], true, 'fees_collection_report.index'),
            $this->row('Online', $collected['Online']['count'], $collected['Online']['amount'], true, 'fees_collection_report.index'),
            $this->row('Cancelled Fees / Transactions', $cancelCount, $cancelAmount, false, 'fees_cancel_report_index'),
        ], $totalCount, $totalAmount);
    }

    /* ------------------------------------------------------------------ 2 */

    private function admission($sub_institute_id, string $today): array
    {
        $inquiries = (int) DB::table('admission_enquiry')
            ->where('sub_institute_id', $sub_institute_id)
            ->whereDate('created_on', $today)
            ->count();

        $registrations = (int) DB::table('admission_registration')
            ->where('sub_institute_id', $sub_institute_id)
            ->whereDate('created_on', $today)
            ->count();

        // admission_status is only ever written as the literal "YES" on confirmation.
        $confirmed = (int) DB::table('admission_registration')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('admission_status', 'YES')
            ->whereRaw('DATE(COALESCE(admission_date, created_on)) = ?', [$today])
            ->count();

        return $this->module('Admission', 'fa-user-plus', [
            $this->row('Total Inquiries', $inquiries, null, false, 'admission_enquiry_report'),
            $this->row('Total Registrations', $registrations, null, false, 'admission_registration_report'),
            $this->row('Total Confirmed Admissions', $confirmed, null, false, 'admission_confirmation_report'),
        ], $inquiries + $registrations + $confirmed);
    }

    /* ------------------------------------------------------------------ 3 */

    private function studentAttendance($sub_institute_id, $syear, string $today): array
    {
        $totalStudents = (int) DB::table('tblstudent_enrollment')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('syear', $syear)
            ->whereNull('end_date')
            ->count();

        // Attendance is captured period wise, so count each student only once.
        $marked = DB::table('attendance_student')
            ->selectRaw('attendance_code, COUNT(DISTINCT student_id) as cnt')
            ->where('sub_institute_id', $sub_institute_id)
            ->whereDate('attendance_date', $today)
            ->groupBy('attendance_code')
            ->pluck('cnt', 'attendance_code');

        $present = (int) ($marked['P'] ?? 0);
        $absent = (int) ($marked['A'] ?? 0);

        return $this->module('Student Attendance', 'fa-user-check', [
            $this->row('Total Students', $totalStudents, null, false, 'daywise_student_attendance_report'),
            $this->row('Present Students', $present, null, false, 'daywise_student_attendance_report'),
            $this->row('Absent Students', $absent, null, false, 'daywise_student_attendance_report'),
        ], $totalStudents);
    }

    /* ------------------------------------------------------------------ 4 */

    private function studentLeave($sub_institute_id, string $today): array
    {
        $total = (int) $this->studentLeaveQuery($sub_institute_id, $today)->count();

        $pending = (int) $this->studentLeaveQuery($sub_institute_id, $today)
            ->where(function ($q) {
                $q->whereNull('reply')->orWhereRaw("TRIM(reply) = ''");
            })
            ->count();

        return $this->module('Student Leave', 'fa-calendar-minus', [
            $this->row('Total Leave Applications', $total, null, false, 'leave_application.index'),
            $this->row('Pending Leave Applications / Replies', $pending, null, false, 'leave_application.index'),
        ], $total);
    }

    private function studentLeaveQuery($sub_institute_id, string $today)
    {
        return DB::table('leave_applications')
            ->where('sub_institute_id', $sub_institute_id)
            ->whereDate('apply_date', $today);
    }

    /* ------------------------------------------------------------------ 5 */

    private function staffAttendance($sub_institute_id, string $today): array
    {
        $totalStaff = (int) DB::table('tbluser')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->count();

        $present = (int) DB::table('hrms_attendances')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('status', 1)
            ->whereDate('day', $today)
            ->distinct()
            ->count('user_id');

        return $this->module('Staff Attendance', 'fa-id-badge', [
            $this->row('Total Staff', $totalStaff, null, false, 'hrms_attendance_report.index'),
            $this->row('Present Staff', $present, null, false, 'hrms_attendance_report.index'),
            $this->row('Absent Staff', max($totalStaff - $present, 0), null, false, 'hrms_attendance_report.index'),
        ], $totalStaff);
    }

    /* ------------------------------------------------------------------ 6 */

    private function staffLeave($sub_institute_id, string $today): array
    {
        $total = (int) $this->staffLeaveQuery($sub_institute_id, $today)->count();
        $pending = (int) $this->staffLeaveQuery($sub_institute_id, $today)->where('status', 'pending')->count();

        return $this->module('Staff Leave', 'fa-person-circle-minus', [
            $this->row('Total Leave Applications', $total, null, false, 'leave.report'),
            $this->row('Pending Leave Applications / Replies', $pending, null, false, 'leave.report'),
        ], $total);
    }

    private function staffLeaveQuery($sub_institute_id, string $today)
    {
        return DB::table('hrms_emp_leaves')
            ->where('sub_institute_id', $sub_institute_id)
            ->whereNull('deleted_at')
            ->whereDate('created_at', $today);
    }

    /* ------------------------------------------------------------------ 7 */

    private function taskManagement($sub_institute_id, string $today): array
    {
        $total = (int) DB::table('task')
            ->where('sub_institute_id', $sub_institute_id)
            ->whereDate('TASK_DATE', $today)
            ->count();

        return $this->module('Task Management', 'fa-list-check', [
            $this->row('Total Tasks', $total, null, false, 'task_report_index'),
        ], $total);
    }

    /* ------------------------------------------------------------------ 8 */

    private function visitorManagement($sub_institute_id, string $today): array
    {
        $total = (int) DB::table('visitor_master')
            ->where('sub_institute_id', $sub_institute_id)
            ->whereDate('meet_date', $today)
            ->count();

        return $this->module('Visitor Management', 'fa-user-clock', [
            $this->row('Total Visitors', $total, null, false, 'show_visitor_report'),
        ], $total);
    }

    /* ------------------------------------------------------------------ 9 */

    private function complaintManagement($sub_institute_id, string $today): array
    {
        $total = (int) DB::table('complaint')
            ->where('SUB_INSTITUTE_ID', $sub_institute_id)
            ->whereDate('DATE', $today)
            ->count();

        return $this->module('Complaint Management', 'fa-triangle-exclamation', [
            $this->row('Total Complaints', $total, null, false, 'complaint_report_index'),
        ], $total);
    }

    /* ----------------------------------------------------------------- 10 */

    private function parentCommunication($sub_institute_id, string $today): array
    {
        $total = (int) $this->parentCommunicationQuery($sub_institute_id, $today)->count();

        $pending = (int) $this->parentCommunicationQuery($sub_institute_id, $today)
            ->where(function ($q) {
                $q->whereNull('reply')->orWhereRaw("TRIM(reply) = ''");
            })
            ->count();

        return $this->module('Parent Communication', 'fa-comments', [
            $this->row('Total Communications', $total, null, false, 'parent_communication.index'),
            $this->row('Pending Replies', $pending, null, false, 'parent_communication.index'),
        ], $total);
    }

    private function parentCommunicationQuery($sub_institute_id, string $today)
    {
        return DB::table('parent_communication')
            ->where('sub_institute_id', $sub_institute_id)
            ->whereDate('date_', $today);
    }

    /* ----------------------------------------------------------------- 11 */

    private function calendar($sub_institute_id, string $today): array
    {
        $byType = DB::table('calendar_events')
            ->selectRaw('event_type, COUNT(*) as cnt')
            ->where('sub_institute_id', $sub_institute_id)
            ->whereDate('school_date', $today)
            ->groupBy('event_type')
            ->pluck('cnt', 'event_type');

        $events = (int) ($byType['event'] ?? 0);
        $holidays = (int) ($byType['holiday'] ?? 0);
        $vacations = (int) ($byType['vacation'] ?? 0);

        return $this->module('Calendar', 'fa-calendar-days', [
            $this->row('Events', $events, null, false, 'calendar.index'),
            $this->row('Holidays', $holidays, null, false, 'calendar.index'),
            $this->row('Vacations', $vacations, null, false, 'calendar.index'),
        ], $events + $holidays + $vacations);
    }

    /* ----------------------------------------------------------------- 12 */

    private function inventory($sub_institute_id, string $today): array
    {
        $requisitions = (int) DB::table('inventory_requisition_details')
            ->where('sub_institute_id', $sub_institute_id)
            ->whereDate('requisition_date', $today)
            ->count();

        $allocations = (int) DB::table('inventory_allocation_details')
            ->where('SUB_INSTITUTE_ID', $sub_institute_id)
            ->whereDate('CREATED_ON', $today)
            ->count();

        return $this->module('Inventory', 'fa-boxes-stacked', [
            $this->row('Requisitions', $requisitions, null, false, 'inventory_requisition_report.index'),
            $this->row('Allocations', $allocations, null, false, 'show_inventory_item_allocation.index'),
        ], $requisitions + $allocations);
    }

    /* ----------------------------------------------------------------- 13 */

    private function library($sub_institute_id, string $today): array
    {
        $issued = (int) DB::table('library_book_circulations')
            ->where('sub_institute_id', $sub_institute_id)
            ->whereNull('deleted_at')
            ->whereDate('issued_date', $today)
            ->count();

        $returned = (int) DB::table('library_book_circulations')
            ->where('sub_institute_id', $sub_institute_id)
            ->whereNull('deleted_at')
            ->whereDate('return_date', $today)
            ->count();

        return $this->module('Library', 'fa-book-open-reader', [
            $this->row('Books Issued', $issued, null, false, 'book_issue_report.index'),
            // There is no dedicated book-return report; the loan report carries the
            // return_date column, so both counts drill into the same screen.
            $this->row('Books Returned', $returned, null, false, 'book_issue_report.index'),
        ], $issued + $returned);
    }
}
