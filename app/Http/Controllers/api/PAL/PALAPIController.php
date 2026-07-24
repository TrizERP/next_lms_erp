<?php

namespace App\Http\Controllers\api\PAL;

use App\Http\Controllers\Controller;
use App\Services\PAL\Intelligence\IntelligenceService;
use App\Services\PAL\Pedagogy\PedagogyOrchestrationService;
use App\Services\PAL\Content\ContentIntelligenceService;
use App\Services\PAL\Telemetry\TelemetryService;
use App\Services\PAL\AI\AIOrchestrationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * PAL V4 API Controller
 * Central API for all PAL intelligence services
 */
class PALAPIController extends Controller
{
    protected IntelligenceService $intelligence;
    protected PedagogyOrchestrationService $pedagogy;
    protected ContentIntelligenceService $content;
    protected TelemetryService $telemetry;
    protected AIOrchestrationService $ai;

    public function __construct(
        IntelligenceService $intelligence,
        PedagogyOrchestrationService $pedagogy,
        ContentIntelligenceService $content,
        TelemetryService $telemetry,
        AIOrchestrationService $ai
    ) {
        $this->intelligence = $intelligence;
        $this->pedagogy = $pedagogy;
        $this->content = $content;
        $this->telemetry = $telemetry;
        $this->ai = $ai;
    }

    // ==================== INTELLIGENCE LAYER APIs ====================

    /**
     * GET /api/pal/learner-state/{learnerId}
     * Get comprehensive learner state across all dimensions
     */
    public function getLearnerState(Request $request, int $learnerId): JsonResponse
    {
        $sessionId = $request->get('session_id');
        
        $state = $this->intelligence->getLearnerState($learnerId, $sessionId);
        
        return response()->json([
            'success' => true,
            'data' => $state,
        ]);
    }

    /**
     * GET /api/pal/mastery-map/{learnerId}
     * Get mastery map for learner
     */
    public function getMasteryMap(Request $request, int $learnerId): JsonResponse
    {
        $subjectId = $request->get('subject_id');
        
        $map = $this->intelligence->getMasteryMap($learnerId, $subjectId);
        
        return response()->json([
            'success' => true,
            'data' => $map,
        ]);
    }

    /**
     * POST /api/pal/mastery/calculate
     * Calculate mastery for a specific learner and assessment
     */
    public function calculateMastery(Request $request, \App\Services\PAL\Mastery\ConceptMasteryService $masteryService): JsonResponse
    {
        $request->validate([
            'learner_id' => 'required|integer',
            'assessment_id' => 'required|integer',
        ]);

        $masteryService->calculateMastery($request->learner_id, $request->assessment_id);

        return response()->json([
            'success' => true,
            'message' => 'Mastery calculated and updated successfully.',
        ]);
    }

    /**
     * GET /api/pal/mastery/weak-concepts/{learnerId}
     * Get weak concepts for a learner
     */
    public function getWeakConcepts(int $learnerId, \App\Services\PAL\Mastery\ConceptMasteryService $masteryService): JsonResponse
    {
        $weakConcepts = $masteryService->identifyWeakConcepts($learnerId);

        return response()->json([
            'success' => true,
            'data' => $weakConcepts,
        ]);
    }

    /**
     * GET /api/pal/velocity/{learnerId}
     * Calculate learning velocity
     */
    public function getLearningVelocity(Request $request, int $learnerId): JsonResponse
    {
        $period = $request->get('period', 'week');
        
        $velocity = $this->intelligence->calculateVelocity($learnerId, $period);
        
        return response()->json([
            'success' => true,
            'data' => $velocity,
        ]);
    }

    /**
     * GET /api/pal/plateau/{learnerId}
     * Detect learning plateau
     */
    public function detectPlateau(int $learnerId): JsonResponse
    {
        $plateau = $this->intelligence->detectPlateau($learnerId);
        
        return response()->json([
            'success' => true,
            'data' => $plateau,
        ]);
    }

    /**
     * GET /api/pal/regression/{learnerId}
     * Detect mastery regression
     */
    public function detectRegression(int $learnerId): JsonResponse
    {
        $regression = $this->intelligence->detectRegression($learnerId);
        
        return response()->json([
            'success' => true,
            'data' => $regression,
        ]);
    }

    /**
     * POST /api/pal/misconception/analyze
     * Analyze misconception patterns
     */
    public function analyzeMisconception(Request $request): JsonResponse
    {
        $request->validate([
            'learner_id' => 'required|integer',
            'concept_id' => 'required|integer',
            'attempt_data' => 'required|array',
        ]);

        $analysis = $this->intelligence->analyzeMisconception(
            $request->learner_id,
            $request->concept_id,
            $request->attempt_data
        );

        return response()->json([
            'success' => true,
            'data' => $analysis,
        ]);
    }

