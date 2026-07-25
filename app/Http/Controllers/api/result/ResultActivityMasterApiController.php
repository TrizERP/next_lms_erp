<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\result_activity_master\resultActivityMasterController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for HPC Activity Master (Holistic Progress Card activities,
 * tables result_activity_master + result_sub_activity).
 *
 * Delegates every operation to the existing web controller
 * (result\result_activity_master\resultActivityMasterController) so business
 * logic is reused 1:1.
 */
class ResultActivityMasterApiController extends BaseResultApiController
{
    /**
     * GET /api/result/hpc-activity-master
     * List activities with skillset, standard, term and concatenated
     * sub-activities (search, sort, pagination). meta.termwise_hpc carries
     * the institute's termwise HPC setting.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(resultActivityMasterController::class, 'index', $request);

            $rows = $this->search(
                $data['result_activity_masters'] ?? [],
                $request->input('search'),
                ['title', 'result_main_title', 'result_title', 'standard', 'term_name', 'sub_activity']
            );
            $rows = $this->sort($rows, $request);
            $page = $this->paginate($rows, $request);

            return $this->success($page['items'], 'Success', 200, [
                'pagination'   => $page['meta'],
                'termwise_hpc' => $data['termwise_hpc'] ?? 'No',
            ]);
        });
    }

    /**
     * GET /api/result/hpc-activity-master/create
     * Form defaults: standards, skillsets, level layers (3/4) and the
     * termwise HPC setting.
     */
    public function create(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(resultActivityMasterController::class, 'create', $request);

            return $this->success($data);
        });
    }

    /**
     * POST /api/result/hpc-activity-master
     * With levels=4 + sub_skill_id, stores a level-4 sub-activity
     * (result_sub_activity); otherwise stores a level-3 activity
     * (result_activity_master, needs standard and optionally term).
     */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'title'        => 'required|string',
                'skill_id'     => 'required',
                'standard'     => 'required_unless:levels,4',
                'levels'       => 'nullable',
                'sub_skill_id' => 'required_if:levels,4',
                'term'         => 'nullable',
                'sort_order'   => 'nullable',
            ]);

            $data = $this->delegate(resultActivityMasterController::class, 'store', $request);

            if (isset($data['status']) && (string) $data['status'] === '0') {
                return $this->error($data['message'] ?? 'Failed to add data', 500);
            }

            return $this->success($data, $data['message'] ?? 'Result activity master added successfully.', 201);
        });
    }

    /**
     * GET /api/result/hpc-activity-master/{id}
     * Single activity with its first sub-activity row, matching skillsets,
     * standards and the termwise HPC setting (same as web edit screen).
     */
    public function show(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $data = $this->delegate(resultActivityMasterController::class, 'edit', $request, $id);

            if (empty($data['result_activity_masters'])) {
                return $this->error('Record not found.', 404);
            }

            return $this->success($data);
        });
    }

    /**
     * PUT /api/result/hpc-activity-master/{id}
     * Body columns: title, skill_id, standard, sort_order, term (stored as
     * term_id) plus optional subData{id[],title[],sort_order[]} to update
     * the linked sub-activities.
     */
    public function update(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $this->validate($request, [
                'title'    => 'required|string',
                'skill_id' => 'required',
                'standard' => 'required',
                'term'     => 'nullable',
                'subData'  => 'nullable|array',
            ]);

            // The web update() writes $request->except('_method','_token',
            // 'submit','subData','hasSubActivity') straight into
            // result_activity_master, so the request we pass on may only
            // contain real column values (the api.session middleware's
            // type=API flag - or syear/term_id overrides - would otherwise end
            // up in the UPDATE statement). Hence no delegate() here: we hand
            // over a whitelisted copy WITHOUT type, the web method then
            // redirects and normalize() picks the flashed payload back up from
            // the session.
            $allowed = ['title', 'skill_id', 'standard', 'sort_order', 'term', 'subData'];
            $clean   = new Request(array_intersect_key($request->all(), array_flip($allowed)));
            $clean->setLaravelSession($request->session());

            $data = $this->normalize(
                app(resultActivityMasterController::class)->update($clean, $id)
            );

            return $this->success($data, $data['message'] ?? 'Result activity master updated successfully.');
        });
    }

    /**
     * DELETE /api/result/hpc-activity-master/{id}
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $data = $this->delegate(resultActivityMasterController::class, 'destroy', $request, $id);

            return $this->success($data, $data['message'] ?? 'Result activity master deleted successfully');
        });
    }

    /**
     * DELETE /api/result/hpc-activity-master/bulk
     * Body: { "ids": [1,2,3] }
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, ['ids' => 'required|array|min:1']);

            foreach ($request->input('ids') as $id) {
                $this->delegate(resultActivityMasterController::class, 'destroy', $request, $id);
            }

            return $this->success(null, 'Data Deleted');
        });
    }

    /**
     * DELETE /api/result/hpc-activity-master/sub-activity/{id}
     * Deletes one level-4 sub-activity (result_sub_activity). The web method
     * reads the id from the `sub_id` request input, so it is merged in from
     * the URL segment.
     */
    public function subActivityDestroy(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $request->merge(['sub_id' => $id]);

            $data = $this->delegate(resultActivityMasterController::class, 'result_sub_activity_destroy', $request);

            return $this->success($data, $data['message'] ?? 'Data Deleted');
        });
    }

    /**
     * GET /api/result/hpc-activity-master/activity-lists
     * Dependent lists for the HPC hierarchy pickers:
     *  - skill_id + standard          -> level-3 activities (result_activity_master)
     *  - level=4 + skill_id           -> level-4 sub-activities (result_sub_activity)
     *  - level=2 + standard           -> skillsets (result_skillset)
     */
    public function activityLists(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(resultActivityMasterController::class, 'getActivityLists', $request);

            if ($data === 'No Data') {
                return $this->success([], 'No Data');
            }

            return $this->success($data);
        });
    }
}
