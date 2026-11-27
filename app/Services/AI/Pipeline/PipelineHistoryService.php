<?php

namespace App\Services\AI\Pipeline;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reads back what the Users / Attendance pipelines persisted, for the dashboards.
 *
 * Source of truth is `ai_audit_logs` (`module.<m>.pipeline_step` / `pipeline_final`, written by
 * ModuleAgentPipeline) joined to the live approval records. Every read is filtered to the
 * caller's institute. Nothing is computed that was not stored, and an approval's status is read
 * from `workflow_approvals` NOW, not from the snapshot taken when the draft was made.
 */
class PipelineHistoryService
{
    /** Final outputs, newest first, each with its steps and (Attendance) live approval status. */
    public function outputs(ModuleAgentPipeline $pipeline, int $inst, int $limit = 5): array
    {
        if (! Schema::hasTable('ai_audit_logs')) {
            return [];
        }

        $m = $pipeline->auditModule();
        $rows = DB::table('ai_audit_logs')->where('sub_institute_id', $inst)
            ->where('event_type', "module.{$m}.pipeline_final")
            ->orderByDesc('id')->limit(min(max($limit, 1), 20))->get();

        $users = $this->userNames($inst, $rows->pluck('actor_id')->filter()->all());
        $out = [];

        foreach ($rows as $row) {
            $p = json_decode((string) $row->payload, true) ?: [];
            $final = $p['final'] ?? [];
            $steps = $this->stepsOf($m, $inst, (string) ($p['pipeline_run_id'] ?? ''));

            if (! empty($final['drafts'])) {
                $final['drafts'] = $this->liveApprovals($inst, $final['drafts']);
            }

            $out[] = [
                'audit_id' => (int) $row->id,
                'pipeline_run_id' => $p['pipeline_run_id'] ?? null,
                'generated_at' => $row->created_at,
                'started_by' => ['id' => $row->actor_id ? (int) $row->actor_id : null, 'name' => $users[$row->actor_id] ?? null],
                'status' => $p['status'] ?? 'completed',
                'academic_year' => $p['academic_year'] ?? null,
                'duration_ms' => $row->duration_ms,
                'options' => $p['options'] ?? [],
                'agent_run_ids' => $p['agent_run_ids'] ?? [],
                'gates' => $p['gates'] ?? [],
                'final' => $final,
                'steps' => $steps,
            ];
        }

        return $out;
    }

    /**
     * Per-agent execution history: first / latest execution, who started it, volumes and outcome.
     *
     * @param  array<int, array<string, mixed>>  $agents  the pipeline's `agents()` descriptors
     */
    public function roster(ModuleAgentPipeline $pipeline, int $inst): array
    {
        $m = $pipeline->auditModule();
        $hist = [];

        if (Schema::hasTable('ai_audit_logs')) {
            $agg = DB::table('ai_audit_logs')->where('sub_institute_id', $inst)->where('event_type', "module.{$m}.pipeline_step")
                ->groupBy('related_type')->selectRaw('related_type AS agent_id, COUNT(*) AS runs, MIN(created_at) AS first_at, MAX(created_at) AS last_at, MAX(id) AS last_id')
                ->get()->keyBy('agent_id');

            $last = DB::table('ai_audit_logs')->whereIn('id', $agg->pluck('last_id')->all() ?: [0])->get()->keyBy('related_type');
            $users = $this->userNames($inst, $last->pluck('actor_id')->filter()->all());

            foreach ($agg as $agentId => $a) {
                $l = $last[$agentId] ?? null;
                $p = $l ? (json_decode((string) $l->payload, true) ?: []) : [];
                $hist[$agentId] = [
                    'runs' => (int) $a->runs, 'first_run_at' => $a->first_at, 'last_run_at' => $a->last_at,
                    'last_status' => $l && $l->outcome === 'success' ? 'completed' : 'failed',
                    'last_duration_ms' => $l->duration_ms ?? null,
                    'last_records' => $p['records'] ?? null,
                    'last_gate' => isset($p['gate']) ? ['score' => $p['gate']['score'] ?? null, 'status' => $p['gate']['status'] ?? null, 'checks' => $p['gate']['checks'] ?? []] : null,
                    'last_started_by' => ['id' => $l && $l->actor_id ? (int) $l->actor_id : null, 'name' => $users[$l->actor_id ?? 0] ?? null],
                    'last_pipeline_run_id' => $p['pipeline_run_id'] ?? null,
                    'last_input' => $p['input'] ?? null,
                    'last_tables_read' => $p['sources'] ?? [],
                    'last_summary' => array_map(static fn ($x) => ['label' => $x['label'], 'value' => $x['value']], array_slice($p['metrics'] ?? [], 0, 6)),
                ];
            }
        }

        $roster = [];
        foreach ($pipeline->agents() as $n => $agent) {
            $roster[] = [
                'order' => $n, 'agent_id' => $agent['agent_id'], 'name' => $agent['name'], 'purpose' => $agent['purpose'],
                'tables' => $agent['tables'], 'graph' => $agent['graph'], 'tools' => $agent['tools'],
                'passes_to' => $agent['passes_to'], 'gate_name' => $agent['gate_name'],
            ] + ($hist[$agent['agent_id']] ?? ['runs' => 0, 'first_run_at' => null, 'last_run_at' => null, 'last_status' => 'never_run']);
        }

        return $roster;
    }

