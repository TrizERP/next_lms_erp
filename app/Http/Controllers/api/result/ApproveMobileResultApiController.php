<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\approve_mobile_result\approve_mobile_result_controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for Approve Mobile Result (allow/deny generated result cards on
 * the mobile app, result_html.is_allowed flag).
 *
 * Delegates every operation to the existing web controller
 * (result\approve_mobile_result\approve_mobile_result_controller) so business
 * logic is reused 1:1.
 */
class ApproveMobileResultApiController extends BaseResultApiController
{
    /**
     * GET /api/result/approve-mobile-result
     * Screen defaults: academic terms of the current year + session context.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(approve_mobile_result_controller::class, 'index', $request);

            return $this->success($data);
        });
    }

    /**
     * GET /api/result/approve-mobile-result/create
     * Generated result cards of a class (result_html rows joined with the
     * student) with their current is_allowed flag (search, sort, pagination).
     *
     * Query: grade, standard, division, term_id.
     */
    public function create(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'grade'    => 'required',
                'standard' => 'required',
                'division' => 'required',
                'term_id'  => 'required',
            ]);

            $data = $this->delegate(approve_mobile_result_controller::class, 'create', $request);

            return $this->listResponse(
                $data['result_report'] ?? [],
                $request,
                ['student_name', 'enrollment_no', 'standard', 'division']
            );
        });
    }

    /**
     * POST /api/result/approve-mobile-result
     * Approve (students[]) / un-approve (student_id[]) result cards.
     *
     * Body: students[] = student ids to set is_allowed=Y,
     *       student_id[] = student ids to set is_allowed=N,
     *       grade_id, standard_id, division_id, term_id.
     */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            // Web controller loops both arrays unconditionally - default them.
            $request->merge([
                'students'   => $request->input('students', []),
                'student_id' => $request->input('student_id', []),
            ]);

            $this->validate($request, [
                'students'    => 'array',
                'student_id'  => 'array',
                'grade_id'    => 'required',
                'standard_id' => 'required',
                'division_id' => 'required',
                'term_id'     => 'required',
            ]);

            $data = $this->delegate(approve_mobile_result_controller::class, 'store', $request);

            return $this->success($data, $data['message'] ?? 'Success');
        });
    }
}
