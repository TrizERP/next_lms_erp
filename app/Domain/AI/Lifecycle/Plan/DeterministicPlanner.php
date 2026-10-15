<?php

namespace App\Domain\AI\Lifecycle\Plan;

use App\Domain\AI\Lifecycle\Flows\AdmissionsFlow;
use App\Domain\AI\Lifecycle\Flows\FeesRemindersFlow;
use App\Domain\AI\Lifecycle\Flows\FeesStudentDetailFlow;
use App\Domain\AI\Lifecycle\Flows\ParentMessageFlow;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\Support\RecordDetail;
use App\Domain\AI\Conversation\Intent;

/**
 * The fast path: a known intent routes the same way every time.
 *
 * This exists because the questions that matter most are the ones that must never be
 * creative. "Approve the recommendation" records a decision in a named person's account
 * and starts a workflow against a student's record; routing it through a model would
 * mean an approval that cannot be replayed and a route that can differ on a Tuesday.
 * Every intent here is a line of code somebody can read, and the trace names which one
 * matched.
 *
 * It plans only what it recognises. A question outside the intent registry gets null,
 * and the hybrid planner takes it to the model — which is the whole point of the split:
 * determinism where it is worth paying for, breadth everywhere else.
 */
class DeterministicPlanner implements Planner
{
    public function __construct(private readonly RecordDetail $detail)
    {
    }

    /**
     * The lookup tool every student-scoped route may need to resolve or hydrate a
     * person. Named as a candidate; whether it is actually called is stage 5's business.
     */
    private const STUDENT_LOOKUP = ['students.search'];

    /**
     * Intents whose answer is about one named student, and therefore need that student
     * resolved before anything else can be true of them.
     */
    /** The tools the admission confirmation flow needs, in the order it uses them. */
    private const ADMISSION_TOOLS = [
        'admissions.validateConfirmation',
        'admissions.updateEnquiry',
        'admissions.confirm',
    ];

    /**
     * What the fee reminder flow may call: the preview-and-send tool, the Fees ranking it shows
     * the person, and the student search it names a student with. All three are Fees-bound.
     */
    private const FEES_REMINDER_TOOLS = ['fees.send_reminders', 'fees.outstanding_accounts', 'students.search'];

    /** What showing one student's fees needs: find them, read the ledger, read the unpaid months. */
    private const FEES_STUDENT_TOOLS = ['students.search', 'fees.outstanding_accounts', 'fees.getPending'];

    /** The two intents that belong to Fees alone, because only Fees has the tool they run. */
    private const FEES_REMINDER_INTENTS = ['fees_send_reminders', 'fees_reminder_status'];

    /** Routes whose answer is something an agent stored, so a module with no agent has none to give. */
    private const AGENT_GOVERNED_ROUTES = [
        'stored_case_read',
        'stored_evidence_read',
        'stored_recommendation_read',
        'decision_gate',
        'workflow_read',
        'outcome_read',
        'effectiveness_read',
    ];

    /** The one tool the parent-message flow previews and sends through. */
    private const PARENT_MESSAGE_TOOLS = ['communication.send_parent_message'];

    private const NEEDS_STUDENT = [
        'student_risk_explain',
        'evidence_inspect',
        'recommendation_advice',
        'approve_recommendation',
        'reject_recommendation',
        'workflow_status',
        'outcome_status',
    ];

