<?php

namespace App\Domain\AI\Agents;

/**
 * What every agent implements.
 *
 * Deliberately small. An agent's job is to look at real data and produce a finding;
 * everything else — scope, permissions, persistence, audit, governance — is handled
 * by AgentRunner and AgentContext around it. Keeping the interface this narrow is
 * what stops each new agent re-implementing (and eventually mis-implementing) the
 * rules.
 *
 * `run()` returns a plain array. It does not decide whether to act on what it found.
 */
interface Agent
{
    /**
     * Execute the agent's analysis.
     *
     * Everything the agent may read or write is reached through $context. It must
     * not open its own database connection or resolve services from the container.
     *
     * @return array The agent's finding, matching its manifest's output schema
     */
    public function run(AgentContext $context): array;

    /**
     * A short description of what this run concluded, for the run log and for the
     * conversational layer to relay.
     */
    public function summarize(array $result): string;
}
