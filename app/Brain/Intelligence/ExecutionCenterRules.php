<?php

namespace App\Brain\Intelligence;

/**
 * The classification rules behind the Execution Center. Pure: no database, no clock of its own.
 *
 * WHAT THE DATA MODEL ACTUALLY SAYS (audited against hpbrain_* before this was written)
 *
 *   recommendation -> decision (recommendation_id) -> execution (decision_id) -> outcome (decision_id)
 *
 *   - An execution is created `queued` when a decision is approved; `executionComplete` sets it to
 *     `completed` (result success/partial) or `failed` (result failed) and writes ONE outcome row
 *     whose `metrics.executionId` names it. Outcomes and executions therefore join on decision_id.
 *   - `status` has no `blocked` value anywhere in the code; it is counted if it ever appears.
 *   - Execution evidence lives in hpbrain_eso_execution_evidence. NOTHING in the application wrote to
 *     it, and `executionComplete` always wrote `evidence_ids = []`, so it has been empty everywhere.
 *   - `confidence` on an outcome is NOT a measurement: it is a constant picked from the reported
 *     result (success 0.9, partial 0.6, failed 0.3). It must be explained as that, not as a score.
 *   - Rows seeded for demonstration are marked: ids start `demo-`, creator `demo-seed`, text `[Sample]`.
 *
 * "COMPLETED" IS NOT "VERIFIED". An execution is completed when someone said so. An outcome is
 * verified only when it is linked to its execution AND carries a before/after measurement AND the
 * execution has recorded evidence. Anything less is reported honestly as what it is.
 */
final class ExecutionCenterRules
{
    /** A queued execution older than this is flagged as waiting too long. */
    public const STALE_QUEUED_DAYS = 7;

    public const CONFIDENCE_BY_RESULT = ['success' => 0.9, 'partial' => 0.6, 'failed' => 0.3];

    public const GROUPS = ['queued', 'running', 'completed', 'failed', 'blocked', 'rolled_back', 'other'];

    /** Outcome verification states, strongest last. */
    public const OUTCOME_STATES = ['not_applicable', 'no_outcome', 'failed', 'reported', 'measured', 'verified'];

    public static function statusGroup(?string $status): string
    {
        $s = strtolower(trim((string) $status));

        return match (true) {
            $s === 'queued' || $s === 'pending' => 'queued',
            in_array($s, ['running', 'in_progress', 'started'], true) => 'running',
            $s === 'completed' || $s === 'complete' || $s === 'done' => 'completed',
            in_array($s, ['failed', 'error'], true) => 'failed',
            $s === 'blocked' => 'blocked',
            in_array($s, ['rolled_back', 'rollback', 'rolledback'], true) => 'rolled_back',
            default => 'other',
        };
    }

    /** Seeded demonstration data, recognised by the markers the seeder writes. Never inferred from content. */
    public static function isSample(array $execution, ?array $decision = null, ?array $outcome = null): bool
    {
        $marked = static fn (mixed $v, string $prefix): bool => is_string($v) && str_starts_with($v, $prefix);

        return $marked($execution['id'] ?? null, 'demo-')
            || ($execution['executed_by'] ?? null) === 'demo-seed'
            || $marked($decision['id'] ?? null, 'demo-')
            || ($decision['decided_by'] ?? null) === 'demo-seed'
            || $marked($decision['rationale'] ?? null, '[Sample]')
            || $marked($outcome['id'] ?? null, 'demo-')
            || ($outcome['created_by'] ?? null) === 'demo-seed'
            || $marked($outcome['feedback'] ?? null, '[Sample]');
    }

    /** A before AND an after figure, recorded by the person who reported the outcome. */
    public static function measurement(?array $kpis): ?array
    {
        if (! is_array($kpis) || ! is_numeric($kpis['measuredBefore'] ?? null) || ! is_numeric($kpis['measuredAfter'] ?? null)) {
            return null;
        }

        return [
            'before' => (float) $kpis['measuredBefore'],
            'after' => (float) $kpis['measuredAfter'],
            'change' => is_numeric($kpis['measuredChange'] ?? null)
                ? (float) $kpis['measuredChange']
                : round((float) $kpis['measuredBefore'] - (float) $kpis['measuredAfter'], 2),
            'unit' => isset($kpis['unit']) && $kpis['unit'] !== '' ? (string) $kpis['unit'] : null,
            'basis' => isset($kpis['basis']) ? (string) $kpis['basis'] : null,
            'accountsAffected' => isset($kpis['accountsAffected']) && is_numeric($kpis['accountsAffected']) ? (int) $kpis['accountsAffected'] : null,
        ];
    }

    /**
     * @param  string  $group  statusGroup() of the execution
     * @param  bool  $hasOutcome  an outcome row joins to this execution
     * @param  string|null  $result  the outcome's reported result
     * @param  bool  $measured  the outcome carries a before/after measurement
     * @param  int  $evidenceCount  evidence recorded against the execution (link rows + outcome evidence ids)
     */
    public static function outcomeState(string $group, bool $hasOutcome, ?string $result, bool $measured, int $evidenceCount): string
    {
        if ($group !== 'completed' && $group !== 'failed') {
            return 'not_applicable';
        }
        if (! $hasOutcome) {
            return 'no_outcome';
        }
        if ($group === 'failed' || strtolower((string) $result) === 'failed') {
            return 'failed';
        }
        if ($measured && $evidenceCount > 0) {
            return 'verified';
        }

        return $measured ? 'measured' : 'reported';
    }

