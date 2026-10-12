<?php

namespace App\Services\Alumni;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Donations, recorded offline and confirmed by a person - there is no payment gateway.
 *
 *   pledged ──mark received──> pending_verification ──verify──> verified ──refund──> partially_refunded ──> refunded
 *      │                              │
 *      └──cancel                      ├──reject (reason)
 *                                     └──cancel (reason)
 *
 * A receipt number exists ONLY on a verified donation, is issued exactly once (unique index + the status check inside the
 * transaction) and is never reused. Refunds are separate rows; the donation and its receipt stay as the record of what
 * happened. Every amount is validated server-side - the client is never the authority.
 */
class AlumniDonationService
{
    public const MODES = ['cash' => 'Cash', 'cheque' => 'Cheque', 'bank_transfer' => 'Bank transfer', 'upi' => 'UPI', 'card' => 'Card', 'other' => 'Other'];

    public const MAX_AMOUNT = 100000000;   // 10 crore per donation: a typo guard, not a policy

    /** Modes that must carry a reference (cheque no., UTR, transaction id) before they can be verified. */
    private const NEEDS_REFERENCE = ['cheque', 'bank_transfer', 'upi', 'card'];

    public function __construct(
        private AlumniMessaging $messaging,
        private AlumniEngagementService $engagement,
    ) {
    }

    // ------------------------------------------------------------------ campaigns

    public function campaignRaised(int $t, int $campaignId): float
    {
        return round((float) DB::table('alumni_donations')->where('sub_institute_id', $t)->where('campaign_id', $campaignId)
            ->whereIn('status', ['verified', 'partially_refunded', 'refunded'])->selectRaw('COALESCE(SUM(amount - refunded_amount), 0) as net')->value('net'), 2);
    }

    public function findCampaign(int $t, int $id): ?object
    {
        return DB::table('alumni_donation_campaigns')->where('sub_institute_id', $t)->where('id', $id)->first();
    }

    // ------------------------------------------------------------------ donations

    public function find(int $t, int $id): ?object
    {
        return DB::table('alumni_donations')->where('sub_institute_id', $t)->where('id', $id)->first();
    }

