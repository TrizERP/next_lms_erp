<?php

namespace App\Domain\GenerativeAI;

/**
 * The outcome of a generation, always flagged as generated.
 *
 * `isGenerated` is a constant true rather than a parameter. Every path out of the
 * generation service produces content that is model output, and the frontend's
 * GeneratedContentBadge and GroundedClaims both depend on that being unconditional —
 * a result that could claim otherwise would be exactly the hole the brief warns about.
 */
final class GenerationResult
{
    public readonly bool $isGenerated;

    private function __construct(
        public readonly bool $succeeded,
        public readonly ?string $content = null,
        public readonly ?array $structured = null,
        public readonly ?int $requestId = null,
        public readonly ?int $outputId = null,
        public readonly ?string $model = null,
        public readonly ?string $provider = null,
        public readonly bool $schemaValid = false,
        public readonly array $schemaErrors = [],
        public readonly bool $safetyPassed = true,
        public readonly array $safetyReport = [],
        public readonly bool $requiresReview = false,
        public readonly ?string $error = null,
        public readonly ?int $latencyMs = null,
    ) {
        $this->isGenerated = true;
    }

    public static function success(
        string $content,
        ?array $structured,
        int $requestId,
        ?int $outputId,
        string $provider,
        string $model,
        bool $schemaValid,
        array $schemaErrors,
        bool $safetyPassed,
        array $safetyReport,
        bool $requiresReview,
        ?int $latencyMs
    ): self {
        return new self(
            true, $content, $structured, $requestId, $outputId, $model, $provider,
            $schemaValid, $schemaErrors, $safetyPassed, $safetyReport, $requiresReview, null, $latencyMs
        );
    }

    public static function failure(string $error, ?int $requestId = null, array $safetyReport = []): self
    {
        return new self(
            false, null, null, $requestId, null, null, null,
            false, [], $safetyReport === [], $safetyReport, false, $error, null
        );
    }

    /**
     * Usable means: it came back, it validated, and nothing in it tripped a safety
     * rule. Callers should check this rather than `succeeded` before showing content.
     */
    public function isUsable(): bool
    {
        return $this->succeeded && $this->safetyPassed && ($this->schemaErrors === []);
    }

    public function toArray(): array
    {
        return [
            'succeeded' => $this->succeeded,
            'is_generated' => true,
            'content' => $this->content,
            'structured' => $this->structured,
            'request_id' => $this->requestId,
            'output_id' => $this->outputId,
            'provider' => $this->provider,
            'model' => $this->model,
            'schema_valid' => $this->schemaValid,
            'schema_errors' => $this->schemaErrors,
            'safety_passed' => $this->safetyPassed,
            'requires_review' => $this->requiresReview,
            'error' => $this->error,
            'latency_ms' => $this->latencyMs,
        ];
    }
}
