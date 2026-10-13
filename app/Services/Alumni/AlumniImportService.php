<?php

namespace App\Services\Alumni;

use App\Imports\AlumniSheetImport;
use App\Services\Platform\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Bulk import of alumni from a CSV or XLSX file.
 *
 *   store()    keeps the file privately, reads the header and a few sample rows, suggests a column mapping
 *   preview()  applies the mapping to EVERY row: validates it, finds duplicates in the file and in the database - writes nothing
 *   confirm()  creates the alumni for rows that are still valid, one transaction per row, and reports exactly what happened
 *   cancel()   throws the batch away
 *
 * RULES (each one is a decision the product owner can change; see the plan):
 *   - Existing alumni are NEVER overwritten. A row that matches an existing alumnus (student id, GR no, enrollment no, email,
 *     mobile, or name + date of birth) is reported as a duplicate and skipped.
 *   - No consent is imported. A spreadsheet is not evidence that someone agreed to be contacted, so imported alumni start with
 *     every channel off and cannot be messaged until consent is recorded.
 *   - Status is never imported: everyone starts as `registered` and goes through verification like any other record.
 *   - A row needs a first name and at least one identifier (email, mobile, enrollment no, GR no or student id), otherwise
 *     duplicates could not be detected.
 *   - A cell that starts with = or @ looks like a spreadsheet formula and is refused rather than stored.
 *   - At most MAX_ROWS rows and MAX_BYTES per file, so one request can finish in time.
 *
 * The uploaded file is deleted when the batch is confirmed or cancelled. Everything is scoped to the institute.
 */
class AlumniImportService
{
    public const MAX_ROWS = 2000;

    public const MAX_BYTES = 5 * 1024 * 1024;

    public const MAX_COLUMNS = 60;

    /** key => [label, required, header synonyms (compared after lower-casing and dropping non-alphanumerics)] */
    public const FIELDS = [
        'first_name' => ['First name', true, ['firstname', 'first', 'givenname', 'fname']],
        'middle_name' => ['Middle name', false, ['middlename', 'middle']],
        'last_name' => ['Last name', false, ['lastname', 'surname', 'familyname', 'lname']],
        'full_name' => ['Full name (split into first and last)', false, ['fullname', 'name', 'studentname', 'alumniname']],
        'email' => ['Email', false, ['email', 'emailid', 'emailaddress', 'mail']],
        'mobile' => ['Mobile', false, ['mobile', 'mobileno', 'mobilenumber', 'phone', 'phoneno', 'contactno', 'contactnumber', 'cell']],
        'alt_mobile' => ['Alternate mobile', false, ['altmobile', 'alternatemobile', 'alternatephone', 'mobile2']],
        'gender' => ['Gender', false, ['gender', 'sex']],
        'dob' => ['Date of birth', false, ['dob', 'dateofbirth', 'birthdate', 'birthday']],
        'blood_group' => ['Blood group', false, ['bloodgroup', 'bloodtype']],
        'batch' => ['Batch', false, ['batch', 'batchyear', 'cohort']],
        'passing_year' => ['Passing year', false, ['passingyear', 'yearofpassing', 'graduationyear', 'passoutyear', 'yop']],
        'admission_year' => ['Admission year', false, ['admissionyear', 'yearofadmission', 'joiningyear']],
        'leaving_date' => ['Leaving date', false, ['leavingdate', 'dateofleaving']],
        'course' => ['Course', false, ['course', 'program', 'programme', 'stream']],
        'department' => ['Department', false, ['department', 'dept']],
        'campus' => ['Campus', false, ['campus', 'branch']],
        'last_class' => ['Last class', false, ['lastclass', 'class', 'standard', 'lastclassattended']],
        'division' => ['Division', false, ['division', 'section']],
        'academic_year' => ['Academic year', false, ['academicyear', 'session']],
        'enrollment_no' => ['Enrollment no.', false, ['enrollmentno', 'enrolmentno', 'enrollmentnumber', 'enrollmentid', 'rollno']],
        'gr_no' => ['GR no.', false, ['grno', 'grnumber', 'generalregisterno', 'admissionno', 'admissionnumber']],
        'student_id' => ['Student ID (system)', false, ['studentid']],
        'city' => ['City', false, ['city', 'town']],
        'state' => ['State', false, ['state', 'province']],
        'country' => ['Country', false, ['country']],
        'pincode' => ['Pincode', false, ['pincode', 'pin', 'zip', 'zipcode', 'postalcode']],
        'current_address' => ['Current address', false, ['currentaddress', 'address']],
        'permanent_address' => ['Permanent address', false, ['permanentaddress']],
        'employment_status' => ['Employment status', false, ['employmentstatus', 'occupation', 'workingstatus']],
        'linkedin_url' => ['LinkedIn', false, ['linkedin', 'linkedinurl', 'linkedinprofile']],
    ];

