<?php

namespace App\Services\Platform;

use App\Models\Platform\PlatformScheduledTask;

/**
 * Tasks whose data the system does not record yet.
 *
 * Each of these has a catalogue entry, but the tables a real job would read do not exist or
 * lack the needed column (for example no offer window on admissions, no expiry date on staff
 * documents). They are registered so a school can switch them on and see them run, and the run
 * says plainly why there is nothing to check. They never read or change a record.
 *
 * The catalogue marks each as `disabled_by_default`, so none runs for any school until someone
 * switches it on. When the data exists, replace the entry here with a real job in the module's
 * group file.
 */
class ScheduledJobsPending
{
    /** task_key => why there is nothing to check yet */
    private const REASONS = [
        'admissions.application.document_chase' => 'document tracking is per enrolled student, not per application.',
        'admissions.confirmation.offer_expiry' => 'no admission offer or offer window is recorded.',
        'students.identity.card_batch' => 'student ID card issue is not recorded.',
        'examination.result.recompute' => 'the system does not record when results were last computed.',
        'front_desk.call_log.follow_up_scan' => 'calls are not logged with a call-back promise.',
        'hostel.mess.monthly_billing' => 'no mess charge or bill is recorded.',
        'hostel.gate_pass.overdue_return' => 'gate passes and return times are not recorded.',
        'transport.vehicle.document_expiry_scan' => 'vehicle fitness, insurance and permit dates are not recorded.',
        'transport.trip.delay_watch' => 'there is no live trip or GPS position data.',
        'inventory.stock.reorder_scan' => 'current stock is not tracked against a reorder level.',
        'compliance.retention.purge_run' => 'no retention periods are defined for records.',
        'fees.collection.gateway_reconcile' => 'there are no gateway settlements to match against.',
        'hr.staff.document_expiry_scan' => 'staff documents carry no expiry date.',
        'hr.leave.balance_accrual' => 'leave is allocated as an annual quota and monthly accrual is not configured.',
    ];

    /** @return array<string,callable(PlatformScheduledTask):string> */
    public static function registry(): array
    {
        $jobs = [];
        foreach (array_keys(self::REASONS) as $key) {
            $jobs[$key] = [self::class, 'notAvailable'];
        }

        return $jobs;
    }

    /** @return string[] */
    public static function keys(): array
    {
        return array_keys(self::REASONS);
    }

    public static function notAvailable(PlatformScheduledTask $task): string
    {
        $reason = self::REASONS[(string) $task->task_key] ?? 'the data this task needs is not recorded.';

        return 'Not available: ' . $reason . ' Nothing was checked or changed.';
    }
}
