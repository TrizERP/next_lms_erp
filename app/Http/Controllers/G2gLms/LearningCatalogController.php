<?php

namespace App\Http\Controllers\G2gLms;

use App\Http\Controllers\Controller;
use App\Http\Controllers\G2gLms\Concerns\ResolvesLmsIdentity;
use App\Models\school_setup\sub_std_mapModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Learning Catalog — G2G LMS migration (Package 1).
 *
 * Ported from hp_erp's `App\Http\Controllers\Api\LmsCourseController`. Courses
 * live in `sub_std_map`, reused as-is; see
 * database/migrations/2026_09_05_220000_alter_sub_std_map_for_g2g_lms.php for
 * the columns this port needed added.
 *
 * ── DEPENDENCIES ON OTHER PACKAGES' TABLES ──────────────────────────────────
 * `lms_course_settings` / `lms_course_prerequisites` (Course Builder,
 * Package 3) and `lms_assignments` / `course_jobrole_map`
 * (Assignments/Administration, Packages 2/4) are read or written by the
 * audience-assignment and settings endpoints ported here, exactly as hp_erp's
 * controller does. `lms_assignments` already exists in this schema
 * (2026_08_21_090600_add_competency_link_to_lms_assignments_table.php);
 * `lms_course_settings`/`lms_course_prerequisites` are created by Package 3's
 * own migrations (2026_09_05_220100/220200). `course_jobrole_map` and
 * `s_user_jobrole` are NOT created by any approved migration in this package
 * or (as far as this pass could find) any other — every access to those two
 * specifically is guarded with `Schema::hasTable()` and degrades gracefully
 * (empty list / skipped write) rather than failing outright. See the Package 1
 * report for the full list of these adaptations.
 */
class LearningCatalogController extends Controller
{
    use ResolvesLmsIdentity;

    private const SORTABLE = [
        'title'      => 's.display_name',
        'category'   => 's.subject_category',
        'type'       => 's.subject_type',
        'status'     => 's.status',
        'learners'   => 'learners',
        'completion' => 'completion_rate',
        'updated_at' => 's.updated_at',
    ];

    private function enrolmentAggregates()
    {
        return DB::table('lms_course_enroll')
            ->whereNull('deleted_at')
            ->select(
                'course_id',
                DB::raw('COUNT(DISTINCT user_id) as learners'),
                DB::raw("COUNT(DISTINCT CASE WHEN status = 'completed' THEN user_id END) as completed_learners")
            )
            ->groupBy('course_id');
    }

