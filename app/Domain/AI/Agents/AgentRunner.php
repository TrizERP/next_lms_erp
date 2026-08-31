<?php

namespace App\Domain\AI\Agents;

use App\Domain\AI\Cases\CaseBuilder;
use App\Domain\AI\Evidence\EvidenceStore;
use App\Domain\AI\Explanations\ExplanationBuilder;
use App\Domain\AI\Recommendations\RecommendationDrafter;
use App\Domain\AI\Signals\SignalStore;
use App\Domain\AI\Support\AiAuditLogger;
use App\Domain\Governance\GovernanceValidator;
use App\Domain\KnowledgeGraph\GraphQueryService;
use App\Domain\Ontology\EntityResolver;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Runs agents, and is the only thing that does.
 *
 * Every rule that must hold for *all* agents lives here rather than in the agents:
 * the role check, the tenant pinning, the verb ceiling, the run record, the audit
 * trail, and the failure handling. A new agent written next year inherits all of it
 * without knowing it exists, which is the point — an agent cannot forget a rule it
 * was never asked to implement.
 *
 * The scope is pinned once, at the start, from the McpRequestContext the caller was
 * already authenticated under. It is never re-derived mid-run and never taken from
 * agent input, so an agent cannot ask to be run against another school.
 */
class AgentRunner
{
    public function __construct(
        private readonly AgentRegistry $registry,
        private readonly Container $container,
        private readonly GovernanceValidator $governance,
        private readonly AiAuditLogger $audit,
        private readonly EntityResolver $entities,
        private readonly GraphQueryService $graph,
        private readonly SignalStore $signals,
        private readonly EvidenceStore $evidence,
        private readonly CaseBuilder $cases,
        private readonly ExplanationBuilder $explanations,
        private readonly RecommendationDrafter $recommendations,
    ) {
    }

    /**
     * Run an agent by key.
     *
     * @return array{
     *   run_id:int|null, status:string, summary:string, result:array,
     *   counters:array, error:string|null
     * }
     */
    public function run(
        string $agentKey,
        McpRequestContext $scope,
        array $input = [],
        string $triggerType = 'manual',
        ?string $triggerReference = null
    ): array {
        $manifest = $this->registry->find($agentKey, $scope->selectedInstituteId);

        if (! $manifest) {
            return $this->refuse($agentKey, $scope, 'No such agent, or it is disabled.', $input, $triggerType);
        }

        // Role gate, before anything is written.
        $roleReport = $this->governance->authorizeRole($scope, $manifest->allowedRoles);

        if (! $roleReport->passed) {
            $this->audit->recordRejection(
                $roleReport->reason() ?? 'Role not permitted.',
                $scope,
                [
                    'actor_label' => $manifest->name,
                    'related_type' => 'ai_agents',
                    'related_id' => $manifest->id,
                    'payload' => ['agent_key' => $agentKey, 'role' => $scope->role],
                ]
            );

            return $this->refuse($agentKey, $scope, $roleReport->reason() ?? 'Not permitted.', $input, $triggerType);
        }

        $runId = $this->openRun($manifest, $scope, $input, $triggerType, $triggerReference);

        $agent = $this->instantiate($manifest);

        if (! $agent instanceof Agent) {
            $this->closeRun($runId, 'failed', [], [], null, 'Agent class does not implement the Agent contract.');

            return $this->refuse(
                $agentKey,
                $scope,
                'This agent is misconfigured and cannot run.',
                $input,
                $triggerType,
                $runId
            );
        }

        $context = new AgentContext(
            manifest: $manifest,
            scope: $scope,
            runId: $runId ?? 0,
            input: $input,
            entities: $this->entities,
            graph: $this->graph,
            signals: $this->signals,
            evidence: $this->evidence,
            cases: $this->cases,
            explanations: $this->explanations,
            recommendations: $this->recommendations,
        );

        $this->audit->record(AiAuditLogger::AGENT_RUN_STARTED, $scope, [
            'actor_type' => 'agent',
            'actor_label' => $manifest->name,
            'related_type' => 'ai_agent_runs',
            'related_id' => $runId,
            'message' => sprintf('Agent %s started.', $manifest->key),
            'payload' => ['trigger' => $triggerType, 'input' => $input],
        ]);

        $startedAt = microtime(true);

        try {
            $result = $agent->run($context);
            $summary = $agent->summarize($result);
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            // A run that overruns its declared budget is recorded as such rather than
            // silently accepted — the timeout is a statement about expected cost.
            $status = $durationMs > ($manifest->timeoutSeconds * 1000) ? 'timed_out' : 'completed';

            $this->closeRun(
                $runId,
                $status,
                $result,
                $context->toolCalls(),
                $context->counters(),
                null,
                $durationMs,
                $result['confidence'] ?? null
            );

            $this->audit->record(AiAuditLogger::AGENT_RUN_COMPLETED, $scope, [
                'actor_type' => 'agent',
                'actor_label' => $manifest->name,
                'related_type' => 'ai_agent_runs',
                'related_id' => $runId,
                'message' => $summary,
                'payload' => $context->counters() + ['duration_ms' => $durationMs, 'status' => $status],
            ]);

            return [
                'run_id' => $runId,
                'status' => $status,
                'summary' => $summary,
                'result' => $result,
                'counters' => $context->counters(),
                'error' => null,
            ];
        } catch (Throwable $exception) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            $this->closeRun(
                $runId,
                'failed',
                [],
                $context->toolCalls(),
                $context->counters(),
                $exception->getMessage(),
                $durationMs
            );

            $this->audit->record(AiAuditLogger::AGENT_RUN_FAILED, $scope, [
                'actor_type' => 'agent',
                'actor_label' => $manifest->name,
                'related_type' => 'ai_agent_runs',
                'related_id' => $runId,
                'outcome' => 'failure',
                'message' => $exception->getMessage(),
                'payload' => ['duration_ms' => $durationMs],
            ]);

            return [
                'run_id' => $runId,
                'status' => 'failed',
                'summary' => 'The agent could not complete its analysis.',
                'result' => [],
                'counters' => $context->counters(),
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Run history, newest first. Used by the AI Administration run log.
     */
    public function runs(McpRequestContext $scope, ?string $agentKey = null, int $limit = 50): array
    {
        if (! Schema::hasTable('ai_agent_runs')) {
            return [];
        }

        $query = DB::table('ai_agent_runs')
            ->where('sub_institute_id', $scope->selectedInstituteId);

        if ($agentKey !== null) {
            $query->where('agent_key', $agentKey);
        }

        return $query->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'reference' => $row->run_reference,
                'agent_key' => $row->agent_key,
                'trigger_type' => $row->trigger_type,
                'status' => $row->status,
                'confidence' => $row->confidence === null ? null : (float) $row->confidence,
                'signals_detected' => (int) $row->signals_detected,
                'evidence_collected' => (int) $row->evidence_collected,
                'cases_opened' => (int) $row->cases_opened,
                'recommendations_drafted' => (int) $row->recommendations_drafted,
                'started_at' => $row->started_at,
                'finished_at' => $row->finished_at,
                'duration_ms' => $row->duration_ms === null ? null : (int) $row->duration_ms,
                'error_message' => $row->error_message,
            ])
            ->all();
    }

