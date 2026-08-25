<?php

namespace App\Domain\Governance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The recommend verb.
 *
 * Drafting an action is the last thing the system may do on its own. This class is
 * the gate between analysis and consequence, and it enforces five things before a
 * recommendation is allowed to exist as anything but a rejected draft:
 *
 *  1. it belongs to a case (nothing is recommended out of nowhere)
 *  2. it cites evidence, and enough of it
 *  3. it clears the confidence floor for its risk level
 *  4. it binds to a measurable expected outcome (EsoBindingRule)
 *  5. if it is consequential, it demands human approval — and cannot opt out
 *
 * Rule 5 is deliberately not configurable. A caller can raise the bar by declaring a
 * recommendation consequential, but nothing in the payload can lower it: a
 * consequential recommendation always ends up `requires_approval = true` and
 * `status = pending_approval`, never `approved`.
 *
 * Authored for K-12 from the platform's stated governance semantics. The G2G
 * people-competency project holds the original of this verb; reconcile when available.
 */
class RecommendVerb
{
    public const RULE_CASE_REQUIRED = 'recommend.case_required';

    public const RULE_EVIDENCE_REQUIRED = 'recommend.evidence_required';

    public const RULE_EVIDENCE_SUFFICIENT = 'recommend.evidence_sufficient';

    public const RULE_CONFIDENCE_FLOOR = 'recommend.confidence_floor';

    public const RULE_APPROVAL_REQUIRED = 'recommend.approval_required';

    public const RULE_ACTION_TYPE_KNOWN = 'recommend.action_type_known';

    public const RULE_WORKFLOW_AUTHORIZED = 'recommend.workflow_authorized';

    /** Confidence floors by risk level. A high-risk draft has to be surer of itself. */
    private const CONFIDENCE_FLOOR = [
        'low' => 0.40,
        'medium' => 0.55,
        'high' => 0.70,
    ];

    /** Minimum distinct pieces of evidence, by risk level. */
    private const MIN_EVIDENCE = [
        'low' => 1,
        'medium' => 2,
        'high' => 3,
    ];

    public function __construct(
        private readonly GroundedClaims $groundedClaims,
        private readonly EsoBindingRule $esoBinding,
    ) {
    }