    /**
     * Record a pledge, or money already received. With a payment mode and a received date it goes straight to
     * `pending_verification` (it still needs a second look before it counts); without them it is a pledge.
     *
     * @param  array<string, mixed>  $d
     */
    public function record(int $t, array $d, ?int $userId): int
    {
        $alumnus = DB::table('alumni')->where('sub_institute_id', $t)->where('id', (int) $d['alumni_id'])->first(['id', 'status']);
        if (! $alumnus) {
            throw new AlumniRuleException('That alumnus was not found.', 422, 'alumni_id');
        }
        if ($alumnus->status === 'deceased') {
            throw new AlumniRuleException('A donation cannot be recorded against a deceased alumnus.', 422, 'alumni_id');
        }
        $campaignId = ! empty($d['campaign_id']) ? (int) $d['campaign_id'] : null;
        if ($campaignId !== null) {
            $campaign = $this->findCampaign($t, $campaignId);
            if (! $campaign) {
                throw new AlumniRuleException('That campaign was not found.', 422, 'campaign_id');
            }
            if ($campaign->status === 'closed') {
                throw new AlumniRuleException('This campaign is closed and no longer takes donations.', 422, 'campaign_id');
            }
        }
        $received = ! empty($d['payment_mode']) && ! empty($d['received_on']);
        if (! empty($d['payment_mode']) xor ! empty($d['received_on'])) {
            throw new AlumniRuleException('Give both the payment mode and the date received, or neither for a pledge.', 422, empty($d['payment_mode']) ? 'payment_mode' : 'received_on');
        }

        return (int) DB::table('alumni_donations')->insertGetId([
            'sub_institute_id' => $t, 'alumni_id' => (int) $d['alumni_id'], 'campaign_id' => $campaignId, 'amount' => round((float) $d['amount'], 2),
            'currency' => 'INR', 'purpose' => $d['purpose'] ?? null, 'payment_mode' => $received ? $d['payment_mode'] : null, 'reference_no' => $received ? ($d['reference_no'] ?? null) : null,
            'pledged_on' => $received ? null : ($d['pledged_on'] ?? now()->toDateString()), 'received_on' => $received ? $d['received_on'] : null,
            'status' => $received ? 'pending_verification' : 'pledged', 'anonymous' => ! empty($d['anonymous']) ? 1 : 0, 'notes' => $d['notes'] ?? null,
            'recorded_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Correct a donation that is not verified yet (a missing cheque number, a mistyped amount). Once verified it is locked:
     * a verified donation has a receipt, and changing it afterwards would make the receipt lie. Use a refund instead.
     *
     * @param  array<string, mixed>  $d
     */
    public function update(int $t, int $id, array $d): object
    {
        return DB::transaction(function () use ($t, $id, $d) {
            $row = $this->lock($t, $id);
            if (! in_array($row->status, ['pledged', 'pending_verification'], true)) {
                throw new AlumniRuleException('A verified donation cannot be edited. Record a refund instead.', 409);
            }
            $set = array_intersect_key($d, array_flip(['amount', 'purpose', 'campaign_id', 'payment_mode', 'reference_no', 'received_on', 'notes', 'anonymous']));
            if (isset($set['campaign_id']) && $set['campaign_id'] !== null && ! DB::table('alumni_donation_campaigns')->where('sub_institute_id', $t)->where('id', (int) $set['campaign_id'])->where('status', '!=', 'closed')->exists()) {
                throw new AlumniRuleException('That campaign was not found or is closed.', 422, 'campaign_id');
            }
            if (isset($set['amount'])) {
                $set['amount'] = round((float) $set['amount'], 2);
            }
            if (array_key_exists('anonymous', $set)) {
                $set['anonymous'] = ! empty($set['anonymous']) ? 1 : 0;
            }
            if ($row->status === 'pending_verification' && (array_key_exists('payment_mode', $set) && empty($set['payment_mode']) || array_key_exists('received_on', $set) && empty($set['received_on']))) {
                throw new AlumniRuleException('Money recorded as received needs its payment mode and date. Cancel the donation instead.', 422, 'payment_mode');
            }
            if ($set) {
                DB::table('alumni_donations')->where('id', $id)->update($set + ['updated_at' => now()]);
            }

            return $this->find($t, $id);
        });
    }

    public function markReceived(int $t, int $id, string $mode, ?string $reference, string $receivedOn, ?int $userId): object
    {
        return DB::transaction(function () use ($t, $id, $mode, $reference, $receivedOn, $userId) {
            $row = $this->lock($t, $id);
            if ($row->status !== 'pledged') {
                throw new AlumniRuleException('Only a pledge can be marked as received.', 409);
            }
            DB::table('alumni_donations')->where('id', $id)->update([
                'payment_mode' => $mode, 'reference_no' => $reference, 'received_on' => $receivedOn, 'pledged_on' => $row->pledged_on,
                'status' => 'pending_verification', 'recorded_by' => $userId ?? $row->recorded_by, 'updated_at' => now(),   // the person who records the money is the recorder, not whoever noted the pledge
            ]);

            return $this->find($t, $id);
        });
    }

    /**
     * Confirm the money really arrived. Issues the receipt number, credits engagement and sends the receipt notice.
     * Cheque / transfer / UPI / card need a reference first - verifying "money received" with nothing to check it
     * against would be a rubber stamp.
     */
    public function verify(int $t, int $id, ?int $userId): object
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                $row = $this->verifyOnce($t, $id, $userId);

                break;
            } catch (QueryException $e) {
                // two verifications at the same moment can pick the same receipt number; the unique index stops the second, which simply takes the next one
                if ($attempt >= 5 || ! in_array((string) $e->getCode(), ['23000', '23505'], true)) {
                    throw $e;
                }
            }
        }

        $this->afterVerified($t, $row, $userId);

        return $row;
    }

