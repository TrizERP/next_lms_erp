<?php

namespace App\Domain\AI\Lifecycle\Plan;

use App\Domain\AI\Lifecycle\StageContext;
use App\Mcp\ToolRegistry;

/**
 * The last resort that still answers: read the module the user is looking at.
 *
 * WHY THIS EXISTS
 *
 * Stage 4 used to be a cliff. If the intent registry had no route and the model planner
 * could not produce one — because the question was novel, or because no provider
 * credential is configured — planning returned null, the stage refused, and stages 5
 * to 12 were all marked not-reached. The user saw twelve rows of "not reached" and no
 * answer, for a question like "show me the students who have pending fees" asked while
 * looking at the Fees page, where the data was one bound tool call away.
 *
 * That is the wrong failure. Not knowing which of several routes is best is a reason to
 * take the plainest one, not a reason to answer nothing. So when neither planner can
 * route a question, this one plans the obvious thing: call the module's own read tools
 * and report what comes back.
 *
 * WHY IT IS GENERIC AND HAS NO KEYWORD TABLE
 *
 * It knows nothing about fees, attendance or admissions. Everything it needs is already
 * declared per module in `config('ai.lifecycle.modules')` and per tool in the tool's own
 * schema, so a module added later is served by it without a line changing here. That is
 * deliberate: a table of phrases mapping "pending" to one tool and "collection" to
 * another is a maintenance burden that grows with every module and is wrong the first
 * time somebody phrases a question differently.
 *
 * TWO RULES DECIDE WHICH TOOLS ARE USABLE
 *
 *   1. **Read-only.** A fallback runs when the system is least sure what was meant, so
 *      it must never reach a tool that changes a record. `admissions.confirm` and
 *      `ai.templates.generate` are both bound to modules and both excluded here.
 *   2. **No unsatisfiable required argument.** `fees.getPending` requires a
 *      `student_id`; on a page-level question there is no student, so calling it can
 *      only fail. A tool whose required arguments cannot be filled from the page context
 *      is skipped rather than called and reported as an error.
 *
 * WHAT IT DELIBERATELY DOES NOT DO
 *
 * It does not run when the question sounds consequential — `HybridPlanner` has already
 * refused those before reaching here — and it never proposes more than
 * `MAX_TOOLS` calls, so an unrecognised question cannot fan out into every tool a
 * module happens to bind.
 */
class ModuleReadPlanner implements Planner
{
    /**
     * How many tools one fallback turn may call.
     *
     * More than one because the useful answer is sometimes in the second tool — a
     * question on the Fees page may be about arrears or about collection, and reading
     * both costs two scoped queries and lets the composer pick the list that actually
     * has rows. Not many more than one, because this path runs when the system is least
     * certain, and uncertainty is a poor reason to make ten database calls.
     */
    private const MAX_TOOLS = 2;

    /**
     * The registry is resolved per call rather than injected.
     *
     * Building `ToolRegistry` instantiates every registered tool, which needs the MCP
     * bindings. Taking it as a constructor argument made this planner unconstructable
     * in a plain unit test — `LifecyclePipelineTest` builds a `HybridPlanner` by hand to
     * assert which planner a sentence reaches, and it should not have to stand up the
     * whole tool layer to do that. Resolving on demand keeps the planner cheap to
     * construct and the registry a singleton either way.
     */
    private function registry(): ?ToolRegistry
    {
        try {
            return app(ToolRegistry::class);
        } catch (\Throwable) {
            // No container, or no tool layer bound in it. There is then nothing to fall
            // back to, and this planner's whole contract is "propose a read or propose
            // nothing" — so it proposes nothing rather than taking the turn down with it.
            return null;
        }
    }

    public function plan(StageContext $context): ?Plan
    {
        $module = $context->module;

        // `general` is the module the resolver returns when the route matched nothing.
        // There is no "its own data" to read, so there is nothing to fall back to.
        if ($module->key === 'general' || ! $module->supports('conversational')) {
            return null;
        }

        $candidates = $this->readableTools($module->mcpTools);

        if ($candidates === []) {
            return null;
        }

        $steps = [
            new PlanStep(
                id: 'read_module',
                purpose: sprintf('Read the %s records this page is about.', $module->label),
                tool: $candidates[0],
            ),
            new PlanStep(
                id: 'report',
                purpose: 'Report what the records show, and say what was read to find it.',
            ),
        ];

        return new Plan(
            goal: sprintf('Answer from the %s data this page is showing.', $module->label),
            steps: $steps,
            source: Plan::SOURCE_MODULE_READ,
            // `mcp_tools`, because that is the route `LaravelMcpStage` dispatches to
            // `runPlannedSteps()` — "make the calls this plan names". The obvious-looking
            // `conversation` falls into that stage's default arm, which resolves a
            // student by name instead; with no name in the question it makes no calls at
            // all, and the turn reports "the selected tool was not needed" after having
            // selected two.
            route: 'mcp_tools',
            intentKey: $context->intent?->key,
            candidateTools: $candidates,
            toolSelectionStrategy: 'module_read_fallback',
            context: [
                'module' => $module->key,
                // Stated plainly so the trace never implies an intent matched when none
                // did. This is the honest description of what happened: no route was
                // found, so the module's own data was read.
                'matched_by' => 'module read fallback',
                'reason' => 'No intent route and no model plan; answered from the module\'s bound read tools.',
            ],
        );
    }

    /**
     * The bound tools this path may call, in the order the module declares them.
     *
     * @param  array<int, string>  $bound
     * @return array<int, string>
     */
    private function readableTools(array $bound): array
    {
        $registry = $this->registry();

        if ($registry === null) {
            return [];
        }

        $usable = [];

        foreach ($bound as $name) {
            $tool = $registry->tool($name);

            if ($tool === null) {
                continue;
            }

            $definition = $tool->definition();

            if (($definition['annotations']['read_only'] ?? false) !== true) {
                continue;
            }

            if ($this->needsArgumentsWeDoNotHave($definition['input_schema'] ?? [])) {
                continue;
            }

            $usable[] = $name;

            if (count($usable) >= self::MAX_TOOLS) {
                break;
            }
        }

        return $usable;
    }

    /**
     * Whether a tool demands something a page-level question cannot supply.
     *
     * Only the tool's own `required` list is consulted. An optional filter is fine —
     * omitting it means "no filter", which is exactly what a general question wants.
     *
     * @param  array<string, mixed>  $schema
     */
    private function needsArgumentsWeDoNotHave(array $schema): bool
    {
        $required = $schema['required'] ?? [];

        return is_array($required) && $required !== [];
    }
}