    private const DATE_FIELDS = ['dob', 'leaving_date'];

    private const IDENTIFIERS = ['email', 'mobile', 'enrollment_no', 'gr_no', 'student_id'];

    public function __construct(private AlumniService $alumni)
    {
    }

    public function find(int $t, int $id): ?object
    {
        return DB::table('alumni_import_batches')->where('sub_institute_id', $t)->where('id', $id)->first();
    }

    // ------------------------------------------------------------------ upload

    /**
     * @return array{batch: object, headers: array<int, string>, sample: array<int, array<int, string>>, suggested: array<string, int>, previously_imported_at: ?string}
     */
    public function store(int $t, UploadedFile $file, ?int $userId): array
    {
        $type = $this->detectType($file);
        $hash = hash_file('sha256', $file->getRealPath());
        $rows = $this->read($file->getRealPath(), $type);
        if (count($rows) < 2) {
            throw new AlumniRuleException('The file has no data rows. The first row must be the column headings, followed by one row per alumnus.', 422, 'file');
        }
        $headers = array_map(fn ($h) => trim((string) $h), array_shift($rows));
        if (count($headers) > self::MAX_COLUMNS) {
            throw new AlumniRuleException('The file has more than ' . self::MAX_COLUMNS . ' columns.', 422, 'file');
        }
        if (count($rows) > self::MAX_ROWS) {
            throw new AlumniRuleException('The file has ' . number_format(count($rows)) . ' rows; the limit is ' . number_format(self::MAX_ROWS) . ' per import. Split it into smaller files.', 422, 'file');
        }
        if (count(array_filter($headers, 'strlen')) === 0) {
            throw new AlumniRuleException('The first row has no column headings.', 422, 'file');
        }

        $path = $file->storeAs('alumni-imports/' . $t, bin2hex(random_bytes(16)) . '.' . $type, 'local');   // random name: the user's file name never reaches the disk
        $id = (int) DB::table('alumni_import_batches')->insertGetId([
            'sub_institute_id' => $t, 'original_name' => mb_substr($this->safeName($file->getClientOriginalName()), 0, 255), 'stored_path' => $path, 'file_hash' => $hash,
            'file_type' => $type, 'status' => 'uploaded', 'total_rows' => count($rows), 'headers' => json_encode($headers), 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $previous = DB::table('alumni_import_batches')->where('sub_institute_id', $t)->where('file_hash', $hash)->where('status', 'completed')->orderByDesc('id')->value('confirmed_at');

        return ['batch' => $this->find($t, $id), 'headers' => $headers, 'sample' => array_map(fn ($r) => array_map('strval', array_pad($r, count($headers), '')), array_slice($rows, 0, 5)),
            'suggested' => $this->suggest($headers), 'previously_imported_at' => $previous];
    }

    /** Column index for each field whose heading looks right. A heading is claimed by at most one field. */
    public function suggest(array $headers): array
    {
        $norm = array_map(fn ($h) => preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $h)), $headers);
        $taken = [];
        $out = [];
        foreach (self::FIELDS as $key => [, , $synonyms]) {
            foreach ($norm as $i => $n) {
                if ($n !== '' && ! isset($taken[$i]) && in_array($n, $synonyms, true)) {
                    $out[$key] = $i;
                    $taken[$i] = true;

                    break;
                }
            }
        }
        if (isset($out['first_name'])) {
            unset($out['full_name']);   // a separate first-name column wins over splitting a full name
        }

        return $out;
    }

