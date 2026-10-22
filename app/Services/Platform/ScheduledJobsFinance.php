<?php

namespace App\Services\Platform;

use App\Models\Platform\PlatformScheduledTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read-only scheduled jobs for assignments and message delivery. Same contract as
 * ScheduledJobs: SELECT/count only, scoped to the task's school, one honest line back.
 * Nothing here sends a message or changes a record.
 *
 * Not registered, on purpose:
 *  - fees.collection.gateway_reconcile: fees_reconciliation holds one school's 2024
 *    import with shifted columns and masked receipt numbers; fees_collect.conciliation
 *    is 0 everywhere. There is no reliable settlement-to-receipt match to measure.
 *  - fees.defaulter.reminder_run: it would send messages.
 */
class ScheduledJobsFinance
{
    /** @return array<string,array{0:string,1:string}> */
    public static function registry(): array
    {
        return [
            'lms.assignment.due_reminder' => [self::class, 'assignmentsDueSoon'],
            'communication.campaign.delivery_reconcile' => [self::class, 'whatsappDeliveryStatus'],
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
}
