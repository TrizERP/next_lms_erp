<?php

namespace App\Http\Controllers\api\TalentManagement\Recruitment;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
 *     has an established Gemini-calling convention instead - `env('GEMINI_API_KEY')`
 *     / `env('GEMINI_MODEL')`, exactly as `AiSopGenerationController::generate()`
 *     uses - so this controller follows that, and tenant/actor identity comes
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

        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Gemini API key is not configured. Please set GEMINI_API_KEY in the backend .env file.',
            ], 500);
        }

        $jd = $validator->validated()['jd'];
        $model = env('GEMINI_MODEL', 'gemini-2.5-flash');

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
            $response = Http::timeout(90)
                ->retry(1, 500)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $apiKey,
                ])
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.3,
                        'topP' => 0.9,
                        'maxOutputTokens' => 1024,
                    ],
                ]);

            if (!$response->successful()) {
                Log::warning('Gemini JD analysis failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to analyze job description with Gemini.',
                ], 502);
            }

            $textResponse = $response->json('candidates.0.content.parts.0.text') ?? '{}';

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
        } catch (\Throwable $exception) {
            Log::error('Gemini JD analysis exception', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to analyze job description.',
            ], 500);
        }
    }
}
