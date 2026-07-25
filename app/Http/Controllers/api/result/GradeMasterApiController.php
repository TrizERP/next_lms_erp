<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\GradeMaster\GradeMasterController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for Grade Master (grade scales + their grade rows).
 *
 * Delegates every operation to the existing web controller
 * (result\GradeMaster\GradeMasterController) so business logic is reused 1:1.
 *
 * Note: the web controller has no edit/update methods, so no GET/PUT {id} is
 * exposed. destroy() deletes GRADE DATA rows (grade_master_data.id), matching
 * the web behaviour exactly.
 */
class GradeMasterApiController extends BaseResultApiController
{
    /**
     * GET /api/result/grade-master
     * List grade scales, each with its nested grade rows (search, sort, pagination).
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = app(GradeMasterController::class)->getData();

            $rows = [];
            foreach ($data['grade'] ?? [] as $grade) {
                $row               = $grade->toArray();
                $row['grade_data'] = isset($data['grade_data'][$grade->id])
                    ? $data['grade_data'][$grade->id]->toArray()
                    : [];
                $rows[]            = $row;
            }

            return $this->listResponse($rows, $request, ['grade_name']);
        });
    }

    /**
     * GET /api/result/grade-master/create-data/{id}
     * Form defaults for adding grade rows to the given grade scale
     * (web route grade_master/createData/{id} -> AddAllData).
     */
    public function createData(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $data = $this->delegate(GradeMasterController::class, 'AddAllData', $request, $id);

            return $this->success($data);
        });
    }

    /**
     * POST /api/result/grade-master
     * Two modes, exactly like the web form:
     *  - default: create a grade scale  -> { grade_name, sort_order }
     *  - add_type=add_grade_data: create a grade row
     *    -> { add_type, grade_id, title, breakoff, gp, sort_order, comment }
     */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            if ($request->input('add_type') === 'add_grade_data') {
                $this->validate($request, [
                    'grade_id'   => 'required',
                    'title'      => 'required|string',
                    'breakoff'   => 'nullable',
                    'gp'         => 'nullable',
                    'sort_order' => 'nullable',
                    'comment'    => 'nullable|string',
                ]);
            } else {
                $this->validate($request, [
                    'grade_name' => 'required|string',
                    'sort_order' => 'nullable',
                ]);
            }

            // The legacy controller branches on the PHP superglobal
            // ($_REQUEST['add_type']), which is empty for JSON bodies -
            // bridge it here without touching the web controller.
            if ($request->filled('add_type')) {
                $_REQUEST['add_type'] = $request->input('add_type');
            }

            $data = $this->delegate(GradeMasterController::class, 'store', $request);

            return $this->success($data, $data['message'] ?? 'Data Saved', 201);
        });
    }

    /**
     * DELETE /api/result/grade-master/{id}
     * Deletes a grade DATA row (grade_master_data.id) - same as the web action.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $data = $this->delegate(GradeMasterController::class, 'destroy', $request, $id);

            return $this->success($data, $data['message'] ?? 'Data Deleted');
        });
    }

    /**
     * DELETE /api/result/grade-master (bulk)
     * Body: { "ids": [1,2,3] } - grade_master_data row ids.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, ['ids' => 'required|array|min:1']);

            foreach ($request->input('ids') as $id) {
                $this->delegate(GradeMasterController::class, 'destroy', $request, $id);
            }

            return $this->success(null, 'Data Deleted');
        });
    }

    /**
     * GET /api/result/grade-master/dropdown
     * Lightweight id/name pairs of grade scales for selects.
     */
    public function dropdown(Request $request): JsonResponse
    {
        return $this->run(function () {
            $data = app(GradeMasterController::class)->getData();

            $out = [];
            foreach ($data['grade'] ?? [] as $grade) {
                $out[] = [
                    'id'   => $grade->id,
                    'name' => $grade->grade_name,
                ];
            }

            return $this->success($out);
        });
    }
}
