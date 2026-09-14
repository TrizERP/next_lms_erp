<?php

namespace App\Domain\AI\Lifecycle\Stages;

use App\Domain\AI\Conversation\AnswerComposer;
use App\Domain\AI\Conversation\ResultSet;
use App\Domain\AI\Explanations\ExplanationBuilder;
use App\Domain\AI\Lifecycle\Flows\AdmissionsFlow;
use App\Domain\AI\Lifecycle\LifecycleStage;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\StageKey;
use App\Domain\AI\Lifecycle\StageOutcome;
use App\Domain\AI\Lifecycle\Support\CaseResolver;
use App\Domain\AI\Lifecycle\Support\RiskScanLimit;
use App\Domain\AI\Lifecycle\Support\SelectionAnswerComposer;
use App\Domain\AI\Lifecycle\Support\ToolAnswerComposer;
use App\Domain\KnowledgeGraph\GraphQueryService;

/**
 * Stage 9 — the ontology walk, the case, and the governed explanation, as one.
 *
 * These three fold together because they answer one question between them: *why*. The
 * ontology says what the subject is connected to, the case says what was concluded about
 * them, and the explanation says which evidence each sentence of that conclusion rests
 * on. Split across three stages they read as bookkeeping; together they read as
 * reasoning, which is what they are.
 *
 * Governance is the part that must not be softened. Each claim cites the evidence ids
 * that support it, and a claim with nothing to cite is **dropped rather than reworded**.
 * A refused explanation is therefore a success of the governance layer and is reported
 * as a refusal with its reason — not as a missing explanation, and never by quietly
 * publishing the claim with a hedge in front of it.
 */
class ReasoningStage implements LifecycleStage
{
    /**
     * The enquiry fields worth showing when someone selects a name off the list.
     *
     * A summary, not the record: enough to confirm the assistant understood who was
     * meant, without reprinting a form the Admission module renders properly.
     */
    private const ENQUIRY_SUMMARY_FIELDS = [
        'enquiry_no' => 'Enquiry no',
        'student_name' => 'Student',
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'mobile' => 'Mobile',
        'email' => 'Email',
        'date_of_birth' => 'Date of birth',
        'admission_standard' => 'Standard',
        'standard_name' => 'Standard',
        'father_name' => 'Father',
        'status' => 'Status',
    ];

    public function __construct(
        private readonly CaseResolver $caseResolver,
        private readonly ExplanationBuilder $explanations,
        private readonly GraphQueryService $graph,
        private readonly AnswerComposer $compose,
        private readonly ToolAnswerComposer $toolAnswers,
        private readonly SelectionAnswerComposer $selections,
    ) {
    }

    public function key(): StageKey
    {
        return StageKey::Reasoning;
    }