    // ----------------------------------------------------------------- preview

    /**
     * Validate every row under a mapping. Writes only import rows, never alumni.
     *
     * @param  array<string, int>  $mapping  field => column index
     * @return object the batch, with fresh counts
     */
    public function preview(int $t, int $batchId, array $mapping): object
    {
        $batch = $this->find($t, $batchId);
        if (! $batch) {
            throw new AlumniRuleException('Import not found.', 404);
        }
        if (! in_array($batch->status, ['uploaded', 'previewed'], true) || $batch->stored_path === null) {
            throw new AlumniRuleException('This import can no longer be changed.', 409);
        }
        $headers = (array) json_decode((string) $batch->headers, true);
        $mapping = $this->checkMapping($mapping, count($headers));

        $rows = $this->read(Storage::disk('local')->path($batch->stored_path), $batch->file_type);
        array_shift($rows);

        // pass 1: normalise and validate every row (no database); pass 2: duplicates, using a few bulk lookups instead of a query per row
        $parsed = [];
        foreach ($rows as $i => $raw) {
            [$data, $errors] = $this->normalise($mapping, $raw);
            if ($errors === []) {
                $errors = $this->validate($data);
            }
            $parsed[$i] = [$data, $errors];
        }
        $existing = $this->existingLookup($t, array_column(array_filter($parsed, fn ($p) => $p[1] === []), 0));

        $seen = [];   // identifier => first row_no, for duplicates inside the file
        $stored = [];
        $counts = ['valid' => 0, 'error' => 0, 'duplicate' => 0];
        foreach ($parsed as $i => [$data, $errors]) {
            $rowNo = $i + 2;   // the header is row 1
            $status = 'valid';
            $dupOf = null;
            $note = null;
            if ($errors !== []) {
                $status = 'error';
            } else {
                foreach ($this->keys($data) as $k) {
                    if (isset($seen[$k])) {
                        $status = 'duplicate';
                        $note = 'Repeats row ' . $seen[$k] . ' of this file.';

                        break;
                    }
                }
                if ($status === 'valid') {
                    $match = $this->matchExisting($data, $existing);
                    if ($match !== null) {
                        $status = 'duplicate';
                        $dupOf = $match['id'];
                        $note = 'Already in the alumni list as ' . $match['alumni_code'] . ' (' . $match['full_name'] . '), matched on ' . implode(', ', array_map(fn ($m) => str_replace('_', ' ', $m), $match['matched_on'])) . '.';
                    }
                }
                foreach ($this->keys($data) as $k) {
                    $seen[$k] ??= $rowNo;
                }
            }
            $counts[$status]++;
            $stored[] = ['sub_institute_id' => $t, 'batch_id' => $batchId, 'row_no' => $rowNo, 'status' => $status, 'data' => json_encode($data), 'errors' => $errors ? json_encode($errors) : null,
                'duplicate_of' => $dupOf, 'note' => $note, 'created_at' => now(), 'updated_at' => now()];
        }

        DB::transaction(function () use ($batchId, $t, $stored, $counts, $mapping) {
            DB::table('alumni_import_rows')->where('batch_id', $batchId)->where('sub_institute_id', $t)->delete();
            foreach (array_chunk($stored, 250) as $chunk) {
                DB::table('alumni_import_rows')->insert($chunk);
            }
            DB::table('alumni_import_batches')->where('id', $batchId)->update([
                'status' => 'previewed', 'mapping' => json_encode($mapping), 'valid_count' => $counts['valid'], 'error_count' => $counts['error'], 'duplicate_count' => $counts['duplicate'],
                'updated_at' => now(),
            ]);
        });

        return $this->find($t, $batchId);
    }

