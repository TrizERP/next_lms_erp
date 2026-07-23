<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\result_master\consolidateReportController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for the Consolidate Report (all terms / exams / subjects marks
 * grid for a class).
 *
 * Delegates to the existing web controller
 * (result\result_master\consolidateReportController) so all report
 * calculations are reused 1:1 — nothing is re-implemented.
 */
class ConsolidateReportApiController extends BaseResultApiController
{
    /**
     * GET /api/result/consolidate-report
     * Structured consolidated marks report for a class.
     * Required query params: grade, standard, division.
     *
     * Returns: totalExams, termList, examMasterWise (term -> exam title ->
     * subject -> exam), studentMarks (per student per term per exam) and the
     * selected grade/standard/division ids.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'grade'    => 'required',
                'standard' => 'required',
                'division' => 'required',
            ]);

            // The report data lives in the web controller's create() method.
            $data = $this->delegate(consolidateReportController::class, 'create', $request);

            // Drop the legacy placeholder status/message keys (the web view
            // ignores them; they would only mislead API consumers).
            unset($data['status'], $data['message']);

            return $this->success($data);
        });
    }
}
