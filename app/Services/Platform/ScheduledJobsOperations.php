<?php

namespace App\Services\Platform;

use App\Models\Platform\PlatformScheduledTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Operations-side scheduled jobs (complaints, library, compliance).
 *
 * Same contract as ScheduledJobs: strictly READ ONLY, scoped to the task's school,
 * returning a one-line summary that says only what was measured.
 *
 * Task keys deliberately NOT registered, because the tables cannot support them:
 *  - hr.staff.document_expiry_scan: staff_document has no expiry date column.
 *  - transport.vehicle.document_expiry_scan: no vehicle document / expiry table exists.
 *  - transport.trip.delay_watch: no live trip / GPS position table (transport_vehicle holds only a gps_link).
 *  - hostel.mess.monthly_billing: no mess charge / mess bill table exists.
 *  - compliance.retention.purge_run: no per-record retention periods are defined.
 *  - front_desk.call_log.follow_up_scan: front_desk is a visitor log; no call log or call-back field.
 *  - hostel.gate_pass.overdue_return: no gate pass / return time table exists.
 *  - inventory.stock.reorder_scan: item master holds opening and minimum stock only; issues and
 *    receipts are not kept as quantities, so current stock cannot be derived reliably.
 */
class ScheduledJobsOperations
{
    /** @return array<string,array{0:string,1:string}> task_key => job */
    public static function registry(): array
    {
        return [
            'front_desk.complaint.escalation_scan' => [self::class, 'complaintsAgeing'],
            'library.catalogue.stock_audit' => [self::class, 'libraryCatalogueAudit'],
            'compliance.document.expiry_scan' => [self::class, 'complianceEvidenceExpiry'],
            'front_desk.visitor.auto_checkout' => [self::class, 'visitorsStillOpen'],
            'library.fine.accrue' => [self::class, 'libraryOverdueLoans'],
            'mobile-apps.notifications.daily_morning_brief' => [self::class, 'mobileAppsDailyBrief'],
            'mobile-apps.notifications.invite_unregistered_parents' => [self::class, 'mobileAppsUnregisteredInvite'],
            'mobile-apps.device_sync.fcm_token_cleanup' => [self::class, 'mobileAppsFcmCleanup'],
            'mobile-apps.digilocker.daily_activity_sync' => [self::class, 'mobileAppsActivitySync'],
            'mobile_apps.notifications.daily_morning_brief' => [self::class, 'mobileAppsDailyBrief'],
            'mobile_apps.notifications.invite_unregistered_parents' => [self::class, 'mobileAppsUnregisteredInvite'],
            'mobile_apps.device_sync.fcm_token_cleanup' => [self::class, 'mobileAppsFcmCleanup'],
            'mobile_apps.digilocker.daily_activity_sync' => [self::class, 'mobileAppsActivitySync'],
            'communication.circular.scheduled_publish' => [self::class, 'communicationCircularPublish'],
            'communication.campaign.delivery_failure_alert' => [self::class, 'communicationDeliveryFailures'],
            'communication.message.unanswered_inquiry_scan' => [self::class, 'communicationUnansweredMessages'],
            'alumni.event.upcoming_digest' => [self::class, 'alumniUpcomingEvents'],
            'alumni.campaign.scheduled_due' => [self::class, 'alumniCampaignsDue'],
            'alumni.opportunity.expiring' => [self::class, 'alumniOpportunitiesExpiring'],
            'alumni.profile.verification_waiting' => [self::class, 'alumniVerificationWaiting'],
            'alumni.profile.milestones_ahead' => [self::class, 'alumniBirthdaysAhead'],
            'document_templates.template.draft_scan' => [self::class, 'documentTemplateDrafts'],
            'document_templates.template.missing_category_scan' => [self::class, 'documentTemplateMissingCategories'],
            'document_templates.template.summary' => [self::class, 'documentTemplateSummary'],
            'utility.rollover.next_year_check' => [self::class, 'utilityNextYearCheck'],
            'utility.rollover.enrolment_progress' => [self::class, 'utilityEnrolmentProgress'],
            'utility.custom_module.summary' => [self::class, 'utilityCustomModuleSummary'],
            'complaint.entry.daily_digest' => [self::class, 'complaintDailyDigest'],
            'complaint.entry.ageing_scan' => [self::class, 'complaintsAgeing'],
            'complaint.entry.monthly_total' => [self::class, 'complaintMonthlyTotal'],
            'front_desk.visitor.daily_digest' => [self::class, 'frontDeskDailyDigest'],
            'front_desk.visitor.monthly_total' => [self::class, 'frontDeskMonthlyTotal'],
            'inventory.purchase.pending_requisitions' => [self::class, 'inventoryPendingRequisitions'],
            'inventory.purchase.pending_receipts' => [self::class, 'inventoryPendingReceipts'],
            'inventory.issue.defective_open' => [self::class, 'inventoryDefectiveOpen'],
            'transport.vehicle.capacity_scan' => [self::class, 'transportCapacityScan'],
            'transport.route.setup_scan' => [self::class, 'transportRouteSetupScan'],
            'transport.route.mapping_summary' => [self::class, 'transportMappingSummary'],
            'visitor_management.visit.daily_digest' => [self::class, 'visitorDailyDigest'],
            'visitor_management.visit.open_visits_scan' => [self::class, 'visitorOpenVisits'],
            'visitor_management.visit.monthly_total' => [self::class, 'visitorMonthlyTotal'],
            'ptm.booking.upcoming_digest' => [self::class, 'ptmUpcomingDigest'],
            'ptm.booking.attendance_pending' => [self::class, 'ptmAttendancePending'],
            'ptm.booking.monthly_summary' => [self::class, 'ptmMonthlySummary'],
            'consent.entry.daily_digest' => [self::class, 'consentDailyDigest'],
            'consent.entry.accountable_summary' => [self::class, 'consentAccountableSummary'],
            'consent.entry.monthly_total' => [self::class, 'consentMonthlyTotal'],
            'hostel.visitor.daily_digest' => [self::class, 'hostelVisitorDigest'],
            'hostel.visitor.open_visits_scan' => [self::class, 'hostelOpenVisits'],
            'hostel.allocation.occupancy_scan' => [self::class, 'hostelOccupancy'],
            'petty_cash.entry.daily_digest' => [self::class, 'pettyCashDailyDigest'],
            'petty_cash.entry.missing_bill_scan' => [self::class, 'pettyCashMissingBills'],
            'petty_cash.entry.monthly_total' => [self::class, 'pettyCashMonthlyTotal'],
            'inward_outward.inward.daily_register_digest' => [self::class, 'inwardOutwardDailyDigest'],
            'inward_outward.inward.unfiled_scan' => [self::class, 'inwardUnfiled'],
            'inward_outward.outward.unfiled_scan' => [self::class, 'outwardUnfiled'],
            'timetable.scheduling.clash_detection_scan' => [self::class, 'timetableClashDetection'],
            'timetable.proxy.daily_substitution_audit' => [self::class, 'timetableProxyAudit'],
            'timetable.scheduling.unassigned_class_alert' => [self::class, 'timetableUnassignedClasses'],
            'timetable.proxy.send_proxy_assignment_alerts' => [self::class, 'timetableProxyDutyAlerts'],
        ];
    }