    private const DUP_FIELDS = ['student_id', 'gr_no', 'enrollment_no', 'email', 'mobile'];

    /**
     * Existing alumni that could match any of these rows, found with a handful of IN queries (500 values each) rather than one
     * query per row. Approximate by design: confirm() re-checks every row with AlumniService::create, which is authoritative,
     * so a case or collation difference here can only make the preview miss a duplicate that confirm then skips.
     *
     * @param  array<int, array<string, mixed>>  $datas
     * @return array{fields: array<string, array<string, array{id:int, alumni_code:string, full_name:string}>>, name_dob: array<string, array{id:int, alumni_code:string, full_name:string}>}
     */
    private function existingLookup(int $t, array $datas): array
    {
        $out = ['fields' => [], 'name_dob' => []];
        foreach (self::DUP_FIELDS as $f) {
            $values = array_values(array_unique(array_filter(array_map(fn ($d) => isset($d[$f]) && $d[$f] !== '' ? (string) $d[$f] : null, $datas))));
            foreach (array_chunk($values, 500) as $chunk) {
                foreach (DB::table('alumni')->where('sub_institute_id', $t)->whereIn($f, $chunk)->orderBy('id')->get(['id', 'alumni_code', 'full_name', $f]) as $r) {
                    $key = mb_strtolower((string) $r->{$f});
                    $out['fields'][$f][$key] ??= ['id' => (int) $r->id, 'alumni_code' => $r->alumni_code, 'full_name' => $r->full_name];   // lowest id wins
                }
            }
        }
        $dobs = array_values(array_unique(array_filter(array_map(fn ($d) => ! empty($d['dob']) ? (string) $d['dob'] : null, $datas))));
        foreach (array_chunk($dobs, 500) as $chunk) {
            foreach (DB::table('alumni')->where('sub_institute_id', $t)->whereIn('dob', $chunk)->orderBy('id')->get(['id', 'alumni_code', 'full_name', 'dob']) as $r) {
                $out['name_dob'][mb_strtolower((string) $r->full_name) . '|' . $r->dob] ??= ['id' => (int) $r->id, 'alumni_code' => $r->alumni_code, 'full_name' => $r->full_name];
            }
        }

        return $out;
    }

    /** The existing alumnus a row matches (lowest id) and the fields it matched on, from existingLookup(); null when none. */
    private function matchExisting(array $data, array $existing): ?array
    {
        $byId = [];
        foreach (self::DUP_FIELDS as $f) {
            if (isset($data[$f]) && $data[$f] !== '' && isset($existing['fields'][$f][mb_strtolower((string) $data[$f])])) {
                $hit = $existing['fields'][$f][mb_strtolower((string) $data[$f])];
                $byId[$hit['id']] ??= $hit + ['matched_on' => []];
                $byId[$hit['id']]['matched_on'][] = $f;
            }
        }
        if (! empty($data['dob']) && ! empty($data['first_name'])) {
            $hit = $existing['name_dob'][mb_strtolower($this->alumni->fullName($data)) . '|' . $data['dob']] ?? null;
            if ($hit) {
                $byId[$hit['id']] ??= $hit + ['matched_on' => []];
                $byId[$hit['id']]['matched_on'][] = 'name_dob';
            }
        }
        if ($byId === []) {
            return null;
        }
        ksort($byId);

        return $byId[array_key_first($byId)];
    }

    // ----------------------------------------------------------------- confirm

    /** Rows imported per request. Each row costs ~16 queries (duplicate re-check, code, insert, completion, audit), so one request stays well inside the time limit. */
    public const CONFIRM_CHUNK = 100;

