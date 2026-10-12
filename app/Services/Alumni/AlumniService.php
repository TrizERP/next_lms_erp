<?php

namespace App\Services\Alumni;

use App\Services\Platform\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Alumni master: ID generation, duplicate detection, create/update, status and
 * verification, profile completion.
 *
 * Every method takes the tenant (sub_institute_id) explicitly and scopes every query by
 * it - there is no global scope in this codebase. Controllers pass the tenant from the
 * verified-JWT session, never from the request body.
 */
class AlumniService
{
    public const STATUSES = [
        'registered', 'pending_verification', 'verified', 'active',
        'inactive', 'blocked', 'deceased', 'opted_out',
    ];

    public const DEFAULT_ID_PATTERN = 'ALM-{YYYY}-{SEQ:5}';

    /** Defaults for the profile-completion rule; a tenant may override via alumni_settings. */
    public const DEFAULT_COMPLETION_WEIGHTS = [
        'personal'  => 15,   // first name, gender, dob
        'contact'   => 15,   // email, mobile, city, country
        'academic'  => 15,   // passing year + batch
        'education' => 15,   // >= 1 alumni_education row
        'career'    => 15,   // >= 1 alumni_career row, or an employment status
        'linkedin'  => 10,
        'skills'    => 15,   // >= 1 alumni_skills row
    ];

    // Fields that identify a person; a match on any one of these flags a duplicate.
    private const DUP_FIELDS = ['student_id', 'gr_no', 'enrollment_no', 'email', 'mobile'];

    /** Columns a caller may set on the master row. Anything else in the payload is ignored. */
    public const FILLABLE = [
        'student_id', 'gr_no', 'enrollment_no', 'first_name', 'middle_name', 'last_name',
        'gender', 'dob', 'blood_group', 'email', 'mobile', 'alt_mobile',
        'current_address', 'permanent_address', 'city', 'state', 'country', 'pincode',
        'campus', 'department', 'course', 'last_standard_id', 'last_class', 'division',
        'academic_year', 'admission_year', 'passing_year', 'batch', 'leaving_date',
        'employment_status', 'linkedin_url',
        'consent_email', 'consent_sms', 'consent_whatsapp', 'consent_push',
    ];

    // ---------------------------------------------------------------- settings

