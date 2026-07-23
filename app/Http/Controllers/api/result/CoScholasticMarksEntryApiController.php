<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\co_scholastic_marks_entry\co_scholastic_marks_entry_controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for Co-Scholastic Marks Entry.
 *
 * Delegates every operation to the existing web controller
 * (result\co_scholastic_marks_entry\co_scholastic_marks_entry_controller) so
 * business logic - marks/grade save & update, mark-type handling (MARKS vs
 * GRADE), approval flags - is reused 1:1, never re-implemented.
 */
class CoScholasticMarksEntryApiController extends BaseResultApiController
{
    /**
     * GET /api/result/co-scholastic-marks-entry
     * Landing data of the co-scholastic marks entry screen (flash message +
     * empty data set; the grid is loaded via GET .../create).
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(co_scholastic_marks_entry_controller::class, 'index', $request);

            return $this->success($data);
        });
    }

    /**
     * GET /api/result/co-scholastic-marks-entry/create
     * Co-scholastic entry grid: students of the selected class with existing
     * points/grades, parent & item dropdowns, grade scale (for GRADE type)
     * and the approval status.
     */
    public function create(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'grade'                => 'required',
                'standard'             => 'required',
                'division'             => 'required',
                'term'                 => 'required',
                'co_scholastic_parent' => 'required',
                'co_scholastic'        => 'required',
            ]);

            // The legacy method reads the PHP superglobal $_REQUEST directly,
            // so mirror the (validated) inputs into it - this also makes JSON
            // request bodies work.
            foreach (['grade', 'standard', 'division', 'term', 'co_scholastic_parent', 'co_scholastic'] as $key) {
                $_REQUEST[$key] = $request->input($key);
            }

            $data = $this->delegate(co_scholastic_marks_entry_controller::class, 'create', $request);

            return $this->success($data);
        });
    }

    /**
     * POST /api/result/co-scholastic-marks-entry
     * Save/update co-scholastic points/grades for many students at once.
     * Body: { "values": { "<student_id>": { "grade_id": 1, "standard_id": 5,
     *         "term_id": 2, "co_scholastic": 7, "grade_opt": "A",
     *         "points": "8" }, ... } }
     * (grade_opt for GRADE mark type, points for MARKS mark type.)
     */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'values'                 => 'required|array|min:1',
                'values.*.grade_id'      => 'required',
                'values.*.standard_id'   => 'required',
                'values.*.term_id'       => 'required',
                'values.*.co_scholastic' => 'required',
            ]);

            // Drive the legacy store() through its existing API branch, which
            // reads $_REQUEST["type"], $_REQUEST["sub_institute_id"],
            // $_REQUEST["syear"] and a JSON-encoded $_REQUEST["data"] payload.
            $_REQUEST['type']             = 'API';
            $_REQUEST['sub_institute_id'] = session('sub_institute_id');
            $_REQUEST['syear']            = session('syear');
            $_REQUEST['data']             = json_encode($request->input('values'));

            $data = $this->delegate(co_scholastic_marks_entry_controller::class, 'store', $request);

            return $this->success($data, $data['message'] ?? 'Data Saved', 201);
        });
    }

    /**
     * POST /api/result/co-scholastic-marks-entry/approve
     * Approve (or un-approve) a co-scholastic item for one class/term.
     * Body: term_id, standard_id, division_id, exam_id (= co_scholastic id),
     *       subject_id (0 for co-scholastic), approve (1|0).
     */
    public function approve(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'term_id'     => 'required',
                'standard_id' => 'required',
                'division_id' => 'required',
                'exam_id'     => 'required',
                'subject_id'  => 'nullable',
                'approve'     => 'nullable',
            ]);

            $data = $this->delegate(co_scholastic_marks_entry_controller::class, 'approve', $request);

            return $this->success($data, $data['message'] ?? 'Data Saved');
        });
    }
}
