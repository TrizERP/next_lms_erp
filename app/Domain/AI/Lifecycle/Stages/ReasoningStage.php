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
use App\Domain\AI\Lifecycle\Support\ChannelHealthAnswer;
use App\Domain\AI\Lifecycle\Support\ExamQuestionHelpAnswer;
use App\Domain\AI\Lifecycle\Support\ParentMessageAnswer;
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
        private readonly ParentMessageAnswer $parentMessages,
        private readonly ChannelHealthAnswer $channelHealth,
        // Optional so a stage built by hand keeps working; the container always supplies it.
        private readonly ?ExamQuestionHelpAnswer $examHelp = null,
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

        $feeReminders = $context->get('fees_reminders_flow');

        if (is_array($feeReminders)) {
            return $this->feeReminders($context, $feeReminders);
        }

        // A student stuck on a question of the exam they are sitting.
        if ($context->plan?->route === 'exam_question_help_flow') {
            return ($this->examHelp ?? app(ExamQuestionHelpAnswer::class))->compose($context);
        }

        // A message to parents (SMS or WhatsApp), in flight or just settled.
        $parentMessage = $context->get('parent_message_flow');

        if (is_array($parentMessage)) {
            return $this->parentMessages->compose($context, $parentMessage);
        }

        // One student's fees. Checked before the generic "selected record" handling below, which
        // would otherwise print a picked student as a bare table of fields.
        $feeStudent = $context->get('fees_student_flow');

        if (is_array($feeStudent)) {
            return $this->feeStudentDetail($context, $feeStudent);
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
     * Fee payment reminders: who, by when, by which channel, exactly what would be sent — then
     * what happened.
     *
     * Every figure and every name on this screen was read from the fee ledger by the tool, and
     * every sentence about sending says what is true: nothing leaves until the person approves
     * the list shown, and "accepted" means the mail server or SMS gateway took the message, not
     * that anyone read it.
     *
     * The students on screen are remembered as the thread's current list, so "this student",
     * "all 10" and "the second one" mean the people the person is looking at.
     *
     * @param  array<string, mixed>  $flow
     */
    private function feeReminders(StageContext $context, array $flow): StageOutcome
    {
        $state = (string) ($flow['state'] ?? 'blocked');
        $data = is_array($flow['data'] ?? null) ? $flow['data'] : [];
        $message = (string) ($flow['message'] ?? '');

        // What the flow decided should be remembered — or forgotten — is carried out by the
        // orchestrator after the pipeline finishes.
        $context->set('pending_action_next', $flow['pending'] ?? null);

        // The reminder that was just sent is what "send it by WhatsApp instead" refers to in the next turn.
        if (is_array($flow['remember'] ?? null)) {
            $context->link(['last_reminder' => $flow['remember']]);
        }

        $money = static fn ($value): string => \App\Services\Mcp\FeesReminderService::formatRupees((float) $value);

        return match ($state) {
            'need_deadline' => $this->feeRemindersNeedDeadline($context, $data, $message),
            'need_student' => $this->feeRemindersNeedStudent($context, $data, $message),
            'ready' => $this->feeRemindersReady($context, $data, $message, $money),
            'sent' => $this->feeRemindersSent($context, $data, $message, $money),
            'failed' => $this->feeRemindersFailed($context, $data, $message),
            'status' => $this->feeRemindersStatus($context, $data, $message, $money),
            'health' => $this->channelHealth->compose($context, $data, $message),
            'cancelled' => $this->feeRemindersSettled($context, 'Stopped. Nothing was sent.', 'No message left and nothing was recorded as sent.'),
            'nothing_to_send' => $this->feeRemindersNothing($context, $data, $message, $money),
            'ambiguous' => $this->feeRemindersAmbiguous($context, $data, $message),
            default => $this->feeRemindersSettled($context, $message !== '' ? $message : 'The reminders could not be prepared.', 'Nothing was sent.'),
        };
    }

    /**
     * Remember the students on screen as the list the next sentence can point at.
     *
     * @param  array<int, array<string, mixed>>  $rows  student_id, student_name, standard_name, outstanding
     */
    private function rememberFeeStudents(StageContext $context, array $rows): void
    {
        $set = ResultSet::fromRows(
            module: $context->module->key,
            tool: 'fees.outstanding_accounts',
            key: 'students',
            rows: array_map(static fn (array $row) => array_filter([
                'student_id' => $row['student_id'] ?? null,
                'student_name' => $row['student_name'] ?? $row['name'] ?? null,
                'standard_name' => $row['standard_name'] ?? $row['class'] ?? null,
                'outstanding' => $row['outstanding'] ?? null,
            ], static fn ($value) => $value !== null), $rows),
            question: $context->question,
        );

        if ($set !== null) {
            $context->set('result_set', $set->toArray());
            $context->link(['last_result_set' => $set->toArray()]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $accounts
     * @return array<int, array<string, mixed>>
     */
    private function feeAccountRecords(array $accounts): array
    {
        return array_map(static fn (array $account) => [
            'title' => (string) ($account['student_name'] ?? ''),
            'lines' => array_values(array_filter([
                isset($account['standard_name']) ? 'Class '.$account['standard_name'] : null,
                'Owes '.($account['outstanding_label'] ?? ''),
            ])),
            'meta' => [],
        ], $accounts);
    }

    /** @param  array<string, mixed>  $data */
    private function feeRemindersNeedDeadline(StageContext $context, array $data, string $message): StageOutcome
    {
        $accounts = is_array($data['accounts'] ?? null) ? $data['accounts'] : [];
        $channel = match ((string) ($data['channel'] ?? 'email')) {
            'sms' => 'SMS',
            'whatsapp' => 'WhatsApp',
            default => 'email',
        };

        $this->rememberFeeStudents($context, $accounts);

        $context->setHeadline($message !== '' && $message !== 'By what date should they pay?'
            ? $message
            : sprintf('I would remind %d student%s by %s. By what date should they pay?', count($accounts), count($accounts) === 1 ? '' : 's', $channel));

        $context->addSection($this->compose->records(
            (string) ($data['selection'] ?? 'Who would be reminded'),
            $this->feeAccountRecords($accounts)
        ));

        $context->addSection($this->compose->text(
            'What I need from you',
            'Reply with the payment deadline — for example "by 31 October". Then I will show you each student, '
            .'what they owe and the exact message, and nothing is sent until you approve it. '
            .'You can also narrow it first ("only the top 3", "the second student") or choose the channel ("by SMS"). Say "cancel" to stop.'
        ));

        $context->suggestFollowUp('By 31 October.', 'Send it by SMS.', 'Cancel.');

        return StageOutcome::pending(
            'Fee reminders are waiting on a payment deadline from the user.',
            ['students' => count($accounts), 'sent' => false],
            ['table' => 'tblstudent', 'ids' => array_values(array_filter(array_column($accounts, 'student_id')))]
        );
    }

    /** @param  array<string, mixed>  $data */
    private function feeRemindersNeedStudent(StageContext $context, array $data, string $message): StageOutcome
    {
        $accounts = is_array($data['accounts'] ?? null) ? $data['accounts'] : [];

        $this->rememberFeeStudents($context, $accounts);

        $context->setHeadline($message !== '' ? $message : 'Which student should I remind?');

        $context->addSection($this->compose->records('Students who owe the most', $this->feeAccountRecords($accounts)));

        $context->addSection($this->compose->text(
            'How to answer',
            'Name the student, give a student number ("student 42"), or point at one of these ("the first", "the third"). '
            .'Say "cancel" to stop. Nothing is sent until you have reviewed the message and approved it.'
        ));

        return StageOutcome::pending(
            'Fee reminders are waiting on which student the user means.',
            ['students_shown' => count($accounts), 'sent' => false],
            ['table' => 'tblstudent', 'ids' => array_values(array_filter(array_column($accounts, 'student_id')))]
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  callable(mixed): string  $money
     */
    private function feeRemindersReady(StageContext $context, array $data, string $message, callable $money): StageOutcome
    {
        $recipients = is_array($data['recipients'] ?? null) ? $data['recipients'] : [];
        $unreachable = is_array($data['unreachable'] ?? null) ? $data['unreachable'] : [];
        $count = (int) ($data['recipient_count'] ?? count($recipients));
        $kind = (string) ($data['channel'] ?? 'email');
        $sms = $kind === 'sms';
        $wa = $kind === 'whatsapp';
        $mobile = $sms || $wa;
        $channelWord = $wa ? 'WhatsApp' : ($sms ? 'SMS' : 'email');
        $contactNoun = $mobile ? 'Mobile' : 'Email';

        $this->rememberFeeStudents($context, array_merge($recipients, $unreachable));

        $context->setHeadline(sprintf('Review these %d %s reminder%s before anything is sent.', $count, $channelWord, $count === 1 ? '' : 's'));

        if ($message !== '') {
            $context->addSection($this->compose->text('Please note', $message));
        }

        $facts = is_array($data['sms'] ?? null) ? $data['sms'] : [];

        $context->addSection($this->compose->keyValues('Summary', array_filter([
            'Selection' => (string) ($data['selection'] ?? ''),
            'Sent by' => $mobile
                ? ($wa ? 'WhatsApp' : 'SMS').', to the mobile number on each student\'s record'.(isset($facts['gateway_host']) && $facts['gateway_host'] ? ' (via '.$facts['gateway_host'].')' : '')
                : 'Email, to the address on each student\'s record',
            'Payment deadline' => (string) ($data['deadline_label'] ?? ''),
            'Students who will be contacted' => (string) $count,
            'Total they owe' => $money($data['total_outstanding'] ?? 0),
            'Will not be contacted' => $unreachable === [] ? null : sprintf('%d (no usable %s)', count($unreachable), $mobile ? 'mobile number' : 'email address'),
            'Message length' => $sms && isset($facts['characters'])
                ? sprintf('%d characters, %d SMS part%s', $facts['characters'], $facts['segments'] ?? 1, ($facts['segments'] ?? 1) === 1 ? '' : 's')
                : null,
        ], static fn ($value) => $value !== null && $value !== '')));

        $context->addSection($this->compose->records(
            'Who will be contacted',
            array_map(static fn (array $row) => [
                'title' => (string) ($row['name'] ?? ''),
                'lines' => array_values(array_filter([
                    isset($row['class']) ? 'Class '.$row['class'] : null,
                    'Owes '.($row['outstanding_label'] ?? $money($row['outstanding'] ?? 0)),
                    $contactNoun.': '.($row['contact_masked'] ?? ''),
                ])),
                'meta' => [],
            ], $recipients)
        ));

        if ($unreachable !== []) {
            $context->addSection($this->compose->records(
                'Will NOT be contacted',
                array_map(static fn (array $row) => [
                    'title' => (string) ($row['name'] ?? ''),
                    'lines' => array_values(array_filter([
                        isset($row['class']) ? 'Class '.$row['class'] : null,
                        'Owes '.$money($row['outstanding'] ?? 0),
                        (string) ($row['reason'] ?? 'No usable contact.'),
                    ])),
                    'meta' => [],
                ], $unreachable)
            ));
        }

        // The institute's saved SMS template or signature, which the Send SMS Parents sender adds to every message.
        $template = is_array($data['template'] ?? null) ? $data['template'] : [];

        if ($mobile && $template !== []) {
            $context->addSection($this->compose->text(
                $wa ? 'WhatsApp template' : 'SMS template',
                match ((string) ($template['kind'] ?? '')) {
                    'signature' => 'Saved text added to every SMS: "'.($template['text'] ?? '').'"',
                    'gateway_parameter' => 'Gateway template parameter: '.($template['name'] ?? ''),
                    'cloud_template' => 'WhatsApp template: '.($template['name'] ?? '').(! empty($template['language']) ? "\nLanguage: ".$template['language'] : ''),
                    default => 'None saved — sent exactly as written.',
                }.(! empty($template['note']) ? "\n".$template['note'] : '')
            ));
        }

        $context->addSection($this->compose->text(
            'The message each student will receive',
            ($mobile ? '' : 'Subject: '.($data['sample_subject'] ?? 'Fee payment reminder')."\n\n")
            // For SMS and WhatsApp this is the finished text — the saved signature included for SMS, one line for
            // WhatsApp — which is what the parent's phone shows.
            .($mobile && ! empty($data['final_message']) ? $data['final_message'] : ($data['sample_message'] ?? ''))
            ."\n\n(The name and the amount are each student's own."
            .(! empty($data['message_is_custom']) ? ' This is your wording.' : '').')'
        ));

        $warnings = is_array($data['warnings'] ?? null) ? $data['warnings'] : [];

        if ($warnings !== []) {
            $context->addSection($this->compose->text('Before you approve', '• '.implode("\n• ", $warnings)));
        }

        $context->addSection($this->compose->text(
            'You can still change any of this',
            'Say "make it 5 November", "only the top 3", "send it by '.($wa ? 'SMS' : ($sms ? 'WhatsApp' : 'SMS')).' instead", or '
            .'change the message to "Dear {name}, your fee of {amount} is due by {deadline}." '
            .'{name}, {amount} and {deadline} are replaced with each student\'s own details. Nothing changes until you approve.'
        ));

        $context->addSection($this->compose->text(
            'Nothing has been sent',
            'This is a preview. The '.($wa ? 'WhatsApp messages' : ($sms ? 'SMS messages' : 'emails')).' go out only when you approve this exact list, and each result — '
            .'accepted, failed or skipped — is recorded so you can check it afterwards.'
        ));

        $context->addAction($this->compose->action(
            'send_fee_reminders',
            sprintf('Send %d %s reminder%s', $count, $channelWord, $count === 1 ? '' : 's'),
            'fees_send_reminders',
            ['utterance' => 'Yes, send the reminders.'],
            'primary'
        ));

        $context->suggestFollowUp(
            $sms ? 'Send it by WhatsApp instead.' : 'Send it by SMS instead.',
            'Edit the reminder message.',
            'Cancel.'
        );

        return StageOutcome::pending(
            sprintf('%d fee reminder%s are waiting for a person to approve the list.', $count, $count === 1 ? '' : 's'),
            ['recipients' => $count, 'unreachable' => count($unreachable), 'deadline' => $data['deadline'] ?? null, 'channel' => $data['channel'] ?? 'email', 'sent' => false],
            ['table' => 'tblstudent', 'ids' => array_values(array_filter(array_column($recipients, 'student_id')))]
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  callable(mixed): string  $money
     */
    private function feeRemindersSent(StageContext $context, array $data, string $message, callable $money): StageOutcome
    {
        $results = is_array($data['results'] ?? null) ? $data['results'] : [];
        $kind = (string) ($data['channel'] ?? 'email');
        $sms = $kind === 'sms';
        $wa = $kind === 'whatsapp';
        $acceptedBy = $wa ? 'WhatsApp' : ($sms ? 'the SMS gateway' : 'the mail server');

        $context->setHeadline($message);

        $context->addSection($this->compose->keyValues('Summary', array_filter([
            'Reference' => (string) ($data['reference'] ?? ''),
            'Sent by' => $wa ? 'WhatsApp' : ($sms ? 'SMS' : 'Email'),
            'Payment deadline' => (string) ($data['deadline'] ?? ''),
            'Accepted by '.$acceptedBy => (string) ($data['sent'] ?? 0),
            'Failed' => (string) ($data['failed'] ?? 0),
            'Skipped (no usable contact)' => (string) ($data['skipped'] ?? 0),
        ], static fn ($value) => $value !== null && $value !== '')));

        $context->addSection($this->compose->records(
            'What happened to each',
            array_map(fn (array $row) => [
                'title' => (string) ($row['name'] ?? ''),
                'lines' => [
                    $this->feeReminderStatusLabel((string) ($row['status'] ?? ''), $kind),
                    'Owes '.$money($row['outstanding'] ?? 0),
                    (string) ($row['detail'] ?? ''),
                ],
                'meta' => [],
            ], $results)
        ));

        $context->addSection($this->compose->text(
            'What "accepted" means',
            ucfirst($acceptedBy).' took the message. That is not proof it reached '.(($sms || $wa) ? 'a phone' : 'an inbox')
            .($wa
                ? ', but WhatsApp reports delivered and read later — ask "Show reminder status" to see what it has reported'
                : ', and nothing here records whether it was '.($sms ? 'read' : 'opened').($sms ? ' — this gateway sends no delivery receipt back' : '')).'. '
            .'Every result is saved — ask "Were the reminders sent?" to see them again.'
        ));

        $context->suggestFollowUp('Show reminder status.');

        return StageOutcome::ran(
            sprintf('%d reminder(s) accepted, %d failed, %d skipped.', (int) ($data['sent'] ?? 0), (int) ($data['failed'] ?? 0), (int) ($data['skipped'] ?? 0)),
            ['reference' => $data['reference'] ?? null, 'channel' => $data['channel'] ?? 'email', 'sent' => (int) ($data['sent'] ?? 0), 'failed' => (int) ($data['failed'] ?? 0)],
            ['table' => 'ai_audit_logs', 'ids' => []]
        );
    }

    /** @param  array<string, mixed>  $data */
    private function feeRemindersFailed(StageContext $context, array $data, string $message): StageOutcome
    {
        $results = is_array($data['results'] ?? null) ? $data['results'] : [];

        $context->setHeadline($message);

        if ($results !== []) {
            $sms = (string) ($data['channel'] ?? 'email');
            $context->addSection($this->compose->records(
                'What happened to each',
                array_map(fn (array $row) => [
                    'title' => (string) ($row['name'] ?? ''),
                    'lines' => array_values(array_filter([
                        $this->feeReminderStatusLabel((string) ($row['status'] ?? ''), $sms),
                        (string) ($row['detail'] ?? ''),
                    ])),
                    'meta' => [],
                ], $results)
            ));
        }

        $context->addSection($this->compose->text(
            'What this means',
            'No reminder was accepted, so nobody was told anything. You can try again once the cause is fixed.'
        ));

        return StageOutcome::blocked('No fee reminder could be sent.', ['detail' => $data]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  callable(mixed): string  $money
     */
    private function feeRemindersStatus(StageContext $context, array $data, string $message, callable $money): StageOutcome
    {
        $reminders = is_array($data['reminders'] ?? null) ? $data['reminders'] : [];

        // A question about delivery gets its answer first, and the answer is what the records can support.
        if (! empty($data['asked_about_delivery']) && $reminders !== []) {
            // About the most recent send, which is what "was it delivered?" means right after sending one.
            $batch = (string) ($reminders[0]['batch'] ?? '');
            $mine = array_values(array_filter($reminders, static fn (array $row) => (string) ($row['batch'] ?? '') === $batch));
            $accepted = count(array_filter($mine, static fn (array $row) => ($row['status'] ?? '') === 'sent'));
            $failed = count(array_filter($mine, static fn (array $row) => ($row['status'] ?? '') === 'failed'));
            $channel = match ((string) ($mine[0]['channel'] ?? 'sms')) {
                'whatsapp' => 'WhatsApp service',
                'sms' => 'SMS gateway',
                default => 'mail server',
            };

            // What each record's own delivery state says. WhatsApp can report delivered and read; an SMS gateway and a
            // mail server cannot, so for those the honest answer is that delivery is unknown.
            $by = array_count_values(array_column($mine, 'delivery_status'));
            $known = array_filter([
                ($by['read'] ?? 0) > 0 ? sprintf('%d read', $by['read']) : null,
                ($by['delivered'] ?? 0) > 0 ? sprintf('%d delivered', $by['delivered']) : null,
                ($by['pending'] ?? 0) > 0 ? sprintf('%d accepted and still waiting for a delivery report', $by['pending']) : null,
                ($by['not_confirmed'] ?? 0) > 0 ? sprintf('%d accepted by the %s but NOT confirmed delivered — it has reported no delivery', $by['not_confirmed'], $channel) : null,
                ($by['unknown'] ?? 0) > 0 ? sprintf('%d accepted but delivery is unknown (the %s offers no report for it)', $by['unknown'], $channel) : null,
                ($by['failed'] ?? 0) + ($by['not_delivered'] ?? 0) > 0 ? sprintf('%d not delivered', ($by['failed'] ?? 0) + ($by['not_delivered'] ?? 0)) : null,
            ]);

            $message = $accepted > 0
                ? sprintf('Latest send (%s): %s.', $batch !== '' ? $batch : 'most recent', implode('; ', $known))
                : sprintf('Nothing in the latest send (%s) was accepted, so nothing was delivered.', $batch !== '' ? $batch : 'most recent');
        }

        $context->setHeadline(trim($message));

        if ($reminders !== []) {
            $delivery = is_array($data['delivery'] ?? null) ? $data['delivery'] : [];

            $context->addSection($this->compose->keyValues('Totals', array_filter([
                'Accepted by the provider' => (string) ($data['accepted'] ?? $data['accepted_by_mail_server'] ?? 0),
                'Failed (not accepted)' => (string) ($data['failed'] ?? 0),
                'Skipped (no usable contact)' => (string) ($data['skipped'] ?? 0),
                'Delivery confirmed (WhatsApp delivered or read)' => (string) ($delivery['confirmed_delivered'] ?? 0),
                'Waiting for a delivery report (recent)' => (string) ($delivery['pending'] ?? 0),
                'Accepted but NOT confirmed delivered' => (string) ($delivery['not_confirmed'] ?? 0),
                'Delivery unknown (no report exists)' => (string) ($delivery['unknown'] ?? 0),
            ], static fn ($value) => $value !== null)));

            // The most recent send in full, then everything earlier in short: the person who just sent a
            // reminder is asking about that one.
            $latest = (string) ($reminders[0]['batch'] ?? '');
            $latestRows = array_values(array_filter($reminders, static fn (array $row) => (string) ($row['batch'] ?? '') === $latest));
            $earlierRows = array_values(array_filter($reminders, static fn (array $row) => (string) ($row['batch'] ?? '') !== $latest));

            $detail = fn (array $row): array => [
                'title' => (string) ($row['student_name'] ?? ('Student #'.($row['student_id'] ?? ''))),
                'lines' => array_values(array_filter([
                    'Status: '.$this->feeReminderStatusLabel((string) ($row['status'] ?? ''), (string) ($row['channel'] ?? 'email')),
                    'Delivery: '.str_replace('_', ' ', (string) ($row['delivery_status'] ?? 'unknown')).(! empty($row['delivery_note']) ? ' — '.$row['delivery_note'] : ''),
                    'Channel: '.match ((string) ($row['channel'] ?? 'email')) {
                        'sms' => 'SMS',
                        'whatsapp' => 'WhatsApp',
                        default => 'Email',
                    }.(! empty($row['sent_to']) ? ' to '.$row['sent_to'] : ''),
                    isset($row['student_id']) ? 'Student ID '.$row['student_id'].(! empty($row['class']) ? ' · Class '.$row['class'] : '') : null,
                    isset($row['outstanding']) ? 'Amount owed: '.$money($row['outstanding']) : null,
                    isset($row['deadline']) ? 'Payment deadline: '.$row['deadline'] : null,
                    ! empty($row['message']) ? 'Message: '.$row['message'] : null,
                    ! empty($row['detail']) ? 'Provider response: '.$row['detail'] : null,
                    ! empty($row['provider_id']) ? 'Gateway message id: '.$row['provider_id'] : null,
                    ! empty($row['batch']) ? 'Reference: '.$row['batch'] : null,
                    isset($row['recorded_at']) ? 'Recorded: '.$row['recorded_at'] : null,
                ])),
                'meta' => [],
            ];

            $context->addSection($this->compose->records(
                $earlierRows === [] ? 'Reminders' : 'Latest reminder'.($latest !== '' ? ' ('.$latest.')' : ''),
                array_map($detail, array_slice($latestRows, 0, 50))
            ));

            if ($earlierRows !== []) {
                $context->addSection($this->compose->records(
                    'Earlier reminders, newest first',
                    array_map(fn (array $row) => [
                        'title' => (string) ($row['student_name'] ?? ('Student #'.($row['student_id'] ?? ''))),
                        'lines' => array_values(array_filter([
                            $this->feeReminderStatusLabel((string) ($row['status'] ?? ''), (string) ($row['channel'] ?? 'email')).' · '.match ((string) ($row['channel'] ?? 'email')) {
                                'sms' => 'SMS',
                                'whatsapp' => 'WhatsApp',
                                default => 'Email',
                            },
                            isset($row['outstanding']) ? 'Owed '.$money($row['outstanding']).(isset($row['deadline']) ? ' · pay by '.$row['deadline'] : '') : null,
                            ! empty($row['detail']) ? (string) $row['detail'] : null,
                            (! empty($row['batch']) ? $row['batch'].' · ' : '').(isset($row['recorded_at']) ? 'recorded '.$row['recorded_at'] : ''),
                        ])),
                        'meta' => [],
                    ], array_slice($earlierRows, 0, 50))
                ));
            }
        }

        $context->addSection($this->compose->text(
            'How to read this',
            (string) ($data['rule'] ?? '"Sent" means the mail server or SMS gateway accepted the message; it is not proof of delivery.')
            ."\n".'Accepted = the provider took the request. Delivered = the provider confirmed it reached the phone or inbox (not available for this SMS gateway or for email). Failed = the provider did not accept it. Unknown = accepted, with no receipt to say more.'
        ));

        return StageOutcome::ran(
            sprintf('Read %d fee reminder record(s) from the ledger.', count($reminders)),
            ['records' => count($reminders)],
            ['table' => 'ai_audit_logs', 'ids' => array_values(array_filter(array_column($reminders, 'reminder_id')))]
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  callable(mixed): string  $money
     */
    private function feeRemindersNothing(StageContext $context, array $data, string $message, callable $money): StageOutcome
    {
        $unreachable = is_array($data['unreachable'] ?? null) ? $data['unreachable'] : [];
        $kind = (string) ($data['channel'] ?? 'email');
        $sms = $kind !== 'email';
        $word = $kind === 'whatsapp' ? 'WhatsApp' : ($sms ? 'SMS' : 'email');

        $context->setHeadline($message);

        if ($unreachable !== []) {
            $context->addSection($this->compose->records(
                $sms ? 'Students with no usable mobile number' : 'Students with no usable email address',
                array_map(static fn (array $row) => [
                    'title' => (string) ($row['name'] ?? ''),
                    'lines' => array_values(array_filter([
                        isset($row['class']) ? 'Class '.$row['class'] : null,
                        'Owes '.$money($row['outstanding'] ?? 0),
                        (string) ($row['reason'] ?? ''),
                    ])),
                    'meta' => [],
                ], $unreachable)
            ));
        }

        $context->addSection($this->compose->text(
            'What would be needed',
            'Nobody on this list can be reached by '.$word.'. Try the other channel ("send it by '.($sms ? 'email' : 'SMS')
            .' instead"), or add the missing '.($sms ? 'mobile number' : 'email address').' to the student record. '
            .'App notifications are not available from this assistant.'
        ));

        return StageOutcome::skipped('Nobody could be reminded by '.$word.', so nothing was prepared to send.', ['sent' => false]);
    }

    /** @param  array<string, mixed>  $data */
    private function feeRemindersAmbiguous(StageContext $context, array $data, string $message): StageOutcome
    {
        $students = is_array($data['students'] ?? null) ? $data['students'] : [];

        $context->setHeadline($message);
        $context->addSection($this->compose->records(
            'Possible matches',
            array_map(static fn (array $row) => [
                'title' => (string) ($row['name'] ?? ''),
                'lines' => array_values(array_filter(['Enrolment no. '.($row['enrollment_no'] ?? '')])),
                'meta' => [],
            ], $students)
        ));

        return StageOutcome::skipped('More than one student matched the name, so nothing was prepared.', ['sent' => false]);
    }

    private function feeRemindersSettled(StageContext $context, string $headline, string $detail): StageOutcome
    {
        $context->setHeadline($headline);
        $context->addSection($this->compose->text('Where things stand', $detail));

        return StageOutcome::skipped($headline, ['sent' => false]);
    }

    /**
     * One student's fees: what they owe, for which months, and how they can be reached.
     *
     * Every number came from the fee ledger for this institute and year. The student is
     * remembered as the thread's selected record, so "send a reminder to this student" and
     * "show their reminder status" mean this person.
     *
     * @param  array<string, mixed>  $flow
     */
    /**
     * What one student paid in a period: the receipts, grouped by receipt number.
     *
     * Money received only. It says so on screen, because the nearest wrong answer — what the student owes
     * or was charged — is a different number that is easy to mistake for it.
     *
     * @param  array<string, mixed>  $data  The `fees.collection_report` payload for one student.
     */
    private function feeStudentPayments(StageContext $context, array $data, string $message): StageOutcome
    {
        $money = static fn ($value): string => \App\Services\Mcp\FeesReminderService::formatRupees((float) $value);
        $student = is_array($data['student'] ?? null) ? $data['student'] : [];
        $period = is_array($data['period'] ?? null) ? $data['period'] : [];
        $summary = is_array($data['summary'] ?? null) ? $data['summary'] : [];
        $studentId = (int) ($student['student_id'] ?? $data['student_id'] ?? 0);
        $name = (string) ($student['name'] ?? '');

        $context->setHeadline($message !== '' ? $message : 'Here is what this student paid in that period.');

        $context->addSection($this->compose->keyValues('Student', array_filter([
            'Name' => $name,
            'Class' => isset($student['class']) ? (string) $student['class'] : null,
            'Enrolment no.' => isset($student['enrollment_no']) ? (string) $student['enrollment_no'] : null,
            'Period' => (string) ($period['label'] ?? ''),
            'Receipts in the period' => (string) ($summary['all_receipts'] ?? 0),
            'Total paid in the period' => $money($summary['total_collected'] ?? 0),
        ], static fn ($value) => $value !== null && $value !== '')));

        // One line per receipt number: a receipt is stored as a row per term it covers.
        $receipts = [];

        foreach ([['rows' => $data['receipts'] ?? [], 'kind' => 'Fee receipt'], ['rows' => $data['other_fee_receipts'] ?? [], 'kind' => 'Other-fee receipt']] as $group) {
            foreach ((array) $group['rows'] as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $key = $group['kind'].'|'.($row['receipt_no'] ?? '').'|'.($row['receipt_date'] ?? '');
                $receipts[$key] ??= ['kind' => $group['kind'], 'no' => $row['receipt_no'] ?? '', 'date' => $row['receipt_date'] ?? '', 'mode' => $row['payment_mode'] ?? '', 'amount' => 0.0, 'lines' => 0];
                $receipts[$key]['amount'] += (float) ($row['amount'] ?? 0);
                $receipts[$key]['lines']++;
            }
        }

        if ($receipts !== []) {
            $context->addSection($this->compose->records(
                'Receipts',
                array_map(fn (array $r) => [
                    'title' => sprintf('%s #%s — %s', $r['kind'], $r['no'] !== '' ? $r['no'] : 'n/a', $money($r['amount'])),
                    'lines' => array_values(array_filter([
                        'Paid on '.\Illuminate\Support\Carbon::parse($r['date'])->format('j F Y'),
                        'Mode: '.($r['mode'] !== '' ? $r['mode'] : 'not recorded'),
                        $r['lines'] > 1 ? sprintf('Covers %d term lines', $r['lines']) : null,
                    ])),
                    'meta' => [],
                ], array_values($receipts))
            ));
        }

        $context->addSection($this->compose->text(
            'What this shows',
            'These are the payments actually received from this student inside the period above. It is not what '
            .'they owe, what they were charged, or what the institute as a whole collected.'
        ));

        if ($studentId > 0) {
            $item = [
                'position' => 1,
                'id' => $studentId,
                'title' => $name,
                'row' => array_filter(['student_id' => $studentId, 'student_name' => $name, 'standard_name' => $student['class'] ?? null], static fn ($value) => $value !== null),
            ];

            // The student and the dates are what the next, shorter sentence means: "and on 5 October?",
            // "how much in cash?", "what about last week?".
            $context->set('selected_record', ['item' => $item, 'singular' => 'student', 'module' => $context->module->key, 'set' => null]);
            $context->link(array_filter([
                'selected_record' => ['item' => $item, 'singular' => 'student', 'module' => $context->module->key],
                'student_id' => $studentId,
                'student_name' => $name,
                'last_period' => isset($period['from'], $period['to']) ? [
                    'from' => (string) $period['from'],
                    'to' => (string) $period['to'],
                    'label' => (string) ($period['label'] ?? ''),
                    'tool' => 'fees.collection_report',
                    'student_id' => $studentId,
                ] : null,
            ], static fn ($value) => $value !== null));
        }

        $context->suggestFollowUp('What about the last 7 days?', 'How much was paid in cash?', 'Show the fee details for this student.');

        return StageOutcome::ran(
            sprintf('Read %s\'s receipts for %s.', $name !== '' ? $name : 'the student', (string) ($period['label'] ?? 'the period')),
            ['student_id' => $studentId, 'receipts' => (int) ($summary['all_receipts'] ?? 0), 'from' => $period['from'] ?? null, 'to' => $period['to'] ?? null]
        );
    }

    private function feeStudentDetail(StageContext $context, array $flow): StageOutcome
    {
        $state = (string) ($flow['state'] ?? 'blocked');
        $data = is_array($flow['data'] ?? null) ? $flow['data'] : [];
        $message = (string) ($flow['message'] ?? '');
        $money = static fn ($value): string => \App\Services\Mcp\FeesReminderService::formatRupees((float) $value);

        if ($state === 'list') {
            $accounts = is_array($data['accounts'] ?? null) ? $data['accounts'] : [];

            if ($accounts === []) {
                $context->setHeadline($message !== '' ? $message : 'No student owes fees under that selection.');

                return StageOutcome::skipped('No individual students were found who owe fees.', ['students' => 0]);
            }

            $this->rememberFeeStudents($context, $accounts);

            $context->setHeadline(! empty($data['of_listed'])
                ? sprintf('Fee details for the %d student%s just listed, largest balance first.', count($accounts), count($accounts) === 1 ? '' : 's')
                : sprintf(
                    '%s%d student%s who owe%s the most, largest balance first.',
                    ($data['standard_id'] ?? null) !== null ? 'In this class: ' : '',
                    count($accounts),
                    count($accounts) === 1 ? '' : 's',
                    count($accounts) === 1 ? 's' : ''
                ));

            $context->addSection($this->compose->records(
                'Students with the highest outstanding fees',
                array_map(static fn (array $a) => [
                    'title' => (string) ($a['student_name'] ?? ''),
                    'lines' => array_values(array_filter([
                        'Student ID '.($a['student_id'] ?? ''),
                        isset($a['standard_name']) ? 'Class '.$a['standard_name'] : null,
                        'Owes '.($a['outstanding_label'] ?? ''),
                        isset($a['months_pending']) && (int) $a['months_pending'] > 0
                            ? sprintf('Unpaid for %d month%s, oldest %s', (int) $a['months_pending'], (int) $a['months_pending'] === 1 ? '' : 's', (string) ($a['oldest_due_month'] ?? 'not recorded'))
                            : null,
                        isset($a['contact']['mobile_masked']) ? 'SMS to '.$a['contact']['mobile_masked'] : 'No usable mobile number',
                        isset($a['contact']['email_masked']) ? 'Email '.$a['contact']['email_masked'] : null,
                    ])),
                    'meta' => [],
                ], $accounts)
            ));

            $context->suggestFollowUp(
                'Show the fee details for the first student.',
                'Send an SMS fee reminder to the first student.',
                sprintf('Send SMS reminders to all %d.', count($accounts))
            );

            return StageOutcome::ran(
                sprintf('Read %d individual students from the fee ledger, largest balance first.', count($accounts)),
                ['students' => count($accounts)],
                ['table' => 'tblstudent', 'ids' => array_values(array_filter(array_column($accounts, 'student_id')))]
            );
        }

        if ($state === 'ambiguous') {
            $students = is_array($data['students'] ?? null) ? $data['students'] : [];

            $context->setHeadline($message);
            $context->addSection($this->compose->records(
                'Possible matches',
                array_map(static fn (array $row) => [
                    'title' => (string) ($row['name'] ?? ''),
                    'lines' => array_values(array_filter(['Enrolment no. '.($row['enrollment_no'] ?? '')])),
                    'meta' => [],
                ], $students)
            ));

            return StageOutcome::skipped('More than one student matched the name, so none was chosen.', ['students' => count($students)]);
        }

        if ($state === 'payments') {
            return $this->feeStudentPayments($context, $data, $message);
        }

        if ($state === 'needs_period') {
            $context->setHeadline($message);
            $context->suggestFollowUp('Yesterday.', 'In the last 7 days.', 'This month.');

            return StageOutcome::skipped('The question asked what a student paid but named no day, so no receipts were read.', ['asked_for_period' => true]);
        }

        if ($state === 'payments_compare') {
            $reports = array_values(array_filter((array) ($data['reports'] ?? []), 'is_array'));
            $fmt = static fn ($value): string => \App\Services\Mcp\FeesReminderService::formatRupees((float) $value);

            $context->setHeadline($message);
            $context->addSection($this->compose->records(
                'Payments side by side',
                array_map(static function (array $report) use ($fmt): array {
                    $summary = (array) ($report['summary'] ?? []);
                    $student = (array) ($report['student'] ?? []);
                    $modes = array_map(
                        static fn (array $m) => sprintf('%s %s', $m['mode'], $fmt($m['amount'])),
                        array_values(array_filter((array) ($report['by_payment_mode'] ?? []), 'is_array'))
                    );

                    return [
                        'title' => (string) ($student['name'] ?? ''),
                        'lines' => array_values(array_filter([
                            isset($student['class']) ? 'Class '.$student['class'] : null,
                            'Paid '.$fmt($summary['total_collected'] ?? 0),
                            sprintf('%d receipt%s', (int) ($summary['all_receipts'] ?? 0), (int) ($summary['all_receipts'] ?? 0) === 1 ? '' : 's'),
                            $modes === [] ? 'No payment in the period' : 'By mode: '.implode(', ', $modes),
                        ])),
                        'meta' => [],
                    ];
                }, $reports)
            ));
            $context->addSection($this->compose->text(
                'What this shows',
                'Payments actually received from each student inside the same period. Not what either owes or was charged.'
            ));

            return StageOutcome::ran('Compared two students\' receipts over one period.', ['students' => count($reports)]);
        }

        if ($state !== 'ok') {
            $context->setHeadline($message !== '' ? $message : 'I could not find that student.');
            $context->addSection($this->compose->text(
                'What you can do',
                'Check the spelling, or give the student number ("student 42") or the enrolment number. '
                .'You can also ask "Which students owe the most?" and pick one from the list.'
            ));

            return StageOutcome::skipped('The student could not be found, so no fee details were read.', ['found' => false]);
        }

        $name = (string) ($data['student_name'] ?? '');
        $studentId = (int) ($data['student_id'] ?? 0);
        $periods = is_array($data['periods'] ?? null) ? $data['periods'] : [];
        $owes = (bool) ($data['owes'] ?? false);
        $ledger = (float) ($data['ledger_outstanding'] ?? 0);
        $contact = is_array($data['contact'] ?? null) ? $data['contact'] : [];
        $channels = is_array($contact['channels'] ?? null) ? $contact['channels'] : [];

        $context->setHeadline($owes
            ? sprintf('%s owes %s%s.', $name, $money($ledger), $periods === [] ? '' : sprintf(', unpaid for %d month%s', count($periods), count($periods) === 1 ? '' : 's'))
            : sprintf('%s has no outstanding fees on the ledger.', $name));

        $context->addSection($this->compose->keyValues('Student', array_filter([
            'Name' => $name,
            'Class' => isset($data['standard_name']) ? (string) $data['standard_name'] : null,
            'Enrolment no.' => isset($data['enrollment_no']) ? (string) $data['enrollment_no'] : null,
            'Outstanding (fee ledger)' => $money($ledger),
            'Unpaid months' => $periods === [] ? null : (string) count($periods),
            'Oldest unpaid month' => $periods === [] ? null : (string) $periods[0]['month'],
            'Latest unpaid month' => $periods === [] ? null : (string) $periods[count($periods) - 1]['month'],
            'Failed payment attempts' => isset($data['failed_payments']) && (int) $data['failed_payments'] > 0 ? (string) $data['failed_payments'] : null,
        ], static fn ($value) => $value !== null && $value !== '')));

        if ($periods !== []) {
            $context->addSection($this->compose->records(
                'Unpaid fee months',
                array_map(static fn (array $period) => [
                    'title' => (string) $period['month'],
                    'lines' => array_values(array_filter([
                        'Owes '.$money($period['remaining']),
                        $period['discount'] > 0 ? 'Discount '.$money($period['discount']) : null,
                    ])),
                    'meta' => [],
                ], array_slice($periods, 0, 24))
            ));

            if (abs((float) ($data['periods_total'] ?? 0) - $ledger) > 1) {
                $context->addSection($this->compose->text(
                    'About the totals',
                    sprintf(
                        'The unpaid months add up to %s, while the fee ledger shows %s. The ledger is the figure used everywhere else; '
                        .'a difference can come from fines or concessions applied outside the monthly schedule.',
                        $money($data['periods_total'] ?? 0),
                        $money($ledger)
                    )
                ));
            }
        } elseif ($owes) {
            $context->addSection($this->compose->text(
                'About the months',
                'The ledger shows a balance, but no unpaid month was listed for this student, so I cannot say which months it covers.'
            ));
        }

        $context->addSection($this->compose->keyValues('How to reach them', array_filter([
            'Email' => $contact['email_masked'] ?? ($contact['email_problem'] ?? 'none on record'),
            'Mobile (SMS goes here: the main mobile on the student record)' => $contact['mobile_masked'] ?? ($contact['mobile_problem'] ?? 'none on record'),
            'Other numbers on record (not used)' => ! empty($contact['other_mobiles']) ? implode(', ', array_map(static fn ($who, $n) => $who.' '.$n, array_keys($contact['other_mobiles']), $contact['other_mobiles'])) : null,
            'Reminder channels available' => $channels === [] ? 'none — neither a usable email nor a usable mobile number' : implode(' and ', array_map(static fn (string $c) => $c === 'sms' ? 'SMS' : 'Email', $channels)),
        ], static fn ($value) => $value !== null && $value !== '')));

        // The student on screen is the one the next sentence means by "this student".
        $item = [
            'position' => 1,
            'id' => $studentId,
            'title' => $name,
            'row' => array_filter([
                'student_id' => $studentId,
                'student_name' => $name,
                'standard_name' => $data['standard_name'] ?? null,
                'outstanding' => $ledger,
            ], static fn ($value) => $value !== null),
        ];
        $context->set('selected_record', ['item' => $item, 'singular' => 'student', 'module' => $context->module->key, 'set' => null]);
        $context->link([
            'selected_record' => ['item' => $item, 'singular' => 'student', 'module' => $context->module->key],
            'student_id' => $studentId,
            'student_name' => $name,
        ]);

        if ($owes) {
            $context->suggestFollowUp(
                ...array_values(array_filter([
                    'Send a fee reminder to this student.',
                    in_array('sms', $channels, true) ? 'Send an SMS fee reminder to this student.' : null,
                    'Show reminder status.',
                ]))
            );
        }

        return StageOutcome::ran(
            sprintf('Read %s\'s fee ledger position and %d unpaid month(s).', $name, count($periods)),
            ['student_id' => $studentId, 'unpaid_months' => count($periods), 'ledger_outstanding' => $ledger],
            ['table' => 'tblstudent', 'ids' => [$studentId]]
        );
    }

    /** @param  string|bool  $channel  'sms', 'whatsapp' or 'email' (true is read as SMS, for older callers). */
    private function feeReminderStatusLabel(string $status, string|bool $channel = false): string
    {
        $channel = $channel === true ? 'sms' : $channel;

        return match ($status) {
            'sent' => match ($channel) {
                'sms' => 'Accepted by the SMS gateway',
                'whatsapp' => 'Accepted by WhatsApp',
                default => 'Accepted by the mail server',
            },
            'failed' => 'Failed',
            'skipped' => 'Not contacted',
            default => $status,
        };
    }

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

        // "Show me their names" is not a pointer at one row, it is a request to list all of them, and
        // the thread is holding exactly those rows. Asking "which of the 10 do you mean?" answered
        // a plural question with a singular one. List what the previous answer held, and say so if
        // that was only part of what the tool found.
        if ($set !== null && $set->count() > 1
            && preg_match('/\b(their|them|these|those)\b/i', $context->question)
            && preg_match('/\b(names?|list|show|who)\b/i', $context->question)
            && ! preg_match('/\b(why|how|explain|flag|risk)\b/i', $context->question)) {
            $held = $set->count();

            $context->setHeadline(
                $set->total > $held
                    ? sprintf('These are the first %d of the %d %s from the previous answer.', $held, $set->total, $set->noun)
                    : sprintf('These are the %d %s from the previous answer.', $held, $set->noun)
            );

            $context->addSection($this->compose->records(
                ucfirst($set->noun),
                array_map(static function (array $item) use ($set): array {
                    $facts = [];

                    foreach ((array) $item['row'] as $key => $value) {
                        if ($key === $set->idField || ! is_scalar($value) || $value === '' || (string) $value === (string) $item['title']) {
                            continue;
                        }

                        $facts[] = ucfirst(str_replace('_', ' ', (string) $key)).': '.$value;
                    }

                    return [
                        'title' => (string) $item['title'],
                        'lines' => array_slice($facts, 0, 3),
                        'meta' => ['Position' => '#'.$item['position']],
                    ];
                }, $set->items)
            ));

            return StageOutcome::ran(
                sprintf('Listed the %d %s the previous answer held; no new lookup was needed.', $held, $set->noun),
                ['from_previous_answer' => true, 'tool' => $set->tool, 'held' => $held, 'total' => $set->total]
            );
        }

        // Only a question that points at a row is a question about WHICH row. "Who is the principal of
        // this school?" names no subject and points at none, and used to be answered with "Which of the
        // 10 students do you mean?" simply because a list happened to be in memory.
        // "that candidate" for a list of enquiries counts: the user's noun need not match the row's.
        // "this school", "this year" and the like are not rows.
        $pointsAtARow = $set !== null && (bool) preg_match(
            '/\b(he|she|him|his|hers?|it|its|that one|this one|the (first|second|third|fourth|fifth|last|one)'
            .'|(this|that)\s+(?!school\b|institute\b|year\b|term\b|page\b|system\b|screen\b|module\b|month\b|week\b|time\b)[a-z]+'
            .'|the\s+'.preg_quote($set->singular, '/').')\b/i',
            $context->question
        );

        if ($set !== null && $set->count() > 1 && $pointsAtARow) {
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
