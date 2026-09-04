<?php

namespace App\Domain\AI\Conversation;

/**
 * Projects the backend's detailed trace onto the product's 12-stage lifecycle.
 *
 * The existing conversation trace is intentionally fine-grained: it shows ontology,
 * case, template, workflow, outcome and learning as separate stages because those are
 * useful backend diagnostics. The product lifecycle the team is validating is
 * different: it asks whether one turn covered Conversational AI -> Action, including
 * Planning, MCP Tool Selection and Laravel MCP. This projector keeps the detailed
 * trace intact and adds that second, product-facing view without breaking callers that
 * already depend on the 15-stage backend ladder.
 *
 * Two backend stages are folded rather than dropped: `template` joins `gen_ai` under
 * Generative AI, and `ontology`/`case`/`explanation` join under Reasoning. `outcome`
 * and `learning` have no place in this view because the product lifecycle ends at
 * Action; they remain in the backend ladder, which is where they are read.
 */
final class LifecycleTraceProjector
{
    /**
     * @param  array<int, array<string, mixed>>  $trace
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    public function project(array $trace, array $context = []): array
    {
        $byKey = $this->byKey($trace);
        $genAiData = is_array($byKey['gen_ai']['data'] ?? null) ? $byKey['gen_ai']['data'] : [];

        $plan = is_array($context['plan'] ?? null)
            ? $context['plan']
            : (is_array($genAiData['lifecycle_plan'] ?? null) ? $genAiData['lifecycle_plan'] : []);

        // A plan naming a tool and a turn calling one are two different facts, and the
        // trace has to keep them apart. Reporting the plan's candidates as "selected"
        // produced turns that claimed a tool was selected while the very next stage
        // reported that nothing was called — a contradiction a reader cannot resolve.
        // `selected_tools` is read as a fallback for turns stored before the plan's field
        // was renamed to `candidate_tools`; both mean the same thing here, which is what
        // the plan proposed rather than what the turn went on to call.
        $plannedTools = $this->stringList(
            $context['candidate_tools']
                ?? $context['selected_tools']
                ?? $genAiData['lifecycle_selected_tools']
                ?? $plan['candidate_tools']
                ?? $plan['selected_tools']
                ?? []
        );
        $mcpCalls = is_array($context['laravel_mcp_calls'] ?? null)
            ? $context['laravel_mcp_calls']
            : (is_array($genAiData['lifecycle_mcp_calls'] ?? null) ? $genAiData['lifecycle_mcp_calls'] : []);
        $mcpCalls = array_values(array_filter($mcpCalls, 'is_array'));

        $executedTools = array_values(array_unique(array_filter(array_map(
            static fn (array $call) => is_string($call['tool'] ?? null) ? $call['tool'] : null,
            $mcpCalls
        ))));

        return [
            $this->fromSource(
                1,
                'conversation',
                'Conversational AI',
                'App\\Domain\\AI\\Conversation\\ConversationStore',
                'AI Journey console — the question thread',
                $byKey['conversation'] ?? null
            ),
            $this->generativeAiStage($byKey),
            $this->fromSource(
                3,
                'agent',
                'Agent',
                'App\\Domain\\AI\\Agents\\AgentRunner -> AcademicRiskAgent',
                'AI Administration → Agent runs',
                $byKey['agent'] ?? null
            ),
            $this->planningStage($plan, $byKey),
            $this->mcpSelectionStage($plannedTools, $executedTools, $plan, $byKey),
            $this->laravelMcpStage($plannedTools, $executedTools, $mcpCalls, $plan, $byKey),
            $this->fromSource(
                7,
                'real_data',
                'Real Data',
                'AttendanceRiskDetector, AssessmentDeclineDetector, MissedAssignmentDetector',
                'The source attendance, assessment and assignment records',
                $byKey['data'] ?? null
            ),
            $this->fromSource(
                8,
                'evidence',
                'Evidence',
                'App\\Domain\\AI\\Evidence\\EvidenceStore',
                'Case → Evidence',
                $byKey['evidence'] ?? null
            ),
            $this->reasoningStage($byKey),
            $this->fromSource(
                10,
                'recommendation',
                'Recommendation',
                'App\\Domain\\AI\\Recommendations\\RecommendationDrafter',
                'Approvals inbox — drafted action',
                $byKey['recommendation'] ?? null
            ),
            $this->fromSource(
                11,
                'human_approval',
                'Human Approval',
                'App\\Domain\\AI\\Decisions\\DecisionGate',
                'Approve / Reject actions',
                $byKey['approval'] ?? null
            ),
            $this->actionStage($byKey),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $trace
     * @return array<string, array<string, mixed>>
     */
    private function byKey(array $trace): array
    {
        $keyed = [];

        foreach ($trace as $stage) {
            if (is_array($stage) && isset($stage['key'])) {
                $keyed[(string) $stage['key']] = $stage;
            }
        }

        return $keyed;
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($item) => is_string($item) ? trim($item) : '',
            $value
        ), 'strlen'));
    }

    /**
     * @param  array<string, mixed>|null  $source
     */
    private function fromSource(
        int $order,
        string $key,
        string $layer,
        string $component,
        string $surface,
        ?array $source,
        ?string $summary = null,
        ?callable $summaryResolver = null
    ): array {
        $status = $source['status'] ?? TraceStage::NOT_REACHED;
        $resolvedSummary = $summary;

        if ($resolvedSummary === null && $summaryResolver !== null && $source !== null) {
            $resolvedSummary = (string) $summaryResolver($source);
        }

        if ($resolvedSummary === null) {
            $resolvedSummary = (string) ($source['summary'] ?? '');
        }

        return [
            'key' => $key,
            'order' => $order,
            'layer' => $layer,
            'status' => $status,
            'summary' => $resolvedSummary,
            'component' => $component,
            'surface' => $surface,
            'data' => $source['data'] ?? [],
            'records' => $source['records'] ?? [],
            'verify' => $source['verify'] ?? [],
            'duration_ms' => $source['duration_ms'] ?? null,
            'note' => $source['note'] ?? null,
        ];
    }

    /**
     * Stage 2 covers both halves of "Generative AI".
     *
     * Understanding is the half that always runs: IntentClassifier turns a sentence into
     * one governed intent. Generation is the half that usually has not run yet, because
     * intervention text is rendered inside the workflow at generate_activity — after a
     * human approves, never before. Showing only the classifier left stage 2 as the one
     * label in the ladder with nothing generative behind it; showing both, together with
     * the template stage's own reason for not having run, is the honest version.
     *
     * @param  array<string, array<string, mixed>>  $byKey
     */
    private function generativeAiStage(array $byKey): array
    {
        $genAi = $byKey['gen_ai'] ?? null;
        $template = $byKey['template'] ?? null;

        $status = $genAi['status'] ?? TraceStage::NOT_REACHED;
        $templateStatus = $template['status'] ?? TraceStage::NOT_REACHED;

        $understanding = trim((string) ($genAi['summary'] ?? ''));

        if ($understanding === '' && $status !== TraceStage::NOT_REACHED) {
            $understanding = 'The question was interpreted into one governed intent.';
        }

        $generation = $templateStatus === TraceStage::RAN
            ? trim((string) ($template['summary'] ?? 'Governed text was rendered from a registered template.'))
            : '';

        $summary = trim($understanding . ($generation !== '' ? ' ' . $generation : ''));

        $note = null;

        if ($generation === '' && $status !== TraceStage::NOT_REACHED) {
            $note = trim((string) ($template['note'] ?? ''))
                ?: 'No text was generated on this turn; generation happens inside the workflow, after approval.';
        }

        return [
            'key' => 'generative_ai',
            'order' => 2,
            'layer' => 'Generative AI',
            'status' => $status,
            'summary' => $summary,
            'component' => 'App\\Domain\\AI\\Conversation\\IntentClassifier + App\\Domain\\Templates\\TemplateRegistry -> GenerativeAI\\GenerationService',
            'surface' => 'AI Journey console — the understood intent, and any generated intervention text',
            'data' => [
                'understanding' => $genAi['data'] ?? [],
                'generation' => [
                    'status' => $templateStatus,
                    'summary' => $template['summary'] ?? null,
                    'note' => $template['note'] ?? null,
                    'payload' => $template['data'] ?? [],
                ],
            ],
            'records' => $this->firstRecords([$template, $genAi]),
            'verify' => $this->firstVerify([$template, $genAi]),
            'duration_ms' => $genAi['duration_ms'] ?? null,
            'note' => $note,
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, array<string, mixed>>  $byKey
     */
    private function planningStage(array $plan, array $byKey): array
    {
        $genAi = $byKey['gen_ai'] ?? null;

        if ($plan !== []) {
            $count = count($plan['steps'] ?? []);
            $summary = $count > 0
                ? sprintf('A deterministic execution plan was prepared with %d step%s.', $count, $count === 1 ? '' : 's')
                : 'A deterministic execution plan was prepared from the resolved intent.';

            return [
                'key' => 'planning',
                'order' => 4,
                'layer' => 'Planning',
                'status' => TraceStage::RAN,
                'summary' => $summary,
                'component' => 'App\\Domain\\AI\\Conversation\\AskService::buildLifecyclePlan',
                'surface' => 'AI Journey console — lifecycle trace',
                'data' => $plan,
                'records' => [],
                'verify' => [],
                'duration_ms' => null,
                'note' => null,
            ];
        }

        if (($genAi['status'] ?? TraceStage::NOT_REACHED) === TraceStage::RAN) {
            return [
                'key' => 'planning',
                'order' => 4,
                'layer' => 'Planning',
                'status' => TraceStage::SKIPPED,
                'summary' => 'No explicit execution plan was recorded for this turn.',
                'component' => 'App\\Domain\\AI\\Conversation\\AskService::buildLifecyclePlan',
                'surface' => 'AI Journey console — lifecycle trace',
                'data' => [],
                'records' => [],
                'verify' => [],
                'duration_ms' => null,
                'note' => 'The turn was still routed deterministically; this trace simply had no named planning payload.',
            ];
        }

        return [
            'key' => 'planning',
            'order' => 4,
            'layer' => 'Planning',
            'status' => TraceStage::NOT_REACHED,
            'summary' => '',
            'component' => 'App\\Domain\\AI\\Conversation\\AskService::buildLifecyclePlan',
            'surface' => 'AI Journey console — lifecycle trace',
            'data' => [],
            'records' => [],
            'verify' => [],
            'duration_ms' => null,
            'note' => 'The question was not understood well enough to plan a route.',
        ];
    }

    /**
     * Stage 5 reports what the turn actually chose to call.
     *
     * `$plannedTools` is what the plan named as a candidate; `$executedTools` is what the
     * turn genuinely invoked, refusals included. Only the second is a selection. A plan
     * can name students.search and then take a route that never needs it, and saying so
     * plainly is better than claiming a selection that the next stage contradicts.
     *
     * @param  array<int, string>  $plannedTools
     * @param  array<int, string>  $executedTools
     * @param  array<string, mixed>  $plan
     * @param  array<string, array<string, mixed>>  $byKey
     */
    private function mcpSelectionStage(array $plannedTools, array $executedTools, array $plan, array $byKey): array
    {
        $base = [
            'key' => 'mcp_tool_selection',
            'order' => 5,
            'layer' => 'MCP Tool Selection',
            'component' => 'App\\Domain\\AI\\Conversation\\AskService::buildLifecyclePlan',
            'surface' => 'AI Journey console — lifecycle trace',
            'records' => [],
            'verify' => [],
            'duration_ms' => null,
        ];

        if ($executedTools !== []) {
            return $base + [
                'status' => TraceStage::RAN,
                'summary' => sprintf(
                    'The turn selected %d MCP tool%s for execution.',
                    count($executedTools),
                    count($executedTools) === 1 ? '' : 's'
                ),
                'data' => [
                    'selected_tools' => $executedTools,
                    'planned_tools' => $plannedTools,
                    'selection_strategy' => $plan['tool_selection_strategy'] ?? null,
                ],
                'note' => null,
            ];
        }

        $genAiRan = ($byKey['gen_ai']['status'] ?? TraceStage::NOT_REACHED) === TraceStage::RAN;

        if ($plannedTools !== [] && $genAiRan) {
            return $base + [
                'status' => TraceStage::SKIPPED,
                'summary' => sprintf(
                    'The plan named %s as a candidate, but this route resolved without calling it.',
                    implode(', ', $plannedTools)
                ),
                'data' => [
                    'selected_tools' => [],
                    'planned_tools' => $plannedTools,
                    'selection_strategy' => $plan['tool_selection_strategy'] ?? null,
                ],
                'note' => 'A candidate is not a selection. Nothing was invoked, so nothing was selected.',
            ];
        }

        if ($genAiRan) {
            return $base + [
                'status' => TraceStage::SKIPPED,
                'summary' => 'No MCP tool was selected; this turn stayed on the scoped domain-service path.',
                'data' => [
                    'selected_tools' => [],
                    'planned_tools' => [],
                    'selection_strategy' => $plan['tool_selection_strategy'] ?? 'domain_services_only',
                ],
                'note' => 'This path answers from governed agents, cases and workflow services rather than MCP tools.',
            ];
        }

        return $base + [
            'status' => TraceStage::NOT_REACHED,
            'summary' => '',
            'data' => [],
            'note' => 'No execution route was chosen, so there was no tool selection to perform.',
        ];
    }

    /**
     * Stage 6 is the transport, and it has to be able to say "I was refused".
     *
     * students.search carries its own role gate. When it refuses, the conversation path
     * falls back to the scoped resolver so the answer survives, but the lifecycle trace
     * must still show that Laravel MCP was reached and turned the call down. Reporting
     * that as "no call was needed" would hide a governance decision behind a shrug.
     *
     * @param  array<int, string>  $plannedTools
     * @param  array<int, string>  $executedTools
     * @param  array<int, array<string, mixed>>  $mcpCalls
     * @param  array<string, mixed>  $plan
     * @param  array<string, array<string, mixed>>  $byKey
     */
    private function laravelMcpStage(
        array $plannedTools,
        array $executedTools,
        array $mcpCalls,
        array $plan,
        array $byKey
    ): array {
        $base = [
            'key' => 'laravel_mcp',
            'order' => 6,
            'layer' => 'Laravel MCP',
            'component' => 'App\\Mcp\\ToolRegistry -> App\\Http\\Controllers\\Mcp\\ToolsCallController',
            'surface' => 'MCP tool audit and resulting data-backed answer',
            'records' => [],
            'verify' => ['api' => 'POST /api/mcp/tools/call'],
            'duration_ms' => null,
        ];

        if ($mcpCalls !== []) {
            $blocked = array_values(array_filter(
                $mcpCalls,
                static fn (array $call) => ($call['status'] ?? null) === 'blocked'
            ));
            $completed = count($mcpCalls) - count($blocked);

            $data = [
                'calls' => $mcpCalls,
                'tools' => $executedTools,
                'completed' => $completed,
                'blocked' => count($blocked),
            ];

            if ($completed === 0) {
                return $base + [
                    'status' => TraceStage::BLOCKED,
                    'summary' => sprintf(
                        'Laravel MCP refused %d tool call%s.',
                        count($blocked),
                        count($blocked) === 1 ? '' : 's'
                    ),
                    'data' => $data,
                    'note' => trim((string) ($blocked[0]['error'] ?? ''))
                        ?: "The caller is outside the tool's allowed roles.",
                ];
            }

            return $base + [
                'status' => TraceStage::RAN,
                'summary' => sprintf(
                    'Laravel MCP executed %d tool call%s.',
                    $completed,
                    $completed === 1 ? '' : 's'
                ),
                'data' => $data,
                'note' => $blocked === []
                    ? null
                    : sprintf(
                        "%d further call%s refused by the tool's role gate.",
                        count($blocked),
                        count($blocked) === 1 ? ' was' : 's were'
                    ),
            ];
        }

        if ($plannedTools !== [] || ($byKey['gen_ai']['status'] ?? TraceStage::NOT_REACHED) === TraceStage::RAN) {
            return $base + [
                'status' => TraceStage::SKIPPED,
                'summary' => 'No Laravel MCP call was needed for this turn.',
                'data' => [
                    'planned_tools' => $plannedTools,
                    'strategy' => $plan['mcp_strategy'] ?? 'not_required',
                ],
                'note' => 'This turn remained on the conversation/agent path, so the MCP transport was available but not invoked.',
            ];
        }

        return $base + [
            'status' => TraceStage::NOT_REACHED,
            'summary' => '',
            'data' => [],
            'note' => 'No selected tool reached execution.',
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $byKey
     */
    private function reasoningStage(array $byKey): array
    {
        $sources = array_values(array_filter([
            $byKey['ontology'] ?? null,
            $byKey['case'] ?? null,
            $byKey['explanation'] ?? null,
        ]));

        $status = $this->combinedStatus($sources);
        $summary = $this->combinedSummary($sources, $status, 'No reasoning stage was reached on this turn.');

        return [
            'key' => 'reasoning',
            'order' => 9,
            'layer' => 'Reasoning',
            'status' => $status,
            'summary' => $summary,
            'component' => 'EntityResolver + CaseBuilder + ExplanationBuilder + GovernanceValidator',
            'surface' => 'Case explanation, reasoning and cited claims',
            'data' => [
                'sources' => array_map(static fn (array $stage) => [
                    'key' => $stage['key'] ?? null,
                    'status' => $stage['status'] ?? null,
                    'summary' => $stage['summary'] ?? null,
                ], $sources),
            ],
            'records' => $this->firstRecords($sources),
            'verify' => $this->firstVerify($sources),
            'duration_ms' => null,
            'note' => $status === TraceStage::NOT_REACHED
                ? 'Nothing had reached the case/explanation path yet.'
                : null,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $byKey
     */
    private function actionStage(array $byKey): array
    {
        $workflow = $byKey['workflow'] ?? null;
        $action = $byKey['action'] ?? null;
        $sources = array_values(array_filter([$workflow, $action]));

        $status = $this->combinedStatus($sources);
        $summary = '';
        $note = null;

        if (($action['status'] ?? TraceStage::NOT_REACHED) === TraceStage::RAN) {
            $summary = (string) ($action['summary'] ?? 'The downstream action changed a real record.');
        } elseif (($workflow['status'] ?? TraceStage::NOT_REACHED) === TraceStage::PENDING) {
            $status = TraceStage::PENDING;
            $summary = (string) ($workflow['summary'] ?? 'A workflow is waiting before any record can change.');
        } elseif (($workflow['status'] ?? TraceStage::NOT_REACHED) === TraceStage::RAN) {
            // The workflow moving is not the action happening. Approving a recommendation
            // starts the run and parks it at its own confirmation step — no record has
            // changed yet — and reporting that as a completed Action told the reader the
            // intervention existed when it did not. The first branch above already caught
            // the case where the action genuinely ran, so reaching here means it did not:
            // this stage is waiting, and waiting is what it should say.
            $status = TraceStage::PENDING;
            $summary = (string) ($workflow['summary'] ?? 'The workflow advanced toward a real action.');
            $note = 'The workflow ran, but no record has changed yet — this stage completes when the '
                . 'workflow reaches the step that writes one.';
        } elseif ($summary === '') {
            $summary = $this->combinedSummary($sources, $status, 'No action stage was reached on this turn.');
        }

        // Stage 12 is the one a reader checks first, and it was the one stage that could
        // render completely blank: on an ordinary scan turn both sources are not_reached,
        // so the summary is empty and nothing above sets a note. Meanwhile the backend
        // ladder holds a perfectly good reason on those very stages — "Waiting on the
        // human decision above. This is the gate, not a gap." Dropping it turned the
        // deliberate design of the whole pipeline into an apparently dead end.
        if ($note === null) {
            $note = $this->firstNote([$action, $workflow]);
        }

        return [
            'key' => 'action',
            'order' => 12,
            'layer' => 'Action',
            'status' => $status,
            'summary' => $summary,
            'component' => 'WorkflowEngine -> CreateAcademicInterventionAction',
            'surface' => 'Student profile → Interventions / other changed records',
            'data' => [
                'workflow' => $workflow ? [
                    'status' => $workflow['status'] ?? null,
                    'summary' => $workflow['summary'] ?? null,
                ] : null,
                'action' => $action ? [
                    'status' => $action['status'] ?? null,
                    'summary' => $action['summary'] ?? null,
                ] : null,
            ],
            'records' => $this->firstRecords([$action, $workflow]),
            'verify' => $this->firstVerify([$action, $workflow]),
            'duration_ms' => null,
            'note' => $note,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>|null>  $stages
     */
    private function combinedStatus(array $stages): string
    {
        $statuses = array_values(array_filter(array_map(
            static fn ($stage) => is_array($stage) ? ($stage['status'] ?? null) : null,
            $stages
        )));

        foreach ([TraceStage::BLOCKED, TraceStage::PENDING, TraceStage::RAN, TraceStage::SKIPPED] as $status) {
            if (in_array($status, $statuses, true)) {
                return $status;
            }
        }

        return TraceStage::NOT_REACHED;
    }

    /**
     * @param  array<int, array<string, mixed>|null>  $stages
     */
    private function combinedSummary(array $stages, string $status, string $fallback): string
    {
        $parts = [];

        foreach ($stages as $stage) {
            if (! is_array($stage)) {
                continue;
            }

            $text = trim((string) ($stage['summary'] ?? ''));

            if ($text !== '') {
                $parts[] = $text;
            }
        }

        // Folded stages often share one reason — three backend stages skipped by the same
        // route all report the same sentence — and printing it three times reads as
        // padding rather than as the single fact it is.
        $parts = array_values(array_unique($parts));

        if ($parts !== []) {
            return implode(' ', array_slice($parts, 0, 3));
        }

        if ($status === TraceStage::NOT_REACHED) {
            return '';
        }

        return $fallback;
    }

    /**
     * The first reason any of these stages gave for its own status.
     *
     * @param  array<int, array<string, mixed>|null>  $stages
     */
    private function firstNote(array $stages): ?string
    {
        foreach ($stages as $stage) {
            if (! is_array($stage)) {
                continue;
            }

            $note = trim((string) ($stage['note'] ?? ''));

            if ($note !== '') {
                return $note;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>|null>  $stages
     * @return array<string, mixed>
     */
    private function firstRecords(array $stages): array
    {
        foreach ($stages as $stage) {
            if (is_array($stage) && is_array($stage['records'] ?? null) && $stage['records'] !== []) {
                return $stage['records'];
            }
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>|null>  $stages
     * @return array<string, mixed>
     */
    private function firstVerify(array $stages): array
    {
        foreach ($stages as $stage) {
            if (is_array($stage) && is_array($stage['verify'] ?? null) && $stage['verify'] !== []) {
                return $stage['verify'];
            }
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $trace
     * @return array<string, int>
     */
    public function summaryCounts(array $trace): array
    {
        $counts = [];

        foreach ($trace as $stage) {
            $status = $stage['status'] ?? TraceStage::NOT_REACHED;
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        return $counts;
    }
}
