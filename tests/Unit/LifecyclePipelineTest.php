<?php

namespace Tests\Unit;

use App\Domain\AI\Conversation\Intent;
use App\Domain\AI\Lifecycle\LifecyclePipeline;
use App\Domain\AI\Lifecycle\LifecycleStage;
use App\Domain\AI\Lifecycle\LifecycleTrace;
use App\Domain\AI\Lifecycle\Modules\ModuleCapability;
use App\Domain\AI\Lifecycle\Plan\DeterministicPlanner;
use App\Domain\AI\Lifecycle\Plan\HybridPlanner;
use App\Domain\AI\Lifecycle\Plan\LlmPlanner;
use App\Domain\AI\Lifecycle\Plan\Plan;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\StageKey;
use App\Domain\AI\Lifecycle\StageOutcome;
use App\Domain\AI\Lifecycle\StageStatus;
use App\Services\Mcp\McpRequestContext;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The twelve-stage lifecycle's contracts.
 *
 * Extends PHPUnit's TestCase rather than Tests\TestCase, matching GovernanceKernelTest:
 * these are pure unit tests with no database or container dependency, so they still run
 * when the application cannot boot — which matters, because this pipeline is what a
 * broken boot would otherwise hide.
 */
class LifecyclePipelineTest extends TestCase
{
    // ---------------------------------------------------------------- stage keys

    public function test_the_ladder_is_twelve_stages_in_the_product_order(): void
    {
        $order = array_map(
            fn (StageKey $key) => $key->value,
            StageKey::inDisplayOrder()
        );

        $this->assertSame([
            'conversation',
            'generative_ai',
            'agent',
            'planning',
            'mcp_tool_selection',
            'laravel_mcp',
            'real_data',
            'evidence',
            'reasoning',
            'recommendation',
            'human_approval',
            'action',
        ], $order);
    }

    public function test_planning_and_tool_selection_execute_before_the_agent(): void
    {
        // A turn must decide to make an agent run before making it — the agent is the
        // most expensive thing here, and the product ladder shows it at position 3 only
        // because that is where the capability sits in the story.
        $this->assertLessThan(
            StageKey::Agent->executionOrder(),
            StageKey::Planning->executionOrder()
        );

        $this->assertLessThan(
            StageKey::Agent->executionOrder(),
            StageKey::McpToolSelection->executionOrder()
        );

        // And the product ladder deliberately disagrees: it shows Agent at 3, ahead of
        // Planning at 4. Both orders are real, which is exactly why the enum carries
        // them separately rather than deriving one from the other.
        $this->assertLessThan(
            StageKey::Planning->displayOrder(),
            StageKey::Agent->displayOrder()
        );
    }

    public function test_downstream_names_every_later_stage_and_no_earlier_one(): void
    {
        $downstream = array_map(
            fn (StageKey $key) => $key->value,
            StageKey::Planning->downstream()
        );

        $this->assertContains('agent', $downstream);
        $this->assertContains('action', $downstream);
        $this->assertNotContains('planning', $downstream);
        $this->assertNotContains('conversation', $downstream);
        $this->assertNotContains('generative_ai', $downstream);
    }

    // ---------------------------------------------------------------- outcomes

    public function test_a_not_reached_stage_always_carries_a_reason(): void
    {
        $outcome = StageOutcome::notReached('Nothing had been approved yet.');

        $this->assertSame(StageStatus::NotReached, $outcome->status);
        $this->assertSame('', $outcome->summary);
        $this->assertSame('Nothing had been approved yet.', $outcome->note);
    }

    public function test_a_null_note_leaves_the_outcome_unchanged(): void
    {
        $outcome = StageOutcome::ran('It ran.')->withNote('a reason')->withNote(null);

        $this->assertSame('a reason', $outcome->note);
    }

    public function test_halting_records_the_reason_for_downstream_stages(): void
    {
        $outcome = StageOutcome::blocked('Refused.')->halting('Nothing downstream ran.');

        $this->assertTrue($outcome->halts());
        $this->assertSame('Nothing downstream ran.', $outcome->halt);
    }

    // ---------------------------------------------------------------- the trace

    public function test_a_fresh_trace_holds_all_twelve_stages_with_reasons(): void
    {
        $stages = (new LifecycleTrace())->toArray();

        $this->assertCount(12, $stages);

        foreach ($stages as $stage) {
            $this->assertSame('not_reached', $stage['status']);
            $this->assertNotSame('', trim((string) $stage['note']), "{$stage['key']} has no reason");
        }
    }

