<?php

namespace Tests\Unit;

use App\Domain\AI\Conversation\ConversationalNarrator;
use App\Domain\AI\Conversation\Intent;
use App\Domain\AI\Configuration\ResolvedAiConfiguration;
use App\Domain\AI\Lifecycle\Modules\ModuleCapability;
use App\Domain\AI\Lifecycle\Plan\DeterministicPlanner;
use App\Domain\AI\Lifecycle\Plan\HybridPlanner;
use App\Domain\AI\Lifecycle\Plan\LlmPlanner;
use App\Domain\AI\Lifecycle\Plan\ModuleReadPlanner;
use App\Domain\AI\Lifecycle\Plan\Plan;
use App\Domain\AI\Lifecycle\Plan\PlanStep;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\Support\RecordDetail;
use App\Domain\AI\Support\ModelClient;
use App\Services\Mcp\McpRequestContext;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Conversation over the Fees data: follow-ups, and a spoken answer that stays true.
 *
 * Pure unit tests - no database, no container, no model. The model is a stub that returns
 * what the test tells it to, which is the only way to prove the parts that must hold
 * whatever a real model says: what the planner is shown, when a model may take over a
 * turn, and that a narration citing a figure the tools never returned is thrown away.
 */
class ConversationalFollowUpTest extends TestCase
{
    // ------------------------------------------------- what the planner is shown

    public function test_the_planner_is_shown_the_rows_the_previous_answer_listed(): void
    {
        $prompt = $this->userPrompt($this->contextWithPreviousList('Show me their classes.'));

        $this->assertStringContainsString('Which students have the highest pending fees?', $prompt);
        $this->assertStringContainsString('1. Abhi D. Raval [student_id=190488]', $prompt);
        $this->assertStringContainsString('2. Milan . Baldaniya [student_id=199480]', $prompt);
        $this->assertStringContainsString('Question: Show me their classes.', $prompt);
    }

    public function test_a_first_question_carries_no_earlier_answer(): void
    {
        $context = $this->context('How much is pending?');
        $context->thread = ['memory' => []];

        $prompt = $this->userPrompt($context);

        $this->assertStringNotContainsString('Earlier in this conversation', $prompt);
        $this->assertStringContainsString('Question: How much is pending?', $prompt);
    }

    // ------------------------------------------------ when a model takes over

    public function test_a_model_plan_replaces_the_list_filter_and_the_intent_follows_it(): void
    {
        $llm = $this->llmReturning($this->toolPlan());
        $planner = new HybridPlanner(new DeterministicPlanner($this->createMock(RecordDetail::class)), $llm, new ModuleReadPlanner());

        $context = $this->contextWithPreviousList('Which of them has the oldest outstanding payment?');
        $context->intent = new Intent('record_filter', 'Narrow the previous answer', 0.7);

        $plan = $planner->plan($context);

        $this->assertSame(Plan::SOURCE_LLM, $plan->source);
        $this->assertSame(['fees.outstanding_accounts'], $plan->candidateTools);
        $this->assertSame('follow_up', $context->intent->key, 'the reasoning stage keys off the intent and would re-filter otherwise');
    }

    public function test_the_list_filter_still_runs_when_the_model_has_no_plan(): void
    {
        $planner = new HybridPlanner(
            new DeterministicPlanner($this->createMock(RecordDetail::class)),
            $this->llmReturning(null),
            new ModuleReadPlanner()
        );

        $context = $this->contextWithPreviousList('Only the ones over 5000');
        $context->intent = new Intent('record_filter', 'Narrow the previous answer', 0.7);

        $plan = $planner->plan($context);

        $this->assertSame(Plan::SOURCE_DETERMINISTIC, $plan->source);
        $this->assertSame('record_filter', $plan->intentKey);
        $this->assertSame('record_filter', $context->intent->key);
    }

    public function test_a_model_plan_with_no_lookups_does_not_replace_the_filter(): void
    {
        $noLookups = new Plan(
            goal: 'Answer from memory',
            steps: [new PlanStep(id: 'reason', purpose: 'Think', tool: null)],
            source: Plan::SOURCE_LLM,
        );

        $planner = new HybridPlanner(
            new DeterministicPlanner($this->createMock(RecordDetail::class)),
            $this->llmReturning($noLookups),
            new ModuleReadPlanner()
        );

        $context = $this->contextWithPreviousList('Only the ones over 5000');
        $context->intent = new Intent('record_filter', 'Narrow the previous answer', 0.7);

        $this->assertSame(Plan::SOURCE_DETERMINISTIC, $planner->plan($context)->source);
    }

    // ------------------------------------------------------ the spoken answer

    public function test_a_narration_using_only_returned_figures_is_kept(): void
    {
        $narrator = $this->narratorSaying(
            '₹2,92,299 is pending across 66 of 75 students. Class 7 carries the most, at ₹1,46,000.'
        );

        $reply = $narrator->narrate($this->contextWithTools(), '₹2,92,299 is pending across 66 of 75 students.');

        $this->assertNotNull($reply);
        $this->assertStringContainsString('Class 7', $reply);
    }

