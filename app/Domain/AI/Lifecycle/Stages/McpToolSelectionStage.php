<?php

namespace App\Domain\AI\Lifecycle\Stages;

use App\Domain\AI\Lifecycle\LifecycleStage;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\StageKey;
use App\Domain\AI\Lifecycle\StageOutcome;
use App\Mcp\ToolRegistry;

/**
 * Stage 5 — committing to the tools the turn will actually call.
 *
 * The distinction this stage guards is between three things that are easy to conflate
 * and produce a self-contradicting trace when they are:
 *
 *   - what the module is **bound** to (configuration),
 *   - what the plan **proposed** (a candidate),
 *   - what the turn **committed** to calling (a selection).
 *
 * Only the third is a selection. A plan can name a lookup tool and then take a route
 * that never needs it, and a stage reporting "1 tool selected" while stage 6 reports no
 * calls leaves a reader with a contradiction they cannot resolve. So this stage reports
 * the commitment, and names the candidates it declined alongside it.
 *
 * It also drops any candidate that is not registered at all — a plan referencing a tool
 * that has since been removed should degrade to one fewer call, not to a crash.
 */
class McpToolSelectionStage implements LifecycleStage
{
    public function __construct(private readonly ToolRegistry $tools)
    {
    }

    public function key(): StageKey
    {
        return StageKey::McpToolSelection;
    }

    public function run(StageContext $context): StageOutcome
    {
        $plan = $context->plan;
        $candidates = $plan?->candidateTools ?? [];

        if ($candidates === []) {
            $context->selectedTools = [];

            return StageOutcome::skipped(
                'No MCP tool was selected; this turn answers from scoped domain services.',
                [
                    'selected_tools' => [],
                    'candidate_tools' => [],
                    'selection_strategy' => $plan?->toolSelectionStrategy ?? 'domain_services_only',
                    'module_bound_tools' => $context->module->mcpTools,
                ]
            )->withNote(
                'This route answers from governed agents, cases and workflow services rather than '
                . 'MCP tools. The transport was available and simply not needed.'
            );
        }

        $registered = $this->registeredNames();
        $selected = [];
        $dropped = [];

        foreach ($candidates as $candidate) {
            if (! in_array($candidate, $registered, true)) {
                $dropped[$candidate] = 'not registered in the tool registry';

                continue;
            }

            if (! in_array($candidate, $context->module->mcpTools, true)) {
                $dropped[$candidate] = sprintf('not bound to the %s module', $context->module->label);

                continue;
            }

            $selected[] = $candidate;
        }

        $context->selectedTools = $selected;

        if ($selected === []) {
            return StageOutcome::blocked(
                sprintf(
                    'The plan named %s, but none of them can be called on this turn.',
                    implode(', ', $candidates)
                ),
                ['candidate_tools' => $candidates, 'dropped' => $dropped]
            )->withNote('Every candidate was either unregistered or outside this module\'s bindings.');
        }

        return StageOutcome::ran(
            sprintf(
                'Selected %d MCP tool%s: %s.',
                count($selected),
                count($selected) === 1 ? '' : 's',
                implode(', ', $selected)
            ),
            [
                'selected_tools' => $selected,
                'candidate_tools' => $candidates,
                'dropped' => $dropped,
                'selection_strategy' => $plan?->toolSelectionStrategy,
                'planned_by' => $plan?->source,
            ]
        );
    }

    /**
     * @return array<int, string>
     */
    private function registeredNames(): array
    {
        return array_values(array_filter(array_map(
            static fn (array $definition) => $definition['name'] ?? null,
            $this->tools->definitions()
        ), 'is_string'));
    }
}
