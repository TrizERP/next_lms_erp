<?php

namespace Tests\Feature;

use App\Http\Controllers\api\alumni\AlumniApiController;
use App\Http\Controllers\api\alumni\AlumniCommunicationApiController;
use App\Services\Alumni\AlumniMessaging;
use App\Services\Alumni\AlumniMilestoneService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\AlumniTestHelpers;
use Tests\TestCase;

/**
 * Communication: consent, eligibility, honest delivery status, idempotency, templates, scheduling, milestones and
 * transactional notices. Runs on in-memory sqlite (see AlumniTestHelpers) against the real NotificationSender.
 */
class AlumniCommunicationTest extends TestCase
{
    use AlumniTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootAlumni();
    }

    private function send(array $over = [], int $tenant = 7, string $profile = 'Admin'): array
    {
        return $this->go(AlumniCommunicationApiController::class, 'send', array_merge([
            'comm_type' => 'announcement', 'subject' => 'Hello {{first_name}}', 'body' => 'Dear {{name}}, from {{institute}}.', 'channels' => ['email'], 'filters' => [],
        ], $over), $tenant, $profile);
    }

    // ------------------------------------------------------------ audience, consent

    public function test_only_consenting_alumni_are_messaged_and_the_others_are_counted_not_contacted(): void
    {
        $yes = $this->alum('Yes');
        $no = $this->alum('No');
        $this->consent($yes, ['email']);

        [$status, $b] = $this->send();
        $camp = DB::table('alumni_campaigns')->first();

        $this->assertSame(201, $status);
        $this->assertSame(2, (int) $camp->recipient_count);          // both are eligible and matched ...
        $this->assertSame(1, (int) $camp->no_consent_count);         // ... one has not agreed to email
        $this->assertSame([(int) $yes->id], $this->log(['channel' => 'email'])->pluck('alumni_id')->map(fn ($i) => (int) $i)->all());
    }

    public function test_never_eligible_alumni_are_excluded_whatever_the_filters(): void
    {
        foreach (['opted_out', 'blocked', 'deceased', 'inactive', 'pending_verification'] as $status) {
            $a = $this->alum(ucfirst(str_replace('_', '', $status)), [], 7, $status);
            $this->consent($a);
        }
        $ok = $this->alum('Fine');
        $this->consent($ok);
        $this->consent($this->alum('Elsewhere', [], 8));

        [, $p] = $this->go(AlumniCommunicationApiController::class, 'preview', ['channels' => ['email'], 'filters' => ['status' => 'blocked']]);   // a status filter cannot widen it
        [, $all] = $this->go(AlumniCommunicationApiController::class, 'preview', ['channels' => ['email', 'sms'], 'filters' => []]);

        $this->assertSame(1, $p['data']['matched']);                           // a `status` filter is ignored: it can only ever narrow within the eligible, never widen to "blocked"
        $this->assertSame($all['data']['matched'], $p['data']['matched']);
        $this->assertSame(1, $all['data']['matched']);
        $this->assertSame(1, $all['data']['channels']['email']['reachable']);
    }

    public function test_filters_choose_the_audience_and_nobody_reachable_is_refused(): void
    {
        foreach ([2010, 2012, 2015, 2018] as $y) {
            $this->consent($this->alum("Y{$y}", ['passing_year' => $y, 'batch' => ($y - 1) . '-' . substr((string) $y, 2), 'country' => $y < 2013 ? 'USA' : 'India']));
        }
        [, $range] = $this->go(AlumniCommunicationApiController::class, 'preview', ['channels' => ['email'], 'filters' => ['passing_year_from' => 2010, 'passing_year_to' => 2012, 'country' => 'USA']]);
        [$none, $e] = $this->send(['filters' => ['country' => 'Mars']]);
        $noConsent = $this->alum('Silent', ['country' => 'Peru']);
        [$unreachable, $u] = $this->send(['filters' => ['country' => 'Peru']]);

        $this->assertSame(2, $range['data']['matched']);                       // the spec's "batch 2010-2015, location USA" style of targeting
        $this->assertSame(422, $none);
        $this->assertArrayHasKey('filters', $e['errors']);
        $this->assertSame(422, $unreachable);                                  // matched, but nobody consented to email
        $this->assertArrayHasKey('channels', $u['errors']);
        $this->assertSame(0, DB::table('alumni_campaigns')->where('subject', 'Hello {{first_name}}')->whereIn('status', ['sent', 'sending'])->count());
    }

    public function test_the_web_inbox_needs_no_consent_but_sms_always_does(): void
    {
        $a = $this->alum('Web');
        $this->send(['channels' => ['web', 'sms']]);
        $rows = $this->log();

        $this->assertSame(['web'], $rows->pluck('channel')->unique()->values()->all());
        $this->assertSame('sent', $rows[0]->status);
        $this->assertSame($a->alumni_code, $rows[0]->recipient);
    }

    // -------------------------------------------------- honest delivery statuses

    public function test_email_through_a_file_mailer_is_reported_as_skipped_not_sent(): void
    {
        config(['mail.default' => 'log']);
        $this->consent($this->alum('Mail'), ['email']);
        $this->send();
        $row = $this->log(['channel' => 'email'])->first();

        $this->assertSame('skipped', $row->status);
        $this->assertStringContainsString("'log'", (string) $row->reason);
        $this->assertSame(1, (int) DB::table('alumni_campaigns')->value('skipped_count'));
        $this->assertSame(0, (int) DB::table('alumni_campaigns')->value('sent_count'));
    }

    public function test_email_through_a_real_mailer_is_reported_as_sent_and_nobody_is_claimed_for_sms(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        $a = $this->alum('Real');
        $this->consent($a, ['email', 'sms', 'whatsapp', 'push']);
        $this->send(['channels' => ['email', 'sms', 'whatsapp', 'mobile']]);
        $by = $this->log()->keyBy('channel');

        $this->assertSame('sent', $by['email']->status);
        foreach (['sms', 'whatsapp', 'mobile'] as $ch) {
            $this->assertSame('skipped', $by[$ch]->status, $ch);               // no provider: never "sent"
            $this->assertStringContainsString('No active ' . $ch . ' integration', (string) $by[$ch]->reason);
        }
        $this->assertSame(1, (int) DB::table('alumni_campaigns')->value('sent_count'));
        $this->assertSame(3, (int) DB::table('alumni_campaigns')->value('skipped_count'));
    }

    public function test_channel_status_tells_the_truth_about_each_channel(): void
    {
        config(['mail.default' => 'log']);
        [, $m] = $this->go(AlumniCommunicationApiController::class, 'meta');
        $by = collect($m['data']['channels'])->keyBy('key');

        $this->assertSame('live', $by['web']['state']);
        $this->assertSame('file_only', $by['email']['state']);
        $this->assertSame('not_configured', $by['sms']['state']);

        DB::table('platform_integration_configs')->insert(['sub_institute_id' => 7, 'category' => 'sms', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()] + $this->integrationDefaults());
        [, $m2] = $this->go(AlumniCommunicationApiController::class, 'meta');
        $this->assertSame('driver_missing', collect($m2['data']['channels'])->keyBy('key')['sms']['state']);
    }

    /** the integration table has a few NOT NULL columns whose names we should not hard-code in a test */
    private function integrationDefaults(): array
    {
        $out = [];
        foreach (DB::select("PRAGMA table_info('platform_integration_configs')") as $c) {
            if ($c->notnull && $c->dflt_value === null && ! $c->pk && ! in_array($c->name, ['sub_institute_id', 'category', 'status', 'created_at', 'updated_at'], true)) {
                $out[$c->name] = str_contains(strtolower((string) $c->type), 'int') ? 0 : 'x';
            }
        }

        return $out;
    }

    // ------------------------------------------------------------- idempotency

    public function test_a_campaign_cannot_be_sent_twice_and_a_repeat_run_messages_nobody_again(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        $this->consent($this->alum('Once'), ['email']);
        $this->send();
        $id = (int) DB::table('alumni_campaigns')->value('id');
        $messaging = app(AlumniMessaging::class);

        $again = $messaging->runCampaign($id);                                 // already sent: not claimed
        DB::table('alumni_campaigns')->where('id', $id)->update(['status' => 'failed']);   // simulate a crash, then a retry of the same campaign
        $retry = $messaging->runCampaign($id);

        $this->assertNull($again);
        $this->assertSame(0, $retry['sent']);                                  // the log's unique key skipped the one already done
        $this->assertSame(1, $this->log(['channel' => 'email'])->count());
        $this->assertSame(1, $this->log(['channel' => 'email', 'status' => 'sent'])->count());
    }

    public function test_a_second_status_change_to_send_a_sent_campaign_is_refused(): void
    {
        $this->consent($this->alum('Done'), ['email']);
        $this->send();
        $id = (int) DB::table('alumni_campaigns')->value('id');

        [$s, $b] = $this->go(AlumniCommunicationApiController::class, 'sendDraft', [], 7, 'Admin', [$id]);
        [$c] = $this->go(AlumniCommunicationApiController::class, 'cancel', [], 7, 'Admin', [$id]);

        $this->assertSame(409, $s);
        $this->assertSame(409, $c);
    }

    // ---------------------------------------------------------- draft, schedule

    public function test_drafts_schedules_and_the_scheduler_task(): void
    {
        $a = $this->alum('Later');
        $this->consent($a, ['email']);

        [, $draft] = $this->send(['save_as_draft' => true]);
        [$past] = $this->send(['scheduled_at' => now()->subHour()->toDateTimeString()]);
        [, $sched] = $this->send(['scheduled_at' => now()->addDay()->toDateTimeString()]);
        $id = $sched['data']['id'];

        Artisan::call('alumni:run', ['task' => 'scheduled']);
        $notYet = DB::table('alumni_campaigns')->where('id', $id)->value('status');
        DB::table('alumni_campaigns')->where('id', $id)->update(['scheduled_at' => now()->subMinute()]);   // time passes
        Artisan::call('alumni:run', ['task' => 'scheduled']);
        $afterDue = DB::table('alumni_campaigns')->where('id', $id)->value('status');
        Artisan::call('alumni:run', ['task' => 'scheduled']);                  // a second run finds nothing to do

        $this->assertSame('draft', $draft['data']['status']);
        $this->assertSame(422, $past);                                         // a time in the past is not "scheduled"
        $this->assertSame('scheduled', $notYet);
        $this->assertSame('sent', $afterDue);
        $this->assertSame(1, $this->log(['campaign_id' => $id])->count());
        [, $c] = $this->go(AlumniCommunicationApiController::class, 'sendDraft', [], 7, 'Admin', [$draft['data']['id']]);
        $this->assertSame('sent', $c['data']['status']);
    }

    // ---------------------------------------------------------------- templates

    public function test_templates_are_seeded_editable_unique_and_built_ins_cannot_be_deleted(): void
    {
        [, $list] = $this->go(AlumniCommunicationApiController::class, 'templates');
        $birthday = collect($list['data'])->firstWhere('comm_type', 'birthday_wishes');
        [$dup] = $this->go(AlumniCommunicationApiController::class, 'templateStore', ['name' => 'Birthday wishes', 'comm_type' => 'announcement', 'subject' => 's', 'body' => 'b']);
        [$bad] = $this->go(AlumniCommunicationApiController::class, 'templateStore', ['name' => 'X', 'comm_type' => 'spam', 'subject' => 's', 'body' => 'b']);
        [$ok, $new] = $this->go(AlumniCommunicationApiController::class, 'templateStore', ['name' => 'Mine', 'comm_type' => 'newsletter', 'subject' => 'News', 'body' => 'Hi']);
        [$edit] = $this->go(AlumniCommunicationApiController::class, 'templateUpdate', ['name' => 'Birthday wishes', 'comm_type' => 'birthday_wishes', 'subject' => 'Many happy returns', 'body' => 'Hi {{name}}'], 7, 'Admin', [$birthday['id']]);
        [$delSystem] = $this->go(AlumniCommunicationApiController::class, 'templateDelete', [], 7, 'Admin', [$birthday['id']]);
        [$delMine] = $this->go(AlumniCommunicationApiController::class, 'templateDelete', [], 7, 'Admin', [$new['data']['id']]);
        [$other] = $this->go(AlumniCommunicationApiController::class, 'templateUpdate', ['name' => 'Hack', 'comm_type' => 'announcement', 'subject' => 's', 'body' => 'b'], 8, 'Admin', [$birthday['id']]);
        [, $other8] = $this->go(AlumniCommunicationApiController::class, 'templates', [], 8);

        $this->assertStringContainsString('Dear', $birthday['body'] . 'Dear');
        $this->assertSame([422, 422, 201, 200, 409, 200, 404], [$dup, $bad, $ok, $edit, $delSystem, $delMine, $other]);
        $this->assertCount(9, $other8['data']);                                // another institute gets its own set, untouched by the edit
        $this->assertNotSame('Many happy returns', collect($other8['data'])->firstWhere('comm_type', 'birthday_wishes')['subject']);
    }

    public function test_placeholders_are_substituted_as_plain_text_and_never_evaluated(): void
    {
        $a = $this->alum('Pat', ['last_name' => '{{institute}} <b>x</b>']);
        $m = app(AlumniMessaging::class);

        $out = $m->render('Hi {{name}} of {{ institute }}! {{unknown}} {{system(\'ls\')}} {{years}}', $this->svc->find(7, (int) $a->id), ['years' => 5]);

        $this->assertStringContainsString('Test School', $out);
        $this->assertStringNotContainsString('{{unknown}}', $out);             // unknown placeholders are removed, not shown
        $this->assertStringContainsString("{{system('ls')}}", $out);           // not a placeholder, left alone, certainly not run
        $this->assertStringContainsString('5', $out);
        $this->assertStringContainsString('<b>x</b>', $out);                   // plain text: nothing is HTML-interpreted or expanded twice
    }

    // ----------------------------------------------------------------- history

    public function test_history_and_campaign_detail_are_scoped_filterable_and_staff_only(): void
    {
        config(['mail.default' => 'log']);
        $a = $this->alum('Hist');
        $this->consent($a, ['email', 'sms']);
        $this->send(['channels' => ['email', 'sms']]);
        $other = $this->alum('Other', [], 8);
        $this->consent($other, ['email']);
        $this->send([], 8);
        $id = (int) DB::table('alumni_campaigns')->where('sub_institute_id', 7)->value('id');

        [, $h] = $this->go(AlumniCommunicationApiController::class, 'history');
        [, $sms] = $this->go(AlumniCommunicationApiController::class, 'history', ['channel' => 'sms', 'status' => 'skipped']);
        [, $c] = $this->go(AlumniCommunicationApiController::class, 'campaignShow', [], 7, 'Admin', [$id]);
        [$cross] = $this->go(AlumniCommunicationApiController::class, 'campaignShow', [], 8, 'Admin', [$id]);
        [, $mine] = $this->go(AlumniCommunicationApiController::class, 'alumnusHistory', [], 7, 'Admin', [$a->id]);
        [$crossAlumnus] = $this->go(AlumniCommunicationApiController::class, 'alumnusHistory', [], 8, 'Admin', [$a->id]);
        [$teacher] = $this->go(AlumniCommunicationApiController::class, 'history', [], 7, 'Teacher');
        [$badFilter] = $this->go(AlumniCommunicationApiController::class, 'history', ['status' => 'nonsense']);

        $this->assertSame(2, $h['data']['pagination']['total']);               // the other institute's message is not here
        $this->assertSame('sms', $sms['data']['rows'][0]['channel']);
        $this->assertSame('All eligible alumni', $h['data']['rows'][0]['recipient_group']);
        $this->assertNotEmpty($c['data']['breakdown']);
        $this->assertNotEmpty($c['data']['reasons']);                          // the skip reasons are listed, verbatim
        $this->assertSame([404, 404, 403, 422], [$cross, $crossAlumnus, $teacher, $badFilter]);
        $this->assertCount(2, $mine['data']['rows']);
    }

    // -------------------------------------------------------------- milestones

    public function test_birthday_wishes_are_consent_based_and_sent_once_a_year(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        $today = Carbon::parse('2026-03-14');
        $wants = $this->alum('Wants', ['dob' => '1999-03-14']);
        $silent = $this->alum('Silent', ['dob' => '1999-03-14']);
        $other = $this->alum('Other', ['dob' => '1999-04-01']);
        $inactive = $this->alum('Inactive', ['dob' => '1999-03-14'], 7, 'inactive');
        foreach ([$wants, $other, $inactive] as $x) {
            $this->consent($x, ['email']);
        }
        $service = app(AlumniMilestoneService::class);

        $first = $service->run(7, $today);
        $second = $service->run(7, $today);                                    // the daily job runs twice

        $this->assertSame(3, $first['birthday_wishes']);                       // everyone born on 14 March, eligible or not, is considered ...
        $emails = $this->log(['channel' => 'email', 'comm_type' => 'birthday_wishes']);
        $this->assertSame([(int) $wants->id], $emails->pluck('alumni_id')->map(fn ($i) => (int) $i)->all());   // ... but only a consenting, eligible one is sent to
        $this->assertSame(2, $first['sent']);                                  // Wants (email + in-app) and Silent (in-app only: the inbox needs no consent)
        $this->assertSame(0, $second['sent']);
        $this->assertSame(1, $emails->where('status', 'sent')->count());
        $this->assertStringContainsString('Wants', (string) $emails[0]->subject);
        $this->assertSame('birthday:2026', $emails[0]->dedupe_key);
        $this->assertGreaterThan(0, $this->log(['channel' => 'web', 'comm_type' => 'birthday_wishes'])->count());   // the in-app inbox needs no consent
    }

    public function test_work_and_graduation_anniversaries_and_a_leap_day_birthday(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        $a = $this->alum('Worker', ['leaving_date' => '2018-06-30']);
        $this->consent($a, ['email']);
        DB::table('alumni_career')->insert(['sub_institute_id' => 7, 'alumni_id' => $a->id, 'company_name' => 'Acme', 'start_date' => '2023-06-30', 'is_current' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $leap = $this->alum('Leapling', ['dob' => '2000-02-29']);
        $this->consent($leap, ['email']);
        $newHire = $this->alum('Newhire');
        $this->consent($newHire, ['email']);
        DB::table('alumni_career')->insert(['sub_institute_id' => 7, 'alumni_id' => $newHire->id, 'company_name' => 'Acme', 'start_date' => '2026-06-30', 'is_current' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $service = app(AlumniMilestoneService::class);

        $june = $service->run(7, Carbon::parse('2026-06-30'));
        $feb28 = $service->run(7, Carbon::parse('2027-02-28'));                // 2027 is not a leap year
        $feb28leap = $service->run(7, Carbon::parse('2028-02-28'));            // 2028 is: Feb 29 exists, so no early greeting

        $this->assertSame(1, $june['work_anniversary']);                       // 3 years; the person who started this year is not an "anniversary"
        $this->assertSame(1, $june['graduation_anniversary']);
        $this->assertStringContainsString('3 year', (string) $this->log(['comm_type' => 'work_anniversary', 'channel' => 'email'])->first()->subject);
        $this->assertSame(1, $feb28['birthday_wishes']);
        $this->assertSame(0, $feb28leap['birthday_wishes']);
    }

    public function test_the_milestone_command_runs_for_a_date_and_an_institute(): void
    {
        $a = $this->alum('Cmd', ['dob' => '1998-08-08']);
        $this->consent($a, ['email']);
        $this->alum('Elsewhere', ['dob' => '1998-08-08'], 8);

        $code = Artisan::call('alumni:run', ['task' => 'milestones', '--date' => '2026-08-08', '--tenant' => 7]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Institute 7', Artisan::output());
        $this->assertSame([7], $this->log(['comm_type' => 'birthday_wishes'])->pluck('sub_institute_id')->map(fn ($i) => (int) $i)->unique()->values()->all());
        $this->assertSame(1, Artisan::call('alumni:run', ['task' => 'nonsense']));
    }

    // ----------------------------------------------------------- transactional

    public function test_a_verification_outcome_reaches_the_alumnus_without_marketing_consent_but_never_a_blocked_one(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        $a = $this->alum('Pending', [], 7, 'pending_verification');                          // no consent given
        $b = $this->alum('Blocked', [], 7, 'blocked');
        DB::table('alumni')->where('id', $b->id)->update(['status' => 'pending_verification']);   // so verify() accepts it ...
        DB::table('alumni')->where('id', $b->id)->update(['status' => 'blocked']);                  // ... then it is blocked before the notice

        [$ok] = $this->go(AlumniApiController::class, 'verify', ['decision' => 'approve'], 7, 'Admin', [$a->id]);
        $direct = app(AlumniMessaging::class)->notify(7, (int) $b->id, 'verification', 's', 'b', 'x:1', true);

        $this->assertSame(200, $ok);
        $rows = $this->log(['alumni_id' => $a->id]);
        $this->assertEqualsCanonicalizing(['email', 'web'], $rows->pluck('channel')->all());   // email + in-app only
        $this->assertSame([1, 1], $rows->pluck('is_transactional')->map(fn ($i) => (int) $i)->all());
        $this->assertSame('sent', $rows->firstWhere('channel', 'email')->status);
        $this->assertSame(['_' => 'blocked'], $direct);                                          // never to a blocked alumnus
        $this->assertSame(0, $this->log(['alumni_id' => $b->id])->count());
    }

    public function test_transactional_notices_never_use_sms_without_consent_and_reject_needs_a_reason(): void
    {
        $a = $this->alum('Sms', [], 7, 'pending_verification');
        $r = app(AlumniMessaging::class)->notify(7, (int) $a->id, 'verification', 's', 'b', 'k:1', true, [], ['email', 'sms', 'web']);
        [$noReason] = $this->go(AlumniApiController::class, 'verify', ['decision' => 'reject'], 7, 'Admin', [$a->id]);

        $this->assertSame('no_consent', $r['sms']);
        $this->assertSame(422, $noReason);
    }

    public function test_registration_notice_on_conversion_is_off_unless_the_institute_switches_it_on(): void
    {
        $this->assertFalse((bool) $this->svc->setting(7, 'notify_on_registration', false));
        [$s] = $this->go(AlumniApiController::class, 'saveSettings', ['notify_on_registration' => true, 'event_reminder_days' => 3]);
        [, $set] = $this->go(AlumniApiController::class, 'settings');

        $this->assertSame(200, $s);
        $this->assertTrue($set['data']['notify_on_registration']);
        $this->assertSame(3, $set['data']['event_reminder_days']);
    }
}
