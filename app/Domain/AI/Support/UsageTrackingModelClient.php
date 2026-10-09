<?php

namespace App\Domain\AI\Support;

use App\Domain\AI\Configuration\ResolvedAiConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * D6 (shared cross-product AI gateway), minimal first step for K-12: this
 * product had zero AI usage tracking before this class existed — not a
 * schema gap, a complete absence. G2G and EB already each have one gateway
 * class per product that every real call funnels through (AiModelClient,
 * AiGateway), so their usage tables get fed by instrumenting ONE place.
 * K-12's equivalent single seam is the `ModelClient` container binding in
 * AiServiceProvider's container binding — but that binding is only HALF the
 * module-aware path: AiModelClientFactory::for()/fromConfiguration() builds
 * its own client via ProviderCatalog::driver() and bypasses the container
 * entirely, so it is wrapped at its own return point too (see
 * AiModelClientFactory::fromConfiguration()). Two edit points, both inside
 * the gateway's own infrastructure, neither inside a feature — not the 15+
 * services that call a provider directly and bypass ModelClient altogether.
 *
 * Honest limitation, not hidden: unlike G2G/EB's gateways, this interface's
 * `chat()`/`json()` return only the completion text, never a token-usage
 * object — so input_tokens/output_tokens/estimated_cost_usd stay null here.
 * Recording provider/model/outcome/latency per call is still a real step
 * from "nothing," and getting token counts would mean instrumenting inside
 * each of the three provider classes individually, which is the 40+-call-
 * site-scale work this round was explicitly scoped to avoid.
 */
final class UsageTrackingModelClient implements ModelClient
{
    private ?int $subInstituteId = null;

    public function __construct(private readonly ModelClient $inner)
    {
    }

    public function isConfigured(): bool
    {
        return $this->inner->isConfigured();
    }

    public function forInstitute(int|string|null $subInstituteId): static
    {
        $clone = new self($this->inner->forInstitute($subInstituteId));
        $clone->subInstituteId = is_numeric($subInstituteId) ? (int) $subInstituteId : null;

        return $clone;
    }

    public function withConfiguration(ResolvedAiConfiguration $configuration): static
    {
        $clone = new self($this->inner->withConfiguration($configuration));
        $clone->subInstituteId = $this->subInstituteId;

        return $clone;
    }

    public function defaultModel(): string
    {
        return $this->inner->defaultModel();
    }

    public function chat(
        array $messages,
        ?string $model = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        bool $expectJson = false,
        ?int $timeout = null,
    ): ?string {
        $startedAt = microtime(true);

        try {
            $result = $this->inner->chat($messages, $model, $maxTokens, $temperature, $expectJson, $timeout);
            $this->record($model, 'ok', $startedAt);

            return $result;
        } catch (Throwable $e) {
            $this->record($model, 'error', $startedAt, $e->getMessage());

            throw $e;
        }
    }

    public function stream(
        array $messages,
        callable $onDelta,
        ?string $model = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
    ): ?string {
        $startedAt = microtime(true);

        try {
            $result = $this->inner->stream($messages, $onDelta, $model, $maxTokens, $temperature);
            $this->record($model, 'ok', $startedAt);

            return $result;
        } catch (Throwable $e) {
            $this->record($model, 'error', $startedAt, $e->getMessage());

            throw $e;
        }
    }

    public function json(
        array $messages,
        ?string $model = null,
        int $maxTokens = 900,
        float $temperature = 0.0,
    ): ?array {
        $startedAt = microtime(true);
        $result = $this->inner->json($messages, $model, $maxTokens, $temperature);
        $this->record($model, $result === null ? 'error' : 'ok', $startedAt, $result === null ? 'no usable answer' : null);

        return $result;
    }

    private function record(?string $model, string $outcome, float $startedAt, ?string $error = null): void
    {
        // Usage logging must never be the reason a real AI call fails or
        // looks slower than it is to the caller.
        try {
            DB::table('ai_usage_events')->insert([
                'product' => 'k12',
                'provider' => class_basename($this->inner),
                'model' => $model ?? $this->inner->defaultModel(),
                'outcome' => $outcome,
                'error' => $error !== null ? mb_substr($error, 0, 500) : null,
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'sub_institute_id' => $this->subInstituteId,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::channel('daily')->warning('AI usage logging failed', ['error' => $e->getMessage()]);
        }
    }
}