    public function setting(int $tenant, string $key, $default = null)
    {
        $raw = DB::table('alumni_settings')
            ->where('sub_institute_id', $tenant)->where('setting_key', $key)->value('setting_value');
        if ($raw === null) {
            return $default;
        }
        $decoded = json_decode($raw, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
    }

    public function saveSetting(int $tenant, string $key, $value, ?int $userId): void
    {
        $now = now();
        $exists = DB::table('alumni_settings')
            ->where('sub_institute_id', $tenant)->where('setting_key', $key)->exists();
        $payload = ['setting_value' => json_encode($value), 'updated_by' => $userId, 'updated_at' => $now];
        if ($exists) {
            DB::table('alumni_settings')
                ->where('sub_institute_id', $tenant)->where('setting_key', $key)->update($payload);
        } else {
            DB::table('alumni_settings')->insert($payload + [
                'sub_institute_id' => $tenant, 'setting_key' => $key, 'created_at' => $now,
            ]);
        }
    }

    // ---------------------------------------------------------------- Alumni ID

    /**
     * Next unique code, e.g. ALM-2026-00001. Pattern tokens: {YYYY}, {SEQ:n}.
     * The counter row is locked inside a transaction so concurrent callers cannot get
     * the same number; the unique index on (tenant, alumni_code) is the final backstop.
     * Must be called inside the caller's transaction (it opens a nested one if not).
     */
    public function nextCode(int $tenant, ?int $year = null): string
    {
        $year = $year ?: (int) now()->format('Y');
        $pattern = (string) $this->setting($tenant, 'id_pattern', self::DEFAULT_ID_PATTERN);
        if (! str_contains($pattern, '{SEQ')) {
            $pattern = self::DEFAULT_ID_PATTERN;   // a pattern without a counter can never be unique
        }

        return DB::transaction(function () use ($tenant, $year, $pattern) {
            $row = DB::table('alumni_id_sequence')
                ->where('sub_institute_id', $tenant)->where('seq_year', $year)->lockForUpdate()->first();
            if ($row === null) {
                try {
                    DB::table('alumni_id_sequence')->insert([
                        'sub_institute_id' => $tenant, 'seq_year' => $year, 'last_number' => 0,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    // lost a race to create the row - fine, re-read it below
                }
                $row = DB::table('alumni_id_sequence')
                    ->where('sub_institute_id', $tenant)->where('seq_year', $year)->lockForUpdate()->first();
            }
            $next = ((int) $row->last_number) + 1;
            DB::table('alumni_id_sequence')->where('id', $row->id)
                ->update(['last_number' => $next, 'updated_at' => now()]);

            $code = str_replace('{YYYY}', (string) $year, $pattern);

            return preg_replace_callback('/\{SEQ:(\d+)\}/', fn ($m) => str_pad((string) $next, (int) $m[1], '0', STR_PAD_LEFT), $code)
                ?? ('ALM-' . $year . '-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT));
        });
    }

    // --------------------------------------------------------------- duplicates

    /**
     * Existing alumni in this tenant that look like the same person.
     * Match on student_id, GR no, enrollment no, email, mobile, or (full name + DOB).
     *
     * @return array<int, array{id:int, alumni_code:string, full_name:string, matched_on:array<int,string>}>
     */
    public function findDuplicates(int $tenant, array $data, ?int $exceptId = null): array
    {
        $q = DB::table('alumni')->where('sub_institute_id', $tenant);
        if ($exceptId !== null) {
            $q->where('id', '!=', $exceptId);
        }

        $clauses = [];
        foreach (self::DUP_FIELDS as $f) {
            $v = isset($data[$f]) ? trim((string) $data[$f]) : '';
            if ($v !== '') {
                $clauses[] = [$f, $v];
            }
        }
        $name = $this->fullName($data);
        $dob = ! empty($data['dob']) ? (string) $data['dob'] : null;
        $nameDob = $name !== '' && $dob !== null;

        if ($clauses === [] && ! $nameDob) {
            return [];
        }

        $q->where(function ($w) use ($clauses, $nameDob, $name, $dob) {
            foreach ($clauses as [$f, $v]) {
                $w->orWhere($f, $v);
            }
            if ($nameDob) {
                $w->orWhere(function ($x) use ($name, $dob) {
                    $x->whereRaw('LOWER(full_name) = ?', [mb_strtolower($name)])->where('dob', $dob);
                });
            }
        });

        $found = [];
        foreach ($q->limit(10)->get() as $row) {
            $matched = [];
            foreach ($clauses as [$f, $v]) {
                if ((string) $row->{$f} === $v) {
                    $matched[] = $f;
                }
            }
            if ($nameDob && mb_strtolower((string) $row->full_name) === mb_strtolower($name) && (string) $row->dob === $dob) {
                $matched[] = 'name_dob';
            }
            $found[] = [
                'id' => (int) $row->id, 'alumni_code' => $row->alumni_code,
                'full_name' => $row->full_name, 'matched_on' => $matched,
            ];
        }

        return $found;
    }

    // ------------------------------------------------------------------ create

    /**
     * Create an alumnus. Throws DuplicateAlumniException when a likely duplicate exists
     * (pass $allowDuplicate only for an explicit staff override of a name+DOB-only match;
     * a hard identifier match - student/GR/enrollment/email/mobile - can never be overridden).
     *
     * @return object the created row
     */
    public function create(int $tenant, array $input, ?int $userId, string $source = 'manual', bool $allowDuplicate = false, ?Request $request = null): object
    {
        $data = $this->clean($input);
        $data['full_name'] = $this->fullName($data);

        $dups = $this->findDuplicates($tenant, $data);
        if ($dups !== []) {
            $hard = array_filter($dups, fn ($d) => array_diff($d['matched_on'], ['name_dob']) !== []);
            if ($hard !== [] || ! $allowDuplicate) {
                throw new DuplicateAlumniException($dups);
            }
        }

        $data['passing_year'] = $data['passing_year'] ?? ($data['leaving_date'] ?? null ? (int) Carbon::parse($data['leaving_date'])->format('Y') : null);
        $data['batch'] = $data['batch'] ?? $this->defaultBatch($data);

        $id = DB::transaction(function () use ($tenant, $data, $userId, $source) {
            $now = now();
            $row = $data + [
                'sub_institute_id' => $tenant,
                'alumni_code' => $this->nextCode($tenant, (int) ($data['passing_year'] ?? 0) ?: null),
                'source' => $source,
                'status' => $source === 'self' ? 'pending_verification' : 'registered',
                'created_by' => $userId, 'updated_by' => $userId,
                'created_at' => $now, 'updated_at' => $now,
            ];
            $id = DB::table('alumni')->insertGetId($row);
            $this->logActivity($tenant, $id, 'registered', 'Alumni record created (' . $source . ')', $userId);

            return $id;
        });

        $this->refreshCompletion($tenant, $id);
        $created = $this->find($tenant, $id);
        AuditTrail::record($tenant, 'alumni', 'alumni.master', 'create', [
            'entity_type' => 'alumni', 'entity_id' => $id, 'actor_user_id' => $userId,
            'after' => ['alumni_code' => $created->alumni_code, 'full_name' => $created->full_name, 'source' => $source],
        ], $request);

        return $created;
    }

    public function find(int $tenant, int $id): ?object
    {
        return DB::table('alumni')->where('sub_institute_id', $tenant)->where('id', $id)->first();
    }

    public function update(int $tenant, int $id, array $input, ?int $userId, ?Request $request = null): ?object
    {
        $before = $this->find($tenant, $id);
        if (! $before) {
            return null;
        }
        $data = $this->clean($input);
        // Only the keys actually supplied change (partial update).
        $data = array_intersect_key($data, array_flip(array_keys($input)));
        $merged = array_merge((array) $before, $data);
        $data['full_name'] = $this->fullName($merged);

        $dups = $this->findDuplicates($tenant, $merged, $id);
        $hard = array_filter($dups, fn ($d) => array_diff($d['matched_on'], ['name_dob']) !== []);
        if ($hard !== []) {
            throw new DuplicateAlumniException(array_values($hard));
        }

        DB::table('alumni')->where('sub_institute_id', $tenant)->where('id', $id)
            ->update($data + ['updated_by' => $userId, 'updated_at' => now()]);
        $this->refreshCompletion($tenant, $id);

        $after = $this->find($tenant, $id);
        $changed = [];
        foreach ($data as $k => $v) {
            if ((string) ($before->{$k} ?? '') !== (string) $v) {
                $changed[$k] = ['old' => $before->{$k} ?? null, 'new' => $v];
            }
        }
        if ($changed !== []) {
            AuditTrail::record($tenant, 'alumni', 'alumni.master', 'update', [
                'entity_type' => 'alumni', 'entity_id' => $id, 'actor_user_id' => $userId,
                'before' => array_map(fn ($c) => $c['old'], $changed),
                'after' => array_map(fn ($c) => $c['new'], $changed),
            ], $request);
        }

        return $after;
    }

    // ------------------------------------------------- status and verification

    public function changeStatus(int $tenant, int $id, string $status, ?int $userId, ?string $remarks = null, ?Request $request = null): ?object
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('Unknown alumni status.');
        }
        $before = $this->find($tenant, $id);
        if (! $before) {
            return null;
        }
        $upd = ['status' => $status, 'updated_by' => $userId, 'updated_at' => now()];
        if ($status === 'verified') {
            $upd += ['verified_by' => $userId, 'verified_at' => now(), 'verification_remarks' => $remarks];
        } elseif ($remarks !== null) {
            $upd['verification_remarks'] = $remarks;
        }
        DB::table('alumni')->where('sub_institute_id', $tenant)->where('id', $id)->update($upd);
        $this->logActivity($tenant, $id, 'status_changed', 'Status changed to ' . $status, $userId);
        AuditTrail::record($tenant, 'alumni', 'alumni.master', 'status', [
            'entity_type' => 'alumni', 'entity_id' => $id, 'actor_user_id' => $userId,
            'before' => ['status' => $before->status], 'after' => ['status' => $status, 'remarks' => $remarks],
        ], $request);

        return $this->find($tenant, $id);
    }

    /** Approve or reject a registration waiting in Pending verification. */
    public function verify(int $tenant, int $id, bool $approve, ?string $remarks, ?int $userId, ?Request $request = null): ?object
    {
        $row = $this->find($tenant, $id);
        if (! $row) {
            return null;
        }
        if ($row->status !== 'pending_verification') {
            throw new \DomainException('Only a registration that is pending verification can be approved or rejected.');
        }

        // Rejecting returns the record to 'inactive' with the reason kept; nothing is deleted.
        return $this->changeStatus($tenant, $id, $approve ? 'verified' : 'inactive', $userId, $remarks, $request);
    }

    // ---------------------------------------------------------------- completion

    public function completion(int $tenant, object $row): array
    {
        $w = array_merge(self::DEFAULT_COMPLETION_WEIGHTS, (array) $this->setting($tenant, 'completion_weights', []));
        $has = fn (string $t) => DB::table($t)->where('sub_institute_id', $tenant)->where('alumni_id', $row->id)->exists();
        $filled = fn (...$cols) => count(array_filter($cols, fn ($c) => trim((string) ($row->{$c} ?? '')) !== '')) === count($cols);

        $checks = [
            'personal'  => $filled('first_name', 'gender', 'dob'),
            'contact'   => $filled('email', 'mobile', 'city', 'country'),
            'academic'  => $filled('passing_year', 'batch'),
            'education' => $has('alumni_education'),
            'career'    => $has('alumni_career') || trim((string) $row->employment_status) !== '',
            'linkedin'  => trim((string) $row->linkedin_url) !== '',
            'skills'    => $has('alumni_skills'),
        ];
        $total = max(1, array_sum(array_intersect_key($w, $checks)));
        $earned = 0;
        $sections = [];
        foreach ($checks as $k => $ok) {
            $earned += $ok ? (int) ($w[$k] ?? 0) : 0;
            $sections[] = ['key' => $k, 'done' => $ok, 'weight' => (int) ($w[$k] ?? 0)];
        }

        return ['percent' => (int) round($earned * 100 / $total), 'sections' => $sections];
    }

    public function refreshCompletion(int $tenant, int $id): void
    {
        $row = $this->find($tenant, $id);
        if ($row) {
            DB::table('alumni')->where('id', $id)->where('sub_institute_id', $tenant)
                ->update(['profile_completion' => $this->completion($tenant, $row)['percent']]);
        }
    }

    /** Keep the master row's searchable "current job" snapshot in step with alumni_career. */
    public function syncCurrentCareer(int $tenant, int $id): void
    {
        $cur = DB::table('alumni_career')
            ->where('sub_institute_id', $tenant)->where('alumni_id', $id)->where('is_current', 1)
            ->orderByDesc('start_date')->orderByDesc('id')->first();

        DB::table('alumni')->where('sub_institute_id', $tenant)->where('id', $id)->update([
            'current_company'   => $cur->company_name ?? null,
            'current_industry'  => $cur->industry ?? null,
            'current_job_title' => $cur->job_title ?? null,
            'employment_status' => $cur->employment_status ?? DB::raw('employment_status'),
            'updated_at'        => now(),
        ]);
        $this->refreshCompletion($tenant, $id);
    }

    // -------------------------------------------------------------------- helpers

    public function logActivity(int $tenant, int $alumniId, string $type, string $title, ?int $userId, int $points = 0, ?string $refType = null, ?int $refId = null): void
    {
        DB::table('alumni_activity')->insert([
            'sub_institute_id' => $tenant, 'alumni_id' => $alumniId, 'activity_type' => $type,
            'title' => mb_substr($title, 0, 255), 'points' => $points,
            'ref_type' => $refType, 'ref_id' => $refId,
            'occurred_at' => now(), 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('alumni')->where('sub_institute_id', $tenant)->where('id', $alumniId)
            ->update(['last_activity_at' => now()]);
    }

    public function fullName(array $d): string
    {
        $parts = array_filter([
            trim((string) ($d['first_name'] ?? '')),
            trim((string) ($d['middle_name'] ?? '')),
            trim((string) ($d['last_name'] ?? '')),
        ], fn ($p) => $p !== '');

        return implode(' ', $parts);
    }

    private function defaultBatch(array $d): ?string
    {
        if (! empty($d['passing_year'])) {
            $y = (int) $d['passing_year'];

            return ($y - 1) . '-' . substr((string) $y, -2);
        }

        return null;
    }

    /** Whitelist + normalise: empty strings become null; unknown keys are dropped. */
    /** Validation rules for the master row: one definition shared by the form and the bulk import. */
    public function rules(bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes|required';

        return [
            'first_name' => $req . '|string|max:100', 'middle_name' => 'nullable|string|max:100', 'last_name' => 'nullable|string|max:100',
            'student_id' => 'nullable|integer|min:1', 'gr_no' => 'nullable|string|max:60', 'enrollment_no' => 'nullable|string|max:60',
            'gender' => 'nullable|string|max:20', 'dob' => 'nullable|date|before:today', 'blood_group' => 'nullable|string|max:10',
            'email' => 'nullable|email|max:190', 'mobile' => 'nullable|string|max:30', 'alt_mobile' => 'nullable|string|max:30',
            'current_address' => 'nullable|string|max:1000', 'permanent_address' => 'nullable|string|max:1000',
            'city' => 'nullable|string|max:120', 'state' => 'nullable|string|max:120', 'country' => 'nullable|string|max:120',
            'pincode' => 'nullable|string|max:20', 'campus' => 'nullable|string|max:120', 'department' => 'nullable|string|max:120',
            'course' => 'nullable|string|max:120', 'last_standard_id' => 'nullable|integer|min:1', 'last_class' => 'nullable|string|max:60',
            'division' => 'nullable|string|max:60', 'academic_year' => 'nullable|string|max:20',
            'admission_year' => 'nullable|integer|between:1950,2100', 'passing_year' => 'nullable|integer|between:1950,2100',
            'batch' => 'nullable|string|max:40', 'leaving_date' => 'nullable|date',
            'employment_status' => 'nullable|in:student,job_seeking,employed,self_employed,entrepreneur,freelancer,government_employee,retired,other',
            'linkedin_url' => 'nullable|url|max:300',
            'consent_email' => 'nullable|boolean', 'consent_sms' => 'nullable|boolean',
            'consent_whatsapp' => 'nullable|boolean', 'consent_push' => 'nullable|boolean',
        ];
    }

    private function clean(array $input): array
    {
        $out = [];
        foreach (self::FILLABLE as $k) {
            if (! array_key_exists($k, $input)) {
                continue;
            }
            $v = $input[$k];
            if (is_string($v)) {
                $v = trim($v);
            }
            $out[$k] = ($v === '' ? null : $v);
        }
        if (isset($out['email'])) {
            $out['email'] = mb_strtolower($out['email']);
        }

        return $out;
    }
}
