<?php

namespace App\Agents\Fees;

use App\Domain\AI\Agents\Agent;
use App\Domain\AI\Agents\AgentContext;
use App\Domain\AI\Fees\KnowledgeBase\FeesKnowledgeBaseService;
use App\Domain\AI\Signals\DetectedSignal;
use App\Domain\K12\Fees\FeeArrearsDetector;
use App\Services\AI\Fees\FeesPromptService;
use Throwable;

/**
 * The Fees agent: who owes money, why that is the figure, and what to do about it.
 *
 * WHAT CHANGED AND WHY
 *
 * The first version of this class answered a question and stopped. It resolved a prompt,
 * summarised a policy and returned them — useful, but it meant stages 8 and 10 to 12 of
 * the lifecycle were never reached for Fees. Evidence, recommendation, approval and
 * action all hang off a *case*, and only an agent opens one. So a fee question could be
 * answered but never acted on, and the trace said `recommendation · not_reached` for
 * every question a school could ask.
 *
 * It also carried a query that could not run: `fees_breackoff.balance` and
 * `fees_breackoff.student_id` do not exist — that table holds the fee structure per
 * grade, not a per-student ledger — so the first real invocation would have raised a SQL
 * error. That query is gone. Nothing here computes what is owed; see FeeArrearsDetector
 * for why that matters.
 *
 * THE SHAPE IS THE ACADEMIC-RISK AGENT'S, DELIBERATELY
 *
 * detect → record signals → open a case → explain with cited evidence → draft a
 * recommendation bound to a workflow. That sequence is not reinvented here: it is the
 * one `AcademicRiskAgent` already proves, and following it means a fee case is read,
 * approved and audited by exactly the machinery that already reads, approves and audits
 * an academic one. A school learns one set of screens, not two.
 *
 * THE ADVISORY HALF IS KEPT, BUT DEMOTED
 *
 * The prompt resolution and knowledge-base summary this class did before still run and
 * still appear under the same keys, so anything reading `prompt` or `knowledge_base`
 * keeps working — the case pipeline is added beside them, not in place of them. What
 * changed is that they can no longer fail the run: both are wrapped, and a failure is
 * reported under `advisory_errors` instead of thrown. An enrichment that cannot be
 * computed is a missing field, not a reason to tell a school its fee analysis failed.
 */
class FeesAgent implements Agent
{
    private const CASE_TYPE = 'fee_collection';

    private const WORKFLOW_KEY = 'fees_collection';

    public function __construct(
        private readonly FeesPromptService $prompts,
        private readonly FeesKnowledgeBaseService $knowledgeBase,
        private readonly FeeArrearsDetector $detector,
    ) {
    }

