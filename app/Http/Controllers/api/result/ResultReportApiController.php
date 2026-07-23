<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\cbse_result\result_report_controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for Result Report (CBSE result reports).
 *
 * Delegates every operation to the existing web controller
 * (result\cbse_result\result_report_controller) so all report, grade,
 * percentage and rank calculations are reused 1:1 — never re-implemented.
 */
class ResultReportApiController extends BaseResultApiController
{
    /**
     * Report types handled by the legacy show_result_report() method.
     */
    private const REPORT_TYPES = [
        'overall_report',
        'merit_report',
        'subject_progress_report',
        'classwise_report',
        'classwise_grade_report',
        'marks_report',
        'weightage_conversion_report',
        'created_exam_report',
    ];

    /**
     * GET /api/result/result-report
     * Filter metadata for the report screen (exam master list of the institute).
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(result_report_controller::class, 'index', $request);

            return $this->success($data);
        });
    }

    /**
     * POST /api/result/result-report/show
     * Generate one of the result reports (structured JSON, same data the
     * Blade views receive). Body: report_of + grade/standard/division and
     * the report-specific filters (term, subject, additional_subjects,
     * exam_type, exam_create, top_students, roll_no, from_date, to_date).
     */
    public function show(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'report_of'           => 'required|in:' . implode(',', self::REPORT_TYPES),
                'grade'               => 'required',
                'standard'            => 'required',
                'division'            => 'required',
                'term'                => 'nullable',
                'subject'             => 'required_if:report_of,subject_progress_report,weightage_conversion_report',
                'additional_subjects' => 'required_if:report_of,marks_report|array',
                'exam_type'           => 'required_if:report_of,created_exam_report',
            ]);

            $this->hydrateSuperglobals($request);

            $data = $this->delegate(result_report_controller::class, 'show_result_report', $request);

            return $this->success($data);
        });
    }

    /**
     * GET /api/result/result-report/standardwise-subjects?std_id=
     * Subjects mapped to a standard (sub_std_map), for the report filters.
     */
    public function standardwiseSubjects(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, ['std_id' => 'required']);

            $rows = $this->delegate(result_report_controller::class, 'ajax_StandardwiseSubject', $request);

            return $this->listResponse($rows, $request, ['display_name', 'subject_name']);
        });
    }

    /**
     * GET /api/result/result-report/download-overall-excel?grade=&standard=&division=
     * Overall report Excel export (passthrough of the legacy .xls stream).
     *
     * The legacy export reads session('over_all_data') which the web flow
     * fills in a previous request; the API is stateless, so the overall
     * report is (re)generated in-memory first, then streamed.
     */
    public function downloadOverallExcel(Request $request)
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'grade'    => 'required',
                'standard' => 'required',
                'division' => 'required',
            ]);

            $request->merge(['report_of' => 'overall_report']);
            $this->hydrateSuperglobals($request);

            // Populates session('over_all_data') for this request only.
            $this->delegate(result_report_controller::class, 'show_result_report', $request);

            // Streams OverallReport.xls directly (echo + exit in legacy code).
            return app(result_report_controller::class)->downloadOverAllReportExcel();
        });
    }

    /**
     * The legacy report helpers (getRank/getClasswise/getWRTData/getMarkwise
     * and the overall/created-exam branches) read $_REQUEST directly in their
     * type=API branches. Requests with a JSON body do not populate $_REQUEST,
     * so mirror the needed inputs into the superglobal before delegating.
     */
    private function hydrateSuperglobals(Request $request): void
    {
        foreach (['grade', 'standard', 'division', 'exam_type', 'exam_create'] as $key) {
            if ($request->has($key)) {
                $_REQUEST[$key] = $request->input($key);
            }
        }

        $_REQUEST['syear']            = $request->input('syear', session('syear'));
        $_REQUEST['sub_institute_id'] = $request->input('sub_institute_id', session('sub_institute_id'));
    }
}
