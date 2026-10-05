<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * The L5 half of a module's payload, read back from the signal ledger.
 *
 * ── WHY THIS IS SHARED ──────────────────────────────────────────────────────
 *
 * `BrainFeesIntelligenceController` already reads recommendations, the decision
 * trail and the learning memory out of the `hpbrain_*` tables, and it does so
 * correctly — measured figures stay null when nobody measured them, an outcome
 * nobody recorded is `undetermined` rather than "pending", and the learning
 * memory is deliberately NOT year-filtered because a memory that only remembers
 * the year you are looking at is not a memory.
 *
 * Reproducing that judgement eleven more times would reproduce the judgement
 * eleven times and then lose it one controller at a time. The only thing that
 * differs between modules is WHICH rule keys to read and what noun the impact
 * figure carries, so those are the two parameters and everything else is shared.
 *
 * ── WHAT IT WILL NOT DO ─────────────────────────────────────────────────────
 *
 * It never invents a stage. A recommendation with no decision has `decision =>
 * null`; a decision with no execution reports `no_action_queued`; an execution
 * with no outcome reports `awaiting_outcome`. Every one of those is a real state
 * of the loop, and filling any of them in would make the ledger claim work that
 * nobody did.
 */
final class ModuleLoop
{
    public function __construct(
        private readonly string $tenantId,
        private readonly ?string $syear,
        /** The module's key — `attendance`, `transport`. Its rule keys are prefixed with it. */
        private readonly string $module,
        /** The noun this module's impact figure carries: "students", "titles", "arrangements". */
        private readonly string $impactUnit = 'records',
    ) {
    }

    private function rulePrefix(): string
    {
        return ModuleSignalBridge::ruleKeyPrefix($this->module).'%';
    }

    /* ------------------------------------------------- L5: recommendations */

    /**
     * What to consider doing, each carried back to the finding it came from.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recommendations(): array
    {
        if (! SchemaCache::hasTable('hpbrain_recommendations')) {
            return [];
        }

        $rows = DB::table('hpbrain_recommendations as r')
            ->join('hpbrain_reasoning_steps as s', 's.id', '=', 'r.reasoning_step_id')
            ->join('hpbrain_signals as sg', 'sg.id', '=', 's.signal_id')
            ->where('r.tenant_id', $this->tenantId)
            ->where('sg.rule_key', 'like', $this->rulePrefix())
            ->when(
                $this->syear !== null && SchemaCache::hasColumn('hpbrain_recommendations', 'syear'),
                fn ($q) => $q->where(fn ($inner) => $inner->where('r.syear', $this->syear)->orWhereNull('r.syear')),
            )
            ->orderByRaw("FIELD(r.priority, 'critical', 'high', 'medium', 'low')")
            ->orderByDesc('r.created_date')
            ->limit(50)
            ->get([
                'r.id', 'r.title', 'r.description', 'r.category', 'r.priority', 'r.urgency',
                'r.confidence', 'r.status', 'r.eso_id', 'r.created_date', 'r.syear',
                'sg.id as signal_id', 'sg.rule_key', 'sg.severity', 'sg.metadata',
            ]);

        if ($rows->isEmpty()) {
            return [];
        }

        $decisions = SchemaCache::hasTable('hpbrain_decisions')
            ? DB::table('hpbrain_decisions')->where('tenant_id', $this->tenantId)
                ->whereIn('recommendation_id', $rows->pluck('id'))
                ->orderByDesc('created_date')->get()->keyBy('recommendation_id')
            : collect();

        return $rows->map(function ($row) use ($decisions) {
            $metadata = $this->metadata($row->metadata);
            $cause = RuleCatalogue::for((string) $row->rule_key);
            $decision = $decisions[$row->id] ?? null;

            return [
                'id' => (string) $row->id,
                'title' => (string) $row->title,
                'description' => (string) $row->description,
                'category' => (string) $row->category,
                'priority' => (string) $row->priority,
                'urgency' => (string) $row->urgency,
                'confidence' => [
                    'band' => Narrative::confidenceBand((float) $row->confidence),
                    'value' => round((float) $row->confidence, 2),
                ],
                'status' => (string) $row->status,
                'syear' => $row->syear,
                'expectedImpact' => $this->expectedImpact($metadata),
                'finding' => [
                    'signalId' => (string) $row->signal_id,
                    'title' => (string) ($metadata['title'] ?? ''),
                    'severity' => (string) $row->severity,
                ],
                // The approved cause, or null. A rule absent from RuleCatalogue
                // reached a recommendation with no explanation behind it, and
                // the card says so rather than composing one.
                'why' => $cause['hypothesis'] ?? null,
                // Review-only when no procedure is attached. The button is the
                // claim; without an ESO there is nothing to run.
                'actionable' => ! empty($row->eso_id),
                'decision' => $decision === null ? null : [
                    'id' => (string) $decision->id,
                    'status' => (string) $decision->status,
                    'rationale' => (string) $decision->rationale,
                    'decidedBy' => (string) $decision->decided_by,
                    'decidedAt' => (string) $decision->created_date,
                ],
            ];
        })->all();
    }

    /* ------------------------------------- L5: decisions, work and outcomes */