    public function run(AgentContext $context): array
    {
        $input = $context->input;
        $scope = $context->scope;
        $studentId = isset($input['student_id']) ? (int) $input['student_id'] : null;
        $instituteId = $scope->selectedInstituteId;
        $syear = $scope->academicYear ?? (int) date('Y');
        $intent = (string) ($input['intent'] ?? 'pending_fees');

        // ---- The advisory half, exactly as before ---------------------------
        //
        // Isolated because it is the half that can fail. `FeesPromptService` queries
        // `fees_breackoff.student_id`, `.balance` and `.paid_amount` — none of which
        // exist on that table — so it raises a SQL error the moment it is reached. That
        // was invisible while this agent had no manifest and never ran; registering the
        // manifest made it the first thing to happen and it took the whole run down with
        // it, reporting `status: failed` for a school that simply has arrears to find.
        //
        // A prompt helper is an enrichment. It must not be able to stop the agent
        // detecting, opening a case and drafting a recommendation — so its failure is
        // caught and reported in the result rather than thrown, and the analysis runs
        // regardless. Fixing that service's schema is a separate job; when it is fixed
        // this keeps working unchanged.
        $advisory = null;
        $advisoryError = null;

        try {
            $advisory = $this->advisoryFor($intent, $studentId, $instituteId, $syear, $input);
        } catch (Throwable $exception) {
            $advisoryError = $exception->getMessage();
        }

        $policySummary = null;
        $policyError = null;

        try {
            $policySummary = $this->knowledgeBase->summaryForStudent(
                $studentId ?? 0,
                $instituteId,
                $syear
            );
        } catch (Throwable $exception) {
            $policyError = $exception->getMessage();
        }

        $advisoryErrors = array_filter([
            'prompt' => $advisoryError,
            'knowledge_base' => $policyError,
        ]);

        // ---- Detect ---------------------------------------------------------
        //
        // Scoped to the named student when the question was about one, and to the
        // cohort filters otherwise. Both go through the same arrears engine the Fees
        // screen uses, so the agent cannot report a figure a clerk cannot reproduce.
        $arguments = array_filter([
            'standard_id' => $input['standard_id'] ?? null,
            'section_id' => $input['section_id'] ?? null,
            'min_amount' => $input['min_amount'] ?? null,
            'limit' => $input['limit'] ?? null,
        ], static fn ($value) => $value !== null);

        $detected = $this->detector->detect($context, $arguments);
        $coverage = $this->detector->coverage($context->scope, $arguments);

        if ($studentId !== null) {
            $detected = array_values(array_filter(
                $detected,
                static fn (DetectedSignal $signal) => (int) $signal->subjectId === $studentId
            ));
        }

        if ($detected === []) {
            return $this->result($intent, $studentId, $instituteId, $syear, $advisory, $policySummary, [
                'students_with_arrears' => 0,
                'signals_detected' => 0,
                'cases' => [],
                'coverage' => $coverage,
                'confidence' => 1.0,
                'message' => $this->nothingFoundMessage($coverage, $studentId),
            ], $advisoryErrors);
        }

        // ---- One case per student -------------------------------------------
        //
        // A case is about a person, not a figure. A cohort sweep that stopped at a
        // ranked list of names would leave the rest of the journey with nothing to stand
        // on: no case means no evidence to cite, no recommendation to draft, and no
        // approval to ask for.
        $byStudent = [];

        foreach ($detected as $signal) {
            $byStudent[(int) $signal->subjectId][] = $signal;
        }

        $cases = [];

        foreach ($byStudent as $subjectId => $signals) {
            $case = $this->buildCaseForStudent($context, (int) $subjectId, $signals);

            if ($case !== null) {
                $cases[] = $case;
            }
        }

        // Largest debt first — the order a fees clerk should work the list in.
        usort($cases, static fn ($a, $b) => ($b['outstanding'] ?? 0) <=> ($a['outstanding'] ?? 0));

        return $this->result($intent, $studentId, $instituteId, $syear, $advisory, $policySummary, [
            'students_with_arrears' => count($cases),
            'signals_detected' => count($detected),
            'total_outstanding' => round(array_sum(array_column($cases, 'outstanding')), 2),
            'cases' => $cases,
            'coverage' => $coverage,
            'confidence' => 1.0,
            'mode' => 'deep_analysis',
        ], $advisoryErrors);
    }

    public function summarize(array $result): string
    {
        $count = (int) ($result['students_with_arrears'] ?? 0);
        $coverage = $result['coverage'] ?? [];

        if ($count === 0) {
            return (string) ($result['message'] ?? 'No fee arrears were found in the current scope.');
        }

        return sprintf(
            'Found %d student%s carrying %s in unpaid fees across %d of %d students checked, and opened a case for each.',
            $count,
            $count === 1 ? '' : 's',
            '₹' . number_format((float) ($result['total_outstanding'] ?? 0), 2),
            (int) ($coverage['checked'] ?? 0),
            (int) ($coverage['cohort'] ?? 0)
        );
    }

    // ---------------------------------------------------------------- internals

