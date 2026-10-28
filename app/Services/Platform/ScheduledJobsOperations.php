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
}
