<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\cbse_result\cbse_1t5_result_controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for the CBSE Std 1-5 report card generator (both terms).
 *
 * Delegates every operation to the existing web controller
 * (result\cbse_result\cbse_1t5_result_controller) so all mark, grade,
 * percentage, co-scholastic and attendance calculations are reused 1:1 —
 * never re-implemented.
 */
class Cbse1t5ResultApiController extends BaseResultApiController
{
    /**
     * GET /api/result/cbse-1t5-result
     * Report filter screen bootstrap (legacy index; the class/division/standard
     * filters themselves come from the shared lookup APIs).
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(cbse_1t5_result_controller::class, 'index', $request);

            return $this->success($data);
        });
    }

    /**
     * POST /api/result/cbse-1t5-result/show
     * Generate the Std 1-5 report card data for every student of the selected
     * class: term 1 (`data`) + term 2 (`term_2_data`) blocks, each keyed by
     * student_id with exams, subject marks, percentage, final grade,
     * co-scholastic grades, attendance and grade ranges, plus the report
     * header/footer configuration.
     *
     * Body: grade, standard, division (ids).
     */
    public function show(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'grade'    => 'required',
                'standard' => 'required',
                'division' => 'required',
            ]);

            $this->hydrateSuperglobals($request);

            $data = $this->delegate(cbse_1t5_result_controller::class, 'show_result', $request);

            return $this->success($data);
        });
    }

    /**
     * POST /api/result/cbse-1t5-result/save-html
     * Persist the rendered report card HTML per student (result_html table),
     * exactly like the web "save result" action.
     *
     * Body: student_arr (comma separated student ids), term_id, grade_id,
     * standard_id, division_id, syear, html_<student_id> per student.
     */
    public function saveHtml(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'student_arr' => 'required|string',
                'term_id'     => 'required',
                'grade_id'    => 'required',
                'standard_id' => 'required',
                'division_id' => 'required',
            ]);

            // The legacy duplicate-check compares request syear; default it to
            // the hydrated session so update-vs-insert behaves like the web.
            if (! $request->filled('syear')) {
                $request->merge(['syear' => session('syear')]);
            }

            $this->delegate(cbse_1t5_result_controller::class, 'save_result_html', $request);

            return $this->success(null, 'Result HTML Saved');
        });
    }

    /**
     * POST /api/result/cbse-1t5-result/pdf
     * Convert the saved report card HTML of one student into PDF file(s) and
     * return their public links (legacy mobile studentResultPDFAPI, including
     * the institute-specific fee-due check).
     *
     * Body: student_id, syear, sub_institute_id, term_id (all numeric;
     * syear/sub_institute_id default to the token session).
     */
    public function pdf(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $request->merge([
                'syear'            => $request->input('syear', session('syear')),
                'sub_institute_id' => $request->input('sub_institute_id', session('sub_institute_id')),
                'term_id'          => $request->input('term_id', session('term_id')),
            ]);

            $this->validate($request, [
                'student_id'       => 'required|numeric',
                'syear'            => 'required|numeric',
                'sub_institute_id' => 'required|numeric',
                'term_id'          => 'required|numeric',
            ]);

            $data = $this->delegate(cbse_1t5_result_controller::class, 'studentResultPDFAPI', $request);

            $status = $data['status'] ?? null;

            if ((string) $status === '1') {
                return $this->success($data['data'] ?? [], $data['message'] ?? 'Success');
            }

            if ((string) $status === '2') {
                return $this->error($data['message'] ?? 'Token Auth Failed', 401);
            }

            return $this->error($data['message'] ?? 'No Record', 404);
        });
    }

    /**
     * The legacy show_result()/getGradeScale()/getCoArea()/getAttendance()
     * read $_REQUEST directly. Requests with a JSON body do not populate
     * $_REQUEST, so mirror the needed inputs into the superglobal before
     * delegating.
     */
    private function hydrateSuperglobals(Request $request): void
    {
        foreach (['grade', 'standard', 'division'] as $key) {
            $_REQUEST[$key] = $request->input($key);
        }

        $_REQUEST['syear']            = $request->input('syear', session('syear'));
        $_REQUEST['sub_institute_id'] = $request->input('sub_institute_id', session('sub_institute_id'));
    }
}
