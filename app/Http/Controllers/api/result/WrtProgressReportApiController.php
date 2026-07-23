<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\cbse_result\WRT_progress_report_controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for the WRT (Weekly Revision Test) progress report
 * (marks + grade + class rank per exam).
 *
 * Delegates every operation to the existing web controller
 * (result\cbse_result\WRT_progress_report_controller) so all marks,
 * percentage, grade-scale and rank calculations are reused 1:1 —
 * never re-implemented.
 */
class WrtProgressReportApiController extends BaseResultApiController
{
    /**
     * GET /api/result/wrt-progress-report
     * Filter screen bootstrap: the institute's exam master list
     * (`exam_master`) used to pick the exam_type filter.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(WRT_progress_report_controller::class, 'index', $request);

            return $this->success($data);
        });
    }

    /**
     * POST /api/result/wrt-progress-report/show
     * Generate the WRT progress report for the selected class, exam type and
     * date range: `WRT_data` (per student: exam-wise obtained/total points,
     * percentage, grade, class rank; plus total_student), `WRT_exam_master`
     * (exam headings in range), `all_student` roster and the report header.
     *
     * Body: grade, standard, division (ids), from_date, to_date (Y-m-d),
     * exam_type (result_exam_master id, optional).
     */
    public function show(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'grade'     => 'required',
                'standard'  => 'required',
                'division'  => 'required',
                'from_date' => 'required|date',
                'to_date'   => 'required|date',
                'exam_type' => 'nullable',
            ]);

            $this->hydrateSuperglobals($request);

            $data = $this->delegate(WRT_progress_report_controller::class, 'show_result', $request);

            return $this->success($data);
        });
    }

    /**
     * The legacy show_result()/getWRTData()/getAllExamMaster()/getRank()
     * read $_REQUEST directly (their type=API branches use syear and
     * sub_institute_id from the superglobal too). Requests with a JSON body
     * do not populate $_REQUEST, so mirror the needed inputs into the
     * superglobal before delegating.
     */
    private function hydrateSuperglobals(Request $request): void
    {
        foreach (['grade', 'standard', 'division', 'from_date', 'to_date'] as $key) {
            $_REQUEST[$key] = $request->input($key);
        }

        // show_result() reads $_REQUEST['exam_type'] unconditionally.
        $_REQUEST['exam_type']        = $request->input('exam_type', '');
        $_REQUEST['syear']            = $request->input('syear', session('syear'));
        $_REQUEST['sub_institute_id'] = $request->input('sub_institute_id', session('sub_institute_id'));
    }
}
