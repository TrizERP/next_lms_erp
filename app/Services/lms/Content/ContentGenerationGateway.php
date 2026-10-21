<?php

namespace App\Services\lms\Content;

use App\Domain\AI\Configuration\AiConfigurationException;
use App\Domain\AI\Configuration\AiModelClientFactory;
use App\Services\GammaService;
use App\Services\QuestionGenerationService;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

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
    public function generate(string $provider, array $params, int|string|null $subInstituteId = null): array
    {
        $startedAt = microtime(true);

        $result = match ($provider) {
            'gamma'    => $this->viaGamma($params),
            'gemini'   => $this->viaGemini($params, $subInstituteId),
            'question' => $this->viaQuestionService($params),
            default    => throw new RuntimeException("No generator is registered for provider \"{$provider}\"."),
        };

        // The document path reports the provider that really answered.
        $result['provider'] = $result['provider'] ?? $provider;
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
    private function viaGemini(array $params, int|string|null $subInstituteId = null): array
    {
        // Provider, model, key and fallbacks come from the central AI configuration
        // ('content_generation'); an unconfigured module throws AiConfigurationException,
        // whose message tells the administrator what to set up.
        $client = app(AiModelClientFactory::class)->for('content_generation', $subInstituteId);

        try {
            $text = trim((string) $client->chat(
                [['role' => 'user', 'content' => (string) ($params['prompt'] ?? '')]],
                null,
                24000,
                0.35,
                false,
                (int) config('gemini.request_timeout', 120)
            ));
        } catch (AiConfigurationException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        } catch (Throwable $e) {
            Log::channel('daily')->warning('lms.gateway: document generation failed', [
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('The document generator failed to produce content.', 0, $e);
        }

        if ($text === '') {
            throw new RuntimeException('The document generator returned an empty result.');
        }

        $used = $client->lastUsed();

        return [
            'provider'      => $used?->provider,
            'model'         => $used?->model,
            'output'        => $text,
            'input_tokens'  => null,
            'output_tokens' => null,
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
            'model'         => null,   // QuestionGenerationService owns its provider; none is reported here.
            'output'        => $output,
            'input_tokens'  => is_array($output) ? ($output['input_tokens'] ?? null) : null,
            'output_tokens' => is_array($output) ? ($output['output_tokens'] ?? null) : null,
        ];
    }

}
