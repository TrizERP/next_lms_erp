<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\result_master\result_master_controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * REST API for Result Master (result configuration per standard/term).
 *
 * Delegates every operation to the existing web controller
 * (result\result_master\result_master_controller) so business logic is reused 1:1.
 */
class ResultMasterApiController extends BaseResultApiController
{
    /**
     * GET /api/result/result-master
     * List result master configurations (search, sort, pagination).
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $rows = app(result_master_controller::class)->getData();

            return $this->listResponse($rows, $request, ['term_name', 'grade_name', 'standard_name', 'result_date']);
        });
    }

    /**
     * POST /api/result/result-master
     * Create result configuration for one or more standards.
     * Note: teacher_sign / principal_sign / director_signatiure are optional
     * image uploads (multipart/form-data).
     */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'term'                     => 'required',
                'standard'                 => 'required|array|min:1',
                'result_date'              => 'required',
                'reopen_date'              => 'required',
                'vaction_start_date'       => 'required',
                'vaction_end_date'         => 'required',
                'result_remark'            => 'nullable',
                'optional_subject_display' => 'nullable',
                'remove_fail_per'          => 'nullable',
            ]);

            $data = $this->delegate(result_master_controller::class, 'store', $request);

            if (isset($data['status']) && $data['status'] === '0') {
                return $this->error($data['message'] ?? 'Given Standard Have Settings.', 422);
            }

            return $this->success($data, $data['message'] ?? 'Data Saved', 201);
        });
    }

    /**
     * GET /api/result/result-master/{id}
     * Single result master configuration (same data as web edit screen).
     */
    public function show(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $exists = DB::table('result_master_confrigration')
                ->where('id', $id)
                ->where('sub_institute_id', session('sub_institute_id'))
                ->exists();

            if (! $exists) {
                return $this->error('Record not found.', 404);
            }

            $data = $this->delegate(result_master_controller::class, 'edit', $request, $id);

            return $this->success($data);
        });
    }

    /**
     * PUT /api/result/result-master/{id}
     * Update a result configuration (single standard).
     */
    public function update(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $this->validate($request, [
                'term'                     => 'required',
                'standard'                 => 'required',
                'result_date'              => 'required',
                'reopen_date'              => 'required',
                'vaction_start_date'       => 'required',
                'vaction_end_date'         => 'required',
                'result_remark'            => 'nullable',
                'optional_subject_display' => 'nullable',
                'remove_fail_per'          => 'nullable',
            ]);

            $data = $this->delegate(result_master_controller::class, 'update', $request, $id);

            return $this->success($data, $data['message'] ?? 'Data Saved');
        });
    }

    /**
     * DELETE /api/result/result-master/{id}
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $data = $this->delegate(result_master_controller::class, 'destroy', $request, $id);

            return $this->success($data, $data['message'] ?? 'Data Deleted');
        });
    }

    /**
     * DELETE /api/result/result-master (bulk)
     * Body: { "ids": [1,2,3] }
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, ['ids' => 'required|array|min:1']);

            foreach ($request->input('ids') as $id) {
                $this->delegate(result_master_controller::class, 'destroy', $request, $id);
            }

            return $this->success(null, 'Data Deleted');
        });
    }

    /**
     * GET /api/result/result-master/dropdown
     * Lightweight id/name pairs for selects.
     *
     * (Named dropdownList because the base class already declares a
     * protected dropdown($map): array helper with an incompatible signature.)
     */
    public function dropdownList(Request $request): JsonResponse
    {
        return $this->run(function () {
            $rows = app(result_master_controller::class)->getData();

            $out = [];
            foreach ($rows as $row) {
                $out[] = [
                    'id'        => $row->id,
                    'name'      => $row->standard_name,
                    'grade'     => $row->grade_name,
                    'term_name' => $row->term_name,
                ];
            }

            return $this->success($out);
        });
    }
}
