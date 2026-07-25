<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\new_result\allResultController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * REST API for All Results (a student's generated result cards across years
 * and terms, table result_html).
 *
 * Delegates every operation to the existing web controller
 * (result\new_result\allResultController) so business logic is reused 1:1.
 *
 * NOTE: the web controller resolves the student from session user_id, so
 * these endpoints are meant to be called with a STUDENT token.
 */
class AllResultsApiController extends BaseResultApiController
{
    /**
     * GET /api/result/all-results
     * List the logged-in student's generated results, one row per year/term
     * (search, sort, pagination).
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(allResultController::class, 'index', $request);

            return $this->listResponse(
                $data['result_data'] ?? [],
                $request,
                ['name', 'title', 'syear']
            );
        });
    }

    /**
     * GET /api/result/all-results/{id}
     * Render one result_html row as a PDF and return its public link
     * (the existing API branch of the web create() method).
     *
     * Query: student_id (optional, only used in the generated file name).
     */
    public function show(Request $request, $id)
    {
        return $this->run(function () use ($request, $id) {
            if (! DB::table('result_html')->where('id', $id)->exists()) {
                return $this->error('Record not found.', 404);
            }

            $request->merge(['id' => $id]);

            $data = $this->delegate(allResultController::class, 'create', $request);

            return $this->success($data);
        });
    }
}