    private function normalizeBuilderInput(Request $request): void
    {
        foreach (['settings', 'prerequisites'] as $key) {
            $value = $request->input($key);

            if (is_string($value) && $value !== '') {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $request->merge([$key => $decoded]);
                }
            }
        }
    }

    private function settingsRules(): array
    {
        return [
            'settings.description'          => 'nullable|string|max:2000',
            'settings.duration_minutes'     => 'nullable|integer|min:0|max:100000',
            'settings.language'             => 'nullable|string|max:50',
            'settings.is_mandatory'         => 'nullable|boolean',
            'settings.discussion_enabled'   => 'nullable|boolean',
            'settings.visibility'           => 'nullable|string|in:all,restricted',
            'settings.passing_score'        => 'nullable|integer|min:0|max:100',
            'settings.max_attempts'         => 'nullable|integer|min:1|max:100',
            'settings.issue_certificate'    => 'nullable|boolean',
            'settings.certificate_template' => 'nullable|string|max:50',
            'settings.recert_alerts'        => 'nullable|boolean',
            'settings.enrollment_rule'      => 'nullable|string|in:open,approval',
            'settings.restrict_departments' => 'nullable|array',
            'settings.restrict_departments.*' => 'integer',
            'settings.restrict_roles'       => 'nullable|array',
            'settings.restrict_roles.*'     => 'string|max:191',
            'settings.available_from'       => 'nullable|date',
            'settings.available_until'      => 'nullable|date|after_or_equal:settings.available_from',
            'prerequisites'                 => 'nullable|array',
            'prerequisites.*'               => 'integer',
        ];
    }

    private function saveSettings(Request $request, int $courseId, $subInstituteId): void
    {
        if (!$request->has('settings') || !Schema::hasTable('lms_course_settings')) {
            return;
        }

        $settings = (array) $request->input('settings', []);
        $userId = $this->contextUserId($request);

        $payload = [
            'sub_institute_id'     => $subInstituteId,
            'description'          => $settings['description'] ?? null,
            'duration_minutes'     => $settings['duration_minutes'] ?? null,
            'language'             => $settings['language'] ?? null,
            'is_mandatory'         => !empty($settings['is_mandatory']),
            'discussion_enabled'   => !empty($settings['discussion_enabled']),
            'visibility'           => $settings['visibility'] ?? 'all',
            'passing_score'        => $settings['passing_score'] ?? null,
            'max_attempts'         => $settings['max_attempts'] ?? null,
            'issue_certificate'    => array_key_exists('issue_certificate', $settings)
                ? (bool) $settings['issue_certificate']
                : true,
            'certificate_template' => $settings['certificate_template'] ?? null,
            'recert_alerts'        => !empty($settings['recert_alerts']),
            'enrollment_rule'      => $settings['enrollment_rule'] ?? 'open',
            'restrict_departments' => empty($settings['restrict_departments'])
                ? null
                : json_encode(array_values(array_map('intval', $settings['restrict_departments']))),
            'restrict_roles'       => empty($settings['restrict_roles'])
                ? null
                : json_encode(array_values($settings['restrict_roles'])),
            'available_from'       => $settings['available_from'] ?? null,
            'available_until'      => $settings['available_until'] ?? null,
            'updated_by'           => $userId,
            'updated_at'           => now(),
        ];

        $exists = DB::table('lms_course_settings')->where('course_id', $courseId)->exists();

        if ($exists) {
            DB::table('lms_course_settings')->where('course_id', $courseId)->update($payload);
        } else {
            DB::table('lms_course_settings')->insert($payload + [
                'course_id'  => $courseId,
                'created_by' => $userId,
                'created_at' => now(),
            ]);
        }
    }

    private function savePrerequisites(Request $request, int $courseId, $subInstituteId): void
    {
        if (!$request->has('prerequisites') || !Schema::hasTable('lms_course_prerequisites')) {
            return;
        }

        $wanted = collect((array) $request->input('prerequisites', []))
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === $courseId || $id <= 0)
            ->unique()
            ->values();

        $valid = DB::table('sub_std_map')
            ->whereIn('id', $wanted)
            ->where('sub_institute_id', $subInstituteId)
            ->whereNull('deleted_at')
            ->pluck('id');

        DB::table('lms_course_prerequisites')->where('course_id', $courseId)->delete();

        if ($valid->isNotEmpty()) {
            DB::table('lms_course_prerequisites')->insert(
                $valid->map(fn ($id) => [
                    'course_id'              => $courseId,
                    'prerequisite_course_id' => $id,
                    'sub_institute_id'       => $subInstituteId,
                    'created_by'             => $this->contextUserId($request),
                    'created_at'             => now(),
                    'updated_at'             => now(),
                ])->all()
            );
        }
    }

    private function loadSettings(int $courseId): array
    {
        if (!Schema::hasTable('lms_course_settings')) {
            return ['settings' => null, 'prerequisites' => collect()];
        }

        $settings = DB::table('lms_course_settings')->where('course_id', $courseId)->first();

        if ($settings) {
            foreach (['is_mandatory', 'discussion_enabled', 'issue_certificate', 'recert_alerts'] as $flag) {
                $settings->$flag = (bool) $settings->$flag;
            }
            foreach (['restrict_departments', 'restrict_roles'] as $list) {
                $decoded = $settings->$list ? json_decode($settings->$list, true) : null;
                $settings->$list = is_array($decoded) ? $decoded : null;
            }
        }

        $prerequisites = Schema::hasTable('lms_course_prerequisites')
            ? DB::table('lms_course_prerequisites as p')
                ->join('sub_std_map as s', 's.id', '=', 'p.prerequisite_course_id')
                ->where('p.course_id', $courseId)
                ->whereNull('p.deleted_at')
                ->whereNull('s.deleted_at')
                ->get(['p.prerequisite_course_id as id', 's.display_name as title'])
            : collect();

        return ['settings' => $settings, 'prerequisites' => $prerequisites];
    }

    /** GET /g2g-lms/learning-catalog/courses */
    public function index(Request $request)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $subInstituteId = $this->lmsTenantId($request);

        if (!$subInstituteId) {
            return response()->json(['status' => false, 'message' => 'sub_institute_id is required'], 422);
        }

        try {
            $query = DB::table('sub_std_map as s')
                ->leftJoin('hrms_departments as d', 'd.id', '=', 's.standard_id')
                ->leftJoinSub($this->enrolmentAggregates(), 'e', function ($join) {
                    $join->on('e.course_id', '=', 's.id');
                })
                ->where('s.sub_institute_id', $subInstituteId)
                ->whereNull('s.deleted_at');

            if ($search = trim((string) $request->input('search', ''))) {
                $query->where(function ($q) use ($search) {
                    $q->where('s.display_name', 'like', "%{$search}%")
                      ->orWhere('s.subject_category', 'like', "%{$search}%")
                      ->orWhere('s.subject_type', 'like', "%{$search}%")
                      ->orWhere('s.subject_code', 'like', "%{$search}%")
                      ->orWhere('s.short_name', 'like', "%{$search}%")
                      ->orWhere('s.jobrole', 'like', "%{$search}%");
                });
            }

            if ($category = $request->input('category')) {
                $query->where('s.subject_category', $category);
            }

            if ($subjectType = $request->input('subject_type')) {
                $query->where('s.subject_type', $subjectType);
            }

            if (($status = $request->input('status')) !== null && $status !== '') {
                $query->where('s.status', (int) $status);
            }

            if ($jobrole = $request->input('jobrole')) {
                $query->where('s.jobrole', $jobrole);
            }

            $total = (clone $query)->count();

            $sortBy = $request->input('sort_by', 'updated_at');
            $sortColumn = self::SORTABLE[$sortBy] ?? self::SORTABLE['updated_at'];
            $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

            $perPage = min(max((int) $request->input('per_page', 10), 1), 100);
            $page = max((int) $request->input('page', 1), 1);

            $courses = $query
                ->select(
                    's.id',
                    's.display_name',
                    's.display_image',
                    's.subject_category',
                    's.subject_type',
                    's.subject_code',
                    's.short_name',
                    's.jobrole',
                    's.proficiency',
                    's.sort_order',
                    's.certificate_validity_months',
                    's.status',
                    's.standard_id',
                    's.created_at',
                    's.updated_at',
                    'd.department as standard_name',
                    DB::raw('COALESCE(e.learners, 0) as learners'),
                    DB::raw('COALESCE(e.completed_learners, 0) as completed_learners'),
                    DB::raw('ROUND(COALESCE(e.completed_learners, 0) / NULLIF(e.learners, 0) * 100) as completion_rate')
                )
                ->orderBy($sortColumn, $sortDir)
                ->forPage($page, $perPage)
                ->get()
                ->map(function ($course) {
                    $course->completion_rate = (int) ($course->completion_rate ?? 0);
                    $course->learners = (int) $course->learners;
                    $course->completed_learners = (int) $course->completed_learners;
                    $course->status = (int) $course->status;
                    return $course;
                });

            return response()->json([
                'status' => true,
                'data' => $courses,
                'meta' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => (int) ceil($total / $perPage),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve courses',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /** GET /g2g-lms/learning-catalog/kpis */
    public function kpis(Request $request)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $subInstituteId = $this->lmsTenantId($request);

        if (!$subInstituteId) {
            return response()->json(['status' => false, 'message' => 'sub_institute_id is required'], 422);
        }

        try {
            $courses = DB::table('sub_std_map')
                ->where('sub_institute_id', $subInstituteId)
                ->whereNull('deleted_at')
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active')
                ->selectRaw('SUM(CASE WHEN status <> 1 THEN 1 ELSE 0 END) as inactive')
                ->selectRaw('COUNT(DISTINCT subject_category) as categories')
                ->first();

            $enrolments = DB::table('lms_course_enroll')
                ->where('sub_institute_id', $subInstituteId)
                ->whereNull('deleted_at')
                ->selectRaw('COUNT(*) as total_enrolments')
                ->selectRaw('COUNT(DISTINCT user_id) as learners')
                ->selectRaw("COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed")
                ->first();

            $totalEnrolments = (int) ($enrolments->total_enrolments ?? 0);
            $completed = (int) ($enrolments->completed ?? 0);

            return response()->json([
                'status' => true,
                'data' => [
                    'total_courses' => (int) ($courses->total ?? 0),
                    'active_courses' => (int) ($courses->active ?? 0),
                    'inactive_courses' => (int) ($courses->inactive ?? 0),
                    'categories' => (int) ($courses->categories ?? 0),
                    'total_learners' => (int) ($enrolments->learners ?? 0),
                    'total_enrolments' => $totalEnrolments,
                    'avg_completion_rate' => $totalEnrolments > 0
                        ? (int) round($completed / $totalEnrolments * 100)
                        : 0,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve catalog KPIs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /** GET /g2g-lms/learning-catalog/filters */
    public function filters(Request $request)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $subInstituteId = $this->lmsTenantId($request);

        if (!$subInstituteId) {
            return response()->json(['status' => false, 'message' => 'sub_institute_id is required'], 422);
        }

        try {
            $distinct = function (string $column) use ($subInstituteId) {
                return DB::table('sub_std_map')
                    ->where('sub_institute_id', $subInstituteId)
                    ->whereNull('deleted_at')
                    ->whereNotNull($column)
                    ->where($column, '!=', '')
                    ->distinct()
                    ->orderBy($column)
                    ->pluck($column)
                    ->values();
            };

            $departments = DB::table('hrms_departments as d')
                ->leftJoin('sub_std_map as s', function ($join) use ($subInstituteId) {
                    $join->on('s.standard_id', '=', 'd.id')
                         ->where('s.sub_institute_id', '=', $subInstituteId)
                         ->whereNull('s.deleted_at');
                })
                ->whereNull('d.deleted_at')
                ->groupBy('d.id', 'd.department')
                ->orderBy('d.department')
                ->get(['d.id', 'd.department', DB::raw('COUNT(s.id) as course_count')]);

            // `s_user_jobrole` is not created by any migration in this schema
            // (checked before writing this) - degrade to an empty list rather
            // than fail. `jobroles` (the legacy free-text distinct list from
            // sub_std_map) is unaffected since it never depended on this table.
            $jobRoles = Schema::hasTable('s_user_jobrole')
                ? DB::table('s_user_jobrole')
                    ->where('sub_institute_id', $subInstituteId)
                    ->orderBy('jobrole')
                    ->get(['id', 'jobrole', 'department_id'])
                : collect();

            return response()->json([
                'status' => true,
                'data' => [
                    'categories' => $distinct('subject_category'),
                    'subject_types' => $distinct('subject_type'),
                    'jobroles' => $distinct('jobrole'),
                    'job_roles' => $jobRoles,
                    'departments' => $departments,
                    'languages' => config('lms.languages', []),
                    'certificate_templates' => collect(config('lms.certificate_templates', []))
                        ->map(fn ($template) => [
                            'value' => $template['value'],
                            'label' => $template['label'],
                        ])
                        ->values(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve catalog filters',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /** GET /g2g-lms/learning-catalog/courses/{id} */
    public function show(Request $request, $id)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $subInstituteId = $this->lmsTenantId($request);

        if (!$subInstituteId) {
            return response()->json(['status' => false, 'message' => 'sub_institute_id is required'], 422);
        }

        try {
            $course = DB::table('sub_std_map as s')
                ->leftJoin('hrms_departments as d', 'd.id', '=', 's.standard_id')
                ->leftJoinSub($this->enrolmentAggregates(), 'e', function ($join) {
                    $join->on('e.course_id', '=', 's.id');
                })
                ->where('s.id', $id)
                ->where('s.sub_institute_id', $subInstituteId)
                ->whereNull('s.deleted_at')
                ->select(
                    's.*',
                    'd.department as standard_name',
                    DB::raw('COALESCE(e.learners, 0) as learners'),
                    DB::raw('COALESCE(e.completed_learners, 0) as completed_learners'),
                    DB::raw('ROUND(COALESCE(e.completed_learners, 0) / NULLIF(e.learners, 0) * 100) as completion_rate')
                )
                ->first();

            if (!$course) {
                return response()->json(['status' => false, 'message' => 'Course not found'], 404);
            }

            $course->completion_rate = (int) ($course->completion_rate ?? 0);
            $course->learners = (int) $course->learners;
            $course->completed_learners = (int) $course->completed_learners;
            $course->status = (int) $course->status;
            $course->chapter_count = DB::table('chapter_master')
                ->where('subject_id', $course->id)
                ->count();

            return response()->json([
                'status' => true,
                'data' => $course,
            ] + $this->loadSettings((int) $course->id));

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve the course',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /** POST /g2g-lms/learning-catalog/courses */
    public function store(Request $request)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $this->normalizeBuilderInput($request);
        $subInstituteId = $this->lmsTenantId($request);

        $validator = Validator::make(
            array_merge($request->all(), ['sub_institute_id' => $subInstituteId]),
            [
                'sub_institute_id' => 'required|integer',
                'display_name'     => 'required|string|max:191',
                'standard_id'      => 'required|integer',
                'subject_category' => 'nullable|string|max:191',
                'subject_code'     => 'nullable|string|max:100',
                'subject_type'     => 'nullable|string|max:100',
                'short_name'       => 'nullable|string|max:100',
                'jobrole'          => 'nullable|string|max:191',
                'proficiency'      => 'nullable|string|max:191',
                'sort_order'       => 'nullable|integer',
                'certificate_validity_months' => 'nullable|integer|min:1|max:600',
                'status'           => 'required|integer|in:0,1',
                'display_image'    => 'nullable|image',
            ] + $this->settingsRules()
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->messages()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $departmentExists = DB::table('hrms_departments')
            ->where('id', $request->standard_id)
            ->whereNull('deleted_at')
            ->exists();

        if (!$departmentExists) {
            return response()->json(['status' => false, 'message' => 'Invalid Department ID'], 422);
        }

        $duplicate = sub_std_mapModel::where('sub_institute_id', $subInstituteId)
            ->where('display_name', $request->display_name)
            ->where('standard_id', $request->standard_id)
            ->whereNull('deleted_at')
            ->first();

        if ($duplicate) {
            return response()->json([
                'status' => false,
                'message' => 'A course with this name already exists in that department',
                'course_id' => $duplicate->id,
            ], 422);
        }

        try {
            $data = [
                'display_name'     => $request->display_name,
                'standard_id'      => $request->standard_id,
                'subject_category' => $request->subject_category,
                'subject_code'     => $request->subject_code,
                'subject_type'     => $request->subject_type,
                'short_name'       => $request->short_name,
                'jobrole'          => $request->jobrole,
                'proficiency'      => $request->proficiency,
                'sort_order'       => $request->sort_order ?? 1,
                'certificate_validity_months' => $request->certificate_validity_months,
                'status'           => (int) $request->status,
                'sub_institute_id' => $subInstituteId,
                'allow_grades'     => 'Yes',
                'allow_content'    => 'Yes',
                'elective_subject' => 'No',
                'add_content'      => 'chapterwise',
                'created_by'       => $this->contextUserId($request),
                'created_at'       => now(),
            ];

            if ($request->hasFile('display_image')) {
                $file = $request->file('display_image');
                $fileName = date('YmdHis') . '.' . $file->getClientOriginalExtension();
                // Uses the default local/public disk (this project has no
                // 'digitalocean' disk configured, unlike hp_erp) so the upload
                // still succeeds; swap the disk name if object storage is wired
                // up for this project later.
                $path = Storage::disk('public')->putFileAs('hp_course', $file, $fileName);
                $data['display_image'] = Storage::disk('public')->url($path);
            }

            $courseId = sub_std_mapModel::insertGetId($data);

            $this->saveSettings($request, $courseId, $subInstituteId);
            $this->savePrerequisites($request, $courseId, $subInstituteId);

            return response()->json([
                'status' => true,
                'message' => 'Course created successfully',
                'data' => sub_std_mapModel::find($courseId),
                'course_id' => $courseId,
            ] + $this->loadSettings($courseId), 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create the course',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /** PUT /g2g-lms/learning-catalog/courses/{id} */
    public function update(Request $request, $id)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $this->normalizeBuilderInput($request);
        $subInstituteId = $this->lmsTenantId($request);

        $validator = Validator::make(
            array_merge($request->all(), ['sub_institute_id' => $subInstituteId]),
            [
                'sub_institute_id' => 'required|integer',
                'display_name'     => 'required|string|max:191',
                'standard_id'      => 'required|integer',
                'subject_category' => 'nullable|string|max:191',
                'subject_code'     => 'nullable|string|max:100',
                'subject_type'     => 'nullable|string|max:100',
                'short_name'       => 'nullable|string|max:100',
                'jobrole'          => 'nullable|string|max:191',
                'proficiency'      => 'nullable|string|max:191',
                'sort_order'       => 'nullable|integer',
                'certificate_validity_months' => 'nullable|integer|min:1|max:600',
                'status'           => 'required|integer|in:0,1',
            ] + $this->settingsRules()
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->messages()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $course = sub_std_mapModel::where('id', $id)
            ->where('sub_institute_id', $subInstituteId)
            ->whereNull('deleted_at')
            ->first();

        if (!$course) {
            return response()->json(['status' => false, 'message' => 'Course not found'], 404);
        }

        $departmentExists = DB::table('hrms_departments')
            ->where('id', $request->standard_id)
            ->whereNull('deleted_at')
            ->exists();

        if (!$departmentExists) {
            return response()->json(['status' => false, 'message' => 'Invalid Department ID'], 422);
        }

        $duplicate = sub_std_mapModel::where('sub_institute_id', $subInstituteId)
            ->where('display_name', $request->display_name)
            ->where('standard_id', $request->standard_id)
            ->where('id', '!=', $id)
            ->whereNull('deleted_at')
            ->exists();

        if ($duplicate) {
            return response()->json([
                'status' => false,
                'message' => 'A course with this name already exists in that department',
            ], 422);
        }

        try {
            $editable = [
                'display_name', 'standard_id', 'subject_category', 'subject_code',
                'subject_type', 'short_name', 'jobrole', 'proficiency', 'sort_order',
                'certificate_validity_months', 'status',
            ];

            $changes = [];
            foreach ($editable as $field) {
                if ($request->has($field)) {
                    $changes[$field] = $field === 'status'
                        ? (int) $request->input($field)
                        : $request->input($field);
                }
            }

            $changes['updated_by'] = $this->contextUserId($request);
            $changes['updated_at'] = now();

            DB::table('sub_std_map')->where('id', $course->id)->update($changes);

            $this->saveSettings($request, (int) $course->id, $subInstituteId);
            $this->savePrerequisites($request, (int) $course->id, $subInstituteId);

            return response()->json([
                'status' => true,
                'message' => 'Course updated successfully',
                'data' => $course->fresh(),
            ] + $this->loadSettings((int) $course->id));

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update the course',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /** DELETE /g2g-lms/learning-catalog/courses/{id} */
    public function destroy(Request $request, $id)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $subInstituteId = $this->lmsTenantId($request);

        if (!$subInstituteId) {
            return response()->json(['status' => false, 'message' => 'sub_institute_id is required'], 422);
        }

        $course = sub_std_mapModel::where('id', $id)
            ->where('sub_institute_id', $subInstituteId)
            ->whereNull('deleted_at')
            ->first();

        if (!$course) {
            return response()->json(['status' => false, 'message' => 'Course not found'], 404);
        }

        try {
            $course->update([
                'deleted_by' => $this->contextUserId($request),
                'updated_at' => now(),
            ]);
            $course->delete();

            return response()->json([
                'status' => true,
                'message' => 'Course deleted successfully',
                'data' => ['id' => (int) $id],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete the course',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /** POST /g2g-lms/learning-catalog/bulk */
    public function bulk(Request $request)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $subInstituteId = $this->lmsTenantId($request);

        $validator = Validator::make(
            array_merge($request->all(), ['sub_institute_id' => $subInstituteId]),
            [
                'sub_institute_id' => 'required|integer',
                'action'           => 'required|in:activate,deactivate,delete',
                'ids'              => 'required|array|min:1',
                'ids.*'            => 'integer',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->messages()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $scoped = sub_std_mapModel::whereIn('id', $request->ids)
                ->where('sub_institute_id', $subInstituteId)
                ->whereNull('deleted_at');

            $matchedIds = (clone $scoped)->pluck('id')->all();

            if (empty($matchedIds)) {
                return response()->json(['status' => false, 'message' => 'No matching courses found'], 404);
            }

            $action = $request->action;

            if ($action === 'delete') {
                sub_std_mapModel::whereIn('id', $matchedIds)->update([
                    'deleted_by' => $this->contextUserId($request),
                    'updated_at' => now(),
                ]);
                sub_std_mapModel::whereIn('id', $matchedIds)->delete();
                $message = count($matchedIds) . ' course(s) deleted successfully';
            } else {
                sub_std_mapModel::whereIn('id', $matchedIds)->update([
                    'status'     => $action === 'activate' ? 1 : 0,
                    'updated_by' => $this->contextUserId($request),
                    'updated_at' => now(),
                ]);
                $message = count($matchedIds) . ' course(s) '
                    . ($action === 'activate' ? 'activated' : 'deactivated')
                    . ' successfully';
            }

            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => [
                    'affected' => count($matchedIds),
                    'ids' => $matchedIds,
                    'skipped' => count($request->ids) - count($matchedIds),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to complete the bulk action',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function expandAudience(Request $request, int $tenant): array
    {
        $userIds = array_map('intval', (array) $request->input('user_ids', []));
        $departmentIds = array_map('intval', (array) $request->input('department_ids', []));
        $jobroleIds = array_map('intval', (array) $request->input('jobrole_ids', []));

        $query = DB::table('tbluser')
            ->where('sub_institute_id', $tenant)
            ->where(function ($q) use ($userIds, $departmentIds, $jobroleIds) {
                if ($userIds) {
                    $q->orWhereIn('id', $userIds);
                }
                if ($departmentIds) {
                    $q->orWhereIn('department_id', $departmentIds);
                }
                if ($jobroleIds) {
                    $q->orWhereIn(DB::raw('CAST(allocated_standards AS UNSIGNED)'), $jobroleIds);
                }
            });

        if (! $userIds && ! $departmentIds && ! $jobroleIds) {
            return [];
        }

        return $query->distinct()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Ensure a `lms_course_enroll` row exists for (user, course) - ported
     * from hp_erp's `App\Services\Lms\EnrolmentWriter::ensureEnrolment()`,
     * inlined here since only `assignAudience()` in this package needs it.
     */
    private function ensureEnrolment(int $userId, int $courseId, int $tenant): void
    {
        $exists = DB::table('lms_course_enroll')
            ->where('user_id', $userId)
            ->where('course_id', $courseId)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('lms_course_enroll')->insert([
            'user_id' => $userId,
            'course_id' => $courseId,
            'status' => 'enrolled',
            'start_date' => now()->toDateString(),
            'sub_institute_id' => $tenant,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** GET /g2g-lms/learning-catalog/courses/{id}/audience/preview */
    public function audiencePreview(Request $request, $id)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $tenant = $this->lmsTenantId($request);
        $userIds = $this->expandAudience($request, (int) $tenant);

        $sample = empty($userIds) ? [] : DB::table('tbluser as u')
            ->leftJoin('hrms_departments as d', 'd.id', '=', 'u.department_id')
            ->leftJoin('s_user_jobrole as j', 'j.id', '=', DB::raw('CAST(u.allocated_standards AS UNSIGNED)'))
            ->whereIn('u.id', array_slice($userIds, 0, 8))
            ->selectRaw("u.id, TRIM(CONCAT_WS(' ', u.first_name, u.last_name)) as name, d.department, j.jobrole")
            ->get();

        $already = empty($userIds) ? 0 : DB::table('lms_course_enroll')
            ->whereIn('user_id', $userIds)
            ->where('course_id', $id)
            ->whereNull('deleted_at')
            ->distinct()
            ->count('user_id');

        return response()->json([
            'status' => true,
            'data' => [
                'count' => count($userIds),
                'already_enrolled' => $already,
                'will_assign' => max(0, count($userIds) - $already),
                'sample' => $sample,
            ],
        ]);
    }

    /** POST /g2g-lms/learning-catalog/courses/{id}/audience */
    public function assignAudience(Request $request, $id)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        if ($denied = $this->guardLmsProfile($request, ['admin', 'hr'],
            'Your profile is not permitted to assign courses.')) {
            return $denied;
        }

        $tenant = (int) $this->lmsTenantId($request);

        $course = DB::table('sub_std_map')
            ->where('id', $id)
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->first();

        if (! $course) {
            return response()->json(['status' => false, 'message' => 'Course not found'], 404);
        }

        $userIds = $this->expandAudience($request, $tenant);

        if (empty($userIds)) {
            return response()->json([
                'status' => false,
                'message' => 'Choose at least one person, department or job role.',
            ], 422);
        }

        $assignedBy = DB::table('tbluser')
            ->where('id', $this->contextUserId($request))
            ->selectRaw("TRIM(CONCAT_WS(' ', first_name, last_name)) as full_name")
            ->value('full_name') ?: 'Admin';

        $type = $request->input('assignment_type', 'Mandatory');
        $dueDate = $request->input('due_date');

        $assigned = 0;
        $alreadyHad = 0;

        DB::transaction(function () use ($userIds, $id, $tenant, $type, $dueDate, $assignedBy, &$assigned, &$alreadyHad) {
            foreach ($userIds as $userId) {
                $exists = DB::table('lms_assignments')
                    ->where('user_id', $userId)
                    ->where('course_id', $id)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($exists) {
                    $alreadyHad++;
                } else {
                    DB::table('lms_assignments')->insert([
                        'user_id' => $userId,
                        'course_id' => $id,
                        'assignment_type' => $type,
                        'due_date' => $dueDate,
                        'status' => 'Not Started',
                        'progress' => 0,
                        'approval_status' => 'approved',
                        'assigned_by' => $assignedBy,
                        'assigned_on' => now(),
                        'sub_institute_id' => $tenant,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $assigned++;
                }

                $this->ensureEnrolment($userId, (int) $id, $tenant);
            }
        });

        // `course_jobrole_map` is not created by any migration in this schema
        // (checked before writing this) - guarded, skipped when absent rather
        // than failing the whole assignment.
        if (Schema::hasTable('course_jobrole_map')) {
            foreach (array_map('intval', (array) $request->input('jobrole_ids', [])) as $roleId) {
                DB::table('course_jobrole_map')->insertOrIgnore([
                    'sub_institute_id' => $tenant,
                    'course_id' => (int) $id,
                    'jobrole_id' => $roleId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return response()->json([
            'status' => true,
            'message' => "Assigned to {$assigned} " . ($assigned === 1 ? 'person' : 'people') . '.',
            'data' => [
                'assigned' => $assigned,
                'already_had_it' => $alreadyHad,
                'reached' => count($userIds),
            ],
        ]);
    }
}