    private function verifyOnce(int $t, int $id, ?int $userId): object
    {
        return DB::transaction(function () use ($t, $id, $userId) {
            $row = $this->lock($t, $id);
            if ($row->status !== 'pending_verification') {
                throw new AlumniRuleException($row->status === 'verified' ? 'This donation is already verified.' : 'Only a donation whose money has been recorded as received can be verified.', 409);
            }
            // separation of duties (product-owner decision): whoever recorded that the money arrived cannot also confirm it
            if ($userId !== null && $row->recorded_by !== null && (int) $row->recorded_by === $userId) {
                throw new AlumniRuleException('You recorded this donation, so another finance user must verify it.', 403);
            }
            if (in_array($row->payment_mode, self::NEEDS_REFERENCE, true) && trim((string) $row->reference_no) === '') {
                throw new AlumniRuleException('Add the cheque number, transaction id or UTR before verifying.', 422, 'reference_no');
            }
            $receipt = $this->issueReceiptNumber($t);
            DB::table('alumni_donations')->where('id', $id)->update([
                'status' => 'verified', 'verified_by' => $userId, 'verified_at' => now(), 'receipt_no' => $receipt, 'receipt_issued_at' => now(), 'updated_at' => now(),
            ]);

            return $this->find($t, $id);
        });
    }

    public function reject(int $t, int $id, string $reason, ?int $userId): object
    {
        return $this->close($t, $id, 'rejected', ['pending_verification'], $reason, $userId, 'Only a donation awaiting verification can be rejected.');
    }

    public function cancel(int $t, int $id, string $reason, ?int $userId): object
    {
        return $this->close($t, $id, 'cancelled', ['pledged', 'pending_verification'], $reason, $userId, 'Only a pledge or an unverified donation can be cancelled. Use a refund for a verified one.');
    }

