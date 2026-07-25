<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\exam_creation\exam_creation_controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * REST API for Exam Creation (result_create_exam).
 *
 * Delegates every operation to the existing web controller
 * (result\exam_creation\exam_creation_controller) so business logic is reused 1:1.
 */
class ExamCreationApiController extends BaseResultApiController
{
    /**
     * GET /api/result/exam-creation
     * List created exams (search, sort, pagination).
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $rows = app(exam_creation_controller::class)->getData();

            return $this->listResponse(
                $rows,
                $request,
                ['exam_name', 'exam_type', 'sub_name', 'std_name', 'term_name', 'display_name']
            );
        });
    }

    /**
     * GET /api/result/exam-creation/create
     * Form defaults: exam master dropdown ({id, name}).
     */
    public function create(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $exams = $this->delegate(exam_creation_controller::class, 'create', $request);

            return $this->success(['exams' => $this->dropdownOptions($exams ?? [])]);
        });
    }

    /**
     * POST /api/result/exam-creation
     * Create exams for multiple subjects/titles at once.
     * Web-side validation (subject/title/app_disp_status arrays) is reused.
     */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'term'     => 'required',
                'exam'     => 'required',
                'standard' => 'required',
            ]);

            $data = $this->delegate(exam_creation_controller::class, 'store', $request);

            if (isset($data['status']) && $data['status'] === '0') {
                return $this->error($data['message'] ?? 'Given Standard Already Has Exams.', 422);
            }

            return $this->success($data, $data['message'] ?? 'Data Saved', 201);
        });
    }

    /**
     * GET /api/result/exam-creation/{id}
     * Single created exam with edit dropdown data (same as web edit screen).
     */
    public function show(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $exists = DB::table('result_create_exam')
                ->where('id', $id)
                ->where('sub_institute_id', session('sub_institute_id'))
                ->exists();

            if (! $exists) {
                return $this->error('Record not found.', 404);
            }

            $data = $this->delegate(exam_creation_controller::class, 'edit', $request, $id);

            return $this->success($data);
        });
    }

    /**
     * PUT /api/result/exam-creation/{id}
     * Update a single created exam. NOTE: the web update expects the exam
     * master id as `exam_id` (store uses `exam`).
     */
    public function update(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $this->validate($request, [
                'term'     => 'required',
                'exam_id'  => 'required',
                'standard' => 'required',
                'subject'  => 'required',
                'title'    => 'required',
            ]);

            $data = $this->delegate(exam_creation_controller::class, 'update', $request, $id);

            if (isset($data['status']) && $data['status'] === '0') {
                return $this->error($data['message'] ?? 'Given Standard Have Exams.', 422);
            }

            return $this->success($data, $data['message'] ?? 'Data Saved');
        });
    }

    /**
     * DELETE /api/result/exam-creation/{id}
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $data = $this->delegate(exam_creation_controller::class, 'destroy', $request, $id);

            return $this->success($data, $data['message'] ?? 'Data Deleted');
        });
    }

    /**
     * DELETE /api/result/exam-creation (bulk)
     * Body: { "ids": [1,2,3] }
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, ['ids' => 'required|array|min:1']);

            foreach ($request->input('ids') as $id) {
                $this->delegate(exam_creation_controller::class, 'destroy', $request, $id);
            }

            return $this->success(null, 'Data Deleted');
        });
    }

    /**
     * GET /api/result/exam-creation/dropdown
     * Lightweight id/name pairs for selects.
     *
     * (Named dropdownList because the base class already declares a
     * protected dropdown($map): array helper with an incompatible signature.)
     */
    public function dropdownList(Request $request): JsonResponse
    {
        return $this->run(function () {
            $rows = app(exam_creation_controller::class)->getData();

            $out = [];
            foreach ($rows as $row) {
                $out[] = [
                    'id'        => $row->id,
                    'name'      => $row->exam_name,
                    'exam_type' => $row->exam_type,
                    'subject'   => $row->sub_name,
                    'standard'  => $row->std_name,
                    'term_name' => $row->term_name,
                ];
            }

            return $this->success($out);
        });
    }
}