    public function run(StageContext $context): StageOutcome
    {
        // A multi-turn task owns the answer while it is in flight.
        $admissions = $context->get('admissions_flow');

        if (is_array($admissions)) {
            return $this->admissions($context, $admissions);
        }

        // A turn that pointed at the previous answer is about that row and nothing else.
        // Checked before the case resolver, which would otherwise try to find an
        // academic-risk case for an admission enquiry and report that it could not tell
        // which student was meant — after the turn had already identified the record
        // precisely, by position, from a list the reader was looking at.
        if ($context->intent?->key === 'record_detail') {
            return $this->selections->detail($context);
        }

        if ($context->intent?->key === 'record_filter') {
            return $this->selections->filter($context);
        }

        // Ambiguity found upstream is a finding, not a failure. Asking which person was
        // meant is a better answer than picking one and being confidently wrong.
        $ambiguous = $context->get('ambiguous_students');

        if (is_array($ambiguous) && count($ambiguous) > 1) {
            return $this->ambiguous($context, $ambiguous);
        }

        if ($context->intent?->key === 'student_risk_scan' && count($context->cases) > 1) {
            return $this->rankedRiskScan($context);
        }

        if ($context->intent?->key === 'learning_effectiveness') {
            return StageOutcome::skipped(
                'Effectiveness is an aggregate across all interventions and needs no case.',
                []
            );
        }

        if (in_array($context->intent?->key, ['approve_recommendation', 'reject_recommendation'], true)) {
            return StageOutcome::skipped(
                'This turn records a decision and does not need a case.',
                []
            );
        }

        $resolved = $this->caseResolver->resolve($context);

        if ($resolved === null) {
            // A turn answered by tools has no case and never will. Falling through to the
            // case-based reply told a user asking about departments that the system did
            // not know which student they meant — after it had read six hundred real
            // department rows. Reasoning about records is still reasoning.
            if ($this->toolAnswers->hasResults($context)) {
                return $this->fromTools($context);
            }

            return $this->noCase($context);
        }

        $case = $resolved['case'];
        $caseId = (int) ($case['case_id'] ?? $case['id'] ?? 0);
        $studentName = $resolved['student_name'];

        $ontology = $this->walk($context, $resolved['student_id']);
        $explanation = $caseId > 0
            ? $this->explanations->latestForCase($caseId, $context->scope, 'teacher')
            : null;

        $context->link([
            'subject_entity_key' => $context->module->entityKey ?? 'student',
            'student_id' => $resolved['student_id'],
            'student_name' => $studentName,
            'case_id' => $caseId ?: null,
        ]);

        if ($explanation === null) {
            return StageOutcome::blocked(
                sprintf(
                    'Case #%d exists, but no explanation passed governance — no claim had citable '
                    . 'evidence behind it.',
                    $caseId
                ),
                ['case_id' => $caseId, 'ontology' => $ontology]
            )->withNote(
                'A claim that cannot cite evidence is dropped rather than softened, so an unexplained '
                . 'case is governance working, not a gap.'
            );
        }

        $claims = is_array($explanation['claims'] ?? null) ? $explanation['claims'] : [];

        // The headline answers the question that was asked.
        //
        // Every follow-up used to open with "X is flagged as critical risk" — true, and
        // an answer to only one of them. A user asking what the teacher should do, or
        // what the evidence is, was told the severity again and had to read down to find
        // their answer. The sections below are the same either way; the first line is
        // what makes a reply feel like a response rather than a broadcast.
        $context->setHeadline($this->headlineFor(
            $context->intent?->key,
            $studentName,
            strtolower($this->compose->severityLabel($case['severity'] ?? null)),
            count($context->evidence)
        ));

        if (! $this->isWorkflowRead($context)) {
            $context->addSection($this->compose->text(
                'Why',
                (string) ($explanation['narrative'] ?? $case['summary'] ?? '')
            ));

            if ($claims !== []) {
                $context->addSection($this->compose->records(
                    'Each claim, and what it rests on',
                    array_map(static fn ($claim) => [
                        'title' => is_array($claim) ? ($claim['claim'] ?? '') : (string) $claim,
                        'lines' => is_array($claim) && ! empty($claim['evidence_ids'])
                            ? ['Cites evidence #' . implode(', #', $claim['evidence_ids'])]
                            : ['No evidence cited'],
                        'meta' => is_array($claim) && isset($claim['confidence'])
                            ? ['Confidence' => number_format((float) $claim['confidence'], 2)]
                            : [],
                    ], $claims)
                ));
            }

            $context->suggestFollowUp(
                'What evidence supports this?',
                'What should the teacher do?'
            );
        }

        // The reference is not always populated. Printing "Case #3 (), severity critical"
        // reads as a missing value the reader should worry about, when it is simply a
        // field this case never had.
        $reference = trim((string) ($case['reference'] ?? $case['case_reference'] ?? ''));

        return StageOutcome::ran(
            sprintf(
                'Case #%d%s, severity %s — explanation composed from cited evidence and passed governance.',
                $caseId,
                $reference === '' ? '' : ' (' . $reference . ')',
                $case['severity'] ?? 'unknown'
            ),
            [
                'case' => array_intersect_key(
                    $case,
                    array_flip(['id', 'case_id', 'reference', 'case_type', 'severity', 'status', 'title', 'priority_score'])
                ),
                'explanation_id' => $explanation['id'] ?? null,
                'audience' => 'teacher',
                'claims' => $claims,
                'governance_passed' => $explanation['governance_passed'] ?? true,
                'ontology' => $ontology,
                'rule' => 'Each sentence cites the evidence ids that support it. A claim with nothing '
                    . 'to cite is dropped rather than softened.',
            ],
            ['table' => 'ai_cases', 'ids' => array_filter([$caseId])],
            [
                'api' => $this->prefix() . '/cases/' . $caseId . '/explanation',
                'sql' => 'select id, case_reference, severity, status from ai_cases where id = ' . $caseId,
            ]
        );
    }

