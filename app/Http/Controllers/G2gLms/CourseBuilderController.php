<?php

namespace App\Http\Controllers\G2gLms;

use App\Http\Controllers\Controller;
use App\Http\Controllers\G2gLms\Concerns\ResolvesLmsIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Course Builder — the 5-step authoring wizard. G2G LMS migration (Package 3).
 *
 * Ported from hp_erp's `App\Http\Controllers\Api\LmsCourseController`
 * (store/update/show + the settings/prerequisites helpers + the audience
 * preview/assign pair) and `App\Http\Controllers\Api\LmsLearningController`
 * (storeChapter/updateChapter/destroyChapter, storeContent/updateContent/
 * destroyContent — the module/content-authoring endpoints the wizard's Step 2
 * calls) plus a light `question_paper` slice for Step 3's quiz list.
 *
 * ── OVERLAP WITH PACKAGE 1 (Learning Catalog) ───────────────────────────────
 * A course is a `sub_std_map` row read by BOTH screens: Package 1's Learning
 * Catalog browses/lists it, this controller authors it. The task brief asks
 * this package to own only "the audience/authoring-specific methods" and not
 * duplicate Package 1's base course CRUD — but the Course Builder wizard IS
 * the create/update flow (it must persist a draft after Step 1 before Step 2
 * can attach chapters to a `course_id`), so `store`/`update`/`show` could not
 * be omitted without breaking the wizard entirely. At the time this was
 * written, Package 1 had not yet landed `LearningCatalogController` (only its
 * migrations existed), so there is no code to defer to. This controller's
 * routes live entirely under the `course-builder` prefix — a different URL
 * namespace from Package 1's `learning-catalog` prefix — so there is no route
 * collision, only the logical duplication of "create/update a sub_std_map
 * row" the brief anticipated. Flagged here and in the final report.
 *
 * ── SCHEMA ADAPTATION: chapter_master / content_master / question_paper ────
 * These are this K12 codebase's own long-standing native tables (not ported
 * from hp_erp), and their columns differ from hp_erp's copies: no
 * `deleted_at`/`updated_at`/`updated_by`. `show_hide` (already present on all
 * three) stands in for a soft delete, and updates simply do not touch
 * `updated_at`/`updated_by`.
 *
 * ── AUDIENCE ASSIGNMENT ──────────────────────────────────────────────────
 * The source's `assignAudience` also writes `lms_assignments` (Package 2's
 * table, tracks assignment_type/due_date/approval) and `course_jobrole_map`.
 * Neither exists in this database yet. This controller writes the one thing
 * that is unambiguously its own to guarantee — the `lms_course_enroll` row
 * that makes the course appear in My Learning — and writes to
 * `lms_assignments`/`course_jobrole_map` only when those tables exist
 * (`lmsTableExists()`), so nothing 500s while Package 2 is still landing.
 */
class CourseBuilderController extends Controller
{
    use ResolvesLmsIdentity;

    /* ================================================================== *
     * Settings + prerequisites (ported from LmsCourseController)
     * ================================================================== */

    private function settingsRules(): array
    {
        return [
            'settings.description' => 'nullable|string|max:2000',
            'settings.duration_minutes' => 'nullable|integer|min:0|max:100000',
            'settings.language' => 'nullable|string|max:50',
            'settings.is_mandatory' => 'nullable|boolean',
            'settings.discussion_enabled' => 'nullable|boolean',
            'settings.visibility' => 'nullable|string|in:all,restricted',
            'settings.passing_score' => 'nullable|integer|min:0|max:100',
            'settings.max_attempts' => 'nullable|integer|min:1|max:100',
            'settings.issue_certificate' => 'nullable|boolean',
            'settings.certificate_template' => 'nullable|string|max:50',
            'settings.recert_alerts' => 'nullable|boolean',
            'settings.enrollment_rule' => 'nullable|string|in:open,approval',
            'settings.restrict_departments' => 'nullable|array',
            'settings.restrict_departments.*' => 'integer',
            'settings.restrict_roles' => 'nullable|array',
            'settings.restrict_roles.*' => 'string|max:191',
            'settings.available_from' => 'nullable|date',
            'settings.available_until' => 'nullable|date|after_or_equal:settings.available_from',
            'prerequisites' => 'nullable|array',
            'prerequisites.*' => 'integer',
        ];
    }

