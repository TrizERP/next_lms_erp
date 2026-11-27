<?php

namespace App\Services\Platform\Fees;

use App\Services\Platform\AuditTrail;
use Illuminate\Support\Facades\DB;

/**
 * The "approved but money hasn't moved yet" half of the concession/refund
 * request feature — see `fees_concession_grants`'s own migration docblock
 * for why this is a table rather than reusing `platform_workflow_runs`.
 *
 * A run can reach `approved` three different ways: through
 * FeesConcessionRequestService::act() (this feature's own wrapper), through
 * the already-existing generic `/platform-services/workflow/runs` approve
 * button (EngineController::actOnRun — shared by every module, not changed
 * by this feature), or through WorkflowEngine::escalateOverdue()'s
 * `auto_approve` SLA action, which involves no user action at all. Only the
 * first of those three calls into this class directly, so recording a grant
 * must also happen on read (`reconcile()`), not only on the write path —
 * `recordFromRun()` is therefore idempotent (`insertOrIgnore` on the unique
 * `run_id`), safe to call as many times as the run is seen.
 */
class FeesConcessionGrantService
{
    public const FLOWS = [
        'fees.concession.flow' => 'concession',
        'fees.refund.flow' => 'refund',
    ];

    /**
     * Picks up every approved, non-sample Fees-flow run for this tenant that
     * has no grant row yet. Call before reading the queue/grant state so a
     * run approved through the generic runs page or an SLA auto-approve is
     * never missed.
     */
    public function reconcile(int $tenantId): int
    {
        $missing = DB::table('platform_workflow_runs as r')
            ->leftJoin('fees_concession_grants as g', 'g.run_id', '=', 'r.id')
            ->where('r.sub_institute_id', $tenantId)
            ->where('r.status', 'approved')
            ->where('r.is_sample', 0)
            ->whereIn('r.flow_key', array_keys(self::FLOWS))
            ->whereNull('g.id')
            ->pluck('r.id');

        $recorded = 0;
        foreach ($missing as $runId) {
            if ($this->recordFromRun($tenantId, (int) $runId) !== null) {
                $recorded++;
            }
        }

        return $recorded;
    }

    /**
     * @return array<string, mixed>|null  null when the run isn't an approved
     *   Fees-flow run, or its payload carried no amount to record against
     *   (e.g. a run started through the generic startRun endpoint rather
     *   than this feature's own submit — nothing invented, nothing applied).
     */
    public function recordFromRun(int $tenantId, int $runId): ?array
    {
        $existing = DB::table('fees_concession_grants')->where('run_id', $runId)->first();
        if ($existing !== null) {
            return (array) $existing;
        }

        $run = DB::table('platform_workflow_runs')
            ->where('sub_institute_id', $tenantId)
            ->where('id', $runId)
            ->where('status', 'approved')
            ->where('is_sample', 0)
            ->first();

        if ($run === null || ! isset(self::FLOWS[$run->flow_key])) {
            return null;
        }

        $payload = json_decode((string) $run->payload, true) ?: [];
        $amount = is_numeric($payload['amount'] ?? null) ? (float) $payload['amount'] : null;
        $studentId = is_numeric($payload['student_id'] ?? null) ? (int) $payload['student_id'] : null;

        if ($amount === null || $studentId === null) {
            return null;
        }

        $lastApproved = DB::table('platform_workflow_run_steps')
            ->where('run_id', $runId)->where('status', 'approved')->orderByDesc('step_order')->first();

        $id = DB::table('fees_concession_grants')->insertOrIgnore([
            'sub_institute_id' => $tenantId,
            'run_id' => $runId,
            'flow_key' => $run->flow_key,
            'kind' => self::FLOWS[$run->flow_key],
            'student_id' => $studentId,
            'syear' => is_numeric($payload['syear'] ?? null) ? (int) $payload['syear'] : null,
            'amount' => $amount,
            'applied_amount' => 0,
            'refund_heads' => isset($payload['refund_heads']) ? json_encode($payload['refund_heads']) : null,
            'status' => 'approved_pending_application',
            'approved_at' => $run->completed_at ?? now(),
            'approved_by_name' => $lastApproved?->acted_by_name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($id) {
            AuditTrail::record($tenantId, 'fees', self::FLOWS[$run->flow_key], 'grant_recorded', [
                'entity_type' => 'student', 'entity_id' => $studentId,
                'after' => ['run_id' => $runId, 'amount' => $amount],
            ]);
        }

        return (array) DB::table('fees_concession_grants')->where('run_id', $runId)->first();
    }

    /**
     * @throws ConcessionGrantNotApplicable
     */
    public function assertApplicable(int $tenantId, int $grantId, int $studentId, string $kind): object
    {
        $grant = DB::table('fees_concession_grants')
            ->where('id', $grantId)->where('sub_institute_id', $tenantId)->lockForUpdate()->first();

        if ($grant === null) {
            throw new ConcessionGrantNotApplicable('That approved request was not found for this institute.');
        }
        if ((int) $grant->student_id !== $studentId) {
            throw new ConcessionGrantNotApplicable('That approved request belongs to a different student.');
        }
        if ($grant->kind !== $kind) {
            throw new ConcessionGrantNotApplicable("That approved request is a {$grant->kind}, not a {$kind}.");
        }
        if ($grant->status === 'applied') {
            throw new ConcessionGrantNotApplicable('That request has already been fully applied.');
        }

        return $grant;
    }

    /**
     * Records money actually moved against an approved grant. Must be
     * called from inside the caller's own receipt/refund transaction, so a
     * failed receipt and a recorded application never disagree.
     *
     * @param  array<string, mixed>  $reference
     * @throws ConcessionGrantNotApplicable
     */
    public function recordApplication(int $tenantId, int $grantId, int $studentId, string $kind, float $applied, array $reference, ?int $userId): array
    {
        $grant = $this->assertApplicable($tenantId, $grantId, $studentId, $kind);

        if ($applied <= 0) {
            throw new ConcessionGrantNotApplicable('Nothing was recorded against this request.');
        }

        $remaining = round((float) $grant->amount - (float) $grant->applied_amount, 2);
        if ($applied > $remaining + 0.01) {
            throw new ConcessionGrantNotApplicable(sprintf('That would apply ₹%s but only ₹%s of this request remains.', number_format($applied, 2), number_format($remaining, 2)));
        }

        $newApplied = round((float) $grant->applied_amount + $applied, 2);
        $existingRefs = $grant->applied_reference !== null ? (json_decode((string) $grant->applied_reference, true) ?: []) : [];
        $existingRefs[] = $reference + ['amount' => $applied, 'at' => now()->toIso8601String()];

        $becomesApplied = $newApplied >= (float) $grant->amount - 0.01;

        DB::table('fees_concession_grants')->where('id', $grantId)->update([
            'applied_amount' => $newApplied,
            'status' => $becomesApplied ? 'applied' : 'approved_pending_application',
            'applied_at' => $becomesApplied ? now() : $grant->applied_at,
            'applied_by' => $userId,
            'applied_reference' => json_encode($existingRefs),
            'updated_at' => now(),
        ]);

        AuditTrail::record($tenantId, 'fees', $kind, 'grant_applied', [
            'entity_type' => 'student', 'entity_id' => $studentId,
            'after' => ['grant_id' => $grantId, 'applied' => $applied, 'status' => $becomesApplied ? 'applied' : 'approved_pending_application'],
        ]);

        return (array) DB::table('fees_concession_grants')->where('id', $grantId)->first();
    }
}
