<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\cbse_result\overall_mark_report_controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for the Overall Mark Report (both-terms overview card).
 *
 * Delegates every operation to the existing web controller
 * (result\cbse_result\overall_mark_report_controller) so all mark, grade,
 * percentage, co-scholastic and attendance calculations are reused 1:1 —
 * never re-implemented.
 */
class OverallMarkReportApiController extends BaseResultApiController
{
    /**
     * GET /api/result/overall-mark-report
     * Report filter screen bootstrap (legacy index; the class/division
     * filters themselves come from the shared lookup APIs).
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(overall_mark_report_controller::class, 'index', $request);

            return $this->success($data);
        });
    }

    /**
     * POST /api/result/overall-mark-report/show
     * Generate the overall mark report for every student of the selected
     * class (`data` keyed by student_id: term-wise exams, subject marks,
     * subject-wise exam breakup, percentage, final grade, co-scholastic
     * grades per term, attendance, trust headings and signature settings).
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

            $data = $this->delegate(overall_mark_report_controller::class, 'show_result', $request);

            return $this->success($data);
        });
    }

    /**
     * The legacy show_result()/getGradeScale()/getCoArea()/getAttendance()/
     * getHeadings()/getExamMasterSettigs() read $_REQUEST directly. Requests
     * with a JSON body do not populate $_REQUEST, so mirror the needed inputs
     * into the superglobal before delegating.
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