    /**
     * The answer for a turn the tools served.
     *
     * Reported as `ran` rather than skipped: reading records, counting them and
     * presenting them against the question is the reasoning this turn did. The
     * distinction from the case path is what was reasoned *about*, not whether anything
     * was.
     */
    private function fromTools(StageContext $context): StageOutcome
    {
        $this->toolAnswers->compose($context);

        // A tool answer that is about exactly one person names that person, so the
        // module can pick up where the conversation left off.
        //
        // Nothing linked a student outside the academic-risk case path, which is why the
        // Fees hand-off could never appear: the assistant would identify who owed money
        // and then have no way to say *which* record the Fees module should open. Only a
        // single-row result qualifies — a list of forty defaulters identifies a cohort,
        // not a student, and guessing the first row would open the wrong ledger.
        $this->linkSingleSubject($context);

        $tools = $context->executedTools();

        $context->suggestRiskJourney('Which students are at academic risk?');

        return StageOutcome::ran(
            sprintf(
                'Answered from live records read through %s.',
                $tools === [] ? 'the scoped services' : implode(', ', $tools)
            ),
            [
                'tools' => $tools,
                'grounding' => 'Every figure in the answer is rendered from the tool payload. '
                    . 'No model wrote this prose, so nothing in it can assert more than the rows do.',
            ],
            [],
            ['api' => 'POST /api/mcp/tools/call']
        );
    }

    /**
     * Link the one student a tool answer is about, when there is exactly one.
     *
     * Reads the same payloads the answer was composed from, so the link can never point
     * at a record the reader was not shown.
     */
    private function linkSingleSubject(StageContext $context): void
    {
        $subjects = [];

        foreach ((array) $context->get('mcp_step_results', []) as $payload) {
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : (is_array($payload) ? $payload : []);

            foreach ($data as $value) {
                if (! is_array($value) || $value === [] || ! array_is_list($value)) {
                    continue;
                }

                foreach ($value as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $id = $row['student_id'] ?? $row['id'] ?? null;

                    if (is_numeric($id) && (int) $id > 0) {
                        $subjects[(int) $id] = $row['student_name'] ?? $row['name'] ?? null;
                    }
                }
            }
        }

        if (count($subjects) !== 1) {
            return;
        }

        $studentId = (int) array_key_first($subjects);

        $context->link(array_filter([
            'student_id' => $studentId,
            'student_name' => $subjects[$studentId],
        ], static fn ($value) => $value !== null));
    }

    /**
     * The opening line, chosen by what was asked.
     *
     * Deliberately a small, fixed set rather than anything generated: the headline is
     * the sentence most likely to be read and quoted, so it must never assert more than
     * the stages below it support.
     */
    private function headlineFor(?string $intentKey, string $student, string $severity, int $evidenceCount): string
    {
        return match ($intentKey) {
            'evidence_inspect' => sprintf(
                '%d piece%s of evidence support the case for %s.',
                $evidenceCount,
                $evidenceCount === 1 ? '' : 's',
                $student
            ),
            'recommendation_advice' => sprintf('What is proposed for %s.', $student),
            'workflow_status' => sprintf('Where %s\'s intervention has got to.', $student),
            'outcome_status' => sprintf('Whether the intervention for %s has worked yet.', $student),
            'learning_effectiveness' => 'What the system has learned from measured outcomes.',
            default => sprintf('%s is flagged as %s.', $student, $severity),
        };
    }

    private function isWorkflowRead(StageContext $context): bool
    {
        return in_array($context->intent?->key, ['workflow_status', 'outcome_status'], true);
    }

    /**
     * The evidence and the narrative behind whoever ranked first.
     *
     * A ranked list answers "who", and leaves "why" one question away for every name on
     * it — including the one the reader will almost always ask about first. Showing the
     * top case's evidence closes that gap without spending a turn, and it is the case
     * the ranking has already argued is the most urgent.
     *
     * It deliberately stops short of the recommendation's approval binding. Displaying
     * why a student is at risk is a read; deciding to intervene is not, and
     * `RecommendationStage` keeps that decision behind an explicit selection.
     *
     * @param  array<string, mixed>|null  $case
     */
    private function highestPriorityDetail(StageContext $context, ?array $case): void
    {
        if ($case === null) {
            return;
        }

        $student = (string) ($case['student_name'] ?? 'this student');
        $caseId = (int) ($case['case_id'] ?? $case['id'] ?? 0);

        // Read by EvidenceStage a stage earlier, kept per case because these rows carry
        // no case id once they are merged.
        $rows = (array) ($context->get('evidence_by_case', [])[$caseId] ?? []);

        $context->addSection($this->compose->evidence(
            sprintf('Evidence behind the highest-priority case (%s)', $student),
            array_slice($rows, 0, 5)
        ));

        $narrative = (string) ($case['explanation']['narrative'] ?? '');

        if ($narrative !== '') {
            $context->addSection($this->compose->text(
                sprintf('Why %s is flagged', $student),
                $narrative
            ));
        }
    }

