<?php

namespace App\Agents\Admissions;

use App\Domain\AI\Agents\Agent;
use App\Domain\AI\Agents\AgentContext;
use App\Domain\AI\Signals\DetectedSignal;
use App\Domain\Admissions\Risk\StalledEnquiryDetector;

/**
 * The Admissions agent: which families the school said it would call back, and has not.
 *
 * THE SHAPE IS THE ATTENDANCE AGENT'S, DELIBERATELY
 *
 * detect → record signals → open a case → explain with cited evidence → draft a
 * recommendation bound to a workflow. That sequence is not reinvented here: it is the one
 * `FeesAgent`, `AttendanceAgent` and `AcademicRiskAgent` already prove, and following it
 * means an admissions case is read, approved and audited by exactly the machinery that
 * already reads, approves and audits an attendance one. A school learns one set of
 * screens, not four.
 *
 * NOTHING HERE READS AN ENQUIRY
 *
 * Every fact comes from `StalledEnquiryDetector`, which in turn asks `AdmissionMcpService`
 * — the same service behind the `admissions.listEnquiries` and
 * `admissions.validateConfirmation` tools the chat and the Admission screens use. This
 * class chooses what to say about those facts and never what they are. If the record says
 * a follow-up was due on 3 August, that is the only date this agent can utter.
 *
 * WHAT IT MAY DO, AND WHAT IT MAY NOT
 *
 * `max_verb = recommend` and `may_execute_actions = 0` on the manifest. The agent may
 * detect, analyse, explain and recommend. It may not contact a family, edit an enquiry, or
 * confirm an admission. Confirming an admission is a consequential write that already has
 * its own human gate on `admissions.confirm`, and it is deliberately absent from this
 * agent's tool list — the only thing it can bind a recommendation to is the
 * `admissions_followup` workflow and its approval step.
 *
 * "NOTHING FOUND" IS NOT THE SAME AS "NOTHING KNOWN"
 *
 * A school that records no follow-up dates produces exactly the same empty list as a
 * school that calls every family on time. The coverage figures are carried through every
 * result and into the summary for that reason, and the agent says which of the two it is
 * looking at rather than letting a reader assume the flattering one.
 */
class AdmissionsAgent implements Agent
{
    private const CASE_TYPE = 'admission_follow_up';

    private const WORKFLOW_KEY = 'admissions_followup';

    public function __construct(
        private readonly StalledEnquiryDetector $detector,
    ) {
    }

