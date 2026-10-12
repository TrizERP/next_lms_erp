<?php

namespace Tests\Feature;

use App\Http\Controllers\api\alumni\AlumniDonationApiController as C;
use Illuminate\Support\Facades\DB;
use Tests\Support\AlumniTestHelpers;
use Tests\TestCase;

/**
 * Donations are recorded offline and confirmed by a person; there is no payment gateway. These tests pin the rules that
 * protect money: who may act, that a pledge is never counted or receipted, that a receipt number is issued once and only
 * after verification, that refunds cannot exceed the gift, that verified rows are locked, and that institutes never see
 * each other's donations. Runs on in-memory sqlite (see AlumniTestHelpers).
 */
class AlumniDonationsTest extends TestCase
{
    use AlumniTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootAlumni();
    }

    private function give(object $alumnus, array $over = [], int $tenant = 7, string $profile = 'Accountant'): array
    {
        return $this->go(C::class, 'store', array_merge(['alumni_id' => $alumnus->id, 'amount' => 5000], $over), $tenant, $profile);
    }

    private function received(object $alumnus, array $over = [], int $tenant = 7): int
    {
        [$s, $b] = $this->give($alumnus, array_merge(['payment_mode' => 'cash', 'received_on' => now()->toDateString()], $over), $tenant);
        $this->assertSame(201, $s, json_encode($b));

        return (int) $b['data']['id'];
    }

    /** Donations are recorded by user 55; verification is done by a different finance user (56), as the rule requires. */
    private function act2(string $method, int $id, array $input = [], int $tenant = 7, string $profile = 'Accountant'): array
    {
        $this->actingUser = $method === 'verify' ? 56 : 55;
        try {
            return $this->go(C::class, $method, $input, $tenant, $profile, [$id]);
        } finally {
            $this->actingUser = 55;
        }
    }

    public function test_the_person_who_recorded_a_donation_cannot_verify_it(): void
    {
        $a = $this->alum('Sep');
        $id = $this->received($a);   // recorded by user 55

        $this->actingUser = 55;
        [$own, $ob] = $this->go(C::class, 'verify', [], 7, 'Accountant', [$id]);
        $this->assertSame(403, $own);
        $this->assertStringContainsString('another finance user', $ob['message']);
        $row = DB::table('alumni_donations')->where('id', $id)->first();
        $this->assertSame('pending_verification', $row->status);
        $this->assertNull($row->receipt_no, 'no receipt for a self-verified gift');

        $this->assertSame(200, $this->act2('verify', $id)[0], 'a second finance user can');
        $this->assertSame(56, (int) DB::table('alumni_donations')->where('id', $id)->value('verified_by'));
        $this->assertSame(55, (int) DB::table('alumni_donations')->where('id', $id)->value('recorded_by'));
    }

    public function test_marking_a_pledge_received_makes_that_person_the_recorder(): void
    {
        $a = $this->alum('Pledgy');
        $this->actingUser = 55;
        $pledge = (int) $this->give($a)[1]['data']['id'];                       // 55 notes the pledge
        $this->actingUser = 56;
        $this->go(C::class, 'received', ['payment_mode' => 'cash', 'received_on' => now()->toDateString()], 7, 'Accountant', [$pledge]);   // 56 records the cash
        $this->assertSame(56, (int) DB::table('alumni_donations')->where('id', $pledge)->value('recorded_by'));

        [$byRecorder] = $this->go(C::class, 'verify', [], 7, 'Accountant', [$pledge]);
        $this->assertSame(403, $byRecorder, 'the person who recorded the money cannot verify it');
        $this->actingUser = 55;
        $this->assertSame(200, $this->go(C::class, 'verify', [], 7, 'Accountant', [$pledge])[0], 'the person who only noted the pledge may');
    }

    // ----------------------------------------------------------------------- access

    public function test_only_finance_roles_may_touch_donations(): void
    {
        $a = $this->alum('Ann');
        $id = $this->received($a);

        foreach (['Teacher', 'Peon', 'Alumni Coordinator'] as $profile) {   // coordinators manage alumni, but not money
            [$s] = $this->go(C::class, 'index', [], 7, $profile);
            [$v] = $this->go(C::class, 'verify', [], 7, $profile, [$id]);
            $this->assertSame(403, $s, $profile);
            $this->assertSame(403, $v, $profile);
        }
        $this->assertSame('pending_verification', DB::table('alumni_donations')->where('id', $id)->value('status'));
        foreach (['Accountant', 'Admin'] as $profile) {
            [$s] = $this->go(C::class, 'index', [], 7, $profile);
            $this->assertSame(200, $s, $profile);
        }
    }

    // -------------------------------------------------------------------- validation

    public function test_amounts_and_dates_are_validated_server_side(): void
    {
        $a = $this->alum('Val');
        foreach ([0, -5, 'abc', 100000001] as $bad) {
            [$s, $b] = $this->give($a, ['amount' => $bad]);
            $this->assertSame(422, $s, 'amount ' . json_encode($bad));
            $this->assertArrayHasKey('amount', $b['errors']);
        }
        [$s1, $b1] = $this->give($a, ['payment_mode' => 'barter', 'received_on' => now()->toDateString()]);
        [$s2, $b2] = $this->give($a, ['payment_mode' => 'cash', 'received_on' => now()->addDay()->toDateString()]);
        [$s3, $b3] = $this->give($a, ['payment_mode' => 'cash']);          // a mode without a date
        [$s4, $b4] = $this->give($a, ['received_on' => now()->toDateString()]);   // a date without a mode
        $this->assertSame(422, $s1);
        $this->assertSame(422, $s2);
        $this->assertArrayHasKey('received_on', $b2['errors']);
        $this->assertSame(422, $s3);
        $this->assertSame(422, $s4);
        $this->assertSame(0, DB::table('alumni_donations')->count(), 'nothing is created by a rejected request');

        [$ok, $okb] = $this->give($a, ['amount' => 100.456]);
        $this->assertSame(201, $ok);
        $this->assertEquals(100.46, $okb['data']['amount']);   // stored as 2-decimal money
    }

    public function test_donor_must_exist_in_this_institute_and_not_be_deceased(): void
    {
        $other = $this->alum('Other', [], 8);
        $dead = $this->alum('Dead', [], 7, 'deceased');
        [$s1, $b1] = $this->give($other);               // alumnus of another institute
        [$s2, $b2] = $this->give($dead);
        $this->assertSame(422, $s1);
        $this->assertSame(422, $s2);
        $this->assertArrayHasKey('alumni_id', $b1['errors']);
        $this->assertSame(0, DB::table('alumni_donations')->count());
    }

    // ------------------------------------------------------------ pledge / verify / receipt

    public function test_a_pledge_is_not_money_and_has_no_receipt(): void
    {
        $a = $this->alum('Pledge');
        [$s, $b] = $this->give($a, ['amount' => 12000]);
        $id = (int) $b['data']['id'];
        $this->assertSame('pledged', $b['data']['status']);
        $this->assertNull($b['data']['receipt_no']);

        [$v, $vb] = $this->act2('verify', $id);
        [$r, $rb] = $this->go(C::class, 'receipt', [], 7, 'Accountant', [$id]);
        $summary = $this->go(C::class, 'summary')[1]['data'];
        $this->assertSame(409, $v, 'a pledge cannot be verified');
        $this->assertSame(409, $r, 'no receipt before verification');
        $this->assertEquals(0, $summary['raised']);
        $this->assertEquals(12000, $summary['pledged_amount']);   // shown, but separate from raised
    }

    public function test_verification_issues_one_receipt_number_once(): void
    {
        $a = $this->alum('Donor');
        $id = $this->received($a, ['amount' => 2500.50]);
        $this->assertNull(DB::table('alumni_donations')->where('id', $id)->value('receipt_no'), 'recorded money is not receipted until verified');

        [$s, $b] = $this->act2('verify', $id);
        $this->assertSame(200, $s, json_encode($b));
        $no = $b['data']['receipt_no'];
        $this->assertMatchesRegularExpression('/^DON-' . now()->format('Y') . '-00001$/', $no);
        $this->assertSame(56, (int) $b['data']['verified_by']);

        [$again, $ab] = $this->act2('verify', $id);   // a double click or a replay
        $this->assertSame(409, $again);
        $this->assertSame($no, DB::table('alumni_donations')->where('id', $id)->value('receipt_no'), 'the number is never re-issued');

        $second = $this->received($a);
        $this->assertSame('DON-' . now()->format('Y') . '-00002', $this->act2('verify', $second)[1]['data']['receipt_no']);

        $other = $this->alum('Elsewhere', [], 8);
        $o = $this->received($other, [], 8);
        $this->assertSame('DON-' . now()->format('Y') . '-00001', $this->act2('verify', $o, [], 8)[1]['data']['receipt_no'], 'numbering is per institute');

        [$rs, $rb] = $this->go(C::class, 'receipt', [], 7, 'Accountant', [$id]);
        $this->assertSame(200, $rs);
        $this->assertSame('Test School', $rb['data']['institute']);
        $this->assertSame('Donation acknowledgement receipt', $rb['data']['kind']);
        $this->assertEquals(2500.50, $rb['data']['amount']);
        $this->assertStringContainsString($no, json_encode($rb), 'the receipt carries its number');
        $this->assertSame(1, DB::table('platform_audit_log')->where('action', 'receipt_view')->count(), 'looking at a receipt is audited');
    }

    public function test_verification_credits_engagement_once_and_sends_a_receipt_notice(): void
    {
        $a = $this->alum('Credit');
        $id = $this->received($a);
        $this->act2('verify', $id);
        $this->act2('verify', $id);   // refused, must not credit again

        $this->assertSame(1, DB::table('alumni_activity')->where('alumni_id', $a->id)->where('activity_type', 'donation')->where('ref_type', 'donation')->count());
        $log = $this->log(['alumni_id' => $a->id, 'comm_type' => 'donation_receipt']);
        $this->assertTrue($log->isNotEmpty(), 'a receipt notice is attempted');
        $this->assertSame(1, $log->where('channel', 'web')->count());
        $this->assertSame('sent', $log->firstWhere('channel', 'web')->status);
        $this->assertStringContainsString('DON-', (string) $log->firstWhere('channel', 'web')->subject);
    }

    public function test_cheque_and_transfer_need_a_reference_before_verification(): void
    {
        $a = $this->alum('Cheque');
        $id = $this->received($a, ['payment_mode' => 'cheque']);
        [$s, $b] = $this->act2('verify', $id);
        $this->assertSame(422, $s);
        $this->assertArrayHasKey('reference_no', $b['errors']);
        $this->assertNull(DB::table('alumni_donations')->where('id', $id)->value('receipt_no'));

        [$u] = $this->act2('update', $id, ['reference_no' => 'CHQ-889911']);
        $this->assertSame(200, $u);
        $this->assertSame(200, $this->act2('verify', $id)[0]);
    }

    public function test_pledge_can_be_marked_received_then_verified(): void
    {
        $a = $this->alum('Later');
        [$s, $b] = $this->give($a);
        $id = (int) $b['data']['id'];

        [$bad] = $this->act2('received', $id, ['payment_mode' => 'cash']);   // no date
        [$ok, $okb] = $this->act2('received', $id, ['payment_mode' => 'upi', 'reference_no' => 'UTR123456', 'received_on' => now()->toDateString()]);
        $this->assertSame(422, $bad);
        $this->assertSame(200, $ok);
        $this->assertSame('pending_verification', $okb['data']['status']);
        $this->assertSame(409, $this->act2('received', $id, ['payment_mode' => 'cash', 'received_on' => now()->toDateString()])[0], 'only a pledge can be marked received');
        $this->assertSame(200, $this->act2('verify', $id)[0]);
    }

    // ---------------------------------------------------------- reject, cancel, lock

    public function test_reject_and_cancel_need_a_reason_and_never_produce_a_receipt(): void
    {
        $a = $this->alum('Rej');
        $id = $this->received($a);
        $this->assertSame(422, $this->act2('reject', $id, [])[0]);
        $this->assertSame(422, $this->act2('reject', $id, ['reason' => 'x'])[0]);
        [$s, $b] = $this->act2('reject', $id, ['reason' => 'Cheque bounced']);
        $this->assertSame(200, $s);
        $this->assertSame('rejected', $b['data']['status']);
        $this->assertNull($b['data']['receipt_no']);
        $this->assertSame(409, $this->act2('verify', $id)[0], 'a rejected donation cannot be revived by verifying');

        [$p, $pb] = $this->give($a);
        $this->assertSame(200, $this->act2('cancel', (int) $pb['data']['id'], ['reason' => 'Donor withdrew the pledge'])[0]);
    }

    public function test_verified_donation_is_locked_and_cannot_be_cancelled_or_rejected(): void
    {
        $a = $this->alum('Lock');
        $id = $this->received($a);
        $this->act2('verify', $id);

        $this->assertSame(409, $this->act2('update', $id, ['amount' => 1])[0]);
        $this->assertSame(409, $this->act2('cancel', $id, ['reason' => 'changed my mind'])[0]);
        $this->assertSame(409, $this->act2('reject', $id, ['reason' => 'changed my mind'])[0]);
        $this->assertEquals(5000, DB::table('alumni_donations')->where('id', $id)->value('amount'));
    }

    // -------------------------------------------------------------------------- refunds

    public function test_refunds_are_bounded_by_the_gift_and_keep_the_receipt(): void
    {
        $a = $this->alum('Ref');
        $id = $this->received($a, ['amount' => 1000]);
        $this->assertSame(409, $this->act2('refund', $id, ['amount' => 100, 'reason' => 'Duplicate', 'refunded_on' => now()->toDateString()])[0], 'only verified money can be refunded');
        $no = $this->act2('verify', $id)[1]['data']['receipt_no'];

        $r = fn (float $amt) => $this->act2('refund', $id, ['amount' => $amt, 'reason' => 'Donor request', 'refunded_on' => now()->toDateString()]);
        $this->assertSame(422, $r(0)[0]);
        $this->assertSame(422, $r(1000.01)[0], 'cannot refund more than was given');
        [$s1, $b1] = $r(400);
        $this->assertSame(200, $s1);
        $this->assertSame('partially_refunded', $b1['data']['status']);
        [$s2, $b2] = $r(600.01);
        $this->assertSame(422, $s2, 'only 600.00 is left');
        $this->assertStringContainsString('600.00', $b2['message']);
        [$s3, $b3] = $r(600);
        $this->assertSame(200, $s3);
        $this->assertSame('refunded', $b3['data']['status']);
        $this->assertSame(409, $r(1)[0], 'nothing left to refund');
        $this->assertSame($no, $b3['data']['receipt_no'], 'the receipt stays as the record of the original gift');

        [, $show] = $this->act2('show', $id);
        $this->assertCount(2, $show['data']['refunds']);
        $this->assertEquals(1000, $show['data']['donation']['refunded_amount']);
        $this->assertSame(2, DB::table('platform_audit_log')->where('action', 'refund')->count());
        $this->assertSame(403, $this->go(C::class, 'refund', ['amount' => 1, 'reason' => 'abc', 'refunded_on' => now()->toDateString()], 7, 'Teacher', [$id])[0]);
    }

    // ------------------------------------------------------------------------ campaigns

    public function test_campaign_raised_counts_only_verified_money_net_of_refunds(): void
    {
        [$cs, $cb] = $this->go(C::class, 'saveCampaign', ['name' => 'Library fund', 'goal_amount' => 10000, 'status' => 'active'], 7, 'Accountant');
        $this->assertSame(201, $cs, json_encode($cb));
        $campaign = (int) $cb['data']['id'];
        $a = $this->alum('Camp');

        $verified = $this->received($a, ['amount' => 4000, 'campaign_id' => $campaign]);
        $this->received($a, ['amount' => 3000, 'campaign_id' => $campaign]);              // recorded but never verified
        $this->give($a, ['amount' => 2000, 'campaign_id' => $campaign]);                  // a pledge
        $this->act2('verify', $verified);
        $this->act2('refund', $verified, ['amount' => 1000, 'reason' => 'Part returned', 'refunded_on' => now()->toDateString()]);

        [, $list] = $this->go(C::class, 'campaigns', [], 7, 'Accountant');
        $row = collect($list['data'])->firstWhere('id', $campaign);
        $this->assertEquals(3000, $row['raised']);       // 4000 - 1000, not 9000
        $this->assertEquals(30.0, $row['percent']);

        $this->go(C::class, 'saveCampaign', ['status' => 'closed'], 7, 'Accountant', [$campaign]);
        [$closed, $cb2] = $this->give($a, ['campaign_id' => $campaign]);
        $this->assertSame(422, $closed, 'a closed campaign takes no new donations');
        $this->assertArrayHasKey('campaign_id', $cb2['errors']);
        $this->assertSame(422, $this->go(C::class, 'saveCampaign', ['name' => 'Bad', 'starts_on' => '2026-05-01', 'ends_on' => '2026-04-01'], 7, 'Accountant')[0]);
    }

    // --------------------------------------------------------------- tenant isolation

    public function test_institutes_never_see_or_change_each_others_donations(): void
    {
        $a = $this->alum('Mine');
        $id = $this->received($a);
        $foreign = $this->alum('Theirs', [], 8);
        $foreignId = $this->received($foreign, [], 8);

        foreach (['show', 'verify', 'receipt'] as $m) {
            $this->assertSame(404, $this->go(C::class, $m, [], 8, 'Accountant', [$id])[0], $m . ' of another institute');
        }
        foreach (['reject', 'cancel'] as $m) {
            $this->assertSame(404, $this->go(C::class, $m, ['reason' => 'not yours to touch'], 8, 'Accountant', [$id])[0], $m);
        }
        $this->assertSame(404, $this->go(C::class, 'refund', ['amount' => 1, 'reason' => 'abc', 'refunded_on' => now()->toDateString()], 8, 'Accountant', [$id])[0]);
        $this->assertSame(404, $this->go(C::class, 'update', ['amount' => 1], 8, 'Accountant', [$id])[0]);
        $this->assertSame('pending_verification', DB::table('alumni_donations')->where('id', $id)->value('status'));

        [, $list] = $this->go(C::class, 'index', [], 8, 'Accountant');
        $this->assertSame([$foreignId], collect($list['data']['rows'])->pluck('id')->map(fn ($i) => (int) $i)->all());
        $this->assertEquals(0, $this->go(C::class, 'summary', [], 8, 'Accountant')[1]['data']['raised']);
        $this->assertSame(404, $this->go(C::class, 'alumnusDonations', [], 8, 'Accountant', [$a->id])[0]);
    }

    // -------------------------------------------------------------------------- summary

    public function test_summary_separates_raised_pending_and_pledged_and_masks_anonymous_donors(): void
    {
        $open = $this->alum('Open');
        $anon = $this->alum('Hidden');
        $this->act2('verify', $this->received($open, ['amount' => 3000]));
        $this->act2('verify', $this->received($anon, ['amount' => 9000, 'anonymous' => true]));
        $this->received($open, ['amount' => 700]);        // pending
        $this->give($open, ['amount' => 500]);            // pledged

        $s = $this->go(C::class, 'summary', [], 7, 'Accountant')[1]['data'];
        $this->assertEquals(12000, $s['raised']);
        $this->assertEquals(700, $s['pending_amount']);
        $this->assertEquals(500, $s['pledged_amount']);
        $this->assertSame(2, $s['verified_count']);
        $this->assertSame(2, $s['donors']);
        $this->assertSame('Anonymous donor', $s['top_donors'][0]['name'], 'the biggest donor asked not to be named');
        $this->assertNull($s['top_donors'][0]['alumni_id']);
        $this->assertStringNotContainsString('Hidden', json_encode($s));
        $this->assertSame(now()->format('Y-m'), $s['by_month'][0]['month']);

        [$bad] = $this->go(C::class, 'summary', ['from' => '2026-05-02', 'to' => '2026-05-01'], 7, 'Accountant');
        $this->assertSame(422, $bad);
    }

    public function test_finance_donor_lookup_returns_minimal_fields_for_this_institute_only(): void
    {
        $mine = $this->alum('Lookup');
        $this->alum('Lookup', [], 8);
        $this->alum('Gone', [], 7, 'deceased');

        [$s, $b] = $this->go(C::class, 'donors', ['q' => 'Look'], 7, 'Accountant');
        $this->assertSame(200, $s);
        $this->assertSame([(int) $mine->id], collect($b['data'])->pluck('id')->map(fn ($i) => (int) $i)->all(), 'only this institute, and never a deceased alumnus');
        $this->assertEqualsCanonicalizing(['id', 'full_name', 'alumni_code', 'batch'], array_keys($b['data'][0]), 'no contact details leak through the finance lookup');
        $this->assertSame(422, $this->go(C::class, 'donors', ['q' => 'L'], 7, 'Accountant')[0], 'two characters minimum');
        $this->assertSame(403, $this->go(C::class, 'donors', ['q' => 'Look'], 7, 'Alumni Coordinator')[0]);
        $this->assertSame([], $this->go(C::class, 'donors', ['q' => '%%'], 7, 'Accountant')[1]['data'], 'LIKE wildcards are escaped, not interpreted');
    }

    public function test_profile_donation_list_and_filters(): void
    {
        $a = $this->alum('List');
        $b = $this->alum('Other');
        $this->received($a, ['amount' => 100]);
        $this->give($b, ['amount' => 200]);

        [, $mine] = $this->go(C::class, 'alumnusDonations', [], 7, 'Accountant', [$a->id]);
        $this->assertCount(1, $mine['data']);
        [, $pending] = $this->go(C::class, 'index', ['status' => 'pending_verification'], 7, 'Accountant');
        $this->assertCount(1, $pending['data']['rows']);
        [, $found] = $this->go(C::class, 'index', ['q' => $b->alumni_code], 7, 'Accountant');
        $this->assertCount(1, $found['data']['rows']);
        [$bad] = $this->go(C::class, 'index', ['status' => 'paid-ish'], 7, 'Accountant');
        $this->assertSame(422, $bad);
    }
}