    /**
     * @param  array<int, DetectedSignal>  $signals
     * @return array<string, mixed>|null
     */
    private function buildCaseForStudent(AgentContext $context, int $studentId, array $signals): ?array
    {
        $stored = $context->recordSignals($signals);

        $caseId = $context->openCase(
            self::CASE_TYPE,
            $signals,
            $stored['signal_ids'],
            $stored['evidence_ids']
        );

        if ($caseId === null) {
            // Recorded, but below the bar for a case. A legitimate outcome — a ₹50
            // balance is a signal worth trending and not worth a teacher's attention.
            return null;
        }

        $signal = $signals[0];
        $name = $signal->subjectLabel ?? ('Student #' . $studentId);
        $outstanding = (float) ($signal->components['outstanding'] ?? 0);

        $context->addHypothesis(
            $caseId,
            sprintf('%s has an unpaid fee balance of %s.', $name, $this->money($outstanding)),
            'Raised from the arrears figure the fee ledger returns for this student, not from an estimate.',
            $stored['evidence_ids'],
            [],
            1.0
        );

        $explanation = $context->explain(
            $caseId,
            $this->buildClaims($context, $signals, $stored['evidence_ids'], $studentId),
            'teacher',
            'student',
            $studentId,
            $name
        );

        $recommendation = null;

        if ($explanation['governance']->passed) {
            $recommendation = $this->draftRecommendation(
                $context,
                $caseId,
                $explanation,
                $studentId,
                $name,
                $signal,
                $stored['evidence_ids']
            );
        }

        return [
            'case_id' => $caseId,
            'student_id' => $studentId,
            'student_name' => $name,
            'enrollment_no' => $signal->components['enrollment_no'] ?? null,
            'standard_name' => $signal->components['standard_name'] ?? null,
            'outstanding' => round($outstanding, 2),
            'pending_items' => (int) ($signal->components['pending_items'] ?? 0),
            'severity' => $signal->severity,
            'signals' => array_map(static fn (DetectedSignal $item) => $item->toArray(), $signals),
            'explanation' => [
                'narrative' => $explanation['narrative'],
                'governance_passed' => $explanation['governance']->passed,
                'reason_refused' => $explanation['governance']->passed ? null : $explanation['governance']->reason(),
            ],
            'recommendation' => $recommendation,
        ];
    }

    /**
     * Each claim cites the evidence that supports it — a claim with nothing to cite is
     * not made at all.
     *
     * @param  array<int, DetectedSignal>  $signals
     * @param  array<int, int>  $evidenceIds
     * @return array<int, array<string, mixed>>
     */
    private function buildClaims(AgentContext $context, array $signals, array $evidenceIds, int $studentId): array
    {
        // Only verified, in-scope evidence may be cited, so read back what was stored
        // rather than assuming every id is citable.
        $citable = collect($context->evidenceFor('student', $studentId, null, 100))
            ->whereIn('id', $evidenceIds)
            ->where('verified', true)
            ->pluck('id')
            ->all();

        if ($citable === []) {
            return [];
        }

        $claims = [];

        foreach ($signals as $signal) {
            $components = $signal->components;
            $outstanding = (float) ($components['outstanding'] ?? 0);
            $items = (int) ($components['pending_items'] ?? 0);
            $label = $signal->subjectLabel ?? ('Student #' . $signal->subjectId);

            $claims[] = [
                'claim' => sprintf(
                    '%s has %s outstanding across %d fee head%s, which the fee ledger classes as %s.',
                    $label,
                    $this->money($outstanding),
                    $items,
                    $items === 1 ? '' : 's',
                    $signal->severity
                ),
                'evidence_ids' => $citable,
                'confidence' => $signal->confidence,
            ];
        }

        return $claims;
    }

