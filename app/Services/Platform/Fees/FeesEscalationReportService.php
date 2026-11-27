<?php

namespace App\Services\Platform\Fees;

use App\Models\Platform\PlatformWorkflow;
use Illuminate\Support\Facades\DB;

/**
 * Reports the Escalation Rule card honestly: the ₹5,000/₹25,000 policy text
 * next to what the approval engine actually does today.
 *
 * This is report-only, by design (not this service's choice — a confirmed
 * project decision). `WorkflowEngine::start()` always runs the tenant's
 * newest active chain in its configured, fixed step order; it does not skip
 * a step because a request's amount falls under a threshold, and this class
 * does not change that. It only classifies real pending/approved requests
 * against the rule and says where engine behaviour and policy disagree.
 */
class FeesEscalationReportService
{
    private const FLOW_KEY = 'fees.concession.flow';

    /**
     * Value-based escalation rule:
     * - Amount <= ₹5,000: Accounts/Clerk can approve directly.
     * - ₹5,001 to ₹25,000: Standard Principal approval.
     * - Amount > ₹25,000: Escalates directly to Trustee / Management.
     *
     * Moved here unchanged from FeesIntelligenceService::determineConcessionEscalation() —
     * this is the approval-chain POLICY, applied to whatever real amount is
     * passed in. It is reported against reality below, never enforced by
     * rewriting the engine's own step order.
     *
     * @return array{amount: float, required_approver_role: string, target_step_name: string, is_escalated_to_trustee: bool, reason: string}
     */
    public function classify(float $amount): array
    {
        if ($amount > 25000) {
            return [
                'amount' => $amount,
                'required_approver_role' => 'Trustee',
                'target_step_name' => 'Trustee / Management Special Sign-off',
                'tier' => 'trustee',
                'is_escalated_to_trustee' => true,
                'reason' => 'Concession amount (₹'.number_format($amount).') exceeds threshold (₹25,000). Direct Trustee approval required.',
            ];
        }

        if ($amount <= 5000) {
            return [
                'amount' => $amount,
                'required_approver_role' => 'Accounts',
                'target_step_name' => 'Clerk / Accounts Level Approval',
                'tier' => 'accounts',
                'is_escalated_to_trustee' => false,
                'reason' => 'Concession amount (₹'.number_format($amount).') is within standard threshold (<= ₹5,000). Accounts clearance is sufficient.',
            ];
        }

        return [
            'amount' => $amount,
            'required_approver_role' => 'Principal',
            'target_step_name' => 'Principal Approval',
            'tier' => 'principal',
            'is_escalated_to_trustee' => false,
            'reason' => 'Standard mid-tier concession approval flow applies.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function report(int $tenantId): array
    {
        $chains = PlatformWorkflow::forTenant($tenantId)
            ->where('flow_key', self::FLOW_KEY)
            ->orderByDesc('id')
            ->get();

        $rule = [
            'accounts_max' => 5000,
            'principal_max' => 25000,
            'enforced' => false,
            'basis' => 'Display policy only — the active chain below runs its configured step order regardless of amount.',
        ];

        if ($chains->isEmpty()) {
            return [
                'available' => true,
                'rule' => $rule,
                'chains' => [],
                'matrix' => [],
                'findings' => [[
                    'code' => 'no_active_chain',
                    'count' => 0,
                    'message' => 'No approval chain is configured for fee concessions yet — the queue cannot accept requests until one is activated.',
                    'run_ids' => [],
                ]],
            ];
        }

        $active = $chains->firstWhere('status', 'active');
        $engineChoiceId = $active?->id;

        $chainRows = $chains->map(fn (PlatformWorkflow $chain) => [
            'id' => $chain->id,
            'name' => $chain->name,
            'status' => $chain->status,
            'condition' => $chain->condition,
            'engine_choice' => $chain->id === $engineChoiceId,
            'steps' => array_map(
                fn (array $step, int $i) => [
                    'order' => $i + 1,
                    'name' => $step['name'] ?? ('Step '.($i + 1)),
                    'approver_type' => $step['approver_type'] ?? 'role',
                    'approver' => $step['approver'] ?? '',
                    'tier' => $this->tierForStep($step['approver_type'] ?? '', $step['name'] ?? '', $step['approver'] ?? ''),
                ],
                array_values((array) $chain->steps),
                array_keys(array_values((array) $chain->steps))
            ),
        ])->values()->all();

        $findings = [];
        if ($chains->count() > 1 && $chains->where('status', 'active')->count() > 0) {
            $findings[] = [
                'code' => 'multiple_active_chains',
                'count' => $chains->count(),
                'message' => sprintf(
                    '%d chain(s) exist for fee concessions; the engine always runs the newest active one (id %d) and ignores any per-chain condition text.',
                    $chains->count(),
                    $engineChoiceId ?? 0
                ),
                'run_ids' => [],
            ];
        }

        if ($active === null) {
            $findings[] = [
                'code' => 'no_active_chain',
                'count' => 0,
                'message' => 'No chain for fee concessions is currently active — requests cannot be submitted.',
                'run_ids' => [],
            ];

            return [
                'available' => true,
                'rule' => $rule,
                'chains' => $chainRows,
                'matrix' => [],
                'findings' => $findings,
            ];
        }

        $activeSteps = array_values((array) $active->steps);
        $stepTiers = array_map(
            fn (array $step) => $this->tierForStep($step['approver_type'] ?? '', $step['name'] ?? '', $step['approver'] ?? ''),
            $activeSteps
        );

        [$matrix, $moreFindings] = $this->pendingMatrix($tenantId, $active->id, $activeSteps, $stepTiers);
        $findings = array_merge($findings, $moreFindings);
        $findings = array_merge($findings, $this->approvedWithoutRequiredTier($tenantId));

        return [
            'available' => true,
            'rule' => $rule,
            'chains' => $chainRows,
            'matrix' => $matrix,
            'findings' => $findings,
        ];
    }

    /**
     * Which tier a configured step actually represents, by its declared
     * approver_type first (principal is its own type), then by label text.
     * Anything that matches neither is `unmapped` and reported as such
     * rather than guessed.
     */
    public function tierForStep(string $approverType, string $name, string $approver): string
    {
        if ($approverType === 'principal') {
            return 'principal';
        }

        $label = $name.' '.$approver;
        if (preg_match('/trustee|management/i', $label)) {
            return 'trustee';
        }
        if (preg_match('/principal/i', $label)) {
            return 'principal';
        }
        if (preg_match('/account|clerk/i', $label)) {
            return 'accounts';
        }

        return 'unmapped';
    }

    /**
     * Cross-tabs real pending requests' current step tier against the tier
     * the ₹-amount rule says they should be at. `required_tier_missing`
     * covers a request whose amount needs a tier the active chain simply
     * doesn't have a step for.
     *
     * @param  array<int, array<string, mixed>>  $activeSteps
     * @param  array<int, string>  $stepTiers
     * @return array{0: array<string, array<string, int>>, 1: list<array<string, mixed>>}
     */
    private function pendingMatrix(int $tenantId, int $workflowId, array $activeSteps, array $stepTiers): array
    {
        $runs = DB::table('platform_workflow_runs')
            ->where('sub_institute_id', $tenantId)
            ->where('workflow_id', $workflowId)
            ->where('flow_key', self::FLOW_KEY)
            ->where('status', 'pending')
            ->where('is_sample', 0)
            ->get();

        $matrix = [];
        $overRouted = [];
        $missingTier = [];

        foreach ($runs as $run) {
            $payload = json_decode((string) $run->payload, true) ?: [];
            $amount = is_numeric($payload['amount'] ?? null) ? (float) $payload['amount'] : null;
            $requiredTier = $amount !== null ? $this->classify($amount)['tier'] : 'no_amount';

            $currentIndex = max(0, ($run->current_step ?? 1) - 1);
            $currentTier = $stepTiers[$currentIndex] ?? 'unmapped';

            $matrix[$currentTier] ??= [];
            $matrix[$currentTier][$requiredTier] = ($matrix[$currentTier][$requiredTier] ?? 0) + 1;

            if ($requiredTier !== 'no_amount' && ! in_array($requiredTier, $stepTiers, true)) {
                $missingTier[] = $run->id;
            } elseif ($requiredTier !== 'no_amount') {
                $requiredIndex = array_search($requiredTier, $stepTiers, true);
                if ($requiredIndex !== false && $currentIndex > $requiredIndex) {
                    $overRouted[] = $run->id;
                }
            }
        }

        $findings = [];
        if ($overRouted !== []) {
            $findings[] = [
                'code' => 'over_routed_pending',
                'count' => count($overRouted),
                'message' => sprintf('%d pending request(s) are waiting at a step above the tier the ₹-amount rule requires for them.', count($overRouted)),
                'run_ids' => $overRouted,
            ];
        }
        if ($missingTier !== []) {
            $findings[] = [
                'code' => 'required_tier_missing',
                'count' => count($missingTier),
                'message' => sprintf('%d pending request(s) need a tier the active chain has no step for.', count($missingTier)),
                'run_ids' => $missingTier,
            ];
        }

        $autoApproved = DB::table('platform_workflow_run_steps as s')
            ->join('platform_workflow_runs as r', 'r.id', '=', 's.run_id')
            ->where('r.sub_institute_id', $tenantId)
            ->where('r.flow_key', self::FLOW_KEY)
            ->where('s.acted_by_name', 'Auto-approved after SLA')
            ->count();
        if ($autoApproved > 0) {
            $findings[] = [
                'code' => 'auto_approved_steps',
                'count' => $autoApproved,
                'message' => sprintf('%d step(s) were auto-approved after their SLA breached, without a human decision.', $autoApproved),
                'run_ids' => [],
            ];
        }

        return [$matrix, $findings];
    }

    /**
     * Approved runs this academic cycle whose own step history has no
     * *approved* step at the tier their amount required — e.g. a
     * ₹30,000 concession that never actually passed through a step this
     * chain maps to "trustee".
     *
     * @return list<array<string, mixed>>
     */
    private function approvedWithoutRequiredTier(int $tenantId): array
    {
        $runs = DB::table('platform_workflow_runs')
            ->where('sub_institute_id', $tenantId)
            ->where('flow_key', self::FLOW_KEY)
            ->where('status', 'approved')
            ->where('is_sample', 0)
            ->where('created_at', '>=', now()->subYear())
            ->get(['id', 'payload', 'workflow_id']);

        if ($runs->isEmpty()) {
            return [];
        }

        $chainsById = PlatformWorkflow::forTenant($tenantId)->whereIn('id', $runs->pluck('workflow_id')->unique())->get()->keyBy('id');
        $violating = [];

        foreach ($runs as $run) {
            $chain = $chainsById->get($run->workflow_id);
            if ($chain === null) {
                continue;
            }
            $payload = json_decode((string) $run->payload, true) ?: [];
            $amount = is_numeric($payload['amount'] ?? null) ? (float) $payload['amount'] : null;
            if ($amount === null) {
                continue;
            }
            $requiredTier = $this->classify($amount)['tier'];

            $approvedSteps = DB::table('platform_workflow_run_steps')
                ->where('run_id', $run->id)->where('status', 'approved')->get(['step_name', 'approver']);

            $hasRequiredTier = $approvedSteps->contains(
                fn ($step) => $this->tierForStep('', (string) $step->step_name, (string) $step->approver) === $requiredTier
            );

            if (! $hasRequiredTier) {
                $violating[] = $run->id;
            }
        }

        if ($violating === []) {
            return [];
        }

        return [[
            'code' => 'approved_without_required_tier',
            'count' => count($violating),
            'message' => sprintf('%d request(s) were approved this year with no approved step at the tier their amount required.', count($violating)),
            'run_ids' => $violating,
        ]];
    }
}
