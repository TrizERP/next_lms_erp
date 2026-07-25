<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\result_remark_master\result_remark_master_controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for Student Result Remark Master.
 *
 * Delegates every operation to the existing web controller
 * (result\result_remark_master\result_remark_master_controller) so business
 * logic is reused 1:1.
 *
 * Note: the web create() only returns an empty Blade form (no defaults), so no
 * GET create endpoint is exposed. The web edit()/destroy() take ($id, Request)
 * in that order - delegation below matches those signatures exactly.
 */
class ResultRemarkMasterApiController extends BaseResultApiController
{
    /**
     * GET /api/result/result-remark-master
     * List result remarks (search, sort, pagination).
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $rows = app(result_remark_master_controller::class)->getData();

            return $this->listResponse($rows, $request, ['title', 'remark_status']);
        });
    }

    /**
     * POST /api/result/result-remark-master
     * Body: { title, result_status, sort_order }
     */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'title'         => 'required|string',
                'result_status' => 'nullable',
                'sort_order'    => 'nullable',
            ]);

            $data = $this->delegate(result_remark_master_controller::class, 'store', $request);

            return $this->success($data, $data['message'] ?? 'Data Saved', 201);
        });
    }

    /**
     * GET /api/result/result-remark-master/{id}
     * Single remark (same data as the web edit screen).
     */
    public function show(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $data = $this->delegate(result_remark_master_controller::class, 'edit', $id, $request);

            if (empty($data)) {
                return $this->error('Record not found.', 404);
            }

            return $this->success($data);
        });
    }

    /**
     * PUT /api/result/result-remark-master/{id}
     * Body: { title, result_status, sort_order, term }
     */
    public function update(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $this->validate($request, [
                'title'         => 'required|string',
                'result_status' => 'nullable',
                'sort_order'    => 'nullable',
                'term'          => 'nullable',
            ]);

            $data = $this->delegate(result_remark_master_controller::class, 'update', $request, $id);

            return $this->success($data, $data['message'] ?? 'Data Saved');
        });
    }

    /**
     * DELETE /api/result/result-remark-master/{id}
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $data = $this->delegate(result_remark_master_controller::class, 'destroy', $id, $request);

            return $this->success($data, $data['message'] ?? 'Data Deleted');
        });
    }

    /**
     * DELETE /api/result/result-remark-master (bulk)
     * Body: { "ids": [1,2,3] }
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, ['ids' => 'required|array|min:1']);

            foreach ($request->input('ids') as $id) {
                $this->delegate(result_remark_master_controller::class, 'destroy', $id, $request);
            }

            return $this->success(null, 'Data Deleted');
        });
    }

    /**
     * GET /api/result/result-remark-master/dropdown
     * Lightweight id/name pairs for selects.
     */
    public function dropdown(Request $request): JsonResponse
    {
        return $this->run(function () {
            $rows = app(result_remark_master_controller::class)->getData();

            $out = [];
            foreach ($rows as $row) {
                $out[] = [
                    'id'            => $row->id,
                    'name'          => $row->title,
                    'remark_status' => $row->remark_status,
                ];
            }

            return $this->success($out);
        });
    }
}