    /**
     * What was decided, what was carried out, and what it achieved — as ONE
     * trail, because a decision with no execution and an execution with no
     * outcome are states of the same thing.
     *
     * @return array<int, array<string, mixed>>
     */
    public function decisionTrail(): array
    {
        if (! SchemaCache::hasTable('hpbrain_decisions')) {
            return [];
        }

        $rows = DB::table('hpbrain_decisions as d')
            ->join('hpbrain_recommendations as r', 'r.id', '=', 'd.recommendation_id')
            ->join('hpbrain_reasoning_steps as s', 's.id', '=', 'r.reasoning_step_id')
            ->join('hpbrain_signals as sg', 'sg.id', '=', 's.signal_id')
            ->where('d.tenant_id', $this->tenantId)
            ->where('sg.rule_key', 'like', $this->rulePrefix())
            ->when(
                $this->syear !== null && SchemaCache::hasColumn('hpbrain_decisions', 'syear'),
                fn ($q) => $q->where(fn ($inner) => $inner->where('d.syear', $this->syear)->orWhereNull('d.syear')),
            )
            ->orderByDesc('d.created_date')
            ->limit(50)
            ->get([
                'd.id', 'd.status', 'd.rationale', 'd.decided_by', 'd.created_date', 'd.syear',
                'r.id as recommendation_id', 'r.title as recommendation_title', 'r.category',
                'sg.metadata', 'sg.rule_key',
            ]);

        if ($rows->isEmpty()) {
            return [];
        }

        $executions = SchemaCache::hasTable('hpbrain_eso_executions')
            ? DB::table('hpbrain_eso_executions')->where('tenant_id', $this->tenantId)
                ->whereIn('decision_id', $rows->pluck('id'))->get()->keyBy('decision_id')
            : collect();

        $outcomes = SchemaCache::hasTable('hpbrain_outcomes')
            ? DB::table('hpbrain_outcomes')->where('tenant_id', $this->tenantId)
                ->whereIn('decision_id', $rows->pluck('id'))->get()->keyBy('decision_id')
            : collect();

        $esoNames = $this->esoNames($executions);

        return $rows->map(function ($row) use ($executions, $outcomes, $esoNames) {
            $metadata = $this->metadata($row->metadata);
            $execution = $executions[$row->id] ?? null;
            $outcome = $outcomes[$row->id] ?? null;

            return [
                'decisionId' => (string) $row->id,
                'status' => (string) $row->status,
                'rationale' => (string) $row->rationale,
                'decidedBy' => (string) $row->decided_by,
                'decidedAt' => (string) $row->created_date,
                'syear' => $row->syear,
                'recommendation' => [
                    'id' => (string) $row->recommendation_id,
                    'title' => (string) $row->recommendation_title,
                    'category' => (string) $row->category,
                ],
                'finding' => (string) ($metadata['title'] ?? ''),
                'expectedImpact' => $this->impact($metadata),
                'execution' => $execution === null ? null : [
                    'id' => (string) $execution->id,
                    'status' => (string) $execution->status,
                    // The procedure a person is carrying out, never an ESO id.
                    'action' => (string) ($esoNames[(string) ($execution->eso_id ?? '')] ?? $row->recommendation_title),
                    'owner' => (string) ($execution->executed_by ?? ''),
                    // 'human' throughout: nothing in these modules runs
                    // unattended, and the payload says so rather than implying it.
                    'executorType' => (string) ($execution->executor_type ?? 'human'),
                    'queuedAt' => (string) $execution->created_date,
                    'startedAt' => $execution->started_date ? (string) $execution->started_date : null,
                    'completedAt' => $execution->completed_date ? (string) $execution->completed_date : null,
                ],
                'outcome' => $outcome === null ? null : [
                    'id' => (string) $outcome->id,
                    'result' => (string) $outcome->result,
                    'feedback' => (string) $outcome->feedback,
                    'recordedAt' => (string) $outcome->created_date,
                    'measured' => $this->measured($outcome),
                ],
                'outcomeState' => $this->outcomeState($execution, $outcome),
            ];
        })->all();
    }

