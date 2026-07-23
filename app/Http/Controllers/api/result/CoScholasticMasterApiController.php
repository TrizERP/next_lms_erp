<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\co_scholastic_master\co_scholastic_master_controller;
use App\Models\result\co_scholastic_master\co_scholastic_master;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for Co-Scholastic Master (co-scholastic parent categories,
 * table result_co_scholastic_parent).
 *
 * Delegates every operation to the existing web controller
 * (result\co_scholastic_master\co_scholastic_master_controller) so business
 * logic is reused 1:1.
 */
class CoScholasticMasterApiController extends BaseResultApiController
{
    /**
     * GET /api/result/co-scholastic-master
     * List co-scholastic parent categories (search, sort, pagination).
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $rows = app(co_scholastic_master_controller::class)->getData();

            return $this->listResponse($rows, $request, ['title']);
        });
    }

    /**
     * GET /api/result/co-scholastic-master/create
     * Form defaults: next sort order + existing categories (ddValue).
     */
    public function create(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(co_scholastic_master_controller::class, 'create', $request);

            return $this->success($data);
        });
    }

    /**
     * POST /api/result/co-scholastic-master
     */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'title'      => 'required|string',
                'sort_order' => 'required',
            ]);

            $data = $this->delegate(co_scholastic_master_controller::class, 'store', $request);

            return $this->success($data, $data['message'] ?? 'Data Saved', 201);
        });
    }

    /**
     * GET /api/result/co-scholastic-master/{id}
     * Single category with edit dropdown data (same as web edit screen).
     */
    public function show(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            if (! co_scholastic_master::whereKey($id)->exists()) {
                return $this->error('Record not found.', 404);
            }

            $data = $this->delegate(co_scholastic_master_controller::class, 'edit', $request, $id);

            return $this->success($data);
        });
    }

    /**
     * PUT /api/result/co-scholastic-master/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $this->validate($request, [
                'title'      => 'required|string',
                'sort_order' => 'required',
            ]);

            if (! co_scholastic_master::whereKey($id)->exists()) {
                return $this->error('Record not found.', 404);
            }

            $data = $this->delegate(co_scholastic_master_controller::class, 'update', $request, $id);

            return $this->success($data, $data['message'] ?? 'Data Saved');
        });
    }

    /**
     * DELETE /api/result/co-scholastic-master/{id}
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $data = $this->delegate(co_scholastic_master_controller::class, 'destroy', $request, $id);

            return $this->success($data, $data['message'] ?? 'Data Deleted');
        });
    }

    /**
     * DELETE /api/result/co-scholastic-master/bulk
     * Body: { "ids": [1,2,3] }
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, ['ids' => 'required|array|min:1']);

            foreach ($request->input('ids') as $id) {
                $this->delegate(co_scholastic_master_controller::class, 'destroy', $request, $id);
            }

            return $this->success(null, 'Data Deleted');
        });
    }

    /**
     * GET /api/result/co-scholastic-master/dropdown
     * Lightweight id/name pairs for selects.
     */
    public function dropdown(Request $request): JsonResponse
    {
        return $this->run(function () {
            $rows = app(co_scholastic_master_controller::class)->ddValue();

            $out = [];
            foreach ($rows as $row) {
                $out[] = [
                    'id'         => $row['id'],
                    'name'       => $row['title'],
                    'sort_order' => $row['sort_order'],
                ];
            }

            return $this->success($out);
        });
    }
}