    public function plan(StageContext $context): ?Plan
    {
        // A thread part-way through a task outranks whatever the sentence looks like on
        // its own. "Division B, quota general" classifies as nothing at all; read
        // against a pending admission it is the answer to the question just asked. Any
        // planner that classified first would strand every multi-turn flow on its
        // second turn.
        $continuation = $this->continuationPlan($context);

        if ($continuation !== null) {
            return $continuation;
        }

        $intent = $context->intent;

        if ($intent === null) {
            return null;
        }

        // "Was the WhatsApp delivered?" names no reminder, so it reads as a question about parent messages. On the Fees
        // page — which keeps its own record of the reminders it sent, and binds that record's tool but not the parent
        // message one — it is a question about those reminders, and is answered from them rather than from nothing.
        if ($intent->key === 'parent_message_status'
            && in_array('fees.reminder_history', $context->module->mcpTools, true)
            && ! in_array('communication.parent_message_history', $context->module->mcpTools, true)) {
            $intent = $context->intent = new Intent(
                key: 'fees_reminder_status',
                label: 'Fee reminder status',
                confidence: $intent->confidence,
                slots: $intent->slots,
                matched: $intent->matched,
                resolvedFrom: $intent->resolvedFrom,
                suggestions: $intent->suggestions,
            );
        }

        // A fee question the classifier had no word for ("the list of students who have pending dues")
        // still names students; on the Fees module that is enough to take the student route.
        if ($intent->isUnknown()) {
            return $this->feesStudentPlan($context, $intent);
        }

        // One student's fees. `fees_query` has no route of its own on purpose, so a sentence that
        // names a student used to reach the model planner and, without it, the whole-school summary.
        // Only a sentence that names ONE student (or a row picked off the previous list) is taken
        // here; every other fee question still takes the route it always did.
        $studentPlan = $this->feesStudentPlan($context, $intent);

        if ($studentPlan !== null) {
            return $studentPlan;
        }

        $steps = $this->stepsFor($intent->key);

        if ($steps === null) {
            return null;
        }

        // The classifier is estate-wide and these two are Fees' alone. Said on any other
        // module's page, the sentence goes to the model planner exactly as it did before they
        // existed, rather than to a route whose tools that module does not have.
        if (in_array($intent->key, self::FEES_REMINDER_INTENTS, true) && $context->module->key !== 'fees') {
            return null;
        }

        // A plan has to be executable by the module it is planned for.
        //
        // The classifier is estate-wide, so "who has low attendance?" matches the
        // academic-risk scan — a reasonable reading, and the intent it would want on a
        // student page. Routed at the attendance module, which has no agent, it planned
        // an agent run that could never happen and the turn reported four skipped stages
        // and no answer. Declining here hands the question to the model planner, which
        // will reach for attendance.overview and actually answer it.
        if ($this->routeFor($intent->key) === 'agent_runner' && ! $context->module->hasAgent()) {
            return null;
        }

        // The same holds for every route that reads what an agent stored — cases, evidence, a drafted
        // recommendation, a workflow run, outcomes, effectiveness. The classifier is estate-wide and
        // matches ordinary words ("plan", "setup", "effective"), so on a screen whose module has no
        // agent there is nothing stored for these routes to read: "summarise the lesson plan" was
        // answered with academic-intervention analytics. Declining hands the sentence to the model
        // planner and then to the module's own read. The general module keeps them: it is the
        // estate-wide view, where these are legitimately asked.
        if ($context->module->key !== 'general'
            && in_array($this->routeFor($intent->key), self::AGENT_GOVERNED_ROUTES, true)
            && ! $context->module->hasAgent()) {
            return null;
        }

        $needsStudent = in_array($intent->key, self::NEEDS_STUDENT, true)
            || $intent->slot('student_id') !== null
            || $intent->slot('student_name') !== null;

        $wanted = match ($intent->key) {
            'admission_confirm' => self::ADMISSION_TOOLS,
            'fees_send_reminders' => self::FEES_REMINDER_TOOLS,
            // The status answer is read straight from the ledger, so it needs no tool of its
            // own; the send tool is the nominal candidate that lets the turn reach the flow.
            // The status answer is read from the saved reminder records through the history tool, so the turn's
            // sources name what was actually read.
            'fees_reminder_status' => ['fees.reminder_history'],
            'parent_message_send' => self::PARENT_MESSAGE_TOOLS,
            // Read from the saved record of those sends, through the history tool, so the sources name it.
            'parent_message_status' => ['communication.parent_message_history'],
            'channel_health' => ['communication.channel_health'],
            'admission_enquiry_list' => ['admissions.listEnquiries'],
            // Selecting a row is answerable from the rows already in memory, so the
            // lookup is an enrichment rather than a requirement: where the module binds a
            // tool that reads one record of this shape, the answer is the whole record;
            // where it does not, it is the fields the reader was already shown. Both are
            // real answers, which is why this route never fails for want of a binding.
            'record_detail' => array_filter([
                $this->detail->toolFor($context->module, $intent->slot('record_id_field')),
            ]),
            'record_filter' => [],
            default => $needsStudent ? self::STUDENT_LOOKUP : [],
        };

        // A module may only propose tools it is actually bound to. Without this, an
        // intent shared across modules would name a tool the module has no permission
        // to select, and stage 5 would have to refuse a plan its own planner wrote.
        $candidates = array_values(array_intersect($wanted, $context->module->mcpTools));

        // The classifier is estate-wide and this intent belongs to the modules that bind the sending
        // tool. Anywhere else the sentence goes to the planners that follow, exactly as before.
        if (in_array($intent->key, ['parent_message_send', 'parent_message_status', 'channel_health'], true) && $candidates === []) {
            return null;
        }

        $strategy = match (true) {
            $candidates === [] => 'domain_services_only',
            in_array($intent->key, ['parent_message_send', 'parent_message_status', 'channel_health'], true) => 'parent_message_flow',
            $intent->key === 'admission_confirm' => 'admissions_confirmation_flow',
            in_array($intent->key, self::FEES_REMINDER_INTENTS, true) => 'fees_reminders_flow',
            $intent->key === 'admission_enquiry_list' => 'mcp_read',
            $intent->key === 'record_detail' => 'selected_record_lookup',
            default => 'mcp_student_resolution',
        };

        // The detail route has to carry its argument on the step. The transport runs a
        // planned step with the arguments the plan wrote — it has no way to know that
        // `record_id` from a selection means `enquiry_id` to this particular tool.
        if ($intent->key === 'record_detail' && $candidates !== []) {
            $steps = $this->detailSteps($intent, $candidates[0]);
        }

        return new Plan(
            goal: $this->goalFor($intent->key, $intent->label),
            steps: $steps,
            source: Plan::SOURCE_DETERMINISTIC,
            route: $intent->key === 'channel_health' && in_array('fees.reminder_history', $context->module->mcpTools, true)
                ? 'fees_reminders_flow'
                : $this->routeFor($intent->key),
            intentKey: $intent->key,
            candidateTools: $candidates,
            toolSelectionStrategy: $strategy,
            context: [
                'module' => $context->module->key,
                'confidence' => round($intent->confidence, 3),
                'matched_by' => 'intent registry',
            ],
        );
    }