    public function test_a_halt_does_not_rewrite_a_stage_that_already_reported(): void
    {
        // The halt stops what follows; it does not revise history. A stage that ran
        // before something later failed must keep its own report.
        $trace = new LifecycleTrace();
        $trace->record(StageKey::Conversation, StageOutcome::ran('Thread opened.'));
        $trace->markNotReached(StageKey::Conversation, 'should not appear');

        $this->assertSame(StageStatus::Ran, $trace->statusOf(StageKey::Conversation));
        $this->assertSame('Thread opened.', $trace->outcomeOf(StageKey::Conversation)->summary);
    }

    public function test_depth_counts_stages_reached_rather_than_stages_that_ran(): void
    {
        // A turn that legitimately waits at the human gate has covered the lifecycle.
        // Scoring it by stages that "ran" would read a governed stop as a failure.
        $trace = new LifecycleTrace();
        $trace->record(StageKey::Conversation, StageOutcome::ran('opened'));
        $trace->record(StageKey::Recommendation, StageOutcome::ran('drafted'));
        $trace->record(StageKey::HumanApproval, StageOutcome::pending('waiting on a teacher'));

        $this->assertSame(11, $trace->depthReached());
    }

    // ---------------------------------------------------------------- the runner

    public function test_stages_run_in_execution_order_regardless_of_registration_order(): void
    {
        $ran = [];

        $pipeline = new LifecyclePipeline([
            $this->stage(StageKey::Action, $ran),
            $this->stage(StageKey::Conversation, $ran),
            $this->stage(StageKey::Planning, $ran),
        ]);

        $pipeline->run($this->context());

        $this->assertSame(['conversation', 'planning', 'action'], $ran);
    }

    public function test_a_halting_stage_marks_every_downstream_stage_with_its_reason(): void
    {
        $ran = [];

        $pipeline = new LifecyclePipeline([
            $this->stage(StageKey::Conversation, $ran),
            $this->stage(
                StageKey::Planning,
                $ran,
                StageOutcome::blocked('Not understood.')->halting('Nothing ran: guessing would be worse.')
            ),
            $this->stage(StageKey::Agent, $ran),
            $this->stage(StageKey::Action, $ran),
        ]);

        $trace = $pipeline->run($this->context());

        // The downstream stages must not have executed at all.
        $this->assertSame(['conversation', 'planning'], $ran);

        foreach ([StageKey::Agent, StageKey::Action] as $key) {
            $this->assertSame(StageStatus::NotReached, $trace->statusOf($key));
            $this->assertSame(
                'Nothing ran: guessing would be worse.',
                $trace->outcomeOf($key)->note
            );
        }
    }

    public function test_a_stage_that_throws_becomes_a_refusal_carrying_the_error(): void
    {
        // A crash is not an empty result. Reporting an exception as "nothing found"
        // is how "the analysis died" becomes "nobody is at risk".
        $ran = [];

        $pipeline = new LifecyclePipeline([
            $this->throwingStage(StageKey::Agent),
            $this->stage(StageKey::Action, $ran),
        ]);

        $trace = $pipeline->run($this->context());

        $agent = $trace->outcomeOf(StageKey::Agent);

        $this->assertSame(StageStatus::Blocked, $agent->status);
        $this->assertSame('the detector table is missing', $agent->data['error']);
        $this->assertSame(StageStatus::NotReached, $trace->statusOf(StageKey::Action));
        $this->assertSame([], $ran, 'a crash must stop the turn');
    }

    public function test_the_runner_times_every_stage(): void
    {
        $ran = [];
        $pipeline = new LifecyclePipeline([$this->stage(StageKey::Conversation, $ran)]);

        $trace = $pipeline->run($this->context());

        $this->assertNotNull($trace->outcomeOf(StageKey::Conversation)->durationMs);
    }

    // ---------------------------------------------------------------- planning

    public function test_a_known_intent_plans_deterministically(): void
    {
        $context = $this->context();
        $context->intent = new Intent('student_risk_scan', 'Find students at risk', 0.91);

        $plan = (new DeterministicPlanner())->plan($context);

        $this->assertInstanceOf(Plan::class, $plan);
        $this->assertSame(Plan::SOURCE_DETERMINISTIC, $plan->source);
        $this->assertSame('agent_runner', $plan->route);
        $this->assertTrue($plan->runsAgent());
        $this->assertNotEmpty($plan->steps);
    }