    /**
     * Refund part or all of a verified donation. The receipt stays valid as the record of the original gift; the refund is
     * its own row. Refunding more than what is left is refused.
     */
    public function refund(int $t, int $id, float $amount, string $reason, string $refundedOn, ?string $mode, ?string $reference, ?int $userId): object
    {
        return DB::transaction(function () use ($t, $id, $amount, $reason, $refundedOn, $mode, $reference, $userId) {
            $row = $this->lock($t, $id);
            if (! in_array($row->status, ['verified', 'partially_refunded'], true)) {
                throw new AlumniRuleException('Only a verified donation can be refunded.', 409);
            }
            $amount = round($amount, 2);
            $left = round((float) $row->amount - (float) $row->refunded_amount, 2);
            if ($amount <= 0) {
                throw new AlumniRuleException('The refund amount must be more than zero.', 422, 'amount');
            }
            if ($amount > $left) {
                throw new AlumniRuleException('Only ₹' . number_format($left, 2) . ' of this donation is left to refund.', 422, 'amount');
            }
            DB::table('alumni_donation_refunds')->insert([
                'sub_institute_id' => $t, 'donation_id' => $id, 'amount' => $amount, 'reason' => $reason, 'refunded_on' => $refundedOn,
                'payment_mode' => $mode, 'reference_no' => $reference, 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $total = round((float) $row->refunded_amount + $amount, 2);
            DB::table('alumni_donations')->where('id', $id)->update([
                'refunded_amount' => $total, 'status' => $total >= round((float) $row->amount, 2) ? 'refunded' : 'partially_refunded', 'updated_at' => now(),
            ]);

            return $this->find($t, $id);
        });
    }

    /** Printable receipt data - only for a verified (or since refunded) donation, never for anything else. */
    public function receipt(int $t, int $id): array
    {
        $row = $this->find($t, $id);
        if (! $row) {
            throw new AlumniRuleException('Donation not found.', 404);
        }
        if ($row->receipt_no === null) {
            throw new AlumniRuleException('A receipt is issued only after the donation is verified.', 409);
        }
        $alumnus = DB::table('alumni')->where('sub_institute_id', $t)->where('id', $row->alumni_id)->first(['full_name', 'alumni_code', 'email', 'city', 'state']);
        $campaign = $row->campaign_id ? $this->findCampaign($t, (int) $row->campaign_id) : null;

        return [
            'receipt_no' => $row->receipt_no, 'issued_at' => $row->receipt_issued_at, 'institute' => $this->messaging->institute($t),
            'donor' => $alumnus ? ['name' => $alumnus->full_name, 'alumni_code' => $alumnus->alumni_code, 'email' => $alumnus->email] : null,
            'amount' => (float) $row->amount, 'currency' => $row->currency, 'payment_mode' => $row->payment_mode, 'reference_no' => $row->reference_no,
            'received_on' => $row->received_on, 'purpose' => $row->purpose ?: ($campaign->purpose ?? null), 'campaign' => $campaign->name ?? null,
            'refunded_amount' => (float) $row->refunded_amount, 'status' => $row->status,
            'kind' => 'Donation acknowledgement receipt',   // NOT a tax-exemption certificate: that needs the institute's registration details (open decision)
        ];
    }

    /**
     * Totals for the donations screen and reports. Only verified money counts as raised; pending and pledged are shown
     * separately so nobody mistakes a promise for cash.
     *
     * @param  array<string, mixed>  $f  campaign_id, from, to
     */
    public function summary(int $t, array $f = []): array
    {
        // every column is qualified: some of these queries are joined to alumni / campaigns, which also have sub_institute_id
        $q = fn () => DB::table('alumni_donations')->where('alumni_donations.sub_institute_id', $t)
            ->when(! empty($f['campaign_id']), fn ($x) => $x->where('alumni_donations.campaign_id', (int) $f['campaign_id']))
            ->when(! empty($f['from']), fn ($x) => $x->whereRaw('COALESCE(alumni_donations.received_on, alumni_donations.pledged_on) >= ?', [$f['from']]))
            ->when(! empty($f['to']), fn ($x) => $x->whereRaw('COALESCE(alumni_donations.received_on, alumni_donations.pledged_on) <= ?', [$f['to']]));
        $money = fn (array $statuses, string $expr = 'amount') => round((float) $q()->whereIn('status', $statuses)->sum(DB::raw($expr)), 2);
        $count = fn (array $statuses) => (int) $q()->whereIn('status', $statuses)->count();
        $counted = ['verified', 'partially_refunded', 'refunded'];

        $byCampaign = $q()->leftJoin('alumni_donation_campaigns as c', 'c.id', '=', 'alumni_donations.campaign_id')->whereIn('alumni_donations.status', $counted)
            ->groupBy('alumni_donations.campaign_id', 'c.name')->selectRaw('alumni_donations.campaign_id, c.name, COUNT(*) as donations, SUM(alumni_donations.amount - alumni_donations.refunded_amount) as net')
            ->orderByDesc('net')->get()->map(fn ($r) => ['campaign_id' => $r->campaign_id, 'name' => $r->name ?: 'No campaign', 'donations' => (int) $r->donations, 'net' => round((float) $r->net, 2)])->all();

        $byMonth = $q()->whereIn('status', $counted)->whereNotNull('received_on')->get(['received_on', 'amount', 'refunded_amount'])
            ->groupBy(fn ($r) => substr((string) $r->received_on, 0, 7))->map(fn ($g, $m) => ['month' => $m, 'donations' => $g->count(), 'net' => round((float) $g->sum(fn ($r) => $r->amount - $r->refunded_amount), 2)])
            ->sortKeys()->values()->all();

        // anonymous donors are shown as such: the summary is the screen that gets shared
        $top = $q()->join('alumni as a', function ($j) {
            $j->on('a.id', '=', 'alumni_donations.alumni_id')->on('a.sub_institute_id', '=', 'alumni_donations.sub_institute_id');
        })->whereIn('alumni_donations.status', $counted)->groupBy('alumni_donations.alumni_id', 'a.full_name', 'a.alumni_code')
            ->selectRaw('alumni_donations.alumni_id, a.full_name, a.alumni_code, MAX(alumni_donations.anonymous) as anon, SUM(alumni_donations.amount - alumni_donations.refunded_amount) as net, COUNT(*) as donations')
            ->orderByDesc('net')->limit(10)->get()->map(fn ($r) => [
                'alumni_id' => $r->anon ? null : $r->alumni_id, 'name' => $r->anon ? 'Anonymous donor' : $r->full_name, 'alumni_code' => $r->anon ? null : $r->alumni_code,
                'donations' => (int) $r->donations, 'net' => round((float) $r->net, 2),
            ])->all();

        return [
            'raised' => $money($counted, 'amount - refunded_amount'), 'gross_verified' => $money($counted), 'refunded' => round((float) $q()->whereIn('status', $counted)->sum('refunded_amount'), 2),
            'verified_count' => $count($counted), 'pending_amount' => $money(['pending_verification']), 'pending_count' => $count(['pending_verification']),
            'pledged_amount' => $money(['pledged']), 'pledged_count' => $count(['pledged']), 'donors' => (int) $q()->whereIn('status', $counted)->distinct()->count('alumni_id'),
            'by_campaign' => $byCampaign, 'by_month' => $byMonth, 'top_donors' => $top,
        ];
    }

    // -------------------------------------------------------------------- helpers

    /** @param array<int, string> $from */
    private function close(int $t, int $id, string $to, array $from, string $reason, ?int $userId, string $refusal): object
    {
        return DB::transaction(function () use ($t, $id, $to, $from, $reason, $userId, $refusal) {
            $row = $this->lock($t, $id);
            if (! in_array($row->status, $from, true)) {
                throw new AlumniRuleException($refusal, 409);
            }
            DB::table('alumni_donations')->where('id', $id)->update(['status' => $to, 'decision_note' => $reason, 'verified_by' => $userId, 'verified_at' => now(), 'updated_at' => now()]);

            return $this->find($t, $id);
        });
    }

    private function lock(int $t, int $id): object
    {
        $row = DB::table('alumni_donations')->where('sub_institute_id', $t)->where('id', $id)->lockForUpdate()->first();
        if (! $row) {
            throw new AlumniRuleException('Donation not found.', 404);
        }

        return $row;
    }

    /** DON-<year>-<5 digit sequence>, per institute. The unique index is the real guard: a clash simply takes the next number. */
    private function issueReceiptNumber(int $t): string
    {
        $prefix = 'DON-' . now()->format('Y') . '-';
        $last = DB::table('alumni_donations')->where('sub_institute_id', $t)->where('receipt_no', 'like', $prefix . '%')->max('receipt_no');
        $seq = $last ? ((int) substr((string) $last, strlen($prefix))) + 1 : 1;

        // two concurrent verifications can compute the same number; the unique index fails the loser, and verify() retries it
        return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    private function afterVerified(int $t, object $row, ?int $userId): void
    {
        try {
            $this->engagement->record($t, (int) $row->alumni_id, 'donation', 'Donation ' . $row->receipt_no, $userId, (string) $row->received_on, null, 'donation', (int) $row->id);
        } catch (\Throwable $e) {
            report($e);   // the donation is verified either way; the engagement credit can be re-run
        }
        $amount = number_format((float) $row->amount, 2);
        $this->messaging->notify($t, (int) $row->alumni_id, 'donation_receipt', 'Thank you - receipt ' . $row->receipt_no,
            "Dear {{name}},\n\nThank you for your donation of ₹{$amount} to {{institute}}. Your receipt number is {$row->receipt_no}.", 'donation-receipt-' . $row->id, true, [], null, $userId);
    }
}
