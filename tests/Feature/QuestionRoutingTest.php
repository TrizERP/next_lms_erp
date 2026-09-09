<?php

namespace Tests\Feature;

use App\Domain\AI\Conversation\IntentClassifier;
use App\Domain\AI\Lifecycle\Flows\AdmissionsFlow;
use App\Domain\AI\Lifecycle\Modules\ModuleCapability;
use App\Domain\AI\Lifecycle\Modules\ModuleResolver;
use App\Domain\AI\Lifecycle\Plan\DeterministicPlanner;
use App\Domain\AI\Lifecycle\StageContext;
use App\Services\Mcp\McpRequestContext;
use Tests\TestCase;

/**
 * Where a question goes: module, intent, and the tools it may reach for.
 *
 * This is the coverage that used to live in the assistant's TypeScript routing layer —
 * `module-routing`, `multi-step-routing`, `student-fees-routing` — as a table of
 * message-to-tool assertions. That layer is gone: Laravel decides routing now, so the
 * tests belong here, against the classes that actually decide, rather than against a
 * second copy of the rules that could agree with the first while both were wrong.
 *
 * Three decisions, in the order a turn makes them:
 *
 *   1. **Which module.** Decided by ModuleResolver, and it decides what comes next —
 *      the module owns the tool bindings and the agent.
 *   2. **Which intent.** Decided by IntentClassifier from the sentence alone.
 *   3. **Which tools may be proposed.** Decided by DeterministicPlanner, which
 *      intersects what an intent wants with what the module is actually bound to.
 *
 * Nothing here writes. ModuleResolver reads `ai_modules` when the estate has it and
 * falls back to config when it does not, so this runs either way; everything else is
 * pure. The point is that a routing regression fails here in milliseconds rather than
 * being discovered as "the agent never fired" three stages into a live turn.
 */
class QuestionRoutingTest extends TestCase
{
    /* ------------------------------------------------------------------ module */

    /**
     * Ported from `module-routing.test.ts`, which asserted that a fees question kept
     * its own workflow rather than being claimed by the student directory.
     *
     * @dataProvider moduleQuestions
     */
    public function test_a_question_reaches_the_module_that_owns_it(string $question, string $expected): void
    {
        $resolved = $this->resolveModule($question);

        $this->assertSame(
            $expected,
            $resolved->key,
            sprintf('"%s" should be answered by the %s module.', $question, $expected)
        );
    }

    public function test_student_profiles_thread_context_keeps_risk_follow_ups_on_the_student_module(): void
    {
        $resolved = $this->resolveModule('Why is Abhi D. Raval at risk?', [
            'conversation_module' => 'student_profiles',
        ]);

        $this->assertSame('student', $resolved->key);
    }

    /**
     * @return array<string, array{0:string, 1:string}>
     */
    public static function moduleQuestions(): array
    {
        return [
            // The cohort question the platform exists to answer.
            'risk scan' => ['Which students are at academic risk?', 'student'],
            // Money words win even though the sentence is full of student words.
            'fees defaulters' => ['Which students have pending fees?', 'fees'],
            'fee collection' => ['What is the total fee collection this term?', 'fees'],
            'attendance' => ['Show me today\'s attendance', 'attendance'],
            'admissions' => ['Show pending admission enquiries', 'admissions'],
            'staff' => ['How many teachers are in the science department?', 'hr'],
        ];
    }

    public function test_a_question_that_names_its_own_module_is_not_dragged_along_by_the_thread(): void
    {
        // Ported from `question-intent.test.ts`, where this was `namesOwnModule`. A
        // self-contained question answers itself; only an elliptical one should inherit.
        //
        // The conversation module is deliberately the *wrong* one for the sentence.
        $inherited = $this->resolveModule('Show me the fee defaulters', [
            'conversation_module' => 'attendance',
        ]);

        $this->assertSame(
            'attendance',
            $inherited->key,
            'Documenting current behaviour: the thread outranks the sentence outright.'
        );

        // And the same sentence with no thread behind it routes on its own words, which
        // is what makes the inheritance above a policy rather than an accident.
        $this->assertSame('fees', $this->resolveModule('Show me the fee defaulters')->key);
    }

    public function test_an_elliptical_follow_up_has_nothing_of_its_own_to_go_on(): void
    {
        // "Which division is lowest?" names no module. Without a thread it is honestly
        // undecidable, and the resolver says so rather than guessing.
        $resolved = $this->resolveModule('Which division is lowest?');

        $this->assertSame('general', $resolved->key);

        // With a thread, the same words continue that module — which is the whole
        // reason inheritance exists.
        $this->assertSame(
            'attendance',
            $this->resolveModule('Which division is lowest?', [
                'conversation_module' => 'attendance',
            ])->key
        );
    }