    /**
     * GET /api/pal/misconception/cluster/{conceptId}
     * Cluster misconceptions
     */
    public function clusterMisconceptions(int $conceptId): JsonResponse
    {
        $cluster = $this->intelligence->clusterMisconceptions($conceptId);
        
        return response()->json([
            'success' => true,
            'data' => $cluster,
        ]);
    }

    /**
     * GET /api/pal/remediation/{learnerId}/{misconceptionId}
     * Get targeted remediation
     */
    public function getRemediation(int $learnerId, int $misconceptionId): JsonResponse
    {
        $remediation = $this->intelligence->getRemediation($learnerId, $misconceptionId);
        
        return response()->json([
            'success' => true,
            'data' => $remediation,
        ]);
    }

    /**
     * GET /api/pal/disengagement-risk/{learnerId}
     * Predict disengagement risk
     */
    public function predictDisengagement(int $learnerId): JsonResponse
    {
        $prediction = $this->intelligence->predictDisengagement($learnerId);
        
        return response()->json([
            'success' => true,
            'data' => $prediction,
        ]);
    }

    /**
     * GET /api/pal/failure-risk/{learnerId}
     * Predict failure risk
     */
    public function predictFailure(int $learnerId): JsonResponse
    {
        $prediction = $this->intelligence->predictFailure($learnerId);
        
        return response()->json([
            'success' => true,
            'data' => $prediction,
        ]);
    }

    /**
     * GET /api/pal/burnout-risk/{learnerId}
     * Predict burnout risk
     */
    public function predictBurnout(int $learnerId): JsonResponse
    {
        $prediction = $this->intelligence->predictBurnout($learnerId);
        
        return response()->json([
            'success' => true,
            'data' => $prediction,
        ]);
    }

    /**
     * GET /api/pal/engagement/{learnerId}/{sessionId}
     * Calculate engagement score
     */
    public function calculateEngagement(int $learnerId, int $sessionId): JsonResponse
    {
        $engagement = $this->intelligence->calculateEngagement($learnerId, $sessionId);
        
        return response()->json([
            'success' => true,
            'data' => $engagement,
        ]);
    }

    /**
     * GET /api/pal/frustration/{learnerId}/{sessionId}
     * Detect frustration
     */
    public function detectFrustration(int $learnerId, int $sessionId): JsonResponse
    {
        $frustration = $this->intelligence->detectFrustration($learnerId, $sessionId);
        
        return response()->json([
            'success' => true,
            'data' => $frustration,
        ]);
    }

    /**
     * GET /api/pal/pedagogy/recommend/{learnerId}/{conceptId}
     * Get recommended pedagogy
     */
    public function getRecommendedPedagogy(Request $request, int $learnerId, int $conceptId): JsonResponse
    {
        $context = [
            'device_type' => $request->get('device_type', 'desktop'),
            'session_id' => $request->get('session_id'),
        ];

        $recommendation = $this->pedagogy->getRecommendation($learnerId, $conceptId, $context);

        return response()->json([
            'success' => true,
            'data' => $recommendation,
        ]);
    }

    /**
     * GET /api/pal/pedagogy/engine/suggested-content
     * Get pedagogy-engine driven suggested content for a chapter.
     */
    public function getPedagogyEngineSuggestedContent(Request $request, \App\Services\PAL\Integration\PedagogySuggestedContentService $engine): JsonResponse
    {
        $request->validate([
            'learner_id' => 'required|integer',
            'chapter_id' => 'required|integer',
            'standard_id' => 'required|integer',
            'subject_id' => 'required|integer',
            'sub_institute_id' => 'required|integer',
        ]);

        $params = $request->only([
            'learner_id',
            'chapter_id',
            'standard_id',
            'subject_id',
            'grade_id',
            'sub_institute_id',
            'syear',
            'student_level',
            'device_type',
        ]);

        // Never trust the client-supplied sub_institute_id: scope content to the
        // (already ownership-verified) learner's own institute so a caller can't
        // enumerate another tenant's content by passing a foreign institute id.
        $params['sub_institute_id'] = $this->resolveContentInstitute(
            $request->attributes->get('pal_auth'),
            (int) $request->input('learner_id'),
            (int) $request->input('sub_institute_id')
        );

        $result = $engine->getSuggestions($params);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Resolve the institute whose content a learner-scoped request may read.
     *
     * The multi-client super admin (is_admin === 2) may target an institute
     * explicitly; everyone else is pinned to the learner's own institute (the
     * learner id is already ownership-verified by PalApiAuth), falling back to
     * the caller's own institute — never the raw client-supplied value.
     */
    private function resolveContentInstitute(?array $auth, int $learnerId, int $requestedSub): int
    {
        if ((int) ($auth['is_admin'] ?? 0) === 2 && $requestedSub > 0) {
            return $requestedSub;
        }

        $learnerSub = DB::table('tblstudent')->where('id', $learnerId)->value('sub_institute_id')
            ?? DB::table('tbluser')->where('id', $learnerId)->value('sub_institute_id');
        if ($learnerSub !== null && $learnerSub !== '') {
            return (int) $learnerSub;
        }

        $callerInstitutes = array_values(array_filter(
            array_map('trim', explode(',', (string) ($auth['sub_institute_id'] ?? ''))),
            'strlen'
        ));
        $callerFirst = (int) ($callerInstitutes[0] ?? 0);
        return $callerFirst > 0 ? $callerFirst : $requestedSub;
    }