    /** @return array<int, array<string, mixed>> */
    private function stepsOf(string $m, int $inst, string $pipelineRunId): array
    {
        if ($pipelineRunId === '') {
            return [];
        }

        return DB::table('ai_audit_logs')->where('sub_institute_id', $inst)->where('event_type', "module.{$m}.pipeline_step")
            ->where('payload', 'like', '%"pipeline_run_id":"' . addcslashes($pipelineRunId, '%_\\') . '"%')
            ->orderBy('id')->get()->map(function ($r) {
                $p = json_decode((string) $r->payload, true) ?: [];

                return [
                    'step' => (int) $r->related_id, 'agent_id' => $r->related_type, 'agent_name' => $r->message,
                    'status' => $r->outcome === 'success' ? 'completed' : 'failed', 'started_at' => $r->created_at,
                    'duration_ms' => $r->duration_ms, 'records' => $p['records'] ?? null,
                    'gate_score' => $p['gate']['score'] ?? null, 'tool' => $p['tool'] ?? null,
                    'agent_run_id' => $p['agent_run_id'] ?? null, 'tables_read' => $p['sources'] ?? [],
                    'checks' => $p['gate']['checks'] ?? [],
                ];
            })->all();
    }

    /** Replace each draft's stored approval snapshot with the approval's CURRENT state. */
    private function liveApprovals(int $inst, array $drafts): array
    {
        $runIds = array_values(array_filter(array_column($drafts, 'workflow_run_id')));
        if ($runIds === [] || ! Schema::hasTable('workflow_approvals')) {
            return $drafts;
        }

        $runs = DB::table('workflow_runs')->where('sub_institute_id', $inst)->whereIn('id', $runIds)->get()->keyBy('id');
        $approvals = DB::table('workflow_approvals')->where('sub_institute_id', $inst)->whereIn('run_id', $runIds)->orderBy('id')->get()->groupBy('run_id');
        $recs = DB::table('ai_recommendations')->where('sub_institute_id', $inst)->whereIn('id', $runs->pluck('recommendation_id')->filter()->all() ?: [0])->pluck('status', 'id');
        $deciders = $this->userNames($inst, $approvals->flatten()->pluck('decided_by')->filter()->all());

        foreach ($drafts as $i => $d) {
            $run = $runs[$d['workflow_run_id'] ?? 0] ?? null;
            if (! $run) {
                continue;
            }
            $a = ($approvals[$run->id] ?? collect())->last();
            $drafts[$i]['workflow_status'] = $run->status;
            $drafts[$i]['recommendation_status'] = $recs[$run->recommendation_id] ?? null;
            $drafts[$i]['approval_id'] = $a->id ?? null;
            $drafts[$i]['approval_status'] = $a->status ?? null;
            $drafts[$i]['approval_expires_at'] = $a->expires_at ?? null;
            $drafts[$i]['approval_decided_by'] = $a && $a->decided_by ? ['id' => (int) $a->decided_by, 'name' => $deciders[$a->decided_by] ?? null] : null;
            $drafts[$i]['approval_decided_at'] = $a->decided_at ?? null;
            $drafts[$i]['approval_comment'] = $a->comment ?? null;
        }

        return $drafts;
    }

    /** @return array<int, string> */
    private function userNames(int $inst, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        return DB::table('tbluser')->where('sub_institute_id', $inst)->whereIn('id', $ids)
            ->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(fn ($u) => [(int) $u->id => trim($u->first_name . ' ' . $u->last_name)])->all();
    }
}