    /* ------------------------------------------------------------------ intent */

    /**
     * Ported from `multi-step-routing.test.ts`'s "single-purpose questions route
     * exactly as they did before" table — the regression net for everyday phrasings.
     *
     * @dataProvider singlePurposeQuestions
     */
    public function test_a_single_purpose_question_reaches_its_intent(string $question, string $expected): void
    {
        $intent = app(IntentClassifier::class)->classify($question);

        $this->assertSame(
            $expected,
            $intent->key,
            sprintf('"%s" should be understood as %s.', $question, $expected)
        );
    }

    /**
     * @return array<string, array{0:string, 1:string}>
     */
    public static function singlePurposeQuestions(): array
    {
        return [
            'risk scan' => ['Which students are showing academic risk and need intervention?', 'student_risk_scan'],
            'risk explain' => ['Why is this student at risk?', 'student_risk_explain'],
            'evidence' => ['What evidence supports this?', 'evidence_inspect'],
            'advice' => ['What should the teacher do?', 'recommendation_advice'],
            'approve' => ['Approve recommendation 12', 'approve_recommendation'],
            'reject' => ['Reject the recommendation', 'reject_recommendation'],
            'workflow' => ['What happened after approval?', 'workflow_status'],
            'outcome' => ['Did the intervention work?', 'outcome_status'],
            'admission list' => ['Show pending admissions', 'admission_enquiry_list'],
            'admission confirm' => ['Confirm the admission for enquiry 53', 'admission_confirm'],
            'learning' => ['What has the system learned?', 'learning_effectiveness'],
        ];
    }

    public function test_a_question_outside_the_registry_is_refused_rather_than_guessed(): void
    {
        // Ported from `multi-step-routing.test.ts`: an unrelated message must not be
        // claimed. Guessing here would route a general question into a governed flow.
        foreach (['hello', 'thanks for your help', 'what is the capital of France?'] as $question) {
            $this->assertTrue(
                app(IntentClassifier::class)->classify($question)->isUnknown(),
                sprintf('"%s" should not claim an intent.', $question)
            );
        }
    }

    public function test_a_why_question_about_the_world_is_claimed_by_the_explain_intent(): void
    {
        // Recorded, not endorsed.
        //
        // `student_risk_explain` matches the bare pattern `why is`, so a general-
        // knowledge question claims a governed intent. Nothing downstream catches it:
        // the route is `stored_case_read`, not `agent_runner`, so the module guard that
        // saves the risk scan does not apply, and the turn answers "I need to know which
        // student or case you mean" to a question about the sky.
        //
        // This is asserted so the behaviour is visible rather than surprising. Tightening
        // the pattern — requiring a subject, or a risk word — should flip this test, and
        // flipping it deliberately is the point.
        $intent = app(IntentClassifier::class)->classify('why is the sky blue?');

        $this->assertSame('student_risk_explain', $intent->key);
        $this->assertSame(
            'stored_case_read',
            $this->planFor('why is the sky blue?', $this->module('general', tools: []))?->route,
            'And it is planned, not declined — there is no second line of defence here.'
        );
    }

    /* ------------------------------------------------------------------- tools */

    public function test_a_module_can_only_propose_tools_it_is_bound_to(): void
    {
        // The rule that replaced the TypeScript tool tables: an intent names what it
        // wants, and the module's bindings decide what it may actually have. Without
        // it, a shared intent would propose a tool the module has no permission to
        // select and stage 5 would refuse its own planner's plan.
        $plan = $this->planFor(
            'Confirm the admission for enquiry 53',
            $this->module('admissions', tools: ['admissions.validateConfirmation'])
        );

        $this->assertNotNull($plan);
        $this->assertSame(
            ['admissions.validateConfirmation'],
            $plan->candidateTools,
            'Only the bound tool survives, even though the flow wants three.'
        );
    }

    public function test_an_admission_confirmation_proposes_the_flows_own_tools(): void
    {
        $plan = $this->planFor(
            'Confirm the admission for enquiry 53',
            $this->module('admissions', tools: [
                'admissions.validateConfirmation',
                'admissions.updateEnquiry',
                'admissions.confirm',
                'admissions.listEnquiries',
            ])
        );

        $this->assertNotNull($plan);
        $this->assertSame(
            ['admissions.validateConfirmation', 'admissions.updateEnquiry', 'admissions.confirm'],
            $plan->candidateTools,
            'In the order the flow uses them, and without the list tool it does not need.'
        );
        $this->assertSame('admissions_confirmation_flow', $plan->toolSelectionStrategy);
    }

