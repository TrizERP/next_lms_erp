<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\marks_entry\marks_entry_controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for Marks Entry (scholastic marks).
 *
 * Delegates every operation to the existing web controller
 * (result\marks_entry\marks_entry_controller) so business logic - marks
 * save/update, absent handling, percentage/grade handling, approval flags,
 * notifications - is reused 1:1, never re-implemented.
 */
class MarksEntryApiController extends BaseResultApiController
{
    /**
     * GET /api/result/marks-entry
     * Landing data of the marks entry screen (flash message + empty data set;
     * the actual grid is loaded via GET /api/result/marks-entry/create).
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(marks_entry_controller::class, 'index', $request);

            return $this->success($data);
        });
    }

    /**
     * GET /api/result/marks-entry/create
     * Marks entry grid: students of the selected class with any already
     * entered marks, subject & exam dropdowns, grade breakups and the
     * approval status of the selected exam.
     */
    public function create(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'grade'       => 'required',
                'standard'    => 'required',
                'division'    => 'required',
                'term'        => 'required',
                'subject'     => 'required',
                'exam_master' => 'required',
                'exam'        => 'required',
            ]);

            // The legacy method reads the PHP superglobal $_REQUEST directly,
            // so mirror the (validated) inputs into it - this also makes JSON
            // request bodies work.
            $this->mirrorToSuperglobal($request, [
                'grade', 'standard', 'division', 'term', 'subject', 'exam_master', 'exam',
            ]);

            $data = $this->delegate(marks_entry_controller::class, 'create', $request);

            return $this->success($data);
        });
    }

    /**
     * POST /api/result/marks-entry
     * Save/update marks for many students at once.
     * Body: { "values": { "<student_id>": { "exam_id": 12, "points": "35",
     *         "per": "70", "grade": "A", "comment": "" }, ... } }
     * points may also be "AB" / "N.A." / "EX" for absent-type entries.
     */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'values'           => 'required|array|min:1',
                'values.*.exam_id' => 'required',
            ]);

            // Drive the legacy store() through its existing API branch, which
            // reads $_REQUEST["type"], $_REQUEST["sub_institute_id"] and a
            // JSON-encoded $_REQUEST["data"] payload.
            $_REQUEST['type']             = 'API';
            $_REQUEST['sub_institute_id'] = session('sub_institute_id');
            $_REQUEST['data']             = json_encode($request->input('values'));

            $data = $this->delegate(marks_entry_controller::class, 'store', $request);

            return $this->success($data, $data['message'] ?? 'Data Saved', 201);
        });
    }

    /**
     * POST /api/result/marks-entry/approve
     * Approve (or un-approve) the marks of one exam/class/subject.
     * Body: term_id, standard_id, division_id, subject_id, exam_id, approve (1|0).
     */
    public function approve(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'term_id'     => 'required',
                'standard_id' => 'required',
                'division_id' => 'required',
                'subject_id'  => 'required',
                'exam_id'     => 'required',
                'approve'     => 'nullable',
            ]);

            $data = $this->delegate(marks_entry_controller::class, 'approve', $request);

            return $this->success($data, $data['message'] ?? 'Data Saved');
        });
    }

    /**
     * POST /api/result/marks-entry/marks-approval
     * Marks approval report for one class/term: scholastic exams (grouped by
     * exam type), subjects, exam types, grade types and co-scholastic entries.
     */
    public function marksApproval(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'term'     => 'required',
                'standard' => 'required',
                'grade'    => 'required',
                'division' => 'required',
            ]);

            $this->mirrorToSuperglobal($request, ['term', 'standard', 'grade', 'division']);

            // The legacy method hard-codes $type = "" and always returns the
            // Blade view; delegate() extracts the view's data array for us.
            $data = $this->delegate(marks_entry_controller::class, 'getMarksApproval', $request);

            return $this->success($data['data'] ?? $data);
        });
    }

    /**
     * POST /api/result/marks-entry/marks-dd
     * Exam-type / exam-title dropdown data of one student's entered marks.
     * Body: student_id (required); sub_institute_id, syear (default: session).
     */
    public function marksDd(Request $request)
    {
        return $this->run(function () use ($request) {
            $this->validate($request, ['student_id' => 'required|numeric']);

            $this->applySessionDefaults($request, ['sub_institute_id', 'syear']);
            $this->mirrorToSuperglobal($request, ['sub_institute_id', 'syear', 'student_id']);

            $this->delegateEcho('get_marks_dd');
        });
    }

    /**
     * POST /api/result/marks-entry/co-scholastic-marks-dd
     * Teacher-wise co-scholastic dropdown data (classes, co-scholastic items,
     * grade scales) for the mobile/API entry flow.
     * Body: teacher_id (required); sub_institute_id (default: session).
     */
    public function coScholasticMarksDd(Request $request)
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'teacher_id'       => 'required|numeric',
                'sub_institute_id' => 'nullable|numeric',
            ]);

            $this->applySessionDefaults($request, ['sub_institute_id']);
            $this->mirrorToSuperglobal($request, ['sub_institute_id', 'teacher_id']);

            $this->delegateEcho('get_co_scholastic_marks_dd', $request);
        });
    }

    /**
     * POST /api/result/marks-entry/get-result
     * Exam-wise result (subject marks, totals, average) of one student.
     * Body: student_id (required); sub_institute_id, syear (default: session).
     */
    public function getResult(Request $request)
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'student_id'       => 'required|numeric',
                'sub_institute_id' => 'nullable|numeric',
                'syear'            => 'nullable|numeric',
            ]);

            $this->applySessionDefaults($request, ['sub_institute_id', 'syear']);
            $this->mirrorToSuperglobal($request, ['sub_institute_id', 'syear', 'student_id']);

            $this->delegateEcho('get_result', $request);
        });
    }

    /* ---------------------------------------------------------------------
     | Internal helpers (no business logic - plumbing only)
     * -------------------------------------------------------------------*/

    /**
     * Default missing request inputs from the hydrated session and merge them
     * back into the request (needed by the legacy validators).
     */
    private function applySessionDefaults(Request $request, array $keys): void
    {
        foreach ($keys as $key) {
            if ($request->input($key) === null || $request->input($key) === '') {
                $request->merge([$key => session($key)]);
            }
        }
    }

    /**
     * Mirror request inputs into $_REQUEST because several legacy methods read
     * the superglobal directly (which JSON request bodies never populate).
     */
    private function mirrorToSuperglobal(Request $request, array $keys): void
    {
        foreach ($keys as $key) {
            $_REQUEST[$key] = $request->input($key);
        }
    }

    /**
     * Delegate to a legacy method that echoes its JSON and exit()s (bypassing
     * Laravel's response cycle). An output-buffer callback re-wraps the flushed
     * legacy payload {status, message, data} into the standard API envelope.
     * If the legacy method throws instead of exiting, the buffer is discarded
     * so run() can emit the standard 500 envelope.
     */
    private function delegateEcho(string $method, ?Request $request = null): void
    {
        header('Content-Type: application/json');

        ob_start(function (string $buffer) {
            $decoded = json_decode($buffer, true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                return $buffer;
            }

            $success = (string) ($decoded['status'] ?? '') === '1';

            return json_encode([
                'success' => $success,
                'message' => is_string($decoded['message'] ?? null)
                    ? $decoded['message']
                    : ($success ? 'Success' : 'Failed'),
                'data'    => $decoded['data'] ?? null,
                'errors'  => $success ? null : ($decoded['message'] ?? null),
            ]);
        });

        try {
            if ($request !== null) {
                $this->delegate(marks_entry_controller::class, $method, $request);
            } else {
                $this->delegate(marks_entry_controller::class, $method);
            }
        } finally {
            // Only reached when the legacy method did NOT exit (i.e. it threw):
            // drop the buffered partial output and let run() handle the error.
            ob_end_clean();
        }
    }
}
