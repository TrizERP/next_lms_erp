<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use App\Jobs\EvaluateAssignmentSubmissionJob;
use App\Models\lms\assignment\lms_assignmentModel;
use App\Models\lms\lmsOfflineExamModel;
use App\Models\lms\lmsOfflineExamAnswerModel;
use App\Models\lms\questionpaperModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use function App\Helpers\getStudents;

/**
 * Dedicated token-authenticated API for the three LMS Assignment menu items:
 *   - Assignment            (perm 312, teacher)  -> create screen
 *   - Assignment Submission (perm 313, student)  -> student upload
 *   - Annotate Assignment   (perm 314, teacher)  -> review / grade
 *
 * This is a new, self-contained controller (mirrors StudentHomeworkApiController)
 * and does NOT modify the existing session/blade controllers under
 * App\Http\Controllers\lms\assignment. It re-implements the same behaviour of
 * assignmentController / assignmentSubmissionController / annotateAssignmentController
 * over stateless token auth, reading identity from the request body (type=API).
 *
 * All responses use the { status_code, message, data } envelope where
 * status_code = 1 success, 0 failure (matching the LMS frontend proxy contract).
 */
class LmsAssignmentApiController extends Controller
{
    // ==================================================================
    // Shared helpers
    // ==================================================================

