<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use App\Jobs\EvaluateHomeworkSubmissionJob;
use App\Jobs\EvaluateHomeworkSubmissionV2Job;
use App\Services\Evaluation\AnswerSheetScoringService;
use App\Models\student\studentHomeworkModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Multi-file submission + teacher-review workflow for homework, rebuilt
 * directly on the existing `homework` table (no more homework_submissions /
 * homework_submission_files tables -- see the 2026_09_11 migration for the
 * columns this now reads/writes: status, submission_files, teacher_remarks,
 * reviewed_by, reviewed_at, feedback_published, ai_*, reviewed_pdf_path,
 * evaluated_at).
 *
 * `studentHomeworkModel`'s existing single-row-per-(homework, student)
 * shape means the old "submission id" concept collapses onto the homework
 * row's own `id` -- every {id} route param below IS the homework row id.
 * Because there is now only one mutable row per student per homework, there
 * is nowhere to keep a submission history: `previous_attempts` is always an
 * empty array and only one submission can exist at a time (overwritable
 * while not yet under review/reviewed), matching the legacy
 * submission_image/completion_status overwrite pattern already used
 * elsewhere on this same table.
 *
 * File-id design: since there is no longer a per-file table with a real
 * primary key, each file in the `submission_files` JSON array is given a
 * synthesized, stable id of the form "{homeworkId}_{index}" (index = its
 * 0-based position in that row's own files array). downloadFile($id) parses
 * that composite id back apart with explode('_', ...). This was chosen over
 * an `?index=` query param because it keeps the same single-path-parameter
 * shape the frontend already calls (`POST lms-homework/submission-file/{id}`)
 * without requiring it to separately track a homework id per file.
 *
 * Response shapes intentionally mirror what the previous (deleted-table)
 * version of this controller produced, so the already-built frontend at
 * /lms/homework/[id] and /lms/homework/review/** keeps working unchanged.
 *
 * All responses use the { status_code, message, data } envelope where
 * status_code = 1 success, 0 failure.
 */
class HomeworkSubmissionApiController extends Controller
{
    // ==================================================================
    // Shared helpers
    // ==================================================================

    private function fail(string $message, int $status = 422, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'status_code' => 0,
            'message' => $message,
        ], $extra), $status);
    }

    /**
     * The student whose homework submissions this request is allowed to
     * touch. For a student caller the user_id/student_id in the body is
     * ignored outright -- otherwise one student could submit against
     * another student's homework row just by editing the request.
     */
    private function submissionStudentId(Request $request)
    {
        if ($this->isStudentSession()) {
            return session()->get('user_id');
        }

        return $request->input('user_id') ?? $request->input('student_id');
    }

    /** True when the verified token belongs to a student. */
    private function isStudentSession(): bool
    {
        return (bool) session()->get('is_student')
            || strtolower(trim((string) session()->get('user_profile_name'))) === 'student';
    }

    /** Decode this row's submission_files JSON into a plain array, tolerant of null/garbled data. */
    private function decodeFiles($homework): array
    {
        if (empty($homework->submission_files)) {
            return [];
        }

        if (is_array($homework->submission_files)) {
            return $homework->submission_files;
        }

        $decoded = json_decode($homework->submission_files, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** Build the files[] response shape (synthesized composite id, *_url) for one homework row. */
    private function buildFilesPayload($homework, string $server): array
    {
        $files = $this->decodeFiles($homework);
        $out = [];

        foreach ($files as $index => $file) {
            $path = $file['path'] ?? '';
            $out[] = [
                'id' => $homework->id . '_' . $index,
                'file_path' => $path,
                'file_url' => $path !== '' ? $server . '/storage/' . ltrim($path, '/') : '',
                'original_name' => $file['original_name'] ?? '',
                'mime_type' => $file['mime_type'] ?? '',
                'file_size' => $file['file_size'] ?? null,
            ];
        }

        return $out;
    }

    /** Build the single submissions[] entry for a homework row that has been submitted at least once. */
    private function buildSubmissionEntry($homework, string $server): array
    {
        return [
            'id' => $homework->id,
            'status' => $homework->status,
            'submission_remarks' => $homework->submission_remarks,
            'teacher_remarks' => $homework->teacher_remarks,
            'ai_status' => $homework->ai_status,
            'ai_failure_reason' => $homework->ai_failure_reason,
            'ai_score' => $homework->ai_score,
            'ai_total_questions' => $homework->ai_total_questions,
            'ai_percentage' => $homework->ai_percentage,
            // Marks, as opposed to the count-based `ai_score` above. Null on
            // submissions evaluated before the 2026_09_22 migration, which the
            // frontend falls back from.
            'ai_marks' => $homework->ai_marks === null ? null : (float) $homework->ai_marks,
            'teacher_marks' => $homework->teacher_marks === null ? null : (float) $homework->teacher_marks,
            'max_marks' => $homework->max_marks === null ? null : (float) $homework->max_marks,
            'evaluation_mode' => $homework->evaluation_mode,
            'reviewed_pdf_path' => $homework->reviewed_pdf_path,
            'evaluated_at' => optional($homework->evaluated_at)->toDateTimeString(),
            'submitted_at' => $homework->submission_date,
            'submitted_at_fmt' => $homework->submission_date
                ? date('d-m-Y', strtotime($homework->submission_date))
                : null,
            'feedback_published' => (bool) $homework->feedback_published,
            'files' => $this->buildFilesPayload($homework, $server),
        ];
    }

    // ==================================================================
    // Detail / status
    // ==================================================================

    public function detail(Request $request, $id): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');

        $homework = studentHomeworkModel::where('id', $id)
            ->when($sub_institute_id, fn ($q) => $q->where('sub_institute_id', $sub_institute_id))
            ->when($syear, fn ($q) => $q->where('syear', $syear))
            ->first();

        if (!$homework) {
            return $this->fail('Homework not found', 404);
        }

        if ($this->isStudentSession()) {
            $student_id = $this->submissionStudentId($request);
            if ((int) $homework->student_id !== (int) $student_id) {
                return $this->fail('You are not allowed to view this homework.', 403);
            }
        }

        $server = $request->getSchemeAndHttpHost();

        // Question-carrying homework - picked out of the question bank, or taken
        // whole from a homework exam paper - keeps its questions as a
        // comma-separated `question_ids` list on the `homework` row (see
        // StudentHomeworkApiController::store()) alongside the generated
        // reference PDF. Attachment-sourced homework keeps getting an empty array.
        $sourceType = $homework->source_type ?? 'attachment';
        $questions = [];
        if (in_array($sourceType, ['question_bank', 'exam_paper'], true) && !empty($homework->question_ids)) {
            $questionIds = array_values(array_filter(array_map(
                'intval',
                explode(',', $homework->question_ids)
            )));

            if (!empty($questionIds)) {
                $questions = \App\Models\lms\lmsQuestionMasterModel::whereIn('id', $questionIds)
                    ->get(['id', 'question_title', 'description', 'question_type_id', 'points'])
                    ->toArray();
            }
        }

        $teacher = DB::table('tbluser')->where('id', $homework->created_by)->first(['first_name', 'middle_name', 'last_name']);
        $teacherName = $teacher ? trim("{$teacher->first_name} {$teacher->middle_name} {$teacher->last_name}") : '';

        $standardName = $homework->standard_id ? DB::table('standard')->where('id', $homework->standard_id)->value('name') : null;
        $divisionName = $homework->division_id ? DB::table('division')->where('id', $homework->division_id)->value('name') : null;
        $subjectName = $homework->subject_id ? DB::table('subject')->where('id', $homework->subject_id)->value('subject_name') : null;

        $submissions = ($homework->completion_status === 'Y' || !empty($homework->status))
            ? [$this->buildSubmissionEntry($homework, $server)]
            : [];

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => [
                'homework' => [
                    'id' => $homework->id,
                    'title' => $homework->title,
                    'description' => $homework->description,
                    'subject_id' => $homework->subject_id,
                    'subject_name' => $subjectName,
                    'standard_id' => $homework->standard_id,
                    'standard_name' => $standardName,
                    'division_id' => $homework->division_id,
                    'division_name' => $divisionName,
                    'date' => $homework->date,
                    'submission_date' => $homework->submission_date,
                    'created_by' => $homework->created_by,
                    'teacher_name' => $teacherName,
                    'reference_file_url' => !empty($homework->image)
                        ? $server . '/storage/student/' . $homework->image
                        : '',
                    'sourceType' => $sourceType,
                    'questions' => $questions,
                ],
                'submissions' => $submissions,
            ],
        ], 200);
    }

    public function submissionAiStatus(Request $request, $id): JsonResponse
    {
        $homework = studentHomeworkModel::find($id);

        if (!$homework) {
            return $this->fail('Submission not found', 404);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => [
                'ai_status' => $homework->ai_status,
                'ai_failure_reason' => $homework->ai_failure_reason,
                'ai_score' => $homework->ai_score,
                'ai_total_questions' => $homework->ai_total_questions,
                'ai_percentage' => $homework->ai_percentage,
                'reviewed_pdf_path' => $homework->reviewed_pdf_path,
                'teacher_remarks' => $homework->teacher_remarks,
                'evaluated_at' => optional($homework->evaluated_at)->toDateTimeString(),
                'status' => $homework->status,
            ],
        ], 200);
    }

    // ==================================================================
    // Student submission
    // ==================================================================

    public function submit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'homework_id' => 'required|numeric',
            'sub_institute_id' => 'nullable|numeric',
            'syear' => 'nullable|numeric',
            'remarks' => 'nullable|string',
            'files' => 'required|array|min:1|max:5',
            'files.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if ($validator->fails()) {
            return $this->fail('Validation failed', 422, ['errors' => $validator->errors()]);
        }

        $homework_id = (int) $request->input('homework_id');
        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');
        $remarks = $request->input('remarks');
        $student_id = $this->submissionStudentId($request);

        if (!$student_id) {
            return $this->fail('user_id (student) is required');
        }

        $homework = studentHomeworkModel::where('id', $homework_id)
            ->when($sub_institute_id, fn ($q) => $q->where('sub_institute_id', $sub_institute_id))
            ->when($syear, fn ($q) => $q->where('syear', $syear))
            ->first();

        if (!$homework) {
            return $this->fail('Homework not found', 404);
        }

        if ((int) $homework->student_id !== (int) $student_id) {
            return $this->fail('You are not allowed to submit against this homework.', 403);
        }

        if (in_array($homework->status, ['Under Review', 'Reviewed'], true)) {
            return $this->fail('This homework has already been reviewed / is under review and cannot be resubmitted.');
        }

        $files = $request->file('files');
        $directory = "homework_submissions/{$homework_id}";

        $submissionFiles = [];
        foreach ($files as $index => $file) {
            $ext = File::extension($file->getClientOriginalName());
            $file_name = "sub_{$homework_id}_" . time() . "_{$index}." . $ext;
            $file->storeAs('public/' . $directory, $file_name);

            $submissionFiles[] = [
                'path' => $directory . '/' . $file_name,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ];
        }

        $homework->update([
            'submission_files' => $submissionFiles,
            'submission_remarks' => $remarks,
            'status' => 'Submitted',
            'completion_status' => 'Y',
            'ai_status' => 'Checking',
            'ai_failure_reason' => null,
        ]);

        // AI evaluation is best-effort and must never break the upload
        // response: dispatch() re-throws job exceptions synchronously when
        // QUEUE_CONNECTION=sync, so this call itself needs a guard even
        // though the job's own handle() already catches its internal
        // OCR/Gemini/PDF failures.
        try {
            EvaluateHomeworkSubmissionV2Job::dispatch($homework_id, (int) $sub_institute_id, (int) $syear);
        } catch (\Throwable $exception) {
            Log::error('AI evaluation failed for homework submission (v2)', [
                'homework_id' => $homework_id,
                'message' => $exception->getMessage(),
            ]);
            try {
                studentHomeworkModel::where('id', $homework_id)->update([
                    'ai_status' => 'Failed',
                    'ai_failure_reason' => mb_substr($exception->getMessage(), 0, 250),
                ]);
            } catch (\Throwable $updateException) {
                // The submission itself is already saved.
            }
        }

        $server = $request->getSchemeAndHttpHost();
        $homework->refresh();

        return response()->json([
            'status_code' => 1,
            'message' => 'Homework submitted successfully',
            'data' => [
                'submission' => $this->buildSubmissionEntry($homework, $server),
            ],
        ], 201);
    }

    // ==================================================================
    // Staff review
    // ==================================================================

    public function reviewList(Request $request): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');
        $status = $request->input('status');

        $query = DB::table('homework as h')
            ->join('tblstudent as ts', function ($join) {
                $join->whereRaw('ts.id = h.student_id');
            })
            ->leftJoin('standard as s', function ($join) {
                $join->whereRaw('s.id = h.standard_id');
            })
            ->leftJoin('division as d', function ($join) {
                $join->whereRaw('d.id = h.division_id');
            })
            ->leftJoin('subject as sub', function ($join) {
                $join->whereRaw('sub.id = h.subject_id');
            })
            ->leftJoin('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw('se.student_id = h.student_id AND se.syear = h.syear AND se.end_date IS NULL');
            })
            ->selectRaw("h.id as submission_id, h.id as homework_id, h.student_id,
                CONCAT_WS(' ', ts.first_name, ts.middle_name, ts.last_name) AS student_name,
                se.roll_no, s.name AS standard, d.name AS division, sub.subject_name AS subject,
                h.title, h.status, h.ai_status, h.ai_score, h.ai_percentage,
                h.submission_date AS submitted_at,
                DATE_FORMAT(h.submission_date, '%d-%m-%Y') AS submitted_at_fmt")
            ->where(function ($q) {
                $q->where('h.completion_status', 'Y')
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('h.status')->where('h.status', '!=', 'Pending');
                    });
            })
            ->when($sub_institute_id, fn ($q) => $q->where('h.sub_institute_id', $sub_institute_id))
            ->when($syear, fn ($q) => $q->where('h.syear', $syear))
            ->when($status, fn ($q) => $q->where('h.status', $status));

        $data = $query->orderBy('h.id', 'DESC')->get()->toArray();

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => $data,
        ], 200);
    }

    public function reviewDetail(Request $request, $id): JsonResponse
    {
        $homework = studentHomeworkModel::find($id);

        if (!$homework) {
            return $this->fail('Submission not found', 404);
        }

        $server = $request->getSchemeAndHttpHost();
        $submission = $this->buildSubmissionEntry($homework, $server);

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => [
                'homework' => $homework,
                'submission' => $submission,
                'files' => $submission['files'],
                // Per-question marks are staff-only: they carry the teacher's
                // in-progress figures, which are nobody else's business until
                // the review is published.
                'answers' => $this->answersFor((int) $homework->id),
                // No separate table means no attempt history can be kept --
                // always empty, but the key stays so the frontend's existing
                // render logic (which expects it to exist) doesn't break.
                'previous_attempts' => [],
            ],
        ], 200);
    }

    public function reviewStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'submission_id' => 'required|numeric',
            'teacher_remarks' => 'nullable|string',
            'status' => 'required|string|in:Under Review,Reviewed,Rejected',
            'publish' => 'nullable|boolean',
            'marks' => 'nullable|array',
            'marks.*.question_no' => 'required_with:marks|numeric',
        ]);

        if ($validator->fails()) {
            return $this->fail('Validation failed', 422, ['errors' => $validator->errors()]);
        }

        // submission_id kept as the accepted param name for frontend
        // compatibility -- it is now just the homework row id.
        $homework = studentHomeworkModel::find($request->input('submission_id'));
        if (!$homework) {
            return $this->fail('Submission not found', 404);
        }

        $reviewed_by = $request->input('user_id') ?? $request->input('teacher_id');
        $status = (string) $request->input('status');

        $this->applyMarks((int) $homework->id, $request->input('marks'));

        // 'Reviewed' is this module's word for approved, and approving is what
        // turns proposals into marks: every question the teacher did not touch
        // takes the AI's figure at that moment, so the total stops moving even
        // if the evaluation is re-run later. The same rule the Exam Evaluation
        // review screen follows.
        if ($status === 'Reviewed') {
            DB::table('homework_evaluation_answer')
                ->where('homework_id', $homework->id)
                ->whereNull('teacher_marks')
                ->update(['teacher_marks' => DB::raw('COALESCE(ai_marks, 0)'), 'updated_at' => now()]);
        }

        $homework->update([
            'teacher_remarks' => $request->input('teacher_remarks'),
            'status' => $status,
            'reviewed_by' => $reviewed_by,
            'reviewed_at' => now(),
            'feedback_published' => $request->boolean('publish'),
            'teacher_marks' => $this->teacherTotal((int) $homework->id),
        ]);

        $server = $request->getSchemeAndHttpHost();
        $homework->refresh();

        return response()->json([
            'status_code' => 1,
            'message' => 'Submission reviewed successfully',
            'data' => $this->buildSubmissionEntry($homework, $server),
        ], 200);
    }

    /**
     * Re-runs the evaluation on a submission that has already been marked.
     *
     * For a scan that came out unreadable, or a homework whose questions were
     * corrected after the fact. Refused once the review is signed off, because
     * re-running would replace marks a teacher has already stood behind --
     * move it back to 'Under Review' first.
     */
    public function reviewReprocess(Request $request, $id): JsonResponse
    {
        $homework = studentHomeworkModel::where('id', $id)
            ->when($request->input('sub_institute_id'), fn ($q, $v) => $q->where('sub_institute_id', $v))
            ->first();

        if (!$homework) {
            return $this->fail('Submission not found', 404);
        }

        // Two submission shapes reach this screen: the v2 flow stores its
        // uploads in `submission_files`, the legacy single-file flow in
        // `submission_image`. Re-running has to pick the job that matches the
        // shape, or it reads the wrong field and reports an empty submission.
        $files = is_array($homework->submission_files) ? $homework->submission_files : [];
        $isV2 = $files !== [];

        if (! $isV2 && empty($homework->submission_image)) {
            return $this->fail('There is no submitted file to evaluate.', 422);
        }

        if ((string) $homework->status === 'Reviewed') {
            return $this->fail('This submission has been reviewed. Move it back to Under Review before re-running the evaluation.', 422);
        }

        // The v2 job returns early on an already-evaluated row, so the status
        // is cleared before dispatch rather than after it.
        $homework->update(['ai_status' => 'Checking', 'ai_failure_reason' => null]);

        try {
            $isV2
                ? EvaluateHomeworkSubmissionV2Job::dispatch(
                    (int) $homework->id,
                    (int) $homework->sub_institute_id,
                    (int) $homework->syear
                )
                : EvaluateHomeworkSubmissionJob::dispatch(
                    (int) $homework->id,
                    (int) $homework->sub_institute_id,
                    (int) $homework->syear
                );
        } catch (Throwable $exception) {
            // dispatch() re-throws a job's own exceptions synchronously while
            // QUEUE_CONNECTION=sync, and the job records its own failure on the
            // row either way -- so this must not also 500 the request.
            Log::error('Homework re-evaluation could not be dispatched', [
                'homework_id' => $homework->id,
                'message' => $exception->getMessage(),
            ]);
        }

        $homework->refresh();

        return response()->json([
            'status_code' => 1,
            'message' => 'Re-evaluation started.',
            'data' => [
                'submission' => $this->buildSubmissionEntry($homework, $request->getSchemeAndHttpHost()),
                'answers' => $this->answersFor((int) $homework->id),
            ],
        ], 200);
    }

    /**
     * The per-question marks for one submission.
     *
     * @return array<int,array<string,mixed>>
     */
    private function answersFor(int $homeworkId): array
    {
        return DB::table('homework_evaluation_answer')
            ->where('homework_id', $homeworkId)
            ->orderBy('question_no')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'question_no' => (int) $row->question_no,
                'question_id' => $row->question_id ? (int) $row->question_id : null,
                'question_title' => (string) ($row->question_title ?? ''),
                'question_type' => (string) ($row->question_type ?? ''),
                'is_objective' => (bool) $row->is_objective,
                'detected_answer' => (string) ($row->detected_answer ?? ''),
                'selected_options' => array_values(array_filter(explode(',', (string) $row->selected_options))),
                'expected_answer' => (string) ($row->expected_answer ?? ''),
                'max_marks' => (float) $row->max_marks,
                'ai_marks' => $row->ai_marks === null ? null : (float) $row->ai_marks,
                'teacher_marks' => $row->teacher_marks === null ? null : (float) $row->teacher_marks,
                'status' => (string) $row->status,
                'ai_confidence' => $row->ai_confidence === null ? null : (float) $row->ai_confidence,
                'ai_remark' => (string) ($row->ai_remark ?? ''),
                // A written answer the model was unsure of is the one a teacher
                // should look at first; an objective mark was checked against
                // the key and needs no second opinion.
                'needs_attention' => ! $row->is_objective
                    && $row->status !== 'unattempted'
                    && $row->ai_confidence !== null
                    && (float) $row->ai_confidence < AnswerSheetScoringService::CONFIDENCE_REVIEW_THRESHOLD,
                'page' => (int) $row->page,
            ])
            ->all();
    }

    /**
     * Writes the teacher's own marks, clamped to what each question is worth.
     *
     * Clamped rather than rejected: a slip in one mark box should land on the
     * nearest legal mark, not throw away the teacher's whole pass over the
     * submission.
     */
    private function applyMarks(int $homeworkId, mixed $marks): void
    {
        if (!is_array($marks)) {
            return;
        }

        foreach ($marks as $row) {
            if (!is_array($row) || !isset($row['question_no'])) {
                continue;
            }

            $answer = DB::table('homework_evaluation_answer')
                ->where('homework_id', $homeworkId)
                ->where('question_no', (int) $row['question_no'])
                ->first(['id', 'max_marks']);

            if (!$answer) {
                continue;
            }

            $given = $row['teacher_marks'] ?? null;
            $value = ($given === null || $given === '')
                ? null
                : round(max(0.0, min((float) $answer->max_marks, (float) $given)), 2);

            DB::table('homework_evaluation_answer')
                ->where('id', $answer->id)
                ->update(['teacher_marks' => $value, 'updated_at' => now()]);
        }
    }

    /** Falls back to the AI's figure per question where the teacher left one alone. */
    private function teacherTotal(int $homeworkId): ?float
    {
        $total = DB::table('homework_evaluation_answer')
            ->where('homework_id', $homeworkId)
            ->selectRaw('SUM(COALESCE(teacher_marks, ai_marks, 0)) as total')
            ->value('total');

        return $total === null ? null : round((float) $total, 2);
    }

    public function downloadFile(Request $request, $id): JsonResponse
    {
        $parts = explode('_', (string) $id);
        if (count($parts) < 2) {
            return $this->fail('File not found', 404);
        }

        $fileIndex = (int) array_pop($parts);
        $homeworkId = implode('_', $parts);

        $homework = studentHomeworkModel::find($homeworkId);
        if (!$homework) {
            return $this->fail('File not found', 404);
        }

        $files = $this->decodeFiles($homework);
        if (!isset($files[$fileIndex])) {
            return $this->fail('File not found', 404);
        }

        $file = $files[$fileIndex];
        $server = $request->getSchemeAndHttpHost();
        $path = $file['path'] ?? '';

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => [
                'url' => $path !== '' ? $server . '/storage/' . ltrim($path, '/') : '',
                'original_name' => $file['original_name'] ?? '',
                'mime_type' => $file['mime_type'] ?? '',
            ],
        ], 200);
    }

    // ==================================================================
    // Student "all my homework" table
    // ==================================================================

    /**
     * Every homework row belonging to the calling student, each flattened
     * into a single table-row shape (homework fields + its own submission
     * status fields) so the student list UI can render in one call instead
     * of one detail() call per homework item. Rows with no submission yet
     * are still included, with the submission-related fields left
     * null/empty, so the table can show pending homework too.
     */
    public function mySubmissions(Request $request): JsonResponse
    {
        $student_id = $this->submissionStudentId($request);

        if (!$student_id) {
            return $this->fail('user_id (student) is required');
        }

        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');

        $homeworks = studentHomeworkModel::where('student_id', $student_id)
            ->when($sub_institute_id, fn ($q) => $q->where('sub_institute_id', $sub_institute_id))
            ->when($syear, fn ($q) => $q->where('syear', $syear))
            ->orderBy('id', 'DESC')
            ->get();

        $server = $request->getSchemeAndHttpHost();

        $data = $homeworks->map(function ($homework) use ($server) {
            $subjectName = $homework->subject_id
                ? DB::table('subject')->where('id', $homework->subject_id)->value('subject_name')
                : null;

            $hasSubmission = $homework->completion_status === 'Y' || !empty($homework->status);

            return [
                'id' => $homework->id,
                'title' => $homework->title,
                'subject_id' => $homework->subject_id,
                'subject_name' => $subjectName,
                'date' => $homework->date,
                'date_fmt' => $homework->date ? date('d-m-Y', strtotime($homework->date)) : null,
                'submission_date' => $homework->submission_date,
                'submission_date_fmt' => $homework->submission_date
                    ? date('d-m-Y', strtotime($homework->submission_date))
                    : null,
                'reference_file_url' => !empty($homework->image)
                    ? $server . '/storage/student/' . $homework->image
                    : '',
                'source_type' => $homework->source_type ?? 'attachment',
                'status' => $hasSubmission ? ($homework->status ?: 'Pending') : null,
                'submission_files' => $hasSubmission ? $this->buildFilesPayload($homework, $server) : [],
                'submission_remarks' => $homework->submission_remarks,
                'teacher_remarks' => $homework->teacher_remarks,
                'ai_status' => $homework->ai_status,
                'ai_failure_reason' => $homework->ai_failure_reason,
                'ai_score' => $homework->ai_score,
                'ai_total_questions' => $homework->ai_total_questions,
                'ai_percentage' => $homework->ai_percentage,
                'reviewed_pdf_path' => $homework->reviewed_pdf_path,
                'evaluated_at' => optional($homework->evaluated_at)->toDateTimeString(),
                'feedback_published' => (bool) $homework->feedback_published,
            ];
        })->values();

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => $data,
        ], 200);
    }
}