    public function test_a_narration_with_a_figure_the_tools_never_returned_is_discarded(): void
    {
        // 3,10,000 is not in the data: a total the model added up for itself.
        $narrator = $this->narratorSaying('About ₹3,10,000 is pending across 66 students.');

        $this->assertNull($narrator->narrate($this->contextWithTools(), 'draft'));
    }

    public function test_an_unconfigured_model_leaves_the_headline_alone(): void
    {
        $this->assertNull($this->narratorSaying('₹2,92,299 is pending.', configured: false)
            ->narrate($this->contextWithTools(), 'draft'));
    }

    public function test_a_turn_with_an_action_is_never_paraphrased(): void
    {
        $context = $this->contextWithTools();
        $context->addAction(['label' => 'Approve', 'utterance' => 'Approve it']);

        $this->assertNull($this->narratorSaying('₹2,92,299 is pending.')->narrate($context, 'draft'));
    }

    public function test_a_turn_with_no_tool_results_is_not_narrated(): void
    {
        $this->assertNull($this->narratorSaying('Anything at all.')->narrate($this->context('hello'), 'draft'));
    }

    // ---------------------------------------------------------------- helpers

    private function userPrompt(StageContext $context): string
    {
        $planner = (new ReflectionClass(LlmPlanner::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(LlmPlanner::class, 'userPrompt');
        $method->setAccessible(true);

        return $method->invoke($planner, $context);
    }

    private function llmReturning(?Plan $plan): LlmPlanner
    {
        return new class($plan) extends LlmPlanner {
            public function __construct(private readonly ?Plan $canned)
            {
            }

            public function plan(StageContext $context): ?Plan
            {
                return $this->canned;
            }
        };
    }

    private function toolPlan(): Plan
    {
        return new Plan(
            goal: 'Read the oldest unpaid month for those students',
            steps: [new PlanStep(
                id: 'accounts',
                purpose: 'Read them again with due months',
                tool: 'fees.outstanding_accounts',
                arguments: ['student_ids' => [190488, 199480], 'include_due_months' => true],
            )],
            source: Plan::SOURCE_LLM,
            route: 'mcp_tools',
            candidateTools: ['fees.outstanding_accounts'],
        );
    }

    private function narratorSaying(string $reply, bool $configured = true): ConversationalNarrator
    {
        $client = new class($reply, $configured) implements ModelClient {
            public function __construct(private readonly string $reply, private readonly bool $configured)
            {
            }

            public function isConfigured(): bool
            {
                return $this->configured;
            }

            public function forInstitute(int|string|null $subInstituteId): static
            {
                return $this;
            }

            public function withConfiguration(ResolvedAiConfiguration $configuration): static
            {
                return $this;
            }

            public function defaultModel(): string
            {
                return 'stub';
            }

            public function chat(array $messages, ?string $model = null, ?int $maxTokens = null, ?float $temperature = null, bool $expectJson = false, ?int $timeout = null): ?string
            {
                return $this->reply;
            }

            public function stream(array $messages, callable $onDelta, ?string $model = null, ?int $maxTokens = null, ?float $temperature = null): ?string
            {
                return $this->reply;
            }

            public function json(array $messages, ?string $model = null, int $maxTokens = 900, float $temperature = 0.0): ?array
            {
                return null;
            }
        };

        return new ConversationalNarrator($client);
    }

    private function contextWithTools(): StageContext
    {
        $context = $this->context('How much pending fee amount is there right now?');
        $context->thread = ['memory' => []];
        $context->set('mcp_step_results', [
            'position' => [
                'success' => true,
                'data' => [
                    'headline' => '₹2,92,299 is pending across 66 of 75 students.',
                    'total_outstanding' => 292299,
                    'students_owing' => 66,
                    'fee_accounts' => 75,
                    'classes_with_most_pending' => [
                        ['standard_name' => 'Class 7', 'outstanding' => 146000],
                    ],
                ],
            ],
        ]);

        return $context;
    }

    private function contextWithPreviousList(string $question): StageContext
    {
        $context = $this->context($question);
        $context->thread = ['memory' => ['last_result_set' => [
            'module' => 'fees',
            'tool' => 'fees.outstanding_accounts',
            'noun' => 'accounts',
            'singular' => 'student',
            'id_field' => 'student_id',
            'question' => 'Which students have the highest pending fees?',
            'total' => 2,
            'items' => [
                ['position' => 1, 'id' => 190488, 'title' => 'Abhi D. Raval',
                    'row' => ['student_id' => 190488, 'student_name' => 'Abhi D. Raval', 'outstanding' => 16899]],
                ['position' => 2, 'id' => 199480, 'title' => 'Milan . Baldaniya',
                    'row' => ['student_id' => 199480, 'student_name' => 'Milan . Baldaniya', 'outstanding' => 10000]],
            ],
        ]]];

        return $context;
    }

    private function context(string $question): StageContext
    {
        return new StageContext(
            question: $question,
            scope: new McpRequestContext(
                userId: 7, role: 'admin', selectedInstituteId: 1, allowedInstituteIds: [1],
                userProfileId: null, clientId: null, academicYear: 2022, termId: null,
                isAdmin: true, isStudent: false,
            ),
            module: new ModuleCapability(
                key: 'fees',
                label: 'Fees',
                capabilities: ['conversational' => true],
                mcpTools: ['fees.position', 'fees.outstanding_accounts'],
            ),
        );
    }
}
