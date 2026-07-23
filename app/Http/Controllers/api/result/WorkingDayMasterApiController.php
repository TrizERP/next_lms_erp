<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\working_day_master\working_day_master_controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for Working Day Master.
 *
 * Delegates every operation to the existing web controller
 * (result\working_day_master\working_day_master_controller) so business logic
 * is reused 1:1.
 *
 * Note: the web create() only returns an empty Blade form (no defaults) and no
 * dropdown source exists, so no GET create / dropdown endpoints are exposed.
 */
class WorkingDayMasterApiController extends BaseResultApiController
{
    /**
     * GET /api/result/working-day-master
     * List working-day entries per term/standard (search, sort, pagination).
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $rows = app(working_day_master_controller::class)->getData();

            return $this->listResponse($rows, $request, ['term_name', 'grade_name', 'standard_name']);
        });
    }

    /**
     * POST /api/result/working-day-master
     * Body: { term, total_working_day, standard: [standard_id, ...] }
     * Creates one row per standard, exactly like the web form.
     */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'term'              => 'required',
                'total_working_day' => 'required',
                'standard'          => 'required|array|min:1',
            ]);

            // The legacy controller iterates the PHP superglobal
            // ($_REQUEST['standard']), which is empty for JSON bodies -
            // bridge it here without touching the web controller.
            $_REQUEST['standard'] = $request->input('standard');

            $data = $this->delegate(working_day_master_controller::class, 'store', $request);

            return $this->success($data, $data['message'] ?? 'Data Saved', 201);
        });
    }

    /**
     * GET /api/result/working-day-master/{id}
     * Single working-day entry (same data as the web edit screen).
     */
    public function show(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $data = $this->delegate(working_day_master_controller::class, 'edit', $request, $id);

            if (empty($data)) {
                return $this->error('Record not found.', 404);
            }

            return $this->success($data);
        });
    }

    /**
     * PUT /api/result/working-day-master/{id}
     * Body: { term, total_working_day, standard } (single standard id).
     */
    public function update(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $this->validate($request, [
                'term'              => 'required',
                'total_working_day' => 'required',
                'standard'          => 'required',
            ]);

            $data = $this->delegate(working_day_master_controller::class, 'update', $request, $id);

            return $this->success($data, $data['message'] ?? 'Data Saved');
        });
    }

    /**
     * DELETE /api/result/working-day-master/{id}
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $data = $this->delegate(working_day_master_controller::class, 'destroy', $request, $id);

            return $this->success($data, $data['message'] ?? 'Data Deleted');
        });
    }

    /**
     * DELETE /api/result/working-day-master (bulk)
     * Body: { "ids": [1,2,3] }
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, ['ids' => 'required|array|min:1']);

            foreach ($request->input('ids') as $id) {
                $this->delegate(working_day_master_controller::class, 'destroy', $request, $id);
            }

            return $this->success(null, 'Data Deleted');
        });
    }
}
