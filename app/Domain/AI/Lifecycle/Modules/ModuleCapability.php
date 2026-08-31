<?php

namespace App\Domain\AI\Lifecycle\Modules;

/**
 * What one module can actually do, and — just as importantly — what it cannot yet.
 *
 * Stages 10 to 12 need an agent that owns a case type and a workflow that can act on
 * it. Today exactly one module has both. The wrong response to that is to hide those
 * stages for every other module, because then the ladder silently changes length and a
 * reader cannot tell a module that has no recommendation from a module that had one and
 * refused it.
 *
 * So every module reports all twelve stages, and a module without the depth to reach
 * stage 10 says so in the words a person can act on: not "not reached", but "no agent
 * owns the fees domain yet, so nothing can be recommended". `depthReason` is that
 * sentence, and it is required whenever the binding is absent.
 */
final class ModuleCapability
{
    /**
     * @param  array<string, bool>  $capabilities  conversational|generative|agent|workflow|ontology
     * @param  array<int, string>  $mcpTools  Tool names this module is permitted to select.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $description = '',
        public readonly ?string $entityKey = null,
        public readonly array $capabilities = [],
        public readonly array $mcpTools = [],
        public readonly ?string $agentKey = null,
        public readonly ?string $workflowKey = null,
        public readonly ?string $caseType = null,
        public readonly ?string $depthReason = null,
    ) {
    }

    /**
     * The module used when a question belongs to no module in particular.
     *
     * It is conversational only and binds no tools, which means a question that lands
     * here reaches stage 2, is understood or refused there, and reports honestly about
     * the ten stages it had no business entering. That is a better answer than routing
     * an unclassifiable question at the student agent and running a cohort scan for it.
     */
    public static function general(): self
    {
        return new self(
            key: 'general',
            label: 'General',
            description: 'Questions that do not belong to one module.',
            capabilities: ['conversational' => true],
            depthReason: 'This question was not scoped to a module, so no module agent owns it.',
        );
    }

    public function supports(string $capability): bool
    {
        return ($this->capabilities[$capability] ?? false) === true;
    }

    /** True when the module can run an agent that opens cases. */
    public function hasAgent(): bool
    {
        return $this->agentKey !== null && $this->supports('agent');
    }

    /** True when an approved recommendation has somewhere to go. */
    public function hasWorkflow(): bool
    {
        return $this->workflowKey !== null && $this->supports('workflow');
    }

    /**
     * Why this module cannot reach the deep stages, in one sentence a reader can act on.
     *
     * Falls back to a generated sentence rather than an empty string, because a stage
     * rendering `not_reached` with no reason is the exact defect the lifecycle view
     * exists to prevent.
     */
    public function whyNoDepth(): string
    {
        if ($this->depthReason !== null) {
            return $this->depthReason;
        }

        // Two different absences, and telling them apart is the difference between a
        // useful sentence and a misleading one. A module with tools genuinely answers
        // from live records and merely stops short of recommending; a module with
        // neither an agent nor a tool cannot answer at all, and saying it "can answer
        // from real data" would be simply untrue.
        if ($this->mcpTools === []) {
            return sprintf(
                'The %s module has no agent and no data tools bound to it yet, so the lifecycle has '
                . 'nothing to run for it — a question here is understood and then honestly declined.',
                strtolower($this->label)
            );
        }

        if (! $this->hasAgent()) {
            return sprintf(
                'No agent is registered for the %s module yet, so this module can answer from real data '
                . 'but cannot open a case or recommend an action.',
                strtolower($this->label)
            );
        }

        if (! $this->hasWorkflow()) {
            return sprintf(
                'The %s module can recommend, but no workflow is bound to carry an approved '
                . 'recommendation into a real change.',
                strtolower($this->label)
            );
        }

        return 'This module has the depth to reach this stage, but this turn had nothing for it to do.';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'entity_key' => $this->entityKey,
            'capabilities' => $this->capabilities,
            'mcp_tools' => $this->mcpTools,
            'agent_key' => $this->agentKey,
            'workflow_key' => $this->workflowKey,
            'case_type' => $this->caseType,
            'reaches_recommendation' => $this->hasAgent(),
            'reaches_action' => $this->hasWorkflow(),
        ];
    }
}
