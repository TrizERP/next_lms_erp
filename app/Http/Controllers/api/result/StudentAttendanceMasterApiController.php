<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\student_attendance_master\student_attendance_master_controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for Student Attendance Master (term attendance for report cards).
 *
 * Delegates every operation to the existing web controller
 * (result\student_attendance_master\student_attendance_master_controller)
 * so business logic is reused 1:1.
 *
 * IMPORTANT: the legacy controller reads its inputs from $_REQUEST, so
 * clients MUST send parameters as query string (GET) or as
 * application/x-www-form-urlencoded / multipart form fields (POST).
 * A raw JSON body will NOT reach $_REQUEST.
 */
class StudentAttendanceMasterApiController extends BaseResultApiController
{
    /**
     * GET /api/result/student-attendance-master/create
     * Attendance entry sheet for a class: student list with existing
     * attendance, total working days and the remark dropdown.
     * Required query params: term, grade, standard, division.
     */
    public function create(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'term'     => 'required',
                'grade'    => 'required',
                'standard' => 'required',
                'division' => 'required',
            ]);

            $data = $this->delegate(student_attendance_master_controller::class, 'create', $request);

            // The legacy controller signals precondition failures (missing
            // working days / remark master) as a message with class=danger.
            if (isset($data['class']) && $data['class'] === 'danger') {
                return $this->error($data['message'] ?? 'Preconditions not met.', 422);
            }

            return $this->success($data);
        });
    }

    /**
     * POST /api/result/student-attendance-master
     * Save (upsert) attendance rows. Form fields:
     *   values[<student_id>][term_id], values[<student_id>][standard],
     *   values[<student_id>][attendance], values[<student_id>][working_day],
     *   values[<student_id>][remark_id], values[<student_id>][teacher_remark]
     * Must be form-encoded (the web controller reads $_REQUEST['values']).
     */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'values' => 'required|array|min:1',
            ]);

            $data = $this->delegate(student_attendance_master_controller::class, 'store', $request);

            return $this->success($data, $data['message'] ?? 'Data Saved', 201);
        });
    }
}
