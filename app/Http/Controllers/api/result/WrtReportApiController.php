<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\cbse_result\WRT_report_controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for the WRT (Weekly Revision Test) report.
 *
 * Delegates every operation to the existing web controller
 * (result\cbse_result\WRT_report_controller) so all exam/marks/percentage
 * calculations are reused 1:1 — never re-implemented.
 */
class WrtReportApiController extends BaseResultApiController
{
    /**
     * GET /api/result/wrt-report
     * Report filter screen bootstrap (legacy index; the class/division and
     * date filters themselves come from the shared lookup APIs).
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(WRT_report_controller::class, 'index', $request);

            return $this->success($data);
        });
    }

    /**
     * POST /api/result/wrt-report/show
     * Generate the WRT report for the selected class and date range:
     * `WRT_data` (per student, per exam title: subject-wise obtained/total
     * points, percentage, exam date/day, absence flag), `WRT_exam_master`
     * (exam headings in range), `all_student` roster and the report header.
     *
     * Body: grade, standard, division (ids), from_date, to_date (Y-m-d).
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
            ]);

            $this->hydrateSuperglobals($request);

            $data = $this->delegate(WRT_report_controller::class, 'show_result', $request);

            return $this->success($data);
        });
    }

    /**
     * The legacy show_result()/getWRTData()/getAllExamMaster()/getHeader()
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

        $_REQUEST['syear']            = $request->input('syear', session('syear'));
        $_REQUEST['sub_institute_id'] = $request->input('sub_institute_id', session('sub_institute_id'));
    }
}
