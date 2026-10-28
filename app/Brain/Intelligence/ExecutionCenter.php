<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Builds the Execution Center payload from the Brain's own tables.
 *
 * Every read is scoped by `tenant_id`, and every related table is fetched in ONE query per table
 * (whereIn over the page of ids), so the cost does not grow with the number of executions. All
 * classification is delegated to ExecutionCenterRules; nothing here invents a value - a missing
 * relationship comes back as null/empty and the screen says so.
 *
 * Join paths, each verified against the live schema:
 *   execution.decision_id        -> decision.id            (title/reason/who decided)
 *   decision.recommendation_id   -> recommendation.id      (title, "Why:", eso_id)
 *   recommendation.reasoning_step_id -> reasoning_step.signal_id -> evidence.signal_id  (the FINDING's evidence)
 *   outcome.decision_id          -> decision.id            (the outcome of this execution's decision)
 *   execution_evidence.execution_id -> execution.id        (proof the ACTION was carried out)
 *
 * "Finding evidence" (why the action was proposed) and "execution evidence" (proof it was done) are
 * different things and are never merged: the first is plentiful, the second must be linked by a person.
 */
final class ExecutionCenter
{
    /** More than this and the summary says it is partial instead of silently ignoring the rest. */
    public const LOAD_LIMIT = 1000;

    private const EVIDENCE_PREVIEW = 5;

    public function __construct(private readonly string $tenantId, private readonly ?\DateTimeInterface $now = null)
    {
    }

    private function now(): \DateTimeInterface
    {
        return $this->now ?? new \DateTimeImmutable();
    }

    private function has(string $table): bool
    {
        return SchemaCache::hasTable($table);
    }

    /**
     * @param  array{page?:int,perPage?:int,status?:string,sample?:string,q?:string}  $filters
     * @return array<string,mixed>
     */
    public function build(array $filters = []): array
    {
        if (! $this->has('hpbrain_eso_executions')) {
            return $this->unavailable('The execution tables are not installed on this server.');
        }

        $total = (int) DB::table('hpbrain_eso_executions')->where('tenant_id', $this->tenantId)->count();
        $rows = DB::table('hpbrain_eso_executions')->where('tenant_id', $this->tenantId)
            ->orderByDesc('created_date')->limit(self::LOAD_LIMIT)->get()->map(fn ($r) => (array) $r)->all();

        $records = $this->records($rows);

        $real = array_values(array_filter($records, fn ($r) => ! $r['isSample']));
        $sample = array_values(array_filter($records, fn ($r) => $r['isSample']));

        $outcomes = $this->outcomeLinkage($records);

        $attention = array_values(array_filter($records, fn ($r) => $r['attention'] !== null));
        usort($attention, fn ($a, $b) => [$a['isSample'], -$this->rank($a['attention']['severity']), $a['createdAt']]
            <=> [$b['isSample'], -$this->rank($b['attention']['severity']), $b['createdAt']]);

        $impact = array_values(array_filter($records, fn ($r) => $r['outcome'] !== null));
        // Genuine records first, newest outcome first within each group.
        usort($impact, fn ($a, $b) => ($a['isSample'] <=> $b['isSample'])
            ?: strcmp((string) $b['outcome']['recordedAt'], (string) $a['outcome']['recordedAt']));

        return [
            'available' => true,
            'tenantId' => $this->tenantId,
            'generatedAt' => $this->now()->format(DATE_ATOM),
            'partial' => $total > self::LOAD_LIMIT ? "Showing the newest ".self::LOAD_LIMIT." of {$total} executions." : null,
            'health' => [
                'real' => ExecutionCenterRules::healthCounts($this->slim($real)),
                'sample' => ExecutionCenterRules::healthCounts($this->slim($sample)),
            ],
            'verification' => [
                'real' => ExecutionCenterRules::verificationCounts($this->slim($real)),
                'sample' => ExecutionCenterRules::verificationCounts($this->slim($sample)),
                'outcomes' => $outcomes,
            ],
            'attention' => array_map([$this, 'brief'], $attention),
            'impact' => array_map([$this, 'impactRow'], $impact),
            'history' => $this->page($records, $filters),
            'definitions' => self::definitions(),
        ];
    }

    /** One record in full, or null when it is not this tenant's. */
    public function find(string $executionId): ?array
    {
        if (! $this->has('hpbrain_eso_executions')) {
            return null;
        }
        $row = DB::table('hpbrain_eso_executions')->where('tenant_id', $this->tenantId)->where('id', $executionId)->first();

        return $row ? ($this->records([(array) $row])[0] ?? null) : null;
    }

    /** Evidence of the finding behind an execution that could be linked to it. Tenant-scoped. */
    public function linkableEvidence(string $executionId): array
    {
        $record = $this->find($executionId);

        return $record === null ? [] : $record['evidence']['finding'];
    }

    /* ------------------------------------------------------------------ records */

    /** @param  array<int,array<string,mixed>>  $rows */
    private function records(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $t = $this->tenantId;
        $decisionIds = array_values(array_unique(array_filter(array_column($rows, 'decision_id'))));

        $decisions = $this->keyed($this->has('hpbrain_decisions') && $decisionIds
            ? DB::table('hpbrain_decisions')->where('tenant_id', $t)->whereIn('id', $decisionIds)->get() : [], 'id');
        $recIds = array_values(array_unique(array_filter(array_map(fn ($d) => $d['recommendation_id'] ?? null, $decisions))));
        $recs = $this->keyed($this->has('hpbrain_recommendations') && $recIds
            ? DB::table('hpbrain_recommendations')->where('tenant_id', $t)->whereIn('id', $recIds)->get() : [], 'id');
        $esoIds = array_values(array_unique(array_filter(array_column($rows, 'eso_id'))));
        $esos = $this->keyed($this->has('hpbrain_eso_definitions') && $esoIds
            ? DB::table('hpbrain_eso_definitions')->where('tenant_id', $t)->whereIn('id', $esoIds)->get(['id', 'eso_code', 'name']) : [], 'id');

        $outcomesByDecision = [];
        if ($this->has('hpbrain_outcomes') && $decisionIds) {
            foreach (DB::table('hpbrain_outcomes')->where('tenant_id', $t)->whereIn('decision_id', $decisionIds)->orderBy('created_date')->get() as $o) {
                $outcomesByDecision[$o->decision_id][] = (array) $o;
            }
        }

        $execIds = array_column($rows, 'id');
        $linkRows = $this->has('hpbrain_eso_execution_evidence')
            ? DB::table('hpbrain_eso_execution_evidence')->where('tenant_id', $t)->whereIn('execution_id', $execIds)->get()->map(fn ($r) => (array) $r)->all() : [];

        $stepIds = array_values(array_unique(array_filter(array_map(fn ($r) => $r['reasoning_step_id'] ?? null, $recs))));
        $steps = $this->keyed($this->has('hpbrain_reasoning_steps') && $stepIds
            ? DB::table('hpbrain_reasoning_steps')->where('tenant_id', $t)->whereIn('id', $stepIds)->get(['id', 'signal_id']) : [], 'id');
        $signalIds = array_values(array_unique(array_filter(array_map(fn ($s) => $s['signal_id'] ?? null, $steps))));

        $outcomeEvidenceIds = [];
        foreach ($outcomesByDecision as $list) {
            foreach ($list as $o) {
                foreach ((array) json_decode((string) ($o['evidence_ids'] ?? '[]'), true) as $id) {
                    $outcomeEvidenceIds[] = (string) $id;
                }
            }
        }
        $linkedEvidenceIds = array_column($linkRows, 'evidence_id');

        $evidenceBySignal = [];
        $evidenceById = [];
        if ($this->has('hpbrain_evidence')) {
            $cols = ['id', 'signal_id', 'source', 'evidence_type', 'content', 'confidence', 'observed_date'];
            if ($signalIds) {
                foreach (DB::table('hpbrain_evidence')->where('tenant_id', $t)->whereIn('signal_id', $signalIds)->orderByDesc('observed_date')->get($cols) as $e) {
                    $evidenceBySignal[$e->signal_id][] = $this->evidenceRow((array) $e);
                }
            }
            $ids = array_values(array_unique(array_merge($linkedEvidenceIds, $outcomeEvidenceIds)));
            if ($ids) {
                foreach (DB::table('hpbrain_evidence')->where('tenant_id', $t)->whereIn('id', $ids)->get($cols) as $e) {
                    $evidenceById[$e->id] = $this->evidenceRow((array) $e);
                }
            }
        }

        $people = $this->people(array_merge(array_column($rows, 'executed_by'), array_map(fn ($d) => $d['decided_by'] ?? '', $decisions)));

        $out = [];
        foreach ($rows as $e) {
            $decision = $decisions[$e['decision_id'] ?? ''] ?? null;
            $rec = $decision ? ($recs[$decision['recommendation_id'] ?? ''] ?? null) : null;
            $eso = $esos[$e['eso_id'] ?? ''] ?? null;
            $outcome = $this->pickOutcome($outcomesByDecision[$e['decision_id'] ?? ''] ?? [], (string) $e['id']);

            $sample = ExecutionCenterRules::isSample($e, $decision, $outcome);
            $group = ExecutionCenterRules::statusGroup($e['status'] ?? null);

            $executionEvidence = [];
            foreach ($linkRows as $l) {
                if ($l['execution_id'] === $e['id'] && isset($evidenceById[$l['evidence_id']])) {
                    $executionEvidence[$l['evidence_id']] = $evidenceById[$l['evidence_id']] + ['linkedAt' => $l['linked_date'] ?? null];
                }
            }
            if ($outcome) {
                foreach ((array) json_decode((string) ($outcome['evidence_ids'] ?? '[]'), true) as $id) {
                    if (isset($evidenceById[(string) $id])) {
                        $executionEvidence[(string) $id] ??= $evidenceById[(string) $id] + ['linkedAt' => null];
                    }
                }
            }
            $signalId = $rec ? ($steps[$rec['reasoning_step_id'] ?? '']['signal_id'] ?? null) : null;
            $findingEvidence = $signalId ? ($evidenceBySignal[$signalId] ?? []) : [];

            $kpis = $outcome ? (json_decode((string) ($outcome['kpis'] ?? ''), true) ?: null) : null;
            $measurement = ExecutionCenterRules::measurement(is_array($kpis) ? $kpis : null);
            $evidenceCount = count($executionEvidence);
            $state = ExecutionCenterRules::outcomeState($group, $outcome !== null, $outcome['result'] ?? null, $measurement !== null, $evidenceCount);
            $age = ExecutionCenterRules::ageDays($e['created_date'] ?? null, $this->now());

            $out[] = [
                'id' => (string) $e['id'],
                'esoId' => (string) ($e['eso_id'] ?? ''),
                'decisionId' => $e['decision_id'] ?? null,
                'title' => $this->title($rec, $decision, $eso),
                'reason' => $this->reason($rec, $decision),
                'decision' => $decision ? [
                    'rationale' => (string) ($decision['rationale'] ?? ''),
                    'status' => (string) ($decision['status'] ?? ''),
                    'decidedBy' => $this->person($people, (string) ($decision['decided_by'] ?? '')),
                    'decidedAt' => $decision['approved_date'] ?? $decision['created_date'] ?? null,
                ] : null,
                'action' => $eso ? ['code' => $eso['eso_code'] ?? null, 'name' => $eso['name'] ?? null] : null,
                'executor' => ['label' => $this->person($people, (string) ($e['executed_by'] ?? '')), 'type' => (string) ($e['executor_type'] ?? ''), 'raw' => (string) ($e['executed_by'] ?? '')],
                'status' => (string) ($e['status'] ?? ''),
                'group' => $group,
                'createdAt' => $e['created_date'] ?? null,
                'startedAt' => $e['started_date'] ?? null,
                'completedAt' => $e['completed_date'] ?? null,
                'ageDays' => $age,
                'error' => $e['error'] ?? null,
                'isSample' => $sample,
                'outcome' => $outcome ? [
                    'id' => (string) $outcome['id'],
                    'result' => (string) ($outcome['result'] ?? ''),
                    'feedback' => (string) ($outcome['feedback'] ?? ''),
                    'confidence' => is_numeric($outcome['confidence'] ?? null) ? (float) $outcome['confidence'] : null,
                    'confidenceMeaning' => ExecutionCenterRules::confidenceMeaning($outcome['confidence'] ?? null, $outcome['result'] ?? null, $sample),
                    'measurement' => $measurement,
                    'recordedAt' => $outcome['created_date'] ?? null,
                    'recordedBy' => $this->person($people, (string) ($outcome['created_by'] ?? '')),
                ] : null,
                'outcomeState' => $state,
                'evidence' => [
                    'execution' => array_values($executionEvidence),
                    'executionCount' => $evidenceCount,
                    'finding' => array_slice($findingEvidence, 0, self::EVIDENCE_PREVIEW),
                    'findingTotal' => count($findingEvidence),
                ],
                'evidenceCount' => $evidenceCount,
                'attention' => ExecutionCenterRules::attention($group, $age, $state, $evidenceCount, $e['error'] ?? null),
                'technical' => ['executionId' => (string) $e['id'], 'esoId' => (string) ($e['eso_id'] ?? ''), 'decisionId' => $e['decision_id'] ?? null,
                    'recommendationId' => $decision['recommendation_id'] ?? null, 'outcomeId' => $outcome['id'] ?? null, 'input' => $e['input'] ?? null, 'output' => $e['output'] ?? null],
            ];
        }

        return $out;
    }

    /** The outcome that belongs to THIS execution when a decision has several. */
    private function pickOutcome(array $candidates, string $executionId): ?array
    {
        $named = null;
        $unnamed = null;
        foreach ($candidates as $o) {
            $m = json_decode((string) ($o['metrics'] ?? ''), true);
            $eid = is_array($m) ? ($m['executionId'] ?? null) : null;
            if ($eid === $executionId) {
                $named = $o;
            } elseif ($eid === null) {
                $unnamed = $o;
            }
        }

        return $named ?? $unnamed;
    }

    /**
     * How many of this tenant's outcome rows join to an execution. Answers "are the outcomes linked to
     * the executions?" from the data rather than from an assumption.
     */
    private function outcomeLinkage(array $records): array
    {
        $total = $this->has('hpbrain_outcomes') ? (int) DB::table('hpbrain_outcomes')->where('tenant_id', $this->tenantId)->count() : 0;
        $linked = count(array_filter($records, fn ($r) => $r['outcome'] !== null));

        return ['total' => $total, 'linkedToAnExecution' => min($linked, $total), 'notLinked' => max(0, $total - $linked)];
    }

    private function page(array $records, array $f): array
    {
        $status = (string) ($f['status'] ?? 'all');
        $sample = (string) ($f['sample'] ?? 'all');
        $q = mb_strtolower(trim((string) ($f['q'] ?? '')));
        $per = max(5, min(100, (int) ($f['perPage'] ?? 25)));
        $page = max(1, (int) ($f['page'] ?? 1));

        $rows = array_values(array_filter($records, function ($r) use ($status, $sample, $q) {
            if ($status !== 'all' && $r['group'] !== $status) {
                return false;
            }
            if ($sample === 'real' && $r['isSample']) {
                return false;
            }
            if ($sample === 'sample' && ! $r['isSample']) {
                return false;
            }
            if ($q !== '') {
                $hay = mb_strtolower(implode(' ', [$r['title'], $r['id'], $r['esoId'], (string) $r['decisionId'], $r['executor']['label'], $r['status'], (string) ($r['outcome']['feedback'] ?? '')]));

                return str_contains($hay, $q);
            }

            return true;
        }));

        return [
            'total' => count($rows),
            'page' => $page,
            'perPage' => $per,
            'lastPage' => max(1, (int) ceil(count($rows) / $per)),
            'data' => array_slice($rows, ($page - 1) * $per, $per),
        ];
    }

    /* ------------------------------------------------------------------ helpers */

    private function title(?array $rec, ?array $decision, ?array $eso): string
    {
        foreach ([$rec['title'] ?? null, $decision['explanation'] ?? null, $eso['name'] ?? null] as $t) {
            if (is_string($t) && trim($t) !== '') {
                return trim($t);
            }
        }

        return 'Action with no recorded description';
    }

    /** The original reason: the recommendation's "Why:" paragraph, else the decision's rationale. */
    private function reason(?array $rec, ?array $decision): ?string
    {
        $desc = (string) ($rec['description'] ?? '');
        if (preg_match('/^Why:\s*(.+?)(?:\n\n|$)/su', $desc, $m)) {
            return trim($m[1]);
        }
        $rationale = trim((string) ($decision['rationale'] ?? ''));

        return $rationale !== '' ? $rationale : null;
    }

    private function brief(array $r): array
    {
        return ['id' => $r['id'], 'title' => $r['title'], 'status' => $r['status'], 'group' => $r['group'], 'isSample' => $r['isSample'],
            'ageDays' => $r['ageDays'], 'createdAt' => $r['createdAt'], 'executor' => $r['executor']['label'], 'outcomeState' => $r['outcomeState']] + $r['attention'];
    }

    private function impactRow(array $r): array
    {
        return ['id' => $r['id'], 'title' => $r['title'], 'isSample' => $r['isSample'], 'outcomeState' => $r['outcomeState'],
            'result' => $r['outcome']['result'], 'feedback' => $r['outcome']['feedback'], 'measurement' => $r['outcome']['measurement'],
            'confidence' => $r['outcome']['confidence'], 'confidenceMeaning' => $r['outcome']['confidenceMeaning'],
            'recordedAt' => $r['outcome']['recordedAt'], 'recordedBy' => $r['outcome']['recordedBy'], 'evidenceCount' => $r['evidenceCount']];
    }

    private function slim(array $records): array
    {
        return array_map(fn ($r) => ['group' => $r['group'], 'outcomeState' => $r['outcomeState'], 'evidenceCount' => $r['evidenceCount']], $records);
    }

    private function rank(string $severity): int
    {
        return ['high' => 3, 'medium' => 2, 'low' => 1][$severity] ?? 0;
    }

    private function evidenceRow(array $e): array
    {
        return ['id' => (string) $e['id'], 'source' => $e['source'] ?? null, 'type' => $e['evidence_type'] ?? null,
            'summary' => $this->summarise($e['content'] ?? null),
            'confidence' => is_numeric($e['confidence'] ?? null) ? (float) $e['confidence'] : null, 'observedAt' => $e['observed_date'] ?? null];
    }

    /**
     * Evidence content is stored as JSON. Show it as readable "label: value" pairs rather than raw
     * braces; text that is not JSON is shown as it is. Only what is stored is shown - nothing added.
     */
    private function summarise(mixed $content): string
    {
        $text = trim((string) $content);
        $data = json_decode($text, true);
        if (is_array($data)) {
            if (isset($data['label']) && is_scalar($data['label'])) {
                $parts = [(string) $data['label'].(isset($data['value']) && is_scalar($data['value']) ? ': '.$data['value'] : '')];
                foreach (['note', 'module'] as $k) {
                    if (isset($data[$k]) && is_scalar($data[$k]) && $data[$k] !== '') {
                        $parts[] = (string) $data[$k];
                    }
                }
                $text = implode(' · ', $parts);
            } else {
                $pairs = [];
                foreach ($data as $k => $v) {
                    if (is_scalar($v) && $v !== '' && $v !== null) {
                        $pairs[] = ucfirst(preg_replace('/(?<!^)[A-Z]/', ' $0', (string) $k) ?? (string) $k).': '.$v;
                    }
                    if (count($pairs) >= 4) {
                        break;
                    }
                }
                $text = implode(' · ', $pairs);
            }
        }

        return mb_substr(trim(preg_replace('/\s+/u', ' ', $text) ?? ''), 0, 240);
    }

    /** @param  iterable<object>|array  $rows */
    private function keyed(iterable $rows, string $key): array
    {
        $out = [];
        foreach ($rows as $r) {
            $r = (array) $r;
            $out[(string) $r[$key]] = $r;
        }

        return $out;
    }

    /**
     * Names for numeric user ids, scoped to this institute. A non-numeric id (a seed marker, a system
     * actor) is not looked up, and an id with no row shows as "User #id" rather than a guessed name.
     *
     * @param  array<int,string>  $ids
     * @return array<string,string>
     */
    private function people(array $ids): array
    {
        $numeric = array_values(array_unique(array_filter(array_map('strval', $ids), fn ($i) => ctype_digit($i))));
        if ($numeric === [] || ! $this->has('tbluser')) {
            return [];
        }
        $out = [];
        foreach (DB::table('tbluser')->where('sub_institute_id', $this->tenantId)->whereIn('id', $numeric)->get(['id', 'first_name', 'last_name']) as $u) {
            $name = trim(($u->first_name ?? '').' '.($u->last_name ?? ''));
            if ($name !== '') {
                $out[(string) $u->id] = $name;
            }
        }

        return $out;
    }

    private function person(array $people, string $raw): string
    {
        if ($raw === '') {
            return 'Not recorded';
        }
        if ($raw === 'demo-seed') {
            return 'Sample data';
        }

        return $people[$raw] ?? (ctype_digit($raw) ? "User #{$raw}" : $raw);
    }

    private function unavailable(string $reason): array
    {
        return ['available' => false, 'reason' => $reason, 'tenantId' => $this->tenantId];
    }

    /** The definitions the screen shows, kept beside the rules that implement them. */
    public static function definitions(): array
    {
        return [
            'statuses' => [
                'queued' => 'A decision was approved and the action has not been reported as done.',
                'running' => 'The action has started and is not finished.',
                'completed' => 'Someone reported the action as carried out. This says it was done, not that it worked.',
                'failed' => 'The action, or its reported outcome, was a failure.',
                'blocked' => 'The action cannot proceed. No part of the application sets this status today, so it is expected to read zero.',
                'rolled_back' => 'The action was undone.',
            ],
            'verification' => [
                'verified' => 'Linked to its execution, with a before and after measurement, and evidence recorded against the execution.',
                'measured' => 'A before and after figure was reported, but no evidence is linked to the execution.',
                'reported' => 'Only the reporter\'s result and note. No measurement and no linked evidence.',
                'no_outcome' => 'The execution is finished but no outcome is recorded against it.',
                'failed' => 'The reported outcome was a failure.',
            ],
            'evidence' => [
                'finding' => 'Evidence behind the finding that led to this action. It explains why the action was proposed; it does not prove the action was carried out.',
                'execution' => 'Evidence a person has linked to show the action was carried out. None is created automatically.',
            ],
            'confidence' => 'Outcome confidence is set from the reported result (success 90%, partial 60%, failed 30%). It is not measured.',
        ];
    }
}