    // ---------------------------------------------------------------- internals

    private function instantiate(AgentManifest $manifest): ?object
    {
        if (! class_exists($manifest->runnerClass)) {
            return null;
        }

        try {
            return $this->container->make($manifest->runnerClass);
        } catch (Throwable) {
            return null;
        }
    }

    private function openRun(
        AgentManifest $manifest,
        McpRequestContext $scope,
        array $input,
        string $triggerType,
        ?string $triggerReference
    ): ?int {
        if (! Schema::hasTable('ai_agent_runs')) {
            return null;
        }

        return (int) DB::table('ai_agent_runs')->insertGetId([
            'run_reference' => $this->nextReference(),
            'agent_id' => $manifest->id,
            'agent_key' => $manifest->key,
            'trigger_type' => $triggerType,
            'trigger_reference' => $triggerReference,
            'triggered_by' => $scope->userId,
            'subject_entity_key' => $input['subject_entity_key'] ?? null,
            'subject_id' => isset($input['subject_id']) && is_numeric($input['subject_id'])
                ? (int) $input['subject_id']
                : null,
            'input' => json_encode($input),
            'status' => 'running',
            'started_at' => now(),
            // Scope pinned here, once.
            'sub_institute_id' => $scope->selectedInstituteId,
            'client_id' => $scope->clientId,
            'academic_year' => $scope->academicYear,
            'term_id' => $scope->termId,
            'actor_role' => $scope->role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function closeRun(
        ?int $runId,
        string $status,
        array $result,
        array $toolCalls,
        ?array $counters,
        ?string $error,
        ?int $durationMs = null,
        mixed $confidence = null
    ): void {
        if ($runId === null || ! Schema::hasTable('ai_agent_runs')) {
            return;
        }

        DB::table('ai_agent_runs')->where('id', $runId)->update([
            'status' => $status,
            'output' => $result === [] ? null : json_encode($result),
            'tool_calls' => $toolCalls === [] ? null : json_encode($toolCalls),
            'signals_detected' => $counters['signals_detected'] ?? 0,
            'evidence_collected' => $counters['evidence_collected'] ?? 0,
            'cases_opened' => $counters['cases_opened'] ?? 0,
            'recommendations_drafted' => $counters['recommendations_drafted'] ?? 0,
            'confidence' => is_numeric($confidence) ? (float) $confidence : null,
            'error_message' => $error,
            'finished_at' => now(),
            'duration_ms' => $durationMs,
            'updated_at' => now(),
        ]);
    }

    private function refuse(
        string $agentKey,
        McpRequestContext $scope,
        string $reason,
        array $input,
        string $triggerType,
        ?int $runId = null
    ): array {
        return [
            'run_id' => $runId,
            'status' => 'rejected',
            'summary' => $reason,
            'result' => [],
            'counters' => [
                'signals_detected' => 0,
                'evidence_collected' => 0,
                'cases_opened' => 0,
                'recommendations_drafted' => 0,
            ],
            'error' => $reason,
        ];
    }

    private function nextReference(): string
    {
        $prefix = sprintf('RUN-%d-', now()->year);

        $last = DB::table('ai_agent_runs')
            ->where('run_reference', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('run_reference');

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