    // ---------------------------------------------------------------- internals

    /**
     * A plan for continuing a task the thread is already part-way through.
     *
     * Returns null when there is nothing in flight, which is the ordinary case.
     */
    private function continuationPlan(StageContext $context): ?Plan
    {
        $pending = $context->thread['memory']['pending_action'] ?? null;

        if (is_array($pending) && ($pending['kind'] ?? null) === FeesRemindersFlow::KIND) {
            return $this->feesReminderContinuation($context, $pending);
        }

        if (is_array($pending) && ($pending['kind'] ?? null) === ParentMessageFlow::KIND) {
            return $this->parentMessageContinuation($context, $pending);
        }

        if (! is_array($pending) || ($pending['kind'] ?? null) !== AdmissionsFlow::KIND) {
            return null;
        }

        $tools = array_values(array_intersect(self::ADMISSION_TOOLS, $context->module->mcpTools));

        if ($tools === []) {
            return null;
        }

        return new Plan(
            goal: 'Continue confirming the admission this conversation is part-way through.',
            steps: [
                new PlanStep('read_reply', 'Read the fields the user just supplied.'),
                new PlanStep(
                    'update',
                    'Write any supplied fields onto the enquiry.',
                    'admissions.updateEnquiry'
                ),
                new PlanStep(
                    'revalidate',
                    'Re-check what the admission still needs.',
                    'admissions.validateConfirmation'
                ),
                new PlanStep('report', 'Ask for what is still missing, or offer the confirmation.'),
            ],
            source: Plan::SOURCE_DETERMINISTIC,
            route: 'admissions_flow',
            intentKey: 'admission_confirm',
            candidateTools: $tools,
            toolSelectionStrategy: 'admissions_confirmation_flow',
            context: [
                'module' => $context->module->key,
                'matched_by' => 'a pending admission on this thread, which outranks the sentence',
                'pending' => $pending,
            ],
        );
    }

