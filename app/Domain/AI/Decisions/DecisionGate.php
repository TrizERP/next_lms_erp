<?php

namespace App\Domain\AI\Decisions;

use App\Domain\AI\Support\AiAuditLogger;
use App\Domain\Governance\EsoBindingRule;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * The human approval gate, as a durable record.
 *
 * The estate already had a confirmation mechanism — McpConfirmationService issues a
 * token, the user confirms, ToolRegistry consumes it. That is a good *transport*, but
 * it expires in ten minutes and is consumed on use, so it cannot answer "who approved
 * this intervention, and when" three months later. This class is the record that
 * survives; the confirmation token is stored alongside it as the evidence of how the
 * approval arrived.
 *
 * Approval does two things and no more: it writes a decision row, and it moves the
 * recommendation to `approved`. It does not execute anything. Execution is a separate
 * step that must call GovernanceValidator::authorizeExecute() and find this row.
 */
class DecisionGate
{
    public function __construct(
        private readonly AiAuditLogger $audit,
        private readonly EsoBindingRule $esoBinding,
    ) {
    }

    /**
     * Record a human approval.
     *
     * @throws RuntimeException when the recommendation cannot legitimately be approved
     * @return array{decision_id:int, recommendation_id:int, outcome_id:int|null}
     */
    public function approve(
        int $recommendationId,
        McpRequestContext $context,
        ?string $reason = null,
        array $modifications = [],
        ?string $confirmationToken = null,
        ?string $deciderName = null
    ): array {
        return $this->record(
            $recommendationId,
            'approved',
            $context,
            $reason,
            $modifications,
            $confirmationToken,
            $deciderName
        );
    }

    public function reject(
        int $recommendationId,
        McpRequestContext $context,
        ?string $reason = null,
        ?string $deciderName = null
    ): array {
        return $this->record(
            $recommendationId,
            'rejected',
            $context,
            $reason,
            [],
            null,
            $deciderName
        );
    }

    public function defer(
        int $recommendationId,
        McpRequestContext $context,
        ?string $reason = null,
        ?string $deciderName = null
    ): array {
        return $this->record($recommendationId, 'deferred', $context, $reason, [], null, $deciderName);
    }

