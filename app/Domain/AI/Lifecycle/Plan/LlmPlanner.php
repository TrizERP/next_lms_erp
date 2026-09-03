<?php

namespace App\Domain\AI\Lifecycle\Plan;

use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Support\OpenRouterClient;
use App\Mcp\ToolRegistry;

/**
 * The breadth path: a question nobody wrote an intent for.
 *
 * A model plans here, and two rules keep that trustworthy rather than merely clever:
 *
 *   1. **Every step is validated against the tools this module is actually bound to,
 *      before anything executes.** A plan naming a tool the caller cannot reach is
 *      rejected whole rather than half-run. This is the difference between a planner and
 *      a suggestion box.
 *
 *   2. **A plan may refuse.** Some questions cannot be answered because the estate does
 *      not record the thing being asked about, and deciding that at plan time is what
 *      produces "the school holds no competency records" rather than a headcount
 *      presented as a capability judgement. The refusal is carried on the plan and the
 *      answer stage honours it.
 *
 * This planner never routes an approval. Consequential intents are matched
 * deterministically upstream, and the prompt below forbids proposing one — so the model
 * can widen what the platform can answer without widening what it can do.
 */
class LlmPlanner implements Planner
{
    private const MAX_STEPS = 6;

    private const MODEL = 'deepseek/deepseek-chat';

    public function __construct(
        private readonly OpenRouterClient $client,
        private readonly ToolRegistry $tools,
    ) {
    }

    public function plan(StageContext $context): ?Plan
    {
        $available = $this->availableTools($context);

        if ($available === [] || ! $this->client->isConfigured()) {
            return null;
        }

        $response = $this->client->json(
            [
                ['role' => 'system', 'content' => $this->systemPrompt($available)],
                ['role' => 'user', 'content' => $this->userPrompt($context)],
            ],
            self::MODEL,
            maxTokens: 800,
        );

        if ($response === null) {
            return null;
        }

        return $this->validate($response, array_keys($available), $context);
    }

    // ---------------------------------------------------------------- internals

    /**
     * The tools this module may propose, with their descriptions and input schemas.
     *
     * Scoped to the module's own bindings rather than the whole registry: a fees
     * question has no business proposing an admissions confirmation, and narrowing the
     * list is both safer and a materially easier planning problem.
     *
     * The schema goes to the model because a plan has to be *executable*. A step naming
     * a tool without valid arguments is a suggestion, and the transport would have to
     * invent the call — so the model is given what each tool accepts and asked to fill
     * it in, and the tool's own validator remains the thing that decides whether it did.
     *
     * @return array<string, array{description:string, schema:array<string, mixed>}>
     */
    private function availableTools(StageContext $context): array
    {
        $permitted = $context->module->mcpTools;

        if ($permitted === []) {
            return [];
        }

        $available = [];

        foreach ($this->tools->definitions() as $definition) {
            $name = $definition['name'] ?? null;

            if (! is_string($name) || ! in_array($name, $permitted, true)) {
                continue;
            }

            // A consequential tool never enters the model's catalogue. The prompt also
            // forbids proposing one, but a prompt is a request and this is a guarantee:
            // a tool the planner cannot see is a tool it cannot name, however the
            // question is worded. Confirmable and write tools reach the user only
            // through the deterministic path and its human gate.
            $annotations = (array) ($definition['annotations'] ?? []);

            if (($annotations['requires_confirmation'] ?? false) || ($annotations['read_only'] ?? true) === false) {
                continue;
            }

            $available[$name] = [
                'description' => (string) ($definition['description'] ?? ''),
                'schema' => (array) ($definition['inputSchema'] ?? $definition['input_schema'] ?? []),
            ];
        }

        return $available;
    }

