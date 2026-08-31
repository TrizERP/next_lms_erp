<?php

namespace App\Domain\AI\Lifecycle;

/**
 * The twelve lifecycle stages, and the two different orders they live in.
 *
 * `displayOrder` is the product's order — the ladder in the diagram, the one a reader
 * checks. `executionOrder` is the order the pipeline actually runs them in, and the two
 * genuinely differ: a turn has to plan and select its tools *before* it runs an agent,
 * but the product reads Agent at position 3 because that is where the capability sits
 * in the story. Encoding both is honest; forcing one to match the other would mean
 * either a misleading diagram or a pipeline that plans after it acts.
 *
 * Stages 7 through 12 are mostly reporters rather than executors: the agent at stage 3
 * is what produces signals, evidence, cases and a drafted recommendation, and those
 * stages read the artifacts it left behind. That is a real property of the design, not
 * a shortcut — and it is why every stage implements the same interface but only some of
 * them write to the context.
 */
enum StageKey: string
{
    case Conversation = 'conversation';
    case GenerativeAi = 'generative_ai';
    case Agent = 'agent';
    case Planning = 'planning';
    case McpToolSelection = 'mcp_tool_selection';
    case LaravelMcp = 'laravel_mcp';
    case RealData = 'real_data';
    case Evidence = 'evidence';
    case Reasoning = 'reasoning';
    case Recommendation = 'recommendation';
    case HumanApproval = 'human_approval';
    case Action = 'action';

    /** Position in the product ladder — what the console renders. */
    public function displayOrder(): int
    {
        return match ($this) {
            self::Conversation => 1,
            self::GenerativeAi => 2,
            self::Agent => 3,
            self::Planning => 4,
            self::McpToolSelection => 5,
            self::LaravelMcp => 6,
            self::RealData => 7,
            self::Evidence => 8,
            self::Reasoning => 9,
            self::Recommendation => 10,
            self::HumanApproval => 11,
            self::Action => 12,
        };
    }

    /**
     * Position in the run.
     *
     * Planning and tool selection move ahead of the agent, because an agent run is the
     * most expensive thing this pipeline does and deciding to make it is a planning
     * decision. Everything from Real Data onward reports on what already happened.
     */
    public function executionOrder(): int
    {
        return match ($this) {
            self::Conversation => 1,
            self::GenerativeAi => 2,
            self::Planning => 3,
            self::McpToolSelection => 4,
            self::LaravelMcp => 5,
            self::Agent => 6,
            self::RealData => 7,
            self::Evidence => 8,
            self::Reasoning => 9,
            self::Recommendation => 10,
            self::HumanApproval => 11,
            self::Action => 12,
        };
    }

    public function layer(): string
    {
        return match ($this) {
            self::Conversation => 'Conversational AI',
            self::GenerativeAi => 'Generative AI',
            self::Agent => 'Agent',
            self::Planning => 'Planning',
            self::McpToolSelection => 'MCP Tool Selection',
            self::LaravelMcp => 'Laravel MCP',
            self::RealData => 'Real Data',
            self::Evidence => 'Evidence',
            self::Reasoning => 'Reasoning',
            self::Recommendation => 'Recommendation',
            self::HumanApproval => 'Human Approval',
            self::Action => 'Action',
        };
    }

    /**
     * The class that genuinely owns this stage, so a reader can open it.
     *
     * A stage may override this at runtime — the Agent stage names the agent it
     * actually ran, which is more useful than naming the runner every time.
     */
    public function component(): string
    {
        return match ($this) {
            self::Conversation => 'App\\Domain\\AI\\Conversation\\ConversationStore',
            self::GenerativeAi => 'App\\Domain\\AI\\Conversation\\IntentClassifier + App\\Domain\\GenerativeAI\\GenerationService',
            self::Agent => 'App\\Domain\\AI\\Agents\\AgentRunner',
            self::Planning => 'App\\Domain\\AI\\Lifecycle\\Plan\\HybridPlanner',
            self::McpToolSelection => 'App\\Domain\\AI\\Lifecycle\\Plan\\ToolSelector',
            self::LaravelMcp => 'App\\Mcp\\ToolRegistry -> App\\Http\\Controllers\\Mcp\\ToolsCallController',
            self::RealData => 'App\\Domain\\AI\\Signals\\SignalDetector implementations',
            self::Evidence => 'App\\Domain\\AI\\Evidence\\EvidenceStore',
            self::Reasoning => 'EntityResolver + CaseBuilder + ExplanationBuilder + GovernanceValidator',
            self::Recommendation => 'App\\Domain\\AI\\Recommendations\\RecommendationDrafter',
            self::HumanApproval => 'App\\Domain\\AI\\Decisions\\DecisionGate',
            self::Action => 'App\\Domain\\Workflow\\WorkflowEngine',
        };
    }

    /** Where in the product a user sees the result of this stage. */
    public function surface(): string
    {
        return match ($this) {
            self::Conversation => 'AI console — the question thread',
            self::GenerativeAi => 'AI console — the understood intent, and any generated text',
            self::Agent => 'AI Administration → Agent runs',
            self::Planning => 'AI console — lifecycle trace',
            self::McpToolSelection => 'AI console — lifecycle trace',
            self::LaravelMcp => 'MCP tool audit and the resulting data-backed answer',
            self::RealData => 'The source records the detectors read',
            self::Evidence => 'Case → Evidence',
            self::Reasoning => 'Case explanation, reasoning and cited claims',
            self::Recommendation => 'Approvals inbox — drafted action',
            self::HumanApproval => 'Approve / Reject actions',
            self::Action => 'The changed record — interventions, assignments, notifications',
        };
    }

    /**
     * Every stage, in the order the pipeline runs them.
     *
     * @return array<int, self>
     */
    public static function inExecutionOrder(): array
    {
        $cases = self::cases();

        usort($cases, static fn (self $a, self $b) => $a->executionOrder() <=> $b->executionOrder());

        return $cases;
    }

    /**
     * Every stage, in the order the console renders them.
     *
     * @return array<int, self>
     */
    public static function inDisplayOrder(): array
    {
        $cases = self::cases();

        usort($cases, static fn (self $a, self $b) => $a->displayOrder() <=> $b->displayOrder());

        return $cases;
    }

    /**
     * The stages that run after this one.
     *
     * This is what lets a halting stage mark its own downstream honestly, instead of
     * every caller hand-writing the list — which is precisely how the old service ended
     * up with five separate copies of the same `foreach` and one of them out of date.
     *
     * @return array<int, self>
     */
    public function downstream(): array
    {
        return array_values(array_filter(
            self::inExecutionOrder(),
            fn (self $stage) => $stage->executionOrder() > $this->executionOrder()
        ));
    }
}
