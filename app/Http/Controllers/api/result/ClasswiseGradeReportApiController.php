<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\classwiseGradeReportController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for Classwise Grade Report.
 *
 * Delegates every operation to the existing web controller
 * (result\classwiseGradeReportController) so marks aggregation, grade
 * conversion, rank and appreciation/remark logic are reused 1:1 — never
 * re-implemented.
 */
class ClasswiseGradeReportApiController extends BaseResultApiController
{
    /**
     * GET /api/result/classwise-grade-report
     * Web index only re-displays the last generated report from the session
     * flash; the API is stateless, so this returns null — use GET
     * classwise-grade-report/create to generate the report.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->mergeContext($request);

            $data = $this->delegate(classwiseGradeReportController::class, 'index', $request);

            return $this->success($data);
        });
    }

    /**
     * GET /api/result/classwise-grade-report/create
     * Generate the classwise grade report: per-student exam/subject marks
     * (with Grand Total & Average when no single exam is selected), rank,
     * appreciation + remark texts and the exam/subject header matrix.
     * Query: grade, standard, division, term, exam_type (exam title).
     */
    public function create(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'standard' => 'required',
                'grade'    => 'nullable',
                'division' => 'nullable',
                'term'     => 'nullable',
            ]);

            $this->mergeContext($request);

            $data = $this->delegate(classwiseGradeReportController::class, 'create', $request);

            return $this->success($data);
        });
    }

    /**
     * GET /api/result/classwise-grade-report/exam-create-names?stdId=&termID=
     * Distinct created-exam titles of a standard (optionally term filtered) —
     * same as web route get-exam-create-name.
     */
    public function examCreateNames(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'stdId'  => 'required',
                'termID' => 'nullable',
            ]);

            $this->mergeContext($request);

            $rows = $this->delegate(classwiseGradeReportController::class, 'getCreateExamName', $request);

            return $this->listResponse($rows, $request, ['title']);
        });
    }

    /**
     * The legacy type=API branches read sub_institute_id/syear from the
     * request (not the session), and getRank() reads them from $_REQUEST
     * directly — supply both from the hydrated session when absent.
     */
    private function mergeContext(Request $request): void
    {
        $request->merge([
            'sub_institute_id' => $request->input('sub_institute_id', session('sub_institute_id')),
            'syear'            => $request->input('syear', session('syear')),
        ]);

        $_REQUEST['sub_institute_id'] = $request->input('sub_institute_id');
        $_REQUEST['syear']            = $request->input('syear');
    }
}
