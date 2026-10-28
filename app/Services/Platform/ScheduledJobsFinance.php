<?php

namespace App\Services\Platform;

use App\Models\Platform\PlatformScheduledTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only scheduled jobs for assignments and message delivery. Same contract as
 * ScheduledJobs: SELECT/count only, scoped to the task's school, one honest line back.
 * Nothing here sends a message or changes a record.
 *
 * Not registered, on purpose:
 *  - fees.collection.gateway_reconcile: fees_reconciliation holds one school's 2024
 *    import with shifted columns and masked receipt numbers; fees_collect.conciliation
 *    is 0 everywhere. There is no reliable settlement-to-receipt match to measure.
 *  - hr.staff.document_expiry_scan: staff_document has no expiry date column, so
 *    nothing can be said about expiry.
 *  - hr.leave.balance_accrual: leave quota is stored once a year per department
 *    (hrms_leave_allocation); there is no monthly accrual rule or stored balance.
 *
 * The finance and payroll jobs below are report only: they never message families,
 * close a day, or create a payroll run.
 */
class ScheduledJobsFinance
{
    /** @return array<string,array{0:string,1:string}> */
    public static function registry(): array
    {
        return [
            'lms.assignment.due_reminder' => [self::class, 'assignmentsDueSoon'],
            'communication.campaign.delivery_reconcile' => [self::class, 'whatsappDeliveryStatus'],
            'fees.defaulter.reminder_run' => [self::class, 'feeReminderPreview'],
            'fees.receipt.daily_close' => [self::class, 'dayCollectionPreview'],
            'hr.payroll.monthly_run' => [self::class, 'payrollPreparationStatus'],
        ];
    }

    /** Assignments due today through the next 3 days that are still marked not submitted. No reminder is sent. */
    public static function assignmentsDueSoon(PlatformScheduledTask $task): string
    {
        $q = DB::table('lms_assignment')->where('sub_institute_id', (int) $task->sub_institute_id)
            ->where('student_submission_status', 'N')
            ->whereDate('submission_date', '>=', Carbon::today())
            ->whereDate('submission_date', '<=', Carbon::today()->addDays(3));
        $count = (clone $q)->count();
        if ($count === 0) {
            return 'No assignments are due in the next 3 days and still not submitted. No reminders were sent.';
        }
        $students = (clone $q)->distinct()->count('student_id');
        $first = (clone $q)->min('submission_date');

        return "{$count} assignments due in the next 3 days are not yet submitted, across {$students} students (earliest due {$first}). No reminders were sent.";
    }

    /**
     * WhatsApp messages sent in the last 7 days, counted by the status already stored
     * on each row. Status is not re-fetched from the provider. SMS and email logs
     * keep no delivery status, so they are not covered.
     */
    public static function whatsappDeliveryStatus(PlatformScheduledTask $task): string
    {
        $rows = DB::table('whatsapp_sent_messages')->where('sub_institute_id', (int) $task->sub_institute_id)
            ->whereDate('sent_date', '>=', Carbon::today()->subDays(7))
            ->selectRaw("COALESCE(NULLIF(LOWER(SUBSTRING_INDEX(message_status, ' ', 1)), ''), 'no status') as st, COUNT(*) as c")
            ->groupBy('st')->orderByDesc('c')->get();
        if ($rows->isEmpty()) {
            return 'No WhatsApp messages were sent in the last 7 days. SMS and email logs hold no delivery status and were not checked.';
        }
        $total = $rows->sum('c');
        $parts = $rows->map(fn ($r) => "{$r->c} {$r->st}")->implode(', ');

        return "{$total} WhatsApp messages in the last 7 days by stored status: {$parts}. Status was not re-fetched from the provider; SMS and email logs hold no delivery status.";
    }