    /**
     * @param  array<int, int>  $evidenceIds
     * @return array<string, mixed>
     */
    private function draftRecommendation(
        AgentContext $context,
        int $caseId,
        array $explanation,
        int $studentId,
        string $name,
        DetectedSignal $signal,
        array $evidenceIds
    ): array {
        $outstanding = (float) ($signal->components['outstanding'] ?? 0);

        $draft = [
            'case_id' => $caseId,
            'explanation_id' => $explanation['id'],
            'domain' => 'k12',
            'action_type' => 'start_fees_collection_review',
            'title' => sprintf('Review the unpaid fees for %s (%s)', $name, $this->money($outstanding)),
            'body' => $explanation['narrative'],
            'rationale' => 'Drafted from the arrears figure the fee ledger returns for this student and the '
                . 'fee heads behind it.',
            'subject_entity_key' => 'student',
            'subject_id' => $studentId,
            'confidence' => $signal->confidence ?? 1.0,
            // Capped by what can actually be cited, not set by the amount alone.
            //
            // `RecommendVerb` requires 3 distinct pieces of verified evidence for a
            // high-risk recommendation, 2 for medium, 1 for low — and pending fees are
            // held per month here, so a student owing six figures across a single month
            // yields exactly one citable row. Claiming 'high' on that got the whole
            // recommendation refused, which left a real debt with no approval request
            // attached to it.
            //
            // Inflating the evidence to clear the bar would have been the wrong repair:
            // the bar is there to stop a serious action resting on a single data point.
            // So the severity says how big the debt is, and the risk level says how well
            // it is evidenced — the recommendation never claims more than it can defend,
            // and the amount still reaches the reader through the title and the case.
            'risk_level' => $this->riskLevel($signal->severity, count($evidenceIds)),
            // Consequential, and that is the point of it.
            //
            // `RecommendVerb::normalizeForPersistence()` files a non-consequential
            // recommendation as `draft` and only a consequential one as
            // `pending_approval` — so marking this false left a proposal to chase a
            // family for six figures sitting in a table nobody is asked to look at,
            // and stages 11 and 12 permanently out of reach.
            //
            // The workflow itself moves no money. What makes this consequential is
            // what a person does after approving it: a family gets contacted about a
            // debt. That decision belongs to a named human being in an approval
            // queue, never to a sweep that ran overnight.
            'is_consequential' => true,
            'evidence_ids' => $evidenceIds,
            // What success would look like, which governance requires before anything
            // can be approved: a recommendation that cannot say what it is trying to
            // achieve cannot be measured afterwards, so it is refused rather than
            // approved on trust.
            'eso_binding' => [
                'objective' => sprintf('Clear the %s outstanding on %s\'s fee account.', $this->money($outstanding), $name),
                'strategy' => 'Review the unpaid heads with the family and agree a payment or a concession '
                    . 'through the fees collection workflow.',
                'outcome' => [
                    'metric_key' => 'fees_outstanding_balance',
                    'metric_label' => 'Outstanding fee balance (INR)',
                    'direction' => 'decrease',
                    'baseline' => round($outstanding, 2),
                    'target' => 0,
                    'horizon_days' => $signal->severity === 'critical' ? 14 : 30,
                ],
            ],
            'workflow_key' => self::WORKFLOW_KEY,
            'workflow_payload' => [
                'student_id' => $studentId,
                'student_name' => $name,
                'case_id' => $caseId,
                'outstanding' => round($outstanding, 2),
                'pending_items' => (int) ($signal->components['pending_items'] ?? 0),
                'severity' => $signal->severity,
                'enrollment_no' => $signal->components['enrollment_no'] ?? null,
                'standard_name' => $signal->components['standard_name'] ?? null,
            ],
        ];

        $result = $context->recommend($draft);

        return [
            'id' => $result['id'],
            'status' => $result['status'],
            'governance_passed' => $result['governance']->passed,
            'reason_refused' => $result['governance']->passed ? null : $result['governance']->reason(),
            'title' => $draft['title'],
            'requires_approval' => true,
            'workflow_key' => self::WORKFLOW_KEY,
        ];
    }