    /* ------------------------------------------------------- L5: learning */

    /**
     * What earlier decisions in this module actually achieved.
     *
     * DELIBERATELY NOT YEAR-FILTERED. What worked last year is exactly what this
     * year's decision should be informed by; the year each entry belongs to is
     * carried on the entry, and `appliesToThisYear` marks the ones from the year
     * being viewed.
     *
     * @return array<string, mixed>
     */
    public function learning(): array
    {
        if (! SchemaCache::hasTable('hpbrain_outcomes')) {
            return [
                'available' => false,
                'reason' => 'Outcome capture is not provisioned in this database.',
                'entries' => [],
            ];
        }

        $rows = DB::table('hpbrain_outcomes as o')
            ->join('hpbrain_decisions as d', 'd.id', '=', 'o.decision_id')
            ->join('hpbrain_recommendations as r', 'r.id', '=', 'd.recommendation_id')
            ->join('hpbrain_reasoning_steps as s', 's.id', '=', 'r.reasoning_step_id')
            ->join('hpbrain_signals as sg', 'sg.id', '=', 's.signal_id')
            ->where('o.tenant_id', $this->tenantId)
            ->where('sg.rule_key', 'like', $this->rulePrefix())
            ->orderByDesc('o.created_date')
            ->limit(25)
            ->get(['o.result', 'o.feedback', 'o.created_date', 'o.syear', 'o.kpis', 'd.rationale',
                'r.title as action', 'sg.metadata']);

        if ($rows->isEmpty()) {
            return [
                'available' => false,
                // Names the module so the sentence is about THIS module rather
                // than about the product.
                'reason' => 'No '.$this->module.' decision has been seen through to a recorded outcome yet, so there '
                    .'is nothing learnt to show. A learning appears here once someone reports back on an approved '
                    .'recommendation.',
                'entries' => [],
            ];
        }

        return [
            'available' => true,
            'reason' => null,
            'entries' => $rows->map(function ($row) {
                $metadata = $this->metadata($row->metadata);

                return [
                    'finding' => (string) ($metadata['title'] ?? ''),
                    'action' => (string) $row->action,
                    'rationale' => (string) $row->rationale,
                    'result' => (string) $row->result,
                    'feedback' => (string) $row->feedback,
                    'syear' => $row->syear,
                    'recordedAt' => (string) $row->created_date,
                    'measured' => $this->measured($row),
                    'appliesToThisYear' => $this->syear !== null && (string) $row->syear === (string) $this->syear,
                ];
            })->all(),
        ];
    }

    /* ---------------------------------------------------------- freshness */

