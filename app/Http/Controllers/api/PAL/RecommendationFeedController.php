<?php

namespace App\Http\Controllers\api\PAL;

use App\Http\Controllers\Controller;
use App\Services\PAL\Coherence\CoherenceMapRepository;
use App\Services\PAL\Coherence\CoherenceRecommender;
use App\Services\PAL\ContentModel\ContentEmbeddingService;
use App\Services\PAL\Gamification\CareerQuestNarrator;
use App\Services\PAL\Gamification\CareerQuestService;
use App\Services\PAL\Gamification\LearnerActivitySource;
use App\Services\PAL\Intelligence\RecommendationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "What should this student do next" — graph-aware recommendation feed combining:
 *
 *   - Graph-Aware Next Best Action: `CoherenceRecommender::nextBestAction()`
 *     which executes multi-tier pedagogical rules (Consolidate -> Unblock Root Blockers -> Advance),
 *     walking Neo4j prerequisite closures via `CoherenceMapRepository`.
 *   - Deterministic Fallback: `RecommendationEngine::getNextBestAction()` when the
 *     learner's subject scope has no projected coherence map in Neo4j.
 *   - Content Recommendation: `RecommendationEngine::getContentRecommendation()`
 *     and `CoherenceMapRepository::contentFor()` difficulty and engagement matching.
 *   - Vector Embedding Retrieval: `ContentEmbeddingService::suggestContentForConcept()`.
 *   - Career Quest Narrative: `CareerQuestNarrator::narrate()`.
 *   - Recommendation Audit: Logged to `pal_recommendation_log` for tracking outcomes.
 *
 * TENANT ISOLATION: mounted under `api/pal`'s existing `pal.auth` middleware.
 */
class RecommendationFeedController extends Controller
{
    public function __construct(
        private readonly RecommendationEngine $recommendations,
        private readonly CoherenceRecommender $coherenceRecommender,
        private readonly CoherenceMapRepository $coherenceMap,
        private readonly CareerQuestService $careerQuest,
        private readonly CareerQuestNarrator $careerNarrator,
        private readonly LearnerActivitySource $activity,
        private readonly ContentEmbeddingService $contentEmbedding,
    ) {
    }

