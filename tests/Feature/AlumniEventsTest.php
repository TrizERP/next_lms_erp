<?php

namespace Tests\Feature;

use App\Http\Controllers\api\alumni\AlumniEventApiController;
use App\Services\Alumni\AlumniEventReminders;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AlumniTestHelpers;
use Tests\TestCase;

/**
 * Events: validation, lifecycle, registration rules, capacity and waitlist, payment gate, every check-in method,
 * completion, cancellation, feedback, reports, invitations, files, reminders and tenant isolation.
 * Runs on in-memory sqlite against the real notification sender (see AlumniTestHelpers).
 */
class AlumniEventsTest extends TestCase
{
    use AlumniTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootAlumni();
    }

    private function mkEvent(array $over = [], bool $publish = true, int $tenant = 7): int
    {
        [$s, $b] = $this->go(AlumniEventApiController::class, 'store', array_merge([
            'name' => 'Reunion ' . uniqid(), 'event_type' => 'batch_reunion', 'event_date' => now()->addDays(10)->toDateString(), 'start_time' => '10:00', 'end_time' => '13:00',
            'venue' => 'Main hall', 'mode' => 'offline', 'capacity' => 100, 'registration_required' => true,
        ], $over), $tenant);
        $this->assertSame(201, $s, json_encode($b));
        $id = (int) $b['data']['id'];
        if ($publish) {
            [$p, $pb] = $this->go(AlumniEventApiController::class, 'publish', [], $tenant, 'Admin', [$id]);
            $this->assertSame(200, $p, json_encode($pb));
        }

        return $id;
    }

    private function reg(int $event, object $alumnus, array $over = [], int $tenant = 7): array
    {
        return $this->go(AlumniEventApiController::class, 'register', array_merge(['alumni_id' => $alumnus->id], $over), $tenant, 'Admin', [$event]);
    }

    private function regRow(int $event, object $alumnus): ?object
    {
        return DB::table('alumni_event_registrations')->where('event_id', $event)->where('alumni_id', $alumnus->id)->first();
    }

    // ---------------------------------------------------------------- validation

    public function test_event_input_is_validated_and_the_kind_travels_as_event_type(): void
    {
        [$a, $ea] = $this->go(AlumniEventApiController::class, 'store', ['name' => 'x', 'event_type' => 'party', 'event_date' => now()->subDay()->toDateString()]);
        [$b, $eb] = $this->go(AlumniEventApiController::class, 'store', ['name' => 'x', 'event_type' => 'webinar', 'event_date' => now()->addDay()->toDateString(), 'start_time' => '11:00', 'end_time' => '10:00', 'online_link' => 'javascript:alert(1)', 'contact_email' => 'nope', 'fee_amount' => -5]);
        [$teacher] = $this->go(AlumniEventApiController::class, 'index', [], 7, 'Teacher');

        $this->assertSame([422, 422, 403], [$a, $b, $teacher]);
        $this->assertArrayHasKey('event_type', $ea['errors']);
        $this->assertArrayHasKey('event_date', $ea['errors']);
        foreach (['end_time', 'online_link', 'contact_email', 'fee_amount'] as $field) {
            $this->assertArrayHasKey($field, $eb['errors'], $field);
        }
        $this->assertSame(0, DB::table('alumni_events')->count());
    }

    public function test_publishing_needs_a_venue_or_link_and_only_a_draft_can_be_published(): void
    {
        $noVenue = $this->mkEvent(['venue' => null], false);
        $noLink = $this->mkEvent(['mode' => 'online', 'venue' => null], false);
        $ok = $this->mkEvent(['mode' => 'online', 'venue' => null, 'online_link' => 'https://meet.example/x']);

        [$v, $ev] = $this->go(AlumniEventApiController::class, 'publish', [], 7, 'Admin', [$noVenue]);
        [$l] = $this->go(AlumniEventApiController::class, 'publish', [], 7, 'Admin', [$noLink]);
        [$again] = $this->go(AlumniEventApiController::class, 'publish', [], 7, 'Admin', [$ok]);

        $this->assertSame([422, 422, 409], [$v, $l, $again]);
        $this->assertArrayHasKey('venue', $ev['errors']);
    }

    // ---------------------------------------------------- registration rules

    public function test_registration_is_one_per_alumnus_and_a_cancelled_one_is_reused(): void
    {
        $e = $this->mkEvent();
        $a = $this->alum('Reg');

        [$s1, $r1] = $this->reg($e, $a);
        [$s2] = $this->reg($e, $a);
        $first = $this->regRow($e, $a);
        $this->go(AlumniEventApiController::class, 'cancelRegistration', [], 7, 'Admin', [$e, $first->id]);
        [$s3, $r3] = $this->reg($e, $a);
        $again = $this->regRow($e, $a);

        $this->assertSame([201, 409, 201], [$s1, $s2, $s3]);
        $this->assertSame('confirmed', $r1['data']['status']);                 // free event, seat free, auto-confirm
        $this->assertSame(1, DB::table('alumni_event_registrations')->where('event_id', $e)->count());
        $this->assertSame($first->registration_no, $again->registration_no);   // same row, same number ...
        $this->assertNotSame($first->qr_token, $again->qr_token);              // ... but a fresh ticket
    }

    public function test_events_must_be_open_for_registration(): void
    {
        $a = $this->alum('Open');
        $draft = $this->mkEvent([], false);
        $noReg = $this->mkEvent(['registration_required' => false]);
        $late = $this->mkEvent(['registration_deadline' => now()->addHour()->toDateTimeString()]);
        DB::table('alumni_events')->where('id', $late)->update(['registration_deadline' => now()->subMinute()]);      // the deadline passes
        $past = $this->mkEvent();
        DB::table('alumni_events')->where('id', $past)->update(['event_date' => now()->subDays(2)->toDateString()]);  // the event has happened
        $cancelled = $this->mkEvent();
        $this->go(AlumniEventApiController::class, 'cancel', ['reason' => 'Weather'], 7, 'Admin', [$cancelled]);

        $codes = array_map(fn ($e) => $this->reg($e, $a)[0], [$draft, $noReg, $late, $past, $cancelled]);
        $messages = array_map(fn ($e) => $this->reg($e, $a)[1]['message'], [$draft, $noReg, $late, $past, $cancelled]);

        $this->assertSame([409, 409, 409, 409, 409], $codes);
        $this->assertStringContainsString('not been published', $messages[0]);
        $this->assertStringContainsString('not required', $messages[1]);
        $this->assertStringContainsString('deadline', $messages[2]);
        $this->assertStringContainsString('already taken place', $messages[3]);
        $this->assertStringContainsString('cancelled', $messages[4]);
        $this->assertSame(0, DB::table('alumni_event_registrations')->count());
    }

    public function test_only_eligible_alumni_in_the_events_audience_can_register_and_other_institutes_cannot(): void
    {
        $e = $this->mkEvent(['filters' => ['batch' => '2012-13']]);
        $inBatch = $this->alum('Inb', ['batch' => '2012-13', 'passing_year' => 2013]);
        $outBatch = $this->alum('Outb', ['batch' => '2018-19']);
        $pending = $this->alum('Pend', ['batch' => '2012-13'], 7, 'pending_verification');
        $blocked = $this->alum('Blk', ['batch' => '2012-13'], 7, 'blocked');
        $elsewhere = $this->alum('Else', ['batch' => '2012-13'], 8);

        $codes = [$this->reg($e, $inBatch)[0], $this->reg($e, $outBatch)[0], $this->reg($e, $pending)[0], $this->reg($e, $blocked)[0], $this->reg($e, $elsewhere)[0]];
        [, $detail] = $this->go(AlumniEventApiController::class, 'show', [], 7, 'Admin', [$e]);

        $this->assertSame([201, 422, 422, 422, 422], $codes);
        $this->assertSame(['batch' => '2012-13'], $detail['data']['audience']);
        [$crossShow] = $this->go(AlumniEventApiController::class, 'show', [], 8, 'Admin', [$e]);
        [$crossReg] = $this->reg($e, $elsewhere, [], 8);                       // the other institute naming this institute's event
        $this->assertSame([404, 404], [$crossShow, $crossReg]);
    }

    // ------------------------------------------------------ capacity, waitlist

    public function test_capacity_counts_participants_waitlists_the_overflow_and_promotes_fairly(): void
    {
        $e = $this->mkEvent(['capacity' => 4]);
        [$a, $b, $c, $d] = [$this->alum('A'), $this->alum('B'), $this->alum('C'), $this->alum('D')];

        [, $ra] = $this->reg($e, $a, ['participants' => 3, 'guest_details' => 'two guests']);
        [, $rb] = $this->reg($e, $b, ['participants' => 2]);                   // 3 + 2 > 4: waitlisted
        [, $rc] = $this->reg($e, $c, ['participants' => 1]);                   // 3 + 1 = 4: fits
        [, $rd] = $this->reg($e, $d, ['participants' => 1]);                   // full: waitlisted behind B

        $this->assertSame(['confirmed', 'waitlisted', 'confirmed', 'waitlisted'], [$ra['data']['status'], $rb['data']['status'], $rc['data']['status'], $rd['data']['status']]);
        [, $show] = $this->go(AlumniEventApiController::class, 'show', [], 7, 'Admin', [$e]);
        $this->assertSame(4, $show['data']['event']['seats_used']);

        // C leaves: 3 seats used, 1 free. B (2 people) does not fit, D (1) does - D is promoted, B keeps waiting
        [, $cancel] = $this->go(AlumniEventApiController::class, 'cancelRegistration', [], 7, 'Admin', [$e, $rc['data']['id']]);
        $this->assertSame(1, $cancel['data']['promoted']);
        $this->assertSame('confirmed', $this->regRow($e, $d)->status);
        $this->assertSame('waitlisted', $this->regRow($e, $b)->status);

        // A leaves too: 1 used, 3 free, so B finally fits
        $this->go(AlumniEventApiController::class, 'cancelRegistration', [], 7, 'Admin', [$e, $ra['data']['id']]);
        $this->assertSame('confirmed', $this->regRow($e, $b)->status);
        $this->assertLessThanOrEqual(4, (int) DB::table('alumni_event_registrations')->where('event_id', $e)->whereIn('status', ['registered', 'confirmed', 'attended'])->sum('participants'));
        $this->assertGreaterThan(0, $this->log(['comm_type' => 'waitlist_promotion'])->count());   // the promoted people were told
    }

    public function test_the_capacity_can_never_be_exceeded_however_many_register(): void
    {
        $e = $this->mkEvent(['capacity' => 5]);
        foreach (range(1, 12) as $i) {
            $this->reg($e, $this->alum("Crowd{$i}"), ['participants' => ($i % 3) + 1]);
        }
        $used = (int) DB::table('alumni_event_registrations')->where('event_id', $e)->whereIn('status', ['registered', 'confirmed', 'attended'])->sum('participants');

        $this->assertLessThanOrEqual(5, $used);
        $this->assertGreaterThan(0, DB::table('alumni_event_registrations')->where('event_id', $e)->where('status', 'waitlisted')->count());
    }

    public function test_capacity_cannot_be_lowered_below_the_seats_already_taken_and_raising_it_admits_the_waitlist(): void
    {
        $e = $this->mkEvent(['capacity' => 2]);
        [$a, $b, $c] = [$this->alum('A'), $this->alum('B'), $this->alum('C')];
        $this->reg($e, $a);
        $this->reg($e, $b);
        $this->reg($e, $c);                                                    // waitlisted

        [$low, $le] = $this->go(AlumniEventApiController::class, 'update', ['capacity' => 1], 7, 'Admin', [$e]);
        [$up] = $this->go(AlumniEventApiController::class, 'update', ['capacity' => 3], 7, 'Admin', [$e]);

        $this->assertSame(422, $low);
        $this->assertArrayHasKey('capacity', $le['errors']);
        $this->assertSame(200, $up);
        $this->assertSame('confirmed', $this->regRow($e, $c)->status);         // the extra seat went to the waiting list
    }

    // ------------------------------------------------------------- paid events

    public function test_a_paid_event_cannot_be_confirmed_or_checked_in_until_the_fee_is_paid_or_waived(): void
    {
        $e = $this->mkEvent(['fee_amount' => 500, 'event_date' => now()->toDateString()]);
        $a = $this->alum('Pays');

        [, $r] = $this->reg($e, $a);
        $rid = $r['data']['id'];
        [$early, $em] = $this->go(AlumniEventApiController::class, 'confirm', [], 7, 'Admin', [$e, $rid]);
        [$scan, $sm] = $this->go(AlumniEventApiController::class, 'attendance', ['method' => 'manual', 'registration_id' => $rid], 7, 'Admin', [$e]);
        [$badPay] = $this->go(AlumniEventApiController::class, 'payment', ['payment_status' => 'free'], 7, 'Admin', [$e, $rid]);
        $this->go(AlumniEventApiController::class, 'payment', ['payment_status' => 'paid'], 7, 'Admin', [$e, $rid]);
        [$ok, $okb] = $this->go(AlumniEventApiController::class, 'confirm', [], 7, 'Admin', [$e, $rid]);

        $this->assertSame('registered', $r['data']['status']);                 // not auto-confirmed: money is owed
        $this->assertSame('pending', $r['data']['payment_status']);
        $this->assertSame([409, 409, 422, 200], [$early, $scan, $badPay, $ok]);
        $this->assertStringContainsString('fee', $em['message']);
        $this->assertSame('confirmed', $okb['data']['status']);

        [$waived] = $this->reg($e, $this->alum('Waived'), ['payment_status' => 'waived']);
        $this->assertSame(201, $waived);
        $this->assertSame('confirmed', DB::table('alumni_event_registrations')->orderByDesc('id')->value('status'));
    }

    // -------------------------------------------------------------- attendance

    public function test_every_check_in_method_works_once_and_credits_engagement_once(): void
    {
        $e = $this->mkEvent(['event_date' => now()->toDateString()]);
        [$q, $n, $c, $m, $mob] = [$this->alum('Qr'), $this->alum('No'), $this->alum('Code'), $this->alum('Man'), $this->alum('Mob')];
        foreach ([$q, $n, $c, $m, $mob] as $x) {
            $this->reg($e, $x);
        }

        [$s1, $b1] = $this->go(AlumniEventApiController::class, 'attendance', ['method' => 'qr', 'value' => $this->regRow($e, $q)->qr_token], 7, 'Admin', [$e]);
        [$s2] = $this->go(AlumniEventApiController::class, 'attendance', ['method' => 'registration_no', 'value' => $this->regRow($e, $n)->registration_no], 7, 'Admin', [$e]);
        [$s3] = $this->go(AlumniEventApiController::class, 'attendance', ['method' => 'alumni_id', 'value' => $c->alumni_code], 7, 'Admin', [$e]);
        [$s4] = $this->go(AlumniEventApiController::class, 'attendance', ['method' => 'manual', 'registration_id' => $this->regRow($e, $m)->id], 7, 'Admin', [$e]);
        [$s5] = $this->go(AlumniEventApiController::class, 'attendance', ['method' => 'mobile', 'value' => $this->regRow($e, $mob)->qr_token], 7, 'Admin', [$e]);
        [$again, $ab] = $this->go(AlumniEventApiController::class, 'attendance', ['method' => 'qr', 'value' => $this->regRow($e, $q)->qr_token], 7, 'Admin', [$e]);

        $this->assertSame([200, 200, 200, 200, 200, 200], [$s1, $s2, $s3, $s4, $s5, $again]);
        $this->assertFalse($b1['data']['already_checked_in']);
        $this->assertSame($q->alumni_code, $b1['data']['alumnus']['alumni_code']);
        $this->assertTrue($ab['data']['already_checked_in']);                  // a second scan is friendly, not an error
        $this->assertSame(['qr', 'registration_no', 'alumni_id', 'manual', 'mobile'], DB::table('alumni_event_registrations')->orderBy('id')->pluck('attendance_method')->all());
        $this->assertSame(1, DB::table('alumni_activity')->where('alumni_id', $q->id)->where('activity_type', 'event_participation')->count());   // credited once
        $this->assertSame(10, (int) DB::table('alumni_activity')->where('alumni_id', $q->id)->where('activity_type', 'event_participation')->value('points'));
    }

    public function test_check_in_refuses_waitlisted_cancelled_unknown_foreign_and_too_early(): void
    {
        $e = $this->mkEvent(['event_date' => now()->toDateString(), 'capacity' => 1]);
        [$in, $wait, $gone] = [$this->alum('In'), $this->alum('Wait'), $this->alum('Gone')];
        $this->reg($e, $in);
        $this->reg($e, $wait);                                                 // waitlisted
        $this->reg($e, $gone);                                                 // waitlisted
        $this->go(AlumniEventApiController::class, 'cancelRegistration', [], 7, 'Admin', [$e, $this->regRow($e, $gone)->id]);
        $other = $this->mkEvent(['event_date' => now()->toDateString()]);
        $this->reg($other, $in);
        $far = $this->mkEvent(['event_date' => now()->addDays(5)->toDateString()]);
        $this->reg($far, $in);

        $checks = [
            $this->go(AlumniEventApiController::class, 'attendance', ['method' => 'qr', 'value' => $this->regRow($e, $wait)->qr_token], 7, 'Admin', [$e]),
            $this->go(AlumniEventApiController::class, 'attendance', ['method' => 'qr', 'value' => $this->regRow($e, $gone)->qr_token], 7, 'Admin', [$e]),
            $this->go(AlumniEventApiController::class, 'attendance', ['method' => 'qr', 'value' => 'not-a-real-token'], 7, 'Admin', [$e]),
            $this->go(AlumniEventApiController::class, 'attendance', ['method' => 'qr', 'value' => $this->regRow($other, $in)->qr_token], 7, 'Admin', [$e]),   // a ticket for a DIFFERENT event
            $this->go(AlumniEventApiController::class, 'attendance', ['method' => 'qr', 'value' => $this->regRow($far, $in)->qr_token], 7, 'Admin', [$far]),   // check-in opens the day before
            $this->go(AlumniEventApiController::class, 'attendance', ['method' => 'qr', 'value' => $this->regRow($e, $in)->qr_token], 8, 'Admin', [$e]),         // another institute
            $this->go(AlumniEventApiController::class, 'attendance', ['method' => 'telepathy', 'value' => 'x'], 7, 'Admin', [$e]),
        ];

        $this->assertSame([409, 409, 404, 404, 409, 404, 422], array_column($checks, 0));
        $this->assertStringContainsString('waiting list', $checks[0][1]['message']);
        $this->assertSame(0, DB::table('alumni_event_registrations')->where('status', 'attended')->count());
    }

    // ------------------------------------------- completion, report, feedback

    public function test_completing_marks_no_shows_and_the_report_matches_the_spec_figures(): void
    {
        $e = $this->mkEvent(['event_date' => now()->toDateString(), 'capacity' => 3]);
        [$a, $b, $c, $d] = [$this->alum('A'), $this->alum('B'), $this->alum('C'), $this->alum('D')];
        foreach ([$a, $b, $c, $d] as $x) {
            $this->reg($e, $x);                                                // D is waitlisted
        }
        $this->go(AlumniEventApiController::class, 'attendance', ['method' => 'alumni_id', 'value' => $a->alumni_code], 7, 'Admin', [$e]);

        [, $live] = $this->go(AlumniEventApiController::class, 'report', [], 7, 'Admin', [$e]);
        [$done, $db] = $this->go(AlumniEventApiController::class, 'complete', [], 7, 'Admin', [$e]);
        [, $r] = $this->go(AlumniEventApiController::class, 'report', [], 7, 'Admin', [$e]);
        [$twice] = $this->go(AlumniEventApiController::class, 'complete', [], 7, 'Admin', [$e]);

        $this->assertNull($live['data']['absent']);                            // not meaningful while the event is still open
        $this->assertSame(3, $live['data']['registered']);
        $this->assertSame(1, $live['data']['waitlisted']);
        $this->assertSame([200, 'completed'], [$done, $db['data']['status']]);
        $this->assertSame(1, $r['data']['attended']);
        $this->assertSame(2, $r['data']['no_show']);
        $this->assertSame(2, $r['data']['absent']);                            // the spec's "Absent"
        $this->assertSame(3, $r['data']['registered']);
        $this->assertSame(1, $r['data']['waitlisted']);                        // the waitlisted person is NOT counted absent
        $this->assertSame(409, $twice);
        // a late arrival can still be checked in by hand after completion
        [$late] = $this->go(AlumniEventApiController::class, 'attendance', ['method' => 'manual', 'registration_id' => $this->regRow($e, $b)->id], 7, 'Admin', [$e]);
        $this->assertSame(200, $late);
    }

    public function test_feedback_is_only_from_people_who_attended_once_and_is_averaged(): void
    {
        $e = $this->mkEvent(['event_date' => now()->toDateString()]);
        [$came, $stayed, $second] = [$this->alum('Came'), $this->alum('Stayed'), $this->alum('Second')];
        foreach ([$came, $stayed, $second] as $x) {
            $this->reg($e, $x);
        }
        foreach ([$came, $second] as $x) {
            $this->go(AlumniEventApiController::class, 'attendance', ['method' => 'alumni_id', 'value' => $x->alumni_code], 7, 'Admin', [$e]);
        }

        [$absent] = $this->go(AlumniEventApiController::class, 'feedback', ['alumni_id' => $stayed->id, 'rating' => 5], 7, 'Admin', [$e]);
        [$bad] = $this->go(AlumniEventApiController::class, 'feedback', ['alumni_id' => $came->id, 'rating' => 6], 7, 'Admin', [$e]);
        [$ok] = $this->go(AlumniEventApiController::class, 'feedback', ['alumni_id' => $came->id, 'rating' => 5, 'comments' => 'Great'], 7, 'Admin', [$e]);
        [$dup] = $this->go(AlumniEventApiController::class, 'feedback', ['alumni_id' => $came->id, 'rating' => 1], 7, 'Admin', [$e]);
        $this->go(AlumniEventApiController::class, 'feedback', ['alumni_id' => $second->id, 'rating' => 4], 7, 'Admin', [$e]);
        [, $r] = $this->go(AlumniEventApiController::class, 'report', [], 7, 'Admin', [$e]);

        $this->assertSame([409, 422, 201, 409], [$absent, $bad, $ok, $dup]);
        $this->assertSame(2, $r['data']['feedback_count']);
        $this->assertEquals(4.5, $r['data']['feedback_average']);
        $this->assertCount(2, $r['data']['feedback']);
    }

    // ------------------------------------------------------------ cancellation

    public function test_cancelling_an_event_cancels_every_seat_tells_everyone_and_freezes_it(): void
    {
        $e = $this->mkEvent(['capacity' => 1]);
        [$a, $b] = [$this->alum('A'), $this->alum('B')];
        $this->reg($e, $a);
        $this->reg($e, $b);                                                    // waitlisted

        [$s, $body] = $this->go(AlumniEventApiController::class, 'cancel', ['reason' => 'Venue unavailable'], 7, 'Admin', [$e]);
        [$edit] = $this->go(AlumniEventApiController::class, 'update', ['name' => 'Renamed'], 7, 'Admin', [$e]);
        [$again] = $this->go(AlumniEventApiController::class, 'cancel', [], 7, 'Admin', [$e]);
        [$reg] = $this->reg($e, $this->alum('Late'));

        $this->assertSame([200, 'cancelled'], [$s, $body['data']['status']]);
        $this->assertSame(0, DB::table('alumni_event_registrations')->where('event_id', $e)->where('status', '!=', 'cancelled')->count());
        $notices = $this->log(['comm_type' => 'announcement']);
        $this->assertCount(4, $notices);                                       // email + in-app, for both people
        $this->assertStringContainsString('Cancelled', (string) $notices[0]->subject);
        $this->assertSame([409, 409, 409], [$edit, $again, $reg]);
    }

    // ------------------------------------------------------- invitations, files

    public function test_inviting_creates_a_campaign_with_the_event_details_and_needs_a_published_event(): void
    {
        $draft = $this->mkEvent([], false);
        [$early] = $this->go(AlumniEventApiController::class, 'invite', ['channels' => ['email']], 7, 'Admin', [$draft]);
        $e = $this->mkEvent(['name' => 'Annual Meet', 'venue' => 'Town Hall', 'filters' => ['country' => 'India']]);
        $in = $this->alum('Invited', ['country' => 'India']);
        $out = $this->alum('Abroad', ['country' => 'Canada']);
        $this->consent($in, ['email']);
        $this->consent($out, ['email']);

        [$s, $b] = $this->go(AlumniEventApiController::class, 'invite', ['channels' => ['email']], 7, 'Admin', [$e]);
        $campaign = DB::table('alumni_campaigns')->first();
        $row = $this->log(['channel' => 'email'])->first();

        $this->assertSame(409, $early);
        $this->assertSame(201, $s);
        $this->assertSame('event_invitation', $campaign->comm_type);
        $this->assertStringContainsString('Annual Meet', $campaign->subject);
        $this->assertStringContainsString('Town Hall', $campaign->body);
        $this->assertStringContainsString('Country: India', $campaign->audience_label);   // the event's own audience is used
        $this->assertSame([(int) $in->id], $this->log(['channel' => 'email'])->pluck('alumni_id')->map(fn ($i) => (int) $i)->all());
        $this->assertStringContainsString('Annual Meet', (string) $row->subject);
    }

    public function test_event_files_are_private_typed_scoped_and_removable(): void
    {
        $e = $this->mkEvent();
        $pdf = UploadedFile::fake()->create('agenda.pdf', 40, 'application/pdf');
        $php = tempnam(sys_get_temp_dir(), 'ev');
        file_put_contents($php, '<?php echo 1; ?>');
        $evil = new UploadedFile($php, 'poster.png', 'image/png', null, true);   // PHP source pretending to be an image

        [$ok, $up] = [$this->act(AlumniEventApiController::class, 'fileUpload', [], 7, 'Admin', [$e], ['file' => $pdf])->getStatusCode(), null];
        $evilCode = $this->act(AlumniEventApiController::class, 'fileUpload', [], 7, 'Admin', [$e], ['file' => $evil])->getStatusCode();
        $row = DB::table('alumni_event_files')->first();
        $download = $this->act(AlumniEventApiController::class, 'fileDownload', [], 7, 'Admin', [$e, $row->id]);
        $otherTenant = $this->act(AlumniEventApiController::class, 'fileDownload', [], 8, 'Admin', [$e, $row->id]);
        $teacher = $this->act(AlumniEventApiController::class, 'fileDownload', [], 7, 'Teacher', [$e, $row->id]);
        [, $detail] = $this->go(AlumniEventApiController::class, 'show', [], 7, 'Admin', [$e]);
        $path = $row->file_path;
        [$del] = $this->go(AlumniEventApiController::class, 'fileDelete', [], 7, 'Admin', [$e, $row->id]);

        $this->assertSame([201, 422], [$ok, $evilCode]);
        $this->assertStringStartsWith("alumni-events/7/{$e}/", $path);
        $this->assertArrayNotHasKey('file_path', $detail['data']['files'][0]);
        $this->assertSame([200, 404, 403], [$download->getStatusCode(), $otherTenant->getStatusCode(), $teacher->getStatusCode()]);
        $this->assertSame(200, $del);
        Storage::disk('local')->assertMissing($path);
    }

    // --------------------------------------------------------------- reminders

    public function test_reminders_go_to_registered_alumni_once_inside_the_window_and_reunions_also_reach_the_unregistered(): void
    {
        $soon = $this->mkEvent(['event_date' => now()->addDays(2)->toDateString(), 'name' => 'Soon', 'event_type' => 'batch_reunion', 'filters' => ['batch' => '2017-18']]);
        $far = $this->mkEvent(['event_date' => now()->addDays(9)->toDateString(), 'name' => 'Far']);
        $registered = $this->alum('Reg');
        $cancelled = $this->alum('Canc');
        $unregistered = $this->alum('Unreg');
        $this->consent($unregistered, ['email']);
        foreach ([$registered, $cancelled] as $x) {
            $this->reg($soon, $x);
            $this->reg($far, $x);
        }
        $this->go(AlumniEventApiController::class, 'cancelRegistration', [], 7, 'Admin', [$soon, $this->regRow($soon, $cancelled)->id]);
        $service = app(AlumniEventReminders::class);

        $first = $service->run(7, now());
        $second = $service->run(7, now());                                     // run again the same day

        $reminders = $this->log(['comm_type' => 'event_reminder']);
        $this->assertSame(1, $first['events']);                                // "Far" is outside the 2-day window
        $this->assertEqualsCanonicalizing([(int) $registered->id], $reminders->pluck('alumni_id')->unique()->map(fn ($i) => (int) $i)->all());
        $this->assertSame(2, $reminders->count());                             // email + in-app, transactional
        $this->assertSame(0, $second['sent']);                                 // nobody is reminded twice
        $reunion = $this->log(['comm_type' => 'reunion']);
        $this->assertContains((int) $unregistered->id, $reunion->pluck('alumni_id')->map(fn ($i) => (int) $i)->all());   // the reunion reminder reaches an unregistered alumnus ...
        // consent-based: email (they agreed) and the in-app inbox (needs none) - not sms/whatsapp/mobile, which they never agreed to
        $this->assertEqualsCanonicalizing(['email', 'web'], $reunion->where('alumni_id', $unregistered->id)->pluck('channel')->unique()->values()->all());
    }

    // ---------------------------------------------------------------- listings

    public function test_listing_and_an_alumnus_history_are_scoped_and_filterable(): void
    {
        $mine = $this->mkEvent(['name' => 'Mine', 'event_type' => 'webinar', 'mode' => 'online', 'venue' => null, 'online_link' => 'https://x.example/m']);
        $this->mkEvent(['name' => 'Theirs'], true, 8);
        $a = $this->alum('Hist');
        $this->reg($mine, $a);

        [, $all] = $this->go(AlumniEventApiController::class, 'index');
        [, $web] = $this->go(AlumniEventApiController::class, 'index', ['event_type' => 'webinar']);
        [$bad] = $this->go(AlumniEventApiController::class, 'index', ['event_type' => 'party']);
        [, $hist] = $this->go(AlumniEventApiController::class, 'alumnusEvents', [], 7, 'Admin', [$a->id]);
        [$cross] = $this->go(AlumniEventApiController::class, 'alumnusEvents', [], 8, 'Admin', [$a->id]);

        $this->assertSame(['Mine'], array_column($all['data']['rows'], 'name'));
        $this->assertSame(1, $all['data']['rows'][0]['seats_used']);
        $this->assertCount(1, $web['data']['rows']);
        $this->assertSame([422, 404], [$bad, $cross]);
        $this->assertSame('confirmed', $hist['data'][0]['status']);
    }
}
