<?php

namespace App\Domain\Eso\Flow;

/**
 * One institute's resolved learning flow: which stages run, in what order,
 * with what parameters, and which profile version produced it.
 *
 * Immutable and inert. It holds no behaviour and touches no database — it is
 * the ANSWER that EsoFlowResolver computed, handed to the pipeline so that a
 * single resolve cannot see the flow change underneath it.
 *
 * The version id is the piece that matters beyond this request: it is stamped
 * onto learner_node_state.flow_version_id when a node's state row is first
 * created (step 6), which is what stops an administrator publishing a new
 * profile version from changing the rules for a learner already mid-concept.
 */
final class EsoFlowPlan
{
    /**
     * @param  array<string, array{rank:int, enabled:bool, params:array<string,mixed>}>  $conceptStages
     * @param  array<string, array{rank:int, enabled:bool, params:array<string,mixed>}>  $nodeStages
     * @param  array<string, array{rank:int, enabled:bool, params:array<string,mixed>}>  $phases
     */
    public function __construct(
        private readonly array $conceptStages,
        private readonly array $nodeStages,
        private readonly array $phases,
        private readonly string $profileKey,
        private readonly ?int $versionId = null,
    ) {
    }

    /**
     * The profile version a learner starting now is pinned to.
     *
     * Null until step 5 seeds real profile rows, and null forever for the
     * shipped config default — a NULL pin on learner_node_state means "this
     * node predates flow versioning" and resolves to the standard profile.
     */
    public function versionId(): ?int
    {
        return $this->versionId;
    }

    public function profileKey(): string
    {
        return $this->profileKey;
    }

    /** @return array<int, string> enabled concept stages, in rank order */
    public function conceptStageKeys(): array
    {
        return $this->ordered($this->conceptStages);
    }

    /** @return array<int, string> enabled node stages, in rank order */
    public function nodeStageKeys(): array
    {
        return $this->ordered($this->nodeStages);
    }

    /** @return array<int, string> enabled phases, in rank order */
    public function phaseOrder(): array
    {
        return $this->ordered($this->phases);
    }

    /**
     * Is this phase part of the flow at all?
     *
     * checkSettled() consults this. When the check is disabled it MUST short
     * circuit to true, or a node with an authored CFU question loops forever:
     * checkSettled() stays false, the skip at EsoPolicyService.php:1141-1146
     * never fires, phaseFor() keeps answering 'check', and nothing serves it.
     */
    public function phaseEnabled(string $phase): bool
    {
        return (bool) ($this->phases[$phase]['enabled'] ?? false);
    }

    public function stageEnabled(string $key): bool
    {
        $stage = $this->conceptStages[$key] ?? $this->nodeStages[$key] ?? null;

        return (bool) ($stage['enabled'] ?? false);
    }

    /** @return array<string,mixed> */
    public function params(string $key): array
    {
        $stage = $this->conceptStages[$key] ?? $this->nodeStages[$key] ?? null;

        return (array) ($stage['params'] ?? []);
    }

    /** @return array<string,mixed> */
    public function phaseParams(string $phase): array
    {
        return (array) ($this->phases[$phase]['params'] ?? []);
    }

    /**
     * Every enabled stage, both scopes.
     *
     * The conformance suite subtracts the conditional ones from this and
     * asserts the remainder all fired — "no stage starves".
     *
     * @return array<int, string>
     */
    public function enabledStageKeys(): array
    {
        return array_merge($this->conceptStageKeys(), $this->nodeStageKeys());
    }

    /**
     * @param  array<string, array{rank:int, enabled:bool, params:array<string,mixed>}>  $stages
     * @return array<int, string>
     */
    private function ordered(array $stages): array
    {
        $enabled = array_filter($stages, static fn (array $s): bool => (bool) ($s['enabled'] ?? false));

        uasort($enabled, static fn (array $a, array $b): int => ($a['rank'] ?? 0) <=> ($b['rank'] ?? 0));

        return array_keys($enabled);
    }
}