    /**
     * Import the next CONFIRM_CHUNK valid rows. Call it again until `remaining` is 0: the batch is `importing` in between, so a
     * closed tab or a crash is resumed rather than lost, and no single request has to import thousands of rows.
     *
     * Safe to call twice at once: the batch flips previewed -> importing in one atomic UPDATE, each row is claimed with its own
     * conditional UPDATE before it is created, and the final "completed" flip happens exactly once.
     *
     * @return object the batch, with `remaining` (rows still to do)
     */
    public function confirm(int $t, int $batchId, ?int $userId, ?Request $request = null, int $limit = self::CONFIRM_CHUNK): object
    {
        $batch = $this->find($t, $batchId);
        if (! $batch) {
            throw new AlumniRuleException('Import not found.', 404);
        }
        if ($batch->status === 'previewed') {
            DB::table('alumni_import_batches')->where('id', $batchId)->where('sub_institute_id', $t)->where('status', 'previewed')
                ->update(['status' => 'importing', 'confirmed_by' => $userId, 'confirmed_at' => now(), 'updated_at' => now()]);   // losing this race is fine: we simply continue
        } elseif ($batch->status !== 'importing') {
            throw new AlumniRuleException($batch->status === 'completed' ? 'This import has already been confirmed.' : 'Preview the import before confirming it.', 409);
        }

        // a row stuck in `working` for 10 minutes belonged to a request that died; it is picked up again
        $todo = fn () => DB::table('alumni_import_rows')->where('batch_id', $batchId)->where('sub_institute_id', $t)
            ->where(fn ($w) => $w->where('status', 'valid')->orWhere(fn ($x) => $x->where('status', 'working')->where('updated_at', '<', now()->subMinutes(10))));
        $imported = $skipped = $failed = 0;
        foreach ($todo()->orderBy('row_no')->limit(max(1, $limit))->get() as $row) {
            $claimed = DB::table('alumni_import_rows')->where('id', $row->id)->where('status', $row->status)->where('updated_at', $row->updated_at)->update(['status' => 'working', 'updated_at' => now()]);
            if ($claimed !== 1) {
                continue;   // another request took it
            }
            $data = (array) json_decode((string) $row->data, true);
            try {
                $alumnus = $this->alumni->create($t, $data, $userId, 'import', false, $request);
                DB::table('alumni_import_rows')->where('id', $row->id)->update(['status' => 'imported', 'alumni_id' => $alumnus->id, 'data' => null, 'updated_at' => now()]);
                $imported++;
            } catch (DuplicateAlumniException $e) {   // someone added the same person after the preview
                $first = $e->matches[0];
                DB::table('alumni_import_rows')->where('id', $row->id)->update(['status' => 'skipped', 'duplicate_of' => $first['id'], 'note' => 'Added to the alumni list since the preview: ' . $first['alumni_code'] . '.', 'updated_at' => now()]);
                $skipped++;
            } catch (\Throwable $e) {
                report($e);
                DB::table('alumni_import_rows')->where('id', $row->id)->update(['status' => 'failed', 'note' => 'This row could not be saved. Nothing was created for it.', 'updated_at' => now()]);
                $failed++;
            }
        }
        DB::table('alumni_import_batches')->where('id', $batchId)->update([
            'imported_count' => DB::raw('imported_count + ' . $imported), 'skipped_count' => DB::raw('skipped_count + ' . $skipped), 'failed_count' => DB::raw('failed_count + ' . $failed), 'updated_at' => now(),
        ]);

        $remaining = (int) DB::table('alumni_import_rows')->where('batch_id', $batchId)->where('sub_institute_id', $t)->whereIn('status', ['valid', 'working'])->count();
        if ($remaining === 0) {
            $this->finish($t, $batchId, $request);
        }
        $out = $this->find($t, $batchId);
        $out->remaining = $remaining;

        return $out;
    }