    public function run(AgentContext $context): array
    {
        $input = $context->input;
        $scope = $context->scope;
        $enquiryId = isset($input['enquiry_id']) ? (int) $input['enquiry_id'] : null;

        // `subject_id` is what AgentController's own validation accepts, and it is how a
        // run launched from the AI Stack or an enquiry page names the record. Treating the
        // two as one means a run is scoped the same however it arrived.
        if ($enquiryId === null && isset($input['subject_id'])) {
            $enquiryId = (int) $input['subject_id'];
        }

        $arguments = array_filter([
            'enquiry_id' => $enquiryId,
            'search_text' => $input['search_text'] ?? null,
            'overdue_days' => $input['overdue_days'] ?? null,
            'limit' => $input['limit'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');

        $detected = $this->detector->detect($scope, $arguments);
        $coverage = $this->detector->coverage($scope, $arguments);
        $floor = $this->detector->minimumOverdueDays($arguments);

        if ($detected === []) {
            return $this->result($enquiryId, $scope->selectedInstituteId, $scope->academicYear, $floor, [
                'enquiries_overdue' => 0,
                'signals_detected' => 0,
                'cases' => [],
                'coverage' => $coverage,
                'confidence' => 1.0,
                'message' => $this->nothingFoundMessage($coverage, $enquiryId, $floor),
            ]);
        }

        // One case per enquiry: a case is about a family's application, not a count. A
        // sweep that stopped at a ranked list would leave the rest of the journey with
        // nothing to stand on — no case means no evidence to cite, no recommendation to
        // draft and no approval to ask for.
        $byEnquiry = [];

        foreach ($detected as $signal) {
            $byEnquiry[(int) $signal->subjectId][] = $signal;
        }

        $cases = [];

        foreach ($byEnquiry as $subjectId => $signals) {
            $case = $this->buildCaseForEnquiry($context, (int) $subjectId, $signals, $coverage);

            if ($case !== null) {
                $cases[] = $case;
            }
        }

        // Longest overdue first — the order an admissions officer should work the list in.
        usort($cases, static fn ($a, $b) => ($b['overdue_days'] ?? 0) <=> ($a['overdue_days'] ?? 0));

        return $this->result($enquiryId, $scope->selectedInstituteId, $scope->academicYear, $floor, [
            'enquiries_overdue' => count($cases),
            'signals_detected' => count($detected),
            'longest_overdue_days' => $cases === [] ? null : $cases[0]['overdue_days'],
            'open_enquiries' => $coverage['open'],
            'cases' => $cases,
            'coverage' => $coverage,
            'confidence' => 1.0,
            'mode' => 'deep_analysis',
        ]);
    }

    public function summarize(array $result): string
    {
        $count = (int) ($result['enquiries_overdue'] ?? 0);
        $coverage = is_array($result['coverage'] ?? null) ? $result['coverage'] : [];

        if ($count === 0) {
            return (string) ($result['message'] ?? 'No open enquiry in this scope is past its follow-up date.');
        }

        return sprintf(
            'Found %d enquiry(ies) past the follow-up date the school recorded for them, among the %d of %d '
            . 'open enquiries that have a follow-up date at all, and opened a case for each.',
            $count,
            (int) ($coverage['scheduled'] ?? 0),
            (int) ($coverage['open'] ?? 0)
        );
    }

    // ---------------------------------------------------------------- internals

    /**
     * @param  array<int, DetectedSignal>  $signals
     * @param  array<string, mixed>  $coverage
     * @return array<string, mixed>|null
     */
    private function buildCaseForEnquiry(
        AgentContext $context,
        int $enquiryId,
        array $signals,
        array $coverage
    ): ?array {
        $stored = $context->recordSignals($signals);

        $caseId = $context->openCase(
            self::CASE_TYPE,
            $signals,
            $stored['signal_ids'],
            $stored['evidence_ids']
        );

        if ($caseId === null) {
            // Recorded, but below the bar for a case. A legitimate outcome — a follow-up
            // two days late is worth trending and not worth interrupting anybody for.
            return null;
        }

        $signal = $signals[0];
        $name = $signal->subjectLabel ?? ('Enquiry #' . $enquiryId);
        $overdueDays = (int) ($signal->components['overdue_days'] ?? 0);
        $followupDate = (string) ($signal->components['followup_date'] ?? '');
        $status = (string) ($signal->components['status'] ?? 'new');
        $enquiryNo = (string) ($signal->components['enquiry_no'] ?? '');

        $context->addHypothesis(
            $caseId,
            sprintf(
                '%s was due a follow-up on %s and has been waiting %d day%s.',
                $name,
                $followupDate !== '' ? $followupDate : 'a date the record holds',
                $overdueDays,
                $overdueDays === 1 ? '' : 's'
            ),
            'Raised from the follow-up date the school itself recorded on this enquiry, not from an '
                . 'estimate of how old the enquiry is.',
            $stored['evidence_ids'],
            [],
            $signal->confidence ?? 0.9
        );

        $explanation = $context->explain(
            $caseId,
            $this->buildClaims($context, $signals, $stored['evidence_ids'], $enquiryId),
            // `admin`, not `staff`. `ExplanationBuilder` validates the audience against a
            // closed list — teacher, admin, parent, student — and refuses the whole
            // explanation for anything else, which silently costs the case its
            // recommendation and its approval. An admissions officer is an administrator on
            // that list; the workflow separately requires the `staff` ROLE to approve, and
            // the two words are not the same field.
            'admin',
            'enquiry',
            $enquiryId,
            $name
        );

        $recommendation = null;

        if ($explanation['governance']->passed) {
            $recommendation = $this->draftRecommendation(
                $context,
                $caseId,
                $explanation,
                $enquiryId,
                $name,
                $signal,
                $stored['evidence_ids'],
                $coverage
            );
        }

        return [
            'case_id' => $caseId,
            'enquiry_id' => $enquiryId,
            'enquiry_no' => $enquiryNo,
            'student_name' => $name,
            'standard_name' => $signal->components['standard_name'] ?? null,
            'status' => $status,
            'followup_date' => $followupDate === '' ? null : $followupDate,
            'overdue_days' => $overdueDays,
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
     * Each claim cites the evidence that supports it — a claim with nothing to cite is not
     * made at all.
     *
     * @param  array<int, DetectedSignal>  $signals
     * @param  array<int, int>  $evidenceIds
     * @return array<int, array<string, mixed>>
     */
    private function buildClaims(AgentContext $context, array $signals, array $evidenceIds, int $enquiryId): array
    {
        // Only verified, in-scope evidence may be cited, so read back what was stored
        // rather than assuming every id is citable.
        $citable = collect($context->evidenceFor('enquiry', $enquiryId, null, 100))
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
            $overdueDays = (int) ($components['overdue_days'] ?? 0);
            $followupDate = (string) ($components['followup_date'] ?? '');
            $status = (string) ($components['status'] ?? 'new');
            $label = $signal->subjectLabel ?? ('Enquiry #' . $signal->subjectId);

            $claims[] = [
                'claim' => sprintf(
                    '%s was due a follow-up on %s, %d day%s ago, and the enquiry is still recorded as "%s", '
                    . 'which the school classes as %s.',
                    $label,
                    $followupDate !== '' ? $followupDate : 'the recorded date',
                    $overdueDays,
                    $overdueDays === 1 ? '' : 's',
                    $status,
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
     * @param  array<string, mixed>  $coverage
     * @return array<string, mixed>
     */
    private function draftRecommendation(
        AgentContext $context,
        int $caseId,
        array $explanation,
        int $enquiryId,
        string $name,
        DetectedSignal $signal,
        array $evidenceIds,
        array $coverage
    ): array {
        $overdueDays = (int) ($signal->components['overdue_days'] ?? 0);
        $followupDate = (string) ($signal->components['followup_date'] ?? '');
        $enquiryNo = (string) ($signal->components['enquiry_no'] ?? '');

        $draft = [
            'case_id' => $caseId,
            'explanation_id' => $explanation['id'],
            'domain' => 'k12',
            'action_type' => 'start_admission_follow_up',
            'title' => sprintf(
                'Follow up %s\'s admission enquiry%s (%d day%s past the recorded date)',
                $name,
                $enquiryNo !== '' ? ' ' . $enquiryNo : '',
                $overdueDays,
                $overdueDays === 1 ? '' : 's'
            ),
            'body' => $explanation['narrative'],
            'rationale' => 'Drafted from the follow-up date the school recorded on this enquiry and what the '
                . 'confirmation check says the record is still missing.',
            'subject_entity_key' => 'enquiry',
            'subject_id' => $enquiryId,
            'confidence' => $signal->confidence ?? 0.9,
            // Capped by what can actually be cited, not by how late the call is.
            //
            // `RecommendVerb` requires three distinct pieces of verified evidence for a
            // high-risk recommendation, two for medium and one for low. An enquiry whose
            // readiness check could not be read yields one citable row, and claiming
            // 'high' on that would get the whole recommendation refused — leaving a real
            // unanswered family with no approval request attached to them. Inflating the
            // evidence to clear the bar would be the wrong repair: the bar exists to stop
            // a consequential action resting on one data point. So severity says how late
            // the school is, and risk level says how well that is evidenced.
            'risk_level' => $this->riskLevel($signal->severity, count($evidenceIds)),
            // Consequential, and that is the point of it.
            //
            // `RecommendVerb::normalizeForPersistence()` files a non-consequential
            // recommendation as `draft` and only a consequential one as
            // `pending_approval`. What makes this consequential is what a person does
            // after approving it: a family is contacted about a place at the school, and
            // nothing in the record says whether one is available or whether the family
            // still wants it. That conversation belongs to a named human being, never to a
            // sweep that ran overnight.
            'is_consequential' => true,
            'evidence_ids' => $evidenceIds,
            // What success would look like, which governance requires before anything can
            // be approved: a recommendation that cannot say what it is trying to achieve
            // cannot be measured afterwards, so it is refused rather than approved on
            // trust. The metric is the overdue interval itself, which is read from the same
            // column the detector read — so the outcome can actually be checked later.
            'eso_binding' => [
                'objective' => sprintf('Answer %s\'s enquiry and bring it back onto its follow-up schedule.', $name),
                'strategy' => 'Contact the family about the enquiry, record what they decide, and set the next '
                    . 'follow-up date — or close the enquiry if they no longer want a place — through the '
                    . 'admission follow-up workflow.',
                'outcome' => [
                    'metric_key' => 'admission_followup_overdue_days',
                    'metric_label' => 'Days past the recorded follow-up date',
                    'direction' => 'decrease',
                    'baseline' => $overdueDays,
                    'target_value' => 0,
                    'horizon_days' => $signal->severity === 'critical' ? 7 : 14,
                ],
            ],
            'workflow_key' => self::WORKFLOW_KEY,
            'workflow_payload' => [
                'enquiry_id' => $enquiryId,
                'enquiry_no' => $enquiryNo,
                'student_name' => $name,
                'case_id' => $caseId,
                'followup_date' => $followupDate === '' ? null : $followupDate,
                'overdue_days' => $overdueDays,
                'status' => $signal->components['status'] ?? null,
                'standard_name' => $signal->components['standard_name'] ?? null,
                'open_enquiries' => (int) ($coverage['open'] ?? 0),
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
     * @param  array<string, mixed>  $coverage
     */
    private function nothingFoundMessage(array $coverage, ?int $enquiryId, int $floor): string
    {
        $open = (int) ($coverage['open'] ?? 0);
        $scheduled = (int) ($coverage['scheduled'] ?? 0);

        if ($open === 0) {
            return (int) ($coverage['read'] ?? 0) === 0
                ? 'No admission enquiries were returned for this institute and academic year, so nothing is '
                    . 'known either way about the pipeline.'
                : 'Every enquiry read for this institute is already approved, converted, closed or cancelled, '
                    . 'so none is waiting on a follow-up.';
        }

        if ($scheduled === 0) {
            return sprintf(
                'None of the %d open enquiries has a follow-up date recorded, so none can be judged late. '
                . 'That is a gap in the record rather than a finding about the pipeline.',
                $open
            );
        }

        if ($enquiryId !== null) {
            return sprintf(
                'Enquiry #%d is not more than %d day%s past the follow-up date recorded on it.',
                $enquiryId,
                $floor,
                $floor === 1 ? '' : 's'
            );
        }

        return $coverage['complete'] === true
            ? 'No open enquiry is past the follow-up date the school recorded for it.'
            : sprintf(
                'No overdue follow-up was found among the %d of %d open enquiries that have a follow-up date '
                . 'recorded at all.',
                $scheduled,
                $open
            );
    }

    /**
     * @param  array<string, mixed>  $findings
     * @return array<string, mixed>
     */
    private function result(
        ?int $enquiryId,
        int|string|null $instituteId,
        ?int $academicYear,
        int $floor,
        array $findings
    ): array {
        return array_merge($findings, array_filter([
            'enquiry_id' => $enquiryId,
            'sub_institute_id' => $instituteId,
            'syear' => $academicYear,
            'minimum_overdue_days' => $floor,
        ], static fn ($value) => $value !== null));
    }

    /**
     * The risk level a recommendation may claim, given how much it can cite.
     *
     * Severity is about how late the school is; risk level is about how defensible acting
     * on it is. They are allowed to differ, and when only the follow-up date could be read
     * they must.
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
}
