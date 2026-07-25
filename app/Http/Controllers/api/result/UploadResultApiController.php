<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\upload_result\upload_result_controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for Upload Result (attach an externally generated result file -
 * image/PDF - to each student per term, table upload_result).
 *
 * Delegates every operation to the existing web controller
 * (result\upload_result\upload_result_controller) so business logic is
 * reused 1:1.
 *
 * NOTE: a student-facing endpoint POST /uploadResultAPI (routes/result.php)
 * already exists to LIST a student's uploaded results by term; it is kept
 * as-is and documented alongside this module - not duplicated here.
 */
class UploadResultApiController extends BaseResultApiController
{
    /**
     * GET /api/result/upload-result
     * Screen context only (the web index carries no data). The student list
     * is served by GET upload-result/create.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(upload_result_controller::class, 'index', $request);

            return $this->success($data);
        });
    }

    /**
     * GET /api/result/upload-result/create
     * Students of a class with the file already uploaded for the given term,
     * if any (search, sort, pagination).
     *
     * Query: grade, standard, division (optional filters), term (required).
     */
    public function create(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'grade'    => 'nullable',
                'standard' => 'nullable',
                'division' => 'nullable',
                'term'     => 'required',
            ]);

            $data = $this->delegate(upload_result_controller::class, 'create', $request);

            return $this->listResponse(
                $data['student_data'] ?? [],
                $request,
                ['student_name', 'enrollment_no', 'uniqueid', 'standard_name', 'division_name', 'file_name']
            );
        });
    }

    /**
     * POST /api/result/upload-result
     * Upload result files for the selected students (multipart/form-data).
     *
     * Fields: students[] (student ids), grade_id, standard_id, term_id,
     * division_id (optional), image[<student_id>] = file (one file per
     * selected student, keyed by the student id).
     */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'students'    => 'required|array|min:1',
                'grade_id'    => 'required',
                'standard_id' => 'required',
                'term_id'     => 'required',
                'image'       => 'nullable|array',
                'image.*'     => 'file',
            ]);

            // Request is passed through as-is so the uploaded files reach the
            // existing store() logic untouched.
            $data = $this->delegate(upload_result_controller::class, 'store', $request);

            return $this->success($data, $data['message'] ?? 'Result Uploaded Successfully', 201);
        });
    }
}
