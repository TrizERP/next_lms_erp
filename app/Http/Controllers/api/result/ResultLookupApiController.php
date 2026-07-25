<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\result_api\resultAPIController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for the Result personalize / PAL lookup endpoints.
 *
 * Delegates every operation to the existing web controller
 * (result\result_api\resultAPIController) so business logic is reused 1:1.
 *
 * The legacy methods read sub_institute_id / syear from the REQUEST (not the
 * session), so this wrapper defaults them from the hydrated session when the
 * caller does not send them explicitly.
 */
class ResultLookupApiController extends BaseResultApiController
{
    /**
     * Default sub_institute_id / syear on the request from the hydrated
     * session so the legacy request-driven methods keep working.
     */
    private function mergeSessionDefaults(Request $request): void
    {
        $request->merge([
            'sub_institute_id' => $request->input('sub_institute_id', session('sub_institute_id')),
            'syear'            => $request->input('syear', session('syear')),
        ]);
    }

    /**
     * GET /api/result/personalize-marks
     * Previous-years personalized marks (result_personalize_marks) grouped by
     * standard and subject with exam-wise detail.
     *
     * Query: enrollment_no (optional), standard (optional standard NAME).
     */
    public function personalizeMarks(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->mergeSessionDefaults($request);

            $data = $this->delegate(resultAPIController::class, 'resultPersonalize', $request);

            return $this->success($data);
        });
    }

    /**
     * GET /api/result/pal-marks
     * PAL (personalized adaptive learning) chapter-wise marks of a student
     * from lms_online_exam_student.
     *
     * Query: standard_id (required), student_id (required),
     *        subject_id (optional).
     */
    public function palMarks(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'standard_id' => 'required|integer',
                'student_id'  => 'required|integer',
                'subject_id'  => 'nullable|integer',
            ]);

            $this->mergeSessionDefaults($request);

            $data = $this->delegate(resultAPIController::class, 'getPalMarks', $request);

            return $this->success($data);
        });
    }

    /**
     * GET /api/result/map-value
     * Question-mapping values (learning outcomes, skills, interests, ...) of
     * the questions a student attempted in one chapter, grouped by map type.
     *
     * Query: standard_id, subject_id, chapter_id, student_id (all required),
     *        exam_type (optional, "pal" switches to the PAL attempt table).
     */
    public function mapValue(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'standard_id' => 'required|integer',
                'subject_id'  => 'required|integer',
                'chapter_id'  => 'required|integer',
                'student_id'  => 'required|integer',
                'exam_type'   => 'nullable|string',
            ]);

            $this->mergeSessionDefaults($request);

            $data = $this->delegate(resultAPIController::class, 'getMapValue', $request);

            return $this->success($data);
        });
    }

    /**
     * GET /api/result/current-result
     * Current-year result of a student: standard totals, subject/exam-wise
     * marks, chapter-wise LMS analysis and student profile details.
     *
     * Query: standard (required standard id), student_id (required),
     *        subject_id (optional).
     */
    public function currentResult(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            // The legacy method concatenates these into raw SQL - enforce ints.
            $this->validate($request, [
                'standard'   => 'required|integer',
                'student_id' => 'required|integer',
                'subject_id' => 'nullable|integer',
            ]);

            $this->mergeSessionDefaults($request);

            $data = $this->delegate(resultAPIController::class, 'currentResult', $request);

            return $this->success($data);
        });
    }
}
