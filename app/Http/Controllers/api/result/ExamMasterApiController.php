<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\ExamMaster\ExamMasterController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for Exam Master.
 *
 * Delegates every operation to the existing web controller
 * (result\ExamMaster\ExamMasterController) so business logic is reused 1:1.
 */
class ExamMasterApiController extends BaseResultApiController
{
    /**
     * GET /api/result/exam-master
     * List exams (search, filter by standard_id, sort, pagination).
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $rows = app(ExamMasterController::class)->getData(
                session('sub_institute_id'),
                $request->input('standard_id', ''),
                'API'
            );

            return $this->listResponse($rows, $request, ['ExamTitle', 'std_name', 'term', 'Code']);
        });
    }

    /**
     * GET /api/result/exam-master/create
     * Form defaults: next code / sort order + term & standard dropdowns.
     */
    public function create(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(ExamMasterController::class, 'create', $request);

            return $this->success($data);
        });
    }

    /**
     * POST /api/result/exam-master
     */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'Code'         => 'required',
                'ExamTitle'    => 'required|string',
                'SortOrder'    => 'required',
                'all_standard' => 'required|array|min:1',
                'all_term'     => 'required|array|min:1',
                'weightage'    => 'nullable',
            ]);

            $data = $this->delegate(ExamMasterController::class, 'store', $request);

            return $this->success($data, $data['message'] ?? 'Data Saved', 201);
        });
    }

    /**
     * GET /api/result/exam-master/{id}
     * Single exam with edit dropdown data (same as web edit screen).
     */
    public function show(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $data = $this->delegate(ExamMasterController::class, 'edit', $request, $id);

            if (empty($data)) {
                return $this->error('Record not found.', 404);
            }

            return $this->success($data);
        });
    }

    /**
     * PUT /api/result/exam-master/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $this->validate($request, [
                'ExamTitle'    => 'required|string',
                'SortOrder'    => 'required',
                'all_standard' => 'required|array|min:1',
                'all_term'     => 'required|array|min:1',
                'weightage'    => 'nullable',
            ]);

            $data = $this->delegate(ExamMasterController::class, 'update', $request, $id);

            return $this->success($data, $data['message'] ?? 'Data Saved');
        });
    }

    /**
     * DELETE /api/result/exam-master/{id}
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $data = $this->delegate(ExamMasterController::class, 'destroy', $request, $id);

            return $this->success($data, $data['message'] ?? 'Data Deleted');
        });
    }

    /**
     * DELETE /api/result/exam-master (bulk)
     * Body: { "ids": [1,2,3] }
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, ['ids' => 'required|array|min:1']);

            foreach ($request->input('ids') as $id) {
                $this->delegate(ExamMasterController::class, 'destroy', $request, $id);
            }

            return $this->success(null, 'Data Deleted');
        });
    }

    /**
     * GET /api/result/exam-master/dropdown
     * Lightweight id/name pairs for selects.
     */
    public function dropdown(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $rows = app(ExamMasterController::class)->getData(
                session('sub_institute_id'),
                $request->input('standard_id', ''),
                'API'
            );

            $out = [];
            foreach ($rows as $row) {
                $out[] = [
                    'id'          => $row->Id,
                    'name'        => $row->ExamTitle,
                    'standard_id' => $row->standard_id,
                    'term_id'     => $row->term_id,
                ];
            }

            return $this->success($out);
        });
    }
}
