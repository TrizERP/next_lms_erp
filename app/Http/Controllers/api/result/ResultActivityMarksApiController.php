<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\result_activity_marks\resultActivityMarksController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for HPC Activity Entry (result activity marks).
 *
 * Delegates every operation to the existing web controller
 * (result\result_activity_marks\resultActivityMarksController) so business
 * logic - skillset/activity resolution, activity-group marks upsert - is
 * reused 1:1, never re-implemented.
 */
class ResultActivityMarksApiController extends BaseResultApiController
{
    /**
     * GET /api/result/hpc-activity-entry
     * Landing data: available result skillsets and the termwise_hpc setting.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(resultActivityMarksController::class, 'index', $request);

            return $this->success($data);
        });
    }

    /**
     * GET /api/result/hpc-activity-entry/create
     * Activity marks grid: students of the selected class, skillsets,
     * activity master / sub-activity dropdowns, activity groups (mark
     * options) and each student's existing activity mark.
     * Params: grade, standard, division (required); skillset_id,
     * activity_master, sub_activity_master, term (optional).
     */
    public function create(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'grade'               => 'required',
                'standard'            => 'required',
                'division'            => 'required',
                'skillset_id'         => 'nullable',
                'activity_master'     => 'nullable',
                'sub_activity_master' => 'nullable',
                'term'                => 'nullable',
            ]);

            $data = $this->delegate(resultActivityMarksController::class, 'create', $request);

            return $this->success($data);
        });
    }

    /**
     * POST /api/result/hpc-activity-entry
     * Save/update activity-group marks for many students at once.
     * Body: { "activity_id": 4, "sub_activity_master": 9 (optional),
     *         "activity_group": { "<student_id>": <group_id>, ... } }
     */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'activity_id'         => 'required',
                'activity_group'      => 'required|array|min:1',
                'sub_activity_master' => 'nullable',
            ]);

            // The legacy store() always returns a redirect (no payload); the
            // upsert itself is fully delegated.
            $this->delegate(resultActivityMarksController::class, 'store', $request);

            return $this->success(null, 'Result activity marks added/updated successfully.', 201);
        });
    }
}
