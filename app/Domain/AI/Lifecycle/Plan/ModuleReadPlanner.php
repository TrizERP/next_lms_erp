<?php

namespace App\Domain\AI\Lifecycle\Plan;

use App\Domain\AI\Lifecycle\Modules\ModuleCapability;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\Support\ToolAnswerComposer;
use App\Domain\AI\Modules\ModuleReadTools;
use App\Services\Mcp\DateRangeResolver;

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

        // The question named nothing any module's vocabulary recognises — not this one,
        // not any other. That is what small talk, a capability question ("what can I do
        // here?") or general knowledge looks like from the resolver's side, and it is a
        // different case from "asked about fees on the fees screen but phrased oddly",
        // which does score for something and is exactly what this planner exists to
        // rescue. Reading the screen's own tools for a domain-free sentence is a guess,
        // not a fallback, and it was the reason such questions always ended up being
        // forced through a case-resolution read that had nothing to resolve: the blind
        // tool call "completed" with a real but irrelevant answer, so nothing downstream
        // could recognise the turn as one a general answer should take instead.
        // "Show all" after a truncated list asks for the same read again without the cap. It names no
        // module word, so it is decided before the check below, and only from the list the thread holds.
        $again = $this->rereadForShowAll($context, $module);

        if ($again !== null) {
            return $again;
        }

        if ($context->get('modules_considered', []) === []) {
            return null;
        }

        $candidates = $this->readableTools($module->mcpTools);

        if ($candidates === []) {
            return null;
        }

        // The words may point at a tool further down the module's list ("chapters", "lesson plan").
        // It is looked for among every readable bound tool, not just the first MAX_TOOLS.
        $hinted = $this->tools()->hintedTool(
            $module->key,
            $this->tools()->select($module->mcpTools, 50),
            $context->question
        );

        if ($hinted !== null) {
            $candidates = array_values(array_unique(array_merge([$hinted], $candidates)));
        }

        // A question that names a day or a range ("fees collected yesterday") is about that period, and
        // reading the module's first tool with no dates answers a different question with all-time
        // figures. When a bound tool declares a `period` argument it can be asked exactly that, so it is
        // read instead, handed the question's own words; the tool resolves them against today's date.
        $dated = DateRangeResolver::mentionsDate($context->question)
            ? $this->tools()->acceptingPeriod($module->mcpTools)
            : null;

        if ($dated !== null) {
            return new Plan(
                goal: sprintf('Answer from the %s data for the period the question names.', $module->label),
                steps: [
                    new PlanStep(
                        id: 'read_period',
                        purpose: 'Read what was recorded for the day or range the question names.',
                        tool: $dated,
                        arguments: ['period' => $context->question],
                    ),
                    new PlanStep(id: 'report', purpose: 'Report what the records show for exactly that period.'),
                ],
                source: Plan::SOURCE_MODULE_READ,
                route: 'mcp_tools',
                intentKey: $context->intent?->key,
                candidateTools: [$dated],
                toolSelectionStrategy: 'module_read_dated',
                context: [
                    'module' => $module->key,
                    // A dated read is aimed at the question, unlike the blind overview: the answer should not
                    // be prefixed as if nothing had been matched.
                    'dated' => true,
                    'matched_by' => 'a date in the question and a tool that takes a period',
                    'reason' => 'No intent route and no model plan; the question names a period and this module binds a tool that answers by period.',
                ],
            );
        }

        // The read was aimed by the question's own words ("Grade 8"), so it answers the question rather
        // than being a guess at what the screen shows.
        $aimed = $this->tools()->argumentsFromQuestion($candidates[0], $context->question);

        $steps = [
            new PlanStep(
                id: 'read_module',
                purpose: sprintf('Read the %s records this page is about.', $module->label),
                tool: $candidates[0],
                arguments: $aimed,
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
                'targeted' => $aimed !== [],
                // Stated plainly so the trace never implies an intent matched when none
                // did. This is the honest description of what happened: no route was
                // found, so the module's own data was read.
                'matched_by' => 'module read fallback',
                'reason' => 'No intent route and no model plan; answered from the module\'s bound read tools.',
            ],
        );
    }

    /**
     * The previous list read again, uncapped, when the question is "show all" / "show the rest".
     *
     * Only the tool the thread's last list came from, only when this module binds it as a readable
     * tool, and with the arguments that list was read with — so "show all" keeps Grade 8 and never
     * reaches a tool the module does not own.
     */
    private function rereadForShowAll(StageContext $context, ModuleCapability $module): ?Plan
    {
        if (! ToolAnswerComposer::asksForEverything($context->question)) {
            return null;
        }

        $set = $context->thread['memory']['last_result_set'] ?? null;

        if (! is_array($set) || ! is_string($set['tool'] ?? null) || (int) ($set['total'] ?? 0) <= count((array) ($set['items'] ?? []))) {
            return null;
        }

        $tool = $set['tool'];

        if (! in_array($tool, $this->readableTools($module->mcpTools), true)) {
            return null;
        }

        $arguments = array_merge(
            (array) ($set['arguments'] ?? []),
            $this->tools()->argumentsFromQuestion($tool, $context->question)
        );

        return new Plan(
            goal: sprintf('List every record of the previous %s answer.', $module->label),
            steps: [
                new PlanStep(
                    id: 'read_all',
                    purpose: 'Read the same records again, without the display cap.',
                    tool: $tool,
                    arguments: $arguments,
                ),
                new PlanStep(id: 'report', purpose: 'Report every record the read returned.'),
            ],
            source: Plan::SOURCE_MODULE_READ,
            route: 'mcp_tools',
            intentKey: $context->intent?->key,
            candidateTools: [$tool],
            toolSelectionStrategy: 'module_read_show_all',
            context: [
                'module' => $module->key,
                'targeted' => true,
                'matched_by' => 'a request to show all of the previous list',
                'reason' => 'The thread holds a truncated list from this module; the same read is repeated in full.',
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