    /** The one-time end of an import: completed, duplicates counted as skipped, file and finished-row data dropped, audited. */
    private function finish(int $t, int $batchId, ?Request $request): void
    {
        $done = DB::table('alumni_import_batches')->where('id', $batchId)->where('sub_institute_id', $t)->where('status', 'importing')->update(['status' => 'completed', 'updated_at' => now()]);
        if ($done !== 1) {
            return;   // a parallel request already finished it
        }
        DB::table('alumni_import_batches')->where('id', $batchId)->update(['skipped_count' => DB::raw('skipped_count + duplicate_count')]);   // duplicates found at preview are skipped by design
        $batch = $this->find($t, $batchId);
        $this->dropFile($batch);
        DB::table('alumni_import_rows')->where('batch_id', $batchId)->whereIn('status', ['imported', 'skipped'])->update(['data' => null]);   // keep personal data only for rows that need attention
        AuditTrail::record($t, 'alumni', 'alumni.import', 'confirm', ['entity_type' => 'alumni_import_batch', 'entity_id' => $batchId,
            'after' => ['file' => $batch->original_name, 'rows' => (int) $batch->total_rows, 'imported' => (int) $batch->imported_count, 'skipped' => (int) $batch->skipped_count, 'errors' => (int) $batch->error_count, 'failed' => (int) $batch->failed_count]], $request);
    }

    public function cancel(int $t, int $batchId, ?Request $request = null): object
    {
        $batch = $this->find($t, $batchId);
        if (! $batch) {
            throw new AlumniRuleException('Import not found.', 404);
        }
        if (! in_array($batch->status, ['uploaded', 'previewed'], true)) {
            throw new AlumniRuleException('Only an import that has not been confirmed can be cancelled.', 409);
        }
        DB::table('alumni_import_batches')->where('id', $batchId)->update(['status' => 'cancelled', 'updated_at' => now()]);
        $this->dropFile($batch);
        DB::table('alumni_import_rows')->where('batch_id', $batchId)->delete();
        AuditTrail::record($t, 'alumni', 'alumni.import', 'cancel', ['entity_type' => 'alumni_import_batch', 'entity_id' => $batchId, 'after' => ['file' => $batch->original_name]], $request);

        return $this->find($t, $batchId);
    }