    /**
     * The prompt half this class has always resolved. Unchanged.
     *
     * @param  array<string, mixed>  $input
     * @return mixed
     */
    private function advisoryFor(string $intent, ?int $studentId, int|string|null $instituteId, ?int $syear, array $input)
    {
        return match ($intent) {
            'fee_details' => $this->prompts->feeDetailsPrompt([
                'student_id' => $studentId,
                'sub_institute_id' => $instituteId,
                'syear' => $syear,
            ]),
            'pending_fees_report' => $this->prompts->pendingFeesReportPrompt([
                'sub_institute_id' => $instituteId,
                'syear' => $syear,
                'standard_id' => $input['standard_id'] ?? null,
                'section_id' => $input['section_id'] ?? null,
            ]),
            'fee_summary' => $this->prompts->feeSummaryPrompt([
                'sub_institute_id' => $instituteId,
                'syear' => $syear,
            ]),
            'fee_reminder' => $this->prompts->feeReminderPrompt([
                'sub_institute_id' => $instituteId,
                'syear' => $syear,
                'student_id' => $studentId,
            ]),
            'fee_status_explanation' => $this->prompts->feeStatusExplanationPrompt([
                'student_id' => $studentId,
                'sub_institute_id' => $instituteId,
                'syear' => $syear,
            ]),
            'collection_workflow' => $this->prompts->collectionWorkflowPrompt([
                'sub_institute_id' => $instituteId,
                'syear' => $syear,
                'student_id' => $studentId,
            ]),
            default => $this->prompts->pendingFeesPrompt([
                'student_id' => $studentId,
                'sub_institute_id' => $instituteId,
                'syear' => $syear,
            ]),
        };
    }

    /**
     * @param  array{checked:int, cohort:int, complete:bool, failed:int}  $coverage
     */
    private function nothingFoundMessage(array $coverage, ?int $studentId): string
    {
        if ($studentId !== null) {
            return sprintf('Student #%d has no unpaid fees in this academic year.', $studentId);
        }

        if ($coverage['failed'] > 0 && $coverage['failed'] >= $coverage['checked']) {
            return 'No student could be checked — every arrears lookup failed. Nothing is known '
                . 'either way about who owes money.';
        }

        return $coverage['complete']
            ? 'No student in this scope has an unpaid fee balance.'
            : sprintf(
                'No unpaid fees were found among the %d of %d students this sweep read.',
                $coverage['checked'],
                $coverage['cohort']
            );
    }

    /**
     * @param  array<string, mixed>  $findings
     * @return array<string, mixed>
     */
    private function result(
        string $intent,
        ?int $studentId,
        int|string|null $instituteId,
        ?int $syear,
        mixed $advisory,
        mixed $policySummary,
        array $findings,
        array $advisoryErrors = []
    ): array {
        return array_merge($findings, array_filter([
            'intent' => $intent,
            'student_id' => $studentId,
            'sub_institute_id' => $instituteId,
            'syear' => $syear,
            'prompt' => $advisory,
            'knowledge_base' => $policySummary,
            // Present only when an enrichment failed, so a reader can tell a missing
            // prompt from a prompt that was never asked for.
            'advisory_errors' => $advisoryErrors === [] ? null : $advisoryErrors,
        ], static fn ($value) => $value !== null));
    }

    /**
     * The risk level a recommendation may claim, given how much it can cite.
     *
     * Severity is about the size of the debt; risk level is about how defensible acting
     * on it is. They are allowed to differ, and when the ledger holds one row they must.
     */
    private function riskLevel(string $severity, int $evidenceCount): string
    {
        $claimed = match ($severity) {
            'critical' => 'high',
            'high' => 'medium',
            default => 'low',
        };

        $supported = match (true) {
            $evidenceCount >= 3 => 'high',
            $evidenceCount === 2 => 'medium',
            default => 'low',
        };

        $rank = ['low' => 1, 'medium' => 2, 'high' => 3];

        return $rank[$claimed] <= $rank[$supported] ? $claimed : $supported;
    }

    private function money(float $amount): string
    {
        return '₹' . number_format($amount, 2);
    }
}