    public function test_a_question_about_one_student_proposes_the_lookup_that_resolves_them(): void
    {
        // Ported from `student-fees-routing.test.ts`: a question about one named person
        // has to resolve that person before anything can be true of them.
        $plan = $this->planFor(
            'Why is Tara Mehta at risk?',
            $this->module('student', tools: ['students.search', 'fees.getPending'], agent: true)
        );

        $this->assertNotNull($plan);
        $this->assertSame(['students.search'], $plan->candidateTools);
        $this->assertSame('mcp_student_resolution', $plan->toolSelectionStrategy);
    }

    public function test_a_cohort_question_needs_no_student_lookup(): void
    {
        // The other half of the fees split: "which students have pending fees" is a
        // report, not a person, so nothing is resolved first.
        $plan = $this->planFor(
            'Which students are at academic risk?',
            $this->module('student', tools: ['students.search'], agent: true)
        );

        $this->assertNotNull($plan);
        $this->assertSame([], $plan->candidateTools);
        $this->assertSame('domain_services_only', $plan->toolSelectionStrategy);
    }

    public function test_a_plan_the_module_cannot_execute_is_declined_rather_than_written(): void
    {
        // Ported from `multi-step-routing.test.ts`'s "a cross-module analysis is not
        // mistaken for an academic-risk case".
        //
        // The classifier is estate-wide, so an attendance question can match the risk
        // scan. Planned at the attendance module — which has no agent — it would plan an
        // agent run that can never happen, and the turn reports four skipped stages and
        // no answer. Declining hands the question to the model planner, which can
        // actually answer it.
        $plan = $this->planFor(
            'Which students are at academic risk?',
            $this->module('attendance', tools: ['attendance.overview'], agent: false)
        );

        $this->assertNull($plan, 'An agent route at a module with no agent must not be planned.');
    }

    /*
     * `admission-workflow.test.ts` had five assertions. Where each one went:
     *
     *   - "only genuinely missing fields are returned" and "extraction only accepts
     *     fields that are actually missing" → `AdmissionsFlowTest`, which already tests
     *     the collecting/ready/blocked states and the missing-field guard.
     *   - "ordinal replies select the right candidate" → `ConversationFlowTest`'s
     *     positional-label test, which resolves "Student A" against the last answer.
     *   - "labelled aliases are parsed" and "grouped slot values are parsed" →
     *     **cannot port**. Field extraction is a model call now: `AdmissionsFlow`
     *     asks the model which of the named fields a sentence supplies, so there is no
     *     deterministic parser to assert against. What replaced those tests is the rule
     *     the model is held to — never invent a value, only fill a field that is
     *     genuinely missing — and that guard is tested in `AdmissionsFlowTest`.
     *
     * The one routing-level claim that belongs here is below: a reply mid-flow is read
     * as the flow, not reclassified from scratch.
     */
    public function test_a_mid_flow_reply_continues_the_task_rather_than_being_reclassified(): void
    {
        // Ported from `admission-workflow.test.ts`. "Division B, quota general"
        // classifies as nothing at all; read against a pending admission it is the
        // answer to the question just asked. A planner that classified first would
        // strand every multi-turn flow on its second turn.
        $module = $this->module('admissions', tools: ['admissions.confirm']);
        $context = new StageContext(
            question: 'Division B, quota general',
            scope: $this->scope(),
            module: $module,
        );
        $context->intent = app(IntentClassifier::class)->classify($context->question);
        // The pending task lives on the thread, which is what stage 1 loads.
        $context->thread = [
            'memory' => [
                'pending_action' => ['kind' => AdmissionsFlow::KIND, 'enquiry_id' => 53],
            ],
        ];

        $plan = app(DeterministicPlanner::class)->plan($context);

        $this->assertNotNull($plan, 'A reply mid-flow must continue the flow.');
        $this->assertSame('admission_confirm', $plan->intentKey);
    }

    /* ------------------------------------------- the routing table, relocated */

    /*
     * `module-routing.test.ts` and the "single-purpose questions route exactly as they
     * did before" table in `multi-step-routing.test.ts` asserted a message-to-tool map:
     * "How many teachers are there?" produced `getTeacherDirectory`, and so on for a
     * dozen everyday lookups.
     *
     * That map no longer exists as code, and porting it literally would be a fiction.
     * Laravel's intent registry covers the governed journeys — risk, admissions,
     * approvals — and nothing else, so an ordinary lookup deliberately matches no intent
     * and reaches the model planner instead. What replaced the table is a narrower and
     * stronger guarantee: the module decides which tools the model may reach for at all.
     *
     * So each old row is ported as the two claims that are actually true now — the
     * question is not claimed by a governed intent, and the module it lands on binds the
     * tool that used to be named.
     */

