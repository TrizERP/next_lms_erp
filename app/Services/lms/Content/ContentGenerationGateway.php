<?php

namespace App\Services\lms\Content;

use App\Services\GammaService;
use App\Services\QuestionGenerationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The one place LMS content authoring talks to a model provider.
 *
 * ============================ SCOPE — READ BEFORE EXTENDING ============================
 * This is a PLACEHOLDER for Track D's **Generative AI Gateway** (Central Engines sheet;
 * AI Stack & Navigation row 5: "Consolidate before a third product builds its own separate
 * LLM integration"). It is deliberately content-scoped.
 *
 * It serves ONLY App\Services\lms\Content\*. It does NOT attempt to capture the ~13 other
 * LLM call sites in this repo — OpenAIService (used from contentController at :1025, :1147,
 * :1230, :1274, :1325, :1350), DeepSeekAssessmentService, ContentModelLlmClient,
 * AiSopGenerationController, the H5P controllers, the two Next.js app/api/ai/* routes.
 * Capturing those is Track D's job; doing it here would create the second competing gateway
 * that this whole exercise exists to prevent.
 *
 * When Track D's gateway lands, this class becomes an adapter over it: the callers only
 * ever use generate(), so the swap needs no caller changes.
 * =======================================================================================
 *
 * WHAT IT OWNS THAT NOTHING OWNED BEFORE
 *  1. Provider and key resolution through config(), never env() at request time. Reading a
 *     key with env() breaks under `config:cache`, which is how a working deploy silently
 *     becomes a 500. contentController.php reads GAMMA_API_KEY via env() at :1893 and again
 *     at :1926; OpenAIService.php does the same at :30, :31, :2124.
 *  2. A returned token/latency record, so a generation can be costed and audited. Nothing
 *     in the existing paths reports this.
 *  3. One error shape. Today a provider failure surfaces as a 500, a 502, a JSON error
 *     body or an empty string depending on which of the three paths you hit.
 */
class ContentGenerationGateway
{
    public function __construct(
        private GammaService $gamma,
        private QuestionGenerationService $questions
    ) {
    }

    /**
     * Generate content with the provider registered for this authoring type.
     *
     * @param  string  $provider  gamma | gemini | question
     * @param  array<string,mixed>  $params
     * @return array{
     *     provider:string, model:?string, output:mixed,
     *     input_tokens:?int, output_tokens:?int, latency_ms:int
     * }
     *
     * @throws RuntimeException on provider failure, with a single consistent shape.
     */
    public function generate(string $provider, array $params): array
    {
        $startedAt = microtime(true);

        $result = match ($provider) {
            'gamma'    => $this->viaGamma($params),
            'gemini'   => $this->viaGemini($params),
            'question' => $this->viaQuestionService($params),
            default    => throw new RuntimeException("No generator is registered for provider \"{$provider}\"."),
        };

        $result['provider'] = $provider;
        $result['latency_ms'] = (int) round((microtime(true) - $startedAt) * 1000);

        return $result;
    }

    /**
     * Presentations, via the existing App\Services\GammaService.
     *
     * Wrapped, not reimplemented — GammaService already handles generate + poll + extract
     * and is the more disciplined of the two Gamma clients in this repo (the other is
     * inlined in contentController::storeGammaContent).
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private function viaGamma(array $params): array
    {
        $generation = $this->gamma->generatePresentation([
            'inputText' => (string) ($params['prompt'] ?? ''),
            'numCards'  => (int) ($params['slide_count'] ?? 12),
            'themeId'   => $params['theme_id'] ?? null,
        ]);

        $generationId = $generation['generationId'] ?? $generation['id'] ?? null;

        if ($generationId === null) {
            throw new RuntimeException('The presentation provider did not return a generation id.');
        }

        $status = $this->gamma->pollStatus($generationId);
        $extracted = $this->gamma->extractResult(is_array($status) ? $status : []);

        return [
            'model'         => config('gamma.model'),
            'output'        => $extracted,
            'input_tokens'  => null,   // Gamma bills per generation, not per token.
            'output_tokens' => null,
        ];
    }

    /**
     * Documents (notes, activities), via Gemini.
     *
     * Keys and endpoint come from config/gemini.php, which already exists and which
     * contentController::storeGammaContent ignores in favour of env() at :1687, :1696-1697.
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private function viaGemini(array $params): array
    {
        $apiKey = config('gemini.api_key');

        if (empty($apiKey)) {
            throw new RuntimeException('The document generator is not configured (missing Gemini API key).');
        }

        $model = config('gemini.model', 'gemini-2.5-flash');
        // 2.5, not 2.0: config/gemini.php is now development's shim over config/ai.php and
        // always resolves a model, so this in-code fallback is unreachable today. It is kept
        // correct anyway - every other call site in this repo defaults to 2.5, and a 2.0 here
        // would silently call a different model than the legacy path it claims parity with.
        $baseUrl = rtrim((string) config('gemini.base_url'), '/');

        $response = Http::timeout((int) config('gemini.request_timeout', 120))
            ->retry(1, 500)
            ->withHeaders(['x-goog-api-key' => $apiKey])
            ->post("{$baseUrl}/models/{$model}:generateContent", [
                'contents' => [[
                    'parts' => [['text' => (string) ($params['prompt'] ?? '')]],
                ]],
                'generationConfig' => [
                    'temperature'     => 0.35,
                    'topP'            => 0.9,
                    'maxOutputTokens' => 24000,
                ],
            ]);

        if (! $response->successful()) {
            Log::channel('daily')->warning('lms.gateway: gemini generation failed', [
                'status' => $response->status(),
            ]);

            throw new RuntimeException('The document generator failed to produce content.');
        }

        $body = $response->json();
        $text = $this->extractGeminiText($body);

        if ($text === '') {
            throw new RuntimeException('The document generator returned an empty result.');
        }

        $usage = $body['usageMetadata'] ?? [];

        return [
            'model'         => $model,
            'output'        => $text,
            'input_tokens'  => isset($usage['promptTokenCount']) ? (int) $usage['promptTokenCount'] : null,
            'output_tokens' => isset($usage['candidatesTokenCount']) ? (int) $usage['candidatesTokenCount'] : null,
        ];
    }

    /**
     * Questions, via the existing App\Services\QuestionGenerationService.
     *
     * TREATED AS A BLACK BOX THAT RETURNS IDS, deliberately. That service is 2,153 lines
     * and owns a generated-column unique index (`uq_qm_concept_hash`), its own content-hash
     * dedup, and its own transaction. Reimplementing any of that here would fork the
     * dedup guarantee, which is worse than the duplication this phase is removing.
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private function viaQuestionService(array $params): array
    {
        $output = $this->questions->generate($params);

        return [
            'model'         => config('deepseek.model'),
            'output'        => $output,
            'input_tokens'  => is_array($output) ? ($output['input_tokens'] ?? null) : null,
            'output_tokens' => is_array($output) ? ($output['output_tokens'] ?? null) : null,
        ];
    }

    /** @param array<string,mixed>|null $payload */
    private function extractGeminiText(?array $payload): string
    {
        $parts = $payload['candidates'][0]['content']['parts'] ?? [];
        $text = '';

        foreach ((array) $parts as $part) {
            if (isset($part['text'])) {
                $text .= $part['text'];
            }
        }

        return trim($text);
    }
}
