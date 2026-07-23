<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\cbse_result\cbse_11_t2_result_controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for the CBSE Std 11 Term 2 report card generator.
 *
 * Delegates every operation to the existing web controller
 * (result\cbse_result\cbse_11_t2_result_controller) so all mark, grade,
 * percentage, co-scholastic and attendance calculations are reused 1:1 —
 * never re-implemented.
 */
class Cbse11T2ResultApiController extends BaseResultApiController
{
    /**
     * GET /api/result/cbse-11-t2-result
     * Report filter screen bootstrap (legacy index; the class/division
     * filters themselves come from the shared lookup APIs).
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(cbse_11_t2_result_controller::class, 'index', $request);

            return $this->success($data);
        });
    }

    /**
     * POST /api/result/cbse-11-t2-result/show
     * Generate the Std 11 Term-2 report card data for every student of the
     * selected class (`data` keyed by student_id: term-wise exams, subject
     * marks incl. subject-wise exam breakup, percentage, final grade,
     * co-scholastic grades and attendance) plus the report header/footer
     * configuration.
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

            $data = $this->delegate(cbse_11_t2_result_controller::class, 'show_result', $request);

            return $this->success($data);
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
