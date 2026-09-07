<?php

namespace App\Http\Controllers\G2gLms;

use App\Http\Controllers\Controller;
use App\Http\Controllers\G2gLms\Concerns\ResolvesLmsIdentity;
use App\Services\G2gLms\DeepSeekAssessmentService;
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
            'settings.auto_apply_rating' => 'nullable|boolean',
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
            /*
             * Passing this course writes the mapped competency rating without
             * a review step. Off by default. The write-on-pass runtime logic
             * itself belongs to the quiz-scoring pipeline (not yet built in
             * this package) — this only lets an author record the intent.
             */
            'auto_apply_rating' => ! empty($settings['auto_apply_rating']),
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
            foreach (['is_mandatory', 'discussion_enabled', 'issue_certificate', 'recert_alerts', 'auto_apply_rating', 'sequential_unlock'] as $flag) {
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
     * Questions on a quiz — ported from hp_erp's LmsAssessmentController.
     *
     * `question_paper` store/update/destroy manage the PAPER; these manage
     * what is ON it. `lms_question_master` and `answer_master` already exist
     * in this schema (unlike `course_jobrole_map`, confirmed present) but had
     * no writer anywhere in this package, so every quiz authored here had
     * `total_ques = 0` and could never actually be sat.
     *
     * Schema adaptation: hp_erp's `answer_master` has `deleted_at`/
     * `updated_at` and soft-deletes an option (a past attempt's
     * `lms_quiz_response.answer_id` can point at one). This schema's
     * `answer_master` has neither column, and the quiz-taking tables
     * (`lms_quiz_attempt`/`lms_quiz_response`) do not exist yet in this
     * package — nothing can reference an option row — so options here are
     * hard-deleted and re-inserted on every update, and written with
     * `created_on` (the column this table actually has) instead of
     * `created_at`/`updated_at`.
     * ================================================================== */

    private function questionRules(): array
    {
        return [
            'question_title' => 'required|string|max:2000',
            'description' => 'nullable|string',
            'points' => 'nullable|integer|min:1|max:100',
            'hint_text' => 'nullable|string|max:1000',
            // A written question has no options and is marked outside this package.
            'options' => 'nullable|array|max:10',
            'options.*.answer' => 'required|string|max:2000',
            'options.*.correct' => 'nullable|boolean',
        ];
    }

    /** The paper, if it belongs to the caller's organisation. */
    private function findPaper($id, $subInstituteId)
    {
        return DB::table('question_paper')
            ->where('id', $id)
            ->where('sub_institute_id', $subInstituteId)
            ->where('show_hide', 1)
            ->first();
    }

    /** The paper's question_ids as an ordered array of ints. */
    private function paperQuestionIds($paper): array
    {
        return collect(explode(',', (string) $paper->question_ids))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Recompute the paper's question list, count and total marks from what is
     * actually attached to it — derived, not incremented, so a deleted
     * question cannot leave the paper claiming marks it no longer has.
     */
    private function syncPaperTotals($paperId, array $orderedIds): void
    {
        $ids = collect($orderedIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        $marks = $ids->isEmpty() ? 0 : (int) DB::table('lms_question_master')
            ->whereIn('id', $ids)
            ->whereNull('deleted_at')
            ->sum('points');

        DB::table('question_paper')->where('id', $paperId)->update([
            'question_ids' => $ids->isEmpty() ? null : $ids->implode(','),
            'total_ques' => $ids->count(),
            'total_marks' => $marks,
        ]);
    }

    /** How many options this request marks correct. */
    private function correctCount(Request $request): int
    {
        return collect((array) $request->input('options', []))
            ->filter(fn ($o) => filter_var($o['correct'] ?? false, FILTER_VALIDATE_BOOLEAN))
            ->count();
    }

    /**
     * Refuse a multiple-choice question that nothing can mark. Zero options
     * is allowed and means a written answer.
     */
    private function rejectUnmarkableOptions(Request $request)
    {
        $options = (array) $request->input('options', []);

        if ($options === [] || $this->correctCount($request) > 0) {
            return null;
        }

        return $this->lmsError(
            'Mark at least one option correct, or remove all options to make this a written answer.',
            422
        );
    }

    /** Write a question's options, flagging the correct ones. */
    private function writeOptions(Request $request, int $questionId, int $subInstituteId, int $userId): void
    {
        $options = (array) $request->input('options', []);

        if ($options === []) {
            return;
        }

        DB::table('answer_master')->insert(array_map(fn ($option) => [
            'question_id' => $questionId,
            'answer' => $option['answer'],
            'correct_answer' => filter_var($option['correct'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
            'sub_institute_id' => $subInstituteId,
            'created_by' => $userId,
            'created_on' => now(),
        ], $options));
    }

    /**
     * GET /api/g2g-lms/course-builder/assessments/{id}/questions
     *
     * The paper's OWN questions, with their options and which is correct —
     * the authoring view. The learner-facing quiz path (not yet built in
     * this package) must never select `correct_answer`.
     */
    public function paperQuestions(Request $request, $id)
    {
        $context = $this->lmsContext($request);
        $paper = $this->findPaper($id, $context['sub_institute_id']);

        if (! $paper) {
            return $this->lmsError('Assessment not found', 404);
        }

        $ids = $this->paperQuestionIds($paper);

        if ($ids === []) {
            return $this->lmsOk([], 'Success', 200, ['total_marks' => 0]);
        }

        $questions = DB::table('lms_question_master')
            ->whereIn('id', $ids)
            ->whereNull('deleted_at')
            ->get(['id', 'question_title', 'description', 'points', 'hint_text'])
            ->keyBy('id');

        $options = DB::table('answer_master')
            ->whereIn('question_id', $ids)
            ->orderBy('id')
            ->get(['id', 'question_id', 'answer', 'correct_answer'])
            ->groupBy('question_id');

        // The paper's own order, not the database's — an author who sequenced
        // their questions meant it.
        $ordered = [];

        foreach ($ids as $questionId) {
            $question = $questions[$questionId] ?? null;
            if (! $question) {
                continue;
            }

            $ordered[] = [
                'id' => (int) $question->id,
                'question_title' => $question->question_title,
                'description' => $question->description,
                'points' => (int) ($question->points ?: 1),
                'hint_text' => $question->hint_text,
                'options' => ($options[$questionId] ?? collect())->map(fn ($o) => [
                    'id' => (int) $o->id,
                    'answer' => $o->answer,
                    'correct' => (bool) $o->correct_answer,
                ])->values(),
            ];
        }

        return $this->lmsOk($ordered, 'Success', 200, [
            'total_marks' => (int) DB::table('lms_question_master')
                ->whereIn('id', $ids)->whereNull('deleted_at')->sum('points'),
        ]);
    }

    /** POST /api/g2g-lms/course-builder/assessments/{id}/questions */
    public function storeQuestion(Request $request, $id)
    {
        $context = $this->lmsContext($request);
        $paper = $this->findPaper($id, $context['sub_institute_id']);

        if (! $paper) {
            return $this->lmsError('Assessment not found', 404);
        }

        $validator = Validator::make($request->all(), $this->questionRules());
        if ($validator->fails()) {
            return $this->lmsError($validator->messages()->first(), 422);
        }

        if ($refusal = $this->rejectUnmarkableOptions($request)) {
            return $refusal;
        }

        $questionId = DB::transaction(function () use ($request, $paper, $context, $id) {
            $now = now();

            $questionId = DB::table('lms_question_master')->insertGetId([
                // 1 = 'multiple' in question_type_master, the only type this
                // installation defines.
                'question_type_id' => 1,
                'subject_id' => (int) $paper->subject_id,
                'standard_id' => $paper->standard_id,
                'question_title' => $request->input('question_title'),
                'description' => $request->input('description'),
                'points' => (int) $request->input('points', 1),
                'hint_text' => $request->input('hint_text'),
                'multiple_answer' => $this->correctCount($request) > 1 ? 1 : 0,
                'sub_institute_id' => $context['sub_institute_id'],
                'status' => 1,
                'created_by' => $context['user_id'],
                'created_on' => $now,
            ]);

            $this->writeOptions($request, $questionId, $context['sub_institute_id'], $context['user_id']);

            // Appended, so an author adding a question does not reorder the
            // ones already there.
            $this->syncPaperTotals($id, [...$this->paperQuestionIds($paper), $questionId]);

            return $questionId;
        });

        return $this->lmsOk(['id' => $questionId], 'Question added', 201);
    }

    /** PUT /api/g2g-lms/course-builder/assessments/{id}/questions/{questionId} */
    public function updateQuestion(Request $request, $id, $questionId)
    {
        $context = $this->lmsContext($request);
        $paper = $this->findPaper($id, $context['sub_institute_id']);

        if (! $paper) {
            return $this->lmsError('Assessment not found', 404);
        }

        $question = DB::table('lms_question_master')
            ->where('id', $questionId)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->first();

        if (! $question || ! in_array((int) $questionId, $this->paperQuestionIds($paper), true)) {
            return $this->lmsError('Question not found', 404);
        }

        $validator = Validator::make($request->all(), $this->questionRules());
        if ($validator->fails()) {
            return $this->lmsError($validator->messages()->first(), 422);
        }

        if ($refusal = $this->rejectUnmarkableOptions($request)) {
            return $refusal;
        }

        DB::transaction(function () use ($request, $id, $questionId, $context, $paper) {
            DB::table('lms_question_master')->where('id', $questionId)->update([
                'question_title' => $request->input('question_title'),
                'description' => $request->input('description'),
                'points' => (int) $request->input('points', 1),
                'hint_text' => $request->input('hint_text'),
                'multiple_answer' => $this->correctCount($request) > 1 ? 1 : 0,
            ]);

            // Options are replaced wholesale rather than diffed — hard-deleted,
            // since nothing in this package can reference an option row yet
            // (see this section's docblock).
            if ($request->has('options')) {
                DB::table('answer_master')->where('question_id', $questionId)->delete();
                $this->writeOptions($request, (int) $questionId, $context['sub_institute_id'], $context['user_id']);
            }

            // Points may have changed, so the paper's total marks must follow.
            $this->syncPaperTotals($id, $this->paperQuestionIds($paper));
        });

        return $this->lmsOk(null, 'Question updated');
    }

    /** DELETE /api/g2g-lms/course-builder/assessments/{id}/questions/{questionId} */
    public function destroyQuestion(Request $request, $id, $questionId)
    {
        $context = $this->lmsContext($request);
        $paper = $this->findPaper($id, $context['sub_institute_id']);

        if (! $paper) {
            return $this->lmsError('Assessment not found', 404);
        }

        $ids = $this->paperQuestionIds($paper);

        if (! in_array((int) $questionId, $ids, true)) {
            return $this->lmsError('Question not found', 404);
        }

        DB::transaction(function () use ($questionId, $ids, $context, $id) {
            // No deleted_by column on this table (unlike hp_erp's copy).
            DB::table('lms_question_master')
                ->where('id', $questionId)
                ->where('sub_institute_id', $context['sub_institute_id'])
                ->update(['deleted_at' => now()]);

            $this->syncPaperTotals(
                $id,
                array_values(array_filter($ids, fn ($x) => $x !== (int) $questionId))
            );
        });

        return $this->lmsOk(null, 'Question removed');
    }

    /**
     * POST /api/g2g-lms/course-builder/assessments/{id}/questions/generate
     *
     * Write MCQ questions for this quiz from the course's own modules and
     * lessons, via `DeepSeekAssessmentService` (already used by
     * `AiAssessmentController`). Adapted from hp_erp's `CourseQuizGenerator`:
     * that service also cites the capability each question tests, sourced
     * from `course_competency_map` — a table this package does not have yet
     * (see the competency-mapping panel's own note), so the capability
     * citation is dropped here rather than faked. Generated questions are
     * APPENDED, never replacing what an author already wrote.
     */
    public function generateQuestions(Request $request, $id, DeepSeekAssessmentService $ai)
    {
        $context = $this->lmsContext($request);
        $paper = $this->findPaper($id, $context['sub_institute_id']);

        if (! $paper) {
            return $this->lmsError('Assessment not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'count' => 'nullable|integer|min:1|max:20',
            'formats' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            return $this->lmsError($validator->messages()->first(), 422);
        }

        if (! $ai->isConfigured()) {
            return $this->lmsError(
                'AI question generation is not configured. Set DEEPSEEK_API_KEY.',
                503
            );
        }

        $count = (int) ($request->input('count') ?: 5);

        $modules = DB::table('chapter_master as c')
            ->leftJoin('content_master as m', function ($join) {
                $join->on('m.chapter_id', '=', 'c.id')->where('m.show_hide', 1);
            })
            ->where('c.subject_id', $paper->subject_id)
            ->where('c.show_hide', 1)
            ->orderBy('c.sort_order')
            ->get(['c.id as chapter_id', 'c.chapter_name', 'm.title as lesson_title', 'm.description as lesson_description'])
            ->groupBy('chapter_id')
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'chapter_id' => (int) $first->chapter_id,
                    'chapter_name' => $first->chapter_name,
                    'lessons' => $rows->filter(fn ($r) => $r->lesson_title)
                        ->map(fn ($r) => ['title' => $r->lesson_title, 'description' => $r->lesson_description])
                        ->values(),
                ];
            })
            ->values();

        if ($modules->isEmpty()) {
            return $this->lmsError(
                'This course has no modules or lessons yet, so there is nothing to write questions about. '
                . 'Add content in step 2 first.',
                422
            );
        }

        $course = DB::table('sub_std_map')->where('id', $paper->subject_id)->value('display_name');
        $content = json_encode($modules->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        try {
            $raw = $ai->chatJson([
                ['role' => 'system', 'content' => 'You write assessment questions for workplace training courses. '
                    . 'You are given a course\'s own teaching material and you write multiple-choice questions '
                    . 'that test whether someone who studied that material understood it. '
                    . 'You reply with a single valid JSON object.'],
                ['role' => 'user', 'content' => <<<PROMPT
                    You are writing the quiz for the course "{$course}".

                    OUTPUT
                    Reply with one JSON object of this shape:
                    {
                      "questions": [
                        {
                          "chapter_id": 45,
                          "question_text": "...",
                          "options": ["...", "...", "...", "..."],
                          "correct_option": "the exact text of the right option",
                          "points": 1
                        }
                      ]
                    }

                    RULES
                    - Write exactly {$count} question(s).
                    - Base every question on the COURSE CONTENT below. Do not test anything the course does not cover.
                    - Carry the "chapter_id" of the module the question comes from.
                    - Every question needs 3 to 4 "options" and a "correct_option" repeating one option's text EXACTLY.
                    - No option may exceed 240 characters.
                    - Ask about applying the material, not about recalling its wording.
                    - "points" reflects how much work the question is. Whole numbers, 1-5.

                    COURSE CONTENT (JSON array of modules, each with its lessons)
                    {$content}

                    Write the {$count} question(s) now and reply with that JSON object.
                    PROMPT],
            ]);
        } catch (\Throwable $e) {
            return $this->lmsError('The questions could not be generated: ' . $e->getMessage(), 502);
        }

        $validChapters = $modules->pluck('chapter_id')->flip();
        $firstChapter = $modules->first()['chapter_id'] ?? null;
        $accepted = [];

        foreach ((array) ($raw['questions'] ?? []) as $q) {
            $text = trim((string) ($q['question_text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $options = is_array($q['options'] ?? null)
                ? array_values(array_filter(array_map(fn ($o) => trim((string) $o), $q['options']), fn ($o) => $o !== ''))
                : [];
            $correct = isset($q['correct_option']) ? trim((string) $q['correct_option']) : null;

            // An MCQ whose key is not among its own options cannot be marked
            // by comparison, and storing it unscorable is worse than losing it.
            if (count($options) < 2 || $correct === null || ! in_array($correct, $options, true)) {
                continue;
            }
            if (max(array_map('mb_strlen', $options)) > 240) {
                continue;
            }

            $chapterId = (int) ($q['chapter_id'] ?? 0);

            $accepted[] = [
                'chapter_id' => $validChapters->has($chapterId) ? $chapterId : $firstChapter,
                'question_title' => $text,
                'options' => $options,
                'correct_option' => $correct,
                'points' => max(1, min(100, (int) ($q['points'] ?? 1))),
            ];
        }

        if ($accepted === []) {
            return $this->lmsError('The generator returned no usable questions.', 502);
        }

        $created = DB::transaction(function () use ($accepted, $paper, $context, $id) {
            $newIds = [];

            foreach ($accepted as $q) {
                $questionId = DB::table('lms_question_master')->insertGetId([
                    'question_type_id' => 1,
                    'subject_id' => (int) $paper->subject_id,
                    'standard_id' => $paper->standard_id,
                    'chapter_id' => $q['chapter_id'],
                    'question_title' => $q['question_title'],
                    'points' => $q['points'],
                    'multiple_answer' => 0,
                    'sub_institute_id' => $context['sub_institute_id'],
                    'status' => 1,
                    'created_by' => $context['user_id'],
                    'created_on' => now(),
                ]);

                DB::table('answer_master')->insert(array_map(fn ($option) => [
                    'question_id' => $questionId,
                    'answer' => $option,
                    'correct_answer' => $option === $q['correct_option'] ? 1 : 0,
                    'sub_institute_id' => $context['sub_institute_id'],
                    'created_by' => $context['user_id'],
                    'created_on' => now(),
                ], $q['options']));

                $newIds[] = $questionId;
            }

            $this->syncPaperTotals($id, [...$this->paperQuestionIds($paper), ...$newIds]);

            return count($newIds);
        });

        $dropped = count((array) ($raw['questions'] ?? [])) - $created;

        return $this->lmsOk([
            'created' => $created,
            'dropped' => max(0, $dropped),
        ], $dropped > 0
            ? "Wrote {$created} question(s); {$dropped} could not be used."
            : "Wrote {$created} question(s).");
    }

    /* ================================================================== *
     * Competencies this course develops (course_competency_map)
     *
     * Ported from hp_erp's `Api\Competency\CourseCompetencyMapController`.
     * `lms_course_competency_effectiveness` (the measured-vs-declared
     * comparison hp_erp's `index()` LEFT joins) does not exist in this
     * package, so `achieved_level`/`mean_percent`/etc. are omitted — this
     * only serves the declared mapping the Course Builder panel needs.
     * ================================================================== */

    /** GET /api/g2g-lms/course-builder/courses/{courseId}/competencies */
    public function courseCompetencies(Request $request, $courseId)
    {
        $context = $this->lmsContext($request);

        $rows = DB::table('course_competency_map as m')
            ->join('competency as c', 'c.id', '=', 'm.competency_id')
            ->where('m.sub_institute_id', $context['sub_institute_id'])
            ->where('m.course_id', $courseId)
            ->orderByDesc('m.is_primary')
            ->orderBy('c.name')
            ->get(['m.id', 'm.competency_id', 'm.proficiency_level', 'm.is_primary', 'c.name as competency_name', 'c.code as competency_code']);

        return $this->lmsOk($rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'competency_id' => (int) $r->competency_id,
            'competency_name' => $r->competency_name,
            'competency_code' => $r->competency_code,
            'proficiency_level' => $r->proficiency_level === null ? null : (int) $r->proficiency_level,
            'is_primary' => (bool) $r->is_primary,
        ])->values());
    }

    /**
     * POST /api/g2g-lms/course-builder/courses/{courseId}/competencies
     *
     * SYNC, not append — rows absent from `items` are deleted for this
     * course, matching the reference exactly (a competency dropped from a
     * course's list must stop being recommended for it).
     */
    public function syncCourseCompetencies(Request $request, $courseId)
    {
        $context = $this->lmsContext($request);

        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.competency_id' => 'required|integer',
            'items.*.proficiency_level' => 'nullable|integer|min:1|max:5',
            'items.*.is_primary' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return $this->lmsError($validator->messages()->first(), 422);
        }

        $course = DB::table('sub_std_map')
            ->where('id', $courseId)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->exists();
        if (! $course) {
            return $this->lmsError('Course not found', 404);
        }

        $seen = [];
        foreach ($request->input('items') as $i => $item) {
            $cid = (int) $item['competency_id'];
            if (isset($seen[$cid])) {
                return $this->lmsError('Item ' . ($i + 1) . ' repeats a competency already in this list.', 422);
            }
            $seen[$cid] = true;
        }

        $valid = DB::table('competency')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->whereIn('id', array_keys($seen))
            ->pluck('id')->all();
        $unknown = array_diff(array_keys($seen), $valid);
        if ($unknown) {
            return $this->lmsError('These competencies do not exist in this organisation: ' . implode(', ', $unknown), 422);
        }

        $result = DB::transaction(function () use ($request, $context, $courseId, $seen) {
            $removed = DB::table('course_competency_map')
                ->where('sub_institute_id', $context['sub_institute_id'])
                ->where('course_id', $courseId)
                ->whereNotIn('competency_id', array_keys($seen))
                ->delete();

            $n = 0;
            foreach ($request->input('items') as $item) {
                DB::table('course_competency_map')->updateOrInsert(
                    [
                        'sub_institute_id' => $context['sub_institute_id'],
                        'course_id' => $courseId,
                        'competency_id' => (int) $item['competency_id'],
                    ],
                    [
                        'proficiency_level' => isset($item['proficiency_level']) ? (int) $item['proficiency_level'] : null,
                        'is_primary' => ! empty($item['is_primary']),
                        'updated_at' => now(),
                    ]
                );
                $n++;
            }

            return ['written' => $n, 'removed' => $removed];
        });

        return $this->lmsOk(['course_id' => (int) $courseId] + $result, 'Course competencies saved.', 201);
    }

    /** DELETE /api/g2g-lms/course-builder/competencies/{id} — drop one mapping row. */
    public function destroyCourseCompetency(Request $request, $id)
    {
        $context = $this->lmsContext($request);

        $deleted = DB::table('course_competency_map')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->where('id', $id)
            ->delete();

        return $this->lmsOk(['removed' => (bool) $deleted], $deleted ? 'Mapping removed.' : 'No mapping to remove.');
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