    /**
     * A plan to show one student's fees, or null when the sentence is not about exactly one.
     *
     * Applies on the Fees module to `fees_query`, and to a `record_detail` whose row is a student
     * (picked off a list the previous answer showed), and only when the module is bound to the
     * tools the answer needs.
     */
    private function feesStudentPlan(StageContext $context, Intent $intent): ?Plan
    {
        if ($context->module->key !== 'fees' || (! in_array($intent->key, ['fees_query', 'record_detail'], true) && ! $intent->isUnknown())) {
            return null;
        }

        $tools = array_values(array_intersect(self::FEES_STUDENT_TOOLS, $context->module->mcpTools));

        if (count($tools) < count(self::FEES_STUDENT_TOOLS)) {
            return null;
        }

        $memory = is_array($context->thread['memory'] ?? null) ? $context->thread['memory'] : [];

        // What one student PAID on a date reads receipts, not the ledger.
        $paymentTools = array_values(array_intersect(['students.search', 'fees.collection_report'], $context->module->mcpTools));

        if (count($paymentTools) === 2 && FeesStudentDetailFlow::paymentTarget($context->question, $memory, $intent) !== null) {
            return new Plan(
                goal: 'Find the one student the question is about and show what they actually paid in the period asked about.',
                steps: [
                    new PlanStep('find', 'Find the student named, through the Fees module\'s own student search.', 'students.search'),
                    new PlanStep('receipts', 'Read the receipts for that student inside the period.', 'fees.collection_report'),
                    new PlanStep('report', 'Report only the payments received from that student in that period, or say none was found.'),
                ],
                source: Plan::SOURCE_DETERMINISTIC,
                route: 'fees_student_flow',
                intentKey: $intent->key,
                candidateTools: $paymentTools,
                toolSelectionStrategy: 'fees_student_payments_flow',
                context: [
                    'module' => $context->module->key,
                    'confidence' => round($intent->confidence, 3),
                    'matched_by' => 'a question about what one student paid on a date',
                ],
            );
        }

        if (FeesStudentDetailFlow::target($context->question, $intent) === null
            && FeesStudentDetailFlow::listTarget($context->question, $memory) === null) {
            return null;
        }

        return new Plan(
            goal: 'Find the one student the question is about and show what they owe, and for which months.',
            steps: [
                new PlanStep('find', 'Find the student named, through the Fees module\'s own student search.', 'students.search'),
                new PlanStep('ledger', 'Read their balance from the fee ledger.', 'fees.outstanding_accounts'),
                new PlanStep('periods', 'Read which fee months they have not paid.', 'fees.getPending'),
                new PlanStep('report', 'Return that student\'s records, or say the student could not be found.'),
            ],
            source: Plan::SOURCE_DETERMINISTIC,
            route: 'fees_student_flow',
            intentKey: $intent->key,
            candidateTools: $tools,
            toolSelectionStrategy: 'fees_student_flow',
            context: [
                'module' => $context->module->key,
                'confidence' => round($intent->confidence, 3),
                'matched_by' => 'a fee question that names one student',
            ],
        );
    }