    /** What the outcome's confidence figure is, in words. Never "a score". */
    public static function confidenceMeaning(mixed $confidence, ?string $result, bool $sample): string
    {
        if (! is_numeric($confidence)) {
            return 'No confidence recorded.';
        }
        $pct = (int) round(((float) $confidence) * 100);
        if ($sample) {
            return "{$pct}% is a figure written into the sample record; it was not measured.";
        }
        $mapped = self::CONFIDENCE_BY_RESULT[strtolower((string) $result)] ?? null;
        if ($mapped !== null && abs($mapped - (float) $confidence) < 0.0001) {
            return "{$pct}% is a fixed value set from the reported result (\"{$result}\"): "
                .'success 90%, partial 60%, failed 30%. It reflects what the reporter said, not a measurement or a statistical probability.';
        }

        return "{$pct}% was recorded with this outcome; how it was set is not stated, so treat it as the reporter's judgement.";
    }

    /**
     * The one thing that needs doing for a record, or null when nothing does.
     *
     * @return array{kind:string,severity:string,reason:string,nextStep:string}|null
     */
    public static function attention(string $group, ?int $ageDays, string $outcomeState, int $evidenceCount, ?string $error): ?array
    {
        if ($group === 'failed') {
            return ['kind' => 'failed', 'severity' => 'high',
                'reason' => $error !== null && $error !== '' ? 'Failed: '.$error : 'The execution is recorded as failed.',
                'nextStep' => 'Review why it failed, then decide again on the recommendation or close it with a note.'];
        }
        if ($group === 'blocked') {
            return ['kind' => 'blocked', 'severity' => 'high', 'reason' => 'The execution is blocked.',
                'nextStep' => 'Find what is blocking it and clear it, or record why it cannot proceed.'];
        }
        if ($group === 'queued' || $group === 'running') {
            $stale = $ageDays !== null && $ageDays >= self::STALE_QUEUED_DAYS;

            return ['kind' => $stale ? 'stale_queue' : $group, 'severity' => $stale ? 'high' : 'low',
                'reason' => $ageDays === null
                    ? 'Approved but not yet completed.'
                    : 'Approved '.$ageDays.' day'.($ageDays === 1 ? '' : 's').' ago and not yet completed.',
                'nextStep' => 'Carry out the action, then report back with the result and, if you can, a before and after figure.'];
        }
        if ($outcomeState === 'no_outcome') {
            return ['kind' => 'outcome_missing', 'severity' => 'medium',
                'reason' => 'Marked completed, but no outcome is recorded against it.',
                'nextStep' => 'Report the outcome so the loop can learn from it.'];
        }
        if ($outcomeState === 'failed') {
            return ['kind' => 'outcome_failed', 'severity' => 'high', 'reason' => 'The reported outcome was a failure.',
                'nextStep' => 'Review what happened and whether the original problem is still open.'];
        }
        if (in_array($outcomeState, ['reported', 'measured'], true) && $evidenceCount === 0) {
            return ['kind' => 'evidence_missing', 'severity' => 'medium',
                'reason' => $outcomeState === 'measured'
                    ? 'A before and after figure was reported, but no evidence is linked to show the action was carried out.'
                    : 'The outcome is the reporter\'s word only: no measurement and no linked evidence.',
                'nextStep' => 'Link the evidence that shows what was done'.($outcomeState === 'reported' ? ', and record a before and after figure next time.' : '.')];
        }

        return null;
    }

    /**
     * Counts by status group, for a set of record summaries ({group}).
     *
     * @param  array<int,array{group:string}>  $records
     * @return array<string,int>
     */
    public static function healthCounts(array $records): array
    {
        $out = array_fill_keys(self::GROUPS, 0);
        $out['total'] = 0;
        foreach ($records as $r) {
            $out[$r['group']] = ($out[$r['group']] ?? 0) + 1;
            $out['total']++;
        }

        return $out;
    }

    /**
     * Verification tallies. Rates are only ever taken over COMPLETED executions that have a linked
     * outcome; an unlinked record is never counted as a verified one.
     *
     * @param  array<int,array{group:string,outcomeState:string,evidenceCount:int}>  $records
     * @return array<string,int>
     */
    public static function verificationCounts(array $records): array
    {
        $out = ['finished' => 0, 'withOutcome' => 0, 'noOutcome' => 0, 'failedOutcome' => 0, 'reported' => 0, 'measured' => 0, 'verified' => 0, 'withEvidence' => 0, 'withoutEvidence' => 0];
        foreach ($records as $r) {
            if (! in_array($r['group'], ['completed', 'failed'], true)) {
                continue;
            }
            $out['finished']++;
            $out[$r['evidenceCount'] > 0 ? 'withEvidence' : 'withoutEvidence']++;
            $key = ['no_outcome' => 'noOutcome', 'failed' => 'failedOutcome', 'reported' => 'reported', 'measured' => 'measured', 'verified' => 'verified'][$r['outcomeState']] ?? null;
            if ($key !== null) {
                $out[$key]++;
                if ($key !== 'noOutcome') {
                    $out['withOutcome']++;
                }
            }
        }

        return $out;
    }

    /** Whole days between an ISO/SQL timestamp and now; null when the timestamp is unusable. */
    public static function ageDays(?string $timestamp, \DateTimeInterface $now): ?int
    {
        if ($timestamp === null || trim($timestamp) === '') {
            return null;
        }
        try {
            $then = new \DateTimeImmutable($timestamp);
        } catch (\Throwable) {
            return null;
        }

        return max(0, (int) floor(($now->getTimestamp() - $then->getTimestamp()) / 86400));
    }
}