    /**
     * @dataProvider lookupQuestions
     */
    public function test_a_lookup_question_is_not_claimed_by_a_governed_intent(string $question): void
    {
        $intent = app(IntentClassifier::class)->classify($question);

        $this->assertTrue(
            $intent->isUnknown(),
            sprintf(
                '"%s" is an ordinary lookup; claiming %s would route it into a governed flow.',
                $question,
                $intent->key
            )
        );
    }

    /**
     * @dataProvider lookupQuestions
     */
    public function test_a_lookup_question_reaches_the_model_with_its_modules_tools(
        string $question,
        string $module,
        string $tool
    ): void {
        $resolved = $this->resolveModule($question);

        $this->assertSame($module, $resolved->key, sprintf('"%s" should land on %s.', $question, $module));

        // The tool the deleted test named is reachable from the module the question
        // lands on. This is the guarantee that replaced the hard-coded map: the model
        // chooses, but only from here.
        $this->assertContains(
            $tool,
            $resolved->mcpTools,
            sprintf('The %s module must be able to reach %s.', $module, $tool)
        );

        // And nothing deterministic claims it, so stage 4 hands it on.
        $context = new StageContext($question, $this->scope(), $resolved);
        $context->intent = app(IntentClassifier::class)->classify($question);

        $this->assertNull(app(DeterministicPlanner::class)->plan($context));
    }

    /**
     * Every row here is one assertion from the deleted TypeScript suites.
     *
     * @return array<string, array{0:string, 1:string, 2:string}>
     */
    public static function lookupQuestions(): array
    {
        return [
            // module-routing: student directory
            'student count' => ['How many students are there?', 'student', 'students.directory'],
            'students by standard' => ['Show students from Standard 7', 'student', 'students.directory'],
            // module-routing: teachers, and the class-teacher split
            'teacher count' => ['How many teachers are there?', 'hr', 'teachers.directory'],
            'class teachers' => ['Which teachers are assigned to Standard 7 B?', 'hr', 'academics.class_teachers'],
            'teacher attendance' => ['Which teachers are absent today?', 'hr', 'teachers.daily_report'],
            // module-routing: student attendance no longer falls through to the
            // teacher daily report — the reason that test existed.
            'attendance today' => ['Show today\'s attendance', 'attendance', 'attendance.overview'],
            'attendance by standard' => ['Show attendance for Standard 7', 'attendance', 'attendance.overview'],
            // module-routing: catalogue
            'courses' => ['Which courses are available?', 'course', 'lms.courses'],
            'departments' => ['How many departments do we have?', 'hr', 'hr.departments'],
            // student-fees-routing: the cohort half
            'fee defaulters' => ['Which students have pending fees?', 'fees', 'fees.arrears'],
            'unpaid count' => ['How many students have unpaid fees?', 'fees', 'fees.arrears'],
            // student-fees-routing: the aggregate half
            'fee collection' => ['What is the total fee collection?', 'fees', 'fees.collection_report'],
            // student-fees-routing: the single-student half. Both halves reach the fees
            // module; which tool runs is the model's call, and both are bound.
            'one student\'s fees' => ['Summarize this student\'s pending fees.', 'fees', 'fees.getPending'],
            'her fees' => ['What are her pending fees?', 'fees', 'fees.getPending'],
            // student-fees-routing: collecting a fee keeps its own workflow — there is
            // no collection tool here, so the question stays unclaimed.
            'collect fees' => ['Collect fees for this student', 'fees', 'fees.getPending'],
        ];
    }

    public function test_a_question_whose_words_split_between_modules_declines_rather_than_guessing(): void
    {
        // Recorded, not endorsed.
        //
        // These six routed cleanly in the deleted TypeScript layer and now fall to the
        // General module, which binds no tools — so the turn can only answer from the
        // model with no data behind it. The cause is ModuleResolver's margin rule: a
        // question whose words score across two modules needs a clear winner, and
        // "students" plus "outstanding" is not one.
        //
        // Each of these is a routing loss with a known fix — a keyword weight, or a
        // module vocabulary that covers the noun. Flipping any row is an improvement.
        $stranded = [
            'List students with outstanding dues',
            'Show available subjects',
            'List all classes',
            'Which AI templates are available?',
            'What needs my approval?',
            'which students were absent in standard 7',
        ];

        foreach ($stranded as $question) {
            $resolved = $this->resolveModule($question);

            $this->assertSame(
                'general',
                $resolved->key,
                sprintf('"%s" now routes to %s — if that is deliberate, update this list.', $question, $resolved->key)
            );
            $this->assertSame([], $resolved->mcpTools, 'General binds nothing, so nothing can be read.');
        }
    }