    /**
     * Carrying on with a fee reminder the thread is part-way through: the deadline reply, the
     * "yes", or a change to the list. Only on the Fees module, which is where it was started.
     *
     * @param  array<string, mixed>  $pending
     */
    private function feesReminderContinuation(StageContext $context, array $pending): ?Plan
    {
        $tools = array_values(array_intersect(self::FEES_REMINDER_TOOLS, $context->module->mcpTools));

        if ($context->module->key !== 'fees' || $tools === []) {
            return null;
        }

        return new Plan(
            goal: 'Continue the fee reminder this conversation is part-way through.',
            steps: [
                new PlanStep('read_reply', 'Read the deadline, count or approval the user just gave.'),
                new PlanStep('preview', 'Show exactly who would be emailed, what each owes and the message.', 'fees.send_reminders'),
                new PlanStep('approve', 'Send only if the person approves the list shown.'),
            ],
            source: Plan::SOURCE_DETERMINISTIC,
            route: 'fees_reminders_flow',
            intentKey: 'fees_send_reminders',
            candidateTools: $tools,
            toolSelectionStrategy: 'fees_reminders_flow',
            context: [
                'module' => $context->module->key,
                'matched_by' => 'a pending fee reminder on this thread, which outranks the sentence',
                'pending' => $pending,
            ],
        );
    }

    /**
     * Carrying on with a parent message the thread is part-way through: the missing detail, a change
     * to the request, or the "yes". Only where the sending tool is bound, which is where it started.
     *
     * @param  array<string, mixed>  $pending
     */
    private function parentMessageContinuation(StageContext $context, array $pending): ?Plan
    {
        $tools = array_values(array_intersect(self::PARENT_MESSAGE_TOOLS, $context->module->mcpTools));

        if ($tools === []) {
            return null;
        }

        return new Plan(
            goal: 'Continue the parent message this conversation is part-way through.',
            steps: [
                new PlanStep('read_reply', 'Read the detail, change or approval the user just gave.'),
                new PlanStep('preview', 'Show exactly who would be messaged and what it says.', 'communication.send_parent_message'),
                new PlanStep('approve', 'Send only if the person approves the list shown.'),
            ],
            source: Plan::SOURCE_DETERMINISTIC,
            route: 'parent_message_flow',
            intentKey: 'parent_message_send',
            candidateTools: $tools,
            toolSelectionStrategy: 'parent_message_flow',
            context: [
                'module' => $context->module->key,
                'matched_by' => 'a pending parent message on this thread, which outranks the sentence',
                'pending' => $pending,
            ],
        );
    }

    private function routeFor(string $intentKey): string
    {
        return match ($intentKey) {
            'student_risk_scan' => 'agent_runner',
            // Same route, different agent: which one runs is the module's binding, not
            // this table's business. Asked on Fees it is the fees agent; the guard in
            // plan() still declines the route entirely where the module has no agent.
            'fees_risk_scan' => 'agent_runner',
            'attendance_risk_scan' => 'agent_runner',
            // And admissions, on the same route and the same guard. Which agent runs is
            // the module's binding: asked on Admissions it is `k12_admissions`.
            'admission_followup_scan' => 'agent_runner',
            'student_risk_explain' => 'stored_case_read',
            'evidence_inspect' => 'stored_evidence_read',
            'recommendation_advice' => 'stored_recommendation_read',
            'approve_recommendation', 'reject_recommendation' => 'decision_gate',
            'workflow_status' => 'workflow_read',
            'outcome_status' => 'outcome_read',
            'learning_effectiveness' => 'effectiveness_read',
            'admission_enquiry_list' => 'mcp_tools',
            'admission_confirm' => 'admissions_flow',
            'fees_send_reminders', 'fees_reminder_status' => 'fees_reminders_flow',
            'parent_message_send', 'parent_message_status' => 'parent_message_flow',
            // Answered by the Fees flow on the Fees page and by the parent-message flow on Communication; see plan().
            'channel_health' => 'parent_message_flow',
            // Both selection routes answer from the previous turn's rows. `record_detail`
            // upgrades to a tool call when the module binds one that reads this record;
            // `record_filter` never does, because narrowing a list the user is looking at
            // is a question about that list rather than a new query.
            'record_detail' => 'selected_record',
            'record_filter' => 'previous_answer_filter',
            default => 'conversation',
        };
    }