    /**
     * The decision history for a recommendation.
     */
    public function historyFor(int $recommendationId, McpRequestContext $context): array
    {
        if (! Schema::hasTable('ai_decisions')) {
            return [];
        }

        return DB::table('ai_decisions')
            ->where('recommendation_id', $recommendationId)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->orderByDesc('decided_at')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'decision' => $row->decision,
                'reason' => $row->reason,
                'modifications' => $row->modifications ? json_decode($row->modifications, true) : [],
                'decided_by' => (int) $row->decided_by,
                'decided_by_name' => $row->decided_by_name,
                'decided_by_role' => $row->decided_by_role,
                'decided_at' => $row->decided_at,
            ])
            ->all();
    }

    // ---------------------------------------------------------------- internals

    private function record(
        int $recommendationId,
        string $decision,
        McpRequestContext $context,
        ?string $reason,
        array $modifications,
        ?string $confirmationToken,
        ?string $deciderName
    ): array {
        if (! Schema::hasTable('ai_decisions') || ! Schema::hasTable('ai_recommendations')) {
            throw new RuntimeException('The decision store is unavailable.');
        }

        // A decision must come from a real, identified person. A system caller with
        // no user id cannot approve on a human's behalf.
        if ($context->userId <= 0) {
            throw new RuntimeException('A decision requires an authenticated user.');
        }

        $recommendation = DB::table('ai_recommendations')
            ->where('id', $recommendationId)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->first();

        if (! $recommendation) {
            throw new RuntimeException('No such recommendation in your scope.');
        }

        if ($decision === 'approved') {
            $this->assertApprovable($recommendation);
        }

        $outcomeId = null;

        DB::beginTransaction();

        try {
            $decisionId = (int) DB::table('ai_decisions')->insertGetId([
                'recommendation_id' => $recommendationId,
                'case_id' => $recommendation->case_id,
                'decision' => $decision,
                'reason' => $reason,
                'modifications' => $modifications === [] ? null : json_encode($modifications),
                'decided_by' => $context->userId,
                'decided_by_role' => $context->role,
                'decided_by_name' => $deciderName ? mb_substr($deciderName, 0, 150) : null,
                'decided_at' => now(),
                'confirmation_token' => $confirmationToken,
                'ip_address' => request()?->ip(),
                'user_agent' => mb_substr((string) request()?->userAgent(), 0, 500),
                'sub_institute_id' => $context->selectedInstituteId,
                'client_id' => $context->clientId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('ai_recommendations')
                ->where('id', $recommendationId)
                ->update([
                    'status' => $this->statusFor($decision),
                    'updated_at' => now(),
                ]);

            if ($recommendation->case_id) {
                DB::table('ai_cases')
                    ->where('id', $recommendation->case_id)
                    ->update([
                        'status' => $decision === 'approved' ? 'approved' : ($decision === 'rejected' ? 'rejected' : 'awaiting_decision'),
                        'updated_at' => now(),
                    ]);
            }

            // Approving is what commits the platform to measuring the result, so the
            // outcome row is seeded here from the ESO binding that was validated at
            // draft time. Without this the learning loop never closes.
            if ($decision === 'approved') {
                $outcomeId = $this->seedOutcome($recommendation, $context);
            }

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        $this->audit->record(AiAuditLogger::DECISION_RECORDED, $context, [
            'actor_type' => 'user',
            'actor_label' => $deciderName,
            'related_type' => 'ai_recommendations',
            'related_id' => $recommendationId,
            'subject_entity_key' => $recommendation->subject_entity_key,
            'subject_id' => $recommendation->subject_id,
            'outcome' => $decision === 'approved' ? 'success' : 'rejected',
            'message' => sprintf('Recommendation %s: %s', $recommendation->recommendation_reference, $decision),
            'payload' => [
                'decision_id' => $decisionId,
                'decision' => $decision,
                'reason' => $reason,
                'had_modifications' => $modifications !== [],
                'outcome_id' => $outcomeId,
            ],
        ]);

        return [
            'decision_id' => $decisionId,
            'recommendation_id' => $recommendationId,
            'outcome_id' => $outcomeId,
        ];
    }

    private function assertApprovable(object $recommendation): void
    {
        if (! $recommendation->governance_passed) {
            throw new RuntimeException(
                'This recommendation did not pass governance validation and cannot be approved.'
            );
        }

        if (in_array($recommendation->status, ['rejected', 'superseded', 'expired'], true)) {
            throw new RuntimeException(
                sprintf('This recommendation is %s and can no longer be approved.', $recommendation->status)
            );
        }

        if ($recommendation->expires_at !== null && now()->greaterThan($recommendation->expires_at)) {
            throw new RuntimeException('This recommendation has expired.');
        }
    }

    private function statusFor(string $decision): string
    {
        return match ($decision) {
            'approved' => 'approved',
            'rejected' => 'rejected',
            default => 'pending_approval',
        };
    }

    /**
     * Create the pending outcome measurement from the recommendation's ESO binding.
     */
    private function seedOutcome(object $recommendation, McpRequestContext $context): ?int
    {
        if (! Schema::hasTable('ai_outcomes') || ! $recommendation->eso_binding) {
            return null;
        }

        $binding = json_decode($recommendation->eso_binding, true);

        if (! is_array($binding)) {
            return null;
        }

        $plan = $this->esoBinding->toOutcomePlan(
            $binding,
            $recommendation->subject_entity_key,
            $recommendation->subject_id
        );

        if (($plan['metric_key'] ?? '') === '') {
            return null;
        }

        return (int) DB::table('ai_outcomes')->insertGetId([
            'case_id' => $recommendation->case_id,
            'recommendation_id' => $recommendation->id,
            'subject_entity_key' => $plan['subject_entity_key'],
            'subject_id' => $plan['subject_id'],
            'metric_key' => $plan['metric_key'],
            'metric_label' => $plan['metric_label'] ? mb_substr((string) $plan['metric_label'], 0, 200) : null,
            'target_value' => $plan['target_value'],
            'status' => 'pending',
            'measure_after' => $plan['measure_after'],
            'detail' => json_encode($plan['detail']),
            'sub_institute_id' => $context->selectedInstituteId,
            'academic_year' => $context->academicYear,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
