<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\result_book_master\result_book_master_controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for Result Book Master (trust header lines + logos per standards).
 *
 * Delegates every operation to the existing web controller
 * (result\result_book_master\result_book_master_controller) so business
 * logic is reused 1:1.
 */
class ResultBookMasterApiController extends BaseResultApiController
{
    /**
     * GET /api/result/result-book-master
     * List trust/book master entries with their standards (search, sort, pagination).
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $rows = app(result_book_master_controller::class)->getData();

            return $this->listResponse(
                $rows,
                $request,
                ['line1', 'line2', 'line3', 'line4', 'grade_name', 'standard_name']
            );
        });
    }

    /**
     * POST /api/result/result-book-master
     * Create a trust master entry and map it to standards.
     * left_logo / right_logo are optional image uploads (multipart/form-data).
     */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'standard' => 'required|array|min:1',
                'line1'    => 'nullable',
                'line2'    => 'nullable',
                'line3'    => 'nullable',
                'line4'    => 'nullable',
                'status'   => 'nullable',
            ]);

            $data = $this->delegate(result_book_master_controller::class, 'store', $request);

            return $this->success($data, $data['message'] ?? 'Data Saved', 201);
        });
    }

    /**
     * GET /api/result/result-book-master/{id}
     * Single trust/book master entry (same data as web edit screen).
     */
    public function show(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $data = $this->delegate(result_book_master_controller::class, 'edit', $request, $id);

            if (empty($data)) {
                return $this->error('Record not found.', 404);
            }

            return $this->success($data);
        });
    }

    /**
     * PUT /api/result/result-book-master/{id}
     * Update: the web logic deletes and re-creates the trust entry and its
     * standard mappings (the record therefore gets a NEW id).
     */
    public function update(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $this->validate($request, [
                'standard' => 'required|array|min:1',
                'line1'    => 'nullable',
                'line2'    => 'nullable',
                'line3'    => 'nullable',
                'line4'    => 'nullable',
                'status'   => 'nullable',
            ]);

            $data = $this->delegate(result_book_master_controller::class, 'update', $request, $id);

            return $this->success($data, $data['message'] ?? 'Data Saved');
        });
    }

    /**
     * DELETE /api/result/result-book-master/{id}
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $data = $this->delegate(result_book_master_controller::class, 'destroy', $request, $id);

            return $this->success($data, $data['message'] ?? 'Data Deleted');
        });
    }

    /**
     * DELETE /api/result/result-book-master (bulk)
     * Body: { "ids": [1,2,3] }
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, ['ids' => 'required|array|min:1']);

            foreach ($request->input('ids') as $id) {
                $this->delegate(result_book_master_controller::class, 'destroy', $request, $id);
            }

            return $this->success(null, 'Data Deleted');
        });
    }

    /**
     * GET /api/result/result-book-master/dropdown
     * Lightweight id/name pairs for selects.
     *
     * (Named dropdownList because the base class already declares a
     * protected dropdown($map): array helper with an incompatible signature.)
     */
    public function dropdownList(Request $request): JsonResponse
    {
        return $this->run(function () {
            $rows = app(result_book_master_controller::class)->getData();

            $out = [];
            foreach ($rows as $row) {
                $out[] = [
                    'id'            => $row['id'],
                    'name'          => $row['line1'],
                    'grade_name'    => $row['grade_name'],
                    'standard_name' => $row['standard_name'],
                    'status'        => $row['status'],
                ];
            }

            return $this->success($out);
        });
    }
}