    /**
     * GET /api/pal/content/variants/{conceptId}
     * Get content variants
     */
    public function getContentVariants(Request $request, int $conceptId): JsonResponse
    {
        $pedagogyType = $request->get('pedagogy', 'concept-based');
        
        $variants = $this->pedagogy->getContentVariants($conceptId, $pedagogyType);

        return response()->json([
            'success' => true,
            'data' => $variants,
        ]);
    }

    /**
     * GET /api/pal/content/recommend/{learnerId}/{conceptId}
     * Get content recommendation
     */
    public function getContentRecommendation(Request $request, int $learnerId, int $conceptId): JsonResponse
    {
        $context = [
            'pedagogy' => $request->get('pedagogy'),
        ];

        $recommendation = $this->content->getRecommendation($learnerId, $conceptId, $context);

        return response()->json([
            'success' => true,
            'data' => $recommendation,
        ]);
    }

    /**
     * GET /api/pal/h5p/variants/{conceptId}
     * Get H5P variants
     */
    public function getH5PVariants(Request $request, int $conceptId): JsonResponse
    {
        $h5pType = $request->get('type', 'quiz');
        
        $variants = $this->content->getH5PVariants($conceptId, $h5pType);

        return response()->json([
            'success' => true,
            'data' => $variants,
        ]);
    }

    /**
     * POST /api/pal/telemetry/xapi
     * Process xAPI statement
     */
    public function processxAPI(Request $request): JsonResponse
    {
        // learner_id is required so PalApiAuth can ownership-scope the ingest,
        // and so the event is attributed to a numeric learner (not the mbox).
        $request->validate([
            'learner_id' => 'required|integer',
            'session_id' => 'nullable|integer',
        ]);

        $event = $this->telemetry->processStatement(
            $request->all(),
            (int) $request->input('learner_id'),
            $request->filled('session_id') ? (int) $request->input('session_id') : null
        );

        return response()->json([
            'success' => true,
            'data' => ['event_id' => $event->id],
        ]);
    }

