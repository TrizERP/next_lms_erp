<?php

namespace App\Http\Controllers\api\alumni;

use App\Http\Controllers\api\alumni\Concerns\AlumniApiSupport;
use App\Http\Controllers\Controller;
use App\Services\Alumni\AlumniConversionService;
use App\Services\Alumni\AlumniEngagementService;
use App\Services\Alumni\AlumniMessaging;
use App\Services\Alumni\AlumniPrivacy;
use App\Services\Alumni\AlumniSearch;
use App\Services\Alumni\AlumniService;
use App\Services\Alumni\DuplicateAlumniException;
use App\Services\Platform\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Alumni master, child records, verification, conversion, dashboard (staff side).
 *
 *   GET|POST alumni/list                       search / filter / sort / paginate
 *   GET      alumni/meta                       filter options (batches, years, countries, ...)
 *   GET      alumni/dashboard                  KPI counts and breakdowns (real queries only)
 *   GET      alumni/{id}                       360 profile payload
 *   POST     alumni/store                      manual registration
 *   POST     alumni/{id}/update                partial update
 *   POST     alumni/{id}/status                change status
 *   POST     alumni/{id}/verify                approve/reject a pending registration
 *   GET      alumni/{id}/academic-history      read-through from tblstudent_enrollment
 *   GET|POST alumni/{id}/{kind}[...]           education|career|business|skills|achievements
 *   GET      alumni/conversion/preview         students the eligibility rule matches (read-only)
 *   POST     alumni/conversion/run             convert (idempotent)
 *   GET|POST alumni/settings                   id pattern, eligibility rule, completion weights
 *
 * TENANCY: the institute and the user come from the session `api.session` hydrates from the
 * verified JWT - never from the request body. Every query below is scoped by it.
 *
 * AUTHORISATION: `staff.only` only keeps Student/Parent out, and the `perm:` middleware is
 * warn-only in this codebase, so the role check is enforced here (gate()). Roles are matched
 * by profile name because profile ids differ per tenant.
 */
class AlumniApiController extends Controller
{
    use AlumniApiSupport;

    private const SORTABLE = ['full_name', 'alumni_code', 'batch', 'passing_year', 'status', 'city', 'country',
        'current_company', 'profile_completion', 'created_at', 'last_activity_at'];

    private const LIST_COLUMNS = ['id', 'alumni_code', 'student_id', 'source', 'full_name', 'gender', 'email', 'mobile',
        'city', 'state', 'country', 'batch', 'passing_year', 'last_class', 'status', 'employment_status',
        'current_company', 'current_industry', 'current_job_title', 'profile_completion', 'created_at', 'last_activity_at'];

