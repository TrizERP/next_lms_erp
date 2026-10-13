<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use App\Services\QuestionGeneration\H5p\H5pContentTypeRegistry;
use App\Services\QuestionGeneration\QuestionFormatRegistry;
use App\Services\QuestionGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * POST /api/intelligence/questions/generate
 * GET  /api/intelligence/questions/formats
 *
 * Generates assessment items for a concept and writes them (column-shaped) into
 * `lms_question_master`. Formats with an active H5P content type (multiple choice and
 * true/false) are written by Claude, in a fixed number of questions per type; every
 * other format is written by DeepSeek as before. See config/question_formats.php.
 *
 * THE TEACHER CHOOSES THE FORMAT, THE MODEL NEVER DOES. `question_format_code`
 * names one `question_type_catalog` code (true_false, fill_blank, ...);
 * `question_format_codes` names several, and the total is split evenly across them.
 * The generator writes only the forms chosen. The formats endpoint lists exactly the codes
 * that are both in the catalogue and implemented in QuestionFormatRegistry.
 * `question_type: mcq|narrative` is kept as a legacy alias for callers that
 * predate formats.
 *
 * Gated by `api.session` + `staff.only` (+ `throttle.qgen` on the billable
 * generate call) -- see routes/api.php. Tenant and author identity come from the
 * verified JWT via the hydrated session -- never from the request body -- and the
 * LLM model/temperature are server-owned so a caller cannot select an
 * arbitrarily expensive model. Naming a format grants nothing: the concept's
 * tenant is checked in the service before any prompt is built.
 */
class IntelligenceQuestionGenerationApiController extends Controller
{
    protected QuestionGenerationService $service;

    public function __construct(QuestionGenerationService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /api/intelligence/questions/formats
     *
     * The formats a teacher can pick: present in question_type_catalog AND
     * implemented in the registry. Not throttled -- it spends nothing.
     */
    public function formats(): JsonResponse
    {
        // While the H5P layer is on, the picker offers only the H5P content types that
        // are active (Phase 1: multiple choice and true/false), each tagged with the H5P
        // type it plays as, and `questions_per_type` is the fixed count the generator
        // writes for each. With the layer off both are absent and nothing is filtered.
        $h5p = app(H5pContentTypeRegistry::class);

        return response()->json([
            'status' => true,
            'message' => 'Question formats fetched successfully.',
            'data' => $h5p->decorate($this->service->formats()->generatable()),
            'max_questions' => QuestionFormatRegistry::MAX_QUESTIONS,
            'questions_per_type' => $h5p->questionsPerType(),
        ]);
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
            // The format. Whether the code is real, implemented and catalogued is
            // decided by QuestionFormatRegistry::resolveRequest, which is the one
            // place that knows; this only keeps the value well-formed.
            'question_format_code' => ['nullable', 'string', 'max:48', 'regex:/^[a-z0-9_]+$/'],
            // Several formats in one request. Each code is validated against the same
            // registry + catalogue rules as a single one (QuestionFormatRegistry::
            // resolveMany); this only keeps the shape sane. The ceiling is however many
            // formats the registry knows, not a number written here.
            'question_format_codes' => ['nullable', 'array', 'min:1', 'max:' . count($this->service->formats()->all())],
            'question_format_codes.*' => ['required', 'string', 'max:48', 'regex:/^[a-z0-9_]+$/', 'distinct'],
            // Legacy alias for callers that predate formats.
            'question_type'     => 'nullable|string|in:mcq,narrative|required_without_all:question_format_code,question_format_codes',
            // `question_type_id` is intentionally absent: it is resolved server-side
            // from the catalogue's lms_question_type_id. Historic rows carry ids such
            // as 4, 7 and 8 because it used to be taken from the client; being
            // outside this rule list means validated() drops it even when sent.
            'total_questions'   => 'required|integer|min:1|max:' . QuestionFormatRegistry::MAX_QUESTIONS,
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
            'quota'                 => 'nullable|array|max:6',
            'quota.*.level'         => 'nullable|string',
            'quota.*.count'         => 'nullable|integer|min:0|max:' . QuestionFormatRegistry::MAX_QUESTIONS,
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

        // The same verified institute also scopes the provider credential. Without
        // this the service resolved whichever `ai_api_keys` row the table returned
        // first, so one school's generation could run on another school's key and
        // against another school's daily limit.
        $result = $this->service
            ->forInstitute($payload['sub_institute_id'])
            ->generate($payload);

        // 403 for a tenant-ownership rejection so it is distinguishable from an
        // ordinary generation failure; everything else stays 422.
        $httpStatus = $result['status']
            ? 200
            : (($result['code'] ?? null) === 'forbidden' ? 403 : 422);

        return response()->json($result, $httpStatus);
    }
}
