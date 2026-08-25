<?php

namespace App\Http\Controllers\AI;

use App\Domain\AI\Agents\AgentRunner;
use App\Domain\AI\Workspace\AiContextService;
use App\Domain\AI\Workspace\CapabilityResolver;
use App\Domain\AI\Workspace\FlowStateResolver;
use App\Domain\AI\Workspace\OntologyViewResolver;
use App\Domain\AI\Workspace\PageDataResolver;
use App\Domain\GenerativeAI\GenerationRequest;
use App\Domain\GenerativeAI\GenerationService;
use App\Domain\Workflow\WorkflowEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The one endpoint the AI panel opens with, plus the actions its tabs dispatch.
 *
 * The panel is a single surface, so it gets a single call: `context` returns where the
 * user is, what the assistant can do there, and anything already in flight for the
 * record on screen. Everything else on this controller is an action one of the five
 * tabs performs.
 *
 * Nothing here bypasses the layers underneath. Running an agent goes through
 * AgentRunner (verb ceiling, tenant pinning, audit); starting a workflow goes through
 * WorkflowEngine (which re-checks the human decision before any consequential step).
 */
class WorkspaceController extends AiController
{
    public function __construct(
        private readonly AiContextService $contextService,
        private readonly CapabilityResolver $capabilities,
        private readonly FlowStateResolver $flow,
        private readonly OntologyViewResolver $ontologyViews,
        private readonly PageDataResolver $pageData,
        private readonly AgentRunner $agents,
        private readonly WorkflowEngine $workflows,
        private readonly GenerationService $generation,
    ) {
    }

