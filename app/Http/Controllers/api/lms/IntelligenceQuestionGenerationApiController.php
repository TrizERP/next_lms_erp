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
            'sub_institute_id'  => 'required',
            'subject_id'        => 'required|integer',
            'standard_id'       => 'required|integer',
            'chapter_id'        => 'required|integer',
            'question_type_id'  => 'required|integer|min:1',
            'question_type'     => 'required|in:mcq,narrative',
            'total_questions'   => 'required|integer|min:1|max:100',
            'created_by'        => 'required|integer',
            'grade_id'          => 'nullable|integer',
            'model'             => 'nullable|string',
            'temperature'       => 'nullable|numeric|between:0,1.5',
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

        $result = $this->service->generate($validator->validated());

        $httpStatus = $result['status'] ? 200 : 422;

        return response()->json($result, $httpStatus);
    }
}
