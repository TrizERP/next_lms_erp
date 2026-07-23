<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\new_result\templateController;
use App\Models\result\result_template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for Result Templates (report-card HTML templates).
 *
 * Delegates every operation to the existing web controller
 * (result\new_result\templateController) so business logic is reused 1:1.
 */
class ResultTemplateApiController extends BaseResultApiController
{
    /**
     * GET /api/result/result-template
     * List result templates (search, sort, pagination).
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(templateController::class, 'index', $request);

            return $this->listResponse(
                $data['data'] ?? [],
                $request,
                ['module_name', 'title', 'user_created']
            );
        });
    }

    /**
     * GET /api/result/result-template/tags
     * All dynamic template tags (<< student_name_value >> etc.) with their
     * meaning, as the HTML help text used by the web template editor.
     */
    public function tags(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(templateController::class, 'viewAllTag', $request);

            return $this->success($data);
        });
    }

    /**
     * POST /api/result/result-template
     */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'module_name'  => 'required|string',
                'title'        => 'required|string',
                'html_content' => 'required|string',
            ]);

            $data = $this->delegate(templateController::class, 'store', $request);

            return $this->success($data, $data['message'] ?? 'Result Template Added Successfully', 201);
        });
    }

    /**
     * GET /api/result/result-template/{id}
     * Single template (same data as web edit screen).
     */
    public function show(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            if (! result_template::whereKey($id)->exists()) {
                return $this->error('Record not found.', 404);
            }

            $data = $this->delegate(templateController::class, 'edit', $request, $id);

            return $this->success($data);
        });
    }

    /**
     * PUT /api/result/result-template/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $this->validate($request, [
                'module_name'  => 'required|string',
                'title'        => 'required|string',
                'html_content' => 'required|string',
            ]);

            $data = $this->delegate(templateController::class, 'update', $request, $id);

            return $this->success($data, $data['message'] ?? 'Template Updated Successfully');
        });
    }

    /**
     * DELETE /api/result/result-template/{id}
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $data = $this->delegate(templateController::class, 'destroy', $request, $id);

            return $this->success($data, $data['message'] ?? 'Template Deleted Successfully');
        });
    }

    /**
     * DELETE /api/result/result-template/bulk
     * Body: { "ids": [1,2,3] }
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, ['ids' => 'required|array|min:1']);

            foreach ($request->input('ids') as $id) {
                $this->delegate(templateController::class, 'destroy', $request, $id);
            }

            return $this->success(null, 'Template Deleted Successfully');
        });
    }

    /**
     * GET /api/result/result-template/dropdown
     * Lightweight id/name pairs for selects.
     */
    public function dropdown(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(templateController::class, 'index', $request);

            $out = [];
            foreach (($data['data'] ?? []) as $row) {
                $out[] = [
                    'id'          => $row['id'],
                    'name'        => $row['title'],
                    'module_name' => $row['module_name'],
                ];
            }

            return $this->success($out);
        });
    }
}