    /**
     * The detail route's steps, with the selected row's id bound to the tool's own
     * argument name.
     *
     * @return array<int, PlanStep>
     */
    private function detailSteps(Intent $intent, string $tool): array
    {
        $idField = (string) $intent->slot('record_id_field', 'id');
        $recordId = $intent->slot('record_id');

        return [
            new PlanStep('resolve_record', sprintf(
                'Identify which row of the previous answer "%s" refers to.',
                $intent->slot('record_title') ?? 'the selection'
            )),
            new PlanStep(
                'read_record',
                'Load that record in full through the module\'s own lookup.',
                $tool,
                is_numeric($recordId)
                    ? [$idField => (int) $recordId] + $this->detail->accepted($tool, (array) $intent->slot('record_context', []))
                    : [],
                ['resolve_record']
            ),
            new PlanStep('report', 'Return the record, and say what it is still missing.', null, [], ['read_record']),
        ];
    }

    private function goalFor(string $intentKey, string $label): string
    {
        return match ($intentKey) {
            'student_risk_scan' => 'Run the academic-risk agent, persist the governed findings, and surface any drafted recommendation.',
            'student_risk_explain' => 'Read the stored case, evidence and explanation for one student.',
            'evidence_inspect' => 'Read the verified evidence rows behind an existing case.',
            'recommendation_advice' => 'Read back the drafted recommendation and explain what it would do, without executing it.',
            'approve_recommendation' => 'Record a human approval and, when allowed, start the bound workflow.',
            'reject_recommendation' => 'Record a human rejection and stop the downstream workflow path.',
            'workflow_status' => 'Read the current workflow run and show which step it is waiting on.',
            'outcome_status' => 'Compare the recorded baseline against the latest measured outcome.',
            'learning_effectiveness' => 'Aggregate historical outcomes into effectiveness by action type.',
            'admission_enquiry_list' => 'List the admission enquiries on record and their state.',
            'admission_confirm' => 'Collect whatever the admission still needs, then put the confirmation to a person.',
            'fees_send_reminders' => 'Work out who is to be reminded and by when, show exactly what would be sent, '
                . 'and send only what a person approves.',
            'fees_reminder_status' => 'Show which fee reminders were sent, failed or skipped, from the record of past sends.',
            'parent_message_status' => 'Show which SMS or WhatsApp messages to parents were sent, failed or delivered, from the saved record.',
            'channel_health' => 'Check whether SMS and WhatsApp can reach parents, asking the providers themselves.',
            'record_detail' => 'Open the row the previous answer listed and show everything held about it.',
            'record_filter' => 'Narrow the previous answer to the rows that match, without re-querying.',
            'fees_risk_scan' => 'Find who is carrying unpaid fees, evidence it from the ledger, and put a '
                . 'collection review to a person.',
            'attendance_risk_scan' => 'Find who is attending below the school bar, evidence it from the '
                . 'marked register, and put a follow-up to a person.',
            'admission_followup_scan' => 'Find the enquiries past the follow-up date the school recorded '
                . 'for them, evidence it from that date, and put a follow-up to a person.',
            default => $label,
        };
    }

