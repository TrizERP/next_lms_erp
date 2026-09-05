<?php

namespace App\Http\Controllers\G2gLms;

use App\Http\Controllers\Controller;
use App\Http\Controllers\G2gLms\Concerns\ResolvesLmsIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * My Learning — G2G LMS migration (Package 1).
 *
 * Ported from hp_erp's `App\Http\Controllers\Api\LmsLearningController` (the
 * course player: chapters + content + progress, notes, chapter/content
 * authoring, certificates, discussions, completion).
 *
 * ── SCHEMA ADAPTATIONS FROM hp_erp (see Package 1 report) ───────────────────
 *   - `content_master`, `chapter_master`, `question_paper` have no
 *     `deleted_at` in this schema, so `whereNull('deleted_at')` against those
 *     three tables is dropped (adaptation, not a behaviour change: rows there
 *     are never soft-deleted in this schema).
 *   - `lms_online_exam` here has `student_id`, not `employee_id`
 *     (hp_erp's column name), and likewise has no `deleted_at`.
 *   - `lms_certificates`, `lms_course_settings`, `lms_course_prerequisites`
 *     are created by Package 3's own migrations
 *     (2026_09_05_220000/220100/220200_create_lms_*), with the identical
 *     column shape hp_erp has after its own follow-up ALTERs — ported here
 *     with no adaptation.
 */
class MyLearningController extends Controller
{
    use ResolvesLmsIdentity;

    private const AUTHORING_PROFILES = ['admin', 'hr'];

    private function guardAuthoring(Request $request)
    {
        return $this->guardLmsProfile($request, self::AUTHORING_PROFILES, 'Your profile is not permitted to edit course content.');
    }

    /* ------------------------------------------------------------------ *
     * Course list / player.
     * ------------------------------------------------------------------ */

    /** GET /g2g-lms/my-learning/courses */
    public function courses(Request $request)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $userId = $this->contextUserId($request);
        $subInstituteId = $this->lmsTenantId($request);

        if (!$userId) {
            return response()->json(['status' => false, 'message' => 'user_id is required'], 422);
        }

        try {
            $latest = DB::table('lms_course_enroll')
                ->select('course_id', DB::raw('MAX(created_at) as latest_enrolled_at'))
                ->where('user_id', $userId)
                ->whereNull('deleted_at')
                ->groupBy('course_id');

            $courses = DB::table('lms_course_enroll as e')
                ->join('sub_std_map as s', 'e.course_id', '=', 's.id')
                ->leftJoin('hrms_departments as d', 'd.id', '=', 's.standard_id')
                ->joinSub($latest, 'latest', function ($join) {
                    $join->on('e.course_id', '=', 'latest.course_id')
                         ->on('e.created_at', '=', 'latest.latest_enrolled_at');
                })
                ->where('e.user_id', $userId)
                ->where('s.sub_institute_id', $subInstituteId)
                ->where('e.sub_institute_id', $subInstituteId)
                ->whereNull('e.deleted_at')
                ->whereNull('s.deleted_at')
                ->select(
                    's.id',
                    's.display_name',
                    's.display_image',
                    's.subject_category',
                    's.subject_type',
                    's.standard_id',
                    'd.department as standard_name',
                    'e.id as enrollment_id',
                    'e.status as enrollment_status',
                    'e.start_date',
                    'e.end_date'
                )
                ->get();

            $courseIds = $courses->pluck('id')->all();

            // content_master has no deleted_at in this schema.
            $totals = empty($courseIds) ? collect() : DB::table('content_master')
                ->whereIn('subject_id', $courseIds)
                ->select('subject_id', DB::raw('COUNT(*) as total'))
                ->groupBy('subject_id')
                ->pluck('total', 'subject_id');

            $completed = empty($courseIds) ? collect() : DB::table('lms_content_progress')
                ->where('user_id', $userId)
                ->where('status', 'completed')
                ->whereNull('deleted_at')
                ->whereIn('course_id', $courseIds)
                ->select('course_id', DB::raw('COUNT(*) as done'))
                ->groupBy('course_id')
                ->pluck('done', 'course_id');

            $courses->transform(function ($course) use ($totals, $completed) {
                $total = (int) ($totals[$course->id] ?? 0);
                $done = (int) ($completed[$course->id] ?? 0);

                $course->total_content = $total;
                $course->completed_content = $done;
                $course->progress_percent = $total > 0 ? (int) round($done / $total * 100) : 0;

                return $course;
            });

            $awaiting = $courses->where('enrollment_status', 'pending')->values();
            $learnable = $courses->whereNotIn('enrollment_status', ['pending'])->values();

            return response()->json([
                'status' => true,
                'data' => $learnable,
                'awaiting_approval' => $awaiting,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to load your courses',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /** GET /g2g-lms/my-learning/courses/{courseId} */
    public function course(Request $request, $courseId)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $userId = $this->contextUserId($request);
        $subInstituteId = $this->lmsTenantId($request);

        if (!$userId) {
            return response()->json(['status' => false, 'message' => 'user_id is required'], 422);
        }

        try {
            $course = DB::table('sub_std_map as s')
                ->leftJoin('hrms_departments as d', 'd.id', '=', 's.standard_id')
                ->where('s.id', $courseId)
                ->when($subInstituteId, fn ($q) => $q->where('s.sub_institute_id', $subInstituteId))
                ->whereNull('s.deleted_at')
                ->select('s.*', 'd.department as standard_name')
                ->first();

            if (!$course) {
                return response()->json(['status' => false, 'message' => 'Course not found'], 404);
            }

            $enrollment = DB::table('lms_course_enroll')
                ->where('user_id', $userId)
                ->where('course_id', $courseId)
                ->whereNull('deleted_at')
                ->orderByDesc('created_at')
                ->first();

            // chapter_master / content_master have no deleted_at in this schema.
            $chapters = DB::table('chapter_master')
                ->where('subject_id', $courseId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'chapter_name', 'chapter_desc', 'standard_id', 'subject_id', 'sort_order', 'show_hide']);

            $content = DB::table('content_master')
                ->where('subject_id', $courseId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get([
                    'id', 'chapter_id', 'title', 'description', 'filename', 'file_type',
                    'file_size', 'url', 'content_category', 'sort_order', 'show_hide',
                ]);

            $progress = DB::table('lms_content_progress')
                ->where('user_id', $userId)
                ->where('course_id', $courseId)
                ->whereNull('deleted_at')
                ->get()
                ->keyBy('content_id');

            $sequential = (bool) DB::table('lms_course_settings')
                ->where('course_id', $courseId)
                ->where('sub_institute_id', $subInstituteId)
                ->value('sequential_unlock');

            $byChapter = [];
            $previousComplete = true;

            foreach ($content as $item) {
                $row = $progress[$item->id] ?? null;

                $item->status = $row->status ?? 'not-started';
                $item->last_position_seconds = $row->last_position_seconds ?? null;
                $item->time_spent_seconds = (int) ($row->time_spent_seconds ?? 0);
                $item->completed_at = $row->completed_at ?? null;

                $item->is_locked = $sequential
                    && !$previousComplete
                    && $item->status === 'not-started';
                $previousComplete = $item->status === 'completed';

                $byChapter[$item->chapter_id][] = $item;
            }

            $totalContent = $content->count();
            $completedContent = $content->filter(fn ($i) => $i->status === 'completed')->count();

            $chapters->transform(function ($chapter) use ($byChapter) {
                $items = $byChapter[$chapter->id] ?? [];
                $chapter->content = array_values($items);
                $chapter->total_content = count($items);
                $chapter->completed_content = count(
                    array_filter($items, fn ($i) => $i->status === 'completed')
                );
                return $chapter;
            });

            return response()->json([
                'status' => true,
                'data' => [
                    'course' => $course,
                    'enrollment' => $enrollment,
                    'chapters' => $chapters,
                    'total_content' => $totalContent,
                    'completed_content' => $completedContent,
                    'progress_percent' => $totalContent > 0
                        ? (int) round($completedContent / $totalContent * 100)
                        : 0,
                    'time_spent_seconds' => (int) $progress->sum('time_spent_seconds'),
                    'content_categories' => $content->pluck('content_category')
                        ->filter()->unique()->values(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to load the course',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /** POST /g2g-lms/my-learning/progress */
    public function saveProgress(Request $request)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $userId = $this->contextUserId($request);

        $validator = Validator::make(
            array_merge($request->all(), ['user_id' => $userId]),
            [
                'user_id'               => 'required|integer',
                'course_id'             => 'required|integer',
                'content_id'            => 'required|integer',
                'chapter_id'            => 'nullable|integer',
                'status'                => 'required|in:not-started,in-progress,completed',
                'last_position_seconds' => 'nullable|integer|min:0',
                'time_spent_delta'      => 'nullable|integer|min:0|max:86400',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->messages()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        // content_master has no deleted_at in this schema.
        $belongs = DB::table('content_master')
            ->where('id', $request->content_id)
            ->where('subject_id', $request->course_id)
            ->exists();

        if (!$belongs) {
            return response()->json([
                'status' => false,
                'message' => 'That content item does not belong to this course.',
            ], 422);
        }

        try {
            $existing = DB::table('lms_content_progress')
                ->where('user_id', $userId)
                ->where('content_id', $request->content_id)
                ->first();

            $status = $request->status;
            if ($existing && $existing->status === 'completed' && $status === 'in-progress') {
                $status = 'completed';
            }

            $payload = [
                'status' => $status,
                'chapter_id' => $request->chapter_id,
                'last_position_seconds' => $request->last_position_seconds,
                'time_spent_seconds' => (int) ($existing->time_spent_seconds ?? 0)
                    + (int) $request->input('time_spent_delta', 0),
                'completed_at' => $status === 'completed'
                    ? ($existing->completed_at ?? now())
                    : null,
                'sub_institute_id' => $this->lmsTenantId($request),
                'updated_by' => $userId,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('lms_content_progress')->where('id', $existing->id)->update($payload);
            } else {
                DB::table('lms_content_progress')->insert($payload + [
                    'user_id' => $userId,
                    'course_id' => $request->course_id,
                    'content_id' => $request->content_id,
                    'created_by' => $userId,
                    'created_at' => now(),
                ]);
            }

            $total = DB::table('content_master')
                ->where('subject_id', $request->course_id)
                ->count();

            $done = DB::table('lms_content_progress')
                ->where('user_id', $userId)
                ->where('course_id', $request->course_id)
                ->where('status', 'completed')
                ->whereNull('deleted_at')
                ->count();

            $percent = $total > 0 ? (int) round($done / $total * 100) : 0;

            DB::table('lms_course_enroll')
                ->where('user_id', $userId)
                ->where('course_id', $request->course_id)
                ->where('status', 'enrolled')
                ->whereNull('deleted_at')
                ->update(['status' => 'in-progress', 'updated_at' => now()]);

            DB::table('lms_assignments')
                ->where('user_id', $userId)
                ->where('course_id', $request->course_id)
                ->whereNull('deleted_at')
                ->update([
                    'progress' => $percent,
                    'status' => $percent >= 100 ? 'Completed' : 'In Progress',
                    'updated_at' => now(),
                ]);

            return response()->json([
                'status' => true,
                'message' => 'Progress saved',
                'data' => [
                    'content_id' => (int) $request->content_id,
                    'status' => $status,
                    'total_content' => $total,
                    'completed_content' => $done,
                    'progress_percent' => $percent,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to save progress',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /** GET /g2g-lms/my-learning/assessments */
    public function assessments(Request $request)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $userId = $this->contextUserId($request);
        $courseId = $request->input('course_id');

        if (!$userId || !$courseId) {
            return response()->json(['status' => false, 'message' => 'user_id and course_id are required'], 422);
        }

        try {
            // question_paper has no deleted_at in this schema.
            $papers = DB::table('question_paper')
                ->where('subject_id', $courseId)
                ->orderBy('id')
                ->get([
                    'id', 'paper_name', 'paper_desc', 'total_ques', 'total_marks',
                    'time_allowed', 'timelimit_enable', 'attempt_allowed',
                    'open_date', 'close_date', 'exam_type', 'show_hide',
                ]);

            $paperIds = $papers->pluck('id')->all();

            // lms_online_exam here keys attempts on `student_id`, not
            // hp_erp's `employee_id`, and has no deleted_at.
            $attempts = empty($paperIds) ? collect() : DB::table('lms_online_exam')
                ->where('student_id', $userId)
                ->whereIn('question_paper_id', $paperIds)
                ->orderByDesc('created_at')
                ->get(['id', 'question_paper_id', 'total_right', 'total_wrong', 'obtain_marks', 'start_time', 'created_at'])
                ->groupBy('question_paper_id');

            $papers->transform(function ($paper) use ($attempts) {
                $mine = $attempts[$paper->id] ?? collect();

                $paper->attempts = $mine->values();
                $paper->attempt_count = $mine->count();
                $paper->best_score = $mine->max('obtain_marks');
                $paper->last_attempt_at = $mine->first()->created_at ?? null;
                $paper->status = $mine->isEmpty() ? 'not-started' : 'completed';

                return $paper;
            });

            return response()->json(['status' => true, 'data' => $papers]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to load assessments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /* ------------------------------------------------------------------ *
     * Notes.
     * ------------------------------------------------------------------ */

    /** GET /g2g-lms/my-learning/notes */
    public function notes(Request $request)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $userId = $this->contextUserId($request);
        if (!$userId) {
            return response()->json(['status' => false, 'message' => 'user_id is required'], 422);
        }

        $notes = DB::table('lms_content_notes as n')
            ->leftJoin('content_master as c', 'c.id', '=', 'n.content_id')
            ->where('n.user_id', $userId)
            ->when($request->input('course_id'), fn ($q, $id) => $q->where('n.course_id', $id))
            ->whereNull('n.deleted_at')
            ->orderByDesc('n.id')
            ->get([
                'n.id', 'n.course_id', 'n.chapter_id', 'n.content_id', 'n.note',
                'n.timestamp_seconds', 'n.created_at', 'n.updated_at',
                'c.title as content_title',
            ]);

        return response()->json(['status' => true, 'data' => $notes]);
    }

    /** POST /g2g-lms/my-learning/notes */
    public function storeNote(Request $request)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $userId = $this->contextUserId($request);

        $validator = Validator::make(
            array_merge($request->all(), ['user_id' => $userId]),
            [
                'user_id'           => 'required|integer',
                'course_id'         => 'required|integer',
                'chapter_id'        => 'nullable|integer',
                'content_id'        => 'nullable|integer',
                'note'              => 'required|string|max:5000',
                'timestamp_seconds' => 'nullable|integer|min:0',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->messages()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $id = DB::table('lms_content_notes')->insertGetId([
            'user_id' => $userId,
            'course_id' => $request->course_id,
            'chapter_id' => $request->chapter_id,
            'content_id' => $request->content_id,
            'note' => $request->note,
            'timestamp_seconds' => $request->timestamp_seconds,
            'sub_institute_id' => $this->lmsTenantId($request),
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Note saved',
            'data' => DB::table('lms_content_notes')->find($id),
        ], 201);
    }

    /** PUT /g2g-lms/my-learning/notes/{id} */
    public function updateNote(Request $request, $id)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $userId = $this->contextUserId($request);

        $validator = Validator::make(
            array_merge($request->all(), ['user_id' => $userId]),
            [
                'user_id' => 'required|integer',
                'note'    => 'required|string|max:5000',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->messages()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $note = DB::table('lms_content_notes')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->first();

        if (!$note) {
            return response()->json(['status' => false, 'message' => 'Note not found'], 404);
        }

        DB::table('lms_content_notes')->where('id', $id)->update([
            'note' => $request->note,
            'timestamp_seconds' => $request->input('timestamp_seconds', $note->timestamp_seconds),
            'updated_by' => $userId,
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Note updated',
            'data' => DB::table('lms_content_notes')->find($id),
        ]);
    }

    /** DELETE /g2g-lms/my-learning/notes/{id} */
    public function destroyNote(Request $request, $id)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $userId = $this->contextUserId($request);
        if (!$userId) {
            return response()->json(['status' => false, 'message' => 'user_id is required'], 422);
        }

        $note = DB::table('lms_content_notes')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->first();

        if (!$note) {
            return response()->json(['status' => false, 'message' => 'Note not found'], 404);
        }

        DB::table('lms_content_notes')->where('id', $id)->update([
            'deleted_at' => now(),
            'deleted_by' => $userId,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Note deleted',
            'data' => ['id' => (int) $id],
        ]);
    }

    /* ------------------------------------------------------------------ *
     * Chapter / content authoring (admin + HR).
     * ------------------------------------------------------------------ */

    /** POST /g2g-lms/my-learning/chapters */
    public function storeChapter(Request $request)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }
        if ($roleError = $this->guardAuthoring($request)) {
            return $roleError;
        }

        $subInstituteId = $this->lmsTenantId($request);

        $validator = Validator::make(
            array_merge($request->all(), ['sub_institute_id' => $subInstituteId]),
            [
                'sub_institute_id' => 'required|integer',
                'subject_id'       => 'required|integer',
                'chapter_name'     => 'required|string|max:191',
                'chapter_desc'     => 'nullable|string',
                'sort_order'       => 'nullable|integer',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->messages()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $course = DB::table('sub_std_map')
            ->where('id', $request->subject_id)
            ->where('sub_institute_id', $subInstituteId)
            ->whereNull('deleted_at')
            ->first();

        if (!$course) {
            return response()->json(['status' => false, 'message' => 'Course not found'], 404);
        }

        $id = DB::table('chapter_master')->insertGetId([
            'subject_id' => $request->subject_id,
            'standard_id' => $course->standard_id,
            'chapter_name' => $request->chapter_name,
            'chapter_desc' => $request->chapter_desc,
            'sort_order' => $request->input('sort_order', 1),
            'show_hide' => 1,
            'sub_institute_id' => $subInstituteId,
            'created_by' => $this->contextUserId($request),
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Chapter created',
            'data' => DB::table('chapter_master')->find($id),
        ], 201);
    }

    /** PUT /g2g-lms/my-learning/chapters/{id} */
    public function updateChapter(Request $request, $id)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }
        if ($roleError = $this->guardAuthoring($request)) {
            return $roleError;
        }

        $subInstituteId = $this->lmsTenantId($request);

        $validator = Validator::make(
            array_merge($request->all(), ['sub_institute_id' => $subInstituteId]),
            [
                'sub_institute_id' => 'required|integer',
                'chapter_name'     => 'required|string|max:191',
                'chapter_desc'     => 'nullable|string',
                'sort_order'       => 'nullable|integer',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->messages()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        // chapter_master has no deleted_at in this schema.
        $chapter = DB::table('chapter_master')
            ->where('id', $id)
            ->where('sub_institute_id', $subInstituteId)
            ->first();

        if (!$chapter) {
            return response()->json(['status' => false, 'message' => 'Chapter not found'], 404);
        }

        DB::table('chapter_master')->where('id', $id)->update([
            'chapter_name' => $request->chapter_name,
            'chapter_desc' => $request->chapter_desc,
            'sort_order' => $request->input('sort_order', $chapter->sort_order),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Chapter updated',
            'data' => DB::table('chapter_master')->find($id),
        ]);
    }

    /** DELETE /g2g-lms/my-learning/chapters/{id} */
    public function destroyChapter(Request $request, $id)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }
        if ($roleError = $this->guardAuthoring($request)) {
            return $roleError;
        }

        $subInstituteId = $this->lmsTenantId($request);

        $chapter = DB::table('chapter_master')
            ->where('id', $id)
            ->where('sub_institute_id', $subInstituteId)
            ->first();

        if (!$chapter) {
            return response()->json(['status' => false, 'message' => 'Chapter not found'], 404);
        }

        // Neither chapter_master nor content_master has deleted_at in this
        // schema, so a chapter delete here is a hard delete of the chapter
        // row (its content rows are re-pointed to no chapter rather than
        // deleted, since content_master.chapter_id is nullable and there is
        // no soft-delete column to stamp).
        DB::table('content_master')->where('chapter_id', $id)->update(['chapter_id' => null]);
        DB::table('chapter_master')->where('id', $id)->delete();

        return response()->json([
            'status' => true,
            'message' => 'Chapter deleted',
            'data' => ['id' => (int) $id],
        ]);
    }

    /** POST /g2g-lms/my-learning/content */
    public function storeContent(Request $request)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }
        if ($roleError = $this->guardAuthoring($request)) {
            return $roleError;
        }

        $subInstituteId = $this->lmsTenantId($request);

        $validator = Validator::make(
            array_merge($request->all(), ['sub_institute_id' => $subInstituteId]),
            [
                'sub_institute_id' => 'required|integer',
                'chapter_id'       => 'required|integer',
                'title'            => 'required|string|max:191',
                'description'      => 'nullable|string',
                'filename'         => 'nullable|string',
                'url'              => 'nullable|string|max:1000',
                'file_type'        => 'nullable|string|max:191',
                'content_category' => 'nullable|string|max:191',
                'sort_order'       => 'nullable|integer',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->messages()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $chapter = DB::table('chapter_master')
            ->where('id', $request->chapter_id)
            ->where('sub_institute_id', $subInstituteId)
            ->first();

        if (!$chapter) {
            return response()->json(['status' => false, 'message' => 'Chapter not found'], 404);
        }

        $id = DB::table('content_master')->insertGetId([
            'chapter_id' => $chapter->id,
            'subject_id' => $chapter->subject_id,
            'standard_id' => $chapter->standard_id,
            'title' => $request->title,
            'description' => $request->description,
            'filename' => $request->input('filename') ?: $request->input('url'),
            'url' => $request->input('url'),
            'file_type' => $request->file_type,
            'content_category' => $request->input('content_category', 'Videos'),
            'sort_order' => $request->input('sort_order', 1),
            'show_hide' => 1,
            'sub_institute_id' => $subInstituteId,
            'created_by' => $this->contextUserId($request),
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Content created',
            'data' => DB::table('content_master')->find($id),
        ], 201);
    }

    /** PUT /g2g-lms/my-learning/content/{id} */
    public function updateContent(Request $request, $id)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }
        if ($roleError = $this->guardAuthoring($request)) {
            return $roleError;
        }

        $subInstituteId = $this->lmsTenantId($request);

        $validator = Validator::make(
            array_merge($request->all(), ['sub_institute_id' => $subInstituteId]),
            [
                'sub_institute_id' => 'required|integer',
                'title'            => 'required|string|max:191',
                'description'      => 'nullable|string',
                'filename'         => 'nullable|string',
                'url'              => 'nullable|string|max:1000',
                'file_type'        => 'nullable|string|max:191',
                'content_category' => 'nullable|string|max:191',
                'sort_order'       => 'nullable|integer',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->messages()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $content = DB::table('content_master')
            ->where('id', $id)
            ->where('sub_institute_id', $subInstituteId)
            ->first();

        if (!$content) {
            return response()->json(['status' => false, 'message' => 'Content not found'], 404);
        }

        DB::table('content_master')->where('id', $id)->update([
            'title' => $request->title,
            'description' => $request->description,
            'filename' => $request->input('filename')
                ?: $request->input('url')
                ?: $content->filename,
            'url' => $request->input('url', $content->url),
            'file_type' => $request->input('file_type', $content->file_type),
            'content_category' => $request->input('content_category', $content->content_category),
            'sort_order' => $request->input('sort_order', $content->sort_order),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Content updated',
            'data' => DB::table('content_master')->find($id),
        ]);
    }

    /** DELETE /g2g-lms/my-learning/content/{id} */
    public function destroyContent(Request $request, $id)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }
        if ($roleError = $this->guardAuthoring($request)) {
            return $roleError;
        }

        $subInstituteId = $this->lmsTenantId($request);

        $content = DB::table('content_master')
            ->where('id', $id)
            ->where('sub_institute_id', $subInstituteId)
            ->first();

        if (!$content) {
            return response()->json(['status' => false, 'message' => 'Content not found'], 404);
        }

        // content_master has no deleted_at in this schema - hard delete.
        DB::table('content_master')->where('id', $id)->delete();

        return response()->json([
            'status' => true,
            'message' => 'Content deleted',
            'data' => ['id' => (int) $id],
        ]);
    }

    /* ------------------------------------------------------------------ *
     * Certificates.
     * ------------------------------------------------------------------ */

    private function resolveCourseSkillId($course): ?int
    {
        $entityCategories = ['task', 'jobrole', 'course', 'sub'];
        $category = strtolower(trim((string) ($course->subject_category ?? '')));

        if ($category === '' || in_array($category, $entityCategories, true)) {
            return null;
        }

        if (!$course->subject_id) {
            return null;
        }

        $exists = DB::table('s_users_skills')
            ->where('id', $course->subject_id)
            ->whereNull('deleted_at')
            ->exists();

        return $exists ? (int) $course->subject_id : null;
    }

    private function certificateWarningDays(): int
    {
        return (int) config('lms.certificate_warning_days', env('LMS_CERT_EXPIRY_WARNING_DAYS', 90));
    }

    /** GET /g2g-lms/my-learning/certificates */
    public function certificates(Request $request)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $userId = $this->contextUserId($request);
        if (!$userId) {
            return response()->json(['status' => false, 'message' => 'user_id is required'], 422);
        }

        $subInstituteId = $this->lmsTenantId($request);
        $wantsAll = $request->input('scope') === 'all' && $this->guardAuthoring($request) === null;

        $now = now();
        $soon = $now->copy()->addDays($this->certificateWarningDays());

        $certificates = DB::table('lms_certificates as c')
            ->leftJoin('sub_std_map as s', 's.id', '=', 'c.course_id')
            ->leftJoin('s_users_skills as k', 'k.id', '=', 'c.skill_id')
            ->leftJoin('tbluser as u', 'u.id', '=', 'c.user_id')
            ->when(
                $wantsAll,
                fn ($q) => $q->where('c.sub_institute_id', $subInstituteId),
                fn ($q) => $q->where('c.user_id', $userId)
            )
            ->when($request->input('course_id'), fn ($q, $id) => $q->where('c.course_id', $id))
            ->when($request->input('search'), function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('c.course_title', 'like', "%{$search}%")
                          ->orWhere('c.certificate_number', 'like', "%{$search}%")
                          ->orWhere('u.first_name', 'like', "%{$search}%")
                          ->orWhere('u.last_name', 'like', "%{$search}%")
                          ->orWhere('u.employee_no', 'like', "%{$search}%");
                });
            })
            ->whereNull('c.deleted_at')
            ->orderByDesc('c.issued_at')
            ->get([
                'c.id', 'c.user_id', 'c.course_id', 'c.skill_id', 'c.certificate_number',
                'c.course_title', 'c.issued_at', 'c.expires_at', 'c.status',
                'c.name', 'c.description', 'c.tags', 'c.verification_code',
                'c.supersedes', 'c.superseded_by', 'c.reissued_at',
                's.display_image', 's.subject_category',
                'k.title as skill_title',
                'u.employee_no',
                DB::raw("TRIM(CONCAT_WS(' ', u.first_name, u.last_name)) as learner_name"),
            ])
            ->map(function ($certificate) use ($now, $soon) {
                if (!$certificate->expires_at) {
                    $certificate->expiry_state = 'active';
                    $certificate->days_to_expiry = null;
                } else {
                    $expiresAt = \Carbon\Carbon::parse($certificate->expires_at);
                    $certificate->days_to_expiry = (int) $now->diffInDays($expiresAt, false);

                    if ($expiresAt < $now) {
                        $certificate->expiry_state = 'expired';
                    } elseif ($expiresAt <= $soon) {
                        $certificate->expiry_state = 'expiring';
                    } else {
                        $certificate->expiry_state = 'active';
                    }
                }

                $decoded = $certificate->tags ? json_decode($certificate->tags, true) : null;
                $certificate->tags = is_array($decoded) ? array_values($decoded) : null;

                return $certificate;
            });

        return response()->json([
            'status' => true,
            'data' => $certificates,
            'meta' => [
                'scope' => $wantsAll ? 'all' : 'mine',
                'warning_days' => $this->certificateWarningDays(),
            ],
        ]);
    }

    /** POST /g2g-lms/my-learning/certificates */
    public function issueCertificate(Request $request)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $userId = $this->contextUserId($request);
        $subInstituteId = $this->lmsTenantId($request);

        $validator = Validator::make(
            array_merge($request->all(), ['user_id' => $userId]),
            [
                'user_id'   => 'required|integer',
                'course_id' => 'required|integer',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->messages()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $existing = DB::table('lms_certificates')
            ->where('user_id', $userId)
            ->where('course_id', $request->course_id)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            return response()->json([
                'status' => true,
                'message' => 'Certificate already issued',
                'data' => $existing,
            ]);
        }

        $course = DB::table('sub_std_map')
            ->where('id', $request->course_id)
            ->whereNull('deleted_at')
            ->first();

        if (!$course) {
            return response()->json(['status' => false, 'message' => 'Course not found'], 404);
        }

        // content_master has no deleted_at in this schema.
        $total = DB::table('content_master')
            ->where('subject_id', $request->course_id)
            ->count();

        $done = DB::table('lms_content_progress')
            ->where('user_id', $userId)
            ->where('course_id', $request->course_id)
            ->where('status', 'completed')
            ->whereNull('deleted_at')
            ->count();

        if ($total === 0 || $done < $total) {
            return response()->json([
                'status' => false,
                'message' => 'Finish every lesson in this course before claiming the certificate.',
                'data' => ['total_content' => $total, 'completed_content' => $done],
            ], 422);
        }

        $enrollment = DB::table('lms_course_enroll')
            ->where('user_id', $userId)
            ->where('course_id', $request->course_id)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->first();

        try {
            $now = now();
            $certificateId = DB::table('lms_certificates')->insertGetId([
                'user_id' => $userId,
                'course_id' => $request->course_id,
                'enrollment_id' => $enrollment->id ?? null,
                'skill_id' => $this->resolveCourseSkillId($course),
                'certificate_number' => 'CERT-' . $now->format('Y') . '-'
                    . str_pad((string) $request->course_id, 5, '0', STR_PAD_LEFT) . '-'
                    . str_pad((string) $userId, 5, '0', STR_PAD_LEFT),
                'verification_code' => $this->makeVerificationCode(),
                'course_title' => $course->display_name,
                'name' => $course->display_name,
                'description' => "Awarded on successful completion of {$course->display_name}.",
                'tags' => json_encode(array_values(array_filter([
                    $course->subject_category,
                    $course->subject_type,
                ]))),
                'issued_at' => $now,
                'expires_at' => $course->certificate_validity_months
                    ? $now->copy()->addMonths((int) $course->certificate_validity_months)
                    : null,
                'status' => 'active',
                'sub_institute_id' => $subInstituteId,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($enrollment && $enrollment->status !== 'completed') {
                DB::table('lms_course_enroll')->where('id', $enrollment->id)->update([
                    'status' => 'completed',
                    'updated_at' => $now,
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Certificate issued',
                'data' => DB::table('lms_certificates')->find($certificateId),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to issue the certificate',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /** GET /g2g-lms/my-learning/certificates/{id}/download */
    public function downloadCertificate(Request $request, $id)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $userId = $this->contextUserId($request);
        $subInstituteId = $this->lmsTenantId($request);
        $isAdmin = $this->guardAuthoring($request) === null;

        $certificate = DB::table('lms_certificates as c')
            ->leftJoin('tbluser as u', 'u.id', '=', 'c.user_id')
            ->where('c.id', $id)
            ->when(
                $isAdmin,
                fn ($q) => $q->where('c.sub_institute_id', $subInstituteId),
                fn ($q) => $q->where('c.user_id', $userId)
            )
            ->whereNull('c.deleted_at')
            ->first([
                'c.*',
                DB::raw("TRIM(CONCAT_WS(' ', u.first_name, u.last_name)) as learner_name"),
            ]);

        if (!$certificate) {
            return response()->json(['status' => false, 'message' => 'Certificate not found'], 404);
        }

        return $this->renderCertificatePdf($certificate);
    }

    /** POST /g2g-lms/my-learning/courses/{courseId}/complete */
    public function completeCourse(Request $request, $courseId)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $userId = $this->contextUserId($request);
        $subInstituteId = $this->lmsTenantId($request);

        $enrolment = DB::table('lms_course_enroll as e')
            ->join('sub_std_map as s', 's.id', '=', 'e.course_id')
            ->where('e.user_id', $userId)
            ->where('e.course_id', $courseId)
            ->where('s.sub_institute_id', $subInstituteId)
            ->whereNull('e.deleted_at')
            ->whereIn('e.status', ['enrolled', 'in-progress', 'completed'])
            ->orderByDesc('e.created_at')
            ->select('e.id', 'e.status')
            ->first();

        if (! $enrolment) {
            return response()->json(['status' => false, 'message' => 'You are not enrolled in this course.'], 404);
        }

        $total = DB::table('content_master')
            ->where('subject_id', $courseId)
            ->count();

        $done = DB::table('lms_content_progress')
            ->where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('status', 'completed')
            ->whereNull('deleted_at')
            ->count();

        DB::transaction(function () use ($enrolment, $userId, $courseId, $total, $done) {
            DB::table('lms_course_enroll')->where('id', $enrolment->id)->update([
                'status' => 'completed',
                'end_date' => now()->toDateString(),
                'updated_at' => now(),
            ]);

            DB::table('lms_assignments')
                ->where('user_id', $userId)
                ->where('course_id', $courseId)
                ->whereNull('deleted_at')
                ->update([
                    'status' => 'Completed',
                    'progress' => $total > 0 ? (int) round($done / $total * 100) : 0,
                    'updated_at' => now(),
                ]);
        });

        return response()->json([
            'status' => true,
            'message' => 'Marked complete.',
            'data' => [
                'marked_complete' => true,
                'total_content' => $total,
                'completed_content' => $done,
                'progress_percent' => $total > 0 ? (int) round($done / $total * 100) : 0,
                'certificate_available' => $total > 0 && $done >= $total,
            ],
        ]);
    }

    /** GET /g2g-lms/my-learning/certificates/verify/{code} */
    public function verifyCertificate(Request $request, string $code)
    {
        $certificate = DB::table('lms_certificates as c')
            ->leftJoin('tbluser as u', 'u.id', '=', 'c.user_id')
            ->where('c.verification_code', $code)
            ->whereNull('c.deleted_at')
            ->first([
                'c.certificate_number', 'c.name', 'c.course_title', 'c.issued_at',
                'c.expires_at', 'c.status', 'c.superseded_by',
                DB::raw("TRIM(CONCAT_WS(' ', u.first_name, u.last_name)) as learner_name"),
            ]);

        if (!$certificate) {
            return response()->json([
                'status' => false,
                'valid' => false,
                'message' => 'No certificate matches that verification code.',
            ], 404);
        }

        $expired = $certificate->expires_at !== null
            && \Carbon\Carbon::parse($certificate->expires_at)->isPast();
        $superseded = $certificate->superseded_by !== null;

        if ($superseded) {
            $message = 'This certificate is genuine but has been superseded by a newer issue.';
        } elseif ($expired) {
            $message = 'This certificate is genuine but expired on '
                . \Carbon\Carbon::parse($certificate->expires_at)->format('d M Y') . '.';
        } else {
            $message = 'This is a valid, current certificate.';
        }

        return response()->json([
            'status' => true,
            'valid' => !$expired && !$superseded,
            'message' => $message,
            'data' => [
                'certificate_number' => $certificate->certificate_number,
                'name' => $certificate->name ?? $certificate->course_title,
                'course_title' => $certificate->course_title,
                'learner_name' => $certificate->learner_name,
                'issued_at' => $certificate->issued_at,
                'expires_at' => $certificate->expires_at,
                'is_expired' => $expired,
                'is_superseded' => $certificate->superseded_by !== null,
            ],
        ]);
    }

    /** POST /g2g-lms/my-learning/certificates/{id}/reissue */
    public function reissueCertificate(Request $request, $id)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }
        if ($roleError = $this->guardAuthoring($request)) {
            return $roleError;
        }

        $subInstituteId = $this->lmsTenantId($request);

        $original = DB::table('lms_certificates')
            ->where('id', $id)
            ->where('sub_institute_id', $subInstituteId)
            ->whereNull('deleted_at')
            ->first();

        if (!$original) {
            return response()->json(['status' => false, 'message' => 'Certificate not found'], 404);
        }

        if ($original->superseded_by !== null) {
            return response()->json([
                'status' => false,
                'message' => 'That certificate has already been re-issued.',
            ], 422);
        }

        $course = DB::table('sub_std_map')->where('id', $original->course_id)->first();
        $now = now();

        try {
            $newId = DB::table('lms_certificates')->insertGetId([
                'user_id' => $original->user_id,
                'course_id' => $original->course_id,
                'enrollment_id' => $original->enrollment_id,
                'skill_id' => $original->skill_id,
                'certificate_number' => $original->certificate_number . '-R'
                    . (DB::table('lms_certificates')
                        ->whereNotNull('supersedes')
                        ->where('course_id', $original->course_id)
                        ->where('user_id', $original->user_id)
                        ->count() + 1),
                'verification_code' => $this->makeVerificationCode(),
                'name' => $original->name,
                'description' => $original->description,
                'tags' => $original->tags,
                'course_title' => $original->course_title,
                'issued_at' => $now,
                'expires_at' => $course && $course->certificate_validity_months
                    ? $now->copy()->addMonths((int) $course->certificate_validity_months)
                    : null,
                'status' => 'active',
                'supersedes' => $original->id,
                'reissued_at' => $now,
                'reissued_by' => $this->contextUserId($request),
                'sub_institute_id' => $subInstituteId,
                'created_by' => $this->contextUserId($request),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('lms_certificates')->where('id', $original->id)->update([
                'superseded_by' => $newId,
                'status' => 'superseded',
                'updated_by' => $this->contextUserId($request),
                'updated_at' => $now,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Certificate re-issued',
                'data' => DB::table('lms_certificates')->find($newId),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to re-issue the certificate',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function makeVerificationCode(): string
    {
        do {
            $code = strtoupper(bin2hex(random_bytes(8)));
        } while (DB::table('lms_certificates')->where('verification_code', $code)->exists());

        return $code;
    }

    private function renderCertificatePdf($certificate)
    {
        $tags = [];
        if (!empty($certificate->tags)) {
            $decoded = json_decode($certificate->tags, true);
            $tags = is_array($decoded) ? $decoded : [];
        }

        $templateKey = DB::table('lms_course_settings')
            ->where('course_id', $certificate->course_id)
            ->value('certificate_template');

        $templates = collect(config('lms.certificate_templates', []));
        $template = $templates->firstWhere('value', $templateKey) ?? $templates->first();

        $view = $template['view'] ?? 'lms.certificate';
        if (!view()->exists($view)) {
            $view = 'lms.certificate';
        }

        $orientation = ($template['value'] ?? 'standard') === 'compliance' ? 'portrait' : 'landscape';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($view, [
            'certificate' => $certificate,
            'learnerName' => $certificate->learner_name ?? '',
            'tags' => $tags,
            'isExpired' => $certificate->expires_at !== null
                && \Carbon\Carbon::parse($certificate->expires_at)->isPast(),
            'verifyUrl' => $certificate->verification_code
                ? rtrim(config('app.url'), '/') . '/verify/certificate/' . $certificate->verification_code
                : null,
        ])->setPaper('a4', $orientation);

        $output = $pdf->output();
        $headerAt = strpos($output, '%PDF');
        if ($headerAt !== false && $headerAt > 0) {
            $output = substr($output, $headerAt);
        }

        return response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $certificate->certificate_number . '.pdf"',
            'Content-Length' => (string) strlen($output),
        ]);
    }

    /* ------------------------------------------------------------------ *
     * Discussions.
     * ------------------------------------------------------------------ */

    private function isInstructor(Request $request): bool
    {
        $identity = $this->lmsIdentity($request);

        if (!is_array($identity)) {
            return false;
        }

        return $this->lmsRoleMatches($identity['user'], self::AUTHORING_PROFILES);
    }

    /** GET /g2g-lms/my-learning/discussions */
    public function discussions(Request $request)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $courseId = $request->input('course_id');
        if (!$courseId) {
            return response()->json(['status' => false, 'message' => 'course_id is required'], 422);
        }

        $threads = DB::table('lms_course_discussions as d')
            ->leftJoin('tbluser as u', 'u.id', '=', 'd.user_id')
            ->leftJoin('content_master as c', 'c.id', '=', 'd.content_id')
            ->where('d.course_id', $courseId)
            ->whereNull('d.deleted_at')
            ->orderByDesc('d.created_at')
            ->get([
                'd.id', 'd.course_id', 'd.chapter_id', 'd.content_id', 'd.user_id',
                'd.title', 'd.message', 'd.is_instructor', 'd.is_resolved', 'd.created_at',
                DB::raw("TRIM(CONCAT_WS(' ', u.first_name, u.last_name)) as author_name"),
                'c.title as content_title',
            ]);

        $replies = $threads->isEmpty() ? collect() : DB::table('lms_course_discussion_replies as r')
            ->leftJoin('tbluser as u', 'u.id', '=', 'r.user_id')
            ->whereIn('r.discussion_id', $threads->pluck('id'))
            ->whereNull('r.deleted_at')
            ->orderBy('r.created_at')
            ->get([
                'r.id', 'r.discussion_id', 'r.user_id', 'r.message', 'r.is_instructor', 'r.created_at',
                DB::raw("TRIM(CONCAT_WS(' ', u.first_name, u.last_name)) as author_name"),
            ])
            ->groupBy('discussion_id');

        $threads->transform(function ($thread) use ($replies) {
            $thread->replies = ($replies[$thread->id] ?? collect())->values();
            $thread->reply_count = $thread->replies->count();
            $thread->is_instructor = (bool) $thread->is_instructor;
            $thread->is_resolved = (bool) $thread->is_resolved;
            return $thread;
        });

        return response()->json(['status' => true, 'data' => $threads]);
    }

    /** POST /g2g-lms/my-learning/discussions */
    public function storeDiscussion(Request $request)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $userId = $this->contextUserId($request);

        $validator = Validator::make(
            array_merge($request->all(), ['user_id' => $userId]),
            [
                'user_id'    => 'required|integer',
                'course_id'  => 'required|integer',
                'chapter_id' => 'nullable|integer',
                'content_id' => 'nullable|integer',
                'title'      => 'nullable|string|max:191',
                'message'    => 'required|string|max:5000',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->messages()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $id = DB::table('lms_course_discussions')->insertGetId([
            'course_id' => $request->course_id,
            'chapter_id' => $request->chapter_id,
            'content_id' => $request->content_id,
            'user_id' => $userId,
            'title' => $request->title,
            'message' => $request->message,
            'is_instructor' => $this->isInstructor($request),
            'sub_institute_id' => $this->lmsTenantId($request),
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Posted',
            'data' => DB::table('lms_course_discussions')->find($id),
        ], 201);
    }

    /** POST /g2g-lms/my-learning/discussions/{id}/replies */
    public function replyToDiscussion(Request $request, $id)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $userId = $this->contextUserId($request);

        $validator = Validator::make(
            array_merge($request->all(), ['user_id' => $userId]),
            [
                'user_id' => 'required|integer',
                'message' => 'required|string|max:5000',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->messages()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $thread = DB::table('lms_course_discussions')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$thread) {
            return response()->json(['status' => false, 'message' => 'Discussion not found'], 404);
        }

        $replyId = DB::table('lms_course_discussion_replies')->insertGetId([
            'discussion_id' => $id,
            'user_id' => $userId,
            'message' => $request->message,
            'is_instructor' => $this->isInstructor($request),
            'sub_institute_id' => $this->lmsTenantId($request),
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Reply posted',
            'data' => DB::table('lms_course_discussion_replies')->find($replyId),
        ], 201);
    }

    /** DELETE /g2g-lms/my-learning/discussions/{id} */
    public function destroyDiscussion(Request $request, $id)
    {
        if ($tokenError = $this->guardLmsToken($request)) {
            return $tokenError;
        }

        $userId = $this->contextUserId($request);

        $thread = DB::table('lms_course_discussions')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$thread) {
            return response()->json(['status' => false, 'message' => 'Discussion not found'], 404);
        }

        if ((int) $thread->user_id !== (int) $userId && !$this->isInstructor($request)) {
            return response()->json([
                'status' => false,
                'message' => 'You can only delete your own posts.',
            ], 403);
        }

        $now = now();
        DB::table('lms_course_discussions')->where('id', $id)
            ->update(['deleted_at' => $now, 'deleted_by' => $userId]);
        DB::table('lms_course_discussion_replies')->where('discussion_id', $id)->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'deleted_by' => $userId]);

        return response()->json([
            'status' => true,
            'message' => 'Discussion deleted',
            'data' => ['id' => (int) $id],
        ]);
    }
}