    /** Decode `settings`/`prerequisites` when sent JSON-encoded (multipart create-with-image). */
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

    private function saveSettings(Request $request, int $courseId, int $subInstituteId): void
    {
        if (! $request->has('settings')) {
            return;
        }

        $settings = (array) $request->input('settings', []);
        $userId = (int) session()->get('user_id');

        $payload = [
            'sub_institute_id' => $subInstituteId,
            'description' => $settings['description'] ?? null,
            'duration_minutes' => $settings['duration_minutes'] ?? null,
            'language' => $settings['language'] ?? null,
            'is_mandatory' => ! empty($settings['is_mandatory']),
            'discussion_enabled' => ! empty($settings['discussion_enabled']),
            'visibility' => $settings['visibility'] ?? 'all',
            'passing_score' => $settings['passing_score'] ?? null,
            'max_attempts' => $settings['max_attempts'] ?? null,
            'issue_certificate' => array_key_exists('issue_certificate', $settings)
                ? (bool) $settings['issue_certificate']
                : true,
            'certificate_template' => $settings['certificate_template'] ?? null,
            'recert_alerts' => ! empty($settings['recert_alerts']),
            'enrollment_rule' => $settings['enrollment_rule'] ?? 'open',
            'restrict_departments' => empty($settings['restrict_departments'])
                ? null
                : json_encode(array_values(array_map('intval', $settings['restrict_departments']))),
            'restrict_roles' => empty($settings['restrict_roles'])
                ? null
                : json_encode(array_values($settings['restrict_roles'])),
            'available_from' => $settings['available_from'] ?? null,
            'available_until' => $settings['available_until'] ?? null,
            'updated_by' => $userId,
            'updated_at' => now(),
        ];

        $exists = DB::table('lms_course_settings')->where('course_id', $courseId)->exists();

        if ($exists) {
            DB::table('lms_course_settings')->where('course_id', $courseId)->update($payload);
        } else {
            DB::table('lms_course_settings')->insert($payload + [
                'course_id' => $courseId,
                'created_by' => $userId,
                'created_at' => now(),
            ]);
        }
    }