    private function rankedRiskScan(StageContext $context): StageOutcome
    {
        $count = RiskScanLimit::fromQuestion($context->question, count($context->cases));
        $cases = array_slice($context->cases, 0, $count);

        // A ranked answer names several valid subjects. Remember the exact order, but
        // do not make the first one the conversation's active student: that would make
        // an approval below this result silently target a student the user did not pick.
        $ranked = array_values(array_map(static fn (array $case) => [
            'student_id' => (int) ($case['student_id'] ?? $case['subject_id'] ?? 0),
            'student_name' => $case['student_name'] ?? null,
            'case_id' => (int) ($case['case_id'] ?? $case['id'] ?? 0),
        ], $cases));

        $context->link(['last_case_list' => $ranked]);

        // The same list again, in the general shape every other list uses, so "show the
        // first student" works here for the same reason it works on an admissions list.
        // `last_case_list` above stays as it is: the risk journey resolves through it and
        // through the case ids it carries, and replacing it would be a rewrite of a
        // working path to no purpose.
        $set = ResultSet::fromRows(
            module: $context->module->key,
            tool: 'the academic-risk agent',
            key: 'students',
            rows: $ranked,
            question: $context->question,
            total: count($context->cases),
        );

        if ($set !== null) {
            $context->set('result_set', $set->toArray());
            $context->link(['last_result_set' => $set->toArray()]);
        }

        $context->setHeadline(sprintf(
            '%d student%s currently showing academic risk signals.',
            count($cases),
            count($cases) === 1 ? ' is' : 's are'
        ));

        // Who is at risk, grouped by how badly. This is the shape of the cohort in one
        // line, and it is what a head of year reads before reading anything else.
        $bySeverity = [];

        foreach ($cases as $case) {
            $bySeverity[$this->compose->severityLabel($case['severity'] ?? null)][] = $case['student_name'] ?? 'Student';
        }

        $context->addSection($this->compose->keyValues(
            'Breakdown',
            array_map(static fn (array $names) => implode(', ', $names), $bySeverity)
        ));

        // The list, with the numbers behind each place in it.
        //
        // These were once withheld until a student was selected, on the reasoning that a
        // ranked answer should not imply a verdict. In use that read as the assistant
        // knowing something it would not say: the ranking is *derived* from the priority
        // score and the signals, so showing the order while hiding what produced it left
        // the reader unable to check the one claim the answer was making.
        $context->addSection($this->compose->records(
            'Students',
            array_map(function (array $case, int $index) {
                return [
                    'id' => $case['case_id'] ?? null,
                    'title' => sprintf('%d. %s', $index + 1, $case['student_name'] ?? 'Student'),
                    'badge' => $this->compose->severityLabel($case['severity'] ?? null),
                    'badge_tone' => in_array($case['severity'] ?? null, ['critical', 'high'], true) ? 'danger' : 'warning',
                    'lines' => array_map(
                        static fn (array $signal) => $signal['summary'] ?? ($signal['signal_key'] ?? 'signal'),
                        array_values($case['signals'] ?? [])
                    ),
                    'meta' => array_filter([
                        'Case' => isset($case['case_id']) ? '#' . $case['case_id'] : null,
                        'Class' => $case['placement']['standard_name'] ?? null,
                        'Priority' => number_format((float) ($case['priority_score'] ?? 0), 2),
                    ]),
                    'case_id' => $case['case_id'] ?? null,
                    'student_id' => $case['student_id'] ?? null,
                ];
            }, $cases, array_keys($cases))
        ));

        $this->highestPriorityDetail($context, $cases[0] ?? null);

        foreach ($cases as $case) {
            $caseId = (int) ($case['case_id'] ?? $case['id'] ?? 0);
            $studentId = (int) ($case['student_id'] ?? $case['subject_id'] ?? 0);

            if ($caseId <= 0 || $studentId <= 0) {
                continue;
            }

            $student = (string) ($case['student_name'] ?? ('Student #' . $studentId));

            $context->addAction($this->compose->action(
                'view_risk_case_' . $caseId,
                'View details: ' . $student,
                'student_risk_explain',
                [
                    'case_id' => $caseId,
                    'student_id' => $studentId,
                    'utterance' => 'Why is this student at academic risk?',
                ]
            ));
        }

        $top = $cases[0]['student_name'] ?? null;

        $context->suggestFollowUp(...array_filter([
            $top === null ? null : sprintf('Why is %s at risk?', $top),
            'What evidence supports this?',
            'What should the teacher do?',
            'Select a student above to view the complete evidence and recommendation.',
        ]));

        return StageOutcome::ran(
            sprintf('Ranked and returned %d of %d cases from this agent run.', count($cases), count($context->cases)),
            [
                'requested_count' => $count,
                'available_cases' => count($context->cases),
                'ranked_case_ids' => array_values(array_filter(array_map(
                    static fn (array $case) => $case['case_id'] ?? null,
                    $cases
                ))),
                'rule' => 'Cases are ordered by the priority score calculated from this run\'s detected signals.',
            ],
            ['table' => 'ai_cases', 'ids' => array_values(array_filter(array_map(
                static fn (array $case) => $case['case_id'] ?? null,
                $cases
            )))]
        );
    }