    /**
     * POST /api/pal/telemetry/batch
     * Process xAPI statements in batch
     */
    public function processxAPIBatch(Request $request): JsonResponse
    {
        $request->validate([
            'learner_id' => 'required|integer',
            'session_id' => 'nullable|integer',
            'statements' => 'required|array',
        ]);

        $result = $this->telemetry->processBatch(
            $request->get('statements', []),
            (int) $request->input('learner_id'),
            $request->filled('session_id') ? (int) $request->input('session_id') : null
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * GET /api/pal/telemetry/session/{sessionId}
     * Get session telemetry summary
     */
    public function getSessionTelemetry(int $sessionId): JsonResponse
    {
        $summary = $this->telemetry->getSessionSummary($sessionId);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * GET /api/pal/time-on-task/{learnerId}
     * Get time-on-task intelligence
     */
    public function getTimeOnTask(Request $request, int $learnerId): JsonResponse
    {
        $period = $request->get('period', 'day');
        
        $time = $this->telemetry->getTimeOnTask($learnerId, $period);

        return response()->json([
            'success' => true,
            'data' => $time,
        ]);
    }

    /**
     * GET /api/pal/telemetry/important-events/{learnerId}
     * Get important learning events
     */
    public function getImportantEvents(int $learnerId): JsonResponse
    {
        $events = $this->telemetry->getImportantEvents($learnerId);

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * POST /api/pal/ai/explanation
     * Generate personalized explanation
     */
    public function generateExplanation(Request $request): JsonResponse
    {
        $request->validate([
            'learner_id' => 'required|integer',
            'concept_id' => 'required|integer',
            'style' => 'nullable|string',
        ]);

        $explanation = $this->ai->generateExplanation(
            $request->learner_id,
            $request->concept_id,
            $request->get('style', 'simple')
        );

        return response()->json([
            'success' => true,
            'data' => $explanation,
        ]);
    }

    /**
     * POST /api/pal/ai/remediation
     * Generate misconception remediation
     */
    public function generateRemediation(Request $request): JsonResponse
    {
        $request->validate([
            'learner_id' => 'required|integer',
            'misconception_id' => 'required|integer',
        ]);

        $remediation = $this->ai->generateRemediation(
            $request->learner_id,
            $request->misconception_id
        );

        return response()->json([
            'success' => true,
            'data' => $remediation,
        ]);
    }

    /**
     * POST /api/pal/ai/practice
     * Generate personalized practice
     */
    public function generatePractice(Request $request): JsonResponse
    {
        $request->validate([
            'learner_id' => 'required|integer',
            'concept_id' => 'required|integer',
            'difficulty' => 'nullable|string',
        ]);

        $practice = $this->ai->generatePractice(
            $request->learner_id,
            $request->concept_id,
            $request->get('difficulty', 'medium')
        );

        return response()->json([
            'success' => true,
            'data' => $practice,
        ]);
    }

    /**
     * POST /api/pal/ai/summary
     * Summarize content
     */
    public function summarizeContent(Request $request): JsonResponse
    {
        $request->validate([
            'content_id' => 'required|integer',
            'format' => 'nullable|string',
        ]);

        $summary = $this->ai->summarizeContent(
            $request->content_id,
            $request->get('format', 'bullet')
        );

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * POST /api/pal/ai/teacher-insights
     * Get teacher insights
     */
    public function getTeacherInsights(Request $request): JsonResponse
    {
        // Role-based access: teacher insights are a staff/admin tool.
        $auth = $request->attributes->get('pal_auth');
        if (($auth['role'] ?? null) === 'student') {
            return response()->json([
                'success' => false,
                'message' => 'Teacher insights are only available to staff and administrators.',
            ], 403);
        }

        $request->validate([
            'learner_id' => 'required|integer',
            'classroom_id' => 'required|integer',
        ]);

        $insights = $this->ai->getTeacherInsights(
            $request->learner_id,
            $request->classroom_id
        );

        return response()->json([
            'success' => true,
            'data' => $insights,
        ]);
    }

    /**
     * GET /api/pal/metacognition/prompts/{learnerId}
     * Get metacognitive prompts
     */
    public function getMetacognitivePrompts(Request $request, int $learnerId): JsonResponse
    {
        $context = $request->get('context', 'general');
        
        $prompts = $this->pedagogy->getMetacognitivePrompts($learnerId, $context);

        return response()->json([
            'success' => true,
            'data' => $prompts,
        ]);
    }

    /**
     * POST /api/pal/metacognition/reflect
     * Record learner reflection
     */
    public function recordReflection(Request $request): JsonResponse
    {
        $request->validate([
            'learner_id' => 'required|integer',
            'response' => 'required|string',
            'prompt_type' => 'nullable|string',
            'session_id' => 'nullable|integer',
            'concept_id' => 'nullable|integer',
            'content_id' => 'nullable|integer',
            'context' => 'nullable|array',
        ]);

        $reflection = $this->pedagogy->recordReflection($request->learner_id, [
            'response' => $request->response,
            'prompt_type' => $request->get('prompt_type', 'general'),
            'session_id' => $request->get('session_id'),
            'concept_id' => $request->get('concept_id'),
            'content_id' => $request->get('content_id'),
            'context' => $request->get('context', []),
        ]);

        return response()->json([
            'success' => true,
            'data' => ['reflection_id' => $reflection->id],
        ]);
    }

    /**
     * POST /api/pal/pedagogy/track
     * Track pedagogy effectiveness
     */
    public function trackPedagogyEffectiveness(Request $request): JsonResponse
    {
        $request->validate([
            'learner_id' => 'required|integer',
            'pedagogy_type' => 'required|string',
            'outcome' => 'required|string|in:success,partial,failure',
            'session_id' => 'nullable|integer',
            'concept_id' => 'nullable|integer',
            'content_id' => 'nullable|integer',
            'effectiveness_score' => 'nullable|numeric|min:0|max:100',
            'context_data' => 'nullable|array',
        ]);

        $result = $this->pedagogy->trackEffectiveness($request->learner_id, $request->pedagogy_type, $request->outcome, [
            'session_id' => $request->get('session_id'),
            'concept_id' => $request->get('concept_id'),
            'content_id' => $request->get('content_id'),
            'effectiveness_score' => $request->get('effectiveness_score'),
            'context_data' => $request->get('context_data', []),
        ]);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