    /** When this module's signals were last written, or null if never. */
    public function signalsRefreshedAt(): ?string
    {
        if (! SchemaCache::hasTable('hpbrain_signals')) {
            return null;
        }

        $at = DB::table('hpbrain_signals')
            ->where('tenant_id', $this->tenantId)
            ->where('rule_key', 'like', $this->rulePrefix())
            ->when(
                $this->syear !== null && SchemaCache::hasColumn('hpbrain_signals', 'syear'),
                fn ($q) => $q->where(fn ($inner) => $inner->where('syear', $this->syear)->orWhereNull('syear')),
            )
            ->max('updated_date');

        return $at === null ? null : (string) $at;
    }

    /* ------------------------------------------------------------ helpers */

    /** @return array<string, mixed> */
    private function metadata(mixed $raw): array
    {
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * The module's own impact figure and its noun, carried through the ledger.
     *
     * @param  array<string, mixed>  $metadata
     * @return array{value:?float,display:string,label:string}|null
     */
    private function impact(array $metadata): ?array
    {
        if (! isset($metadata['impactDisplay'], $metadata['impactLabel'])) {
            return null;
        }

        return [
            'value' => isset($metadata['impactValue']) && is_numeric($metadata['impactValue'])
                ? (float) $metadata['impactValue']
                : null,
            'display' => (string) $metadata['impactDisplay'],
            'label' => (string) $metadata['impactLabel'],
        ];
    }

    /**
     * What approving this would address, stated as EXPOSURE rather than as
     * recovery — the figure is already true and already counted; nothing here
     * forecasts what the action achieves.
     *
     * @param  array<string, mixed>  $metadata
     * @return array{display:string,basis:string,wording:string}|null
     */
    private function expectedImpact(array $metadata): ?array
    {
        $impact = $this->impact($metadata);
        if ($impact === null) {
            return null;
        }

        return [
            'display' => $impact['display'],
            'basis' => 'The figure the finding rests on, as it stands today',
            'wording' => 'Addresses '.$impact['display'].' '.$impact['label'].'.',
        ];
    }

    /**
     * The measured before/after a person recorded, or null.
     *
     * NULL rather than zero. A zero would make the ledger claim the action moved
     * nothing, which is a different statement from nobody having measured it.
     *
     * @return array<string, mixed>|null
     */
    private function measured(object $outcome): ?array
    {
        $kpis = json_decode((string) ($outcome->kpis ?? '{}'), true);
        if (! is_array($kpis) || ! isset($kpis['measuredBefore'], $kpis['measuredAfter'])) {
            return null;
        }
        if ($kpis['measuredBefore'] === null || $kpis['measuredAfter'] === null) {
            return null;
        }

        return [
            'before' => (float) $kpis['measuredBefore'],
            'after' => (float) $kpis['measuredAfter'],
            'change' => (float) ($kpis['measuredChange'] ?? 0),
            'unitsAffected' => $kpis['accountsAffected'] ?? $kpis['unitsAffected'] ?? null,
            'basis' => (string) ($kpis['basis'] ?? 'Recorded by the person reporting back'),
        ];
    }

    /**
     * Procedure names for queued executions, so the work queue shows the action
     * rather than an ESO id.
     *
     * @param  \Illuminate\Support\Collection<string, object>  $executions
     * @return array<string, string>
     */
    private function esoNames($executions): array
    {
        $ids = array_values(array_filter($executions->pluck('eso_id')->all()));
        if ($ids === [] || ! SchemaCache::hasTable('hpbrain_eso_definitions')) {
            return [];
        }

        return DB::table('hpbrain_eso_definitions')
            ->where('tenant_id', $this->tenantId)
            ->whereIn('id', $ids)
            ->pluck('name', 'id')
            ->map(fn ($name) => (string) $name)
            ->all();
    }

    /** The loop's honest state for one decision. */
    private function outcomeState(?object $execution, ?object $outcome): string
    {
        if ($outcome !== null) {
            return match ((string) $outcome->result) {
                'success' => 'resolved',
                'partial' => 'partially_resolved',
                'failed' => 'not_reached',
                default => 'undetermined',
            };
        }

        return $execution === null ? 'no_action_queued' : 'awaiting_outcome';
    }
}