    /** Child record kinds: table, validation rules, and whether a write must refresh the career snapshot. */
    private const CHILD = [
        'education' => [
            'table' => 'alumni_education', 'order' => ['passing_year', 'desc'],
            'rules' => [
                'qualification' => 'required|string|max:120', 'course' => 'nullable|string|max:160',
                'specialization' => 'nullable|string|max:160', 'institution' => 'nullable|string|max:220',
                'country' => 'nullable|string|max:120', 'city' => 'nullable|string|max:120',
                'admission_year' => 'nullable|integer|between:1950,2100', 'passing_year' => 'nullable|integer|between:1950,2100',
                'current_status' => 'nullable|in:ongoing,completed,discontinued', 'score' => 'nullable|string|max:40',
                'scholarship' => 'nullable|string|max:200', 'awards' => 'nullable|string|max:300',
                'further_education' => 'nullable|string|max:300',
            ],
        ],
        'career' => [
            'table' => 'alumni_career', 'order' => ['start_date', 'desc'], 'syncs_career' => true,
            'rules' => [
                'employment_status' => 'nullable|in:student,job_seeking,employed,self_employed,entrepreneur,freelancer,government_employee,retired,other',
                'company_name' => 'required|string|max:190', 'company_type' => 'nullable|string|max:80',
                'industry' => 'nullable|string|max:120', 'job_title' => 'nullable|string|max:190',
                'department' => 'nullable|string|max:120', 'employment_type' => 'nullable|string|max:60',
                'start_date' => 'nullable|date', 'end_date' => 'nullable|date|after_or_equal:start_date',
                'is_current' => 'nullable|boolean', 'location' => 'nullable|string|max:160',
                'country' => 'nullable|string|max:120', 'salary_range' => 'nullable|string|max:80',
                'website' => 'nullable|url|max:300',
            ],
        ],
        'business' => [
            'table' => 'alumni_business', 'order' => ['established_year', 'desc'],
            'rules' => [
                'business_name' => 'required|string|max:190', 'business_type' => 'nullable|string|max:100',
                'industry' => 'nullable|string|max:120', 'role' => 'nullable|in:founder,co_founder',
                'established_year' => 'nullable|integer|between:1900,2100', 'website' => 'nullable|url|max:300',
                'location' => 'nullable|string|max:160', 'employee_count' => 'nullable|integer|min:0',
                'description' => 'nullable|string|max:5000', 'contact_details' => 'nullable|string|max:300',
            ],
        ],
        'skills' => [
            'table' => 'alumni_skills', 'order' => ['skill', 'asc'],
            'rules' => [
                'skill' => 'required|string|max:120',
                'level' => 'nullable|in:beginner,intermediate,advanced,expert',
            ],
        ],
        'achievements' => [
            'table' => 'alumni_achievements', 'order' => ['achieved_on', 'desc'],
            'rules' => [
                'category' => 'required|in:award,certification,academic,professional,sports,social_service,entrepreneurship,government_recognition,publication,patent,other',
                'title' => 'required|string|max:220', 'description' => 'nullable|string|max:5000',
                'achieved_on' => 'nullable|date', 'organization' => 'nullable|string|max:220',
            ],
        ],
    ];

    public function __construct(
        private AlumniService $alumni,
        private AlumniConversionService $conversion,
    ) {
    }

