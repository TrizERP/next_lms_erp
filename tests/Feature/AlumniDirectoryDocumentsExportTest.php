<?php

namespace Tests\Feature;

use App\Http\Controllers\api\alumni\AlumniApiController;
use App\Http\Controllers\api\alumni\AlumniDirectoryApiController;
use App\Http\Controllers\api\alumni\AlumniDocumentApiController;
use App\Http\Controllers\api\alumni\AlumniExportApiController;
use App\Services\Alumni\AlumniPrivacy;
use App\Services\Alumni\AlumniService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Directory privacy, privacy editor, batches, documents, export and bulk actions.
 *
 * RUNS ON IN-MEMORY SQLITE, FORCED IN setUp (phpunit.xml does not force it); setUp refuses to continue
 * otherwise. Files use a fake private disk, so nothing is written to storage/.
 */
class AlumniDirectoryDocumentsExportTest extends TestCase
{
    private AlumniService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'refusing to run against a real database');
        (require base_path('database/migrations/2026_10_10_100000_create_alumni_core_tables.php'))->up();
        Storage::fake('local');
        $this->svc = app(AlumniService::class);
    }

    private function act(string $controller, string $method, array $input = [], int $tenant = 7, string $profile = 'Admin', array $args = [], array $files = [], bool $student = false)
    {
        $request = Request::create('/x', 'POST', $input, [], $files);
        $request->merge(['type' => 'API']);   // what the api.session middleware does to every request
        $store = app('session.store');
        $store->flush();
        $request->setLaravelSession($store);
        $store->put('sub_institute_id', $tenant);
        $store->put('user_id', 55);
        $store->put('user_profile_name', $profile);
        $store->put('is_student', $student);

        return app($controller)->{$method}($request, ...$args);
    }

    private function call2(string $controller, string $method, array $input = [], int $tenant = 7, string $profile = 'Admin', array $args = [], array $files = []): array
    {
        $response = $this->act($controller, $method, $input, $tenant, $profile, $args, $files);

        return [$response->getStatusCode(), $response->getData(true)];
    }

    private function alum(array $over = [], int $tenant = 7, string $status = 'active'): object
    {
        static $n = 0;
        $n++;
        $row = $this->svc->create($tenant, array_merge([
            'first_name' => 'Person' . $n, 'last_name' => 'Test', 'dob' => '2003-01-' . str_pad((string) (($n % 27) + 1), 2, '0', STR_PAD_LEFT),
            'email' => "p{$n}@example.org", 'mobile' => '90000' . str_pad((string) $n, 5, '0', STR_PAD_LEFT),
            'city' => 'Pune', 'country' => 'India', 'passing_year' => 2020, 'gender' => $n % 2 ? 'Male' : 'Female',
        ], $over), 1);
        $this->svc->changeStatus($tenant, (int) $row->id, $status, 1);

        return $this->svc->find($tenant, (int) $row->id);
    }

    private function setPrivacy(object $a, array $fields, int $tenant = 7): void
    {
        $this->call2(AlumniDirectoryApiController::class, 'privacySave', ['fields' => $fields], $tenant, 'Admin', [$a->id]);
    }

    // ----------------------------------------------------------------- directory

    public function test_a_private_field_is_blanked_and_cannot_be_used_to_find_the_alumnus(): void
    {
        $open = $this->alum(['first_name' => 'Open', 'city' => 'Pune']);
        $closed = $this->alum(['first_name' => 'Closed', 'city' => 'Pune']);
        $this->setPrivacy($closed, ['location' => 'private']);

        [, $all] = $this->call2(AlumniDirectoryApiController::class, 'directory');
        [, $byCity] = $this->call2(AlumniDirectoryApiController::class, 'directory', ['city' => 'Pune']);

        $byName = collect($all['data']['rows'])->keyBy('full_name');
        $this->assertSame('Pune', $byName['Open Test']['city']);
        $this->assertNull($byName['Closed Test']['city']);                       // blanked
        $this->assertContains('location', $byName['Closed Test']['hidden']);
        $this->assertSame(['Open Test'], array_column($byCity['data']['rows'], 'full_name'));   // and not searchable
    }

    public function test_private_email_is_hidden_even_from_staff_in_the_directory_but_institution_only_is_shown(): void
    {
        $a = $this->alum(['first_name' => 'Mail', 'email' => 'mail@example.org']);
        [, $d1] = $this->call2(AlumniDirectoryApiController::class, 'directory', ['q' => 'Mail']);
        $this->setPrivacy($a, ['email' => 'private']);
        [, $d2] = $this->call2(AlumniDirectoryApiController::class, 'directory', ['q' => 'Mail']);

        $this->assertSame('mail@example.org', $d1['data']['rows'][0]['email']);   // default institution_only: staff may see it
        $this->assertNull($d2['data']['rows'][0]['email']);
        $this->assertContains('email', $d2['data']['rows'][0]['hidden']);
    }

    public function test_what_another_alumnus_may_see_is_narrower_than_what_staff_see(): void
    {
        $privacy = app(AlumniPrivacy::class);
        $vis = $privacy->defaults(7);
        [$asAlumni, $hiddenFromAlumni] = $privacy->mask(['email' => 'x@y.z', 'mobile' => '1', 'city' => 'Pune', 'current_company' => 'TCS'], $vis, 'alumni');
        [$asStaff] = $privacy->mask(['email' => 'x@y.z', 'mobile' => '1', 'city' => 'Pune', 'current_company' => 'TCS'], $vis, 'institution');
        [$asPublic, $hiddenFromPublic] = $privacy->mask(['email' => 'x@y.z', 'city' => 'Pune'], $vis, 'public');

        $this->assertNull($asAlumni['email']);                       // institution_only by default
        $this->assertNull($asAlumni['mobile']);
        $this->assertSame('Pune', $asAlumni['city']);                // alumni_only: other alumni may see it
        $this->assertSame('x@y.z', $asStaff['email']);
        $this->assertContains('location', $hiddenFromPublic);        // the public sees nothing but identity
        $this->assertNull($asPublic['city']);
    }

    public function test_only_verified_and_active_alumni_appear_and_other_institutes_never_do(): void
    {
        $this->alum(['first_name' => 'Shown'], 7, 'verified');
        $this->alum(['first_name' => 'Pending'], 7, 'pending_verification');
        $this->alum(['first_name' => 'Opted'], 7, 'opted_out');
        $this->alum(['first_name' => 'Blocked'], 7, 'blocked');
        $this->alum(['first_name' => 'Elsewhere'], 8, 'active');

        [, $d] = $this->call2(AlumniDirectoryApiController::class, 'directory');

        $this->assertSame(['Shown Test'], array_column($d['data']['rows'], 'full_name'));
    }

    public function test_skill_and_education_filters_respect_the_visibility_of_those_sections(): void
    {
        $a = $this->alum(['first_name' => 'Skilled']);
        $b = $this->alum(['first_name' => 'Secret']);
        foreach ([$a, $b] as $x) {
            DB::table('alumni_skills')->insert(['sub_institute_id' => 7, 'alumni_id' => $x->id, 'skill' => 'Python', 'level' => 'expert', 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->setPrivacy($b, ['skills' => 'private']);

        [, $d] = $this->call2(AlumniDirectoryApiController::class, 'directory', ['skill' => 'python']);

        $this->assertSame(['Skilled Test'], array_column($d['data']['rows'], 'full_name'));
        $this->assertSame('Python', $d['data']['rows'][0]['skills'][0]['skill']);
    }

    public function test_directory_filter_options_do_not_reveal_values_that_only_hidden_alumni_have(): void
    {
        $this->alum(['first_name' => 'Visible', 'city' => 'Pune', 'country' => 'India']);
        $secret = $this->alum(['first_name' => 'Secret', 'city' => 'Reykjavik', 'country' => 'Iceland']);
        $this->alum(['first_name' => 'Pending', 'city' => 'Oslo', 'country' => 'Norway'], 7, 'pending_verification');
        $this->alum(['first_name' => 'Elsewhere', 'city' => 'Lima', 'country' => 'Peru'], 8);
        $this->setPrivacy($secret, ['location' => 'private']);

        [, $o] = $this->call2(AlumniDirectoryApiController::class, 'directoryOptions');

        $this->assertSame(['Pune'], $o['data']['cities']);
        $this->assertSame(['India'], $o['data']['countries']);   // not Iceland (private), Norway (not listed yet), Peru (other institute)
    }

    public function test_directory_validates_paging_and_requires_a_staff_role(): void
    {
        [$bad] = $this->call2(AlumniDirectoryApiController::class, 'directory', ['per_page' => 5000]);
        [$sort] = $this->call2(AlumniDirectoryApiController::class, 'directory', ['sort' => 'password']);
        [$teacher] = $this->call2(AlumniDirectoryApiController::class, 'directory', [], 7, 'Teacher');

        $this->assertSame(422, $bad);
        $this->assertSame(422, $sort);
        $this->assertSame(403, $teacher);
    }

    // -------------------------------------------------------------- privacy editor

    public function test_privacy_editor_saves_levels_consents_and_default_resets(): void
    {
        $a = $this->alum();

        [$unknown] = $this->call2(AlumniDirectoryApiController::class, 'privacySave', ['fields' => ['password' => 'public']], 7, 'Admin', [$a->id]);
        [$badLevel] = $this->call2(AlumniDirectoryApiController::class, 'privacySave', ['fields' => ['email' => 'everyone']], 7, 'Admin', [$a->id]);
        [$ok, $body] = $this->call2(AlumniDirectoryApiController::class, 'privacySave', [
            'fields' => ['mobile' => 'public', 'email' => 'private'], 'consents' => ['consent_email' => true, 'consent_sms' => false],
        ], 7, 'Admin', [$a->id]);
        $levels = array_column($body['data']['fields'], 'level', 'key');
        [, $reset] = $this->call2(AlumniDirectoryApiController::class, 'privacySave', ['fields' => ['mobile' => 'default']], 7, 'Admin', [$a->id]);

        $this->assertSame(422, $unknown);
        $this->assertSame(422, $badLevel);
        $this->assertSame(200, $ok);
        $this->assertSame('public', $levels['mobile']);
        $this->assertSame('private', $levels['email']);
        $this->assertTrue($body['data']['consents']['consent_email']);
        $this->assertSame('institution_only', array_column($reset['data']['fields'], 'level', 'key')['mobile']);
        $this->assertSame(1, DB::table('alumni_privacy')->where('alumni_id', $a->id)->count());   // reset removed the override
    }

    public function test_privacy_of_another_institutes_alumnus_cannot_be_read_or_changed(): void
    {
        $a = $this->alum();
        [$read] = $this->call2(AlumniDirectoryApiController::class, 'privacyShow', [], 8, 'Admin', [$a->id]);
        [$write] = $this->call2(AlumniDirectoryApiController::class, 'privacySave', ['fields' => ['email' => 'public']], 8, 'Admin', [$a->id]);

        $this->assertSame(404, $read);
        $this->assertSame(404, $write);
        $this->assertSame(0, DB::table('alumni_privacy')->count());
    }

    // --------------------------------------------------------------------- batches

    public function test_batch_statistics_are_counted_in_the_database_per_tenant(): void
    {
        $this->alum(['passing_year' => 2020, 'batch' => '2019-20', 'gender' => 'Male', 'country' => 'India', 'employment_status' => 'employed'], 7, 'active');
        $this->alum(['passing_year' => 2020, 'batch' => '2019-20', 'gender' => 'Female', 'country' => 'Canada', 'employment_status' => 'entrepreneur'], 7, 'verified');
        $this->alum(['passing_year' => 2021, 'batch' => '2020-21', 'gender' => 'Female', 'country' => 'India', 'employment_status' => 'job_seeking'], 7, 'registered');
        $this->alum(['passing_year' => 2020, 'batch' => '2019-20'], 8, 'active');   // other institute

        [, $b] = $this->call2(AlumniDirectoryApiController::class, 'batches');
        [, $y] = $this->call2(AlumniDirectoryApiController::class, 'batches', ['group_by' => 'passing_year']);
        [$bad] = $this->call2(AlumniDirectoryApiController::class, 'batches', ['group_by' => 'password; drop table alumni']);

        $row = collect($b['data']['rows'])->firstWhere('batch_key', '2019-20');
        $this->assertSame(2, $row['total']);
        $this->assertSame(1, $row['male']);
        $this->assertSame(1, $row['female']);
        $this->assertSame(1, $row['active']);
        $this->assertSame(1, $row['verified']);
        $this->assertSame(1, $row['abroad']);
        $this->assertSame(1, $row['employed']);
        $this->assertSame(1, $row['entrepreneurs']);
        $this->assertSame(3, $b['data']['totals']['total']);
        $this->assertSame([2021, 2020], array_column($y['data']['rows'], 'batch_key'));
        $this->assertSame(422, $bad);
    }

    // ------------------------------------------------------------------- documents

    private function pdf(string $name = 'degree.pdf', int $kb = 50): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kb, 'application/pdf');
    }

    public function test_document_upload_stores_privately_and_never_reveals_the_path(): void
    {
        $a = $this->alum();
        [$status, $body] = $this->call2(AlumniDocumentApiController::class, 'upload', ['doc_type' => 'graduation_certificate'], 7, 'Admin', [$a->id], ['file' => $this->pdf()]);

        $this->assertSame(201, $status);
        $this->assertArrayNotHasKey('file_path', $body['data']);
        $path = DB::table('alumni_documents')->value('file_path');
        $this->assertStringStartsWith("alumni/7/{$a->id}/", $path);
        $this->assertStringNotContainsString('degree', $path);              // random name, not the client's
        Storage::disk('local')->assertExists($path);
        [, $list] = $this->call2(AlumniDocumentApiController::class, 'index', [], 7, 'Admin', [$a->id]);
        $this->assertArrayNotHasKey('file_path', $list['data']['rows'][0]);
    }

    public function test_document_upload_rejects_dangerous_oversized_and_untyped_files(): void
    {
        $a = $this->alum();
        $php = UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;');
        // A REAL file (UploadedFile::fake() decides its type from the file name, so it cannot test content sniffing):
        // PHP source named .pdf must be refused because the bytes are not a PDF.
        $tmp = tempnam(sys_get_temp_dir(), 'alm');
        file_put_contents($tmp, '<?php echo 1; ?>');
        $spoof = new UploadedFile($tmp, 'photo.pdf', 'application/pdf', null, true);

        [$s1] = $this->call2(AlumniDocumentApiController::class, 'upload', ['doc_type' => 'other'], 7, 'Admin', [$a->id], ['file' => $php]);
        [$s2] = $this->call2(AlumniDocumentApiController::class, 'upload', ['doc_type' => 'other'], 7, 'Admin', [$a->id], ['file' => $this->pdf('big.pdf', 6000)]);
        [$s3] = $this->call2(AlumniDocumentApiController::class, 'upload', ['doc_type' => 'virus'], 7, 'Admin', [$a->id], ['file' => $this->pdf()]);
        [$s4] = $this->call2(AlumniDocumentApiController::class, 'upload', ['doc_type' => 'other'], 7, 'Admin', [$a->id], []);
        [$s5] = $this->call2(AlumniDocumentApiController::class, 'upload', ['doc_type' => 'other'], 7, 'Admin', [$a->id], ['file' => $spoof]);

        $this->assertSame([422, 422, 422, 422, 422], [$s1, $s2, $s3, $s4, $s5]);
        $this->assertSame(0, DB::table('alumni_documents')->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_download_is_tenant_scoped_role_gated_and_returns_the_file(): void
    {
        $a = $this->alum();
        [, $up] = $this->call2(AlumniDocumentApiController::class, 'upload', ['doc_type' => 'id_proof'], 7, 'Admin', [$a->id], ['file' => $this->pdf('proof.pdf')]);
        $docId = $up['data']['id'];

        $ok = $this->act(AlumniDocumentApiController::class, 'download', [], 7, 'Admin', [$a->id, $docId]);
        $otherTenant = $this->act(AlumniDocumentApiController::class, 'download', [], 8, 'Admin', [$a->id, $docId]);
        $teacher = $this->act(AlumniDocumentApiController::class, 'download', [], 7, 'Teacher', [$a->id, $docId]);
        $wrongAlumnus = $this->act(AlumniDocumentApiController::class, 'download', [], 7, 'Admin', [$a->id + 99, $docId]);

        $this->assertSame(200, $ok->getStatusCode());
        $this->assertStringContainsString('proof.pdf', (string) $ok->headers->get('content-disposition'));
        $this->assertSame('nosniff', $ok->headers->get('x-content-type-options'));
        $this->assertSame(404, $otherTenant->getStatusCode());
        $this->assertSame(403, $teacher->getStatusCode());
        $this->assertSame(404, $wrongAlumnus->getStatusCode());
    }

    public function test_document_verification_delete_and_achievement_evidence(): void
    {
        $a = $this->alum();
        $other = $this->alum();
        [, $up] = $this->call2(AlumniDocumentApiController::class, 'upload', ['doc_type' => 'achievement_certificate'], 7, 'Admin', [$a->id], ['file' => $this->pdf()]);
        $docId = $up['data']['id'];
        [, $foreign] = $this->call2(AlumniDocumentApiController::class, 'upload', ['doc_type' => 'other'], 7, 'Admin', [$other->id], ['file' => $this->pdf('o.pdf')]);
        $achId = DB::table('alumni_achievements')->insertGetId(['sub_institute_id' => 7, 'alumni_id' => $a->id, 'category' => 'award', 'title' => 'Prize', 'verification_status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        [$noReason] = $this->call2(AlumniDocumentApiController::class, 'verify', ['decision' => 'rejected'], 7, 'Admin', [$a->id, $docId]);
        [$ok, $v] = $this->call2(AlumniDocumentApiController::class, 'verify', ['decision' => 'verified'], 7, 'Admin', [$a->id, $docId]);
        [$crossTenant] = $this->call2(AlumniDocumentApiController::class, 'verify', ['decision' => 'verified'], 8, 'Admin', [$a->id, $docId]);
        [$foreignDoc] = $this->call2(AlumniDocumentApiController::class, 'verifyAchievement', ['decision' => 'verified', 'document_id' => $foreign['data']['id']], 7, 'Admin', [$a->id, $achId]);
        [$achOk] = $this->call2(AlumniDocumentApiController::class, 'verifyAchievement', ['decision' => 'verified', 'document_id' => $docId], 7, 'Admin', [$a->id, $achId]);
        $path = DB::table('alumni_documents')->where('id', $docId)->value('file_path');
        [$del] = $this->call2(AlumniDocumentApiController::class, 'destroy', [], 7, 'Admin', [$a->id, $docId]);

        $this->assertSame(422, $noReason);
        $this->assertSame('verified', $v['data']['verification_status']);
        $this->assertSame(404, $crossTenant);
        $this->assertSame(422, $foreignDoc);                                  // evidence must be the alumnus's own document
        $this->assertSame(200, $achOk);
        $this->assertSame(200, $del);
        Storage::disk('local')->assertMissing($path);
        $this->assertNull(DB::table('alumni_achievements')->where('id', $achId)->value('document_id'));
    }

    // ---------------------------------------------------- export and bulk actions

    private function csv($response): array
    {
        ob_start();
        $response->sendContent();
        $text = ltrim((string) ob_get_clean(), "\xEF\xBB\xBF");

        return array_map('str_getcsv', array_filter(explode("\n", $text), 'strlen'));
    }

    public function test_csv_export_applies_filters_privacy_and_tenant_scope(): void
    {
        $a = $this->alum(['first_name' => 'Visible', 'city' => 'Pune', 'email' => 'v@example.org']);
        $b = $this->alum(['first_name' => 'Hidden', 'city' => 'Pune', 'email' => 'h@example.org']);
        $this->alum(['first_name' => 'Surat', 'city' => 'Surat']);
        $this->alum(['first_name' => 'Elsewhere', 'city' => 'Pune'], 8);
        $this->setPrivacy($b, ['email' => 'private', 'mobile' => 'private']);

        $res = $this->act(AlumniExportApiController::class, 'export', ['city' => 'Pune', 'format' => 'csv']);
        $rows = $this->csv($res);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertStringContainsString('text/csv', (string) $res->headers->get('content-type'));
        $this->assertStringContainsString('attachment', (string) $res->headers->get('content-disposition'));
        $this->assertSame('Alumni ID', $rows[0][0]);
        $byName = collect(array_slice($rows, 1))->keyBy(1);
        $this->assertCount(2, $byName);                                       // Surat and the other institute are not in it
        $this->assertSame('v@example.org', $byName['Visible Test'][8]);
        $this->assertSame('', $byName['Hidden Test'][8]);                     // private email left out
        $this->assertSame('', $byName['Hidden Test'][7]);                     // private mobile left out
    }

    public function test_export_neutralises_spreadsheet_formulas_but_keeps_phone_numbers(): void
    {
        $this->alum(['first_name' => '=HYPERLINK("http://evil","x")', 'mobile' => '+91 98765 43210', 'email' => 'f@example.org']);
        $this->alum(['first_name' => '@SUM(1+1)', 'last_name' => '-2+3', 'mobile' => '+1+1']);

        $rows = array_slice($this->csv($this->act(AlumniExportApiController::class, 'export', ['format' => 'csv'])), 1);
        $names = array_column($rows, 1);

        $this->assertContains("'=HYPERLINK(\"http://evil\",\"x\") Test", $names);
        $this->assertContains("'@SUM(1+1) -2+3", $names);
        $this->assertContains('+91 98765 43210', array_column($rows, 7));      // a real phone number is left alone
        $this->assertContains("'+1+1", array_column($rows, 7));                // a formula-shaped one is not
    }

    public function test_export_by_ids_cannot_reach_another_institutes_records_and_xlsx_is_supported(): void
    {
        $mine = $this->alum(['first_name' => 'Mine']);
        $theirs = $this->alum(['first_name' => 'Theirs'], 8);

        $rows = $this->csv($this->act(AlumniExportApiController::class, 'export', ['ids' => [$mine->id, $theirs->id], 'format' => 'csv']));
        $xlsx = $this->act(AlumniExportApiController::class, 'export', ['format' => 'xlsx']);
        [$bad] = $this->call2(AlumniExportApiController::class, 'export', ['format' => 'exe']);
        [$teacher] = $this->call2(AlumniExportApiController::class, 'export', [], 7, 'Teacher');

        $this->assertSame(['Mine Test'], array_column(array_slice($rows, 1), 1));
        $this->assertSame(200, $xlsx->getStatusCode());
        $this->assertStringContainsString('.xlsx', (string) $xlsx->headers->get('content-disposition'));
        $this->assertSame(422, $bad);
        $this->assertSame(403, $teacher);
    }

    public function test_bulk_status_changes_only_this_institutes_rows(): void
    {
        $a = $this->alum(['first_name' => 'A'], 7, 'registered');
        $b = $this->alum(['first_name' => 'B'], 7, 'registered');
        $x = $this->alum(['first_name' => 'X'], 8, 'registered');

        [$ok, $body] = $this->call2(AlumniExportApiController::class, 'bulkStatus', ['ids' => [$a->id, $b->id, $x->id], 'status' => 'inactive', 'remarks' => 'cleanup']);
        [$badStatus] = $this->call2(AlumniExportApiController::class, 'bulkStatus', ['ids' => [$a->id], 'status' => 'god']);
        [$none] = $this->call2(AlumniExportApiController::class, 'bulkStatus', ['ids' => [], 'status' => 'active']);
        [$tooMany] = $this->call2(AlumniExportApiController::class, 'bulkStatus', ['ids' => range(1, 501), 'status' => 'active']);
        [$teacher] = $this->call2(AlumniExportApiController::class, 'bulkStatus', ['ids' => [$a->id], 'status' => 'active'], 7, 'Teacher');

        $this->assertSame(200, $ok);
        $this->assertSame(2, $body['data']['updated']);
        $this->assertSame([$x->id], $body['data']['not_found']);
        $this->assertSame('inactive', DB::table('alumni')->where('id', $a->id)->value('status'));
        $this->assertSame('registered', DB::table('alumni')->where('id', $x->id)->value('status'));   // untouched
        $this->assertSame([422, 422, 422, 403], [$badStatus, $none, $tooMany, $teacher]);
    }
}
