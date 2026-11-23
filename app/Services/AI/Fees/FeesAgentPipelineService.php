<?php

namespace App\Services\AI\Fees;

use App\Domain\AI\Agents\AgentRunner;
use App\Services\Mcp\McpRequestContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Operational control for the two governed Fees agents (`k12_fees`,
 * `k12_fees_predictive`) — turning them from one-shot
 * (`POST /api/ai/agents/{agent}/run`) into something that can run on a
 * schedule and be toggled from a UI, the same shape every other module's
 * `{module}/agent/*` route group already offers.
 *
 * Deliberately NOT a copy of PalAgentPipelineService's bespoke tool
 * orchestration: `k12_fees`/`k12_fees_predictive` are already real,
 * governed agents registered in `ai_agents`, invoked through the same
 * AgentRunner every governed agent uses. This service's job is only to
 * sequence those two real runs and persist on/off/interval state, not to
 * hand-roll a second agent-execution mechanism. `eso()` reads back the
 * real `eso_binding` already on each drafted recommendation rather than a
 * hardcoded descriptive object — PAL/Engagement's own ESO is static
 * documentation text; this one is live data.
 */
class FeesAgentPipelineService
{
    public const STATE_AGENT_KEY = 'fees_pipeline';

    public const DEFAULT_INTERVAL_SECONDS = 300;

    private const AGENT_KEYS = ['k12_fees', 'k12_fees_predictive'];

    private const WORKFLOW_KEYS = ['fees_collection', 'fees_proactive_outreach'];

    public function __construct(private readonly AgentRunner $runner)
    {
    }

    public function runPipeline(int $subInstituteId, array $options = []): array
    {
        $scope = $this->buildContext($subInstituteId, $options);
        $results = [];

        foreach (self::AGENT_KEYS as $agentKey) {
            $results[$agentKey] = $this->runner->run($agentKey, $scope, $options, 'pipeline', 'fees_agent_pipeline');
        }

        $summary = [
            'students_with_arrears' => $results['k12_fees']['result']['students_with_arrears'] ?? 0,
            'students_with_worsening_trend' => $results['k12_fees_predictive']['result']['students_with_worsening_trend'] ?? 0,
            'recommendations_drafted' =>
                ($results['k12_fees']['counters']['recommendations_drafted'] ?? 0)
                + ($results['k12_fees_predictive']['counters']['recommendations_drafted'] ?? 0),
        ];

        $allCompleted = ($results['k12_fees']['status'] ?? null) === 'completed'
            && ($results['k12_fees_predictive']['status'] ?? null) === 'completed';

        $this->persistState($subInstituteId, [
            'last_run_at' => Carbon::now(),
            'last_run_status' => $allCompleted ? 'success' : 'partial',
            'summary_counts' => $summary,
        ]);

        return [
            'status' => 'completed',
            'sub_institute_id' => $subInstituteId,
            'timestamp' => Carbon::now()->toIso8601String(),
            'runs' => $results,
            'summary' => $summary,
        ];
    }

    public function getAgentState(int $subInstituteId): array
    {
        $default = [
            'agent_key' => self::STATE_AGENT_KEY,
            'is_active' => false,
            'status' => 'idle',
            'interval_seconds' => self::DEFAULT_INTERVAL_SECONDS,
            'last_run_time' => null,
            'last_run_status' => null,
            'next_scheduled_run' => null,
            'counts' => ['students_with_arrears' => 0, 'students_with_worsening_trend' => 0, 'recommendations_drafted' => 0],
        ];

        if (! Schema::hasTable('fees_agent_state')) {
            return $default;
        }

        $row = DB::table('fees_agent_state')
            ->where('sub_institute_id', $subInstituteId)
            ->where('agent_key', self::STATE_AGENT_KEY)
            ->first();

        if (! $row) {
            return $default;
        }

        $summary = $row->summary_counts ? json_decode($row->summary_counts, true) : [];
        $interval = (int) ($row->interval_seconds ?: self::DEFAULT_INTERVAL_SECONDS);
        $lastRun = $row->last_run_at ? Carbon::parse($row->last_run_at) : null;
        $nextRun = ($row->is_active && $lastRun) ? (clone $lastRun)->addSeconds($interval) : null;

        return [
            'agent_key' => self::STATE_AGENT_KEY,
            'is_active' => (bool) $row->is_active,
            'status' => $row->is_active ? 'active' : 'paused',
            'interval_seconds' => $interval,
            'last_run_time' => $row->last_run_at,
            'last_run_status' => $row->last_run_status,
            'next_scheduled_run' => $nextRun?->toIso8601String(),
            'counts' => [
                'students_with_arrears' => $summary['students_with_arrears'] ?? 0,
                'students_with_worsening_trend' => $summary['students_with_worsening_trend'] ?? 0,
                'recommendations_drafted' => $summary['recommendations_drafted'] ?? 0,
            ],
        ];
    }

