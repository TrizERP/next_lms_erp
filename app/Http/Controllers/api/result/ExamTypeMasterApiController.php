<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\ExamTypeMaster\ExamTypeMasterController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for Exam Type Master.
 *
 * Delegates every operation to the existing web controller
 * (result\ExamTypeMaster\ExamTypeMasterController) so business logic is reused 1:1.
 */
class ExamTypeMasterApiController extends BaseResultApiController
{
    /**
     * GET /api/result/exam-type-master
     * List exam types (search, sort, pagination).
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $rows = app(ExamTypeMasterController::class)->getData();

            return $this->listResponse($rows, $request, ['Code', 'ExamType', 'ShortName']);
        });
    }

    /**
     * GET /api/result/exam-type-master/create
     * Form defaults: next Code / SortOrder.
     */
    public function create(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(ExamTypeMasterController::class, 'create', $request);

            return $this->success($data);
        });
    }

    /**
     * POST /api/result/exam-type-master
     */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'Code'      => 'required',
                'ExamType'  => 'required|string',
                'ShortName' => 'nullable|string',
                'SortOrder' => 'required',
            ]);

            $data = $this->delegate(ExamTypeMasterController::class, 'store', $request);

            return $this->success($data, $data['message'] ?? 'Data Saved', 201);
        });
    }

    /**
     * GET /api/result/exam-type-master/{id}
     * Single exam type (same data as the web edit screen).
     */
    public function show(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $data = $this->delegate(ExamTypeMasterController::class, 'edit', $request, $id);

            if (empty($data)) {
                return $this->error('Record not found.', 404);
            }

            return $this->success($data);
        });
    }

    /**
     * PUT /api/result/exam-type-master/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $this->validate($request, [
                'Code'      => 'required',
                'ExamType'  => 'required|string',
                'ShortName' => 'nullable|string',
                'SortOrder' => 'required',
            ]);

            $data = $this->delegate(ExamTypeMasterController::class, 'update', $request, $id);

            return $this->success($data, $data['message'] ?? 'Data Saved');
        });
    }

    /**
     * DELETE /api/result/exam-type-master/{id}
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $data = $this->delegate(ExamTypeMasterController::class, 'destroy', $request, $id);

            return $this->success($data, $data['message'] ?? 'Data Deleted');
        });
    }

    /**
     * DELETE /api/result/exam-type-master (bulk)
     * Body: { "ids": [1,2,3] }
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, ['ids' => 'required|array|min:1']);

            foreach ($request->input('ids') as $id) {
                $this->delegate(ExamTypeMasterController::class, 'destroy', $request, $id);
            }

            return $this->success(null, 'Data Deleted');
        });
    }

    /**
     * GET /api/result/exam-type-master/dropdown
     * Lightweight id/name pairs for selects.
     */
    public function dropdown(Request $request): JsonResponse
    {
        return $this->run(function () {
            $rows = app(ExamTypeMasterController::class)->getData();

            $out = [];
            foreach ($rows as $row) {
                $out[] = [
                    'id'   => $row->Id ?? $row->id,
                    'name' => $row->ExamType,
                    'code' => $row->Code,
                ];
            }

            return $this->success($out);
        });
    }
}