    private function savePrerequisites(Request $request, int $courseId, int $subInstituteId): void
    {
        if (! $request->has('prerequisites')) {
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
            $userId = (int) session()->get('user_id');
            DB::table('lms_course_prerequisites')->insert(
                $valid->map(fn ($id) => [
                    'course_id' => $courseId,
                    'prerequisite_course_id' => $id,
                    'sub_institute_id' => $subInstituteId,
                    'created_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all()
            );
        }
    }

    private function loadSettings(int $courseId): array
    {
        $settings = DB::table('lms_course_settings')->where('course_id', $courseId)->first();

        if ($settings) {
            foreach (['is_mandatory', 'discussion_enabled', 'issue_certificate', 'recert_alerts', 'sequential_unlock'] as $flag) {
                if (property_exists($settings, $flag)) {
                    $settings->$flag = (bool) $settings->$flag;
                }
            }
            foreach (['restrict_departments', 'restrict_roles'] as $list) {
                $decoded = $settings->$list ? json_decode($settings->$list, true) : null;
                $settings->$list = is_array($decoded) ? $decoded : null;
            }
        }

        $prerequisites = DB::table('lms_course_prerequisites as p')
            ->join('sub_std_map as s', 's.id', '=', 'p.prerequisite_course_id')
            ->where('p.course_id', $courseId)
            ->whereNull('p.deleted_at')
            ->whereNull('s.deleted_at')
            ->get(['p.prerequisite_course_id as id', 's.display_name as title']);

        return ['settings' => $settings, 'prerequisites' => $prerequisites];
    }

    /* ================================================================== *
     * Reference data for the wizard's selects
     *
     * hp_erp's `LmsCourseController::filters()` served these from
     * `config('lms.*)`, which does not exist in this repo — languages and
     * certificate templates are a small fixed list here instead. Everything
     * else (categories/types/departments/job roles) is read live, same as
     * source.
     * ================================================================== */

    /** GET /api/g2g-lms/course-builder/options */
    public function options(Request $request)
    {
        $context = $this->lmsContext($request);
        $sid = $context['sub_institute_id'];

        $distinct = fn (string $column) => DB::table('sub_std_map')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->values();

        $departments = DB::table('hrms_departments')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->orderBy('department')
            ->get(['id', 'department']);

        $jobRoles = $this->lmsTableExists('s_user_jobrole')
            ? DB::table('s_user_jobrole')
                ->where('sub_institute_id', $sid)
                ->whereNull('deleted_at')
                ->orderBy('jobrole')
                ->get(['id', 'jobrole', 'department_id'])
            : collect();

        $courses = DB::table('sub_std_map')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->orderBy('display_name')
            ->limit(200)
            ->get(['id', 'display_name']);

        return $this->lmsOk([
            'categories' => $distinct('subject_category'),
            'subject_types' => $distinct('subject_type'),
            'departments' => $departments,
            'job_roles' => $jobRoles,
            'languages' => ['English', 'Hindi', 'Marathi', 'Gujarati', 'Tamil', 'Telugu'],
            'certificate_templates' => [
                ['value' => 'standard', 'label' => 'Standard'],
                ['value' => 'compliance', 'label' => 'Compliance'],
            ],
            'courses' => $courses,
        ]);
    }

    /* ================================================================== *
     * Course (sub_std_map)
     * ================================================================== */

    /** GET /api/g2g-lms/course-builder/courses/{id} */
    public function show(Request $request, $id)
    {
        $context = $this->lmsContext($request);

        $course = DB::table('sub_std_map')
            ->where('id', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->first();

        if (! $course) {
            return $this->lmsError('Course not found', 404);
        }

        return response()->json([
            'status' => true,
            'data' => $course,
        ] + $this->loadSettings((int) $course->id));
    }

    /** POST /api/g2g-lms/course-builder/courses — create the draft. */
    public function store(Request $request)
    {
        $this->normalizeBuilderInput($request);
        $context = $this->lmsContext($request);
        $subInstituteId = $context['sub_institute_id'];

        $validator = Validator::make(
            array_merge($request->all(), ['sub_institute_id' => $subInstituteId]),
            [
                'sub_institute_id' => 'required|integer',
                'display_name' => 'required|string|max:191',
                'standard_id' => 'required|integer',
                'subject_category' => 'nullable|string|max:191',
                'subject_code' => 'nullable|string|max:100',
                'subject_type' => 'nullable|string|max:100',
                'jobrole' => 'nullable|string|max:191',
                'sort_order' => 'nullable|integer',
                'certificate_validity_months' => 'nullable|integer|min:1|max:600',
                'status' => 'required|integer|in:0,1',
                'display_image' => 'nullable|image',
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
            ->where('sub_institute_id', $subInstituteId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $departmentExists) {
            return $this->lmsError('Invalid Department ID', 422);
        }

        $duplicate = DB::table('sub_std_map')
            ->where('sub_institute_id', $subInstituteId)
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
                'display_name' => $request->display_name,
                'standard_id' => $request->standard_id,
                'subject_category' => $request->subject_category,
                'subject_code' => $request->subject_code,
                'subject_type' => $request->subject_type,
                'jobrole' => $request->jobrole,
                'sort_order' => $request->sort_order ?? 1,
                'certificate_validity_months' => $request->certificate_validity_months,
                'status' => (int) $request->status,
                'sub_institute_id' => $subInstituteId,
                'allow_grades' => 'Yes',
                'allow_content' => 'Yes',
                'elective_subject' => 'No',
                'add_content' => 'chapterwise',
                'created_by' => $context['user_id'],
                'created_at' => now(),
            ];

            if ($request->hasFile('display_image')) {
                $file = $request->file('display_image');
                $fileName = date('YmdHis') . '.' . $file->getClientOriginalExtension();
                $path = \Illuminate\Support\Facades\Storage::disk('public')->putFileAs('lms_course', $file, $fileName);
                $data['display_image'] = \Illuminate\Support\Facades\Storage::disk('public')->url($path);
            }

            $courseId = DB::table('sub_std_map')->insertGetId($data);

            $this->saveSettings($request, $courseId, $subInstituteId);
            $this->savePrerequisites($request, $courseId, $subInstituteId);

            return response()->json([
                'status' => true,
                'message' => 'Course created successfully',
                'data' => DB::table('sub_std_map')->find($courseId),
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

    /** PUT /api/g2g-lms/course-builder/courses/{id} */
    public function update(Request $request, $id)
    {
        $this->normalizeBuilderInput($request);
        $context = $this->lmsContext($request);
        $subInstituteId = $context['sub_institute_id'];

        $validator = Validator::make(
            array_merge($request->all(), ['sub_institute_id' => $subInstituteId]),
            [
                'sub_institute_id' => 'required|integer',
                'display_name' => 'required|string|max:191',
                'standard_id' => 'required|integer',
                'subject_category' => 'nullable|string|max:191',
                'subject_code' => 'nullable|string|max:100',
                'subject_type' => 'nullable|string|max:100',
                'jobrole' => 'nullable|string|max:191',
                'sort_order' => 'nullable|integer',
                'certificate_validity_months' => 'nullable|integer|min:1|max:600',
                'status' => 'required|integer|in:0,1',
            ] + $this->settingsRules()
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->messages()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $course = DB::table('sub_std_map')
            ->where('id', $id)
            ->where('sub_institute_id', $subInstituteId)
            ->whereNull('deleted_at')
            ->first();

        if (! $course) {
            return $this->lmsError('Course not found', 404);
        }

        $departmentExists = DB::table('hrms_departments')
            ->where('id', $request->standard_id)
            ->where('sub_institute_id', $subInstituteId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $departmentExists) {
            return $this->lmsError('Invalid Department ID', 422);
        }

        $duplicate = DB::table('sub_std_map')
            ->where('sub_institute_id', $subInstituteId)
            ->where('display_name', $request->display_name)
            ->where('standard_id', $request->standard_id)
            ->where('id', '!=', $id)
            ->whereNull('deleted_at')
            ->exists();

        if ($duplicate) {
            return $this->lmsError('A course with this name already exists in that department', 422);
        }

        try {
            DB::table('sub_std_map')->where('id', $id)->update([
                'display_name' => $request->display_name,
                'standard_id' => $request->standard_id,
                'subject_category' => $request->subject_category,
                'subject_code' => $request->subject_code,
                'subject_type' => $request->subject_type,
                'jobrole' => $request->jobrole,
                'sort_order' => $request->input('sort_order', $course->sort_order),
                'certificate_validity_months' => $request->certificate_validity_months,
                'status' => (int) $request->status,
                'updated_by' => $context['user_id'],
                'updated_at' => now(),
            ]);

            $this->saveSettings($request, (int) $id, $subInstituteId);
            $this->savePrerequisites($request, (int) $id, $subInstituteId);

            return response()->json([
                'status' => true,
                'message' => 'Course updated successfully',
                'data' => DB::table('sub_std_map')->find($id),
                'course_id' => (int) $id,
            ] + $this->loadSettings((int) $id));
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update the course',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /* ================================================================== *
     * Modules (chapter_master) + content (content_master)
     * ================================================================== */

    /** GET /api/g2g-lms/course-builder/courses/{id}/modules */
    public function modules(Request $request, $id)
    {
        $context = $this->lmsContext($request);

        $chapters = DB::table('chapter_master')
            ->where('subject_id', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->where('show_hide', 1)
            ->orderBy('sort_order')
            ->get();

        $contentByChapter = DB::table('content_master')
            ->whereIn('chapter_id', $chapters->pluck('id'))
            ->where('show_hide', 1)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('chapter_id');

        $chapters = $chapters->map(function ($chapter) use ($contentByChapter) {
            $chapter->content = ($contentByChapter->get($chapter->id) ?? collect())->values();

            return $chapter;
        })->values();

        return $this->lmsOk(['chapters' => $chapters]);
    }

    /** POST /api/g2g-lms/course-builder/chapters */
    public function storeModule(Request $request)
    {
        $context = $this->lmsContext($request);

        $validator = Validator::make($request->all(), [
            'subject_id' => 'required|integer',
            'chapter_name' => 'required|string|max:191',
            'chapter_desc' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->messages()->first(), 'errors' => $validator->errors()], 422);
        }

        $course = DB::table('sub_std_map')
            ->where('id', $request->subject_id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->first();

        if (! $course) {
            return $this->lmsError('Course not found', 404);
        }

        $id = DB::table('chapter_master')->insertGetId([
            'subject_id' => $request->subject_id,
            'standard_id' => $course->standard_id,
            'chapter_name' => $request->chapter_name,
            'chapter_desc' => $request->chapter_desc,
            'sort_order' => $request->input('sort_order', 1),
            'show_hide' => 1,
            'sub_institute_id' => $context['sub_institute_id'],
            'syear' => $context['syear'] ?: null,
            'created_by' => $context['user_id'],
            'created_at' => now(),
        ]);

        return $this->lmsOk(DB::table('chapter_master')->find($id), 'Module created', 201);
    }

    /** PUT /api/g2g-lms/course-builder/chapters/{id} */
    public function updateModule(Request $request, $id)
    {
        $context = $this->lmsContext($request);

        $validator = Validator::make($request->all(), [
            'chapter_name' => 'required|string|max:191',
            'chapter_desc' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->messages()->first(), 'errors' => $validator->errors()], 422);
        }

        $chapter = DB::table('chapter_master')
            ->where('id', $id)->where('sub_institute_id', $context['sub_institute_id'])->first();

        if (! $chapter) {
            return $this->lmsError('Module not found', 404);
        }

        DB::table('chapter_master')->where('id', $id)->update([
            'chapter_name' => $request->chapter_name,
            'chapter_desc' => $request->chapter_desc,
            'sort_order' => $request->input('sort_order', $chapter->sort_order),
        ]);

        return $this->lmsOk(DB::table('chapter_master')->find($id), 'Module renamed');
    }

    /** DELETE /api/g2g-lms/course-builder/chapters/{id} */
    public function destroyModule(Request $request, $id)
    {
        $context = $this->lmsContext($request);

        $chapter = DB::table('chapter_master')
            ->where('id', $id)->where('sub_institute_id', $context['sub_institute_id'])->first();

        if (! $chapter) {
            return $this->lmsError('Module not found', 404);
        }

        DB::table('chapter_master')->where('id', $id)->update(['show_hide' => 0]);
        DB::table('content_master')->where('chapter_id', $id)->update(['show_hide' => 0]);

        return $this->lmsOk(['id' => (int) $id], 'Module removed');
    }

    /** POST /api/g2g-lms/course-builder/content */
    public function storeContent(Request $request)
    {
        $context = $this->lmsContext($request);

        $validator = Validator::make($request->all(), [
            'chapter_id' => 'required|integer',
            'title' => 'required|string|max:191',
            'description' => 'nullable|string',
            'filename' => 'nullable|string',
            'url' => 'nullable|string|max:1000',
            'file_type' => 'nullable|string|max:191',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->messages()->first(), 'errors' => $validator->errors()], 422);
        }

        $chapter = DB::table('chapter_master')
            ->where('id', $request->chapter_id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->first();

        if (! $chapter) {
            return $this->lmsError('Module not found', 404);
        }

        $id = DB::table('content_master')->insertGetId([
            'chapter_id' => $chapter->id,
            'subject_id' => $chapter->subject_id,
            'standard_id' => $chapter->standard_id,
            'title' => $request->title,
            'description' => $request->description,
            // filename is the canonical media column the player reads first.
            'filename' => $request->input('filename') ?: $request->input('url'),
            'url' => $request->input('url'),
            'file_type' => $request->file_type,
            'content_category' => 'Videos',
            'sort_order' => $request->input('sort_order', 1),
            'show_hide' => 1,
            'sub_institute_id' => $context['sub_institute_id'],
            'syear' => $context['syear'] ?: null,
            'created_by' => $context['user_id'],
            'created_at' => now(),
        ]);

        return $this->lmsOk(DB::table('content_master')->find($id), 'Content created', 201);
    }

    /** DELETE /api/g2g-lms/course-builder/content/{id} */
    public function destroyContent(Request $request, $id)
    {
        $context = $this->lmsContext($request);

        $content = DB::table('content_master')
            ->where('id', $id)->where('sub_institute_id', $context['sub_institute_id'])->first();

        if (! $content) {
            return $this->lmsError('Content not found', 404);
        }

        DB::table('content_master')->where('id', $id)->update(['show_hide' => 0]);

        return $this->lmsOk(['id' => (int) $id], 'Content removed');
    }

    /* ================================================================== *
     * Assessments (question_paper)
     * ================================================================== */

    /** GET /api/g2g-lms/course-builder/assessments?course_id= */
    public function assessments(Request $request)
    {
        $context = $this->lmsContext($request);

        $rows = DB::table('question_paper')
            ->where('subject_id', $request->input('course_id'))
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->where('show_hide', 1)
            ->orderByDesc('id')
            ->get()
            ->map(function ($row) {
                $ids = array_filter(explode(',', (string) $row->question_ids));
                $row->question_ids = array_map('intval', $ids);
                $row->total_ques = $row->total_ques ?? count($ids);

                return $row;
            });

        return $this->lmsOk($rows);
    }

    /** POST /api/g2g-lms/course-builder/assessments */
    public function storeAssessment(Request $request)
    {
        $context = $this->lmsContext($request);

        $validator = Validator::make($request->all(), [
            'course_id' => 'required|integer',
            'paper_name' => 'required|string|max:191',
            'paper_desc' => 'nullable|string|max:191',
            'attempt_allowed' => 'nullable|integer',
            'time_allowed' => 'nullable|integer',
            'timelimit_enable' => 'nullable|boolean',
            'open_date' => 'nullable|date',
            'close_date' => 'nullable|date',
            'shuffle_question' => 'nullable|boolean',
            'show_feedback' => 'nullable|boolean',
            'result_show_ans' => 'nullable|boolean',
            'exam_type' => 'nullable|string|max:191',
            'question_ids' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->messages()->first(), 'errors' => $validator->errors()], 422);
        }

        $course = DB::table('sub_std_map')
            ->where('id', $request->course_id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->first();

        if (! $course) {
            return $this->lmsError('Course not found', 404);
        }

        $questionIds = (array) $request->input('question_ids', []);

        $id = DB::table('question_paper')->insertGetId([
            'standard_id' => $course->standard_id,
            'subject_id' => $request->course_id,
            'paper_name' => $request->paper_name,
            'paper_desc' => $request->paper_desc,
            'attempt_allowed' => $request->attempt_allowed,
            'time_allowed' => $request->time_allowed,
            'timelimit_enable' => (bool) $request->input('timelimit_enable', false),
            'open_date' => $request->open_date,
            'close_date' => $request->close_date,
            'shuffle_question' => (bool) $request->input('shuffle_question', false),
            'show_feedback' => (bool) $request->input('show_feedback', false),
            'result_show_ans' => (bool) $request->input('result_show_ans', false),
            'exam_type' => $request->input('exam_type', 'quiz'),
            'question_ids' => implode(',', $questionIds),
            'total_ques' => count($questionIds),
            'total_marks' => count($questionIds),
            'show_hide' => 1,
            'sub_institute_id' => $context['sub_institute_id'],
            'syear' => $context['syear'] ?: null,
            'created_by' => $context['user_id'],
            'created_on' => now(),
        ]);

        return $this->lmsOk(DB::table('question_paper')->find($id), 'Assessment created', 201);
    }

    /** DELETE /api/g2g-lms/course-builder/assessments/{id} */
    public function destroyAssessment(Request $request, $id)
    {
        $context = $this->lmsContext($request);

        $paper = DB::table('question_paper')
            ->where('id', $id)->where('sub_institute_id', $context['sub_institute_id'])->first();

        if (! $paper) {
            return $this->lmsError('Assessment not found', 404);
        }

        DB::table('question_paper')->where('id', $id)->update(['show_hide' => 0]);

        return $this->lmsOk(['id' => (int) $id], 'Assessment removed');
    }

    /* ================================================================== *
     * Audience — who this course reaches
     * ================================================================== */

    private function expandAudience(Request $request, int $tenant): array
    {
        $userIds = array_map('intval', (array) $request->input('user_ids', []));
        $departmentIds = array_map('intval', (array) $request->input('department_ids', []));
        $jobroleIds = array_map('intval', (array) $request->input('jobrole_ids', []));

        if (! $userIds && ! $departmentIds && ! $jobroleIds) {
            return [];
        }

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

        return $query->distinct()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** GET /api/g2g-lms/course-builder/courses/{id}/audience/preview */
    public function audiencePreview(Request $request, $id)
    {
        $context = $this->lmsContext($request);
        $userIds = $this->expandAudience($request, $context['sub_institute_id']);

        $sample = empty($userIds) ? [] : DB::table('tbluser as u')
            ->leftJoin('hrms_departments as d', 'd.id', '=', 'u.department_id')
            ->whereIn('u.id', array_slice($userIds, 0, 8))
            ->selectRaw("u.id, TRIM(CONCAT_WS(' ', u.first_name, u.last_name)) as name, d.department")
            ->get();

        $already = (empty($userIds) || ! $this->lmsTableExists('lms_course_enroll'))
            ? 0
            : DB::table('lms_course_enroll')
                ->whereIn('user_id', $userIds)
                ->where('course_id', $id)
                ->whereNull('deleted_at')
                ->distinct()
                ->count('user_id');

        return $this->lmsOk([
            'count' => count($userIds),
            'already_enrolled' => $already,
            'will_assign' => max(0, count($userIds) - $already),
            'sample' => $sample,
        ]);
    }

    /**
     * POST /api/g2g-lms/course-builder/courses/{id}/audience
     *
     * Writes the `lms_course_enroll` row so the course reaches My Learning.
     * `lms_assignments` (assignment_type/due_date tracking) and
     * `course_jobrole_map` are written too, but only when those tables exist
     * — see the class doc-comment.
     */
    public function assignAudience(Request $request, $id)
    {
        $context = $this->lmsContext($request);
        if (! $this->isLmsStaffAdmin($context)) {
            return $this->lmsError('Your profile is not permitted to assign courses.', 403);
        }

        $tenant = $context['sub_institute_id'];

        $course = DB::table('sub_std_map')
            ->where('id', $id)->where('sub_institute_id', $tenant)->whereNull('deleted_at')->first();

        if (! $course) {
            return $this->lmsError('Course not found', 404);
        }

        $userIds = $this->expandAudience($request, $tenant);
        if (empty($userIds)) {
            return $this->lmsError('Choose at least one person, department or job role.', 422);
        }

        $hasEnroll = $this->lmsTableExists('lms_course_enroll');
        $hasAssignments = $this->lmsTableExists('lms_assignments');
        $hasJobroleMap = $this->lmsTableExists('course_jobrole_map');

        $assignedBy = DB::table('tbluser')
            ->where('id', $context['user_id'])
            ->selectRaw("TRIM(CONCAT_WS(' ', first_name, last_name)) as full_name")
            ->value('full_name') ?: 'Admin';

        $type = $request->input('assignment_type', 'Mandatory');
        $dueDate = $request->input('due_date');

        $assigned = 0;
        $alreadyHad = 0;

        DB::transaction(function () use ($userIds, $id, $tenant, $type, $dueDate, $assignedBy, $hasEnroll, $hasAssignments, &$assigned, &$alreadyHad) {
            foreach ($userIds as $userId) {
                $hadIt = false;

                if ($hasAssignments) {
                    $hadIt = DB::table('lms_assignments')
                        ->where('user_id', $userId)->where('course_id', $id)->whereNull('deleted_at')->exists();

                    if (! $hadIt) {
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
                    }
                } elseif ($hasEnroll) {
                    // No lms_assignments table yet: fall back to lms_course_enroll
                    // itself to decide "already had it".
                    $hadIt = DB::table('lms_course_enroll')
                        ->where('user_id', $userId)->where('course_id', $id)->whereNull('deleted_at')->exists();
                }

                if ($hadIt) {
                    $alreadyHad++;
                } else {
                    $assigned++;
                }

                if ($hasEnroll) {
                    DB::table('lms_course_enroll')->insertOrIgnore([
                        'sub_institute_id' => $tenant,
                        'user_id' => $userId,
                        'course_id' => $id,
                        'status' => 'enrolled',
                        'start_date' => now()->toDateString(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        if ($hasJobroleMap) {
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

        return $this->lmsOk([
            'assigned' => $assigned,
            'already_had_it' => $alreadyHad,
            'reached' => count($userIds),
        ], "Assigned to {$assigned} " . ($assigned === 1 ? 'person' : 'people') . '.');
    }
}
