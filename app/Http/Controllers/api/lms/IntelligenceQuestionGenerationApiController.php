<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use App\Services\QuestionGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * POST /api/intelligence/questions/generate
 *
 * Generates MCQ / narrative assessment items for a concept via the DeepSeek LLM
 * and writes them (column-shaped) into `lms_question_master`.
 *
 * Gated by `api.session` + `staff.only` + `throttle.qgen` (see routes/api.php).
 * Tenant and author identity come from the verified JWT via the hydrated
 * session - never from the request body - and the LLM model/temperature are
 * server-owned so a caller cannot select an arbitrarily expensive model.
 */
class IntelligenceQuestionGenerationApiController extends Controller
{
    protected QuestionGenerationService $service;

    public function __construct(QuestionGenerationService $service)
    {
        $this->service = $service;
    }

    public function generate(Request $request): JsonResponse
    {
        // DeepSeek (esp. reasoning models) can take well over the default 60s.
        set_time_limit(300);

        $validator = Validator::make($request->all(), [
            'concept_id'        => 'required|integer|min:1',
            // sub_institute_id and created_by are deliberately NOT accepted from
            // the request. They are injected from the verified session below.
            'subject_id'        => 'required|integer',
            'standard_id'       => 'required|integer',
            'chapter_id'        => 'required|integer',
            'question_type_id'  => 'required|integer|min:1',
            'question_type'     => 'required|in:mcq,narrative',
            'total_questions'   => 'required|integer|min:1|max:100',
            'grade_id'          => 'nullable|integer',
            // `model` and `temperature` are intentionally absent: they are
            // server-owned (config/deepseek.php). `seed` is kept because it only
            // makes a run reproducible and carries no cost implication.
            'seed'              => 'nullable|integer',
            // Every quota key needs a rule of its own. The service is handed
            // $validator->validated(), which returns ONLY attributes that were
            // validated - so a key with no rule here is silently dropped before
            // QuestionGenerationService::buildQuota ever sees it, and the caller's
            // difficulty/marks quietly become the built-in BLOOM_META defaults.
            'quota'                 => 'nullable|array',
            'quota.*.level'         => 'nullable|string',
            'quota.*.count'         => 'nullable|integer|min:0',
            'quota.*.difficulty'    => 'nullable|string|in:Easy,Medium,Hard',
            'quota.*.points'        => 'nullable|integer|min:0|max:100',
            'quota.*.dok'           => 'nullable|integer|min:1|max:4',
            'pre_grade_topic'             => 'nullable|string|max:250',
            'post_grade_topic'            => 'nullable|string|max:250',
            'cross_curriculum_grade_topic'=> 'nullable|string|max:250',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Identity from the verified token payload only. `api.session` refuses
        // the request outright if either value is missing from the JWT, so these
        // are guaranteed present here.
        $payload = $validator->validated();
        $payload['sub_institute_id'] = (int) $request->session()->get('sub_institute_id');
        $payload['created_by']       = (int) $request->session()->get('user_id');

        $result = $this->service->generate($payload);

        // 403 for a tenant-ownership rejection so it is distinguishable from an
        // ordinary generation failure; everything else stays 422.
        $httpStatus = $result['status']
            ? 200
            : (($result['code'] ?? null) === 'forbidden' ? 403 : 422);

        return response()->json($result, $httpStatus);
    }
}