    // ---------------------------------------------------------------- branches

    /**
     * Say where the admission has got to, and ask for exactly what is still needed.
     *
     * The "collecting" reply is the one that matters most: it must name the outstanding
     * fields in words a person uses, and confirm what was just accepted, or the user
     * cannot tell whether their last message landed.
     *
     * @param  array<string, mixed>  $flow
     */
    private function admissions(StageContext $context, array $flow): StageOutcome
    {
        $state = (string) ($flow['state'] ?? 'blocked');
        $enquiryId = $flow['enquiry_id'] ?? null;

        // Whatever the flow decided should be remembered — or forgotten — is carried out
        // by the orchestrator after the pipeline finishes.
        $context->set('pending_action_next', $flow['pending'] ?? null);
        // The state travels too, because the panel's next step depends on it: an enquiry
        // that still needs confirming should hand off to the Admission module with that
        // record open, while one already confirmed should open the enrolment it became.
        // Without the state the panel can only see "there is an enquiry" and has to guess
        // which of those two the user is in the middle of.
        $validation = is_array($flow['data'] ?? null) ? $flow['data'] : [];
        $allowed = is_array($validation['allowed_actions'] ?? null) ? $validation['allowed_actions'] : [];

        // The state travels too, because the panel's next step depends on it: an enquiry
        // that still needs confirming should hand off to the Admission module with that
        // record open, while one already confirmed should open the enrolment it became.
        //
        // `open_confirmation_page` is the backend's own answer to "may this person be
        // sent to the confirmation screen", and it is deliberately separate from
        // `confirm` — an admission missing four fields cannot be confirmed here but can
        // absolutely be finished there. That distinction is the whole reason the module
        // hand-off exists, so it is carried rather than re-derived.
        $context->link([
            'enquiry_id' => $enquiryId,
            'admission_state' => $state,
            'can_open_confirmation_page' => ($allowed['open_confirmation_page'] ?? null) === true ? 1 : null,
        ]);

        // Whose admission this is. Shown before anything is asked for, because a user who
        // picked a name off a list and was handed four field labels had no way to tell
        // whether the assistant had even understood which person they meant.
        $enquiry = is_array($validation['enquiry'] ?? null) ? $validation['enquiry'] : [];

        if ($enquiry !== []) {
            $details = [];

            foreach (self::ENQUIRY_SUMMARY_FIELDS as $field => $label) {
                $value = $enquiry[$field] ?? null;

                if (is_scalar($value) && trim((string) $value) !== '') {
                    $details[$label] = (string) $value;
                }
            }

            if ($details !== []) {
                $context->addSection($this->compose->keyValues('Admission enquiry details', $details));
            }
        }

        $supplied = is_array($flow['supplied'] ?? null) ? $flow['supplied'] : [];

        if ($supplied !== []) {
            $context->addSection($this->compose->keyValues(
                'Recorded from your message',
                array_combine(
                    array_map(
                        static fn (string $field) => ucfirst(AdmissionsFlow::label($field)),
                        array_keys($supplied)
                    ),
                    array_values($supplied)
                )
            ));
        }

        return match ($state) {
            'collecting' => $this->admissionsCollecting($context, $flow, (int) $enquiryId),
            'ready' => $this->admissionsReady($context, $flow, (int) $enquiryId),
            'confirmed' => $this->admissionsConfirmed($context, $flow, (int) $enquiryId),
            'already_confirmed' => $this->admissionsSettled(
                $context,
                'That admission has already been confirmed.',
                'Nothing was changed. The student record for this enquiry already exists.'
            ),
            'cancelled' => $this->admissionsSettled(
                $context,
                (string) ($flow['message'] ?? 'Stopped.'),
                'The enquiry is untouched, including any details recorded earlier in this conversation.'
            ),
            default => $this->admissionsBlocked($context, $flow),
        };
    }

