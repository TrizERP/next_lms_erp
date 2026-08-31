<?php

namespace App\Providers;

use App\Domain\AI\Agents\AgentRegistry;
use App\Domain\AI\Agents\AgentRunner;
use App\Domain\AI\Cases\CaseBuilder;
use App\Domain\AI\Decisions\DecisionGate;
use App\Domain\AI\Evidence\EvidenceStore;
use App\Domain\AI\Explanations\ExplanationBuilder;
use App\Domain\AI\Outcomes\OutcomeTracker;
use App\Domain\AI\Recommendations\RecommendationDrafter;
use App\Domain\AI\Signals\SignalStore;
use App\Domain\AI\Signals\ThresholdRegistry;
use App\Domain\AI\Support\AiAuditLogger;
use App\Domain\AI\Workspace\AiContextService;
use App\Domain\AI\Workspace\CapabilityResolver;
use App\Domain\AI\Workspace\FlowStateResolver;
use App\Domain\AI\Workspace\OntologyViewResolver;
use App\Domain\AI\Workspace\PageTypeResolver;
use App\Domain\AI\Workspace\RouteMatcher;
use App\Domain\GenerativeAI\GenerationService;
use App\Domain\GenerativeAI\OutputValidator;
use App\Domain\GenerativeAI\SafetyChecker;
use App\Domain\Governance\EsoBindingRule;
use App\Domain\Governance\ExplainVerb;
use App\Domain\Governance\GovernanceValidator;
use App\Domain\Governance\GroundedClaims;
use App\Domain\Governance\RecommendVerb;
use App\Domain\K12\AcademicRisk\AcademicRiskAgent;
use App\Domain\K12\AcademicRisk\AssessmentDeclineDetector;
use App\Domain\K12\AcademicRisk\AttendanceRiskDetector;
use App\Domain\K12\AcademicRisk\CreateAcademicInterventionAction;
use App\Domain\K12\AcademicRisk\Metrics\AssessmentAverageResolver;
use App\Domain\K12\AcademicRisk\Metrics\AttendanceRateResolver;
use App\Domain\K12\AcademicRisk\MissedAssignmentDetector;
use App\Domain\K12\AcademicRisk\StudentScope;
use App\Domain\KnowledgeGraph\GraphQueryService;
use App\Domain\Ontology\EntityResolver;
use App\Domain\Ontology\OntologyRegistry;
use App\Domain\Templates\TemplateRegistry;
use App\Domain\Workflow\Actions\ActionRegistry;
use App\Domain\Workflow\ConditionEvaluator;
use App\Domain\Workflow\StepHandlerRegistry;
use App\Domain\Workflow\Steps\ActionStepHandler;
use App\Domain\Workflow\Steps\AgentStepHandler;
use App\Domain\Workflow\Steps\ApprovalStepHandler;
use App\Domain\Workflow\Steps\GenerateStepHandler;
use App\Domain\Workflow\Steps\MeasureStepHandler;
use App\Domain\Workflow\Steps\NotifyStepHandler;
use App\Services\Neo4jService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Wires the shared intelligence layer.
 *
 * Modelled on McpServiceProvider: singletons, tagged collections, and
 * `loadRoutesFrom`. Nothing here touches RouteServiceProvider or any existing
 * provider, so the 578 legacy controllers and 32 route files are unaffected.
 *
 * Registration is the only place step handlers, workflow actions and metric
 * resolvers are declared. That is intentional — it makes "what can this system
 * actually do?" answerable by reading one file.
 */