    /**
     * @return array<int, PlanStep>|null
     */
    private function stepsFor(string $intentKey): ?array
    {
        $steps = match ($intentKey) {
            // No hydration step here, deliberately.
            //
            // An earlier version listed "hydrate the highest-priority student through
            // Laravel MCP", and the trace then showed a plan promising an MCP call
            // beside a tool-selection stage reporting that nothing was selected. The
            // promise was unkeepable: which student is highest-priority is only known
            // after the agent runs, and the agent runs *after* the transport stage. A
            // plan must describe what the turn does, so the step is gone rather than
            // the contradiction being explained away. The student's name comes from the
            // case row, which is the record the decision is bound to anyway.
            'student_risk_scan' => [
                ['classify', 'Resolve the question into the academic-risk scan intent.'],
                ['agent', 'Run the module agent across the in-scope students.'],
                ['reason', 'Group signals into cases and compose governed explanations.'],
                ['recommend', 'Draft any recommendation that governance allows.'],
            ],
            'student_risk_explain' => [
                ['resolve_case', 'Identify the student or case the follow-up refers to.'],
                ['read_case', 'Load the stored case, evidence and governed explanation.'],
                ['report', 'Return the explanation and the evidence behind each claim.'],
            ],
            'evidence_inspect' => [
                ['resolve_case', 'Identify which case or student the user means.'],
                ['read_evidence', 'Load the verified evidence rows already linked to the case.'],
                ['report', 'Return evidence with provenance and any linked explanation.'],
            ],
            'recommendation_advice' => [
                ['resolve_case', 'Identify which case is being discussed.'],
                ['read_recommendation', 'Load the drafted recommendation and its explanation.'],
                ['report', 'Show the proposed action without executing it.'],
            ],
            'approve_recommendation', 'reject_recommendation' => [
                ['resolve_target', 'Identify the recommendation or workflow approval to decide on.'],
                ['decision', 'Record the human decision through the governed backend gate.'],
                ['workflow', 'Advance or halt the downstream workflow based on that decision.'],
            ],
            'workflow_status' => [
                ['resolve_workflow', 'Identify the workflow run tied to the case or recommendation.'],
                ['read_workflow', 'Load the workflow run and its current step states.'],
                ['report', 'Explain which downstream action has or has not happened yet.'],
            ],
            'outcome_status' => [
                ['resolve_case', 'Identify which intervention outcome to inspect.'],
                ['read_outcomes', 'Load the recorded baseline and latest measurement.'],
                ['report', 'Show whether the intervention improved the tracked metrics.'],
            ],
            'learning_effectiveness' => [
                ['read_outcomes', 'Load measured outcomes across prior interventions.'],
                ['aggregate', 'Summarise effectiveness by action type and status.'],
                ['report', 'Return what the system has learned from measured outcomes.'],
            ],
            'admission_enquiry_list' => [
                ['read_enquiries', 'Load the admission enquiries in scope.', 'admissions.listEnquiries'],
                ['report', 'Return the enquiries and which are still pending.'],
            ],
            // Mirrors the academic scan's shape because it is the same journey: the
            // agent detects and opens a case, the case carries the evidence, and the
            // recommendation it drafts stops at a person rather than acting.
            'fees_risk_scan' => [
                ['detect', 'Read arrears for the students in scope through the fee ledger.'],
                ['analyse', 'Open a case for each student carrying a balance, citing the unpaid heads.'],
                ['recommend', 'Draft a fees collection review for each case.'],
                ['report', 'Return who owes what, and what is now waiting for approval.'],
            ],
            // The same four rungs over the register rather than the ledger. The wording of
            // `detect` matters: the agent reads what has been *marked*, and a student
            // nobody has marked is not a student who does not attend.
            'attendance_risk_scan' => [
                ['detect', 'Read attendance for the students in scope through the marked register.'],
                ['analyse', 'Open a case for each student below the bar, citing the days they were absent.'],
                ['recommend', 'Draft an attendance follow-up for each case.'],
                ['report', 'Return who is attending least, and what is now waiting for approval.'],
            ],
            // The same four rungs over the enquiry records. The wording of `detect`
            // matters: an enquiry with no follow-up date recorded is not an enquiry the
            // school is late on, and the agent counts those apart rather than with.
            'admission_followup_scan' => [
                ['detect', 'Read the open enquiries and the follow-up date recorded on each.'],
                ['analyse', 'Open a case for each enquiry past its own recorded date, citing that date.'],
                ['recommend', 'Draft an admission follow-up for each case.'],
                ['report', 'Return which families are waiting, and what is now waiting for approval.'],
            ],
            // `fees_query` is intentionally not deterministic. The classifier recognises
            // that a question belongs to the fees module, but the exact MCP tool depends
            // on the wording of the question itself — whether it asks for a cohort,
            // one student's pending balance, or a collection report. Letting the
            // generic module-tool planner handle it preserves the existing fee tools and
            // avoids a brittle one-off route for every fee phrase.
            //
            // A deterministic entry was added here once and had to come out. Claiming the
            // intent here is not additive: the plan written here becomes the only plan, so
            // its candidate list replaces the module's bound fee tools instead of adding
            // to them. That entry named `students.search` — `fees_query` was listed in
            // NEEDS_STUDENT too — which dropped `fees.arrears` and `fees.collection_report`
            // from the turn entirely, because stage 5 may only select from what the plan
            // proposed. It then routed to `fees_mcp_tools`, a name `LaravelMcpStage` does
            // not dispatch, so the turn fell through to subject resolution and answered
            // "the question did not identify a student or a case" to "which students have
            // pending fees?". Anything fees-specific belongs in the fee tools, the module
            // bindings or the classifier — all of which add without displacing.

            // Replaced wholesale by detailSteps() when the module binds a lookup tool.
            // This is the shape when it does not: the row the reader was shown is still a
            // real record read from the database a turn ago, and reporting it is a better
            // answer than refusing because no deeper lookup happens to be bound.
            'record_detail' => [
                ['resolve_record', 'Identify which row of the previous answer this refers to.'],
                ['report', 'Return what is held about that record, and what it is missing.'],
            ],
            'record_filter' => [
                ['resolve_filter', 'Read the field and value from the rows already shown.'],
                ['report', 'Return the rows that match, out of the ones the last answer returned.'],
            ],
            'fees_send_reminders' => [
                ['resolve', 'Read who is to be reminded and by when from the sentence.'],
                ['preview', 'Show who would be emailed, what each owes and the message.', 'fees.send_reminders'],
                ['approve', 'Put the list to a person — this emails real families.'],
                ['send', 'Send only what was approved, and record every outcome.'],
            ],
            'fees_reminder_status' => [
                ['read', 'Read the record of fee reminders already sent.', 'fees.reminder_history'],
                ['report', 'Return each reminder with its status.'],
            ],
            'channel_health' => [
                ['check', 'Ask the SMS gateway and Meta whether messages can reach parents.', 'communication.channel_health'],
                ['report', 'Name each problem found, and what the providers say about the recent messages.'],
            ],
            'parent_message_status' => [
                ['read', 'Read the saved record of parent messages already sent.', 'communication.parent_message_history'],
                ['report', 'Return each message with the provider\'s reply and what is known about delivery.'],
            ],
            'parent_message_send' => [
                ['resolve', 'Read the channel, the class or student and the wording from the sentence.'],
                ['preview', 'Show who would be messaged and exactly what it says.', 'communication.send_parent_message'],
                ['approve', 'Put the list to a person — this messages real families.'],
                ['send', 'Send only what was approved, and record what the provider answered.'],
            ],
            'admission_confirm' => [
                ['resolve_enquiry', 'Identify which admission enquiry is being confirmed.'],
                ['validate', 'Check what the admission still needs.', 'admissions.validateConfirmation'],
                ['collect', 'Ask the user for anything missing, across as many turns as it takes.'],
                ['approve', 'Put the confirmation to a person — this creates a student enrolment.'],
            ],
            default => null,
        };

        if ($steps === null) {
            return null;
        }

        return array_map(
            static fn (array $step) => new PlanStep(
                id: $step[0],
                purpose: $step[1],
                tool: $step[2] ?? null,
            ),
            $steps
        );
    }
}