    /**
     * @param  array<string, mixed>  $flow
     */
    private function admissionsCollecting(StageContext $context, array $flow, int $enquiryId): StageOutcome
    {
        $missing = is_array($flow['missing'] ?? null) ? $flow['missing'] : [];

        $context->setHeadline(sprintf(
            'Admission #%d needs %d more detail%s before it can be confirmed.',
            $enquiryId,
            count($missing),
            count($missing) === 1 ? '' : 's'
        ));

        $context->addSection($this->compose->records(
            'Still needed',
            array_map(static fn (array $field) => [
                'title' => $field['label'] ?? AdmissionsFlow::label((string) ($field['field'] ?? '')),
                'lines' => [],
                'meta' => [],
            ], $missing)
        ));

        $context->addSection($this->compose->text(
            'How to answer',
            'Reply with the values in one message — for example "division B, quota general, '
            . 'enrolled today". Say "cancel" to stop; nothing is confirmed until you approve it.'
        ));

        return StageOutcome::pending(
            sprintf(
                'Admission #%d is waiting on %d field%s from the user.',
                $enquiryId,
                count($missing),
                count($missing) === 1 ? '' : 's'
            ),
            [
                'enquiry_id' => $enquiryId,
                'missing_fields' => array_column($missing, 'field'),
                'accepted_this_turn' => array_keys(is_array($flow['supplied'] ?? null) ? $flow['supplied'] : []),
            ],
            ['table' => 'admission_enquiry', 'ids' => [$enquiryId]],
            ['api' => 'POST /api/mcp/tools/call  {"tool":"admissions.validateConfirmation"}']
        );
    }

    /**
     * @param  array<string, mixed>  $flow
     */
    private function admissionsReady(StageContext $context, array $flow, int $enquiryId): StageOutcome
    {
        $context->setHeadline(sprintf('Admission #%d is ready to confirm.', $enquiryId));

        $context->addSection($this->compose->text(
            'What confirming does',
            'It creates a student enrolment from this enquiry. That is a real record on the '
            . 'school roll, and it is not something this assistant will do without you saying so.'
        ));

        $context->addAction($this->compose->action(
            'confirm_admission',
            'Confirm admission #' . $enquiryId,
            'admission_confirm',
            ['enquiry_id' => $enquiryId, 'utterance' => 'Yes, confirm the admission.'],
            'primary'
        ));

        $context->suggestFollowUp('Cancel.');

        return StageOutcome::ran(
            sprintf('Admission #%d has every required field and is waiting on a person.', $enquiryId),
            ['enquiry_id' => $enquiryId, 'ready' => true],
            ['table' => 'admission_enquiry', 'ids' => [$enquiryId]]
        );
    }

    /**
     * @param  array<string, mixed>  $flow
     */
    private function admissionsConfirmed(StageContext $context, array $flow, int $enquiryId): StageOutcome
    {
        $data = is_array($flow['data'] ?? null) ? $flow['data'] : [];

        $context->setHeadline((string) ($flow['message'] ?? 'The admission has been confirmed.'));

        $context->addSection($this->compose->keyValues('Created', array_filter([
            'Student' => $data['student_name'] ?? null,
            'Student id' => isset($data['student_id']) ? (string) $data['student_id'] : null,
            'Enrollment no' => $data['enrollment_no'] ?? null,
            'Standard' => $data['standard_name'] ?? null,
            'Division' => $data['division_name'] ?? null,
        ])));

        return StageOutcome::ran(
            sprintf('Admission #%d confirmed; a student record now exists.', $enquiryId),
            ['enquiry_id' => $enquiryId] + $data,
            ['table' => 'tblstudent', 'ids' => array_filter([$data['student_id'] ?? null])]
        );
    }

    private function admissionsSettled(StageContext $context, string $headline, string $body): StageOutcome
    {
        $context->setHeadline($headline);
        $context->addSection($this->compose->text('What this means', $body));

        return StageOutcome::skipped($headline);
    }