    // ---------------------------------------------------------------------- list

    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:100',
            'sort' => ['nullable', Rule::in(self::SORTABLE)], 'dir' => 'nullable|in:asc,desc',
            'status' => ['nullable', Rule::in(AlumniService::STATUSES)],
        ]);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }

        $tenant = $this->tenant($request);
        $q = $this->filtered($tenant, $request);
        $total = (clone $q)->count();
        $perPage = (int) ($request->input('per_page') ?: 25);
        $page = (int) ($request->input('page') ?: 1);
        $sort = $request->input('sort', 'created_at');
        $dir = $request->input('dir', 'desc');

        $rows = $q->orderBy($sort, $dir)->orderBy('id', 'desc')
            ->forPage($page, $perPage)->get(self::LIST_COLUMNS);

        return $this->ok([
            'rows' => $rows,
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total,
                'last_page' => (int) max(1, ceil($total / $perPage))],
        ]);
    }

    public function meta(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $tenant = $this->tenant($request);
        $distinct = fn (string $col) => DB::table('alumni')->where('sub_institute_id', $tenant)
            ->whereNotNull($col)->where($col, '!=', '')->distinct()->orderBy($col)->limit(500)->pluck($col);

        return $this->ok([
            'statuses' => AlumniService::STATUSES,
            'batches' => $distinct('batch'),
            'passing_years' => $distinct('passing_year'),
            'countries' => $distinct('country'),
            'states' => $distinct('state'),
            'cities' => $distinct('city'),
            'industries' => $distinct('current_industry'),
            'employment_statuses' => ['student', 'job_seeking', 'employed', 'self_employed', 'entrepreneur',
                'freelancer', 'government_employee', 'retired', 'other'],
            'skill_levels' => ['beginner', 'intermediate', 'advanced', 'expert'],
            'eligibility_rules' => AlumniConversionService::RULES,
        ]);
    }

    // ----------------------------------------------------------------- dashboard

    public function dashboard(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $base = fn () => DB::table('alumni')->where('sub_institute_id', $t);
        $group = fn (string $col, int $limit = 10) => $base()->whereNotNull($col)->where($col, '!=', '')
            ->select($col . ' as label', DB::raw('COUNT(*) as total'))
            ->groupBy($col)->orderByDesc('total')->limit($limit)->get();

        $byStatus = $base()->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status');
        $count = fn (string $s) => (int) ($byStatus[$s] ?? 0);

        $recent = $base()->orderByDesc('id')->limit(8)->get(['id', 'alumni_code', 'full_name', 'batch', 'status', 'source', 'created_at']);

        return $this->ok([
            'totals' => [
                'total' => (int) $byStatus->sum(),
                'new_last_30_days' => $base()->where('created_at', '>=', now()->subDays(30))->count(),
                'verified' => $count('verified'),
                'active' => $count('active'),
                'pending_verification' => $count('pending_verification'),
                'average_profile_completion' => (int) round((float) $base()->avg('profile_completion')),
                'employed' => $base()->whereIn('employment_status', ['employed', 'government_employee'])->count(),
                'entrepreneurs' => $base()->whereIn('employment_status', ['entrepreneur', 'self_employed'])->count(),
                'job_seeking' => $base()->where('employment_status', 'job_seeking')->count(),
                'with_higher_education' => DB::table('alumni_education')->where('sub_institute_id', $t)->distinct()->count('alumni_id'),
                'with_career_data' => DB::table('alumni_career')->where('sub_institute_id', $t)->distinct()->count('alumni_id'),
            ],
            'by_status' => $byStatus,
            'by_batch' => $group('batch', 15),
            'by_passing_year' => $group('passing_year', 15),
            'by_country' => $group('country'),
            'by_state' => $group('state'),
            'by_city' => $group('city'),
            'by_industry' => $group('current_industry'),
            'by_company' => $group('current_company'),
            'recent_registrations' => $recent,
            'pending_approvals' => $base()->where('status', 'pending_verification')->orderBy('id')->limit(8)
                ->get(['id', 'alumni_code', 'full_name', 'batch', 'created_at']),
            'engagement' => $this->engagementSummary($t),
        ]);
    }

    /** Real counts from the Phase C tables; null for a block whose table has not been created yet. */
    private function engagementSummary(int $t): array
    {
        $eng = app(AlumniEngagementService::class);
        $out = ['average_score' => null, 'active_mentors' => null, 'open_mentorship_requests' => null, 'published_opportunities' => null, 'pending_opportunities' => null, 'guest_speakers' => null];
        if (Schema::hasTable('alumni_activity')) {
            $scores = $eng->scores($t);
            $out['average_score'] = $scores === [] ? 0 : (int) round(array_sum($scores) / count($scores));
            $out['guest_speakers'] = (int) DB::table('alumni_activity')->where('sub_institute_id', $t)->where('activity_type', 'guest_lecture')->distinct()->count('alumni_id');
        }
        if (Schema::hasTable('alumni_mentor_profiles')) {
            $out['active_mentors'] = (int) DB::table('alumni_mentor_profiles')->where('sub_institute_id', $t)->where('status', 'active')->count();
            $out['open_mentorship_requests'] = (int) DB::table('alumni_mentorship_requests')->where('sub_institute_id', $t)->whereIn('status', ['open', 'matched'])->count();
        }
        if (Schema::hasTable('alumni_opportunities')) {
            $today = now()->toDateString();
            $out['published_opportunities'] = (int) DB::table('alumni_opportunities')->where('sub_institute_id', $t)->where('status', 'published')
                ->where(fn ($w) => $w->whereNull('last_date')->orWhere('last_date', '>=', $today))->count();
            $out['pending_opportunities'] = (int) DB::table('alumni_opportunities')->where('sub_institute_id', $t)->where('status', 'pending_approval')->count();
        }

        return $out;
    }

    // ---------------------------------------------------------------- one alumnus

    public function show(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $row = $this->alumni->find($t, (int) $id);
        if (! $row) {
            return $this->notFound();
        }
        $kids = [];
        foreach (self::CHILD as $kind => $cfg) {
            $kids[$kind] = $this->childRows($t, (int) $id, $kind);
        }

        return $this->ok([
            'alumni' => $row,
            'completion' => $this->alumni->completion($t, $row),
            'children' => $kids,
            // metadata only: file_path is never sent to the browser
            'documents' => DB::table('alumni_documents')->where('sub_institute_id', $t)->where('alumni_id', $row->id)
                ->orderByDesc('id')->get(['id', 'doc_type', 'original_name', 'mime', 'size_bytes', 'verification_status', 'remarks', 'created_at']),
            'privacy' => DB::table('alumni_privacy')->where('sub_institute_id', $t)->where('alumni_id', $row->id)
                ->pluck('visibility', 'field_key'),
            'timeline' => DB::table('alumni_activity')->where('sub_institute_id', $t)->where('alumni_id', $row->id)
                ->orderByDesc('occurred_at')->orderByDesc('id')->limit(50)
                ->get(['id', 'activity_type', 'title', 'detail', 'points', 'occurred_at']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), $this->masterRules(true));
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        try {
            $row = $this->alumni->create($t, $request->all(), $this->userId($request), 'manual',
                $request->boolean('allow_duplicate'), $request);
        } catch (DuplicateAlumniException $e) {
            return $this->duplicate($e);
        }

        return $this->ok($row, 'Alumni added.', 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), $this->masterRules(false));
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        try {
            $row = $this->alumni->update($this->tenant($request), (int) $id, $request->all(), $this->userId($request), $request);
        } catch (DuplicateAlumniException $e) {
            return $this->duplicate($e);
        }

        return $row ? $this->ok($row, 'Alumni updated.') : $this->notFound();
    }

    public function status(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), [
            'status' => ['required', Rule::in(AlumniService::STATUSES)], 'remarks' => 'nullable|string|max:500',
        ]);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $row = $this->alumni->changeStatus($this->tenant($request), (int) $id, $request->input('status'),
            $this->userId($request), $request->input('remarks'), $request);

        return $row ? $this->ok($row, 'Status updated.') : $this->notFound();
    }

    public function verify(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), [
            'decision' => 'required|in:approve,reject', 'remarks' => 'nullable|string|max:500',
        ]);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        if ($request->input('decision') === 'reject' && trim((string) $request->input('remarks')) === '') {
            return $this->validationError(['remarks' => ['Give a reason when rejecting a registration.']]);
        }
        try {
            $row = $this->alumni->verify($this->tenant($request), (int) $id, $request->input('decision') === 'approve',
                $request->input('remarks'), $this->userId($request), $request);
        } catch (\DomainException $e) {
            return response()->json(['status_code' => 0, 'message' => $e->getMessage()], 409);
        }

        if ($row) {
            $this->tellVerification($this->tenant($request), $row, $request->input('decision') === 'approve', (string) $request->input('remarks'));
        }

        return $row ? $this->ok($row, $request->input('decision') === 'approve' ? 'Registration approved.' : 'Registration rejected.') : $this->notFound();
    }

    /** Read-through from the ERP: nothing is copied into alumni tables. */
    public function academicHistory(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $row = $this->alumni->find($t, (int) $id);
        if (! $row) {
            return $this->notFound();
        }
        if (! $row->student_id || ! Schema::hasTable('tblstudent_enrollment')) {
            return $this->ok(['linked' => false, 'rows' => []]);
        }

        $rows = DB::table('tblstudent_enrollment as e')
            ->leftJoin('standard as st', 'st.id', '=', 'e.standard_id')
            ->leftJoin('division as d', 'd.id', '=', 'e.section_id')
            ->where('e.sub_institute_id', $t)->where('e.student_id', $row->student_id)
            ->orderBy('e.syear')
            ->get(['e.syear', 'st.name as class_name', 'd.name as division', 'e.start_date', 'e.end_date', 'e.enrollment_code']);

        return $this->ok(['linked' => true, 'rows' => $rows]);
    }

    // --------------------------------------------------------------- child records

    public function childList(Request $request, $id, string $kind): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        if (! $this->alumni->find($t, (int) $id)) {
            return $this->notFound();
        }

        return $this->ok($this->childRows($t, (int) $id, $kind));
    }

    public function childStore(Request $request, $id, string $kind): JsonResponse
    {
        return $this->childSave($request, (int) $id, $kind, null);
    }

    public function childUpdate(Request $request, $id, string $kind, $rid): JsonResponse
    {
        return $this->childSave($request, (int) $id, $kind, (int) $rid);
    }

    public function childDelete(Request $request, $id, string $kind, $rid): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $cfg = self::CHILD[$kind];
        $t = $this->tenant($request);
        $before = DB::table($cfg['table'])->where('sub_institute_id', $t)->where('alumni_id', (int) $id)->where('id', (int) $rid)->first();
        if (! $before) {
            return $this->notFound();
        }
        DB::table($cfg['table'])->where('id', $before->id)->delete();
        $this->afterChildWrite($t, (int) $id, $kind);
        AuditTrail::record($t, 'alumni', 'alumni.' . $kind, 'delete', [
            'entity_type' => 'alumni_' . $kind, 'entity_id' => $before->id, 'before' => (array) $before,
        ], $request);

        return $this->ok(null, 'Removed.');
    }

    private function childSave(Request $request, int $id, string $kind, ?int $rid): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $cfg = self::CHILD[$kind];
        $t = $this->tenant($request);
        if (! $this->alumni->find($t, $id)) {
            return $this->notFound();
        }
        $v = Validator::make($request->all(), $cfg['rules']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $data = [];
        foreach (array_keys($cfg['rules']) as $f) {
            if ($request->has($f)) {
                $val = $request->input($f);
                $data[$f] = (is_string($val) && trim($val) === '') ? null : $val;
            }
        }
        if ($kind === 'skills' && $request->has('level') && empty($data['level'])) {
            $data['level'] = 'intermediate';   // the column is NOT NULL; an unchosen level means the default
        }
        if (isset($data['is_current'])) {
            $data['is_current'] = $request->boolean('is_current') ? 1 : 0;
        }
        $now = now();

        if ($kind === 'skills' && isset($data['skill'])) {
            $dup = DB::table('alumni_skills')->where('alumni_id', $id)->whereRaw('LOWER(skill) = ?', [mb_strtolower($data['skill'])])
                ->when($rid, fn ($q) => $q->where('id', '!=', $rid))->exists();
            if ($dup) {
                return $this->validationError(['skill' => ['This skill is already on the profile.']]);
            }
        }

        if ($rid === null) {
            $new = $data + ['sub_institute_id' => $t, 'alumni_id' => $id, 'created_at' => $now, 'updated_at' => $now];
            if ($kind !== 'skills') {
                $new['created_by'] = $this->userId($request);
            }
            $rid = DB::table($cfg['table'])->insertGetId($new);
            $action = 'create';
        } else {
            $affected = DB::table($cfg['table'])->where('sub_institute_id', $t)->where('alumni_id', $id)->where('id', $rid)
                ->update($data + ['updated_at' => $now]);
            $exists = DB::table($cfg['table'])->where('sub_institute_id', $t)->where('alumni_id', $id)->where('id', $rid)->exists();
            if (! $exists) {
                return $this->notFound();
            }
            $action = 'update';
        }
        $this->afterChildWrite($t, $id, $kind);
        $this->alumni->logActivity($t, $id, $kind . '_' . $action . 'd', ucfirst($kind) . ' record ' . $action . 'd', $this->userId($request));
        AuditTrail::record($t, 'alumni', 'alumni.' . $kind, $action, [
            'entity_type' => 'alumni_' . $kind, 'entity_id' => $rid, 'after' => $data,
        ], $request);

        return $this->ok(DB::table($cfg['table'])->where('id', $rid)->first(), 'Saved.', $action === 'create' ? 201 : 200);
    }

    private function afterChildWrite(int $t, int $id, string $kind): void
    {
        if (! empty(self::CHILD[$kind]['syncs_career'])) {
            $this->alumni->syncCurrentCareer($t, $id);
        } else {
            $this->alumni->refreshCompletion($t, $id);
        }
    }

    private function childRows(int $t, int $id, string $kind)
    {
        $cfg = self::CHILD[$kind];

        return DB::table($cfg['table'])->where('sub_institute_id', $t)->where('alumni_id', $id)
            ->orderBy($cfg['order'][0], $cfg['order'][1])->orderByDesc('id')->get();
    }

    // ------------------------------------------------------- conversion / settings

    public function conversionPreview(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }

        return $this->ok($this->conversion->preview($this->tenant($request), (int) ($request->input('limit') ?: 200)));
    }

    public function conversionRun(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), [
            'student_ids' => 'nullable|array|max:2000', 'student_ids.*' => 'integer|min:1',
            'confirm_all' => 'nullable|boolean',
        ]);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $ids = $request->input('student_ids');
        if ($ids === null && ! $request->boolean('confirm_all')) {
            return $this->validationError(['student_ids' => ['Choose students to convert, or confirm converting everyone the rule matches.']]);
        }
        $t = $this->tenant($request);
        $result = $this->conversion->run($t, $ids, $this->userId($request), $request);
        AuditTrail::record($t, 'alumni', 'alumni.conversion', 'run', [
            'entity_type' => 'alumni_conversion', 'after' => ['created' => $result['created'], 'skipped' => count($result['skipped'])],
        ], $request);

        return $this->ok($result, $result['created'] . ' student(s) converted to alumni.');
    }

    public function settings(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $eng = app(AlumniEngagementService::class);

        return $this->ok([
            'id_pattern' => $this->alumni->setting($t, 'id_pattern', AlumniService::DEFAULT_ID_PATTERN),
            'eligibility_rule' => $this->conversion->rule($t),
            'completion_weights' => array_merge(AlumniService::DEFAULT_COMPLETION_WEIGHTS, (array) $this->alumni->setting($t, 'completion_weights', [])),
            'engagement_weights' => $eng->weights($t),
            'engagement_window_months' => $eng->windowMonths($t),
            'engagement_levels' => array_merge(AlumniEngagementService::DEFAULT_LEVELS, array_intersect_key((array) $this->alumni->setting($t, 'engagement_levels', []), AlumniEngagementService::DEFAULT_LEVELS)),
            'home_country' => $this->alumni->setting($t, 'home_country', 'India'),
            'alumni_portal_url' => (string) $this->alumni->setting($t, 'alumni_portal_url', ''),
            'notify_on_registration' => (bool) $this->alumni->setting($t, 'notify_on_registration', false),
            'event_reminder_days' => (int) $this->alumni->setting($t, 'event_reminder_days', 2),
            'opportunity_approval_required' => (bool) $this->alumni->setting($t, 'opportunity_approval_required', true),
            'privacy_defaults' => app(AlumniPrivacy::class)->defaults($t),
        ]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), [
            'id_pattern' => ['nullable', 'string', 'max:60', 'regex:/\{SEQ:\d{1,2}\}/'],
            'eligibility_rule' => ['nullable', Rule::in(AlumniConversionService::RULES)],
            'completion_weights' => 'nullable|array',
            'completion_weights.*' => 'integer|min:0|max:100',
            'engagement_weights' => 'nullable|array',
            'engagement_weights.*' => 'integer|min:0|max:100',
            'engagement_window_months' => 'nullable|integer|between:1,120',
            'engagement_levels' => 'nullable|array',
            'engagement_levels.*' => 'integer|min:1|max:100',
            'home_country' => 'nullable|string|max:120',
            'alumni_portal_url' => ['nullable', 'string', 'max:300', 'regex:#^https?://[^\s]+$#i'],
            'notify_on_registration' => 'nullable|boolean',
            'event_reminder_days' => 'nullable|integer|between:0,14',
            'opportunity_approval_required' => 'nullable|boolean',
            'privacy_defaults' => 'nullable|array',
            'privacy_defaults.*' => ['string', Rule::in(AlumniPrivacy::LEVELS)],
        ], ['id_pattern.regex' => 'The pattern must contain a counter such as {SEQ:5}.']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $u = $this->userId($request);
        foreach (['id_pattern', 'eligibility_rule', 'home_country', 'alumni_portal_url'] as $k) {
            if ($request->filled($k)) {
                $this->alumni->saveSetting($t, $k, $request->input($k), $u);
            }
        }
        if ($request->filled('engagement_window_months')) {
            $this->alumni->saveSetting($t, 'engagement_window_months', (int) $request->input('engagement_window_months'), $u);
        }
        foreach (['opportunity_approval_required', 'notify_on_registration'] as $flag) {
            if ($request->has($flag)) {
                $this->alumni->saveSetting($t, $flag, $request->boolean($flag), $u);
            }
        }
        if ($request->has('event_reminder_days') && $request->input('event_reminder_days') !== null) {
            $this->alumni->saveSetting($t, 'event_reminder_days', (int) $request->input('event_reminder_days'), $u);
        }
        $maps = [
            'completion_weights' => AlumniService::DEFAULT_COMPLETION_WEIGHTS,
            'engagement_weights' => AlumniEngagementService::DEFAULT_WEIGHTS,
            'engagement_levels' => AlumniEngagementService::DEFAULT_LEVELS,
            'privacy_defaults' => AlumniPrivacy::DEFAULTS,
        ];
        foreach ($maps as $key => $allowed) {
            if ($request->has($key)) {
                $this->alumni->saveSetting($t, $key, array_intersect_key((array) $request->input($key), $allowed), $u);
            }
        }
        AuditTrail::record($t, 'alumni', 'alumni.settings', 'update', ['entity_type' => 'alumni_settings', 'after' => $request->only([
            'id_pattern', 'eligibility_rule', 'completion_weights', 'engagement_weights', 'engagement_window_months', 'engagement_levels',
            'home_country', 'opportunity_approval_required', 'privacy_defaults'])], $request);

        return $this->settings($request);
    }


    // --------------------------------------------------------------------- helpers

    /** The outcome of a verification is a transactional notice to the alumnus (email + in-app). Never blocks the decision. */
    private function tellVerification(int $t, object $row, bool $approved, string $reason): void
    {
        try {
            app(AlumniMessaging::class)->notify($t, (int) $row->id, 'verification',
                $approved ? 'Your alumni profile has been verified' : 'Your alumni registration needs attention',
                $approved ? "Dear {{name}},\n\nYour alumni profile at {{institute}} has been verified. Welcome to the alumni network."
                    : "Dear {{name}},\n\nWe could not verify your alumni registration at {{institute}}.\nReason: {{reason}}\nPlease contact the alumni office.",
                'verification:' . ($approved ? 'approved' : 'rejected') . ':' . $row->id, true, ['reason' => $reason]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function filtered(int $t, Request $r)
    {
        return app(AlumniSearch::class)->filtered($t, $r->all());
    }

    private function masterRules(bool $creating): array
    {
        return $this->alumni->rules($creating);
    }
}