    /**
     * Report only. Instalment late dates falling in the next 7 days and already passed,
     * for the school's latest year with late dates. Late dates are per class, so who
     * still owes is not evaluated. No reminder is sent.
     */
    public static function feeReminderPreview(PlatformScheduledTask $task): string
    {
        if (! Schema::hasTable('fees_late_master') || ! Schema::hasColumn('fees_late_master', 'late_date')) {
            return 'Report only: the fee late-date table is not available, so no reminder preview was produced. No reminder was sent.';
        }
        $tenant = (int) $task->sub_institute_id;
        $syear = DB::table('fees_late_master')->where('sub_institute_id', $tenant)->max('syear');
        if (! $syear) {
            return 'Report only: no late dates are set up for this school, so there is nothing to preview. No reminder was sent.';
        }
        $base = fn () => DB::table('fees_late_master')->where('sub_institute_id', $tenant)
            ->where('syear', $syear)->where('status', '1');
        $soon = $base()->whereDate('late_date', '>=', Carbon::today())
            ->whereDate('late_date', '<=', Carbon::today()->addDays(7));
        $soonRows = (clone $soon)->count();
        $soonClasses = (clone $soon)->distinct()->count('standard_id');
        $past = $base()->whereDate('late_date', '<', Carbon::today());
        $pastRows = (clone $past)->count();
        $oldest = (clone $past)->min('late_date');

        $text = "Report only: {$soonRows} instalments across {$soonClasses} classes reach their late date in the next 7 days";
        $text .= $pastRows > 0
            ? ", and {$pastRows} are already past it (oldest {$oldest})"
            : ', and none are past it';

        return $text." for {$syear}. Which families still owe was not evaluated; no reminder was sent.";
    }

    /**
     * Report only. Today's receipts (not deleted) as a count, payment modes and school
     * total. The day is not closed or frozen and nothing is written.
     */
    public static function dayCollectionPreview(PlatformScheduledTask $task): string
    {
        if (! Schema::hasTable('fees_collect')
            || ! Schema::hasColumn('fees_collect', 'receiptdate')
            || ! Schema::hasColumn('fees_collect', 'amount')) {
            return 'Report only: the fee collection table is not available, so no day summary was produced. The day was not closed.';
        }
        $q = DB::table('fees_collect')->where('sub_institute_id', (int) $task->sub_institute_id)
            ->whereDate('receiptdate', Carbon::today());
        if (Schema::hasColumn('fees_collect', 'is_deleted')) {
            $q->where(function ($w) {
                $w->whereNull('is_deleted')->orWhere('is_deleted', '!=', 'Y');
            });
        }
        $receipts = (clone $q)->count();
        if ($receipts === 0) {
            return 'Report only: no fee receipts have been recorded today. The day was not closed.';
        }
        $modes = Schema::hasColumn('fees_collect', 'payment_mode') ? (clone $q)->distinct()->count('payment_mode') : 0;
        $total = (int) round((float) (clone $q)->sum('amount'));
        $modeText = $modes > 0 ? " across {$modes} payment modes" : '';

        return "Report only: {$receipts} fee receipts{$modeText} are recorded today, totalling {$total}. The day was not closed or frozen.";
    }

    /**
     * Report only. Active staff with no saved salary row for the current month.
     * No payroll run is created.
     */
    public static function payrollPreparationStatus(PlatformScheduledTask $task): string
    {
        foreach (['tbluser', 'employee_monthly_salary_data'] as $t) {
            if (! Schema::hasTable($t)) {
                return 'Report only: the staff or payroll table is not available, so no payroll status was produced. No payroll was prepared.';
            }
        }
        if (! Schema::hasColumn('tbluser', 'status')
            || ! Schema::hasColumn('employee_monthly_salary_data', 'month')
            || ! Schema::hasColumn('employee_monthly_salary_data', 'year')
            || ! Schema::hasColumn('employee_monthly_salary_data', 'employee_id')) {
            return 'Report only: the payroll tables lack the expected columns, so no payroll status was produced. No payroll was prepared.';
        }
        $tenant = (int) $task->sub_institute_id;
        $month = Carbon::today()->format('M');
        $year = (int) Carbon::today()->format('Y');
        $label = "{$month} {$year}";

        $active = DB::table('tbluser')->where('sub_institute_id', $tenant)->where('status', 1)->count();
        if ($active === 0) {
            return "Report only: no active staff are recorded, so payroll for {$label} has nothing to prepare. No payroll was prepared.";
        }
        $prepared = DB::table('employee_monthly_salary_data as s')
            ->join('tbluser as u', 'u.id', '=', 's.employee_id')
            ->where('s.sub_institute_id', $tenant)->where('u.sub_institute_id', $tenant)->where('u.status', 1)
            ->where('s.month', $month)->where('s.year', $year)
            ->distinct()->count('s.employee_id');
        $missing = max(0, $active - $prepared);

        if ($missing === 0) {
            return "Report only: payroll for {$label} is saved for all {$active} active staff. No payroll was prepared.";
        }

        return "Report only: payroll for {$label} is not yet prepared for {$missing} of {$active} active staff. No payroll was prepared.";
    }
}