    /**
     * @param  array<string, mixed>  $flow
     */
    private function admissionsBlocked(StageContext $context, array $flow): StageOutcome
    {
        $message = (string) ($flow['message'] ?? 'The admission could not be processed.');

        $context->setHeadline('I could not take that admission further.');
        $context->addSection($this->compose->text('Why', $message));

        return StageOutcome::blocked($message, ['enquiry_id' => $flow['enquiry_id'] ?? null])
            ->halting('The admission flow stopped, so nothing downstream ran.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $matches
     */
    private function ambiguous(StageContext $context, array $matches): StageOutcome
    {
        $name = $context->intent?->slot('student_name') ?? 'that name';

        $context->setHeadline(sprintf('"%s" matches %d students.', $name, count($matches)));
        $context->addSection($this->compose->records(
            'Which one did you mean?',
            array_map(static fn (array $student) => [
                'title' => $student['student_name'] ?? ('Student #' . ($student['student_id'] ?? '?')),
                'meta' => array_filter([
                    'Class' => $student['standard_name'] ?? null,
                    'Enrolment' => $student['enrollment_no'] ?? null,
                ]),
            ], array_slice($matches, 0, 8))
        ));

        return StageOutcome::blocked(
            sprintf('The subject is ambiguous — "%s" matches %d students.', $name, count($matches)),
            ['matches' => count($matches)]
        )->halting('The turn could not identify one subject, so nothing was concluded or acted on.');
    }

    private function noCase(StageContext $context): StageOutcome
    {
        $belowThreshold = $context->get('signals_below_threshold', []);
        $floors = $context->get('case_floors', []);

        if ($belowThreshold !== []) {
            $context->setHeadline(sprintf(
                'No case was opened, but %d signal%s did fire — none strong enough on its own.',
                count($belowThreshold),
                count($belowThreshold) === 1 ? '' : 's'
            ));

            $context->addSection($this->compose->records(
                'Signals below the case threshold',
                array_map(static function (array $signal) use ($floors) {
                    $floor = (float) ($floors[$signal['signal_key'] ?? ''] ?? 0.5);
                    $score = (float) ($signal['score'] ?? 0);

                    return [
                        'title' => $signal['student'] ?: ('Student #' . ($signal['student_id'] ?? '?')),
                        'badge' => ucfirst((string) ($signal['severity'] ?? '')),
                        'badge_tone' => 'warning',
                        'lines' => [str_replace('_', ' ', (string) ($signal['signal_key'] ?? ''))],
                        'meta' => [
                            'Score' => number_format($score, 3),
                            'Needs' => number_format($floor, 3) . ' to open a case alone',
                            'Short by' => number_format(max(0, $floor - $score), 3),
                        ],
                    ];
                }, $belowThreshold)
            ));

            $context->addSection($this->compose->text(
                'This is not a failure',
                'The evidence behind these signals is stored either way. If a second signal appears for '
                . 'the same student, or one of these worsens past the floor shown against it, the next '
                . 'run opens a case automatically and the rest of the journey follows.'
            ));

            return StageOutcome::skipped(
                'Signals were recorded but none cleared the threshold for opening a case.',
                ['signals' => count($belowThreshold), 'floors_in_force' => $floors]
            );
        }

        if ($context->agentRun !== null) {
            // "Nothing crossed its trigger" and "nothing could be judged" are opposite
            // findings that used to produce the same sentence.
            //
            // A school whose students have no assessment, attendance or homework rows
            // was told "no students are currently showing risk signals" — a clean bill
            // of health issued by detectors that had not been able to look at a single
            // child. That is the most dangerous thing this stage can say, and it is the
            // one the whole blind-detector apparatus exists to prevent. The trace has
            // carried the distinction all along; the answer did not.
            $coverage = $context->get('detector_coverage', []);
            $usable = array_values(array_filter(
                $coverage,
                static fn ($entry) => is_array($entry) && ($entry['blind'] ?? false) !== true
            ));
            $blind = array_values(array_filter(
                $coverage,
                static fn ($entry) => is_array($entry) && ($entry['blind'] ?? false) === true
            ));

            if ($coverage !== [] && $usable === []) {
                $context->setHeadline(
                    'Nothing could be assessed — the records these checks depend on are not there.'
                );

                $context->addSection($this->compose->records(
                    'What each check needed, and what it found',
                    array_map(static fn (array $entry) => [
                        'title' => ucfirst(str_replace('_', ' ', (string) ($entry['signal_key'] ?? 'a detector'))),
                        'badge' => 'No data',
                        'badge_tone' => 'warning',
                        'lines' => array_filter([$entry['requirement'] ?? null]),
                        'meta' => [
                            'Students examined' => (string) ($entry['examined'] ?? '0'),
                            'Able to judge' => (string) ($entry['judged'] ?? $entry['evaluated'] ?? '0'),
                        ],
                    ], $blind)
                ));

                $context->addSection($this->compose->text(
                    'What this is not',
                    'This is not a finding that no student is at risk. Every student in scope was read '
                    . 'and none of them had the source records these checks measure, so the sweep says '
                    . 'nothing either way. Load attendance, assessment or homework data for this '
                    . 'institute and the same question will answer properly.'
                ));

                return StageOutcome::blocked(
                    sprintf(
                        'All %d detector%s were blind — no student had the records they measure.',
                        count($blind),
                        count($blind) === 1 ? ' was' : 's were'
                    ),
                    ['detector_coverage' => $coverage]
                )->withNote(
                    'Reported as a refusal rather than a clean sweep, because a clean sweep is a '
                    . 'claim about the students and this is a fact about the data.'
                );
            }

            $context->setHeadline('No students are currently showing risk signals.');
            $context->addSection($this->compose->text(
                'What was checked',
                $blind === []
                    ? 'The detectors read the source records for the students in scope. Nothing crossed its trigger.'
                    : sprintf(
                        'The detectors that had records to read found nothing above their triggers. %d of '
                        . '%d could not judge anyone for want of records, so this sweep says nothing about '
                        . 'what %s would have found.',
                        count($blind),
                        count($coverage),
                        count($blind) === 1 ? 'it' : 'they'
                    )
            ));

            return StageOutcome::skipped(
                'No signal fired, so there was nothing to build a case from or explain.',
                ['blind_detectors' => count($blind), 'detectors' => count($coverage)]
            );
        }

        // The thread listed several rows and the user pointed at one of them without
        // saying which.
        //
        // "Why was that student flagged?" after a four-student risk scan is not a
        // question the pipeline can answer, and it is not a question it should guess at
        // either — picking the first of four would attach an explanation, and later a
        // recommendation and an approval, to a child nobody chose. But refusing with
        // "the question did not identify a student" is the generic non-answer: the
        // conversation is holding the four names, so the useful reply is to show them
        // and let one word settle it. ReferenceResolver reads the answer back by the
        // title printed here, or by position, so every option offered is one the next
        // turn can actually resolve.
        $set = ResultSet::fromArray($context->thread['memory']['last_result_set'] ?? null);

        if ($set !== null && $set->count() > 1) {
            $context->setHeadline(sprintf(
                'Which of the %d %s do you mean?',
                $set->count(),
                $set->noun
            ));

            $context->addSection($this->compose->records(
                'From the previous answer',
                array_map(static fn (array $item) => [
                    'title' => (string) $item['title'],
                    'meta' => ['Position' => '#' . $item['position']],
                ], $set->items)
            ));

            $context->addSection($this->compose->text(
                'How to answer',
                sprintf(
                    'Name one of them, or say "the first %s" — either resolves against the list above.',
                    $set->singular
                )
            ));

            $context->suggestFollowUp(...array_map(
                static fn (array $item) => sprintf('Why is %s at risk?', $item['title']),
                array_slice($set->items, 0, 4)
            ));

            return StageOutcome::blocked(
                sprintf(
                    'The previous answer listed %d %s and this question points at one of them without '
                    . 'naming it.',
                    $set->count(),
                    $set->noun
                ),
                ['candidates' => array_column($set->items, 'title'), 'id_field' => $set->idField]
            )->halting(
                'The turn could not identify one subject, so nothing was concluded or acted on. '
                . 'Naming one of the rows above answers it without re-running the scan.'
            );
        }

        return StageOutcome::blocked(
            'The question did not identify a student or a case, so there was nothing to reason about.',
            []
        )->withNote(
            'No subject was identified, so no recommendation, approval or action could apply.'
        );
    }

    /**
     * A shallow relationship walk — enough to show the subject is a node in a real graph,
     * not deep enough to make every question pay for a traversal it did not ask for.
     *
     * @return array<string, mixed>
     */
    private function walk(StageContext $context, int $studentId): array
    {
        $entityKey = $context->module->entityKey ?? 'student';

        if ($studentId <= 0) {
            return ['walked' => false, 'reason' => 'No subject id to walk from.'];
        }

        $relations = $this->graph->availableRelations($entityKey, $context->scope->selectedInstituteId);
        $walked = [];

        foreach (array_slice($relations, 0, 3) as $relation) {
            $name = is_array($relation) ? ($relation['key'] ?? $relation['relation'] ?? null) : $relation;

            if (! is_string($name)) {
                continue;
            }

            $walked[$name] = count($this->graph->neighbours($entityKey, $studentId, $name, $context->scope, 25));
        }

        return [
            'walked' => $walked !== [],
            'entity' => $entityKey,
            'subject_id' => $studentId,
            'relations_available' => count($relations),
            'neighbours_by_relation' => $walked,
        ];
    }

    private function prefix(): string
    {
        return '/' . trim((string) config('ai.route_prefix', 'api/ai'), '/');
    }
}