    /** GET /api/pal/recommendations/next */
    public function next(Request $request): JsonResponse
    {
        [$learnerId, $error] = $this->learnerId($request);
        if ($error !== null) {
            return $error;
        }

        $learner = $this->activity->learner($learnerId);
        if ($learner === null) {
            return $this->fail('This learner could not be resolved.', 404);
        }

        $subInstituteId = (int) ($learner['sub_institute_id'] ?? 0);
        $scope = $this->scopeForLearner($request, $learnerId);

        $action = null;
        $graphAware = false;
        $whyPayload = null;
        $rootBlockers = [];
        $stuckOn = null;
        $conceptIds = [];

        // 1. Try Graph-Aware Coherence Recommender first if scope is resolved
        if (is_array($scope) && ! empty($scope['standard_id']) && ! empty($scope['subject_id'])) {
            try {
                $coherenceResult = $this->coherenceRecommender->nextBestAction(
                    $learnerId,
                    (int) $scope['standard_id'],
                    (int) $scope['subject_id']
                );

                if (($coherenceResult['action'] ?? '') !== 'no_map' && ! empty($coherenceResult['concept'])) {
                    $graphAware = true;
                    $targetConcept = $coherenceResult['concept'];
                    $targetConceptId = (int) ($targetConcept['id'] ?? 0);
                    $whyPayload = $coherenceResult['why'] ?? [];
                    $rootBlockers = $whyPayload['roots'] ?? [];
                    $stuckOn = $whyPayload['stuck_on'] ?? null;

                    $action = [
                        'action' => $coherenceResult['action'],
                        'concepts' => $targetConceptId > 0 ? [$targetConceptId] : [],
                        'target_concept' => $targetConcept,
                        'priority' => match ($coherenceResult['action']) {
                            'consolidate' => 0.95,
                            'unblock' => 0.90,
                            'advance' => 0.80,
                            default => 0.70,
                        },
                        'reason' => (string) ($whyPayload['because'] ?? 'Prerequisite graph analysis'),
                        'why' => $whyPayload,
                        'graph_aware' => true,
                        'roots' => $rootBlockers,
                        'stuck_on' => $stuckOn,
                    ];

                    $conceptIds = $targetConceptId > 0 ? [$targetConceptId] : [];
                }
            } catch (\Throwable $e) {
                // If graph is temporarily unavailable, fall back cleanly to SQL recommendation engine
            }
        }

        // 2. Fall back to deterministic SQL engine if graph recommendation wasn't possible
        if ($action === null) {
            $subjectId = is_array($scope) ? ($scope['subject_id'] ?? null) : ($request->filled('subject_id') ? (int) $request->input('subject_id') : null);
            $action = $this->recommendations->getNextBestAction($learnerId, $subjectId);
            $action['graph_aware'] = false;
            $action['why'] = [
                'rule' => $action['action'] ?? 'unknown',
                'because' => $action['reason'] ?? 'Mastery score analysis',
                'roots' => [],
                'stuck_on' => null,
            ];

            $conceptIds = array_slice(array_values(array_unique(array_map(
                'intval',
                array_filter((array) ($action['concepts'] ?? []), static fn ($c) => (int) $c > 0)
            ))), 0, 3);
        }

        // 3. Content recommendations
        $contentByConcept = [];
        $embeddingByConcept = [];
        $embeddingEnabled = $this->contentEmbedding->enabled($subInstituteId ?: null);

        foreach ($conceptIds as $conceptId) {
            $contentByConcept[$conceptId] = $this->recommendations->getContentRecommendation($learnerId, $conceptId);

            if ($embeddingEnabled) {
                $embeddingByConcept[$conceptId] = $this->contentEmbedding->suggestContentForConcept(
                    $conceptId,
                    $subInstituteId,
                    3
                );
            }
        }

        // 4. Audit Trail: Log recommendation to pal_recommendation_log if table exists
        $this->logRecommendation($learnerId, $subInstituteId, $scope, $action);

        // 5. Career Narrative
        $quest = $this->careerQuest->quest($learnerId);
        $careerNarrative = ($quest['available'] ?? false)
            ? $this->careerNarrator->narrate($learnerId, $subInstituteId, $quest)
            : [
                'status' => 'insufficient_evidence',
                'narrative' => null,
                'message' => (string) ($quest['reason'] ?? 'No career quest state yet for this learner.'),
            ];

        return $this->ok([
            'learner_id' => $learnerId,
            'next_best_action' => $action,
            'scope' => is_array($scope) ? $scope : null,
            'content_recommendations' => $contentByConcept,
            'content_suggestions_embedding' => $embeddingEnabled ? $embeddingByConcept : null,
            'career_narrative' => $careerNarrative,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Resolve standard and subject for the learner from current enrollment.
     */
    private function scopeForLearner(Request $request, int $learnerId): array|string
    {
        $enrollment = DB::table('tblstudent_enrollment')
            ->where('student_id', $learnerId)
            ->orderByDesc('syear')
            ->orderByDesc('id')
            ->first(['standard_id', 'sub_institute_id', 'syear']);

        if ($enrollment === null) {
            return "Learner {$learnerId} has no enrollment.";
        }

        $tenant = (int) $enrollment->sub_institute_id;
        $standard = (int) $enrollment->standard_id;

        if ($request->filled('subject_id')) {
            return [
                'sub_institute_id' => $tenant,
                'standard_id' => $standard,
                'subject_id' => (int) $request->get('subject_id'),
            ];
        }

        // Pick richest subject map for this class if no subject is specified
        $subject = DB::table('lms_concept')
            ->where('sub_institute_id', $tenant)
            ->where('standard_id', $standard)
            ->select('subject_id', DB::raw('count(*) as n'))
            ->groupBy('subject_id')
            ->orderByDesc('n')
            ->first();

        return [
            'sub_institute_id' => $tenant,
            'standard_id' => $standard,
            'subject_id' => $subject ? (int) $subject->subject_id : null,
        ];
    }

    /**
     * Audit log for generated recommendation.
     */
    private function logRecommendation(int $learnerId, int $subInstituteId, mixed $scope, array $action): void
    {
        try {
            if (! Schema::hasTable('pal_recommendation_log')) {
                return;
            }

            $conceptId = (int) ($action['concepts'][0] ?? ($action['target_concept']['id'] ?? 0));
            $subjectId = is_array($scope) ? (int) ($scope['subject_id'] ?? 0) : null;
            $rule = (string) ($action['action'] ?? 'unknown');

            DB::table('pal_recommendation_log')->insert([
                'learner_id' => $learnerId,
                'concept_id' => $conceptId > 0 ? $conceptId : null,
                'subject_id' => $subjectId > 0 ? $subjectId : null,
                'sub_institute_id' => $subInstituteId,
                'pedagogy_type' => $rule,
                'selection_reason' => mb_substr((string) ($action['reason'] ?? ''), 0, 510),
                'tier_1_rule_key' => $action['graph_aware'] ? 'coherence_prerequisite_engine' : 'sql_competency_threshold',
                'algorithm_version' => $action['graph_aware'] ? 'pal-v4-graph-coherence' : 'pal-v4-sql',
                'shown_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
            // Logging is non-blocking
        }
    }

    /**
     * Resolve the learner this request is about.
     *
     * @return array{0:int,1:?JsonResponse}
     */
    private function learnerId(Request $request): array
    {
        $auth = $this->auth($request);

        if (($auth['role'] ?? '') === 'student') {
            return [(int) $auth['user_id'], null];
        }

        $learnerId = (int) $request->input('learner_id', 0);
        if ($learnerId <= 0) {
            return [0, $this->fail('learner_id is required — pick a student to view their recommendations.', 422)];
        }

        return [$learnerId, null];
    }

    private function auth(Request $request): array
    {
        $auth = $request->attributes->get('pal_auth');

        return is_array($auth) ? $auth : [];
    }

    private function ok($data, string $message = 'Success'): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    private function fail(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
