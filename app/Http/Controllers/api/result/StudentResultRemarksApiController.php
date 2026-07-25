<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\new_result\studentResultRemarksController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for Student Result Remarks (class-teacher remarks per term).
 *
 * Delegates every operation to the existing web controller
 * (result\new_result\studentResultRemarksController) so business logic is
 * reused 1:1.
 */
class StudentResultRemarksApiController extends BaseResultApiController
{
    /**
     * GET /api/result/student-result-remarks
     * Screen context (sub_institute_id + syear). The actual student list is
     * served by GET student-result-remarks/students.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(studentResultRemarksController::class, 'index', $request);

            return $this->success($data);
        });
    }

    /**
     * GET /api/result/student-result-remarks/students
     * Students of a class with their existing remarks for the given term
     * (search, sort, pagination).
     *
     * Query: grade, standard, division, term.
     */
    public function students(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'grade'    => 'nullable',
                'standard' => 'required',
                'division' => 'required',
                'term'     => 'required',
            ]);

            $data = $this->delegate(studentResultRemarksController::class, 'create', $request);

            return $this->listResponse(
                $data['get_students'] ?? [],
                $request,
                ['first_name', 'middle_name', 'last_name', 'enrollment_no', 'roll_no', 'result_remarks']
            );
        });
    }

    /**
     * POST /api/result/student-result-remarks
     * Save/update remarks for the given students.
     *
     * Body: student_id[] (ids), result_remarks[<student_id>] (selected remark),
     * result_remarks_input[<student_id>] (free-text remark), grade_id,
     * standard_id, division_id, term_id.
     */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'student_id'           => 'required|array|min:1',
                'term_id'              => 'required',
                'result_remarks'       => 'nullable|array',
                'result_remarks_input' => 'nullable|array',
            ]);

            $data = $this->delegate(studentResultRemarksController::class, 'store', $request);

            return $this->success($data, $data['message'] ?? 'Added Successfully !', 201);
        });
    }
}
