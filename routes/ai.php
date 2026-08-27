<?php

use App\Http\Controllers\AI\AgentController;
use App\Http\Controllers\AI\AskController;
use App\Http\Controllers\AI\CaseController;
use App\Http\Controllers\AI\GenerationController;
use App\Http\Controllers\AI\OntologyController;
use App\Http\Controllers\AI\OutcomeController;
use App\Http\Controllers\AI\RecommendationController;
use App\Http\Controllers\AI\WorkflowController;
use App\Http\Controllers\AI\WorkspaceController;
use App\Http\Middleware\McpAuth;
use App\Http\Middleware\McpContextHydrator;
use App\Http\Middleware\McpRateLimit;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Shared intelligence API
|--------------------------------------------------------------------------
|
| Deliberately behind the same middleware stack as routes/mcp.php: McpAuth for the
| JWT, McpRateLimit for abuse, and McpContextHydrator to put a scoped
| McpRequestContext on the request. Controllers read the scope from that context and
| never from request input, so an authenticated caller cannot name another school.
|
| Loaded by AiServiceProvider, mirroring how McpServiceProvider loads routes/mcp.php.
| Nothing is registered in RouteServiceProvider, so no existing route file is touched.
|
*/

Route::prefix(config('ai.route_prefix', 'api/ai'))
    ->middleware(['api'])
    ->group(function () {
        Route::middleware([McpAuth::class, McpRateLimit::class, McpContextHydrator::class])->group(function () {

            /*
            | AI Workspace — the unified panel.
            |
            | `context` is the single call the assistant makes when it opens: it returns
            | the resolved module and entity, the suggestions for each of the five tabs,
            | and anything already in flight for the record on screen. Every other route
            | here is an action one of those tabs dispatches, and each still runs through
            | the layer that governs it — AgentRunner, WorkflowEngine, GenerationService.
            */
            // GET is the shape §23 documents and is handy to inspect by hand; POST is
            // what the panel uses, because selected records and page data do not
            // belong in a query string. Same handler either way.
            Route::match(['get', 'post'], '/workspace/context', [WorkspaceController::class, 'context']);
            Route::match(['get', 'post'], '/workspace/flow', [WorkspaceController::class, 'flowState']);
            Route::post('/workspace/workflow-status', [WorkspaceController::class, 'workflowStatus']);
            Route::post('/workspace/generate', [WorkspaceController::class, 'generate']);
            Route::post('/workspace/agents/{agent}/run', [WorkspaceController::class, 'runAgent'])
                ->where('agent', '[a-z0-9_\-]+');
            Route::post('/workspace/workflows/{workflow}/start', [WorkspaceController::class, 'startWorkflow'])
                ->where('workflow', '[a-z0-9_\-]+');
            Route::post('/workspace/ontology-views/{view}', [WorkspaceController::class, 'ontologyView'])
                ->where('view', '[a-z0-9_\-]+');

            /*
            | Ask — the conversational front door.
            |
            | `ask` is the only route that runs the whole architecture from a sentence,
            | and the only one that returns the fifteen-stage trace of what each layer
            | did. `interpret` is its read-only half, for confirming that a rephrasing
            | still lands on the intent you expect without running anything.
            */
            Route::post('/ask', [AskController::class, 'ask']);
            Route::post('/ask/interpret', [AskController::class, 'interpret']);
            Route::get('/ask/intents', [AskController::class, 'intents']);
            Route::get('/conversations/{conversation}', [AskController::class, 'conversation'])
                ->whereNumber('conversation');

            /*
            | Signals, cases and the evidence chain — all reads.
            */
            Route::get('/signals', [CaseController::class, 'signals']);
            Route::get('/cases', [CaseController::class, 'index']);
            Route::get('/cases/{case}', [CaseController::class, 'show'])->whereNumber('case');
            Route::get('/cases/{case}/evidence', [CaseController::class, 'evidence'])->whereNumber('case');
            Route::get('/cases/{case}/explanation', [CaseController::class, 'explanation'])->whereNumber('case');
            Route::get('/cases/{case}/recommendations', [CaseController::class, 'recommendations'])->whereNumber('case');
            Route::post('/cases/{case}/status', [CaseController::class, 'updateStatus'])->whereNumber('case');

            // Everything the intelligence layer knows about one record — the payload
            // behind the AI tabs on a student or teacher profile.
            Route::get('/subjects/{entity}/{id}', [CaseController::class, 'forSubject'])
                ->where('entity', '[a-z_]+')
                ->whereNumber('id');

            /*
            | Recommendations and the human approval gate.
            */
            Route::get('/recommendations/pending', [RecommendationController::class, 'pending']);
            Route::get('/recommendations/{recommendation}', [RecommendationController::class, 'show'])
                ->whereNumber('recommendation');
            Route::post('/recommendations/{recommendation}/approve', [RecommendationController::class, 'approve'])
                ->whereNumber('recommendation');
            Route::post('/recommendations/{recommendation}/reject', [RecommendationController::class, 'reject'])
                ->whereNumber('recommendation');
            Route::post('/recommendations/{recommendation}/defer', [RecommendationController::class, 'defer'])
                ->whereNumber('recommendation');

            /*
            | Agents.
            */
            Route::get('/agents', [AgentController::class, 'index']);
            Route::get('/agent-runs', [AgentController::class, 'runs']);
            Route::get('/agents/{agent}', [AgentController::class, 'show'])->where('agent', '[a-z0-9_\-]+');
            Route::post('/agents/{agent}/run', [AgentController::class, 'run'])->where('agent', '[a-z0-9_\-]+');

            /*
            | Workflows. Note there is no "run this step" route: a run advances only
            | through the engine or by resolving an approval.
            */
            Route::get('/workflows', [WorkflowController::class, 'index']);
            Route::post('/workflows/{workflow}/execute', [WorkflowController::class, 'execute'])
                ->where('workflow', '[a-z0-9_\-]+');
            Route::get('/workflow-runs', [WorkflowController::class, 'runs']);
            Route::get('/workflow-runs/{run}', [WorkflowController::class, 'status'])->whereNumber('run');
            Route::get('/approvals/pending', [WorkflowController::class, 'pendingApprovals']);
            Route::post('/approvals/{approval}/resolve', [WorkflowController::class, 'resolveApproval'])
                ->whereNumber('approval');

            /*
            | Ontology and Knowledge Graph.
            */
            Route::get('/ontology/entities', [OntologyController::class, 'entities']);
            Route::get('/ontology/relationships', [OntologyController::class, 'relationships']);
            Route::post('/ontology/resolve', [OntologyController::class, 'resolve']);
            Route::post('/ontology/candidates', [OntologyController::class, 'candidates']);
            Route::get('/knowledge-graph/relations/{entity}', [OntologyController::class, 'relations'])
                ->where('entity', '[a-z_]+');
            Route::post('/knowledge-graph/query', [OntologyController::class, 'query']);

            /*
            | Generative AI.
            */
            Route::get('/templates', [GenerationController::class, 'templates']);
            Route::post('/generate', [GenerationController::class, 'generate']);
            Route::post('/generated-outputs/{output}/review', [GenerationController::class, 'review'])
                ->whereNumber('output');

            /*
            | Outcomes, effectiveness and audit.
            */
            Route::get('/outcomes', [OutcomeController::class, 'index']);
            Route::post('/outcomes/measure-due', [OutcomeController::class, 'measureDue']);
            Route::get('/outcomes/effectiveness', [OutcomeController::class, 'effectiveness']);
            Route::get('/audit-logs', [OutcomeController::class, 'auditLogs']);
        });
    });
