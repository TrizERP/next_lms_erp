<?php

namespace App\Http\Controllers\api\PAL;

use App\Http\Controllers\Controller;
use App\Services\PAL\ContentModel\ContentEmbeddingService;
use App\Services\PAL\Gamification\CareerQuestNarrator;
use App\Services\PAL\Gamification\CareerQuestService;
use App\Services\PAL\Gamification\LearnerActivitySource;
use App\Services\PAL\Intelligence\RecommendationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "What should this student do next" — one real, read-only aggregation of
 * the recommendation-shaped outputs that already exist today, scattered
 * across places that had never been read together before this
 * (2026-10-12):
 *
 *   - Next Best Action: `RecommendationEngine::getNextBestAction()` — a
 *     deterministic (no LLM) weak-concept / spaced-review / advance
 *     decision over `pal_competencies`. Built, but wired to no route at
 *     all until now (confirmed: no controller anywhere referenced this
 *     class before this file).
 *   - Content Recommendation: `RecommendationEngine::getContentRecommendation()`
 *     — deterministic `content_master` lookup by `concept_id` + difficulty
 *     match, for every weak concept the action above names.
 *   - Content Authoring's embedding retrieval: `ContentEmbeddingService`'s
 *     cosine-similarity pattern, applied in the direction `suggestConcepts()`
 *     does not (concept -> content rather than content -> concept) via the
 *     new `suggestContentForConcept()`, as a supplementary signal when the
 *     embedding pipeline has something for that chapter. Skipped honestly
 *     (`content_suggestions_embedding: null`) when no AI key is configured
 *     — never guessed.
 *   - Career Quest's narrative: `CareerQuestService::quest()` +
 *     `CareerQuestNarrator::narrate()` — read-only here, unlike
 *     `generateReport()` (which this deliberately does NOT call: that
 *     method persists a report snapshot and queues a gamification
 *     notification, which a "what's next" read endpoint must not do as a
 *     side effect of being viewed). Grounded-only, relaying the
 *     narrator's own existing ok / insufficient_evidence /
 *     graph_unavailable / model_unavailable vocabulary unchanged.
 *
 * Nothing here invents a new scoring or ranking rule — every figure comes
 * from one of those calls, made exactly as their own classes already
 * define them. This controller only orders and combines them.
 *
 * TENANT ISOLATION: mounted under `api/pal`'s existing `pal.auth`
 * middleware (`routes/pal_api.php`) — the same ownership scoping every
 * other per-learner PAL V4 route already uses. A student always resolves
 * to themselves; staff must name a `learner_id`, which `PalApiAuth` has
 * already rejected if it falls outside the caller's institute/class scope
 * before this method ever runs. No institute id is ever read from request
 * input here — it is resolved from the learner's own enrolment record, the
 * same way `CareerQuestService::generateReport()` already does it.
 */
class RecommendationFeedController extends Controller
{
    public function __construct(
        private readonly RecommendationEngine $recommendations,
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
        $subjectId = $request->filled('subject_id') ? (int) $request->input('subject_id') : null;

        $action = $this->recommendations->getNextBestAction($learnerId, $subjectId);

        // Bounded: this is a "what next" feed, not a full catalogue —
        // looking up content for every weak concept a learner has would
        // make one request as expensive as a whole catalogue scan.
        $conceptIds = array_slice(array_values(array_unique(array_map(
            'intval',
            array_filter((array) ($action['concepts'] ?? []), static fn ($c) => (int) $c > 0)
        ))), 0, 3);

        $contentByConcept = [];
        $embeddingByConcept = [];
        $embeddingEnabled = $this->contentEmbedding->enabled();

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
            'content_recommendations' => $contentByConcept,
            // null (not []) when embedding is unconfigured, so a caller can
            // tell "nothing suggested" apart from "this signal wasn't run".
            'content_suggestions_embedding' => $embeddingEnabled ? $embeddingByConcept : null,
            'career_narrative' => $careerNarrative,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Resolve the learner this request is about — the same rule
     * `NewPalGamificationController::learnerId()` already applies: a
     * student is always themselves; staff must name one, and `PalApiAuth`
     * has already rejected a `learner_id` outside the caller's scope
     * before this runs.
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