    /**
     * Validate a recommendation draft before it is written.
     *
     * @param  array{
     *   case_id?:int|null, action_type?:string, evidence_ids?:array, confidence?:float|null,
     *   risk_level?:string, is_consequential?:bool, requires_approval?:bool,
     *   eso_binding?:array|null, workflow_key?:string|null, subject_entity_key?:string,
     *   subject_id?:int|string
     * }  $draft
     * @param  array<int, string>  $authorizedWorkflowKeys  Workflows the actor may bind to
     */
    public function validate(
        array $draft,
        int $subInstituteId,
        array $authorizedWorkflowKeys = []
    ): GovernanceReport {
        $violations = [];
        $warnings = [];
        $passed = [];

        $riskLevel = strtolower((string) ($draft['risk_level'] ?? 'low'));
        if (! array_key_exists($riskLevel, self::CONFIDENCE_FLOOR)) {
            $riskLevel = 'low';
        }

        // 1. A recommendation must descend from a case.
        $caseId = $draft['case_id'] ?? null;
        if (! $caseId) {
            $violations[] = [
                'rule' => self::RULE_CASE_REQUIRED,
                'message' => 'A recommendation must belong to a case, so its reasoning can be inspected.',
            ];
        } else {
            $passed[] = self::RULE_CASE_REQUIRED;
        }

        // 2 & 3. Evidence must exist, be in scope, and be plentiful enough.
        $evidenceIds = $this->normalizeIds($draft['evidence_ids'] ?? []);

        if ($evidenceIds === []) {
            $violations[] = [
                'rule' => self::RULE_EVIDENCE_REQUIRED,
                'message' => 'A recommendation must cite the evidence it rests on.',
            ];
        } else {
            $usable = $this->countUsableEvidence(
                $evidenceIds,
                $subInstituteId,
                $draft['subject_entity_key'] ?? null,
                $draft['subject_id'] ?? null
            );

            $required = self::MIN_EVIDENCE[$riskLevel];

            if ($usable < $required) {
                $violations[] = [
                    'rule' => self::RULE_EVIDENCE_SUFFICIENT,
                    'message' => sprintf(
                        'A %s-risk recommendation needs at least %d pieces of verified, in-scope evidence; %d were usable.',
                        $riskLevel,
                        $required,
                        $usable
                    ),
                    'context' => ['usable' => $usable, 'required' => $required, 'cited' => count($evidenceIds)],
                ];
            } else {
                $passed[] = self::RULE_EVIDENCE_REQUIRED;
                $passed[] = self::RULE_EVIDENCE_SUFFICIENT;
            }
        }

        // 4. Confidence floor.
        $confidence = $draft['confidence'] ?? null;
        $floor = self::CONFIDENCE_FLOOR[$riskLevel];

        if ($confidence === null) {
            $violations[] = [
                'rule' => self::RULE_CONFIDENCE_FLOOR,
                'message' => 'A recommendation must state its confidence.',
            ];
        } elseif ((float) $confidence < $floor) {
            $violations[] = [
                'rule' => self::RULE_CONFIDENCE_FLOOR,
                'message' => sprintf(
                    'Confidence %.2f is below the %.2f floor for a %s-risk recommendation.',
                    (float) $confidence,
                    $floor,
                    $riskLevel
                ),
                'context' => ['confidence' => (float) $confidence, 'floor' => $floor],
            ];
        } else {
            $passed[] = self::RULE_CONFIDENCE_FLOOR;
        }

        // 5. Action type must be named.
        $actionType = trim((string) ($draft['action_type'] ?? ''));
        if ($actionType === '') {
            $violations[] = [
                'rule' => self::RULE_ACTION_TYPE_KNOWN,
                'message' => 'A recommendation must name the action it proposes.',
            ];
        } else {
            $passed[] = self::RULE_ACTION_TYPE_KNOWN;
        }

        // 6. ESO binding.
        $esoReport = $this->esoBinding->validate($draft['eso_binding'] ?? null);
        $violations = array_merge($violations, $esoReport->violations);
        $warnings = array_merge($warnings, $esoReport->warnings);
        $passed = array_merge($passed, $esoReport->passedRules);

        // 7. The approval gate. Consequential means human-approved, full stop.
        $isConsequential = (bool) ($draft['is_consequential'] ?? true);

        if ($isConsequential && ($draft['requires_approval'] ?? true) === false) {
            $violations[] = [
                'rule' => self::RULE_APPROVAL_REQUIRED,
                'message' => 'A consequential recommendation cannot waive human approval.',
            ];
        } else {
            $passed[] = self::RULE_APPROVAL_REQUIRED;
        }

        // 8. A bound workflow must be one the actor is licensed for.
        $workflowKey = $draft['workflow_key'] ?? null;

        if ($workflowKey) {
            if ($authorizedWorkflowKeys !== [] && ! in_array($workflowKey, $authorizedWorkflowKeys, true)) {
                $violations[] = [
                    'rule' => self::RULE_WORKFLOW_AUTHORIZED,
                    'message' => sprintf('Workflow "%s" is not authorised for this agent.', $workflowKey),
                    'context' => ['workflow_key' => $workflowKey],
                ];
            } else {
                $passed[] = self::RULE_WORKFLOW_AUTHORIZED;
            }
        }

        return $violations === []
            ? GovernanceReport::pass(array_values(array_unique($passed)), $warnings, Verb::Recommend)
            : GovernanceReport::fail($violations, array_values(array_unique($passed)), $warnings, Verb::Recommend);
    }

    /**
     * Normalise a draft into the shape `ai_recommendations` stores, forcing the
     * approval fields regardless of what the caller supplied.
     */
    public function normalizeForPersistence(array $draft, GovernanceReport $report): array
    {
        $isConsequential = (bool) ($draft['is_consequential'] ?? true);

        return [
            'verb' => Verb::Recommend->value,
            'is_consequential' => $isConsequential,
            // Never lowered by the payload.
            'requires_approval' => $isConsequential ? true : (bool) ($draft['requires_approval'] ?? true),
            'governance_passed' => $report->passed,
            'governance_report' => $report->toJson(),
            'status' => $report->passed
                ? ($isConsequential ? 'pending_approval' : 'draft')
                : 'draft',
            'evidence_ids' => json_encode($this->normalizeIds($draft['evidence_ids'] ?? [])),
        ];
    }

    /**
     * How many cited pieces of evidence are actually usable: present, in this tenant,
     * about this subject, verified, and not generated.
     */
    private function countUsableEvidence(
        array $evidenceIds,
        int $subInstituteId,
        ?string $subjectEntityKey,
        int|string|null $subjectId
    ): int {
        if (! Schema::hasTable('ai_evidence')) {
            return 0;
        }

        $query = DB::table('ai_evidence')
            ->whereIn('id', $evidenceIds)
            ->where('sub_institute_id', $subInstituteId)
            ->where('verified', true)
            ->where('is_generated', false);

        if ($subjectEntityKey !== null) {
            $query->where('subject_entity_key', $subjectEntityKey);
        }

        if ($subjectId !== null) {
            $query->where('subject_id', $subjectId);
        }

        return (int) $query->count();
    }

    private function normalizeIds(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($id) => is_numeric($id) ? (int) $id : null,
            $value
        ), static fn ($id) => $id !== null && $id > 0)));
    }
}
