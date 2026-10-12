<?php

namespace Tests\Feature;

use App\Http\Controllers\api\alumni\AlumniImportApiController as C;
use App\Services\Alumni\AlumniImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\AlumniTestHelpers;
use Tests\TestCase;

/**
 * The bulk import is the one place that can create hundreds of personal records from a file a person chose, so these tests pin
 * the guarantees: the file is checked by content, a preview writes nothing, an existing alumnus is never overwritten, nobody
 * gets imported consent, a batch can be confirmed once, a spoofed or oversized file is refused, and institutes cannot touch
 * each other's batches. In-memory sqlite (see AlumniTestHelpers).
 */
class AlumniImportTest extends TestCase
{
    use AlumniTestHelpers;

    private const HEAD = "First Name,Last Name,Email,Mobile,DOB,Batch,Passing Year\n";

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootAlumni();
    }

    private function csvFile(string $content, string $name = 'alumni.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    /** A real .xlsx written with PhpSpreadsheet. */
    private function xlsxFile(array $rows, string $name = 'alumni.xlsx'): UploadedFile
    {
        $book = new Spreadsheet();
        $book->getActiveSheet()->fromArray($rows, null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'imp') . '.xlsx';
        (new Xlsx($book))->save($path);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function upload(UploadedFile $file, int $tenant = 7, string $profile = 'Admin'): array
    {
        $r = $this->act(C::class, 'upload', [], $tenant, $profile, [], ['file' => $file]);

        return [$r->getStatusCode(), $r->getData(true)];
    }

    private function hit(string $method, int $id, array $input = [], int $tenant = 7, string $profile = 'Admin'): array
    {
        return $this->go(C::class, $method, $input, $tenant, $profile, [$id]);
    }

    private function standard(): array
    {
        return ['first_name' => 0, 'last_name' => 1, 'email' => 2, 'mobile' => 3, 'dob' => 4, 'batch' => 5, 'passing_year' => 6];
    }

    /** Upload + preview a CSV, return [batchId, previewBatch]. */
    private function previewed(string $csv, ?array $mapping = null, int $tenant = 7): array
    {
        [$s, $b] = $this->upload($this->csvFile($csv), $tenant);
        $this->assertSame(201, $s, json_encode($b));
        $id = (int) $b['data']['batch']['id'];
        [$ps, $pb] = $this->hit('preview', $id, ['mapping' => $mapping ?? $b['data']['suggested']], $tenant);
        $this->assertSame(200, $ps, json_encode($pb));

        return [$id, $pb['data']];
    }

    private function csvOut($response): array
    {
        ob_start();
        $response->sendContent();
        $text = ltrim((string) ob_get_clean(), "\xEF\xBB\xBF");

        return array_map('str_getcsv', array_filter(explode("\n", $text), 'strlen'));
    }

    // ------------------------------------------------------------------------ access

    public function test_only_alumni_managers_can_import(): void
    {
        foreach (['Teacher', 'Accountant', 'Peon'] as $profile) {
            $this->assertSame(403, $this->upload($this->csvFile(self::HEAD . "A,B,a@example.org,9000000001,2000-01-01,2017-18,2018\n"), 7, $profile)[0], $profile);
        }
        $this->assertSame(403, $this->go(C::class, 'meta', [], 7, 'Teacher')[0]);
        $this->assertSame(0, DB::table('alumni_import_batches')->count());
        $this->assertSame(201, $this->upload($this->csvFile(self::HEAD . "A,B,a@example.org,9000000001,2000-01-01,2017-18,2018\n"), 7, 'Alumni Coordinator')[0]);
    }

    // ------------------------------------------------------------------ file checks

    public function test_files_are_checked_by_content_not_by_name_and_size_limits_apply(): void
    {
        $zipAsCsv = $this->csvFile("PK\x03\x04binary", 'x.csv');
        $csvAsXlsx = $this->csvFile(self::HEAD . "A,B,a@example.org,1,2000-01-01,x,2018\n", 'x.xlsx');
        $nul = $this->csvFile("a,b\0c\n1,2\n", 'x.csv');
        $exe = $this->csvFile('MZ not a spreadsheet', 'x.exe');
        $xls = $this->csvFile('whatever', 'old.xls');
        foreach ([$zipAsCsv, $csvAsXlsx, $nul, $exe, $xls] as $f) {
            [$s, $b] = $this->upload($f);
            $this->assertSame(422, $s, $f->getClientOriginalName());
            $this->assertArrayHasKey('file', $b['errors']);
        }
        $this->assertSame(422, $this->upload($this->csvFile(''))[0], 'empty');
        $this->assertSame(422, $this->upload($this->csvFile("First Name,Email\n"))[0], 'headings only');
        $this->assertSame(422, $this->upload($this->csvFile(",,\nA,b\n"))[0], 'no usable headings');

        $big = $this->csvFile(self::HEAD . str_repeat("Abcdefgh,Ijklmnop,someone@example.org,9000000000,2000-01-01,2017-18,2018\n", 90000));
        $this->assertGreaterThan(AlumniImportService::MAX_BYTES, $big->getSize());
        [$bs, $bb] = $this->upload($big);
        $this->assertSame(422, $bs);
        $this->assertStringContainsString('MB', $bb['message']);

        $rows = self::HEAD . implode('', array_map(fn ($i) => "N{$i},L,n{$i}@example.org,\n", range(1, AlumniImportService::MAX_ROWS + 1)));
        [$rs, $rb] = $this->upload($this->csvFile($rows));
        $this->assertSame(422, $rs, 'one row over the limit');
        $this->assertStringContainsString('Split it', $rb['message']);
        $this->assertSame(0, DB::table('alumni_import_batches')->count(), 'a refused file leaves no batch');
        $this->assertSame([], Storage::disk('local')->allFiles('alumni-imports'), 'and no file on disk');
        $this->assertSame(422, $this->act(C::class, 'upload', [], 7, 'Admin')->getStatusCode(), 'no file at all');
    }

    public function test_upload_stores_privately_under_a_random_name_and_suggests_the_mapping(): void
    {
        [, $b] = $this->upload($this->csvFile("Given Name,Surname,E-mail ID,Phone No,Date of Birth,Year of Passing,Weird\nA,B,a@example.org,900,2000-01-01,2018,x\n", '../../evil name.csv'));
        $batch = $b['data']['batch'];
        $this->assertStringStartsWith('alumni-imports/7/', $batch['stored_path']);
        $this->assertDoesNotMatchRegularExpression('/evil|\.\./', $batch['stored_path']);
        Storage::disk('local')->assertExists($batch['stored_path']);
        $this->assertStringNotContainsString('..', $batch['original_name']);
        $this->assertSame(['first_name' => 0, 'last_name' => 1, 'email' => 2, 'mobile' => 3, 'dob' => 4, 'passing_year' => 5], $b['data']['suggested']);
        $this->assertCount(1, $b['data']['sample']);
        $this->assertSame(1, (int) $batch['total_rows']);
    }

    // ------------------------------------------------------------------- the preview

    public function test_preview_validates_every_row_finds_duplicates_and_writes_no_alumni(): void
    {
        $existing = $this->alum('Existing', ['email' => 'taken@example.org', 'mobile' => '7000000099']);
        $named = $this->alum('Same', ['dob' => '1999-05-05']);
        $before = DB::table('alumni')->count();

        $csv = self::HEAD
            . "Good,One,good1@example.org,9000000001,2000-01-31,2017-18,2018\n"                // 2  valid
            . "Good,Two,good2@example.org,9000000002,25/03/2001,2017-18,2018.0\n"             // 3  valid: day-first date, spreadsheet-style year
            . ",NoFirst,x@example.org,9000000003,2000-01-01,2017-18,2018\n"                   // 4  no first name
            . "Bad,Email,not-an-email,9000000004,2000-01-01,2017-18,2018\n"                   // 5  bad email
            . "Bad,Date,bd@example.org,9000000005,31st Jan,2017-18,2018\n"                    // 6  bad date
            . "Future,Dob,fd@example.org,9000000006," . now()->addYear()->format('Y-m-d') . ",2017-18,2018\n"   // 7  born in the future
            . "No,Identifier,,,2000-01-01,2017-18,2018\n"                                     // 8  nothing to detect duplicates with
            . "Evil,Cell,=cmd|' /C calc'!A0,9000000008,2000-01-01,2017-18,2018\n"             // 9  formula
            . "Good,Again,good1@example.org,9000000009,2000-01-01,2017-18,2018\n"             // 10 repeats row 2's email
            . "Dup,Email,TAKEN@example.org,9000000010,2000-01-01,2017-18,2018\n"              // 11 already in the list (email, case-insensitive)
            . "Same,Alum,same2@example.org,,1999-05-05,2017-18,2018\n"                        // 12 already in the list (name + dob; a different email does not hide it)
            . "Year,Wrong,yw@example.org,9000000011,2000-01-01,2017-18,1800\n";               // 13 passing year out of range

        [$id, $batch] = $this->previewed($csv);

        $this->assertSame($before, DB::table('alumni')->count(), 'a preview creates no alumni');
        $this->assertSame('previewed', $batch['status']);
        $this->assertSame(12, (int) $batch['total_rows']);
        $this->assertSame([2, 7, 3], [(int) $batch['valid_count'], (int) $batch['error_count'], (int) $batch['duplicate_count']], 'valid / errors / duplicates add up to the 12 rows');

        $rows = collect($this->hit('rows', $id)[1]['data']['rows'])->keyBy('row_no');
        $this->assertSame('valid', $rows[2]['status']);
        $this->assertSame('valid', $rows[3]['status']);
        $this->assertArrayHasKey('first_name', (array) $rows[4]['errors']);
        $this->assertArrayHasKey('email', (array) $rows[5]['errors']);
        $this->assertArrayHasKey('dob', (array) $rows[6]['errors']);
        $this->assertArrayHasKey('dob', (array) $rows[7]['errors']);
        $this->assertStringContainsString('duplicates can be detected', json_encode($rows[8]['errors']));
        $this->assertStringContainsString('formulas', json_encode($rows[9]['errors']));
        $this->assertSame('duplicate', $rows[10]['status']);
        $this->assertStringContainsString('Repeats row 2', $rows[10]['note']);
        $this->assertSame('duplicate', $rows[11]['status']);
        $this->assertSame($existing->id, (int) $rows[11]['duplicate_of']);
        $this->assertStringContainsString('email', $rows[11]['note']);
        $this->assertSame('duplicate', $rows[12]['status']);
        $this->assertSame($named->id, (int) $rows[12]['duplicate_of']);
        $this->assertArrayHasKey('passing_year', (array) $rows[13]['errors']);

        $errorRows = array_column($this->hit('rows', $id, ['status' => 'error'])[1]['data']['rows'], 'row_no');
        $this->assertSame([4, 5, 6, 7, 8, 9, 13], array_map('intval', $errorRows), 'the status filter returns exactly the failing rows');
        $this->assertSame(422, $this->hit('rows', $id, ['status' => 'weird'])[0]);
    }

    public function test_mapping_is_validated(): void
    {
        [, $b] = $this->upload($this->csvFile(self::HEAD . "A,B,a@example.org,9000000001,2000-01-01,2017-18,2018\n"));
        $id = (int) $b['data']['batch']['id'];

        $this->assertSame(422, $this->hit('preview', $id, ['mapping' => ['email' => 2]])[0], 'a first name (or full name) column is required');
        $this->assertSame(422, $this->hit('preview', $id, ['mapping' => ['first_name' => 0, 'email' => 0]])[0], 'one column cannot feed two fields');
        $this->assertSame(422, $this->hit('preview', $id, ['mapping' => ['first_name' => 99]])[0], 'column out of range');
        $this->assertSame(422, $this->hit('preview', $id, ['mapping' => ['first_name' => 0, 'status' => 1]])[0], 'status cannot be imported');
        $this->assertSame(422, $this->hit('preview', $id, ['mapping' => ['first_name' => 0, 'consent_email' => 1]])[0], 'consent cannot be imported');
        $this->assertSame(422, $this->hit('preview', $id, [])[0], 'a mapping is required');
        $this->assertSame(200, $this->hit('preview', $id, ['mapping' => ['first_name' => 0, 'email' => 2, 'mobile' => '']])[0], 'an empty entry means "do not import this field"');
    }

    public function test_full_name_is_split_and_other_formats_are_read(): void
    {
        // semicolon-delimited, Windows-1252 encoded (what Excel's "CSV" often is), full name only
        $csv = mb_convert_encoding("Name;Email;Date of Birth\nJosé Maria de Souza;jose@example.org;07-08-1999\nCher;cher@example.org;1999-12-31\n", 'Windows-1252', 'UTF-8');
        [$s, $b] = $this->upload($this->csvFile($csv));
        $this->assertSame(['full_name' => 0, 'email' => 1, 'dob' => 2], $b['data']['suggested']);
        $id = (int) $b['data']['batch']['id'];
        $this->assertSame(200, $this->hit('preview', $id, ['mapping' => $b['data']['suggested']])[0]);
        $this->hit('confirm', $id);

        $a = DB::table('alumni')->where('email', 'jose@example.org')->first();
        $this->assertSame(['José', 'Maria de', 'Souza'], [$a->first_name, $a->middle_name, $a->last_name]);
        $this->assertSame('1999-08-07', $a->dob, 'day-first');
        $this->assertSame('Cher', DB::table('alumni')->where('email', 'cher@example.org')->value('first_name'));
        $this->assertNull(DB::table('alumni')->where('email', 'cher@example.org')->value('last_name'));
    }

    public function test_xlsx_files_with_excel_dates_and_numeric_years_import(): void
    {
        $serial = 36556;   // 2000-01-31 as Excel stores it
        $file = $this->xlsxFile([['First Name', 'Last Name', 'Email', 'DOB', 'Passing Year'], ['Xl', 'Sheet', 'xl@example.org', $serial, 2018]]);
        [, $b] = $this->upload($file);
        $id = (int) $b['data']['batch']['id'];
        $this->assertSame(200, $this->hit('preview', $id, ['mapping' => $b['data']['suggested']])[0]);
        [$cs, $cb] = $this->hit('confirm', $id);
        $this->assertSame(200, $cs, json_encode($cb));
        $a = DB::table('alumni')->where('email', 'xl@example.org')->first();
        $this->assertNotNull($a);
        $this->assertSame('2000-01-31', $a->dob);
        $this->assertSame(2018, (int) $a->passing_year);
    }

    // ------------------------------------------------------------------- confirming

    public function test_confirm_creates_only_valid_rows_never_overwrites_and_imports_no_consent(): void
    {
        $existing = $this->alum('Existing', ['email' => 'taken@example.org', 'city' => 'Pune']);
        DB::table('alumni')->where('id', $existing->id)->update(['consent_email' => 1]);
        $csv = self::HEAD
            . "Fresh,One,fresh1@example.org,9000000001,2000-01-31,2017-18,2018\n"
            . "Fresh,Two,fresh2@example.org,9000000002,2001-02-01,2017-18,2018\n"
            . "Bad,Email,nope,9000000003,2000-01-01,2017-18,2018\n"
            . "Dup,Taken,taken@example.org,9000000004,2000-01-01,2017-18,2018\n";
        [$id] = $this->previewed($csv);
        $path = DB::table('alumni_import_batches')->where('id', $id)->value('stored_path');
        Storage::disk('local')->assertExists($path);
        $before = DB::table('alumni')->count();

        [$s, $b] = $this->hit('confirm', $id);
        $this->assertSame(200, $s, json_encode($b));
        $batch = $b['data'];
        $this->assertSame('completed', $batch['status']);
        $this->assertSame([2, 1, 0], [(int) $batch['imported_count'], (int) $batch['skipped_count'], (int) $batch['failed_count']]);
        $this->assertSame($before + 2, DB::table('alumni')->count());

        $fresh = DB::table('alumni')->whereIn('email', ['fresh1@example.org', 'fresh2@example.org'])->get();
        $this->assertCount(2, $fresh);
        foreach ($fresh as $a) {
            $this->assertSame('import', $a->source);
            $this->assertSame('registered', $a->status, 'imported alumni go through verification like everyone else');
            $this->assertSame([0, 0, 0, 0], [(int) $a->consent_email, (int) $a->consent_sms, (int) $a->consent_whatsapp, (int) $a->consent_push], 'a spreadsheet is not consent');
            $this->assertSame(7, (int) $a->sub_institute_id);
            $this->assertStringStartsWith('ALM-', $a->alumni_code);
        }
        $same = DB::table('alumni')->where('id', $existing->id)->first();
        $this->assertSame(['Pune', 1, 'Existing'], [$same->city, (int) $same->consent_email, $same->first_name], 'an existing alumnus is not touched');
        $this->assertSame(0, DB::table('alumni')->where('email', 'nope')->count());

        Storage::disk('local')->assertMissing($path);
        $this->assertNull(DB::table('alumni_import_batches')->where('id', $id)->value('stored_path'), 'the uploaded personal data is gone');
        $this->assertSame(0, DB::table('alumni_import_rows')->where('batch_id', $id)->whereIn('status', ['imported', 'skipped'])->whereNotNull('data')->count(), 'no personal data kept for finished rows');
        $this->assertGreaterThan(0, DB::table('alumni_import_rows')->where('batch_id', $id)->where('status', 'error')->whereNotNull('data')->count(), 'rows that need attention keep their values so they can be fixed');
        $this->assertSame(1, DB::table('platform_audit_log')->where('component', 'alumni.import')->where('action', 'confirm')->count());
        $this->assertSame(2, DB::table('alumni_import_rows')->where('batch_id', $id)->where('status', 'imported')->whereNotNull('alumni_id')->count());
    }

    public function test_a_batch_can_be_confirmed_only_once_and_must_be_previewed_first(): void
    {
        [, $b] = $this->upload($this->csvFile(self::HEAD . "Once,Only,once@example.org,9000000001,2000-01-01,2017-18,2018\n"));
        $id = (int) $b['data']['batch']['id'];
        $this->assertSame(409, $this->hit('confirm', $id)[0], 'cannot confirm before a preview');
        $this->hit('preview', $id, ['mapping' => $b['data']['suggested']]);

        $this->assertSame(200, $this->hit('confirm', $id)[0]);
        $this->assertSame(409, $this->hit('confirm', $id)[0], 'a second confirm (double click) is refused');
        $this->assertSame(1, DB::table('alumni')->where('email', 'once@example.org')->count());
        $this->assertSame(409, $this->hit('preview', $id, ['mapping' => $b['data']['suggested']])[0], 'a finished import cannot be re-previewed');
        $this->assertSame(409, $this->hit('cancel', $id)[0], 'nor cancelled');
        $this->assertSame(404, $this->hit('confirm', 99999)[0]);
    }

    private function bigCsv(int $n): string
    {
        $rows = self::HEAD;
        for ($i = 1; $i <= $n; $i++) {
            $rows .= "Bulk{$i},Person,bulk{$i}@example.invalid,9300" . str_pad((string) $i, 6, '0', STR_PAD_LEFT) . ",2000-01-01,2017-18,2018\n";
        }

        return $rows;
    }

    public function test_preview_uses_a_constant_number_of_queries_however_many_rows(): void
    {
        [, $b] = $this->upload($this->csvFile($this->bigCsv(400)));
        $id = (int) $b['data']['batch']['id'];
        $n = 0;
        DB::listen(function () use (&$n) {
            $n++;
        });
        $this->hit('preview', $id, ['mapping' => $b['data']['suggested']]);

        $this->assertLessThan(40, $n, "preview of 400 rows ran {$n} queries; duplicate checks must be bulk lookups, not one query per row");
        $this->assertSame(400, (int) DB::table('alumni_import_rows')->where('batch_id', $id)->where('status', 'valid')->count());
    }

    public function test_confirm_works_in_resumable_chunks(): void
    {
        [$id] = $this->previewed($this->bigCsv(250));
        $path = DB::table('alumni_import_batches')->where('id', $id)->value('stored_path');
        $before = DB::table('alumni')->count();

        [$s1, $b1] = $this->hit('confirm', $id);
        $this->assertSame(200, $s1);
        $this->assertSame([150, 'importing', 100], [$b1['data']['remaining'], $b1['data']['status'], (int) $b1['data']['imported_count']], 'first request does one chunk of 100');
        $this->assertSame($before + 100, DB::table('alumni')->count());
        Storage::disk('local')->assertExists($path);   // the file is kept until the last chunk
        $this->assertSame(409, $this->hit('preview', $id, ['mapping' => ['first_name' => 0]])[0], 'cannot re-preview a running import');
        $this->assertSame(409, $this->hit('cancel', $id)[0], 'cannot cancel a running import');

        [, $b2] = $this->hit('confirm', $id);   // a "resume", e.g. after the tab was closed
        $this->assertSame([50, 'importing', 200], [$b2['data']['remaining'], $b2['data']['status'], (int) $b2['data']['imported_count']]);
        [, $b3] = $this->hit('confirm', $id);
        $this->assertSame([0, 'completed', 250], [$b3['data']['remaining'], $b3['data']['status'], (int) $b3['data']['imported_count']]);
        $this->assertSame($before + 250, DB::table('alumni')->count(), 'every row exactly once');
        $this->assertSame(250, DB::table('alumni')->where('source', 'import')->distinct()->count('email'));
        Storage::disk('local')->assertMissing($path);
        $this->assertSame(1, DB::table('platform_audit_log')->where('component', 'alumni.import')->where('action', 'confirm')->count(), 'audited once, at the end');
        $this->assertSame(409, $this->hit('confirm', $id)[0]);
    }

    public function test_a_row_left_working_by_a_dead_request_is_recovered_but_a_live_one_is_not_stolen(): void
    {
        [$id] = $this->previewed(self::HEAD . "A,One,a1@example.invalid,9400000001,2000-01-01,2017-18,2018\nB,Two,b2@example.invalid,9400000002,2000-01-01,2017-18,2018\n");
        DB::table('alumni_import_rows')->where('batch_id', $id)->where('row_no', 2)->update(['status' => 'working', 'updated_at' => now()->subMinutes(30)]);   // its request died
        DB::table('alumni_import_rows')->where('batch_id', $id)->where('row_no', 3)->update(['status' => 'working', 'updated_at' => now()]);                    // still being imported elsewhere

        [, $b] = $this->hit('confirm', $id);
        $this->assertSame(1, $b['data']['remaining'], 'the live row is left alone and still counts as outstanding');
        $this->assertSame(1, DB::table('alumni')->where('email', 'a1@example.invalid')->count(), 'the abandoned row was recovered');
        $this->assertSame(0, DB::table('alumni')->where('email', 'b2@example.invalid')->count(), 'the live row was not taken');
        $this->assertSame('importing', $b['data']['status']);
    }

    public function test_purge_finishes_an_import_nobody_completed(): void
    {
        [$id] = $this->previewed($this->bigCsv(150));
        $this->hit('confirm', $id);   // 100 of 150, then the user walks away
        DB::table('alumni_import_batches')->where('id', $id)->update(['updated_at' => now()->subDays(3)]);

        app(AlumniImportService::class)->purge();

        $batch = DB::table('alumni_import_batches')->where('id', $id)->first();
        $this->assertSame('completed', $batch->status);
        $this->assertNull($batch->stored_path);
        $this->assertSame(100, DB::table('alumni')->where('source', 'import')->count(), 'nothing is imported behind the user\'s back');
        $this->assertSame(50, DB::table('alumni_import_rows')->where('batch_id', $id)->where('status', 'skipped')->where('note', 'The import was not finished.')->count());
        $this->assertSame(50, (int) $batch->skipped_count);
    }

    public function test_someone_added_between_preview_and_confirm_is_skipped_not_duplicated(): void
    {
        [$id] = $this->previewed(self::HEAD . "Late,Joiner,late@example.org,9000000001,2000-01-01,2017-18,2018\nOther,Person,other@example.org,9000000002,2000-01-01,2017-18,2018\n");
        $late = $this->alum('Late', ['email' => 'late@example.org']);   // created by someone else after the preview

        [, $b] = $this->hit('confirm', $id);
        $this->assertSame([1, 1], [(int) $b['data']['imported_count'], (int) $b['data']['skipped_count']]);
        $this->assertSame(1, DB::table('alumni')->where('email', 'late@example.org')->count());
        $note = DB::table('alumni_import_rows')->where('batch_id', $id)->where('row_no', 2)->first();
        $this->assertSame('skipped', $note->status);
        $this->assertSame($late->id, (int) $note->duplicate_of);
    }

    public function test_cancel_discards_the_batch_and_the_file(): void
    {
        [$id] = $this->previewed(self::HEAD . "Cancel,Me,cancel@example.org,9000000001,2000-01-01,2017-18,2018\n");
        $path = DB::table('alumni_import_batches')->where('id', $id)->value('stored_path');

        $this->assertSame(200, $this->hit('cancel', $id)[0]);
        Storage::disk('local')->assertMissing($path);
        $this->assertSame(0, DB::table('alumni_import_rows')->where('batch_id', $id)->count());
        $this->assertSame(409, $this->hit('confirm', $id)[0]);
        $this->assertSame(0, DB::table('alumni')->where('email', 'cancel@example.org')->count());
    }

    // ------------------------------------------------------------ downloads, tenancy

    public function test_problems_file_lists_only_rows_needing_attention_and_is_formula_safe(): void
    {
        [$id] = $this->previewed(self::HEAD
            . "Fine,Row,fine@example.org,9000000001,2000-01-01,2017-18,2018\n"
            . "=SUM(1+1),Evil,evil@example.org,9000000002,2000-01-01,2017-18,2018\n"
            . "Bad,Email,wrong,9000000003,2000-01-01,2017-18,2018\n");

        $rows = $this->csvOut($this->act(C::class, 'problems', [], 7, 'Admin', [$id]));
        $this->assertSame(['Row in your file', 'Outcome', 'Problem'], array_slice($rows[0], 0, 3));
        $this->assertCount(3, $rows, 'header + the two problem rows; the valid row is not listed');
        $this->assertSame(['3', '4'], array_column(array_slice($rows, 1), 0));
        $this->assertStringContainsString('formula', $rows[1][2]);
        $this->assertStringNotContainsString('Fine', json_encode($rows));

        $tpl = $this->csvOut($this->act(C::class, 'template', [], 7, 'Admin'));
        $this->assertSame('First name', $tpl[0][0]);
        $this->assertStringContainsString('example.invalid', json_encode($tpl[1]), 'the template row is obviously fake');
        $this->assertSame(403, $this->go(C::class, 'template', [], 7, 'Teacher')[0]);
    }

    public function test_batches_are_invisible_to_other_institutes(): void
    {
        [$id] = $this->previewed(self::HEAD . "Mine,Only,mine@example.org,9000000001,2000-01-01,2017-18,2018\n");

        foreach (['show', 'rows', 'confirm', 'cancel'] as $m) {
            $this->assertSame(404, $this->hit($m, $id, [], 8)[0], $m);
        }
        $this->assertSame(404, $this->hit('preview', $id, ['mapping' => ['first_name' => 0]], 8)[0]);
        $this->assertSame(404, $this->act(C::class, 'problems', [], 8, 'Admin', [$id])->getStatusCode());
        $this->assertSame([], $this->go(C::class, 'index', [], 8)[1]['data']);
        $this->assertSame('previewed', DB::table('alumni_import_batches')->where('id', $id)->value('status'), 'untouched');
        $this->assertSame(0, DB::table('alumni')->where('email', 'mine@example.org')->count());
    }

    // ------------------------------------------------------------------------ purge

    public function test_purge_forgets_abandoned_uploads_and_old_row_data(): void
    {
        [$old] = $this->previewed(self::HEAD . "Old,Upload,old@example.org,9000000001,2000-01-01,2017-18,2018\n");
        [$fresh] = $this->previewed(self::HEAD . "New,Upload,new@example.org,9000000002,2000-01-01,2017-18,2018\n");
        $oldPath = DB::table('alumni_import_batches')->where('id', $old)->value('stored_path');
        $freshPath = DB::table('alumni_import_batches')->where('id', $fresh)->value('stored_path');
        DB::table('alumni_import_batches')->where('id', $old)->update(['updated_at' => now()->subDays(3)]);

        [$done] = $this->previewed(self::HEAD . "Done,Row,done@example.org,9000000003,2000-01-01,2017-18,2018\nBad,One,bad,9000000004,2000-01-01,2017-18,2018\n");
        $this->hit('confirm', $done);
        DB::table('alumni_import_rows')->where('batch_id', $done)->update(['updated_at' => now()->subDays(15)]);

        $r = app(AlumniImportService::class)->purge();
        $this->assertSame(1, $r['abandoned_uploads']);
        $this->assertGreaterThanOrEqual(1, $r['rows_cleared']);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($freshPath);
        $this->assertSame('cancelled', DB::table('alumni_import_batches')->where('id', $old)->value('status'));
        $this->assertSame('previewed', DB::table('alumni_import_batches')->where('id', $fresh)->value('status'));
        $this->assertSame(0, DB::table('alumni_import_rows')->where('batch_id', $done)->whereNotNull('data')->count());
    }
}
