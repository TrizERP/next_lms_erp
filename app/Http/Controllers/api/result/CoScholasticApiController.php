<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\co_scholastic\co_scholastic_controller;
use App\Models\result\co_scholastic\co_scholastic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for Co-Scholastic mapping (co-scholastic areas mapped to
 * standard/term with MARK or GRADE evaluation, table result_co_scholastic
 * + result_co_scholastic_grade).
 *
 * Delegates every operation to the existing web controller
 * (result\co_scholastic\co_scholastic_controller) so business logic is
 * reused 1:1.
 */
class CoScholasticApiController extends BaseResultApiController
{
    /**
     * GET /api/result/co-scholastic
     * List co-scholastic areas with parent, term & standard names
     * (search, sort, pagination).
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $rows = app(co_scholastic_controller::class)->getData();

            return $this->listResponse($rows, $request, ['title', 'parent_name', 'term_name', 'standard']);
        });
    }

    /**
     * GET /api/result/co-scholastic/create
     * Form defaults: standards, next sort order + parent category list (ddValue).
     */
    public function create(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(co_scholastic_controller::class, 'create', $request);

            return $this->success($data);
        });
    }

    /**
     * POST /api/result/co-scholastic
     * Creates one row per standard in `standard[]`; for mark_type=GRADE the
     * co_grade[] rows (title + break_off) are stored as a new grade map.
     */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'title'      => 'required|string',
                'term'       => 'required',
                'parent_id'  => 'required',
                'mark_type'  => 'required',
                'sort_order' => 'required',
                'standard'   => 'required|array|min:1',
                'max_mark'   => 'nullable',
                'co_grade'   => 'required_if:mark_type,GRADE|array',
            ]);

            // The web controller iterates $_REQUEST['co_grade'] directly, which
            // is empty for JSON bodies; prime the superglobal from the parsed
            // request so both form-encoded and JSON clients work.
            $_REQUEST['co_grade'] = $request->input('co_grade', []);

            $data = $this->delegate(co_scholastic_controller::class, 'store', $request);

            return $this->success($data, $data['message'] ?? 'Data Saved', 201);
        });
    }

    /**
     * GET /api/result/co-scholastic/{id}
     * Single co-scholastic area with its grade rows (grd_data), parent
     * category list (ddValue) and standards - same as web edit screen.
     */
    public function show(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            if (! co_scholastic::whereKey($id)->exists()) {
                return $this->error('Record not found.', 404);
            }

            $data = $this->delegate(co_scholastic_controller::class, 'edit', $request, $id);

            return $this->success($data);
        });
    }

    /**
     * PUT /api/result/co-scholastic/{id}
     * Note: `standard` is a single id here (mirrors the web form), and
     * co_grade rows with an existing `id` are updated while rows without
     * an id are inserted.
     */
    public function update(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $this->validate($request, [
                'title'      => 'required|string',
                'term'       => 'required',
                'parent_id'  => 'required',
                'mark_type'  => 'required',
                'sort_order' => 'required',
                'standard'   => 'required',
                'max_mark'   => 'nullable',
                'co_grade'   => 'required_if:mark_type,GRADE|array',
            ]);

            if (! co_scholastic::whereKey($id)->exists()) {
                return $this->error('Record not found.', 404);
            }

            // Same $_REQUEST priming as store() - see comment there.
            $_REQUEST['co_grade'] = $request->input('co_grade', []);

            $data = $this->delegate(co_scholastic_controller::class, 'update', $request, $id);

            return $this->success($data, $data['message'] ?? 'Data Saved');
        });
    }

    /**
     * DELETE /api/result/co-scholastic/{id}
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $data = $this->delegate(co_scholastic_controller::class, 'destroy', $request, $id);

            return $this->success($data, $data['message'] ?? 'Data Deleted');
        });
    }

    /**
     * DELETE /api/result/co-scholastic/bulk
     * Body: { "ids": [1,2,3] }
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, ['ids' => 'required|array|min:1']);

            foreach ($request->input('ids') as $id) {
                $this->delegate(co_scholastic_controller::class, 'destroy', $request, $id);
            }

            return $this->success(null, 'Data Deleted');
        });
    }

    /**
     * GET /api/result/co-scholastic/dropdown
     * Lightweight id/name pairs of co-scholastic areas for selects.
     */
    public function dropdown(Request $request): JsonResponse
    {
        return $this->run(function () {
            $rows = app(co_scholastic_controller::class)->getData();

            $out = [];
            foreach ($rows as $row) {
                $out[] = [
                    'id'          => $row->id,
                    'name'        => $row->title,
                    'parent_id'   => $row->parent_id,
                    'parent_name' => $row->parent_name,
                    'standard_id' => $row->standard_id,
                    'term_id'     => $row->term_id,
                    'mark_type'   => $row->mark_type,
                ];
            }

            return $this->success($out);
        });
    }
}
