<?php

namespace App\Domain\AI\Lifecycle\Plan;

/**
 * One step of a plan: a thing the turn intends to do, and why.
 *
 * `tool` is nullable on purpose. Plenty of steps are not tool calls — running an agent,
 * reading a stored case, composing an explanation — and forcing every step to name a
 * tool would either invent tools that do not exist or hide the steps that matter most.
 */
final class PlanStep
{
    /**
     * @param  array<string, mixed>  $arguments  What to call the tool with. Empty for a
     *                                           step that is reasoning rather than a call.
     * @param  array<int, string>  $dependsOn  Ids of earlier steps whose results this needs.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $purpose,
        public readonly ?string $tool = null,
        public readonly array $arguments = [],
        public readonly array $dependsOn = [],
    ) {
    }

    public function isToolCall(): bool
    {
        return $this->tool !== null;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): ?self
    {
        $id = trim((string) ($raw['id'] ?? ''));
        $purpose = trim((string) ($raw['purpose'] ?? ''));

        if ($id === '' || $purpose === '') {
            return null;
        }

        $tool = $raw['tool'] ?? null;
        $arguments = $raw['arguments'] ?? [];

        return new self(
            id: $id,
            purpose: $purpose,
            tool: is_string($tool) && $tool !== '' ? $tool : null,
            arguments: is_array($arguments) ? $arguments : [],
            dependsOn: array_values(array_filter(
                (array) ($raw['depends_on'] ?? $raw['dependsOn'] ?? []),
                'is_string'
            )),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'purpose' => $this->purpose,
            'tool' => $this->tool,
            'arguments' => $this->arguments ?: null,
            'depends_on' => $this->dependsOn ?: null,
        ], static fn ($value) => $value !== null);
    }
}