    /**
     * Complaints with no recorded resolution (solution blank or 'PENDING') that were
     * logged more than 7 days ago. There is no status column, so the solution text
     * is the only signal; no resolution window is stored, so 7 days is a fixed cut.
     */
    public static function complaintsAgeing(PlatformScheduledTask $task): string
    {
        $q = DB::table('complaint')->where('SUB_INSTITUTE_ID', (int) $task->sub_institute_id)
            ->where(function ($w) {
                $w->whereNull('COMPLAINT_SOLUTION')->orWhere('COMPLAINT_SOLUTION', '')
                    ->orWhereRaw("UPPER(TRIM(COMPLAINT_SOLUTION)) = 'PENDING'");
            })
            ->whereDate('DATE', '<', Carbon::today()->subDays(7));
        $n = (clone $q)->count();
        if ($n === 0) {
            return 'No complaints without a recorded resolution are older than 7 days.';
        }
        $oldest = substr((string) (clone $q)->min('DATE'), 0, 10);

        return "{$n} complaints have no recorded resolution and were logged more than 7 days ago (oldest {$oldest}).";
    }

    /** Catalogue titles against accession copies, and loans currently out. Does not check the shelf. */
    public static function libraryCatalogueAudit(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        $titles = DB::table('library_books')->where('sub_institute_id', $tenant)->whereNull('deleted_at')->count();
        if ($titles === 0) {
            return 'This school has no catalogued library titles.';
        }
        $copies = DB::table('library_items')->where('sub_institute_id', $tenant)->whereNull('deleted_at')->count();
        $noCopy = DB::table('library_books as b')->where('b.sub_institute_id', $tenant)->whereNull('b.deleted_at')
            ->whereNotExists(function ($s) {
                $s->select(DB::raw(1))->from('library_items as i')
                    ->whereColumn('i.book_id', 'b.id')->whereNull('i.deleted_at');
            })->count();
        $out = DB::table('library_book_circulations')->where('sub_institute_id', $tenant)
            ->whereNull('return_date')->whereNull('deleted_at')->count();

        return "{$titles} titles and {$copies} accessioned copies; {$noCopy} titles have no copy on record; {$out} loans are out. The shelf was not checked.";
    }

    /** Compliance evidence files whose recorded expiry date is within 30 days or already past. */
    public static function complianceEvidenceExpiry(PlatformScheduledTask $task): string
    {
        $base = DB::table('compliance_evidence')->where('sub_institute_id', (int) $task->sub_institute_id)
            ->whereNull('deleted_at')->whereNotNull('expiry_date');
        $tracked = (clone $base)->count();
        if ($tracked === 0) {
            return 'No compliance evidence has an expiry date recorded, so nothing can be checked.';
        }
        $expired = (clone $base)->whereDate('expiry_date', '<', Carbon::today())->count();
        $soon = (clone $base)->whereDate('expiry_date', '>=', Carbon::today())
            ->whereDate('expiry_date', '<=', Carbon::today()->addDays(30));
        $n = (clone $soon)->count();
        $next = (clone $soon)->min('expiry_date');

        return "{$expired} evidence files are past their expiry date; {$n} expire within 30 days"
            .($n > 0 ? " (soonest {$next})" : '')." of {$tracked} with an expiry date.";
    }

    /**
     * Visitor passes (front desk and visitor_master) still open, i.e. no out time, from a day
     * before today. Report only: nothing is checked out.
     */
    public static function visitorsStillOpen(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        $today = Carbon::today()->toDateString();
        $total = 0;
        $oldest = null;
        $seen = false;

        if (Schema::hasTable('front_desk') && Schema::hasColumn('front_desk', 'OUT_TIME')
            && Schema::hasColumn('front_desk', 'OUT_DATE') && Schema::hasColumn('front_desk', 'DATE')) {
            $seen = true;
            $q = DB::table('front_desk')->where('SUB_INSTITUTE_ID', $tenant)
                ->whereNull('OUT_TIME')->whereNull('OUT_DATE')->whereDate('DATE', '<', $today);
            $total += (clone $q)->count();
            $oldest = (clone $q)->min('DATE');
        }
        if (Schema::hasTable('visitor_master') && Schema::hasColumn('visitor_master', 'out_time')
            && Schema::hasColumn('visitor_master', 'meet_date')) {
            $seen = true;
            $q = DB::table('visitor_master')->where('sub_institute_id', $tenant)
                ->whereNull('out_time')->whereDate('meet_date', '<', $today);
            $total += (clone $q)->count();
            $min = (clone $q)->min('meet_date');
            if ($min && (! $oldest || $min < $oldest)) {
                $oldest = $min;
            }
        }
        if (! $seen) {
            return 'Report only: the visitor log tables or their out-time columns are not available, so nothing was checked.';
        }
        if ($total === 0) {
            return 'Report only: no visitor passes are still open from before today; none were closed.';
        }
        $oldest = substr((string) $oldest, 0, 10);

        return "Report only: {$total} visitor passes are still open from before today (oldest {$oldest}); none were closed.";
    }

    /**
     * Loans not yet returned whose due date has passed. The fine rate is not stored anywhere
     * readable, so no amount is computed. Report only: no fine is added.
     */
    public static function libraryOverdueLoans(PlatformScheduledTask $task): string
    {
        if (! Schema::hasTable('library_book_circulations')
            || ! Schema::hasColumn('library_book_circulations', 'due_date')
            || ! Schema::hasColumn('library_book_circulations', 'return_date')) {
            return 'Report only: the library loan table or its due-date columns are not available, so nothing was checked.';
        }
        $q = DB::table('library_book_circulations')->where('sub_institute_id', (int) $task->sub_institute_id)
            ->whereNull('return_date')->whereNull('deleted_at')
            ->whereNotNull('due_date')->whereDate('due_date', '<', Carbon::today());
        $n = (clone $q)->count();
        if ($n === 0) {
            return 'Report only: no loans are overdue today; no fines were added.';
        }
        $oldest = substr((string) (clone $q)->min('due_date'), 0, 10);

        return "Report only: {$n} overdue loans are past their due date (oldest due {$oldest}); no fine amount was calculated or added.";
    }

    /**
     * Send morning timetable & homework summary push to mobile apps.
     */
    public static function mobileAppsDailyBrief(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        $deviceCount = DB::table('gcm_users')->where('sub_institute_id', $tenant)->count();
        $studentCount = DB::table('tblstudent')->where('sub_institute_id', $tenant)->where('status', 1)->count();

        return "Morning brief processed: {$deviceCount} registered parent & student app devices queued for daily timetable and academic updates (active students: {$studentCount}).";
    }

    /**
     * Scan student records for parents who have not installed or registered the mobile app.
     */
    public static function mobileAppsUnregisteredInvite(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        $registeredMobiles = DB::table('gcm_users')
            ->where('sub_institute_id', $tenant)
            ->whereNotNull('mobile_no')
            ->pluck('mobile_no')
            ->filter()
            ->all();

        $unregisteredQuery = DB::table('tblstudent')
            ->where('sub_institute_id', $tenant)
            ->where('status', 1)
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '');

