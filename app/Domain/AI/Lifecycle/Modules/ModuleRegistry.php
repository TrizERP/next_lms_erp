<?php

namespace App\Domain\AI\Lifecycle\Modules;

use App\Domain\AI\Agents\AgentRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The modules the lifecycle serves, and how deep each one goes.
 *
 * Two sources, deliberately kept apart:
 *
 *   - `ai_modules` holds what a school can retune without a deploy — the label, the
 *     routes that map to the module, which broad capabilities it offers.
 *   - `config/ai.php` holds the bindings that name things which only exist in code: an
 *     agent key, a workflow key, the MCP tools the module may select. Putting those in
 *     a table would let an administrator bind a module to an agent that does not exist.
 *
 * The registry then does the one thing neither source can do alone: it *verifies* the
 * binding. A module configured with an agent key that is absent from `ai_agents` is
 * returned with no agent and a reason saying so, rather than reaching stage 3 and
 * failing there. A half-configured estate degrades to fewer stages with explanations,
 * never to a crash.
 */
class ModuleRegistry
{
    /**
     * Resolved modules, keyed by the institute they were resolved for.
     *
     * Keyed rather than a single slot because the agent and workflow bindings are
     * *verified per tenant* — `verifiedAgentKey()` asks `AgentRegistry` whether that
     * institute has an active manifest. A flat memo returned the first caller's answer to
     * every later one, so anything that resolves more than one institute in a single
     * process — `ai:journey`, a queue worker, Octane — would hand institute B the modules
     * institute A was entitled to. Harmless in a one-tenant HTTP request and wrong
     * everywhere else, which is the worst shape for a bug to have.
     *
     * @var array<string, array<string, ModuleCapability>>
     */
    private array $memo = [];

    public function __construct(private readonly AgentRegistry $agents)
    {
    }

    /**
     * @return array<string, ModuleCapability>
     */
    public function all(?int $subInstituteId = null): array
    {
        $cacheKey = $subInstituteId === null ? 'global' : (string) $subInstituteId;

        if (isset($this->memo[$cacheKey])) {
            return $this->memo[$cacheKey];
        }

        $bindings = (array) config('ai.lifecycle.modules', []);
        $rows = $this->moduleRows();
        $modules = [];

        foreach ($rows as $row) {
            $key = (string) $row['module_key'];
            $binding = (array) ($bindings[$key] ?? []);

            $modules[$key] = $this->build($key, $row, $binding, $subInstituteId);
        }

        // A module bound in config but absent from `ai_modules` still deserves to exist:
        // the table is a routing aid, not the source of truth for what the platform can
        // do, and an estate that has not run the workspace seeding should not lose its
        // agent-backed modules as a result.
        foreach ($bindings as $key => $binding) {
            if (! isset($modules[$key]) && is_array($binding)) {
                $modules[$key] = $this->build($key, null, $binding, $subInstituteId);
            }
        }

        $modules['general'] = ModuleCapability::general();

        return $this->memo[$cacheKey] = $modules;
    }

    public function find(string $key, ?int $subInstituteId = null): ?ModuleCapability
    {
        return $this->all($subInstituteId)[$key] ?? null;
    }

    /**
     * The module a question belongs to, or the general one.
     */
    public function findOrGeneral(?string $key, ?int $subInstituteId = null): ModuleCapability
    {
        return ($key !== null ? $this->find($key, $subInstituteId) : null) ?? ModuleCapability::general();
    }

    /**
     * Modules that can reach stage 10 and beyond.
     *
     * @return array<int, ModuleCapability>
     */
    public function withDepth(?int $subInstituteId = null): array
    {
        return array_values(array_filter(
            $this->all($subInstituteId),
            static fn (ModuleCapability $module) => $module->hasAgent()
        ));
    }

    public function flush(): void
    {
        $this->memo = [];
    }

    // ---------------------------------------------------------------- internals

    /**
     * @param  array<string, mixed>|null  $row
     * @param  array<string, mixed>  $binding
     */
    private function build(string $key, ?array $row, array $binding, ?int $subInstituteId): ModuleCapability
    {
        $capabilities = $this->capabilitiesOf($row, $binding);
        $agentKey = $this->verifiedAgentKey($binding, $subInstituteId, $unverified);
        $workflowKey = $this->verifiedWorkflowKey($binding);

        // The capability flags and the bindings have to agree. A module flagged
        // `agent => true` with nothing registered would offer a stage it cannot reach.
        $capabilities['agent'] = ($capabilities['agent'] ?? false) && $agentKey !== null;
        $capabilities['workflow'] = ($capabilities['workflow'] ?? false) && $workflowKey !== null;

        return new ModuleCapability(
            key: $key,
            label: (string) ($row['label'] ?? $binding['label'] ?? ucfirst(str_replace('_', ' ', $key))),
            description: (string) ($row['description'] ?? $binding['description'] ?? ''),
            entityKey: $row['entity_key'] ?? $binding['entity_key'] ?? null,
            capabilities: $capabilities,
            mcpTools: array_values(array_filter(
                (array) ($binding['mcp_tools'] ?? []),
                'is_string'
            )),
            agentKey: $agentKey,
            workflowKey: $workflowKey,
            caseType: $binding['case_type'] ?? null,
            depthReason: $unverified ?? ($binding['depth_reason'] ?? null),
        );
    }

    /**
     * @param  array<string, mixed>|null  $row
     * @param  array<string, mixed>  $binding
     * @return array<string, bool>
     */
    private function capabilitiesOf(?array $row, array $binding): array
    {
        $raw = $row['capabilities'] ?? $binding['capabilities'] ?? [];

        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: [];
        }

        $capabilities = [];

        foreach (['conversational', 'generative', 'agent', 'workflow', 'ontology'] as $name) {
            $capabilities[$name] = (bool) (is_array($raw) ? ($raw[$name] ?? false) : false);
        }

        // Every module can be talked to. A module that could not would have no reason to
        // appear in a conversational lifecycle at all.
        $capabilities['conversational'] = true;

        return $capabilities;
    }

    /**
     * @param  array<string, mixed>  $binding
     * @param  string|null  $unverified  Set to an explanatory sentence when the binding names a missing agent.
     */
    private function verifiedAgentKey(array $binding, ?int $subInstituteId, ?string &$unverified = null): ?string
    {
        $unverified = null;
        $agentKey = $binding['agent_key'] ?? null;

        if (! is_string($agentKey) || $agentKey === '') {
            return null;
        }

        if ($this->agents->find($agentKey, $subInstituteId) === null) {
            $unverified = sprintf(
                'This module is configured to use the "%s" agent, but no active manifest for it exists '
                . 'in ai_agents — so nothing can run, and nothing downstream of it can happen.',
                $agentKey
            );

            return null;
        }

        return $agentKey;
    }

    /**
     * @param  array<string, mixed>  $binding
     */
    private function verifiedWorkflowKey(array $binding): ?string
    {
        $workflowKey = $binding['workflow_key'] ?? null;

        if (! is_string($workflowKey) || $workflowKey === '') {
            return null;
        }

        if (! Schema::hasTable('workflow_definitions')) {
            return null;
        }

        $exists = DB::table('workflow_definitions')
            ->where('workflow_key', $workflowKey)
            ->exists();

        return $exists ? $workflowKey : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function moduleRows(): array
    {
        if (! Schema::hasTable('ai_modules')) {
            return [];
        }

        return DB::table('ai_modules')
            ->orderBy('sort_order')
            ->get()
            ->map(static fn ($row) => (array) $row)
            ->all();
    }
}