    private function parseCsvIds($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('intval', $value), fn ($id) => $id > 0));
        }
        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }
        $parts = array_filter(array_map('trim', explode(',', $value)), fn ($part) => $part !== '');

        return array_values(array_filter(array_map('intval', $parts), fn ($id) => $id > 0));
    }

    private function fail(string $message, int $status = 422, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'status_code' => 0,
            'message' => $message,
        ], $extra), $status);
    }

    // ==================================================================
    // MODULE 1 - Assignment (teacher create screen)
    // ==================================================================

    /**
     * Standard-filtered subject dropdown for the create screen.
     * Counterpart of the blade `ajax_getHomeworkSubjects`.
     */
    public function subjects(Request $request): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');
        $standard_id = $request->input('standard_id');
        $division_id = $request->input('division_id');
        $user_profile_name = $request->input('user_profile_name');
        $teacher_id = $request->input('user_id');

        if (!$sub_institute_id || !$standard_id) {
            return $this->fail('sub_institute_id and standard_id are required');
        }

        if ($user_profile_name === 'Admin' || !$user_profile_name) {
            $data = DB::table('sub_std_map as s')
                ->selectRaw("s.subject_id, s.display_name, s.standard_id")
                ->where('s.sub_institute_id', $sub_institute_id)
                ->where('s.standard_id', $standard_id)
                ->groupByRaw('s.subject_id, s.standard_id, s.display_name')
                ->orderBy('s.display_name')
                ->get()->toArray();
        } else {
            $data = DB::table('sub_std_map as s')
                ->join('timetable as t', function ($join) {
                    $join->whereRaw('t.standard_id = s.standard_id AND t.sub_institute_id = s.sub_institute_id AND t.subject_id = s.subject_id');
                })
                ->selectRaw('s.subject_id, s.display_name, s.standard_id')
                ->where('s.sub_institute_id', $sub_institute_id)
                ->where('t.syear', $syear)
                ->where('s.standard_id', $standard_id)
                ->when($division_id, function ($q) use ($division_id) {
                    $q->where('t.division_id', $division_id);
                })
                ->when($user_profile_name === 'Teacher' && $teacher_id, function ($q) use ($teacher_id) {
                    $q->where('t.teacher_id', $teacher_id);
                })
                ->groupByRaw('s.subject_id, s.standard_id, s.display_name')
                ->orderBy('s.display_name')
                ->get()->toArray();
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => $data,
        ], 200);
    }

    /**
     * Search students by grade / standard / division for the assignment
     * student picker table. Same shape used by the homework assign screen.
     */
    public function students(Request $request): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');

        if (!$sub_institute_id || !$syear) {
            return $this->fail('sub_institute_id and syear are required');
        }

        $query = DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw('se.student_id = s.id AND se.sub_institute_id = s.sub_institute_id');
            })
            ->join('standard as st', function ($join) {
                $join->whereRaw('st.id = se.standard_id AND st.sub_institute_id = se.sub_institute_id');
            })
            ->join('division as d', function ($join) {
                $join->whereRaw('d.id = se.section_id AND d.sub_institute_id = se.sub_institute_id');
            })
            ->selectRaw("s.id, CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                s.enrollment_no, s.gender, s.mobile, se.roll_no,
                se.standard_id, st.name AS standard_name, se.section_id AS division_id, d.name AS division_name")
            ->where('s.sub_institute_id', $sub_institute_id)
            ->where('se.syear', $syear)
            ->whereNull('se.end_date');

        if ($grade) {
            $query->where('se.grade_id', $grade);
        }
        if ($standard) {
            $query->where('se.standard_id', $standard);
        }
        if ($division) {
            $query->where('se.section_id', $division);
        }

        $data = $query->orderBy('student_name')->get()->toArray();

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => $data,
        ], 200);
    }

    /**
     * Offline question papers for the selected subject (the "Select Exam" PDF
     * dropdown on the create screen). Mirrors assignmentController::create().
     */
    public function examPapers(Request $request): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id');
        $subject_id = $request->input('subject_id');

        if (!$sub_institute_id || !$subject_id) {
            return $this->fail('sub_institute_id and subject_id are required');
        }

        $data = DB::table('question_paper')
            ->selectRaw("id, paper_name, total_marks, exam_type, subject_id,
                CONCAT(CONCAT_WS('_', id, sub_institute_id, syear), '.pdf') AS pdf_name")
            ->where('sub_institute_id', $sub_institute_id)
            ->where('exam_type', 'offline')
            ->where('subject_id', $subject_id)
            ->orderBy('id', 'DESC')
            ->get()->toArray();

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => $data,
        ], 200);
    }

    /**
     * Create one lms_assignment row per selected student.
     * Mirrors assignmentController::store() (+ notification, best-effort).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'syear' => 'required|numeric',
            'students' => 'required',
            'title' => 'required|string|max:50',
            'description' => 'nullable|string|max:50',
            'subject_id' => 'required|numeric',
            'exam_id' => 'required|numeric',
            'submission_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->fail('Validation failed', 422, ['errors' => $validator->errors()]);
        }

        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');
        $students = $this->parseCsvIds($request->input('students'));
        $title = $request->input('title');
        $description = $request->input('description');
        $submission_date = $request->input('submission_date');
        $subject_id = $request->input('subject_id');
        $exam_id = $request->input('exam_id');
        $created_by = $request->input('user_id') ?? $request->input('teacher_id');

        // exam_pdf can arrive either as a plain pdf name or the packed
        // "<pdf_name>####<exam_id>" value used by the legacy blade select.
        $exam_pdf_input = (string) $request->input('exam_pdf', '');
        if (strpos($exam_pdf_input, '####') !== false) {
            $parts = explode('####', $exam_pdf_input);
            $exam_pdf_input = $parts[0];
            if (empty($exam_id) && isset($parts[1])) {
                $exam_id = $parts[1];
            }
        }
        $exam_pdf = $exam_pdf_input !== '' ? 'QuestionPaper/' . $exam_pdf_input : null;

        if (empty($students)) {
            return $this->fail('Select at least one student.');
        }

        $student_details = getStudents($students, $sub_institute_id, $syear);
        if (empty($student_details)) {
            return $this->fail("Students not found for academic year $syear", 404);
        }

        $inserted_ids = [];
        foreach ($student_details as $arr) {
            $assignment_arr = [
                'student_id' => $arr['id'],
                'sub_institute_id' => $sub_institute_id,
                'title' => $title,
                'description' => $description,
                'standard_id' => $arr['standard_id'],
                'division_id' => $arr['section_id'],
                'subject_id' => $subject_id,
                'exam_id' => $exam_id,
                'exam_pdf' => $exam_pdf,
                'created_date' => date('Y-m-d'),
                'submission_date' => $submission_date,
                'syear' => $syear,
                'student_submission_status' => 'N',
                'teacher_submission_status' => 'N',
                'created_ip' => $request->ip(),
                'created_by' => $created_by,
            ];

            $inserted_ids[] = lms_assignmentModel::insertGetId($assignment_arr);

            // Notification is best-effort: never fail assignment creation on it.
            try {
                if (function_exists('App\\Helpers\\sendNotification')) {
                    \App\Helpers\sendNotification([
                        'NOTIFICATION_TYPE' => 'LMS Assignment',
                        'NOTIFICATION_DATE' => date('Y-m-d'),
                        'STUDENT_ID' => $arr['id'],
                        'NOTIFICATION_DESCRIPTION' => $title,
                        'STATUS' => 0,
                        'SUB_INSTITUTE_ID' => $sub_institute_id,
                        'SYEAR' => $syear,
                        'CREATED_BY' => $created_by,
                        'CREATED_IP' => $request->ip(),
                    ]);
                }
            } catch (\Throwable $e) {
                // swallow – notification is non-critical
            }
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Assignment Added successfully',
            'assignment_ids' => $inserted_ids,
            'count' => count($inserted_ids),
        ], 201);
    }

    /**
     * List assignments created (teacher/admin view) - report of created rows.
     */
    public function index(Request $request): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');
        $grade = $request->input('grade');
        $standard_id = $request->input('standard_id');
        $division_id = $request->input('division_id');
        $subject_id = $request->input('subject_id');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');

        if (!$sub_institute_id || !$syear) {
            return $this->fail('sub_institute_id and syear are required');
        }

        $server = $request->getSchemeAndHttpHost();

        // subject/tblstudent are left-joined (not inner) so a row is never
        // silently dropped from the report just because its subject_id or
        // student_id fails to resolve — every assigned/submitted row must
        // stay visible even if a reference lookup is stale.
        $query = DB::table('lms_assignment as a')
            ->leftJoin('subject as s', function ($join) {
                $join->whereRaw('s.id = a.subject_id');
            })
            ->leftJoin('tblstudent as ts', function ($join) {
                $join->whereRaw('ts.id = a.student_id');
            })
            ->leftJoin('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw('se.student_id = a.student_id AND se.syear = a.syear AND se.end_date IS NULL');
            })
            ->leftJoin('standard as st', function ($join) {
                $join->whereRaw('st.id = a.standard_id');
            })
            ->leftJoin('division as d', function ($join) {
                $join->whereRaw('d.id = a.division_id');
            })
            ->selectRaw("a.*, s.subject_name, st.name AS standard_name, d.name AS division_name,
                CONCAT_WS(' ', ts.first_name, ts.middle_name, ts.last_name) AS student_name,
                ts.enrollment_no, ts.mobile,
                IF(a.exam_pdf IS NULL OR a.exam_pdf = '', '', CONCAT('$server/storage/', a.exam_pdf)) AS exam_pdf_url,
                IF(a.submission_image IS NULL OR a.submission_image = '', '',
                    CONCAT('$server/storage/lms_assignment_submission/', a.submission_image)) AS submission_file_url,
                DATE_FORMAT(a.created_date, '%d-%m-%Y') AS created_date_fmt,
                DATE_FORMAT(a.submission_date, '%d-%m-%Y') AS submission_date_fmt")
            ->where('a.sub_institute_id', $sub_institute_id)
            ->where('a.syear', $syear);

        if ($grade) {
            $query->where('se.grade_id', $grade);
        }
        if ($standard_id) {
            $query->where('a.standard_id', $standard_id);
        }
        if ($division_id) {
            $query->where('a.division_id', $division_id);
        }
        if ($subject_id) {
            $query->where('a.subject_id', $subject_id);
        }
        if ($from_date) {
            $query->whereDate('a.created_date', '>=', $from_date);
        }
        if ($to_date) {
            $query->whereDate('a.created_date', '<=', $to_date);
        }

        $data = $query->orderBy('a.id', 'DESC')->get()->toArray();

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => $data,
        ], 200);
    }

    /**
     * Bulk hard-delete lms_assignment rows (Student Homework Report action).
     * API counterpart of StudentHomeworkApiController::bulkDelete().
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $ids = $request->input('selected_students', $request->input('ids'));
        if (is_string($ids)) {
            $ids = array_filter(array_map('trim', explode(',', $ids)), fn ($id) => $id !== '');
        }

        if (empty($ids)) {
            return $this->fail('No assignment selected');
        }

        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');

        $deleted = DB::table('lms_assignment')
            ->whereIn('id', $ids)
            ->when($sub_institute_id, fn ($q) => $q->where('sub_institute_id', $sub_institute_id))
            ->when($syear, fn ($q) => $q->where('syear', $syear))
            ->delete();

        return response()->json([
            'status_code' => 1,
            'message' => 'Assignment Deleted Successfully',
            'deleted' => $deleted,
        ], 200);
    }

    // ==================================================================
    // MODULE 2 - Assignment Submission (student upload screen)
    // ==================================================================

    /**
     * List the logged-in student's assignments for the submission screen.
     * Counterpart of assignmentSubmissionController::index()/getData().
     * Identity (student) comes from user_id in the token-authenticated body.
     */
    public function submissionList(Request $request): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');
        $student_id = $request->input('user_id') ?? $request->input('student_id');

        if (!$sub_institute_id || !$syear) {
            return $this->fail('sub_institute_id and syear are required');
        }
        if (!$student_id) {
            return $this->fail('user_id (student) is required');
        }

        $server = $request->getSchemeAndHttpHost();

        $data = DB::table('lms_assignment as a')
            ->leftJoin('subject as s', function ($join) {
                $join->whereRaw('s.id = a.subject_id');
            })
            ->selectRaw("a.*, s.subject_name,
                IF(a.exam_pdf IS NULL OR a.exam_pdf = '', '', CONCAT('$server/storage/', a.exam_pdf)) AS exam_pdf_url,
                IF(a.submission_image IS NULL OR a.submission_image = '', '',
                    CONCAT('$server/storage/lms_assignment_submission/', a.submission_image)) AS submission_file_url,
                DATE_FORMAT(a.created_date, '%d-%m-%Y') AS created_date_fmt,
                DATE_FORMAT(a.submission_date, '%d-%m-%Y') AS submission_date_fmt")
            ->where('a.sub_institute_id', $sub_institute_id)
            ->where('a.syear', $syear)
            ->where('a.student_id', $student_id)
            ->orderBy('a.id', 'DESC')
            ->get()->toArray();

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => $data,
        ], 200);
    }

    /**
     * Store student submissions: one uploaded file per assignment.
     * Mirrors assignmentSubmissionController::store() over multipart:
     *   image[<assignment_id>] = file
     */
    public function submissionStore(Request $request): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');
        $student_id = $request->input('user_id') ?? $request->input('student_id');

        if (!$sub_institute_id || !$syear || !$student_id) {
            return $this->fail('sub_institute_id, syear and user_id are required');
        }

        $imageFiles = $request->file('image');
        if (!is_array($imageFiles) || empty($imageFiles)) {
            return $this->fail('Please choose at least one file to upload.');
        }

        $updated = 0;
        foreach ($imageFiles as $assignment_id => $file) {
            if (!$file) {
                continue;
            }
            $originalname = $file->getClientOriginalName();
            $ext = File::extension($originalname);
            $file_name = 'assignment_' . $assignment_id . '-' . date('YmdHis') . '-' . $student_id . '.' . $ext;
            $file->storeAs('public/lms_assignment_submission', $file_name);

            $submissionData = [
                'submission_image' => $file_name,
                'student_submitted_date' => date('Y-m-d'),
                'student_submission_status' => 'Y',
                'student_submitted_by' => $student_id,
            ];
            $whereRow = [
                'id' => $assignment_id,
                'syear' => $syear,
                'sub_institute_id' => $sub_institute_id,
                'student_id' => $student_id,
            ];

            // Recording the submission must succeed even if the AI evaluation
            // columns aren't there yet (e.g. migration not run in this env) —
            // fall back to the core submission fields rather than 500 the
            // student's upload over a missing 'ai_status' column.
            try {
                $ok = lms_assignmentModel::where($whereRow)->update(array_merge($submissionData, [
                    'ai_status' => 'Checking',
                    'ai_failure_reason' => null,
                ]));
            } catch (\Throwable $exception) {
                Log::warning('Could not set ai_status on assignment submission — falling back without AI columns', [
                    'assignment_id' => $assignment_id,
                    'message' => $exception->getMessage(),
                ]);
                $ok = lms_assignmentModel::where($whereRow)->update($submissionData);
            }

            if ($ok) {
                $updated++;

                // AI evaluation is best-effort and must never break the upload
                // response: dispatch() re-throws job exceptions synchronously
                // when QUEUE_CONNECTION=sync, so this call itself needs a guard
                // even though the job's own handle() already catches its
                // internal OCR/Gemini/PDF failures.
                try {
                    EvaluateAssignmentSubmissionJob::dispatch((int) $assignment_id, (int) $sub_institute_id, (int) $syear);
                } catch (\Throwable $exception) {
                    Log::error('AI evaluation failed for assignment submission', [
                        'assignment_id' => $assignment_id,
                        'message' => $exception->getMessage(),
                    ]);
                    try {
                        lms_assignmentModel::where($whereRow)->update([
                            'ai_status' => 'Failed',
                            'ai_failure_reason' => mb_substr($exception->getMessage(), 0, 250),
                        ]);
                    } catch (\Throwable $updateException) {
                        // AI columns may not exist in this environment — the submission itself is already saved.
                    }
                }
            }
        }

        return response()->json([
            'status_code' => $updated > 0 ? 1 : 0,
            'message' => $updated > 0 ? 'Assignment Submited successfully' : 'Failed to submit assignment please try again',
            'updated' => $updated,
        ], $updated > 0 ? 200 : 422);
    }

    /**
     * Poll AI evaluation status/result for a single assignment (used by the
     * student submission screen and the teacher annotate list to refresh
     * "Checking..." rows without a full reload).
     */
    public function aiEvaluationStatus(Request $request, $id): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');

        $assignment = lms_assignmentModel::where('id', $id)
            ->when($sub_institute_id, fn ($q) => $q->where('sub_institute_id', $sub_institute_id))
            ->when($syear, fn ($q) => $q->where('syear', $syear))
            ->first();

        if (!$assignment) {
            return $this->fail('Assignment not found', 404);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => [
                'id' => $assignment->id,
                'ai_status' => $assignment->ai_status,
                'ai_failure_reason' => $assignment->ai_failure_reason,
                'ai_score' => $assignment->ai_score,
                'ai_total_questions' => $assignment->ai_total_questions,
                'ai_percentage' => $assignment->ai_percentage,
                'reviewed_pdf_path' => $assignment->reviewed_pdf_path,
                'teacher_remarks' => $assignment->teacher_remarks,
                'evaluated_at' => optional($assignment->evaluated_at)->toDateTimeString(),
            ],
        ], 200);
    }

    // ==================================================================
    // MODULE 3 - Annotate Assignment (teacher review / grade screen)
    // ==================================================================

    /**
     * List all assignments for the teacher's annotate screen.
     * Counterpart of annotateAssignmentController::index()/getData().
     */
    public function annotateList(Request $request): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');
        $grade = $request->input('grade');
        $standard_id = $request->input('standard_id');
        $division_id = $request->input('division_id');
        $subject_id = $request->input('subject_id');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $status = $request->input('status');

        if (!$sub_institute_id || !$syear) {
            return $this->fail('sub_institute_id and syear are required');
        }

        $server = $request->getSchemeAndHttpHost();

        $query = DB::table('lms_assignment as a')
            ->leftJoin('subject as s', function ($join) {
                $join->whereRaw('s.id = a.subject_id');
            })
            ->leftJoin('tblstudent as ts', function ($join) {
                $join->whereRaw('ts.id = a.student_id');
            })
            ->leftJoin('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw('se.student_id = a.student_id AND se.syear = a.syear AND se.end_date IS NULL');
            })
            ->leftJoin('standard as st', function ($join) {
                $join->whereRaw('st.id = a.standard_id');
            })
            ->leftJoin('division as d', function ($join) {
                $join->whereRaw('d.id = a.division_id');
            })
            ->selectRaw("a.*, s.subject_name, st.name AS standard_name, d.name AS division_name,
                ts.enrollment_no, ts.mobile,
                CONCAT_WS(' ', ts.first_name, ts.middle_name, ts.last_name) AS student_name,
                IF(a.exam_pdf IS NULL OR a.exam_pdf = '', '', CONCAT('$server/storage/', a.exam_pdf)) AS exam_pdf_url,
                IF(a.submission_image IS NULL OR a.submission_image = '', '',
                    CONCAT('$server/storage/lms_assignment_submission/', a.submission_image)) AS submission_file_url,
                DATE_FORMAT(a.created_date, '%d-%m-%Y') AS created_date_fmt,
                DATE_FORMAT(a.submission_date, '%d-%m-%Y') AS submission_date_fmt")
            ->where('a.sub_institute_id', $sub_institute_id)
            ->where('a.syear', $syear);

        if ($grade) {
            $query->where('se.grade_id', $grade);
        }
        if ($standard_id) {
            $query->where('a.standard_id', $standard_id);
        }
        if ($division_id) {
            $query->where('a.division_id', $division_id);
        }
        if ($subject_id) {
            $query->where('a.subject_id', $subject_id);
        }
        if ($status && in_array($status, ['Y', 'N'], true)) {
            $query->where('a.student_submission_status', $status);
        }
        if ($from_date && $to_date) {
            $query->whereRaw("DATE_FORMAT(a.submission_date, '%Y-%m-%d') BETWEEN ? AND ?", [$from_date, $to_date]);
        }

        $data = $query->orderBy('a.id', 'DESC')->get()->toArray();

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => $data,
        ], 200);
    }

    /**
     * Load one assignment's question paper + questions for the grading screen.
     * Counterpart of annotateAssignmentController::edit().
     */
    public function annotateQuestions(Request $request): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id');
        $assignment_id = $request->input('assignment_id');

        if (!$sub_institute_id) {
            return $this->fail('sub_institute_id is required');
        }
        if (!$assignment_id) {
            return $this->fail('assignment_id is required');
        }

        $assignment = lms_assignmentModel::find($assignment_id);
        if (!$assignment) {
            return $this->fail('Assignment not found', 404);
        }
        $assignment = $assignment->toArray();

        $paper = questionpaperModel::find($assignment['exam_id']);
        if (!$paper) {
            return $this->fail('Question paper not found for this assignment', 404);
        }
        $paper = $paper->toArray();

        $questions = [];
        if (!empty($paper['question_ids'])) {
            $questions = DB::table('lms_question_master')
                ->whereRaw('id in (' . $paper['question_ids'] . ')')
                ->where('sub_institute_id', $sub_institute_id)
                ->get()->toArray();
        }

        $server = $request->getSchemeAndHttpHost();
        $assignment['submission_file_url'] = !empty($assignment['submission_image'])
            ? $server . '/storage/lms_assignment_submission/' . $assignment['submission_image']
            : '';

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => [
                'assignment' => $assignment,
                'question_paper' => $paper,
                'questions' => json_decode(json_encode($questions), true),
            ],
        ], 200);
    }

    /**
     * Record the teacher's review/grade of a submitted assignment.
     * Mirrors annotateAssignmentController::store(): inserts an offline exam +
     * per-question answers, then flags the assignment as teacher-reviewed.
     * Expects JSON: hid_question_paper_id, hid_assignment_id, hid_student_id,
     * questions { <question_id>: <marks> }, teacher_remarks.
     */
    public function annotateStore(Request $request): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');
        $user_id = $request->input('user_id') ?? $request->input('teacher_id');
        $question_paper_id = $request->input('hid_question_paper_id') ?? $request->input('question_paper_id');
        $assignment_id = $request->input('hid_assignment_id') ?? $request->input('assignment_id');
        $student_id = $request->input('hid_student_id') ?? $request->input('student_id');
        $question_arr = $request->input('questions', []);
        $teacher_remarks = $request->input('teacher_remarks');

        if (!$sub_institute_id || !$assignment_id || !$question_paper_id || !$student_id) {
            return $this->fail('sub_institute_id, assignment, question paper and student are required');
        }

        if (!is_array($question_arr)) {
            $question_arr = [];
        }

        $paper = questionpaperModel::find($question_paper_id);
        if (!$paper) {
            return $this->fail('Question paper not found', 404);
        }
        $paper = $paper->toArray();

        // Any MCQ question left untoggled by the teacher counts as 0 (wrong).
        if (!empty($paper['question_ids'])) {
            $questionData = DB::table('lms_question_master')
                ->whereRaw('id in (' . $paper['question_ids'] . ')')
                ->where('sub_institute_id', $sub_institute_id)
                ->get()->toArray();
            foreach ($questionData as $v) {
                if (!isset($question_arr[$v->id])) {
                    $question_arr[$v->id] = 0;
                }
            }
        }

        $total_wrong = $total_right = $obtain_marks = 0;
        foreach ($question_arr as $marks) {
            if ((float) $marks == 0) {
                $total_wrong++;
            } else {
                $total_right++;
            }
            $obtain_marks += (float) $marks;
        }

        $offline_exam_id = lmsOfflineExamModel::insertGetId([
            'student_id' => $student_id,
            'question_paper_id' => $question_paper_id,
            'assignment_id' => $assignment_id,
            'total_right' => $total_right,
            'total_wrong' => $total_wrong,
            'obtain_marks' => $obtain_marks,
            'created_by' => $user_id,
            'syear' => $syear,
            'sub_institute_id' => $sub_institute_id,
        ]);

        foreach ($question_arr as $qid => $marks) {
            lmsOfflineExamAnswerModel::insert([
                'question_paper_id' => $question_paper_id,
                'offline_exam_id' => $offline_exam_id,
                'student_id' => $student_id,
                'question_id' => $qid,
                'ans_status' => ((float) $marks == 0) ? 'wrong' : 'right',
                'created_by' => $user_id,
            ]);
        }

        lms_assignmentModel::where(['id' => $assignment_id])->update([
            'teacher_id' => $user_id,
            'teacher_remarks' => $teacher_remarks,
            'teacher_submission_date' => date('Y-m-d'),
            'teacher_submission_status' => 'Y',
        ]);

        return response()->json([
            'status_code' => 1,
            'message' => 'Assignment Reviewed Successfully',
            'obtain_marks' => $obtain_marks,
            'offline_exam_id' => $offline_exam_id,
        ], 201);
    }
}