    /** Scheduled clean-up: forget abandoned uploads (2 days) and the personal data of old rows (14 days). Returns what it did. */
    public function purge(): array
    {
        $files = 0;
        foreach (DB::table('alumni_import_batches')->whereIn('status', ['uploaded', 'previewed'])->where('updated_at', '<', now()->subDays(2))->get() as $b) {
            DB::table('alumni_import_batches')->where('id', $b->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
            DB::table('alumni_import_rows')->where('batch_id', $b->id)->delete();
            $this->dropFile($b);
            $files++;
        }
        foreach (DB::table('alumni_import_batches')->where('status', 'importing')->where('updated_at', '<', now()->subDays(2))->get() as $b) {   // an import nobody finished: what is left is skipped, not silently imported later
            $left = DB::table('alumni_import_rows')->where('batch_id', $b->id)->whereIn('status', ['valid', 'working'])->update(['status' => 'skipped', 'note' => 'The import was not finished.', 'updated_at' => now()]);
            DB::table('alumni_import_batches')->where('id', $b->id)->update(['skipped_count' => DB::raw('skipped_count + ' . (int) $left)]);
            $this->finish((int) $b->sub_institute_id, (int) $b->id, null);
        }
        $cleared = DB::table('alumni_import_rows')->whereNotNull('data')->where('updated_at', '<', now()->subDays(14))->update(['data' => null]);

        return ['abandoned_uploads' => $files, 'rows_cleared' => $cleared];
    }

    /** Sample file: the importable headings and one clearly fake example row. */
    public static function template(): array
    {
        $keys = ['first_name', 'last_name', 'email', 'mobile', 'dob', 'batch', 'passing_year', 'course', 'city', 'country', 'enrollment_no'];

        return [array_map(fn ($k) => self::FIELDS[$k][0], $keys), ['Asha', 'Example', 'asha.example@example.invalid', '9000000000', '2000-01-31', '2017-18', '2018', 'Science', 'Pune', 'India', 'EN-0001']];
    }

    // ----------------------------------------------------------------- helpers

    private function dropFile(object $batch): void
    {
        if ($batch->stored_path) {
            Storage::disk('local')->delete($batch->stored_path);
            DB::table('alumni_import_batches')->where('id', $batch->id)->update(['stored_path' => null]);
        }
    }

    private function safeName(string $name): string
    {
        return trim(preg_replace('/[^\p{L}\p{N} ._()\-]/u', '_', basename($name))) ?: 'upload';
    }

    /** The real type, from the content - never the extension or the client's mime type alone. */
    private function detectType(UploadedFile $file): string
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw new AlumniRuleException('The file is larger than ' . (self::MAX_BYTES / 1048576) . ' MB.', 422, 'file');
        }
        $ext = strtolower($file->getClientOriginalExtension());
        $head = (string) file_get_contents($file->getRealPath(), false, null, 0, 4096);
        $isZip = str_starts_with($head, "PK\x03\x04");
        if ($ext === 'xlsx' && $isZip) {
            return 'xlsx';
        }
        if ($ext === 'csv' && ! $isZip && ! str_contains($head, "\0")) {
            return 'csv';
        }
        throw new AlumniRuleException('Upload a .csv or .xlsx file. Older .xls files and other formats are not supported.', 422, 'file');
    }

    /** @return array<int, array<int, string>> rows of cell text; completely empty rows are dropped */
    private function read(string $path, string $type): array
    {
        $rows = $type === 'xlsx' ? $this->readXlsx($path) : $this->readCsv($path);

        return array_values(array_filter($rows, fn ($r) => count(array_filter($r, fn ($c) => trim((string) $c) !== '')) > 0));
    }

    private function readCsv(string $path): array
    {
        $text = (string) file_get_contents($path);
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');   // Excel's "CSV" is often Windows-1252
        }
        $firstLine = strtok($text, "\n") ?: '';
        $delimiter = ',';
        foreach ([';', "\t", '|'] as $candidate) {
            if (substr_count($firstLine, $candidate) > substr_count($firstLine, $delimiter)) {
                $delimiter = $candidate;
            }
        }
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $text);
        rewind($handle);
        $rows = [];
        while (($r = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $rows[] = array_map(fn ($c) => (string) $c, $r);
            if (count($rows) > self::MAX_ROWS + 50) {
                break;   // far past the limit; store() reports the real count
            }
        }
        fclose($handle);

        return $rows;
    }

    private function readXlsx(string $path): array
    {
        try {
            $sheets = Excel::toArray(new AlumniSheetImport(), $path);
        } catch (\Throwable $e) {
            throw new AlumniRuleException('That file could not be read as a spreadsheet. Check that it is a valid .xlsx file.', 422, 'file');
        }
        $first = $sheets[0] ?? [];

        return array_map(fn ($r) => array_map(fn ($c) => is_scalar($c) || $c === null ? (string) $c : '', (array) $r), $first);
    }

    /** @param array<string, mixed> $mapping @return array<string, int> */
    private function checkMapping(array $mapping, int $columns): array
    {
        $clean = [];
        $used = [];
        foreach ($mapping as $field => $index) {
            if ($index === null || $index === '' || $index === -1) {
                continue;
            }
            if (! isset(self::FIELDS[$field])) {
                throw new AlumniRuleException('Unknown field: ' . $field, 422, 'mapping');
            }
            if (! is_numeric($index) || (int) $index < 0 || (int) $index >= $columns) {
                throw new AlumniRuleException('Column for ' . self::FIELDS[$field][0] . ' does not exist in the file.', 422, 'mapping');
            }
            if (isset($used[(int) $index])) {
                throw new AlumniRuleException('One column cannot feed two fields (' . self::FIELDS[$used[(int) $index]][0] . ' and ' . self::FIELDS[$field][0] . ').', 422, 'mapping');
            }
            $used[(int) $index] = $field;
            $clean[$field] = (int) $index;
        }
        if (! isset($clean['first_name']) && ! isset($clean['full_name'])) {
            throw new AlumniRuleException('Choose the column that holds the first name (or the full name).', 422, 'mapping');
        }

        return $clean;
    }

    /**
     * Apply the mapping to one raw row: trim, drop control characters, reject formulas, convert dates, split a full name.
     *
     * @param  array<string, int>  $mapping
     * @param  array<int, string>  $raw
     * @return array{0: array<string, mixed>, 1: array<string, array<int, string>>} [values, errors]
     */
    private function normalise(array $mapping, array $raw): array
    {
        $data = [];
        $errors = [];
        foreach ($mapping as $field => $index) {
            $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) ($raw[$index] ?? '')) ?? '';
            $v = trim($v);
            if ($v === '') {
                continue;
            }
            if (preg_match('/^[=@]/', $v)) {
                $errors[$field][] = 'Cells that start with = or @ look like spreadsheet formulas and are not accepted.';

                continue;
            }
            if (in_array($field, self::DATE_FIELDS, true)) {
                $d = $this->date($v);
                if ($d === null) {
                    $errors[$field][] = 'Use a date like 2001-03-25 or 25/03/2001.';

                    continue;
                }
                $v = $d;
            } elseif ($field === 'email') {
                $v = mb_strtolower($v);
            } elseif (in_array($field, ['mobile', 'alt_mobile'], true)) {
                $v = preg_replace('/[\s().\-]/', '', $v);
            } elseif ($field === 'employment_status') {
                $v = str_replace([' ', '-'], '_', mb_strtolower($v));
            } elseif (in_array($field, ['passing_year', 'admission_year', 'student_id'], true) && preg_match('/^\d+\.0+$/', $v)) {
                $v = (string) (int) $v;   // a spreadsheet's 2018 often arrives as "2018.0"
            }
            $data[$field] = $v;
        }
        if (! isset($data['first_name']) && isset($data['full_name'])) {
            $parts = preg_split('/\s+/', $data['full_name']);
            $data['first_name'] = array_shift($parts);
            if ($parts !== [] && ! isset($data['last_name'])) {
                $data['last_name'] = array_pop($parts);
                if ($parts !== [] && ! isset($data['middle_name'])) {
                    $data['middle_name'] = implode(' ', $parts);
                }
            }
        }
        unset($data['full_name']);

        return [$data, $errors];
    }

    /** @return array<string, array<int, string>> */
    private function validate(array $data): array
    {
        $rules = array_diff_key($this->alumni->rules(true), array_flip(['consent_email', 'consent_sms', 'consent_whatsapp', 'consent_push']));
        $v = Validator::make($data, $rules);
        $errors = $v->fails() ? $v->errors()->messages() : [];
        if (! isset($errors['first_name']) && ! isset($data['first_name'])) {
            $errors['first_name'] = ['The first name is required.'];
        }
        if ($errors === [] && count(array_filter(self::IDENTIFIERS, fn ($k) => ! empty($data[$k]))) === 0) {
            $errors['email'] = ['Give an email, mobile, enrollment no., GR no. or student ID so duplicates can be detected.'];
        }

        return $errors;
    }

    /** Identifiers that make two rows "the same person" inside one file. */
    private function keys(array $d): array
    {
        $out = [];
        foreach (['email', 'mobile', 'enrollment_no', 'gr_no', 'student_id'] as $f) {
            if (! empty($d[$f])) {
                $out[] = $f . ':' . mb_strtolower((string) $d[$f]);
            }
        }
        if (! empty($d['dob']) && ! empty($d['first_name'])) {
            $out[] = 'name_dob:' . mb_strtolower($this->alumni->fullName($d)) . '|' . $d['dob'];
        }

        return $out;
    }

    /** A date as Y-m-d from ISO, day-first formats or an Excel serial number; null when it is none of those. */
    private function date(string $v): ?string
    {
        if (preg_match('/^\d{5}(\.\d+)?$/', $v) && (float) $v > 1 && (float) $v < 80000) {   // Excel serial date
            return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $v))->format('Y-m-d');
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'j/n/Y', 'j-n-Y'] as $format) {
            $d = \DateTime::createFromFormat('!' . $format, $v);
            $errs = \DateTime::getLastErrors();
            if ($d && (! $errs || ($errs['warning_count'] === 0 && $errs['error_count'] === 0))) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }
}