    public function test_the_deterministic_planner_declines_what_it_does_not_recognise(): void
    {
        $context = $this->context();
        $context->intent = Intent::unknown();

        $this->assertNull((new DeterministicPlanner())->plan($context));
    }

    public function test_a_plan_only_proposes_tools_the_module_is_bound_to(): void
    {
        // Otherwise a shared intent would name a tool the module has no permission to
        // select, and tool selection would have to refuse a plan its own planner wrote.
        $context = $this->context(new ModuleCapability(
            key: 'student',
            label: 'Student',
            capabilities: ['conversational' => true, 'agent' => true],
            mcpTools: [],
            agentKey: 'k12_academic_risk',
        ));

        $context->intent = new Intent('student_risk_explain', 'Explain', 0.9, ['student_name' => 'Ravi']);

        $plan = (new DeterministicPlanner())->plan($context);

        $this->assertSame([], $plan->candidateTools);
        $this->assertSame('domain_services_only', $plan->toolSelectionStrategy);
    }

    public function test_consequential_wording_never_reaches_the_model(): void
    {
        // If "approve the thing" did not classify, the honest outcome is that nothing
        // was understood — not a model's best guess at which record to change.
        $llm = new class extends LlmPlanner
        {
            public bool $called = false;

            public function __construct()
            {
            }

            public function plan(StageContext $context): ?Plan
            {
                $this->called = true;

                return null;
            }
        };

        $planner = new HybridPlanner(new DeterministicPlanner(), $llm);

        $context = $this->context();
        $context->intent = Intent::unknown();

        $this->assertNull($planner->plan($context = $this->contextAsking('Approve the intervention for Ravi')));
        $this->assertFalse($llm->called, 'a consequential sentence must not be model-planned');
    }

    public function test_an_ordinary_unmatched_question_does_reach_the_model(): void
    {
        $llm = new class extends LlmPlanner
        {
            public bool $called = false;

            public function __construct()
            {
            }

            public function plan(StageContext $context): ?Plan
            {
                $this->called = true;

                return null;
            }
        };

        $planner = new HybridPlanner(new DeterministicPlanner(), $llm);
        $planner->plan($this->contextAsking('Which students have the lowest attendance this term?'));

        $this->assertTrue($llm->called);
    }

    // ---------------------------------------------------------------- helpers

    private function context(?ModuleCapability $module = null): StageContext
    {
        return new StageContext(
            question: 'Which students are at academic risk?',
            scope: $this->scope(),
            module: $module ?? $this->studentModule(),
        );
    }

    private function contextAsking(string $question): StageContext
    {
        $context = new StageContext(
            question: $question,
            scope: $this->scope(),
            module: $this->studentModule(),
        );

        $context->intent = Intent::unknown();

        return $context;
    }

    private function studentModule(): ModuleCapability
    {
        return new ModuleCapability(
            key: 'student',
            label: 'Student',
            capabilities: ['conversational' => true, 'agent' => true, 'workflow' => true],
            mcpTools: ['students.search'],
            agentKey: 'k12_academic_risk',
            workflowKey: 'k12_academic_intervention',
            caseType: 'academic_risk',
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

    /**
     * @param  array<int, string>  $ran  Written to by reference as stages execute.
     */
    private function stage(StageKey $key, array &$ran, ?StageOutcome $outcome = null): LifecycleStage
    {
        return new class($key, $ran, $outcome) implements LifecycleStage
        {
            /**
             * @param  array<int, string>  $ran
             */
            public function __construct(
                private readonly StageKey $key,
                private array &$ran,
                private readonly ?StageOutcome $outcome,
            ) {
            }

            public function key(): StageKey
            {
                return $this->key;
            }

            public function run(StageContext $context): StageOutcome
            {
                $this->ran[] = $this->key->value;

                return $this->outcome ?? StageOutcome::ran('ran');
            }
        };
    }

    private function throwingStage(StageKey $key): LifecycleStage
    {
        return new class($key) implements LifecycleStage
        {
            public function __construct(private readonly StageKey $key)
            {
            }

            public function key(): StageKey
            {
                return $this->key;
            }

            public function run(StageContext $context): StageOutcome
            {
                throw new RuntimeException('the detector table is missing');
            }
        };
    }
}
