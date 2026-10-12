<?php

namespace Tests\Feature;

use App\Http\Controllers\api\alumni\AlumniDonationApiController;
use App\Http\Controllers\api\alumni\AlumniReportApiController as C;
use Illuminate\Support\Facades\DB;
use Tests\Support\AlumniTestHelpers;
use Tests\TestCase;

/**
 * Reports are a sharing surface. These tests pin: who may open which report, that institutes never see each other's data,
 * that a field an alumnus made private is blank in a row report and absent from a counting report (an aggregate must not
 * read a private value back out), that salary is never reported, that counts are right, and that the export is the same data,
 * formula-safe and audited. In-memory sqlite (see AlumniTestHelpers).
 */
class AlumniReportsTest extends TestCase
{
    use AlumniTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootAlumni();
    }

    private function report(string $key, array $f = [], int $tenant = 7, string $profile = 'Admin'): array
    {
        return $this->go(C::class, 'show', $f, $tenant, $profile, [$key]);
    }

    private function hide(object $a, string ...$groups): void
    {
        foreach ($groups as $g) {
            DB::table('alumni_privacy')->insert(['sub_institute_id' => $a->sub_institute_id, 'alumni_id' => $a->id, 'field_key' => $g, 'visibility' => 'private', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** current_company / current_industry are a snapshot written from career records, so tests set them directly. */
    private function works(object $a, string $company, ?string $industry = null): object
    {
        DB::table('alumni')->where('id', $a->id)->update(['current_company' => $company, 'current_industry' => $industry]);

        return $a;
    }

    private function job(object $a, array $over = []): void
    {
        DB::table('alumni_career')->insert(array_merge(['sub_institute_id' => $a->sub_institute_id, 'alumni_id' => $a->id, 'employment_status' => 'employed', 'company_name' => 'Acme', 'industry' => 'IT',
            'job_title' => 'Engineer', 'is_current' => 1, 'salary_range' => '30-40 LPA', 'created_at' => now(), 'updated_at' => now()], $over));
    }

    private function study(object $a, array $over = []): void
    {
        DB::table('alumni_education')->insert(array_merge(['sub_institute_id' => $a->sub_institute_id, 'alumni_id' => $a->id, 'qualification' => 'MBA', 'institution' => 'IIM X', 'country' => 'India',
            'passing_year' => 2021, 'created_at' => now(), 'updated_at' => now()], $over));
    }

    private function csv($response): array
    {
        ob_start();
        $response->sendContent();
        $text = ltrim((string) ob_get_clean(), "\xEF\xBB\xBF");

        return array_map('str_getcsv', array_filter(explode("\n", $text), 'strlen'));
    }

    // ------------------------------------------------------------------------ access

    public function test_reports_are_listed_and_gated_by_role(): void
    {
        $this->alum('Gate');
        [$s, $b] = $this->go(C::class, 'index', [], 7, 'Admin');
        $this->assertSame(9, count($b['data']));
        $this->assertContains('donation', array_column($b['data'], 'key'));

        $coordinator = array_column($this->go(C::class, 'index', [], 7, 'Alumni Coordinator')[1]['data'], 'key');
        $this->assertNotContains('donation', $coordinator, 'managing alumni is not the same as seeing money');
        $this->assertCount(8, $coordinator);
        $this->assertSame(['donation'], array_column($this->go(C::class, 'index', [], 7, 'Accountant')[1]['data'], 'key'));

        $this->assertSame(403, $this->go(C::class, 'index', [], 7, 'Teacher')[0]);
        $this->assertSame(403, $this->report('master', [], 7, 'Teacher')[0]);
        $this->assertSame(403, $this->report('donation', [], 7, 'Alumni Coordinator')[0]);
        $this->assertSame(403, $this->report('master', [], 7, 'Accountant')[0]);
        $this->assertSame(403, $this->go(C::class, 'export', ['format' => 'csv'], 7, 'Teacher', ['master'])[0]);
        $this->assertSame(404, $this->report('nope')[0]);
        $this->assertSame(422, $this->report('master', ['page' => 0])[0]);
        $this->assertSame(422, $this->report('event', ['from' => 'yesterday-ish'])[0]);
    }

    // ---------------------------------------------------------------- master + masking

    public function test_master_report_is_tenant_scoped_filtered_paged_and_masked(): void
    {
        $this->alum('Open', ['city' => 'Pune', 'batch' => '2017-18']);
        $hidden = $this->alum('Shy', ['city' => 'Pune', 'batch' => '2017-18', 'email' => 'shy@example.org']);
        $this->alum('Other', ['city' => 'Pune', 'batch' => '2019-20']);
        $this->alum('Foreign', ['city' => 'Pune', 'batch' => '2017-18'], 8);
        $this->hide($hidden, 'email', 'mobile', 'location', 'employment');

        [, $b] = $this->report('master', ['batch' => '2017-18']);
        $rows = collect($b['data']['rows'])->keyBy('full_name');
        $this->assertCount(2, $rows, 'another batch and another institute are excluded');
        $this->assertNull($rows['Shy Alum']['email']);
        $this->assertNull($rows['Shy Alum']['mobile']);
        $this->assertNull($rows['Shy Alum']['city']);
        $this->assertSame('Pune', $rows['Open Alum']['city']);
        $this->assertNotNull($rows['Open Alum']['mobile'], 'staff see what the institution is allowed to see');
        $this->assertSame(2, $b['data']['pagination']['total']);
        $this->assertEqualsCanonicalizing(['alumni_code', 'full_name', 'batch', 'passing_year', 'course', 'mobile', 'email', 'city', 'country', 'employment_status', 'current_company', 'current_job_title', 'status', 'profile_completion'], array_keys($b['data']['rows'][0]), 'no stray columns reach the client');

        [, $p1] = $this->report('master', ['per_page' => 2, 'page' => 1]);
        [, $p2] = $this->report('master', ['per_page' => 2, 'page' => 2]);
        $this->assertCount(2, $p1['data']['rows']);
        $this->assertCount(1, $p2['data']['rows']);
        $this->assertSame(3, $p1['data']['pagination']['total']);
        $this->assertSame(2, $p1['data']['pagination']['last_page']);
    }

    // ------------------------------------------------------------------------- batch

    public function test_batch_report_counts_and_leaves_out_private_employment(): void
    {
        $a = $this->alum('A', ['batch' => '2017-18', 'passing_year' => 2018, 'employment_status' => 'employed']);
        $b = $this->alum('B', ['batch' => '2017-18', 'passing_year' => 2018, 'employment_status' => 'employed']);
        $c = $this->alum('C', ['batch' => '2017-18', 'passing_year' => 2018, 'employment_status' => 'entrepreneur']);
        $this->alum('D', ['batch' => '2019-20', 'passing_year' => 2020, 'employment_status' => 'job_seeking']);
        $this->alum('E', ['batch' => '2017-18', 'passing_year' => 2018, 'employment_status' => 'employed'], 8);
        $this->study($a);
        $this->hide($b, 'employment');   // B does not want their employment counted

        [, $r] = $this->report('batch');
        $rows = collect($r['data']['rows'])->keyBy('batch');
        $this->assertSame(['2019-20', '2017-18'], array_column($r['data']['rows'], 'batch'), 'newest passing year first');
        $this->assertSame(3, (int) $rows['2017-18']['total']);
        $this->assertSame(1, $rows['2017-18']['employed'], 'B is hidden, E is another institute');
        $this->assertSame(1, $rows['2017-18']['entrepreneurs']);
        $this->assertSame(1, $rows['2017-18']['higher_education']);
        $this->assertSame(0, $rows['2019-20']['employed']);
    }

    // ----------------------------------------------------------- career, education

    public function test_career_report_hides_private_employment_and_never_shows_salary(): void
    {
        $open = $this->alum('Open');
        $shy = $this->alum('Shy');
        $foreign = $this->alum('Foreign', [], 8);
        $this->job($open, ['company_name' => 'Acme', 'industry' => 'IT']);
        $this->job($open, ['company_name' => 'OldCo', 'industry' => 'Finance', 'is_current' => 0]);
        $this->job($shy, ['company_name' => 'SecretCo']);
        $this->job($foreign, ['company_name' => 'ForeignCo'], 8);
        $this->hide($shy, 'employment');

        [, $b] = $this->report('career');
        $companies = array_column($b['data']['rows'], 'company_name');
        $this->assertEqualsCanonicalizing(['Acme', 'OldCo'], $companies);
        $this->assertStringNotContainsString('LPA', json_encode($b), 'salary is never reported');
        $this->assertArrayNotHasKey('salary_range', $b['data']['rows'][0]);
        $this->assertSame('Yes', $b['data']['rows'][0]['current'], 'current jobs first');

        $this->assertSame(['Acme'], array_column($this->report('career', ['current_only' => 1])[1]['data']['rows'], 'company_name'));
        $this->assertSame(['OldCo'], array_column($this->report('career', ['industry' => 'Finance'])[1]['data']['rows'], 'company_name'));
        $this->assertSame(['Acme'], array_column($this->report('career', ['company' => 'cm'])[1]['data']['rows'], 'company_name'));
        $this->assertSame([], $this->report('career', ['company' => '%'])[1]['data']['rows'], 'wildcards are escaped');
    }

    public function test_higher_education_report_respects_privacy_filters_and_tenant(): void
    {
        $a = $this->alum('A');
        $b = $this->alum('B');
        $f = $this->alum('F', [], 8);
        $this->study($a, ['institution' => 'IIM Ahmedabad', 'qualification' => 'MBA']);
        $this->study($b, ['institution' => 'MIT', 'qualification' => 'MS']);
        $this->study($f, ['institution' => 'Oxford'], 8);
        $this->hide($b, 'higher_education');

        [, $r] = $this->report('higher_education');
        $this->assertSame(['IIM Ahmedabad'], array_column($r['data']['rows'], 'institution'));
        $this->assertSame(['IIM Ahmedabad'], array_column($this->report('higher_education', ['institution' => 'iim'])[1]['data']['rows'], 'institution'));
        $this->assertSame([], $this->report('higher_education', ['qualification' => 'PhD'])[1]['data']['rows']);
    }

    // ------------------------------------------------------- location, company

    public function test_location_and_company_reports_count_only_what_may_be_seen(): void
    {
        $pune = ['city' => 'Pune', 'state' => 'MH', 'country' => 'India'];
        $this->works($this->alum('A', $pune), 'Acme', 'IT');
        $this->works($this->alum('B', $pune), 'Acme', 'IT');
        $shy = $this->works($this->alum('C', $pune), 'Acme', 'IT');
        $this->works($this->alum('D', ['city' => 'Surat', 'state' => 'GJ', 'country' => 'India']), 'Beta');
        $this->works($this->alum('E', $pune, 8), 'Acme');
        $this->hide($shy, 'location', 'employment');

        $loc = collect($this->report('location')[1]['data']['rows'])->keyBy('city');
        $this->assertSame(2, (int) $loc['Pune']['total'], 'C is hidden, E is another institute');
        $this->assertSame(1, (int) $loc['Surat']['total']);

        $co = collect($this->report('company_industry')[1]['data']['rows'])->keyBy('company');
        $this->assertSame(2, (int) $co['Acme']['total']);
        $this->assertSame('Not specified', $co['Beta']['industry']);
        $this->assertSame(['Surat'], array_column($this->report('location', ['city' => 'Surat'])[1]['data']['rows'], 'city'));
    }

    // ---------------------------------------------------------- engagement, events

    public function test_engagement_report_scores_and_levels(): void
    {
        $busy = $this->alum('Busy');
        $quiet = $this->alum('Quiet');
        $this->alum('Away', [], 8);
        $eng = app(\App\Services\Alumni\AlumniEngagementService::class);
        $eng->record(7, (int) $busy->id, 'guest_lecture', 'Talk', 1, null, 30);
        $eng->record(7, (int) $busy->id, 'mentoring', 'Mentor', 1, null, 60);

        [, $r] = $this->report('engagement');
        $this->assertSame(['Busy Alum', 'Quiet Alum'], array_column($r['data']['rows'], 'full_name'), 'most engaged first, other institute excluded');
        $this->assertSame(90, $r['data']['rows'][0]['score']);
        $this->assertSame(2, (int) $r['data']['rows'][0]['activities']);
        $this->assertSame(0, $r['data']['rows'][1]['score']);
        $this->assertNotSame($r['data']['rows'][0]['level'], $r['data']['rows'][1]['level']);
    }

    public function test_event_report_counts_registrations_attendance_and_feedback(): void
    {
        $a = $this->alum('A');
        $b = $this->alum('B');
        $c = $this->alum('C');
        $ev = (int) DB::table('alumni_events')->insertGetId(['sub_institute_id' => 7, 'name' => 'Reunion', 'event_type' => 'batch_reunion', 'event_date' => '2026-01-10', 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('alumni_events')->insert(['sub_institute_id' => 8, 'name' => 'Foreign', 'event_type' => 'webinar', 'event_date' => '2026-01-11', 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([[$a, 'attended'], [$b, 'no_show'], [$c, 'cancelled']] as $i => [$al, $st]) {
            DB::table('alumni_event_registrations')->insert(['sub_institute_id' => 7, 'event_id' => $ev, 'alumni_id' => $al->id, 'registration_no' => 'R' . $i, 'participants' => 1, 'status' => $st, 'qr_token' => 'q' . $i, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('alumni_event_feedback')->insert(['sub_institute_id' => 7, 'event_id' => $ev, 'alumni_id' => $a->id, 'rating' => 4, 'created_at' => now(), 'updated_at' => now()]);

        [, $r] = $this->report('event');
        $this->assertCount(1, $r['data']['rows'], 'the other institute\'s event is not listed');
        $row = $r['data']['rows'][0];
        $this->assertSame([2, 1, 1, 50], [(int) $row['registered'], (int) $row['attended'], (int) $row['no_show'], $row['attendance_rate']], 'cancelled registrations do not count as registered');
        $this->assertEquals(4.0, $row['rating']);
        $this->assertCount(0, $this->report('event', ['from' => '2026-02-01'])[1]['data']['rows']);
        $this->assertSame('Batch reunion', $row['event_type']);
    }

    // --------------------------------------------------------------------- donations

    public function test_donation_report_is_finance_only_and_masks_anonymous_donors(): void
    {
        $open = $this->alum('Open');
        $anon = $this->alum('Hidden');
        $don = fn (object $a, array $d) => $this->go(AlumniDonationApiController::class, 'store', array_merge(['alumni_id' => $a->id, 'payment_mode' => 'cash', 'received_on' => now()->toDateString()], $d), 7, 'Accountant');
        $id1 = $don($open, ['amount' => 1000])[1]['data']['id'];
        $id2 = $don($anon, ['amount' => 5000, 'anonymous' => true])[1]['data']['id'];
        $this->actingUser = 56;   // verified by a different person than the recorder
        $this->go(AlumniDonationApiController::class, 'verify', [], 7, 'Accountant', [$id1]);
        $this->go(AlumniDonationApiController::class, 'verify', [], 7, 'Accountant', [$id2]);
        $this->actingUser = 55;

        [$s, $b] = $this->report('donation', [], 7, 'Accountant');
        $this->assertSame(200, $s);
        $names = array_column($b['data']['rows'], 'full_name');
        $this->assertContains('Anonymous donor', $names);
        $this->assertNotContains('Hidden Alum', $names);
        $this->assertStringNotContainsString('Hidden', json_encode($b));
        $this->assertEquals(6000, collect($b['data']['summary'])->firstWhere('label', 'Raised (verified, net)')['value']);
        $this->assertSame(0, $this->report('donation', [], 8, 'Accountant')[1]['data']['pagination']['total'], 'another institute sees none');
        [$fs, $fb] = $this->report('donation', ['status' => 'verified', 'campaign_id' => 999]);
        $this->assertSame(200, $fs);
        $this->assertSame([], $fb['data']['rows'], 'an unknown campaign filter yields an empty list, not an error or everything');
    }

    // -------------------------------------------------------------------------- export

    public function test_csv_export_matches_the_report_is_masked_formula_safe_and_audited(): void
    {
        $this->alum('Normal', ['city' => 'Pune']);
        $this->works($this->alum('Evil', ['city' => 'Pune']), '=HYPERLINK("http://x","click")');
        $hidden = $this->alum('Shy', ['city' => 'Pune', 'email' => 'shy@example.org']);
        $this->alum('Foreign', ['city' => 'Pune'], 8);
        $this->hide($hidden, 'email');

        $res = $this->act(C::class, 'export', ['format' => 'csv', 'city' => 'Pune'], 7, 'Admin', ['master']);
        $this->assertSame(200, $res->getStatusCode());
        $this->assertStringContainsString('attachment', (string) $res->headers->get('content-disposition'));
        $this->assertSame('nosniff', $res->headers->get('x-content-type-options'));
        $rows = $this->csv($res);
        $this->assertSame('Alumni ID', $rows[0][0]);
        $this->assertCount(4, $rows, 'header + the three alumni of this institute');
        $text = json_encode($rows);
        $this->assertStringNotContainsString('shy@example.org', $text, 'masked in the file exactly as on screen');
        $this->assertStringNotContainsString('Foreign', $text);
        $companyCol = array_search('Company', $rows[0], true);
        $evilRow = collect(array_slice($rows, 1))->first(fn ($r) => str_contains($r[1], 'Evil'));
        $this->assertStringStartsWith("'=HYPERLINK", $evilRow[$companyCol], 'a formula is neutralised');

        $audit = DB::table('platform_audit_log')->where('action', 'export')->where('component', 'alumni.reports')->first();
        $this->assertNotNull($audit);
        $this->assertStringContainsString('master', (string) $audit->after_json);
    }

    public function test_xlsx_export_and_export_gates(): void
    {
        $this->alum('X');
        $xlsx = $this->act(C::class, 'export', ['format' => 'xlsx'], 7, 'Admin', ['batch']);
        $this->assertSame(200, $xlsx->getStatusCode());
        $this->assertStringContainsString('alumni-report-batch-', (string) $xlsx->headers->get('content-disposition'));
        $this->assertStringContainsString('.xlsx', (string) $xlsx->headers->get('content-disposition'));

        [$bad] = $this->go(C::class, 'export', ['format' => 'pdf'], 7, 'Admin', ['master']);
        $this->assertSame(422, $bad);
        $this->assertSame(403, $this->go(C::class, 'export', ['format' => 'csv'], 7, 'Alumni Coordinator', ['donation'])[0], 'a coordinator cannot export donations');
        $this->assertSame(404, $this->go(C::class, 'export', ['format' => 'csv'], 7, 'Admin', ['payroll'])[0]);
        $this->assertSame(1, DB::table('platform_audit_log')->where('component', 'alumni.reports')->where('action', 'export')->count(), 'only the successful export is logged, not the refused ones');
    }
}