    /**
     * @param  array<string, array{description:string, schema:array<string, mixed>}>  $available
     */
    private function systemPrompt(array $available): string
    {
        $catalogue = implode("\n", array_map(
            static function (string $name, array $tool): string {
                $schema = $tool['schema'] === []
                    ? 'no arguments'
                    : json_encode($tool['schema'], JSON_UNESCAPED_SLASHES);

                return sprintf("- %s: %s\n  arguments: %s", $name, $tool['description'], $schema);
            },
            array_keys($available),
            $available
        ));

        return <<<PROMPT
        You plan how a school management system should answer a question. You do not answer it.

        Return JSON only, with this shape:
        {
          "goal": "what a complete answer requires, in one sentence",
          "steps": [{"id": "short_id", "tool": "exact tool name or null", "arguments": {}, "purpose": "one plain sentence", "depends_on": []}],
          "refuse_if": [{"when_unavailable": "the data that must exist", "reason": "what to tell the user when it does not"}]
        }

        The only tools that exist are:
        {$catalogue}

        Rules:
        - Between 1 and 6 steps. Never name a tool outside the list above.
        - `arguments` must match that tool's argument schema. Omit anything you cannot fill
          from the question itself — never invent an id, a name or a date.
        - A step that is reasoning rather than a lookup should use "tool": null.
        - `depends_on` may only reference the id of an earlier step.
        - Never plan to approve, reject, confirm, create, update or delete anything. This
          system routes consequential actions through a separate governed path, and a plan
          proposing one will be discarded.
        - Set `refuse_if` whenever the question asks about something a school system might
          not record at all. An honest refusal is a correct plan; a proxy metric is not.
        PROMPT;
    }

    private function userPrompt(StageContext $context): string
    {
        $module = $context->module;

        return sprintf(
            "Module: %s (%s)\nQuestion: %s",
            $module->label,
            $module->description !== '' ? $module->description : 'no description',
            $context->question
        );
    }

    /**
     * Reject a plan that could not be executed as written.
     *
     * Runs before any tool call, so an unusable plan costs one model call rather than a
     * sequence of half-completed backend requests.
     *
     * @param  array<string, mixed>  $response
     * @param  array<int, string>  $allowed
     */
    private function validate(array $response, array $allowed, StageContext $context): ?Plan
    {
        $goal = trim((string) ($response['goal'] ?? ''));
        $rawSteps = $response['steps'] ?? null;

        if ($goal === '' || ! is_array($rawSteps) || $rawSteps === []) {
            return null;
        }

        if (count($rawSteps) > self::MAX_STEPS) {
            return null;
        }

        $steps = [];
        $seen = [];

        foreach ($rawSteps as $raw) {
            if (! is_array($raw)) {
                return null;
            }

            $step = PlanStep::fromArray($raw);

            if ($step === null || isset($seen[$step->id])) {
                return null;
            }

            if ($step->tool !== null && ! in_array($step->tool, $allowed, true)) {
                return null;
            }

            // A dependency on a step that has not been declared yet cannot be satisfied
            // in execution order, so the plan is unrunnable as written.
            foreach ($step->dependsOn as $dependency) {
                if (! isset($seen[$dependency])) {
                    return null;
                }
            }

            $seen[$step->id] = true;
            $steps[] = $step;
        }

        $candidates = array_values(array_unique(array_filter(
            array_map(static fn (PlanStep $step) => $step->tool, $steps)
        )));

        return new Plan(
            goal: $goal,
            steps: $steps,
            source: Plan::SOURCE_LLM,
            route: $candidates === [] ? 'conversation' : 'mcp_tools',
            intentKey: null,
            candidateTools: $candidates,
            toolSelectionStrategy: $candidates === [] ? 'domain_services_only' : 'llm_selected_mcp_tools',
            refusals: $this->refusals($response),
            context: [
                'module' => $context->module->key,
                'model' => self::MODEL,
                'matched_by' => 'model plan, validated against the module tool bindings',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<int, array{when_unavailable:string, reason:string}>
     */
    private function refusals(array $response): array
    {
        $raw = $response['refuse_if'] ?? $response['refuseIf'] ?? [];
        $refusals = [];

        foreach ((array) $raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $when = trim((string) ($entry['when_unavailable'] ?? $entry['whenUnavailable'] ?? ''));
            $reason = trim((string) ($entry['reason'] ?? ''));

            if ($when !== '' && $reason !== '') {
                $refusals[] = ['when_unavailable' => $when, 'reason' => $reason];
            }
        }

        return $refusals;
    }
}
