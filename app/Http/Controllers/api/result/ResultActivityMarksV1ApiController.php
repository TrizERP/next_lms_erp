<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\result_activity_marks\resultActivityMarksV1Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for HPC Entry v1 (whole-grid Holistic Progress Card entry).
 *
 * Delegates every operation to the existing web controller
 * (result\result_activity_marks\resultActivityMarksV1Controller) so business
 * logic - skillset/activity/sub-activity tree, termwise HPC handling,
 * activity marks upsert - is reused 1:1, never re-implemented.
 *
 * NOTE: create()/store() are invoked with type=JSON instead of type=API on
 * purpose: the legacy type=API branch mis-assigns syear from
 * sub_institute_id, while the JSON branch correctly uses the hydrated
 * session values and still returns JSON we can normalize.
 */
class ResultActivityMarksV1ApiController extends BaseResultApiController
{
    /**
     * GET /api/result/hpc-entry-v1
     * Landing data: status + the termwise_hpc setting.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(resultActivityMarksV1Controller::class, 'index', $request);

            return $this->success($data);
        });
    }

    /**
     * GET /api/result/hpc-entry-v1/create
     * Full HPC grid for one class: students, skillset tree (grouped by main
     * sort order), activities per skill, sub-activities per activity, the
     * activity groups (mark options) and every student's existing marks.
     * Params: grade, standard, division (required); term (required when the
     * institute's termwise_hpc setting is "Yes").
     */
    public function create(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'grade'    => 'required',
                'standard' => 'required',
                'division' => 'required',
                'term'     => 'nullable',
            ]);

            // Use the JSON branch (not API) so session sub_institute_id/syear
            // are used - the legacy API branch sets syear = sub_institute_id.
            $request->merge(['type' => 'JSON']);
            $data = $this->normalize(
                app(resultActivityMarksV1Controller::class)->create($request)
            );

            // is_mobile(type=JSON) returns response()->json(payload);
            // normalize() gives us the payload array directly.
            return $this->success($data);
        });
    }

    /**
     * POST /api/result/hpc-entry-v1
     * Save/update the whole HPC grid in one call.
     * Body: { "marksArr": { "<student_id>": {
     *          "activity_id": { "<activity_id>": <group_id>, ... },
     *          "sub_activity_id": { "<activity_id>": { "<sub_activity_id>": <group_id>, ... } }
     *        }, ... } }
     */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'marksArr' => 'required|array|min:1',
            ]);

            // Same reasoning as create(): the JSON branch uses the correct
            // session syear/user_id; is_mobile(type=JSON) returns JSON.
            $request->merge(['type' => 'JSON']);
            $data = $this->normalize(
                app(resultActivityMarksV1Controller::class)->store($request)
            );

            return $this->success($data, $data['message'] ?? 'Data Saved', 201);
        });
    }
}
