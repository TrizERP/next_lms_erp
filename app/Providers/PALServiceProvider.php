<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\PAL\Administration\ArchitectureHealthService;
use App\Services\PAL\Administration\ArchitectureRegistry;
use App\Services\PAL\Runtime\PalEvidenceRepository;
use App\Services\PAL\Runtime\SubsystemRuntime;
use App\Services\PAL\Intelligence\IntelligenceService;
use App\Services\PAL\Intelligence\LearnerStateEngine;
use App\Services\PAL\Intelligence\LearningVelocityEngine;
use App\Services\PAL\Intelligence\MisconceptionIntelligenceEngine;
use App\Services\PAL\Intelligence\PredictiveInterventionEngine;
use App\Services\PAL\Intelligence\BehavioralAnalyticsService;
use App\Services\PAL\Pedagogy\PedagogyOrchestrationService;
use App\Services\PAL\Content\SemanticIntelligenceSource;
use App\Services\PAL\Pedagogy\PedagogyEngineResolver;
use App\Services\PAL\Pedagogy\PedagogyEngineService;
use App\Services\PAL\Pedagogy\PedagogySelectorEngine;
use App\Services\PAL\Pedagogy\CognitiveLoadEngine;
use App\Services\PAL\Pedagogy\EmotionalSafetyEngine;
use App\Services\PAL\Pedagogy\MetacognitionEngine;
use App\Services\PAL\Pedagogy\PedagogyFatigueEngine;
use App\Services\PAL\Content\ContentIntelligenceService;
use App\Services\PAL\Framework\FrameworkCatalogService;
use App\Services\PAL\Framework\FrameworkProgressService;
use App\Services\PAL\Telemetry\TelemetryService;
use App\Services\PAL\ULU\ULUGraphService;
use App\Services\PAL\ULU\ULUService;
use App\Services\PAL\AI\AIOrchestrationService;
use App\Services\PAL\Integration\PedagogySuggestedContentService;

class PALServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Load all PAL models (they are grouped in one file)
        require_once app_path('Models/PAL/Models.php');

        // Intelligence Layer Services
        $this->app->singleton(LearnerStateEngine::class, function () {
            return new LearnerStateEngine();
        });
        
        $this->app->singleton(LearningVelocityEngine::class, function () {
            return new LearningVelocityEngine();
        });
        
        $this->app->singleton(MisconceptionIntelligenceEngine::class, function () {
            return new MisconceptionIntelligenceEngine();
        });
        
        $this->app->singleton(PredictiveInterventionEngine::class, function () {
            return new PredictiveInterventionEngine();
        });
        
        $this->app->singleton(BehavioralAnalyticsService::class, function () {
            return new BehavioralAnalyticsService();
        });
        
        $this->app->singleton(IntelligenceService::class, function ($app) {
            return new IntelligenceService(
                $app->make(LearnerStateEngine::class),
                $app->make(LearningVelocityEngine::class),
                $app->make(MisconceptionIntelligenceEngine::class),
                $app->make(PredictiveInterventionEngine::class),
                $app->make(BehavioralAnalyticsService::class)
            );
        });

        // Pedagogy Engine Services
        $this->app->singleton(FrameworkCatalogService::class, function () {
            return new FrameworkCatalogService();
        });

        $this->app->singleton(FrameworkProgressService::class, function ($app) {
            return new FrameworkProgressService(
                $app->make(FrameworkCatalogService::class)
            );
        });

        $this->app->singleton(ULUGraphService::class, function ($app) {
            return new ULUGraphService($app->bound(\App\Services\Neo4jService::class) ? $app->make(\App\Services\Neo4jService::class) : null);
        });

        $this->app->singleton(ULUService::class, function ($app) {
            return new ULUService(
                $app->make(FrameworkCatalogService::class),
                $app->make(ULUGraphService::class)
            );
        });

        $this->app->singleton(PedagogySelectorEngine::class, function ($app) {
            return new PedagogySelectorEngine(
                $app->make(FrameworkCatalogService::class)
            );
        });

        // Reads extracted chapter intelligence out of `semantic_intelligence`.
        $this->app->singleton(SemanticIntelligenceSource::class, function () {
            return new SemanticIntelligenceSource();
        });

        // Executes the Pedagogy Engine rules against a real concept.
        $this->app->singleton(PedagogyEngineResolver::class, function ($app) {
            return new PedagogyEngineResolver(
                $app->make(FrameworkCatalogService::class)
            );
        });

        // Read model for the Pedagogy Engine served to the PAL UI.
        $this->app->singleton(PedagogyEngineService::class, function ($app) {
            return new PedagogyEngineService(
                $app->make(FrameworkCatalogService::class),
                $app->make(SemanticIntelligenceSource::class),
                $app->make(PedagogyEngineResolver::class)
            );
        });

        $this->app->singleton(CognitiveLoadEngine::class, function () {
            return new CognitiveLoadEngine();
        });
        
        $this->app->singleton(EmotionalSafetyEngine::class, function () {
            return new EmotionalSafetyEngine();
        });
        
        $this->app->singleton(MetacognitionEngine::class, function () {
            return new MetacognitionEngine();
        });
        
        $this->app->singleton(PedagogyFatigueEngine::class, function () {
            return new PedagogyFatigueEngine();
        });
        
        $this->app->singleton(PedagogyOrchestrationService::class, function ($app) {
            return new PedagogyOrchestrationService(
                $app->make(PedagogySelectorEngine::class),
                $app->make(CognitiveLoadEngine::class),
                $app->make(EmotionalSafetyEngine::class),
                $app->make(MetacognitionEngine::class),
                $app->make(PedagogyFatigueEngine::class)
            );
        });

        // Content Intelligence
        $this->app->singleton(ContentIntelligenceService::class, function () {
            return new ContentIntelligenceService();
        });

        // Telemetry Service
        $this->app->singleton(TelemetryService::class, function ($app) {
            return new TelemetryService(
                $app->make(FrameworkCatalogService::class),
                $app->make(FrameworkProgressService::class)
            );
        });

        // AI Orchestration Service
        $this->app->singleton(AIOrchestrationService::class, function () {
            return new AIOrchestrationService();
        });

        // LMS integration
        $this->app->singleton(PedagogySuggestedContentService::class, function ($app) {
            return new PedagogySuggestedContentService(
                $app->make(PedagogyOrchestrationService::class)
            );
        });

        // Administration — the architecture control plane. The registry is
        // stateless; the health service memoises its probes for the life of a
        // request, so it is bound per request rather than as a singleton to
        // avoid a stale report inside a queue worker.
        $this->app->singleton(ArchitectureRegistry::class, function () {
            return new ArchitectureRegistry();
        });

        $this->app->bind(ArchitectureHealthService::class, function ($app) {
            return new ArchitectureHealthService(
                $app->make(FrameworkCatalogService::class)
            );
        });

        // Runtime — the live layer. Reads the real learner evidence out of the
        // legacy PAL stack and runs the engines over it, so a subsystem page
        // shows what its configuration actually produces.
        $this->app->singleton(PalEvidenceRepository::class, function () {
            return new PalEvidenceRepository();
        });

        $this->app->bind(SubsystemRuntime::class, function ($app) {
            return new SubsystemRuntime(
                $app->make(PalEvidenceRepository::class),
                $app->make(ArchitectureRegistry::class)
            );
        });
    }

    public function boot(): void
    {
        // Load routes
        $this->loadRoutesFrom(base_path('routes/pal_api.php'));
    }
}
