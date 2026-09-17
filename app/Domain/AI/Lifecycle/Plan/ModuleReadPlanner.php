<?php

namespace App\Domain\AI\Lifecycle\Plan;

use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Modules\ModuleReadTools;

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
 * Read-only, and no required argument a page-level question cannot supply.
 *
 * Both rules live in `ModuleReadTools` rather than here, because the workspace's Create
 * and Analyse tabs need the same answer when they resolve the data a template is about.
 * The chat and the report must not disagree about which of a module's tools may be read.
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
     * Constructed on demand rather than injected, so `new ModuleReadPlanner()` keeps
     * working in the unit tests that build a `HybridPlanner` by hand — see the note in
     * `ModuleReadTools` about why the tool registry cannot be a constructor argument.
     */
    private function tools(): ModuleReadTools
    {
        return new ModuleReadTools();
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
        return $this->tools()->select($bound, self::MAX_TOOLS);
    }
}