    public function activateContinuousMonitoring(int $subInstituteId, int $intervalSeconds = self::DEFAULT_INTERVAL_SECONDS): array
    {
        $intervalSeconds = max(60, min(86400, $intervalSeconds));

        $this->persistState($subInstituteId, [
            'is_active' => true,
            'interval_seconds' => $intervalSeconds,
            'last_heartbeat_at' => Carbon::now(),
        ]);

        return ['activated' => true, 'interval_seconds' => $intervalSeconds, 'sub_institute_id' => $subInstituteId];
    }

    public function deactivate(int $subInstituteId): array
    {
        $this->persistState($subInstituteId, ['is_active' => false]);

        return ['deactivated' => true, 'sub_institute_id' => $subInstituteId];
    }

    public function setInterval(int $subInstituteId, int $intervalSeconds): array
    {
        $intervalSeconds = max(60, min(86400, $intervalSeconds));
        $this->persistState($subInstituteId, ['interval_seconds' => $intervalSeconds]);

        return ['sub_institute_id' => $subInstituteId, 'interval_seconds' => $intervalSeconds];
    }

    /**
     * Every tenant currently switched on and due, ticked in sequence — the
     * scheduler's single entry point (see app/Console/Kernel.php, added
     * separately once confirmed).
     */
    public function tickActiveAgents(): void
    {
        if (! Schema::hasTable('fees_agent_state')) {
            return;
        }

        $due = DB::table('fees_agent_state')
            ->where('agent_key', self::STATE_AGENT_KEY)
            ->where('is_active', true)
            ->get();

        foreach ($due as $row) {
            $interval = (int) ($row->interval_seconds ?: self::DEFAULT_INTERVAL_SECONDS);
            $lastRun = $row->last_run_at ? Carbon::parse($row->last_run_at) : null;

            if ($lastRun !== null && $lastRun->addSeconds($interval)->isFuture()) {
                continue;
            }

            try {
                $this->runPipeline((int) $row->sub_institute_id);
            } catch (Throwable $exception) {
                Log::warning('[fees-agent-pipeline] scheduled tick failed', [
                    'sub_institute_id' => $row->sub_institute_id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * The real eso_binding already on each drafted recommendation, not a
     * hardcoded descriptive object — what a reviewer sees in the approval
     * queue, surfaced here as the live objective/strategy/outcome this
     * tenant's agents are actually pursuing right now.
     */
    public function eso(int $subInstituteId): array
    {
        if (! Schema::hasTable('ai_recommendations')) {
            return ['sub_institute_id' => $subInstituteId, 'recent' => []];
        }

        $rows = DB::table('ai_recommendations')
            ->where('sub_institute_id', $subInstituteId)
            ->whereIn('workflow_key', self::WORKFLOW_KEYS)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'title', 'workflow_key', 'risk_level', 'confidence', 'status', 'eso_binding', 'created_at']);

        return [
            'sub_institute_id' => $subInstituteId,
            'recent' => $rows->map(fn ($row) => [
                'recommendation_id' => (int) $row->id,
                'title' => $row->title,
                'workflow_key' => $row->workflow_key,
                'risk_level' => $row->risk_level,
                'confidence' => $row->confidence === null ? null : (float) $row->confidence,
                'status' => $row->status,
                'eso_binding' => $row->eso_binding ? json_decode($row->eso_binding, true) : null,
                'created_at' => $row->created_at,
            ])->all(),
        ];
    }

    private function buildContext(int $subInstituteId, array $options): McpRequestContext
    {
        return new McpRequestContext(
            userId: (int) ($options['acting_user_id'] ?? 1),
            role: 'staff',
            selectedInstituteId: $subInstituteId,
            allowedInstituteIds: [$subInstituteId],
            userProfileId: null,
            clientId: isset($options['client_id']) ? (int) $options['client_id'] : null,
            academicYear: isset($options['syear']) ? (int) $options['syear'] : null,
            termId: null,
            isAdmin: false,
            isStudent: false,
        );
    }

    private function persistState(int $subInstituteId, array $data): void
    {
        if (! Schema::hasTable('fees_agent_state')) {
            return;
        }

        $payload = $data;

        if (isset($payload['summary_counts']) && is_array($payload['summary_counts'])) {
            $payload['summary_counts'] = json_encode($payload['summary_counts']);
        }

        $payload['updated_at'] = Carbon::now();

        $exists = DB::table('fees_agent_state')
            ->where('sub_institute_id', $subInstituteId)
            ->where('agent_key', self::STATE_AGENT_KEY)
            ->exists();

        if ($exists) {
            DB::table('fees_agent_state')
                ->where('sub_institute_id', $subInstituteId)
                ->where('agent_key', self::STATE_AGENT_KEY)
                ->update($payload);

            return;
        }

        DB::table('fees_agent_state')->insert(array_merge($payload, [
            'sub_institute_id' => $subInstituteId,
            'agent_key' => self::STATE_AGENT_KEY,
            'is_active' => $payload['is_active'] ?? false,
            'interval_seconds' => $payload['interval_seconds'] ?? self::DEFAULT_INTERVAL_SECONDS,
            'created_at' => Carbon::now(),
        ]));
    }
}
