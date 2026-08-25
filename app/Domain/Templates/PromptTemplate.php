<?php

namespace App\Domain\Templates;

/**
 * A versioned prompt.
 *
 * Prompts used to be private methods on OpenAIService and AIOrchestrationService,
 * which meant they could not be reused across domains, changed without a deploy, or
 * pointed at afterwards to explain what produced a piece of text. As a row they can
 * be all three — and `allowAsEvidence` lets the platform state, per template, whether
 * its output is ever a candidate for verification.
 */
final class PromptTemplate
{
    public function __construct(
        public readonly int $id,
        public readonly string $key,
        public readonly string $name,
        public readonly int $version,
        public readonly string $userPrompt,
        public readonly ?string $systemPrompt = null,
        public readonly string $domain = 'shared',
        public readonly ?string $category = null,
        public readonly array $variables = [],
        public readonly array $outputSchema = [],
        public readonly string $outputFormat = 'text',
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly ?float $temperature = null,
        public readonly ?int $maxTokens = null,
        public readonly array $safetyRules = [],
        public readonly bool $allowAsEvidence = false,
        public readonly bool $requiresReview = false,
    ) {
    }

    public static function fromRow(object $row): self
    {
        return new self(
            id: (int) $row->id,
            key: (string) $row->template_key,
            name: (string) $row->name,
            version: (int) $row->version,
            userPrompt: (string) $row->user_prompt,
            systemPrompt: $row->system_prompt ?? null,
            domain: (string) ($row->domain ?? 'shared'),
            category: $row->category ?? null,
            variables: self::decode($row->variables ?? null),
            outputSchema: self::decode($row->output_schema ?? null),
            outputFormat: (string) ($row->output_format ?? 'text'),
            provider: $row->provider ?? null,
            model: $row->model ?? null,
            temperature: $row->temperature === null ? null : (float) $row->temperature,
            maxTokens: $row->max_tokens === null ? null : (int) $row->max_tokens,
            safetyRules: self::decode($row->safety_rules ?? null),
            allowAsEvidence: (bool) ($row->allow_as_evidence ?? false),
            requiresReview: (bool) ($row->requires_review ?? false),
        );
    }

    /**
     * Variables the caller failed to supply.
     *
     * @return array<int, string>
     */
    public function missingVariables(array $supplied): array
    {
        $missing = [];

        foreach ($this->variables as $variable) {
            $key = $variable['key'] ?? null;

            if ($key === null || ! ($variable['required'] ?? false)) {
                continue;
            }

            $value = $supplied[$key] ?? null;

            if ($value === null || (is_string($value) && trim($value) === '')) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'version' => $this->version,
            'domain' => $this->domain,
            'category' => $this->category,
            'variables' => $this->variables,
            'output_format' => $this->outputFormat,
            'provider' => $this->provider,
            'model' => $this->model,
            'allow_as_evidence' => $this->allowAsEvidence,
            'requires_review' => $this->requiresReview,
        ];
    }

    private static function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
