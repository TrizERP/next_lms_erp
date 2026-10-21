<?php

namespace App\Services\QuestionGeneration\H5p;

use App\Domain\AI\Configuration\AiConfigurationException;
use App\Domain\AI\Configuration\AiModelClientFactory;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The model call behind H5P and Prayogshala question generation — through AI Providers.
 *
 * (The class keeps its old name because the generators and their tests inject it by name; it no
 * longer talks to Anthropic or to any one vendor.)
 *
 * WHAT WAS WRONG
 *
 * This client used to call Anthropic's Messages API directly and looked for a credential tagged
 * `ANTHROPIC_API_KEY`, falling back to the `.env` file. AI Providers cannot hold an Anthropic key
 * (there is no Anthropic driver), so an administrator who configured their provider the supported
 * way, and removed the duplicate from `.env`, got "Claude API key is not configured" and a 422 —
 * the generation was waiting for a credential no screen could create.
 *
 * WHAT IT DOES NOW
 *
 * It asks the factory for the module's providers and uses them in the order saved in AI Providers,
 * with fallback when one fails or its quota is spent. Provider, model, key and base URL are whatever
 * is saved there; nothing is read from the environment. The default module is `assessment_ai`
 * (question generation); the Prayogshala generator passes `content_generation`.
 *
 * It returns the envelope `QuestionGenerationService::callDeepSeek()` returns (ok / content / model /
 * finish_reason / usage, or ok=false with an error), so the code after the call — JSON extraction,
 * validation, persistence — is unchanged and unaware of which provider answered.
 *
 * No temperature or top_p is sent: the chain targets several vendors and some models reject sampling
 * parameters outright. Token counts are not available from the shared client and are reported as 0.
 */
class ClaudeQuestionClient
{
    /**
     * @return array{ok: bool, error?: string, content?: string, model?: string, finish_reason?: ?string, usage?: array<string, int>}
     */
    public function complete(
        string $system,
        string $user,
        int|string|null $subInstituteId = null,
        string $module = 'assessment_ai'
    ): array {
        $cfg = (array) config('claude.question_generation', []);
        $timeout = max(10, (int) ($cfg['timeout_seconds'] ?? 180));
        $maxTokens = (int) ($cfg['max_output_tokens'] ?? 8000);

        try {
            @set_time_limit($timeout + 60);

            $client = app(AiModelClientFactory::class)->for($module, $subInstituteId);

            $text = $client->chat(
                [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
                null,
                $maxTokens > 0 ? $maxTokens : null,
                null,
                false,
                $timeout,
            );
        } catch (AiConfigurationException $e) {
            // Says what an administrator has to set up in AI Providers — never an environment variable.
            return ['ok' => false, 'error' => 'AI provider is not configured. ' . $e->getMessage()];
        } catch (Throwable $e) {
            Log::error('Question generation call failed: ' . $e->getMessage());

            // Provider failures already carry an explanation an administrator can act on.
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        if ($text === null || trim($text) === '') {
            return ['ok' => false, 'error' => 'The AI provider returned an empty message.'];
        }

        $used = $client->lastUsed();

        return [
            'ok' => true,
            'content' => $text,
            'model' => (string) ($used?->model ?? $client->defaultModel()),
            'finish_reason' => null,
            'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0],
        ];
    }
}
