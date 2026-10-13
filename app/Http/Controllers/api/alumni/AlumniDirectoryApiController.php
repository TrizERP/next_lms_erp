<?php

namespace App\Http\Controllers\api\alumni;

use App\Http\Controllers\api\alumni\Concerns\AlumniApiSupport;
use App\Http\Controllers\Controller;
use App\Services\Alumni\AlumniPrivacy;
use App\Services\Alumni\AlumniService;
use App\Services\Platform\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Directory (privacy-enforced), privacy and consent editor, batch statistics.
 *
 *   GET  alumni/directory          search other alumni; only fields the viewer may see come back
 *   GET  alumni/{id}/privacy       an alumnus's visibility choices and consent flags
 *   POST alumni/{id}/privacy       change them (staff recording the alumnus's request; audited)
 *   GET  alumni/batches            per-batch (or year / course / ...) statistics
 *
 * DIRECTORY PRIVACY. The viewer is derived from the session here ('institution' for staff), never from
 * a request parameter. Hidden fields are removed from each row AND cannot be used to search: filtering on
 * city, company, skill, ... only matches alumni who let this viewer see that field (AlumniPrivacy::scopeVisible).
 * Only verified or active alumni appear. Identity (name, Alumni ID, batch, passing year, course) is always
 * shown. Student ID / GR / enrollment search is institution-only.
 *
 * The staff management screens (alumni list, profile, edit) are deliberately NOT masked: they are how the
 * institution maintains the records it already holds. Sharing surfaces (directory, exports) are masked.
 */
class AlumniDirectoryApiController extends Controller
{
    use AlumniApiSupport;

    private const VIEWER = 'institution';   // every caller of these routes is staff; alumni self-service is a later phase

    private const GROUP_BY = ['batch', 'passing_year', 'admission_year', 'course', 'department', 'campus'];

    private const SORTABLE = ['full_name', 'passing_year', 'batch', 'created_at'];

    public function __construct(private AlumniService $alumni, private AlumniPrivacy $privacy)
    {
    }

    // ------------------------------------------------------------------ directory

    public function directory(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:50',
            'sort' => ['nullable', Rule::in(self::SORTABLE)], 'dir' => 'nullable|in:asc,desc',
        ]);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }

        $t = $this->tenant($request);
        $viewer = self::VIEWER;
        $q = DB::table('alumni')->where('sub_institute_id', $t)->whereIn('status', ['verified', 'active']);

        // identity-level filters: always visible
        if (($s = trim((string) $request->input('q'))) !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $s) . '%';
            $q->where(fn ($w) => $w->where('full_name', 'like', $like)->orWhere('alumni_code', 'like', $like));
        }
        foreach (['batch', 'passing_year', 'course', 'department', 'campus'] as $c) {
            if ($request->filled($c)) {
                $q->where($c, $request->input($c));
            }
        }
        // institution identifiers: staff only
        if ($viewer === 'institution') {
            foreach (['student_id', 'gr_no', 'enrollment_no'] as $c) {
                if ($request->filled($c)) {
                    $q->where($c, $request->input($c));
                }
            }
        }
        // field filters: only against fields this viewer is allowed to see
        $like = fn (string $key) => '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $request->input($key)) . '%';
        foreach (['country', 'state', 'city'] as $c) {
            if ($request->filled($c)) {
                $this->privacy->scopeVisible($q, $t, 'location', $viewer);
                $q->where($c, $request->input($c));
            }
        }
        if ($request->filled('company')) {
            $this->privacy->scopeVisible($q, $t, 'employment', $viewer);
            $q->where('current_company', 'like', $like('company'));
        }
        if ($request->filled('industry')) {
            $this->privacy->scopeVisible($q, $t, 'employment', $viewer);
            $q->where('current_industry', $request->input('industry'));
        }
        if ($request->filled('job_role')) {
            $this->privacy->scopeVisible($q, $t, 'employment', $viewer);
            $q->where('current_job_title', 'like', $like('job_role'));
        }
        if ($request->filled('skill')) {
            $this->privacy->scopeVisible($q, $t, 'skills', $viewer);
            $q->whereExists(fn ($e) => $e->select(DB::raw(1))->from('alumni_skills as sk')
                ->whereColumn('sk.alumni_id', 'alumni.id')->where('sk.sub_institute_id', $t)
                ->whereRaw('LOWER(sk.skill) like ?', [mb_strtolower($like('skill'))]));
        }
        if ($request->filled('higher_education')) {
            $this->privacy->scopeVisible($q, $t, 'higher_education', $viewer);
            $he = $like('higher_education');
            $q->whereExists(fn ($e) => $e->select(DB::raw(1))->from('alumni_education as ed')
                ->whereColumn('ed.alumni_id', 'alumni.id')->where('ed.sub_institute_id', $t)
                ->where(fn ($x) => $x->where('ed.qualification', 'like', $he)->orWhere('ed.course', 'like', $he)->orWhere('ed.institution', 'like', $he)));
        }

        $total = (clone $q)->count();
        $perPage = (int) ($request->input('per_page') ?: 24);
        $page = (int) ($request->input('page') ?: 1);
        $rows = $q->orderBy($request->input('sort', 'full_name'), $request->input('dir', 'asc'))->orderBy('id')
            ->forPage($page, $perPage)
            ->get(['id', 'alumni_code', 'full_name', 'batch', 'passing_year', 'course', 'last_class',
                'city', 'state', 'country', 'current_company', 'current_job_title', 'current_industry', 'employment_status',
                'linkedin_url', 'email', 'mobile']);

        $ids = $rows->pluck('id')->map(fn ($i) => (int) $i)->all();
        $vis = $this->privacy->visibilityFor($t, $ids);
        $skills = $education = [];
        if ($ids !== []) {
            foreach (DB::table('alumni_skills')->where('sub_institute_id', $t)->whereIn('alumni_id', $ids)->orderBy('skill')->get(['alumni_id', 'skill', 'level']) as $s) {
                $skills[(int) $s->alumni_id][] = ['skill' => $s->skill, 'level' => $s->level];
            }
            foreach (DB::table('alumni_education')->where('sub_institute_id', $t)->whereIn('alumni_id', $ids)->orderByDesc('passing_year')->get(['alumni_id', 'qualification', 'course', 'institution', 'country']) as $e) {
                $education[(int) $e->alumni_id][] = ['qualification' => $e->qualification, 'course' => $e->course, 'institution' => $e->institution, 'country' => $e->country];
            }
        }

        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r->id;
            [$m, $hidden] = $this->privacy->mask((array) $r, $vis[$id], $viewer);
            $out[] = [
                'id' => $id, 'alumni_code' => $m['alumni_code'], 'full_name' => $m['full_name'], 'batch' => $m['batch'],
                'passing_year' => $m['passing_year'], 'course' => $m['course'], 'last_class' => $m['last_class'],
                'city' => $m['city'], 'state' => $m['state'], 'country' => $m['country'],
                'current_company' => $m['current_company'], 'current_job_title' => $m['current_job_title'],
                'current_industry' => $m['current_industry'], 'employment_status' => $m['employment_status'],
                'linkedin_url' => $m['linkedin_url'], 'email' => $m['email'], 'mobile' => $m['mobile'],
                'skills' => in_array('skills', $hidden, true) ? [] : array_slice($skills[$id] ?? [], 0, 6),
                'education' => in_array('higher_education', $hidden, true) ? [] : array_slice($education[$id] ?? [], 0, 2),
                'hidden' => $hidden,
            ];
        }

        return $this->ok([
            'rows' => $out,
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => (int) max(1, ceil($total / $perPage))],
        ]);
    }

    /**
     * Filter choices for the directory. Unlike the admin `alumni/meta`, a value is listed only if at least one
     * directory-eligible alumnus lets this viewer see it - otherwise a dropdown would reveal that someone
     * private lives in a city, or works in an industry, even though their row never appears.
     */
    public function directoryOptions(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $base = fn () => DB::table('alumni')->where('sub_institute_id', $t)->whereIn('status', ['verified', 'active']);
        $identity = fn (string $col) => $base()->whereNotNull($col)->where($col, '!=', '')->distinct()->orderBy($col)->limit(300)->pluck($col);
        $guarded = function (string $col, string $group) use ($base, $t) {
            $q = $base()->whereNotNull($col)->where($col, '!=', '');
            $this->privacy->scopeVisible($q, $t, $group, self::VIEWER);

            return $q->distinct()->orderBy($col)->limit(300)->pluck($col);
        };

        return $this->ok([
            'batches' => $identity('batch'), 'passing_years' => $identity('passing_year'), 'courses' => $identity('course'),
            'countries' => $guarded('country', 'location'), 'cities' => $guarded('city', 'location'),
            'industries' => $guarded('current_industry', 'employment'),
        ]);
    }

    // ------------------------------------------------------- privacy and consent

    public function privacyShow(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $row = $this->alumni->find($t, (int) $id);
        if (! $row) {
            return $this->notFound();
        }

        return $this->ok($this->privacyPayload($t, $row));
    }

    public function privacySave(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), [
            'fields' => 'nullable|array',
            'fields.*' => ['string', Rule::in([...AlumniPrivacy::LEVELS, 'default'])],
            'consents' => 'nullable|array',
            'consents.*' => 'boolean',
        ]);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $unknown = array_diff(array_keys((array) $request->input('fields', [])), array_keys(AlumniPrivacy::GROUPS));
        $badConsent = array_diff(array_keys((array) $request->input('consents', [])), ['consent_email', 'consent_sms', 'consent_whatsapp', 'consent_push']);
        if ($unknown !== [] || $badConsent !== []) {
            return $this->validationError(['fields' => ['Unknown setting: ' . implode(', ', array_merge($unknown, $badConsent))]]);
        }

        $t = $this->tenant($request);
        $row = $this->alumni->find($t, (int) $id);
        if (! $row) {
            return $this->notFound();
        }
        $before = $this->privacyPayload($t, $row);
        $this->privacy->saveChoices($t, $row, (array) $request->input('fields', []), (array) $request->input('consents', []), $this->userId($request));

        $fresh = $this->alumni->find($t, (int) $id);
        $after = $this->privacyPayload($t, $fresh);
        $this->alumni->logActivity($t, (int) $id, 'privacy_updated', 'Privacy and consent settings changed', $this->userId($request));
        AuditTrail::record($t, 'alumni', 'alumni.privacy', 'update', [
            'entity_type' => 'alumni_privacy', 'entity_id' => (int) $id,
            'before' => ['levels' => array_column($before['fields'], 'level', 'key'), 'consents' => $before['consents']],
            'after' => ['levels' => array_column($after['fields'], 'level', 'key'), 'consents' => $after['consents']],
        ], $request);

        return $this->ok($after, 'Privacy settings saved.');
    }

    private function privacyPayload(int $t, object $row): array
    {
        return $this->privacy->payload($t, $row);
    }

    // -------------------------------------------------------------------- batches

    public function batches(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['group_by' => ['nullable', Rule::in(self::GROUP_BY)]]);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $by = (string) ($request->input('group_by') ?: 'batch');   // whitelisted above, so safe to interpolate
        $home = mb_strtolower((string) $this->alumni->setting($t, 'home_country', 'India'));

        $rows = DB::table('alumni')->where('sub_institute_id', $t)->whereNotNull($by)->where($by, '!=', '')
            ->groupBy($by)->orderByDesc($by)
            ->selectRaw("$by as batch_key, COUNT(*) as total,
                SUM(CASE WHEN LOWER(gender) = 'male' THEN 1 ELSE 0 END) as male,
                SUM(CASE WHEN LOWER(gender) = 'female' THEN 1 ELSE 0 END) as female,
                SUM(CASE WHEN status = 'registered' THEN 1 ELSE 0 END) as registered,
                SUM(CASE WHEN status = 'pending_verification' THEN 1 ELSE 0 END) as pending_verification,
                SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN employment_status IN ('employed','government_employee') THEN 1 ELSE 0 END) as employed,
                SUM(CASE WHEN employment_status IN ('entrepreneur','self_employed') THEN 1 ELSE 0 END) as entrepreneurs,
                SUM(CASE WHEN employment_status = 'job_seeking' THEN 1 ELSE 0 END) as job_seeking,
                SUM(CASE WHEN country IS NOT NULL AND country <> '' AND LOWER(country) <> ? THEN 1 ELSE 0 END) as abroad,
                SUM(CASE WHEN EXISTS (SELECT 1 FROM alumni_education e WHERE e.alumni_id = alumni.id AND e.sub_institute_id = ?) THEN 1 ELSE 0 END) as higher_education",
                [$home, $t])
            ->get();

        $cast = fn ($r) => array_map(fn ($x) => is_numeric($x) ? (int) $x : $x, (array) $r);
        $rows = $rows->map($cast)->all();
        $totals = [];
        foreach ($rows as $r) {
            foreach ($r as $k => $val) {
                if ($k !== 'batch_key') {
                    $totals[$k] = ($totals[$k] ?? 0) + $val;
                }
            }
        }

        return $this->ok(['group_by' => $by, 'home_country' => $this->alumni->setting($t, 'home_country', 'India'), 'rows' => $rows, 'totals' => $totals]);
    }
}
