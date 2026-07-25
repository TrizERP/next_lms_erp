<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\new_result\studentResultController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for New Report Card (student result generation).
 *
 * Delegates every operation to the existing web controller
 * (result\new_result\studentResultController) so template rendering,
 * scholastic/co-scholastic marks, attendance and grade calculations are
 * reused 1:1 — never re-implemented.
 */
class StudentResultApiController extends BaseResultApiController
{
    /**
     * GET /api/result/student-result
     * Report-card templates of the institute + academic terms + result types
     * (the search-panel metadata of the web screen).
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(studentResultController::class, 'index', $request);

            return $this->success($data);
        });
    }

    /**
     * GET /api/result/student-result/create
     * Student list for the selected class plus the selected template/format
     * context (step 1 of report-card generation).
     * Query: template, grade, standard, division, format, result_type, term_id.
     */
    public function create(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'standard' => 'required',
                'grade'    => 'nullable',
                'division' => 'nullable',
            ]);

            $data = $this->delegate(studentResultController::class, 'create', $request);

            $message = $data['message'] ?? 'Success';

            return $this->success($data, $message);
        });
    }

    /**
     * POST /api/result/student-result
     * Generate the report cards for the selected students using the selected
     * template. Returns the fully rendered report-card HTML per student
     * (data.html / data.all_stud_html) plus the generation context — this is
     * the exact payload the web result_view screen receives and what
     * save-html later persists.
     */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'template_id' => 'required',
                'students'    => 'required',
                'format'      => 'required',
                'result_type' => 'nullable',
            ]);

            $data = $this->delegate(studentResultController::class, 'store', $request);

            // The legacy method always returns a view; delegate() yields its
            // data array as ['data' => ...] — unwrap it.
            if (is_array($data) && array_key_exists('data', $data) && count($data) === 1) {
                $data = $data['data'];
            }

            return $this->success($data);
        });
    }

    /**
     * POST /api/result/student-result/save-html
     * Persist the generated report-card HTML (result_html) and attendance
     * summary (result_reportcard_marks) — same as web route save_result_html_new.
     * Body: student_arr (CSV of student ids), term_id, grade_id, standard_id,
     * division_id, result_type, html_{student_id} fields, total_working_day,
     * present_working_day, student_percentage.
     */
    public function saveHtml(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'student_arr' => 'required',
                'term_id'     => 'required',
                'grade_id'    => 'required',
                'standard_id' => 'required',
                'division_id' => 'required',
                'result_type' => 'nullable',
            ]);

            $this->delegate(studentResultController::class, 'save_result_html', $request);

            return $this->success(null, 'Result HTML saved.');
        });
    }
}
