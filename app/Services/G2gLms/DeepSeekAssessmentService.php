<?php

namespace App\Services\G2gLms;

use App\Domain\AI\Configuration\AiModelClientFactory;
use App\Domain\AI\Support\FallbackChainModelClient;
use Throwable;

/**
 * JSON chat client for `AiAssessmentController::generate()` and
 * `CourseBuilderController::generateQuestions()`.
 *
 * The class name is historical (it once posted straight to DeepSeek). It now runs on the
 * platform's centralised AI configuration: module `assessment_ai`, resolved through
 * `AiModelClientFactory`. Provider, model, key and fallback providers all come from what the
 * administrator saved; nothing is read from config/deepseek.php or the environment.
 *
 * Public surface is unchanged: `isConfigured()`, `model()` and `chatJson(array $messages): array`.
 */
class DeepSeekAssessmentService
{
    public const MODULE = 'assessment_ai';

    protected int $timeout;
    protected int $maxTokens;

    /** The school the call is made for. Null = derive from the signed-in session. */
    protected int|string|null $subInstituteId = null;

    private ?FallbackChainModelClient $client = null;

    public function __construct()
    {
        // Non-provider tuning only.
        $this->timeout   = (int) config('deepseek.timeout_seconds', 120);
        $this->maxTokens = (int) config('deepseek.max_output_tokens', 0);
    }

    /** Scope the service to a school explicitly (otherwise the signed-in school is used). */
    public function forInstitute(int|string|null $subInstituteId): static
    {
        $this->subInstituteId = $subInstituteId;
        $this->client = null;

        return $this;
    }

    /** The authenticated school: explicit scope first, then the session. Never request input. */
    protected function tenant(): int|string|null
    {
        if ($this->subInstituteId !== null) {
            return $this->subInstituteId;
        }

        try {
            $sid = session()->get('sub_institute_id');
        } catch (Throwable $e) {
            return null;
        }

        return is_numeric($sid) && (int) $sid > 0 ? (int) $sid : null;
    }

    protected function client(): FallbackChainModelClient
    {
        return $this->client ??= app(AiModelClientFactory::class)->for(self::MODULE, $this->tenant());
    }

    /** The model the module is configured to use (the head of its provider chain). */
    public function model(): string
    {
        $used = $this->client()->lastUsed();
        if ($used !== null && (string) $used->model !== '') {
            return (string) $used->model;
        }

        return (string) $this->client()->defaultModel();
    }

    public function isConfigured(): bool
    {
        return $this->client()->isConfigured();
    }

    /**
     * @param array<int, array{role:string, content:string}> $messages
     * @return array Decoded JSON object from the model's reply.
     * @throws \App\Domain\AI\Configuration\AiConfigurationException when assessment_ai is not configured.
     * @throws Throwable when the call fails or the reply is not valid JSON.
     */
    public function chatJson(array $messages): array
    {
        $client = $this->client();

        $content = $client->chat(
            $messages,
            null,
            $this->maxTokens > 0 ? $this->maxTokens : null,
            null,
            true,
            $this->timeout
        );

        if ($content === null || trim($content) === '') {
            throw new \RuntimeException('The AI provider returned an empty message.');
        }

        $content = trim($content);
        if (str_starts_with($content, '```')) {
            $content = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $content) ?? $content);
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('The AI provider did not return valid JSON.');
        }

        return $decoded;
    }
}
