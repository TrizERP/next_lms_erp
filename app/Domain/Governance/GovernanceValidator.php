<?php

namespace App\Domain\Governance;

use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The single entry point for governance.
 *
 * Everything that wants to act — an agent, a workflow step, a conversational tool —
 * asks here first. Centralising it means a new agent cannot forget a rule: the rules
 * are not in the agents, they are in front of them.
 *
 * The two checks that matter most:
 *
 *   authorizeVerb()   — is this actor licensed for this verb at all?
 *   authorizeExecute()— is there a real human Decision behind this action?
 *
 * `authorizeExecute` is the human approval gate. It does not consult confidence,
 * severity or agent configuration. It looks for a row in `ai_decisions` that says a
 * named person approved this exact recommendation, and refuses without one.
 */
class GovernanceValidator
{
    public const RULE_VERB_PERMITTED = 'governance.verb_permitted';

    public const RULE_DECISION_REQUIRED = 'governance.decision_required';

    public const RULE_DECISION_APPROVED = 'governance.decision_approved';

    public const RULE_DECISION_IN_SCOPE = 'governance.decision_in_scope';

    public const RULE_RECOMMENDATION_STATE = 'governance.recommendation_state';

    public const RULE_ROLE_PERMITTED = 'governance.role_permitted';

    public function __construct(
        private readonly ExplainVerb $explainVerb,
        private readonly RecommendVerb $recommendVerb,
        private readonly GroundedClaims $groundedClaims,
        private readonly EsoBindingRule $esoBindingRule,
    ) {
    }

    /**
     * May this actor perform this verb, given its ceiling?
     */
    public function authorizeVerb(Verb $requested, Verb $ceiling, ?string $actorLabel = null): GovernanceReport
    {
        if ($ceiling->permits($requested)) {
            return GovernanceReport::pass([self::RULE_VERB_PERMITTED], [], $requested);
        }

        return GovernanceReport::fail([[
            'rule' => self::RULE_VERB_PERMITTED,
            'message' => sprintf(
                '%s is licensed up to "%s" and may not "%s".',
                $actorLabel ?: 'This actor',
                $ceiling->value,
                $requested->value
            ),
            'context' => ['requested' => $requested->value, 'ceiling' => $ceiling->value],
        ]], [], [], $requested);
    }

    public function validateExplanation(
        array $draft,
        McpRequestContext $context,
        ?string $subjectEntityKey = null,
        int|string|null $subjectId = null
    ): GovernanceReport {
        return $this->explainVerb->validate(
            $draft,
            $context->selectedInstituteId,
            $subjectEntityKey,
            $subjectId
        );
    }

    public function validateRecommendation(
        array $draft,
        McpRequestContext $context,
        array $authorizedWorkflowKeys = []
    ): GovernanceReport {
        return $this->recommendVerb->validate(
            $draft,
            $context->selectedInstituteId,
            $authorizedWorkflowKeys
        );
    }

    /**
     * The human approval gate.
     *
     * Called immediately before anything consequential runs — a workflow starting, an
     * action step executing. Returns a failing report unless an approving decision
     * exists, in this tenant, for this recommendation, and the recommendation itself
     * is in an approved state.
     */
    public function authorizeExecute(int $recommendationId, McpRequestContext $context): GovernanceReport
    {
        if (! Schema::hasTable('ai_decisions') || ! Schema::hasTable('ai_recommendations')) {
            return GovernanceReport::fail([[
                'rule' => self::RULE_DECISION_REQUIRED,
                'message' => 'The decision record store is unavailable, so no consequential action may run.',
            ]], [], [], Verb::Execute);
        }

        $recommendation = DB::table('ai_recommendations')
            ->where('id', $recommendationId)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->first();

        if (! $recommendation) {
            return GovernanceReport::fail([[
                'rule' => self::RULE_DECISION_IN_SCOPE,
                'message' => 'No such recommendation in this school\'s scope.',
                'context' => ['recommendation_id' => $recommendationId],
            ]], [], [], Verb::Execute);
        }

        // A recommendation that never passed governance can never be executed, even
        // if somebody approved it — approval does not repair a broken draft.
        if (! $recommendation->governance_passed) {
            return GovernanceReport::fail([[
                'rule' => self::RULE_RECOMMENDATION_STATE,
                'message' => 'This recommendation did not pass governance validation and cannot be executed.',
                'context' => ['recommendation_id' => $recommendationId],
            ]], [], [], Verb::Execute);
        }

        if (! in_array($recommendation->status, ['approved', 'executed'], true)) {
            return GovernanceReport::fail([[
                'rule' => self::RULE_RECOMMENDATION_STATE,
                'message' => sprintf(
                    'This recommendation is "%s"; consequential actions need an approved recommendation.',
                    $recommendation->status
                ),
                'context' => ['recommendation_id' => $recommendationId, 'status' => $recommendation->status],
            ]], [], [], Verb::Execute);
        }

        $decision = DB::table('ai_decisions')
            ->where('recommendation_id', $recommendationId)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->orderByDesc('decided_at')
            ->first();

        if (! $decision) {
            return GovernanceReport::fail([[
                'rule' => self::RULE_DECISION_REQUIRED,
                'message' => 'No human decision exists for this recommendation. It cannot be executed.',
                'context' => ['recommendation_id' => $recommendationId],
            ]], [], [], Verb::Execute);
        }

        if ($decision->decision !== 'approved') {
            return GovernanceReport::fail([[
                'rule' => self::RULE_DECISION_APPROVED,
                'message' => sprintf('The latest decision on this recommendation was "%s".', $decision->decision),
                'context' => ['recommendation_id' => $recommendationId, 'decision' => $decision->decision],
            ]], [], [], Verb::Execute);
        }

        // A decision must have a real person behind it. A system-authored row is not
        // an approval, whatever it says.
        if ((int) $decision->decided_by <= 0) {
            return GovernanceReport::fail([[
                'rule' => self::RULE_DECISION_APPROVED,
                'message' => 'The decision on this recommendation has no human decider recorded.',
                'context' => ['recommendation_id' => $recommendationId],
            ]], [], [], Verb::Execute);
        }

        return GovernanceReport::pass([
            self::RULE_DECISION_REQUIRED,
            self::RULE_DECISION_APPROVED,
            self::RULE_DECISION_IN_SCOPE,
            self::RULE_RECOMMENDATION_STATE,
        ], [], Verb::Execute);
    }

    /**
     * Role check for a governed operation. Complements, never replaces, the
     * tenant scoping already applied by McpContextResolver.
     */
    public function authorizeRole(McpRequestContext $context, array $allowedRoles): GovernanceReport
    {
        if ($allowedRoles === [] || in_array($context->role, $allowedRoles, true)) {
            return GovernanceReport::pass([self::RULE_ROLE_PERMITTED]);
        }

        return GovernanceReport::fail([[
            'rule' => self::RULE_ROLE_PERMITTED,
            'message' => 'Your role does not permit this operation.',
            'context' => ['role' => $context->role, 'allowed' => $allowedRoles],
        ]]);
    }

    public function groundedClaims(): GroundedClaims
    {
        return $this->groundedClaims;
    }

    public function esoBinding(): EsoBindingRule
    {
        return $this->esoBindingRule;
    }

    public function explain(): ExplainVerb
    {
        return $this->explainVerb;
    }

    public function recommend(): RecommendVerb
    {
        return $this->recommendVerb;
    }
}
