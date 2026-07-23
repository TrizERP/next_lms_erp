<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\std_grd_maping\std_grd_maping_controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for Standard - Grade (scale) Mapping.
 *
 * Delegates every operation to the existing web controller
 * (result\std_grd_maping\std_grd_maping_controller) so business logic is reused 1:1.
 */
class StdGradeMappingApiController extends BaseResultApiController
{
    /**
     * GET /api/result/standard-grade-mapping
     * List grade-scale to standards mappings (search, sort, pagination).
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $rows = array_values(app(std_grd_maping_controller::class)->getData());

            return $this->listResponse($rows, $request, ['scale_name', 'grade_name', 'standard_name']);
        });
    }

    /**
     * GET /api/result/standard-grade-mapping/create
     * Form defaults: grade scales not yet mapped (ddValue).
     */
    public function create(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(std_grd_maping_controller::class, 'create', $request);

            return $this->success($data);
        });
    }

    /**
     * POST /api/result/standard-grade-mapping
     * Body: { grade_scale, standard: [standard_id, ...] }
     */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'grade_scale' => 'required',
                'standard'    => 'required|array|min:1',
            ]);

            // The legacy controller iterates the PHP superglobal
            // ($_REQUEST['standard']), which is empty for JSON bodies -
            // bridge it here without touching the web controller.
            $_REQUEST['standard'] = $request->input('standard');

            $data = $this->delegate(std_grd_maping_controller::class, 'store', $request);

            return $this->success($data, $data['message'] ?? 'Data Saved', 201);
        });
    }

    /**
     * GET /api/result/standard-grade-mapping/{id}
     * Single mapping with all grade scales for the edit dropdown (ddValue).
     */
    public function show(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $data = $this->delegate(std_grd_maping_controller::class, 'edit', $request, $id);

            if (empty($data['id'])) {
                return $this->error('Record not found.', 404);
            }

            return $this->success($data);
        });
    }

    /**
     * PUT /api/result/standard-grade-mapping/{id}
     * Body: { grade_scale, standard: [standard_id, ...] } - the web logic
     * deletes the old mapping rows for the scale and recreates them.
     */
    public function update(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $this->validate($request, [
                'grade_scale' => 'required',
                'standard'    => 'required|array|min:1',
            ]);

            if (! $this->mappingExists($id)) {
                return $this->error('Record not found.', 404);
            }

            // Bridge the superglobal the legacy controller reads (see store()).
            $_REQUEST['standard'] = $request->input('standard');

            $data = $this->delegate(std_grd_maping_controller::class, 'update', $request, $id);

            return $this->success($data, $data['message'] ?? 'Data Saved');
        });
    }

    /**
     * DELETE /api/result/standard-grade-mapping/{id}
     * Deletes ALL mapping rows of the record's grade scale (web behaviour).
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            if (! $this->mappingExists($id)) {
                return $this->error('Record not found.', 404);
            }

            $data = $this->delegate(std_grd_maping_controller::class, 'destroy', $request, $id);

            return $this->success($data, $data['message'] ?? 'Data Deleted');
        });
    }

    /**
     * DELETE /api/result/standard-grade-mapping (bulk)
     * Body: { "ids": [1,2,3] }
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, ['ids' => 'required|array|min:1']);

            foreach ($request->input('ids') as $id) {
                if ($this->mappingExists($id)) {
                    $this->delegate(std_grd_maping_controller::class, 'destroy', $request, $id);
                }
            }

            return $this->success(null, 'Data Deleted');
        });
    }

    /**
     * GET /api/result/standard-grade-mapping/dropdown
     * Lightweight id/name pairs (mapping id + scale name) for selects.
     */
    public function dropdown(Request $request): JsonResponse
    {
        return $this->run(function () {
            $rows = app(std_grd_maping_controller::class)->getData();

            $out = [];
            foreach ($rows as $row) {
                $out[] = [
                    'id'          => $row['id'],
                    'name'        => $row['scale_name'],
                    'grade_scale' => $row['grade_scale'],
                ];
            }

            return $this->success($out);
        });
    }

    /**
     * The web update/destroy assume the id exists (undefined index otherwise);
     * guard with the same legacy getData() lookup so the API returns clean 404s.
     */
    private function mappingExists($id): bool
    {
        foreach (app(std_grd_maping_controller::class)->getData() as $row) {
            if ($row['id'] == $id) {
                return true;
            }
        }

        return false;
    }
}