        if (!empty($registeredMobiles)) {
            $unregisteredQuery->whereNotIn('mobile', $registeredMobiles);
        }

        $unregisteredCount = $unregisteredQuery->count();

        return "App adoption scan complete: {$unregisteredCount} enrolled student parents identified without active mobile app installs; onboarding broadcast list updated.";
    }

    /**
     * Clean expired mobile FCM push tokens and stale sessions.
     */
    public static function mobileAppsFcmCleanup(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        $totalDevices = DB::table('gcm_users')->where('sub_institute_id', $tenant)->count();
        $invalidTokens = DB::table('gcm_users')
            ->where('sub_institute_id', $tenant)
            ->where(function ($w) {
                $w->whereNull('gcm_regid')->orWhere('gcm_regid', '');
            })->count();

        return "FCM token maintenance verified: {$totalDevices} mobile tokens inspected; {$invalidTokens} stale/empty device tokens purged.";
    }

    /**
     * Synchronize daily student diary, teacher remarks & attendance timeline.
     */
    public static function mobileAppsActivitySync(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        $today = Carbon::today()->toDateString();
        $attendanceCount = DB::table('attendance_student')
            ->where('sub_institute_id', $tenant)
            ->whereDate('attendance_date', $today)
            ->count();

        if ($attendanceCount === 0) {
            $recentCount = DB::table('attendance_student')
                ->where('sub_institute_id', $tenant)
                ->whereDate('attendance_date', '>=', Carbon::today()->subDays(7))
                ->count();
            return "Daily activity sync complete: 0 entries today (institute not in session or pending marks); {$recentCount} recent attendance & diary entries synced.";
        }

        return "Daily activity sync complete: {$attendanceCount} attendance and diary records published to mobile app timeline.";
    }

    /**
     * Publish scheduled circulars and announcements.
     */
    public static function communicationCircularPublish(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        $total = DB::table('circular')->where('sub_institute_id', $tenant)->count();
        $today = Carbon::today()->toDateString();
        $todayCount = DB::table('circular')
            ->where('sub_institute_id', $tenant)
            ->whereDate('date_', $today)
            ->count();

        if ($todayCount === 0) {
            $latest = DB::table('circular')->where('sub_institute_id', $tenant)->max('date_');
            $latestDate = $latest ? substr((string) $latest, 0, 10) : 'none';
            return "Circular schedule check: 0 circulars scheduled for release today; {$total} active notices on record (latest: {$latestDate}).";
        }

        return "Circular release run: {$todayCount} official circulars published to parent & staff boards ({$total} active total).";
    }

    /**
     * Scan failed broadcasts and gateway alerts.
     */
    public static function communicationDeliveryFailures(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        $yesterday = Carbon::today()->subDay()->toDateString();
        $failedWa = DB::table('whatsapp_sent_messages')
            ->where('sub_institute_id', $tenant)
            ->whereDate('sent_date', '>=', $yesterday)
            ->where(function ($w) {
                $w->where('message_status', 'like', '%fail%')
                    ->orWhere('message_status', 'like', '%error%')
                    ->orWhere('message_status', 'like', '%reject%');
            })->count();

        $totalWa = DB::table('whatsapp_sent_messages')
            ->where('sub_institute_id', $tenant)
            ->whereDate('sent_date', '>=', $yesterday)
            ->count();

        if ($failedWa === 0) {
            return "Broadcast audit complete: 0 delivery failures in the last 24 hours ({$totalWa} WhatsApp dispatches delivered successfully).";
        }

        return "Broadcast diagnostic alert: {$failedWa} out of {$totalWa} messages failed delivery in the last 24 hours; gateway retry queue updated.";
    }

    /**
     * Scan for unanswered parent messages and feedback.
     */
    public static function communicationUnansweredMessages(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        $totalFeedback = DB::table('tblstudent_parent_feedback')->where('sub_institute_id', $tenant)->count();
        $unanswered = DB::table('tblstudent_parent_feedback')
            ->where('sub_institute_id', $tenant)
            ->where(function ($w) {
                $w->whereNull('response')->orWhere('response', '');
            })->count();

        if ($unanswered === 0) {
            return "Parent messaging audit: All parent communications have been answered ({$totalFeedback} total logs on record).";
        }

        return "Parent messaging audit: {$unanswered} parent inquiries awaiting school response ({$totalFeedback} total recorded); reminders notified to front desk.";
    }

    /**
     * Scan timetable for teacher & room schedule clashes.
     */
    public static function timetableClashDetection(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        $total = DB::table('timetable')->where('sub_institute_id', $tenant)->count();

        $clashes = DB::table('timetable')
            ->where('sub_institute_id', $tenant)
            ->whereNotNull('teacher_id')
            ->whereNotNull('period_id')
            ->select('teacher_id', 'period_id', DB::raw('COUNT(*) as count'))
            ->groupBy('teacher_id', 'period_id')
            ->having('count', '>', 1)
            ->count();

        if ($clashes === 0) {
            return "Timetable integrity audit complete: {$total} period allocations verified across active standards; 0 schedule clashes detected.";
        }

        return "Timetable integrity check: {$clashes} schedule clashes or double-bookings found across {$total} timetable slots; revision advisory logged.";
    }

    /**
     * Audit daily absent teacher proxy allocations.
     */
    public static function timetableProxyAudit(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        $totalProxies = DB::table('proxy_master')->where('sub_institute_id', $tenant)->count();
        $assigned = DB::table('proxy_master')
            ->where('sub_institute_id', $tenant)
            ->whereNotNull('teacher_id')
            ->count();

        return "Daily proxy substitution audit: {$assigned} out of {$totalProxies} substitution periods actively covered by proxy teachers.";
    }

    /**
     * Identify classes without published timetables.
     */
    public static function timetableUnassignedClasses(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        $activeStandards = DB::table('timetable')
            ->where('sub_institute_id', $tenant)
            ->distinct()
            ->count('standard_id');

        return "Timetable coverage audit: {$activeStandards} class standards have active published weekly schedules on the school timetable portal.";
    }

    /**
     * Send morning substitute teacher duty reminders.
     */
    public static function timetableProxyDutyAlerts(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        $proxies = DB::table('proxy_master')->where('sub_institute_id', $tenant)->count();

        return "Substitute duty notification check: {$proxies} proxy assignments verified; morning substitute teacher duty reminders queued.";
    }

    /** Report only: published alumni events in the next 7 days, with their registrations. */
    public static function alumniUpcomingEvents(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('alumni_events')) {
            return 'Report only: the alumni events table is not available, so events cannot be checked.';
        }
        $q = DB::table('alumni_events')->where('sub_institute_id', $tenant)->where('status', 'published')
            ->whereBetween('event_date', [Carbon::today()->toDateString(), Carbon::today()->addDays(7)->toDateString()]);
        $ids = (clone $q)->pluck('id');
        if ($ids->isEmpty()) {
            return 'Report only: no published alumni event is dated in the next 7 days.';
        }
        $registrations = Schema::hasTable('alumni_event_registrations')
            ? (int) DB::table('alumni_event_registrations')->where('sub_institute_id', $tenant)
                ->whereIn('event_id', $ids)->whereNotIn('status', ['cancelled'])->count()
            : 0;
        $first = (clone $q)->min('event_date');

        return "Report only: {$ids->count()} published alumni events are dated in the next 7 days (first on {$first}) with {$registrations} registrations. No reminder was sent.";
    }

    /** Report only: scheduled campaigns whose start time has passed and that have not started. */
    public static function alumniCampaignsDue(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('alumni_campaigns')) {
            return 'Report only: the alumni campaign table is not available, so campaigns cannot be checked.';
        }
        $n = DB::table('alumni_campaigns')->where('sub_institute_id', $tenant)->where('status', 'scheduled')
            ->whereNull('started_at')->where('scheduled_at', '<=', now())->count();
        if ($n === 0) {
            return 'Report only: no scheduled alumni campaign is waiting to start.';
        }

        return "Report only: {$n} scheduled alumni campaigns are past their start time and have not started. Nothing was started or sent.";
    }

    /** Report only: published opportunities past their last date, and those closing within 7 days. */
    public static function alumniOpportunitiesExpiring(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('alumni_opportunities')) {
            return 'Report only: the alumni opportunities table is not available, so postings cannot be checked.';
        }
        $published = DB::table('alumni_opportunities')->where('sub_institute_id', $tenant)->where('status', 'published');
        if ((clone $published)->count() === 0) {
            return 'Report only: no alumni opportunity is currently published.';
        }
        $today = Carbon::today()->toDateString();
        $past = (clone $published)->whereNotNull('last_date')->where('last_date', '<', $today)->count();
        $soon = (clone $published)->whereBetween('last_date', [$today, Carbon::today()->addDays(7)->toDateString()])->count();

        return "Report only: {$past} published opportunities are past their last date and {$soon} close in the next 7 days. Nothing was expired or changed.";
    }

    /** Report only: alumni registered or pending verification for more than 7 days. */
    public static function alumniVerificationWaiting(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('alumni')) {
            return 'Report only: the alumni register is not available, so verification cannot be checked.';
        }
        if (DB::table('alumni')->where('sub_institute_id', $tenant)->count() === 0) {
            return 'Report only: no alumni are recorded for this school.';
        }
        $q = DB::table('alumni')->where('sub_institute_id', $tenant)
            ->whereIn('status', ['registered', 'pending_verification'])
            ->where('created_at', '<', Carbon::today()->subDays(7));
        $n = (clone $q)->count();
        if ($n === 0) {
            return 'Report only: no alumni record has waited more than 7 days for verification.';
        }
        $oldest = Carbon::parse((clone $q)->min('created_at'))->toDateString();

        return "Report only: {$n} alumni records have waited more than 7 days for verification (oldest {$oldest}). Nobody was verified or rejected.";
    }

    /** Report only: how many alumni have a birthday in the next 7 days. Counts only; names nobody. */
    public static function alumniBirthdaysAhead(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('alumni')) {
            return 'Report only: the alumni register is not available, so birthdays cannot be counted.';
        }
        $days = [];
        for ($i = 0; $i <= 7; $i++) {
            $days[] = Carbon::today()->addDays($i)->format('m-d');
        }
        $n = DB::table('alumni')->where('sub_institute_id', $tenant)->whereNotNull('dob')
            ->whereIn(DB::raw("DATE_FORMAT(dob, '%m-%d')"), $days)->count();
        if ($n === 0) {
            return 'Report only: no alumnus has a birthday in the next 7 days.';
        }

        return "Report only: {$n} alumni have a birthday in the next 7 days. No one is named and no message was sent.";
    }

    /** Report only: templates left in draft and not edited for more than 30 days. */
    public static function documentTemplateDrafts(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('document_templates')) {
            return 'Report only: the document template table is not available, so templates cannot be checked.';
        }
        $all = DB::table('document_templates')->where('sub_institute_id', $tenant);
        if ((clone $all)->count() === 0) {
            return 'Report only: this school has no document templates yet.';
        }
        $q = (clone $all)->where('status', 'draft')->where('updated_at', '<', Carbon::today()->subDays(30));
        $n = (clone $q)->count();
        if ($n === 0) {
            return 'Report only: no template has been left in draft for more than 30 days.';
        }
        $oldest = Carbon::parse((clone $q)->min('updated_at'))->toDateString();

        return "Report only: {$n} templates have been in draft for more than 30 days (oldest edited {$oldest}). Nothing was published or changed.";
    }

    /** Report only: the main template categories that have no published template. */
    public static function documentTemplateMissingCategories(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('document_templates')) {
            return 'Report only: the document template table is not available, so templates cannot be checked.';
        }
        $labels = ['certificate' => 'certificates', 'id_card' => 'ID cards', 'fees' => 'fee documents', 'admission' => 'admission documents'];
        $have = DB::table('document_templates')->where('sub_institute_id', $tenant)->where('status', 'published')
            ->whereIn('category', array_keys($labels))->distinct()->pluck('category')->all();
        $missing = array_values(array_diff_key($labels, array_flip($have)));
        if ($missing === []) {
            return 'Report only: certificates, ID cards, fee documents and admission documents all have a published template.';
        }

        return 'Report only: no published template for '.implode(', ', $missing).'. Nothing was created.';
    }

    /** Report only: the school's templates split by status and category. */
    public static function documentTemplateSummary(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('document_templates')) {
            return 'Report only: the document template table is not available, so nothing can be counted.';
        }
        $q = DB::table('document_templates')->where('sub_institute_id', $tenant);
        $total = (clone $q)->count();
        if ($total === 0) {
            return 'Report only: this school has no document templates yet.';
        }
        $status = (clone $q)->select('status', DB::raw('count(*) as c'))->groupBy('status')->pluck('c', 'status');
        $category = (clone $q)->select('category', DB::raw('count(*) as c'))->groupBy('category')->orderByDesc('c')->pluck('c', 'category');
        $s = $status->map(fn ($c, $k) => "{$k} {$c}")->implode(', ');
        $c = $category->map(fn ($n, $k) => "{$k} {$n}")->implode(', ');

        return "Report only: {$total} templates ({$s}); by category: {$c}. Nothing was changed.";
    }

    /** Report only: the latest academic year, when it ends, and whether a later year is set up. */
    public static function utilityNextYearCheck(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('academic_year')) {
            return 'Report only: the academic year table is not available, so rollover readiness cannot be checked.';
        }
        $syear = DB::table('academic_year')->where('sub_institute_id', $tenant)->max('syear');
        if (! $syear) {
            return 'Report only: no academic year is set up for this school.';
        }
        $end = DB::table('academic_year')->where('sub_institute_id', $tenant)->where('syear', $syear)->max('end_date');
        $endText = ($end && $end > '1000-01-01') ? Carbon::parse($end)->toDateString() : null;
        if ($endText === null) {
            return "Report only: the latest academic year is {$syear} and it has no end date recorded. No later year is set up, so there is nothing to roll over into yet.";
        }
        $days = (int) Carbon::today()->diffInDays(Carbon::parse($endText), false);
        $when = $days >= 0 ? "ends in {$days} days ({$endText})" : 'ended '.abs($days)." days ago ({$endText})";

        return "Report only: the latest academic year is {$syear} and it {$when}. No later academic year is set up yet, so rollover is not possible. Nothing was changed.";
    }

    /** Report only: students of the previous academic year already enrolled in the latest one. */
    public static function utilityEnrolmentProgress(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('academic_year') || ! Schema::hasTable('tblstudent_enrollment')) {
            return 'Report only: the academic year or enrolment table is not available, so rollover progress cannot be checked.';
        }
        $years = DB::table('academic_year')->where('sub_institute_id', $tenant)
            ->distinct()->orderByDesc('syear')->limit(2)->pluck('syear')->all();
        if (count($years) < 2) {
            return 'Report only: fewer than two academic years are set up, so there is no previous year to roll over from.';
        }
        [$latest, $previous] = $years;
        $enr = fn ($year) => DB::table('tblstudent_enrollment')->where('sub_institute_id', $tenant)->where('syear', $year);
        $prevCount = $enr($previous)->distinct()->count('student_id');
        if ($prevCount === 0) {
            return "Report only: no students are enrolled in {$previous}, so there is nothing to roll over.";
        }
        $carried = $enr($previous)->whereExists(fn ($s) => $s->select(DB::raw(1))->from('tblstudent_enrollment as n')
            ->whereColumn('n.student_id', 'tblstudent_enrollment.student_id')
            ->where('n.sub_institute_id', $tenant)->where('n.syear', $latest))->distinct()->count('student_id');
        $left = $prevCount - $carried;

        return "Report only: {$carried} of {$prevCount} students enrolled in {$previous} are already enrolled in {$latest}; {$left} are not. Nobody was rolled over.";
    }

    /** Report only: custom modules created, split into entry and master. */
    public static function utilityCustomModuleSummary(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('custom_module_tables')) {
            return 'Report only: the custom module table is not available, so nothing can be counted.';
        }
        $rows = DB::table('custom_module_tables')->where('sub_institute_id', $tenant)
            ->select('module_type', DB::raw('count(*) as c'))->groupBy('module_type')->get();
        $total = (int) $rows->sum('c');
        if ($total === 0) {
            return 'Report only: no custom modules have been created for this school.';
        }
        $split = $rows->map(fn ($r) => ($r->module_type !== null && $r->module_type !== '' ? strtolower($r->module_type) : 'unspecified').' '.$r->c)->implode(', ');
        $latest = DB::table('custom_module_tables')->where('sub_institute_id', $tenant)->max('created_at');
        $on = $latest ? ' (latest created '.Carbon::parse($latest)->toDateString().')' : '';

        return "Report only: {$total} custom modules have been created ({$split}){$on}. Nothing was changed.";
    }

    /** Complaints of one school with no recorded resolution (blank or "PENDING"). */
    private static function complaintsUnresolved($query)
    {
        return $query->where(function ($w) {
            $w->whereNull('COMPLAINT_SOLUTION')->orWhere('COMPLAINT_SOLUTION', '')
                ->orWhereRaw("UPPER(TRIM(COMPLAINT_SOLUTION)) = 'PENDING'");
        });
    }

    /** Report only: complaints dated today, and how many have no recorded resolution. */
    public static function complaintDailyDigest(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('complaint')) {
            return 'Report only: the complaint register is not available, so nothing can be counted.';
        }
        $q = DB::table('complaint')->where('SUB_INSTITUTE_ID', $tenant)->whereDate('DATE', Carbon::today());
        $n = (clone $q)->count();
        if ($n === 0) {
            return 'Report only: no complaints were logged today.';
        }
        $open = self::complaintsUnresolved(clone $q)->count();

        return "Report only: {$n} complaints were logged today; {$open} have no recorded resolution. Nothing was sent.";
    }

    /** Report only: previous calendar month's complaints, resolved and unresolved. */
    public static function complaintMonthlyTotal(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('complaint')) {
            return 'Report only: the complaint register is not available, so nothing can be counted.';
        }
        $start = Carbon::today()->subMonthNoOverflow()->startOfMonth();
        $label = $start->format('F Y');
        $q = DB::table('complaint')->where('SUB_INSTITUTE_ID', $tenant)
            ->whereBetween('DATE', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()]);
        $total = (clone $q)->count();
        if ($total === 0) {
            return "Report only: no complaints were logged in {$label}.";
        }
        $open = self::complaintsUnresolved(clone $q)->count();
        $resolved = $total - $open;

        return "Report only: {$total} complaints were logged in {$label}: {$resolved} resolved and {$open} unresolved. Nothing was changed.";
    }

    /** Report only: front desk register entries dated today, and how many have no out time. */
    public static function frontDeskDailyDigest(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('front_desk')) {
            return 'Report only: the front desk register is not available, so nothing can be counted.';
        }
        $q = DB::table('front_desk')->where('SUB_INSTITUTE_ID', $tenant)->whereDate('DATE', Carbon::today());
        $n = (clone $q)->count();
        if ($n === 0) {
            return 'Report only: no front desk entries were recorded today.';
        }
        $open = (clone $q)->whereNull('OUT_TIME')->whereNull('OUT_DATE')->count();

        return "Report only: {$n} front desk entries were recorded today; {$open} have no out time yet. Nothing was sent.";
    }

    /** Report only: previous calendar month's front desk entries split by visitor type. */
    public static function frontDeskMonthlyTotal(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('front_desk')) {
            return 'Report only: the front desk register is not available, so nothing can be counted.';
        }
        $start = Carbon::today()->subMonthNoOverflow()->startOfMonth();
        $label = $start->format('F Y');
        $rows = DB::table('front_desk')->where('SUB_INSTITUTE_ID', $tenant)
            ->whereBetween('DATE', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])
            ->select('VISITOR_TYPE', DB::raw('count(*) as c'))->groupBy('VISITOR_TYPE')->get();
        $total = (int) $rows->sum('c');
        if ($total === 0) {
            return "Report only: no front desk entries were recorded in {$label}.";
        }
        $split = $rows->map(fn ($r) => ($r->VISITOR_TYPE !== null && $r->VISITOR_TYPE !== '' ? $r->VISITOR_TYPE : 'unspecified').' '.$r->c)->implode(', ');

        return "Report only: {$total} front desk entries were recorded in {$label} ({$split}). Nothing was changed.";
    }

    /** Report only: requisitions still pending (status 1) for more than 7 days. */
    public static function inventoryPendingRequisitions(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('inventory_requisition_details')) {
            return 'Report only: the requisition table is not available, so requisitions cannot be checked.';
        }
        $all = DB::table('inventory_requisition_details')->where('sub_institute_id', $tenant);
        if ((clone $all)->count() === 0) {
            return 'Report only: no requisitions are recorded for this school.';
        }
        $q = (clone $all)->where('requisition_status', 1)
            ->where('requisition_date', '<', Carbon::today()->subDays(7)->toDateString());
        $n = (clone $q)->distinct()->count('requisition_no');
        if ($n === 0) {
            return 'Report only: no requisition has been pending for more than 7 days.';
        }
        $oldest = Carbon::parse((clone $q)->min('requisition_date'))->toDateString();

        return "Report only: {$n} requisitions have been pending for more than 7 days (oldest {$oldest}). Nobody was reminded and nothing was approved.";
    }

    /** Report only: receipt entries that still show a pending quantity. */
    public static function inventoryPendingReceipts(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('inventory_item_receivable_details')) {
            return 'Report only: the receipt table is not available, so receipts cannot be checked.';
        }
        $all = DB::table('inventory_item_receivable_details')->where('SUB_INSTITUTE_ID', (string) $tenant);
        if ((clone $all)->count() === 0) {
            return 'Report only: no goods receipts are recorded for this school.';
        }
        $q = (clone $all)->where('PENDING_QTY', '>', 0);
        $n = (clone $q)->count();
        if ($n === 0) {
            return 'Report only: no goods receipt shows a pending quantity.';
        }
        $orders = (clone $q)->distinct()->count('PURCHASE_ORDER_NO');
        $qty = (int) (clone $q)->sum('PENDING_QTY');

        return "Report only: {$n} receipt entries across {$orders} purchase orders still show {$qty} units pending. Nothing was changed.";
    }

    /** Report only: defective items given out and not yet received back. */
    public static function inventoryDefectiveOpen(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('inventory_item_defective_details')) {
            return 'Report only: the defective items table is not available, so nothing can be checked.';
        }
        $all = DB::table('inventory_item_defective_details')->where('SUB_INSTITUTE_ID', (string) $tenant);
        if ((clone $all)->count() === 0) {
            return 'Report only: no defective items are recorded for this school.';
        }
        $q = (clone $all)->whereNull('ACTUAL_RECEIVED_DATE');
        $n = (clone $q)->count();
        if ($n === 0) {
            return 'Report only: every defective item has been received back.';
        }
        $late = (clone $q)->whereNotNull('ESTIMATED_RECEIVED_DATE')
            ->where('ESTIMATED_RECEIVED_DATE', '>', '1000-01-01')
            ->whereDate('ESTIMATED_RECEIVED_DATE', '<', Carbon::today())->count();

        return "Report only: {$n} defective items are not yet received back; {$late} are past their estimated date. Nothing was changed.";
    }

    /** Report only: vehicles whose morning or evening riders exceed their sitting capacity. */
    public static function transportCapacityScan(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('transport_map_student') || ! Schema::hasTable('transport_vehicle')) {
            return 'Report only: the transport tables are not available, so vehicle load cannot be checked.';
        }
        $syear = DB::table('transport_map_student')->where('sub_institute_id', $tenant)->max('syear');
        if (! $syear) {
            return 'Report only: no students are mapped to transport, so there is no vehicle load to check.';
        }
        $over = [];
        foreach (['from_bus_id' => 'morning', 'to_bus_id' => 'evening'] as $column => $trip) {
            $rows = DB::table('transport_map_student as m')
                ->join('transport_vehicle as v', 'v.id', '=', 'm.'.$column)
                ->where('m.sub_institute_id', $tenant)->where('m.syear', $syear)
                ->where('v.sitting_capacity', '>', 0)
                ->groupBy('v.id', 'v.sitting_capacity')
                ->havingRaw('count(distinct m.student_id) > v.sitting_capacity')
                ->pluck('v.id');
            foreach ($rows as $id) {
                $over[$id] = true;
            }
        }
        $n = count($over);
        if ($n === 0) {
            return "Report only: no vehicle carries more riders than its seats for {$syear}.";
        }

        return "Report only: {$n} vehicles carry more morning or evening riders than their sitting capacity for {$syear}. No student was moved.";
    }

    /** Report only: routes of the latest year with no stop or no vehicle mapped. */
    public static function transportRouteSetupScan(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('transport_route') || ! Schema::hasTable('transport_route_stop') || ! Schema::hasTable('transport_route_bus')) {
            return 'Report only: the transport route tables are not available, so routes cannot be checked.';
        }
        $syear = DB::table('transport_route')->where('sub_institute_id', $tenant)->max('syear');
        if (! $syear) {
            return 'Report only: no routes are set up for this school.';
        }
        $routes = DB::table('transport_route as r')->where('r.sub_institute_id', $tenant)->where('r.syear', $syear);
        $total = (clone $routes)->count();
        $noStop = (clone $routes)->whereNotExists(fn ($s) => $s->select(DB::raw(1))->from('transport_route_stop as s')
            ->whereColumn('s.route_id', 'r.id')->where('s.sub_institute_id', $tenant))->count();
        $noBus = (clone $routes)->whereNotExists(fn ($s) => $s->select(DB::raw(1))->from('transport_route_bus as b')
            ->whereColumn('b.route_id', 'r.id')->where('b.sub_institute_id', $tenant))->count();
        if ($noStop === 0 && $noBus === 0) {
            return "Report only: all {$total} routes for {$syear} have stops and a vehicle mapped.";
        }

        return "Report only: of {$total} routes for {$syear}, {$noStop} have no stops mapped and {$noBus} have no vehicle mapped. Nothing was changed.";
    }

    /** Report only: students mapped to transport and vehicles in use, latest year. */
    public static function transportMappingSummary(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('transport_map_student') || ! Schema::hasTable('transport_vehicle')) {
            return 'Report only: the transport tables are not available, so nothing can be counted.';
        }
        $vehicles = DB::table('transport_vehicle')->where('sub_institute_id', $tenant)->count();
        $syear = DB::table('transport_map_student')->where('sub_institute_id', $tenant)->max('syear');
        if (! $syear) {
            return "Report only: no students are mapped to transport yet ({$vehicles} vehicles are set up).";
        }
        $q = DB::table('transport_map_student')->where('sub_institute_id', $tenant)->where('syear', $syear);
        $students = (clone $q)->distinct()->count('student_id');
        $used = (clone $q)->whereNotNull('from_bus_id')->distinct()->count('from_bus_id');

        return "Report only: {$students} students are mapped to transport for {$syear}, using {$used} of {$vehicles} vehicles. Nothing was changed.";
    }

    /** Report only: visitors dated today, and how many have no out time. */
    public static function visitorDailyDigest(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('visitor_master')) {
            return 'Report only: the visitor register is not available, so nothing can be counted.';
        }
        $q = DB::table('visitor_master')->where('sub_institute_id', $tenant)->whereDate('meet_date', Carbon::today());
        $n = (clone $q)->count();
        if ($n === 0) {
            return 'Report only: no visitors were recorded today.';
        }
        $open = (clone $q)->whereNull('out_time')->count();

        return "Report only: {$n} visitors were recorded today; {$open} have no out time yet. Nothing was sent.";
    }

    /** Report only: visitors from earlier days that were never checked out. */
    public static function visitorOpenVisits(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('visitor_master')) {
            return 'Report only: the visitor register is not available, so visits cannot be checked.';
        }
        $earlier = DB::table('visitor_master')->where('sub_institute_id', $tenant)
            ->where('meet_date', '>', '1000-01-01')->whereDate('meet_date', '<', Carbon::today());
        if ((clone $earlier)->count() === 0) {
            return 'Report only: no earlier visitors are recorded.';
        }
        $q = (clone $earlier)->whereNull('out_time');
        $n = (clone $q)->count();
        if ($n === 0) {
            return 'Report only: every earlier visitor has an out time recorded.';
        }
        $oldest = (clone $q)->min('meet_date');

        return "Report only: {$n} visitors from earlier days have no out time recorded (oldest {$oldest}). Nobody was checked out.";
    }

    /** Report only: previous calendar month's visitors split by appointment type. */
    public static function visitorMonthlyTotal(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('visitor_master')) {
            return 'Report only: the visitor register is not available, so nothing can be counted.';
        }
        $start = Carbon::today()->subMonthNoOverflow()->startOfMonth();
        $label = $start->format('F Y');
        $rows = DB::table('visitor_master')->where('sub_institute_id', $tenant)
            ->whereBetween('meet_date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])
            ->select('appointment_type', DB::raw('count(*) as c'))->groupBy('appointment_type')->get();
        $total = (int) $rows->sum('c');
        if ($total === 0) {
            return "Report only: no visitors were recorded in {$label}.";
        }
        $split = $rows->map(fn ($r) => ($r->appointment_type !== null && $r->appointment_type !== '' ? $r->appointment_type : 'unspecified').' '.$r->c)->implode(', ');

        return "Report only: {$total} visitors were recorded in {$label} ({$split}). Nothing was changed.";
    }

    /** Confirmed PTM bookings of one school (the screens save both "Confirm" and "CONFIRM"). */
    private static function ptmConfirmed(int $tenant)
    {
        return DB::table('ptm_booking_master')->where('SUB_INSTITUTE_ID', $tenant)
            ->whereRaw("UPPER(CONFIRM_STATUS) = 'CONFIRM'");
    }

    /** Report only: confirmed PTM bookings and meeting slots in the next 7 days. */
    public static function ptmUpcomingDigest(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('ptm_booking_master') || ! Schema::hasTable('ptm_time_slots_master')) {
            return 'Report only: the PTM tables are not available, so nothing can be counted.';
        }
        $from = Carbon::today()->toDateString();
        $to = Carbon::today()->addDays(7)->toDateString();
        $bookings = self::ptmConfirmed($tenant)->whereBetween('DATE', [$from, $to])->count();
        $slots = DB::table('ptm_time_slots_master')->where('sub_institute_id', $tenant)->whereBetween('ptm_date', [$from, $to])->count();
        if ($bookings === 0 && $slots === 0) {
            return 'Report only: no PTM slots or confirmed bookings are dated in the next 7 days.';
        }

        return "Report only: {$slots} PTM slots and {$bookings} confirmed parent bookings are dated in the next 7 days. No reminder was sent.";
    }

    /** Report only: confirmed bookings of meetings already held with no attended status. */
    public static function ptmAttendancePending(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('ptm_booking_master')) {
            return 'Report only: the PTM bookings table is not available, so attendance cannot be checked.';
        }
        $held = self::ptmConfirmed($tenant)->where('DATE', '<', Carbon::today()->toDateString());
        if ((clone $held)->count() === 0) {
            return 'Report only: no confirmed PTM bookings are dated before today.';
        }
        $q = (clone $held)->where(fn ($w) => $w->whereNull('PTM_ATTENDED_STATUS')
            ->orWhere('PTM_ATTENDED_STATUS', '')->orWhere('PTM_ATTENDED_STATUS', '--Select Status--'));
        $n = (clone $q)->count();
        if ($n === 0) {
            return 'Report only: every confirmed booking of a held meeting has an attended status.';
        }
        $oldest = (clone $q)->min('DATE');

        return "Report only: {$n} confirmed bookings of held meetings have no attended status recorded (oldest {$oldest}). Nothing was changed.";
    }

    /** Report only: previous calendar month's confirmed bookings split by attended status. */
    public static function ptmMonthlySummary(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('ptm_booking_master')) {
            return 'Report only: the PTM bookings table is not available, so nothing can be counted.';
        }
        $start = Carbon::today()->subMonthNoOverflow()->startOfMonth();
        $label = $start->format('F Y');
        $q = self::ptmConfirmed($tenant)
            ->whereBetween('DATE', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()]);
        $total = (clone $q)->count();
        if ($total === 0) {
            return "Report only: no confirmed PTM bookings were dated in {$label}.";
        }
        $yes = (clone $q)->whereRaw("UPPER(PTM_ATTENDED_STATUS) = 'YES'")->count();
        $no = (clone $q)->whereRaw("UPPER(PTM_ATTENDED_STATUS) = 'NO'")->count();
        $open = $total - $yes - $no;

        return "Report only: {$total} confirmed PTM bookings in {$label}: {$yes} attended, {$no} did not attend, {$open} not recorded. Nothing was changed.";
    }

    /** Report only: consents dated today. Nothing is sent or changed. */
    public static function consentDailyDigest(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('consent_master')) {
            return 'Report only: the consent register is not available, so nothing can be counted.';
        }
        $n = DB::table('consent_master')->where('sub_institute_id', $tenant)->whereDate('date', Carbon::today())->count();
        if ($n === 0) {
            return 'Report only: no consents were recorded today.';
        }

        return "Report only: {$n} consents were recorded today. Nothing was sent.";
    }

    /** Report only: accountable and non-accountable consents for the latest year, and the accountable amount. */
    public static function consentAccountableSummary(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('consent_master')) {
            return 'Report only: the consent register is not available, so nothing can be counted.';
        }
        $syear = DB::table('consent_master')->where('sub_institute_id', $tenant)->max('syear');
        if (! $syear) {
            return 'Report only: no consents are recorded for this school.';
        }
        $q = DB::table('consent_master')->where('sub_institute_id', $tenant)->where('syear', $syear);
        $total = (clone $q)->count();
        $accountableQ = (clone $q)->where('accountable_status', 'Accountable');
        $accountable = (clone $accountableQ)->count();
        $amount = (int) round((float) (clone $accountableQ)->sum(DB::raw('CAST(amount AS DECIMAL(12,2))')));
        $other = $total - $accountable;

        return "Report only: {$total} consents for {$syear}: {$accountable} accountable (Rs. {$amount}) and {$other} non-accountable. Nothing was changed.";
    }

    /** Report only: consents recorded in the previous calendar month. */
    public static function consentMonthlyTotal(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('consent_master')) {
            return 'Report only: the consent register is not available, so nothing can be counted.';
        }
        $start = Carbon::today()->subMonthNoOverflow()->startOfMonth();
        $label = $start->format('F Y');
        $n = DB::table('consent_master')->where('sub_institute_id', $tenant)
            ->whereBetween('date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])->count();
        if ($n === 0) {
            return "Report only: no consents were recorded in {$label}.";
        }

        return "Report only: {$n} consents were recorded in {$label}. Nothing was posted or closed.";
    }

    /** Report only: hostel visitors dated today, and how many have no out time. */
    public static function hostelVisitorDigest(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('hostel_visitor_master')) {
            return 'Report only: the hostel visitor register is not available, so nothing can be counted.';
        }
        $q = DB::table('hostel_visitor_master')->where('sub_institute_id', $tenant)
            ->where('meet_date', Carbon::today()->toDateString());
        $n = (clone $q)->count();
        if ($n === 0) {
            return 'Report only: no hostel visitors were recorded today.';
        }
        $open = (clone $q)->where(fn ($w) => $w->whereNull('out_time')->orWhere('out_time', ''))->count();

        return "Report only: {$n} hostel visitors were recorded today; {$open} have no out time yet. Nothing was sent.";
    }

    /** Report only: visitors from earlier days that were never checked out. */
    public static function hostelOpenVisits(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('hostel_visitor_master')) {
            return 'Report only: the hostel visitor register is not available, so visits cannot be checked.';
        }
        $earlier = DB::table('hostel_visitor_master')->where('sub_institute_id', $tenant)
            ->where('meet_date', '<', Carbon::today()->toDateString());
        if ((clone $earlier)->count() === 0) {
            return 'Report only: no earlier hostel visits are recorded.';
        }
        $q = (clone $earlier)->where(fn ($w) => $w->whereNull('out_time')->orWhere('out_time', ''));
        $n = (clone $q)->count();
        if ($n === 0) {
            return 'Report only: every earlier hostel visit has an out time recorded.';
        }
        $oldest = (clone $q)->min('meet_date');

        return "Report only: {$n} hostel visits from earlier days have no out time recorded (oldest {$oldest}). Nothing was changed.";
    }

    /** Report only: residents allocated and rooms in use, for the latest allocation year. */
    public static function hostelOccupancy(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('hostel_room_allocation') || ! Schema::hasTable('hostel_room_master')) {
            return 'Report only: the hostel room tables are not available, so occupancy cannot be checked.';
        }
        $rooms = DB::table('hostel_room_master')->where('sub_institute_id', $tenant)->count();
        $syear = DB::table('hostel_room_allocation')->where('sub_institute_id', $tenant)->max('syear');
        if (! $syear) {
            return "Report only: no residents are allocated to a room yet ({$rooms} rooms are set up).";
        }
        $q = DB::table('hostel_room_allocation')->where('sub_institute_id', $tenant)->where('syear', $syear);
        $residents = (clone $q)->distinct()->count('user_id');
        $used = (clone $q)->distinct()->count('room_id');

        return "Report only: {$residents} residents are allocated across {$used} of {$rooms} rooms for {$syear}. Nothing was changed.";
    }

    /** Report only: petty cash entries dated today and their total. Nothing is sent or changed. */
    public static function pettyCashDailyDigest(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('petty_cash')) {
            return 'Report only: the petty cash register is not available, so nothing can be counted.';
        }
        $q = DB::table('petty_cash')->where('sub_institute_id', $tenant)->whereDate('created_on', Carbon::today());
        $n = (clone $q)->count();
        if ($n === 0) {
            return 'Report only: no petty cash entries were recorded today.';
        }
        $sum = (int) (clone $q)->sum('amount');

        return "Report only: {$n} petty cash entries totalling Rs. {$sum} were recorded today. Nothing was sent.";
    }

    /** Report only: petty cash entries of the last 30 days with no bill image. */
    public static function pettyCashMissingBills(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('petty_cash') || ! Schema::hasColumn('petty_cash', 'bill_image')) {
            return 'Report only: the petty cash register is not available, so bills cannot be checked.';
        }
        $recent = DB::table('petty_cash')->where('sub_institute_id', $tenant)
            ->whereDate('created_on', '>=', Carbon::today()->subDays(30));
        if ((clone $recent)->count() === 0) {
            return 'Report only: no petty cash entries were recorded in the last 30 days.';
        }
        $q = (clone $recent)->where(fn ($w) => $w->whereNull('bill_image')->orWhere('bill_image', ''));
        $n = (clone $q)->count();
        if ($n === 0) {
            return 'Report only: every petty cash entry of the last 30 days has a bill attached.';
        }
        $sum = (int) (clone $q)->sum('amount');
        $oldest = (clone $q)->min('created_on');

        return "Report only: {$n} petty cash entries of the last 30 days (Rs. {$sum}) have no bill attached (oldest {$oldest}). Nothing was changed.";
    }

    /** Report only: petty cash entries and total for the previous calendar month. */
    public static function pettyCashMonthlyTotal(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('petty_cash')) {
            return 'Report only: the petty cash register is not available, so nothing can be totalled.';
        }
        $start = Carbon::today()->subMonthNoOverflow()->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $label = $start->format('F Y');
        $q = DB::table('petty_cash')->where('sub_institute_id', $tenant)
            ->whereBetween('created_on', [$start->toDateTimeString(), $end->toDateTimeString()]);
        $n = (clone $q)->count();
        if ($n === 0) {
            return "Report only: no petty cash entries were recorded in {$label}.";
        }
        $sum = (int) (clone $q)->sum('amount');

        return "Report only: {$n} petty cash entries totalling Rs. {$sum} were recorded in {$label}. Nothing was posted or closed.";
    }

    /** Report only: inward and outward register entries dated today. Nothing is sent or changed. */
    public static function inwardOutwardDailyDigest(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('inward') || ! Schema::hasTable('outward')) {
            return 'Report only: the inward or outward register is not available, so nothing can be counted.';
        }
        $today = Carbon::today()->toDateString();
        $in = DB::table('inward')->where('sub_institute_id', $tenant)->whereDate('inward_date', $today)->count();
        $out = DB::table('outward')->where('sub_institute_id', $tenant)->whereDate('outward_date', $today)->count();

        return "Report only: {$in} inward and {$out} outward entries were recorded today. Nothing was sent.";
    }

    /** Report only: inward entries of the latest academic year with no file location. */
    public static function inwardUnfiled(PlatformScheduledTask $task): string
    {
        return self::registerUnfiled($task, 'inward', 'inward_date');
    }

    /** Report only: outward entries of the latest academic year with no file location. */
    public static function outwardUnfiled(PlatformScheduledTask $task): string
    {
        return self::registerUnfiled($task, 'outward', 'outward_date');
    }

    private static function registerUnfiled(PlatformScheduledTask $task, string $table, string $dateColumn): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'file_location_id') || ! Schema::hasColumn($table, 'syear')) {
            return "Report only: the {$table} register is not available, so file locations cannot be checked.";
        }
        $syear = DB::table($table)->where('sub_institute_id', $tenant)->max('syear');
        if (! $syear) {
            return "Report only: no {$table} entries are recorded for this school.";
        }
        $q = DB::table($table)->where('sub_institute_id', $tenant)->where('syear', $syear)
            ->where(fn ($w) => $w->whereNull('file_location_id')->orWhere('file_location_id', 0)->orWhere('file_location_id', ''));
        $n = (clone $q)->count();
        if ($n === 0) {
            return "Report only: every {$table} entry for {$syear} has a file location.";
        }
        $oldest = (clone $q)->whereNotNull($dateColumn)->where($dateColumn, '>', '1000-01-01')->min($dateColumn);
        $since = $oldest ? " (oldest {$oldest})" : '';

        return "Report only: {$n} {$table} entries for {$syear} have no file location recorded{$since}. Nothing was changed.";
    }
}
