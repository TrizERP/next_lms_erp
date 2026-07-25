<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\cbse_result\cbse_1t5_t2_result_controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for the CBSE Std 1-5 Term 2 report card generator (1t9_s1_t2
 * layout: term 1 and term 2 blocks in a single card).
 *
 * Delegates every operation to the existing web controller
 * (result\cbse_result\cbse_1t5_t2_result_controller) so all mark, grade,
 * percentage, co-scholastic and attendance calculations are reused 1:1 —
 * never re-implemented.
 */
class Cbse1t5T2ResultApiController extends BaseResultApiController
{
    /**
     * GET /api/result/cbse-1t5-t2-result
     * Report filter screen bootstrap (legacy index; the class/division
     * filters themselves come from the shared lookup APIs).
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(cbse_1t5_t2_result_controller::class, 'index', $request);

            return $this->success($data);
        });
    }

    /**
     * POST /api/result/cbse-1t5-t2-result/show
     * Generate the Term-2 style report card data for every student of the
     * selected class: term 1 (`data`) + term 2 (`term_2_data`) blocks keyed
     * by student_id with exams, subject marks, percentage, final grade,
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

            $data = $this->delegate(cbse_1t5_t2_result_controller::class, 'show_result', $request);

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