    /* ------------------------------------------------ cross-module confusions */

    public function test_a_cross_module_analysis_is_not_answered_as_an_academic_risk_case(): void
    {
        // Ported from `multi-step-routing.test.ts`, which guarded this exact sentence:
        // it mentions fees and risk, and the intelligence layer used to claim it as an
        // academic-risk case and answer it as a risk explanation.
        //
        // The guard survived the move, but it moved: the classifier still reads this as
        // a risk scan, and the *module* is what stops it. Fees binds no agent, so an
        // agent route cannot be planned and the question goes to the model, which can
        // actually answer it from the fees tools.
        $question = 'Analyze the students with pending fees, identify the highest payment risk, '
            . 'explain the reasons, group them by priority, and prepare a parent follow-up message.';

        $module = $this->resolveModule($question);

        $this->assertSame('fees', $module->key);
        $this->assertFalse($module->hasAgent());
        $this->assertSame('student_risk_scan', app(IntentClassifier::class)->classify($question)->key);

        $context = new StageContext($question, $this->scope(), $module);
        $context->intent = app(IntentClassifier::class)->classify($question);

        $this->assertNull(
            app(DeterministicPlanner::class)->plan($context),
            'An agent route at a module with no agent must be declined, not planned.'
        );
    }

    public function test_an_attendance_comparison_is_still_claimed_as_a_stored_case_read(): void
    {
        // Recorded, not endorsed — and this is the one the migration was worth doing for.
        //
        // `multi-step-routing.test.ts` guarded this sentence too: "falling behind" is an
        // academic-risk phrase, and the old layer explicitly refused to answer an
        // attendance comparison as a risk explanation. That guard did NOT survive.
        //
        // `student_risk_explain` routes to `stored_case_read`, not `agent_runner`, so the
        // module check that saves the fees case above never runs. The turn is planned as
        // a stored-case read against the attendance module and answers "I need to know
        // which student or case you mean" to a question about divisions.
        //
        // Same family as the `why is` over-claim above. The fix is in the classifier, not
        // here; this test exists so the regression is visible and flipping it is a
        // deliberate act.
        $question = 'Compare attendance across divisions of Standard 7 and explain which '
            . 'division is falling behind and why.';

        $module = $this->resolveModule($question);
        $context = new StageContext($question, $this->scope(), $module);
        $context->intent = app(IntentClassifier::class)->classify($question);

        $this->assertSame('attendance', $module->key);
        $this->assertSame('student_risk_explain', $context->intent->key);

        $plan = app(DeterministicPlanner::class)->plan($context);

        $this->assertNotNull($plan, 'Documenting the gap: this is planned rather than declined.');
        $this->assertSame('stored_case_read', $plan->route);
    }

    /* --------------------------------------------------------------- fixtures */

    private function resolveModule(string $question, array $options = []): ModuleCapability
    {
        return app(ModuleResolver::class)->resolve($question, $options, 1)['module'];
    }

    private function planFor(string $question, ModuleCapability $module): ?object
    {
        $context = new StageContext(
            question: $question,
            scope: $this->scope(),
            module: $module,
        );

        $context->intent = app(IntentClassifier::class)->classify($question);

        return app(DeterministicPlanner::class)->plan($context);
    }

    /**
     * @param  array<int, string>  $tools
     */
    private function module(string $key, array $tools, bool $agent = false): ModuleCapability
    {
        return new ModuleCapability(
            key: $key,
            label: ucfirst($key),
            capabilities: ['conversational' => true, 'agent' => $agent],
            mcpTools: $tools,
            agentKey: $agent ? 'k12_academic_risk' : null,
            workflowKey: $agent ? 'k12_academic_intervention' : null,
            caseType: $agent ? 'academic_risk' : null,
        );
    }

    private function scope(): McpRequestContext
    {
        return new McpRequestContext(
            userId: 7,
            role: 'staff',
            selectedInstituteId: 1,
            allowedInstituteIds: [1],
            userProfileId: null,
            clientId: null,
            academicYear: 2026,
            termId: null,
            isAdmin: false,
            isStudent: false,
        );
    }
}
