<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\result_skillset\resultSkillsetController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for HPC Skillset (Holistic Progress Card skillsets,
 * table result_skillset).
 *
 * Delegates every operation to the existing web controller
 * (result\result_skillset\resultSkillsetController) so business logic is
 * reused 1:1.
 */
class ResultSkillsetApiController extends BaseResultApiController
{
    /**
     * GET /api/result/hpc-skillset
     * List skillsets with standard name (search, sort, pagination).
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(resultSkillsetController::class, 'index', $request);

            return $this->listResponse(
                $data['result_skillsets'] ?? [],
                $request,
                ['main_title', 'title', 'standard_name', 'group']
            );
        });
    }

    /**
     * GET /api/result/hpc-skillset/create
     * Form defaults: standards + activity group list.
     */
    public function create(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(resultSkillsetController::class, 'create', $request);

            return $this->success($data);
        });
    }

    /**
     * POST /api/result/hpc-skillset
     */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'main_title' => 'required|string',
                'title'      => 'required|string',
                'standard'   => 'required',
                'group'      => 'nullable',
                'sort_order' => 'nullable',
            ]);

            $data = $this->delegate(resultSkillsetController::class, 'store', $request);

            return $this->success($data, $data['message'] ?? 'Skillset added successfully.', 201);
        });
    }

    /**
     * GET /api/result/hpc-skillset/{id}
     * Single skillset with standards + activity group lists (same as web
     * edit screen).
     */
    public function show(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $data = $this->delegate(resultSkillsetController::class, 'edit', $request, $id);

            if (empty($data['result_skillset'])) {
                return $this->error('Record not found.', 404);
            }

            return $this->success($data);
        });
    }

    /**
     * PUT /api/result/hpc-skillset/{id}
     * Also re-syncs `standard` on the mapped HPC activity master rows
     * (existing web behaviour).
     */
    public function update(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $this->validate($request, [
                'main_title' => 'required|string',
                'title'      => 'required|string',
                'standard'   => 'required',
                'group'      => 'nullable',
                'sort_order' => 'nullable',
            ]);

            // The web update() writes $request->except('_method','_token','submit')
            // straight into result_skillset, so the request we pass on may only
            // contain real column values (the api.session middleware's type=API
            // flag - or syear/term_id overrides - would otherwise end up in the
            // UPDATE statement). Hence no delegate() here: we hand over a
            // whitelisted copy WITHOUT type, the web method then redirects and
            // normalize() picks the flashed payload back up from the session.
            $columns = ['main_title', 'title', 'standard', 'group', 'sort_order'];
            $clean   = new Request(array_intersect_key($request->all(), array_flip($columns)));
            $clean->setLaravelSession($request->session());

            $data = $this->normalize(
                app(resultSkillsetController::class)->update($clean, $id)
            );

            return $this->success($data, $data['message'] ?? 'Skillset updated successfully.');
        });
    }

    /**
     * DELETE /api/result/hpc-skillset/{id}
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $data = $this->delegate(resultSkillsetController::class, 'destroy', $request, $id);

            return $this->success($data, $data['message'] ?? 'Skillset deleted successfully');
        });
    }

    /**
     * DELETE /api/result/hpc-skillset/bulk
     * Body: { "ids": [1,2,3] }
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, ['ids' => 'required|array|min:1']);

            foreach ($request->input('ids') as $id) {
                $this->delegate(resultSkillsetController::class, 'destroy', $request, $id);
            }

            return $this->success(null, 'Data Deleted');
        });
    }

    /**
     * GET /api/result/hpc-skillset/dropdown
     * Lightweight id/name pairs of skillsets for selects (skill_id pickers).
     */
    public function dropdown(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(resultSkillsetController::class, 'index', $request);

            $out = [];
            foreach ($data['result_skillsets'] ?? [] as $row) {
                $out[] = [
                    'id'            => $row['id'],
                    'name'          => $row['title'],
                    'main_title'    => $row['main_title'],
                    'standard_id'   => $row['standard'],
                    'standard_name' => $row['standard_name'],
                ];
            }

            return $this->success($out);
        });
    }
}
