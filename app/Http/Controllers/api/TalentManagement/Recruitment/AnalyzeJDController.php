<?php

namespace App\Http\Controllers\api\TalentManagement\Recruitment;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Domain\AI\Configuration\AiConfigurationException;
use App\Domain\AI\Configuration\AiModelClientFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Ported from hp_erp's `App\Http\Controllers\Api\Gemini\AnalyzeJDController`
 * (route `/gemini/analyze-jd`). That route/controller never existed in this
 * backend, which is why the frontend's `recruitmentService.analyzeJobDescription`
 * call 404'd.
 *
 * Not a 1:1 port - two pieces of the source depend on hp_erp-only
 * infrastructure this project has no equivalent for, so they are dropped
 * rather than faked:
 *   - Source resolves its Gemini API key from a `gemini_api` DB table with
 *     multi-key fallback and Sanctum-token identity (`ResolvesApiIdentity`).
 *   - Neither `gemini_api` nor Sanctum auth exist here. This project already
 *     uses the central AI configuration (module `recruitment_ai`) instead, and
 *     tenant/actor identity comes
 *     from the JWT-hydrated session (`api.session` middleware), matching
 *     every other controller in this module (see `JobPostingController`).
 *   - Source also calls `https://hp.triz.co.in/getSkillCompetency` to map
 *     extracted skills onto hp_erp's own ICF competency framework
 *     (`mapped_competencies`/`framework_coverage` in its response). That
 *     endpoint is hp_erp's own tenant data under hp_erp's own sub_institute_id
 *     numbering - calling it from this project's tenants would return another
 *     organisation's (or no) framework data under a coincidentally-matching
 *     id, not this tenant's. This project has no competency-framework
 *     equivalent to map onto, so that step is dropped rather than wired to
 *     data that cannot be correct for this project's callers.
 *
 * The frontend only reads this response by JSON.stringify()-ing it whole into
 * an outbound n8n webhook payload (`sendJobPostingWebhook`) - it does not
 * destructure specific fields - so the response envelope only needs to carry
 * the same field *names* the rest of that pipeline already expects, not byte-
 * for-byte parity with hp_erp's shape.
 */
class AnalyzeJDController extends Controller
{
    public function analyze(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'jd' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $jd = $validator->validated()['jd'];

        $prompt = <<<PROMPT
Analyze this job description and extract:
1. Core technical skills (5-8 specific skills)
2. Behavioral traits (3-5 soft skills)
3. Competency level required for each skill (Beginner/Intermediate/Advanced/Expert)

Job Description:
{$jd}

Respond strictly in valid JSON:
{
  "core_skills": [],
  "behavioral_traits": [],
  "competency_level": {}
}
PROMPT;

        try {
            // Provider, model, key and fallback order come from the tenant's central AI
            // configuration ('recruitment_ai'); tenant comes from the JWT-hydrated session.
            $textResponse = app(AiModelClientFactory::class)
                ->for('recruitment_ai', session()->get('sub_institute_id'))
                ->chat(
                    [['role' => 'user', 'content' => $prompt]],
                    null,
                    1024,
                    0.3,
                    true,
                    90
                );

            if (!is_string($textResponse) || trim($textResponse) === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to analyze job description with the AI provider.',
                ], 502);
            }
            try {
                $parsed = json_decode($textResponse, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                preg_match('/\{[\s\S]*\}/', $textResponse, $matches);
                $parsed = isset($matches[0]) ? json_decode($matches[0], true) : [];
            }

            return response()->json([
                'success' => true,
                'core_skills' => $parsed['core_skills'] ?? [],
                'behavioral_traits' => $parsed['behavioral_traits'] ?? [],
                'competency_level' => $parsed['competency_level'] ?? [],
            ]);
        } catch (AiConfigurationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 500);
        } catch (\Throwable $exception) {
            Log::error('JD analysis exception', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to analyze job description.',
            ], 500);
        }
    }
}