    /**
     * Everything the panel needs to render itself for the current page.
     *
     * Reachable by GET (the shape §23 documents, and convenient to inspect in a
     * browser) and by POST, which is what the panel uses because `selected_records`
     * and `page_data` can be larger than a query string should carry. Both land here.
     */
    public function context(Request $request)
    {
        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'route' => 'required|string|max:500',
                'entity_type' => 'nullable|string|max:100',
                'entity_id' => 'nullable',
                'selected_records' => 'nullable|array|max:100',
                'page_data' => 'nullable|array',
            ]);

            $context = $this->contextService->resolve($validated['route'], $scope, $validated);
            $suggestions = $this->capabilities->resolve($context);

            return $this->success('AI workspace context resolved.', [
                'context' => $context->toArray(),
                'suggestions' => $suggestions,
                // Anything already running or waiting on this record, so the panel can
                // show progress instead of offering to start something twice.
                'active' => [
                    'workflow_runs' => $context->hasEntity() ? $this->entityWorkflowRuns($context, 5) : [],
                    'pending_recommendations' => $context->hasEntity()
                        ? $this->entityPendingRecommendations($context)
                        : [],
                ],
                'ontology_views' => $context->supports('ontology')
                    ? $this->ontologyViews->available($context)
                    : [],
                // Where this record sits in Signal → … → Outcome, derived from the
                // rows themselves. Drives the stage strip and the single next action.
                'flow' => $this->flow->resolve($context),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * The flow on its own, for refreshing the strip after an action without
     * re-resolving every suggestion.
     */
    public function flowState(Request $request)
    {
        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'route' => 'required|string|max:500',
                'entity_type' => 'nullable|string|max:100',
                'entity_id' => 'nullable',
            ]);

            $context = $this->contextService->resolve($validated['route'], $scope, $validated);

            return $this->success('Flow state resolved.', [
                'flow' => $this->flow->resolve($context),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Step-by-step status of the workflows attached to the record on screen.
     *
     * This is the "✓ Risk identified · ✓ Teacher approved · → Activity assigned"
     * view: every step of the pinned version, in order, marked with what has actually
     * happened. Steps that have not been reached yet are listed as pending rather than
     * omitted, because a progress view that only shows the past is not progress.
     */
    public function workflowStatus(Request $request)
    {
        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'route' => 'required|string|max:500',
                'entity_type' => 'nullable|string|max:100',
                'entity_id' => 'nullable',
                'run_id' => 'nullable|integer|min:1',
            ]);

            $context = $this->contextService->resolve($validated['route'], $scope, $validated);

            if (isset($validated['run_id'])) {
                $status = $this->workflows->status((int) $validated['run_id'], $scope);

                if (! $status) {
                    return $this->failure('No such workflow run.', 404);
                }

                return $this->success('Workflow run loaded.', [
                    'runs' => [$this->withPlannedSteps($status, $scope)],
                ]);
            }

            if (! $context->hasEntity()) {
                return $this->success('No record is selected.', ['runs' => []]);
            }

            $runs = [];

            foreach ($this->entityWorkflowRuns($context, 10) as $summary) {
                $status = $this->workflows->status((int) $summary['id'], $scope);

                if ($status) {
                    $runs[] = $this->withPlannedSteps($status, $scope);
                }
            }

            return $this->success('Workflow status loaded.', ['runs' => $runs]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Run an agent for the current context. The subject is taken from the resolved
     * context, not from the request, so the agent analyses the record the user is
     * actually looking at.
     */
    public function runAgent(Request $request, string $agent)
    {
        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'route' => 'required|string|max:500',
                'entity_type' => 'nullable|string|max:100',
                'entity_id' => 'nullable',
                'limit' => 'nullable|integer|min:1|max:200',
            ]);

            $context = $this->contextService->resolve($validated['route'], $scope, $validated);

            $input = array_filter([
                'subject_entity_key' => $context->entityKey,
                'subject_id' => $context->hasEntity() && is_numeric($context->entityId)
                    ? (int) $context->entityId
                    : null,
                'limit' => $validated['limit'] ?? null,
            ], fn ($value) => $value !== null);

            $result = $this->agents->run(
                $agent,
                $scope,
                $input,
                'conversation',
                'workspace:' . ($context->moduleKey ?? 'unknown')
            );

            if ($result['status'] === 'rejected') {
                return $this->failure($result['summary'], 403);
            }

            return $this->success($result['summary'], $result);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Generate content for the current context. Variables are filled from the
     * resolved entity so a teacher does not retype what is already on screen.
     */
    public function generate(Request $request)
    {
        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'route' => 'required|string|max:500',
                'template_key' => 'required|string|max:120',
                'entity_type' => 'nullable|string|max:100',
                'entity_id' => 'nullable',
                'variables' => 'nullable|array',
            ]);

            $context = $this->contextService->resolve($validated['route'], $scope, $validated);

            $variables = array_merge(
                array_filter([
                    'entity_label' => $context->entityLabel,
                    'module' => $context->moduleLabel,
                ], fn ($value) => $value !== null),
                $this->scalarAttributes($context->entityAttributes),
                // What is on the screen. Without this an analysis template has nothing
                // to analyse and the model would fall back on general knowledge, which
                // is exactly the failure the grounding rules exist to prevent.
                $this->pageVariables($context),
                $validated['variables'] ?? []
            );

            $result = $this->generation->generate(
                new GenerationRequest(
                    templateKey: $validated['template_key'],
                    purpose: 'workspace:' . ($context->moduleKey ?? 'unknown'),
                    variables: $variables,
                    domain: 'k12',
                    subjectEntityKey: $context->entityKey,
                    subjectId: $context->hasEntity() ? $context->entityId : null,
                    context: ['route' => $context->route, 'module' => $context->moduleKey],
                ),
                $scope
            );

            if (! $result->succeeded) {
                return $this->failure($result->error ?? 'Generation failed.', 422, $result->safetyReport);
            }

            if (! $result->isUsable()) {
                return $this->failure(
                    $result->safetyPassed
                        ? 'The generated content did not match the expected format.'
                        : 'The generated content did not pass safety checks.',
                    422,
                    ['schema_errors' => $result->schemaErrors, 'safety_report' => $result->safetyReport]
                );
            }

            return $this->success('Content generated.', $result->toArray());
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Walk a relationship view for the record on screen.
     */
    public function ontologyView(Request $request, string $view)
    {
        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'route' => 'required|string|max:500',
                'entity_type' => 'nullable|string|max:100',
                'entity_id' => 'nullable',
            ]);

            $context = $this->contextService->resolve($validated['route'], $scope, $validated);
            $result = $this->ontologyViews->resolve($view, $context);

            if (! $result['found']) {
                return $this->failure('That relationship view is not available here.', 404);
            }

            return $this->success('Relationships loaded.', $result);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Start a workflow from the workspace.
     *
     * Only permitted for definitions whose trigger is manual. A workflow triggered by
     * `recommendation_approved` must be reached by approving its recommendation, so
     * that starting it cannot skip the decision record it depends on.
     */
    public function startWorkflow(Request $request, string $workflow)
    {
        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'route' => 'required|string|max:500',
                'entity_type' => 'nullable|string|max:100',
                'entity_id' => 'nullable',
                'input' => 'nullable|array',
            ]);

            $context = $this->contextService->resolve($validated['route'], $scope, $validated);

            $definition = Schema::hasTable('workflow_definitions')
                ? DB::table('workflow_definitions')
                    ->where('workflow_key', $workflow)
                    ->where('status', 1)
                    ->first()
                : null;

            if (! $definition) {
                return $this->failure('No such workflow.', 404);
            }

            if ($definition->trigger_type === 'recommendation_approved') {
                return $this->failure(
                    'This process starts when its recommendation is approved, not from here.',
                    422
                );
            }

            $input = array_merge($validated['input'] ?? [], array_filter([
                'entity_type' => $context->entityKey,
                'entity_id' => $context->entityId,
                'entity_label' => $context->entityLabel,
            ], fn ($value) => $value !== null));

            $result = $this->workflows->start($workflow, $scope, $input, array_filter([
                'trigger_type' => 'manual',
                'subject_entity_key' => $context->entityKey,
                'subject_id' => $context->hasEntity() && is_numeric($context->entityId)
                    ? (int) $context->entityId
                    : null,
            ], fn ($value) => $value !== null));

            if ($result['status'] === 'rejected') {
                return $this->failure($result['message'], 403);
            }

            return $this->success($result['message'], $result);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    // ---------------------------------------------------------------- internals

    /**
     * Merge the run's recorded steps with the version's planned steps, so the panel
     * can render the whole journey rather than only the part already executed.
     */
    private function withPlannedSteps(array $status, $scope): array
    {
        $run = Schema::hasTable('workflow_runs')
            ? DB::table('workflow_runs')->where('id', $status['id'])->first()
            : null;

        $planned = [];

        if ($run && Schema::hasTable('workflow_versions')) {
            $version = DB::table('workflow_versions')->where('id', $run->version_id)->first();
            $planned = $version ? $this->decode($version->steps) : [];
        }

        $executed = collect($status['steps'] ?? [])->keyBy('step_key');
        $steps = [];
        $reachedCurrent = false;

        foreach ($planned as $index => $step) {
            if (! is_array($step) || empty($step['key'])) {
                continue;
            }

            $key = (string) $step['key'];
            $record = $executed->get($key);
            $isCurrent = ($status['current_step_key'] ?? null) === $key;

            if ($isCurrent) {
                $reachedCurrent = true;
            }

            $steps[] = [
                'key' => $key,
                'label' => $step['label'] ?? $this->humanize($key),
                'type' => $step['type'] ?? null,
                'sequence' => $index,
                // A step with no record that sits after the current one has not run yet.
                'status' => $record['status'] ?? ($isCurrent ? 'current' : ($reachedCurrent ? 'pending' : 'pending')),
                'is_current' => $isCurrent,
                'started_at' => $record['started_at'] ?? null,
                'finished_at' => $record['finished_at'] ?? null,
                'error_message' => $record['error_message'] ?? null,
                'output' => $record['output'] ?? null,
                'requires_approval' => ($step['type'] ?? null) === 'approval',
            ];
        }

        // Anything executed that the version no longer declares (an edited definition
        // after this run started) is appended rather than dropped.
        foreach ($executed as $key => $record) {
            if (! collect($steps)->firstWhere('key', $key)) {
                $steps[] = [
                    'key' => $key,
                    'label' => $record['label'] ?? $this->humanize((string) $key),
                    'type' => $record['step_type'] ?? null,
                    'sequence' => count($steps),
                    'status' => $record['status'],
                    'is_current' => false,
                    'started_at' => $record['started_at'] ?? null,
                    'finished_at' => $record['finished_at'] ?? null,
                    'error_message' => $record['error_message'] ?? null,
                    'output' => $record['output'] ?? null,
                    'requires_approval' => false,
                ];
            }
        }

        $status['planned_steps'] = $steps;
        $status['progress'] = [
            'total' => count($steps),
            'completed' => count(array_filter($steps, fn ($step) => $step['status'] === 'completed')),
        ];

        return $status;
    }

    /**
     * Workflow runs attached to the record on screen.
     */
    private function entityWorkflowRuns($context, int $limit): array
    {
        if (! Schema::hasTable('workflow_runs') || ! $context->hasEntity()) {
            return [];
        }

        return DB::table('workflow_runs')
            ->where('sub_institute_id', $context->scope->selectedInstituteId)
            ->where('subject_entity_key', $context->entityKey)
            ->where('subject_id', $context->entityId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'reference' => $row->run_reference,
                'workflow_key' => $row->workflow_key,
                'status' => $row->status,
                'current_step_key' => $row->current_step_key,
                'started_at' => $row->started_at,
                'finished_at' => $row->finished_at,
            ])
            ->all();
    }

    /**
     * Recommendations waiting on a decision for this record — the "Approval required"
     * state the panel surfaces before anything can proceed.
     */
    private function entityPendingRecommendations($context): array
    {
        if (! Schema::hasTable('ai_recommendations') || ! $context->hasEntity()) {
            return [];
        }

        return DB::table('ai_recommendations')
            ->where('sub_institute_id', $context->scope->selectedInstituteId)
            ->where('subject_entity_key', $context->entityKey)
            ->where('subject_id', $context->entityId)
            ->where('status', 'pending_approval')
            ->where('governance_passed', true)
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'reference' => $row->recommendation_reference,
                'title' => $row->title,
                'action_type' => $row->action_type,
                'risk_level' => $row->risk_level,
                'confidence' => $row->confidence === null ? null : (float) $row->confidence,
                'requires_approval' => true,
                'case_id' => $row->case_id ? (int) $row->case_id : null,
            ])
            ->all();
    }

    /**
     * The page snapshot, flattened into template variables.
     *
     * This is what lets one shared template — `k12.analyse.list` — produce an analysis
     * of fee defaulters on one page and library loans on another. The template never
     * names a module; it renders whatever the page reported.
     *
     * Rows are rendered as a compact list rather than JSON: the model reads them
     * better, and it keeps a serialised object from ever reaching a prompt that might
     * echo it back to the user.
     */
    private function pageVariables($context): array
    {
        $page = $context->page;

        // Where the page reported nothing, ask the module. A page that stays silent is
        // not evidence of an empty module, and treating it as such is what produced
        // "this catalogue currently shows no courses" for a school with 208 of them.
        $fallback = $this->pageData->resolve($context);

        $records = $page->records !== [] ? $page->records : $fallback['records'];
        $metrics = $page->metrics !== [] ? $page->metrics : $fallback['metrics'];
        $recordCount = $page->hasRecords() ? $page->recordCount : $fallback['record_count'];

        $filters = $page->describeFilters();

        if ($filters === null && $fallback['filters'] !== []) {
            $filters = implode(', ', array_map(
                fn (array $filter) => $filter['label'] . ': ' . $filter['value'],
                $fallback['filters']
            ));
        }

        $variables = [
            'page_title' => $page->title ?? $context->moduleLabel ?? 'this page',
            'page_type' => $context->pageType,
            'record_count' => (string) $recordCount,
            'filters' => $filters ?? 'none',
            'search_query' => $page->searchQuery ?? '',
            // So a template — and anyone reading the stored request — can tell whether
            // the rows came from the screen or from the module behind it.
            'data_source' => $page->records !== []
                ? 'the page'
                : ($fallback['resolved'] ? ($fallback['source'] ?? 'the module') : 'none'),
        ];

        $variables['metrics'] = $metrics === []
            ? 'none reported'
            : implode('; ', array_map(
                fn (array $metric) => $metric['label'] . ': ' . $metric['value']
                    . ($metric['unit'] ? ' ' . $metric['unit'] : '')
                    . ($metric['trend'] ? ' (' . $metric['trend'] . ')' : ''),
                $metrics
            ));

        $variables['records'] = $records === []
            ? 'none listed'
            : implode("\n", array_map(function (array $record) {
                $attributes = implode(', ', array_map(
                    fn ($key, $value) => "{$key}: {$value}",
                    array_keys($record['attributes']),
                    $record['attributes']
                ));

                $label = $record['label'] ?? ('#' . ($record['id'] ?? '?'));

                return '- ' . $label . ($attributes !== '' ? " ({$attributes})" : '');
            }, $records));

        // Stated explicitly so the template can tell the model when it is looking at a
        // window rather than the whole set.
        $variables['is_partial'] = $recordCount > count($records) ? 'yes' : 'no';
        $variables['rows_shown'] = (string) count($records);

        return $variables;
    }

    private function scalarAttributes(array $attributes): array
    {
        $scalars = [];

        foreach ($attributes as $key => $value) {
            if (is_scalar($value)) {
                $scalars[$key] = $value;
            }
        }

        return $scalars;
    }

    private function humanize(string $value): string
    {
        return ucfirst(str_replace('_', ' ', $value));
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