class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('ai.php'), 'ai');

        $this->registerFoundation();
        $this->registerGovernance();
        $this->registerIntelligence();
        $this->registerGeneration();
        $this->registerWorkflow();
        $this->registerAgents();
        $this->registerWorkspace();
        $this->registerLifecycle();
        $this->registerK12();
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/ai.php'));

        RateLimiter::for('ai', function (Request $request) {
            $limit = (int) config('ai.rate_limit.per_minute', 60);
            $auth = $request->attributes->get('mcp_auth', []);

            return Limit::perMinute($limit)->by($auth['user_id'] ?? $request->ip());
        });
    }

    // ---- Ontology, graph, audit -------------------------------------------

    private function registerFoundation(): void
    {
        $this->app->singleton(OntologyRegistry::class);
        $this->app->singleton(AiAuditLogger::class);
        $this->app->singleton(ThresholdRegistry::class);
        $this->app->singleton(StudentScope::class);

        $this->app->singleton(
            EntityResolver::class,
            fn ($app) => new EntityResolver($app->make(OntologyRegistry::class))
        );

        // Neo4j is optional at runtime. If the driver cannot be constructed the graph
        // service falls back to SQL rather than the whole layer failing to boot.
        $this->app->singleton(GraphQueryService::class, function ($app) {
            $neo4j = null;

            if (config('ai.knowledge_graph.prefer_graph', true)) {
                try {
                    $neo4j = $app->make(Neo4jService::class);
                } catch (Throwable) {
                    $neo4j = null;
                }
            }

            return new GraphQueryService($app->make(OntologyRegistry::class), $neo4j);
        });
    }

    // ---- Governance kernel -------------------------------------------------

    private function registerGovernance(): void
    {
        $this->app->singleton(GroundedClaims::class);
        $this->app->singleton(EsoBindingRule::class);

        $this->app->singleton(
            ExplainVerb::class,
            fn ($app) => new ExplainVerb($app->make(GroundedClaims::class))
        );

        $this->app->singleton(RecommendVerb::class, fn ($app) => new RecommendVerb(
            $app->make(GroundedClaims::class),
            $app->make(EsoBindingRule::class)
        ));

        $this->app->singleton(GovernanceValidator::class, fn ($app) => new GovernanceValidator(
            $app->make(ExplainVerb::class),
            $app->make(RecommendVerb::class),
            $app->make(GroundedClaims::class),
            $app->make(EsoBindingRule::class)
        ));
    }

    // ---- Signal, evidence, case, explanation, recommendation, decision -----

    private function registerIntelligence(): void
    {
        $this->app->singleton(
            EvidenceStore::class,
            fn ($app) => new EvidenceStore($app->make(AiAuditLogger::class))
        );

        $this->app->singleton(SignalStore::class, fn ($app) => new SignalStore(
            $app->make(EvidenceStore::class),
            $app->make(AiAuditLogger::class)
        ));

        $this->app->singleton(CaseBuilder::class, fn ($app) => new CaseBuilder(
            $app->make(SignalStore::class),
            $app->make(EvidenceStore::class),
            $app->make(ThresholdRegistry::class),
            $app->make(AiAuditLogger::class)
        ));

        $this->app->singleton(ExplanationBuilder::class, fn ($app) => new ExplanationBuilder(
            $app->make(GovernanceValidator::class),
            $app->make(AiAuditLogger::class)
        ));

        $this->app->singleton(RecommendationDrafter::class, fn ($app) => new RecommendationDrafter(
            $app->make(GovernanceValidator::class),
            $app->make(AiAuditLogger::class)
        ));

        $this->app->singleton(DecisionGate::class, fn ($app) => new DecisionGate(
            $app->make(AiAuditLogger::class),
            $app->make(EsoBindingRule::class)
        ));

        // Metric resolvers: a metric with no resolver cannot be measured, so this
        // list is effectively the set of outcomes the platform can honestly score.
        $this->app->tag([
            AssessmentAverageResolver::class,
            AttendanceRateResolver::class,
        ], 'ai.metric_resolvers');

        $this->app->singleton(OutcomeTracker::class, fn ($app) => new OutcomeTracker(
            $app->make(AiAuditLogger::class),
            $app->tagged('ai.metric_resolvers')
        ));
    }

    // ---- Templates and generation -----------------------------------------

    private function registerGeneration(): void
    {
        $this->app->singleton(TemplateRegistry::class);
        $this->app->singleton(OutputValidator::class);
        $this->app->singleton(SafetyChecker::class);

        $this->app->singleton(GenerationService::class, fn ($app) => new GenerationService(
            $app->make(TemplateRegistry::class),
            $app->make(OutputValidator::class),
            $app->make(SafetyChecker::class),
            $app->make(AiAuditLogger::class),
            $app->make(\App\Domain\AI\Support\OpenRouterClient::class)
        ));
    }

    // ---- Workflow ----------------------------------------------------------

    private function registerWorkflow(): void
    {
        $this->app->singleton(ConditionEvaluator::class);

        // Everything a workflow is allowed to DO. Nothing outside this list is
        // reachable from a workflow definition, however it is written.
        $this->app->tag([
            CreateAcademicInterventionAction::class,
        ], 'workflow.actions');

        $this->app->singleton(
            ActionRegistry::class,
            fn ($app) => new ActionRegistry($app->tagged('workflow.actions'))
        );

        $this->app->singleton(ActionStepHandler::class, fn ($app) => new ActionStepHandler(
            $app->make(ActionRegistry::class),
            $app->make(AiAuditLogger::class)
        ));

        $this->app->singleton(ApprovalStepHandler::class);

        $this->app->singleton(
            NotifyStepHandler::class,
            fn ($app) => new NotifyStepHandler($app->make(AiAuditLogger::class))
        );

        $this->app->singleton(
            GenerateStepHandler::class,
            fn ($app) => new GenerateStepHandler($app->make(GenerationService::class))
        );

        $this->app->singleton(
            MeasureStepHandler::class,
            fn ($app) => new MeasureStepHandler($app->make(OutcomeTracker::class))
        );

        $this->app->singleton(
            AgentStepHandler::class,
            fn ($app) => new AgentStepHandler($app->make(AgentRunner::class))
        );

        $this->app->tag([
            ActionStepHandler::class,
            ApprovalStepHandler::class,
            NotifyStepHandler::class,
            GenerateStepHandler::class,
            MeasureStepHandler::class,
            AgentStepHandler::class,
        ], 'workflow.step_handlers');

        $this->app->singleton(
            StepHandlerRegistry::class,
            fn ($app) => new StepHandlerRegistry($app->tagged('workflow.step_handlers'))
        );

        $this->app->singleton(\App\Domain\Workflow\WorkflowEngine::class, fn ($app) => new \App\Domain\Workflow\WorkflowEngine(
            $app->make(StepHandlerRegistry::class),
            $app->make(ConditionEvaluator::class),
            $app->make(GovernanceValidator::class),
            $app->make(AiAuditLogger::class)
        ));
    }

    // ---- Agents ------------------------------------------------------------

    private function registerAgents(): void
    {
        $this->app->singleton(AgentRegistry::class);

        $this->app->singleton(AgentRunner::class, fn ($app) => new AgentRunner(
            $app->make(AgentRegistry::class),
            $app,
            $app->make(GovernanceValidator::class),
            $app->make(AiAuditLogger::class),
            $app->make(EntityResolver::class),
            $app->make(GraphQueryService::class),
            $app->make(SignalStore::class),
            $app->make(EvidenceStore::class),
            $app->make(CaseBuilder::class),
            $app->make(ExplanationBuilder::class),
            $app->make(RecommendationDrafter::class)
        ));
    }

    // ---- AI Workspace ------------------------------------------------------

    /**
     * The unified panel's resolvers. Route→module→entity mapping and per-module
     * capability config both live in the database, so these are thin services over
     * `ai_modules`, `ai_suggestions` and `ai_ontology_views` rather than logic that
     * would need a deploy to change.
     */
    private function registerWorkspace(): void
    {
        $this->app->singleton(RouteMatcher::class);

        // Stateless, and consulted on every panel open: the page-type rules come from
        // config, so a singleton just avoids re-reading them per request.
        $this->app->singleton(PageTypeResolver::class, fn ($app) => new PageTypeResolver(
            $app->make(RouteMatcher::class),
            (array) config('ai.page_types', [])
        ));

        $this->app->singleton(AiContextService::class, fn ($app) => new AiContextService(
            $app->make(RouteMatcher::class),
            $app->make(EntityResolver::class),
            $app->make(OntologyRegistry::class),
            $app->make(PageTypeResolver::class)
        ));

        $this->app->singleton(
            CapabilityResolver::class,
            fn ($app) => new CapabilityResolver(
                $app->make(AgentRegistry::class),
                $app->make(PageTypeResolver::class)
            )
        );

        // Stateless: it derives the stage from the rows on every call rather than
        // holding anything, so a singleton is safe and cheap.
        $this->app->singleton(FlowStateResolver::class);

        $this->app->singleton(OntologyViewResolver::class, fn ($app) => new OntologyViewResolver(
            $app->make(GraphQueryService::class),
            $app->make(OntologyRegistry::class)
        ));
    }

    // ---- The twelve-stage lifecycle ----------------------------------------

    /**
     * One pipeline, twelve stages, one class each.
     *
     * The tagged list below is the lifecycle. Reading it tells you every stage the
     * platform has, in one place, and the pipeline sorts them into execution order
     * itself — so a stage cannot be accidentally left out of the middle of the ladder by
     * a registration written in the wrong order.
     *
     * Everything here is additive. While `ai.lifecycle.enabled` is false these bindings
     * are constructed but never invoked, and /ask keeps using the previous service.
     */
    private function registerLifecycle(): void
    {
        $this->app->singleton(\App\Domain\AI\Support\OpenRouterClient::class);

        $this->app->singleton(
            \App\Domain\AI\Lifecycle\Modules\ModuleRegistry::class,
            fn ($app) => new \App\Domain\AI\Lifecycle\Modules\ModuleRegistry(
                $app->make(AgentRegistry::class)
            )
        );

        $this->app->singleton(
            \App\Domain\AI\Lifecycle\Modules\ModuleResolver::class,
            fn ($app) => new \App\Domain\AI\Lifecycle\Modules\ModuleResolver(
                $app->make(\App\Domain\AI\Lifecycle\Modules\ModuleRegistry::class),
                $app->make(RouteMatcher::class)
            )
        );

        $this->app->singleton(
            \App\Domain\AI\Lifecycle\Support\McpToolCaller::class,
            fn ($app) => new \App\Domain\AI\Lifecycle\Support\McpToolCaller(
                $app->make(\App\Mcp\ToolRegistry::class)
            )
        );

        $this->app->singleton(
            \App\Domain\AI\Lifecycle\Support\CaseResolver::class,
            fn ($app) => new \App\Domain\AI\Lifecycle\Support\CaseResolver(
                $app->make(CaseBuilder::class),
                $app->make(EntityResolver::class)
            )
        );

        // ---- planning: deterministic first, model second --------------------

        $this->app->singleton(\App\Domain\AI\Lifecycle\Plan\DeterministicPlanner::class);

        $this->app->singleton(
            \App\Domain\AI\Lifecycle\Plan\LlmPlanner::class,
            fn ($app) => new \App\Domain\AI\Lifecycle\Plan\LlmPlanner(
                $app->make(\App\Domain\AI\Support\OpenRouterClient::class),
                $app->make(\App\Mcp\ToolRegistry::class)
            )
        );

        $this->app->singleton(
            \App\Domain\AI\Lifecycle\Plan\HybridPlanner::class,
            fn ($app) => new \App\Domain\AI\Lifecycle\Plan\HybridPlanner(
                $app->make(\App\Domain\AI\Lifecycle\Plan\DeterministicPlanner::class),
                $app->make(\App\Domain\AI\Lifecycle\Plan\LlmPlanner::class)
            )
        );

        // ---- the twelve stages ----------------------------------------------

        $this->app->tag([
            \App\Domain\AI\Lifecycle\Stages\ConversationalAiStage::class,
            \App\Domain\AI\Lifecycle\Stages\GenerativeAiStage::class,
            \App\Domain\AI\Lifecycle\Stages\AgentStage::class,
            \App\Domain\AI\Lifecycle\Stages\PlanningStage::class,
            \App\Domain\AI\Lifecycle\Stages\McpToolSelectionStage::class,
            \App\Domain\AI\Lifecycle\Stages\LaravelMcpStage::class,
            \App\Domain\AI\Lifecycle\Stages\RealDataStage::class,
            \App\Domain\AI\Lifecycle\Stages\EvidenceStage::class,
            \App\Domain\AI\Lifecycle\Stages\ReasoningStage::class,
            \App\Domain\AI\Lifecycle\Stages\RecommendationStage::class,
            \App\Domain\AI\Lifecycle\Stages\HumanApprovalStage::class,
            \App\Domain\AI\Lifecycle\Stages\ActionStage::class,
        ], 'ai.lifecycle_stages');

        $this->app->singleton(
            \App\Domain\AI\Lifecycle\LifecyclePipeline::class,
            fn ($app) => new \App\Domain\AI\Lifecycle\LifecyclePipeline(
                $app->tagged('ai.lifecycle_stages')
            )
        );

        $this->app->singleton(
            \App\Domain\AI\Lifecycle\LifecycleAskService::class,
            fn ($app) => new \App\Domain\AI\Lifecycle\LifecycleAskService(
                $app->make(\App\Domain\AI\Lifecycle\Modules\ModuleResolver::class),
                $app->make(\App\Domain\AI\Lifecycle\LifecyclePipeline::class),
                $app->make(\App\Domain\AI\Conversation\ConversationStore::class),
                $app->make(\App\Domain\AI\Conversation\AnswerComposer::class)
            )
        );

        // The one place the cutover flag is read. Both the HTTP endpoint and
        // `php artisan ai:journey` go through this, so the terminal can never be
        // verifying a different pipeline than the API is serving.
        $this->app->singleton(
            \App\Domain\AI\Conversation\AskPipeline::class,
            fn ($app) => new \App\Domain\AI\Conversation\AskPipeline(
                $app->make(\App\Domain\AI\Lifecycle\LifecycleAskService::class),
                $app->make(\App\Domain\AI\Conversation\AskService::class)
            )
        );
    }

    // ---- K-12 domain -------------------------------------------------------

    private function registerK12(): void
    {
        $this->app->singleton(AssessmentDeclineDetector::class);
        $this->app->singleton(AttendanceRiskDetector::class);
        $this->app->singleton(MissedAssignmentDetector::class);
        $this->app->singleton(AcademicRiskAgent::class);
    }
}
