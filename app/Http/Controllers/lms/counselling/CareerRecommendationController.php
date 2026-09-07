<?php

namespace App\Http\Controllers\lms\counselling;

use App\CareerIntelligence\AlignmentBandClassifier;
use App\CareerIntelligence\AlternativeOccupationRecommender;
use App\CareerIntelligence\CareerRecommendationExplainer;
use App\CareerIntelligence\KnowledgeMatchService;
use App\Http\Controllers\Controller;
use App\Models\lms\counselling\StudentAspiration;
use Illuminate\Http\Request;

/**
 * Knowledge-Based Career Recommendation Engine — API + presentation wiring.
 *
 * Scoring itself (KnowledgeMatchService, AlternativeOccupationRecommender)
 * is untouched by this pass — this class only shapes the response for the
 * Counsellor "Explore Adjacent Careers" experience: alignment bands,
 * score-improvement deltas, and narrative text, all derived from numbers
 * those two services already calculated.
 */
class CareerRecommendationController extends Controller
{
    public function __construct(
        private readonly KnowledgeMatchService $knowledgeMatchService,
        private readonly AlternativeOccupationRecommender $recommender,
        private readonly AlignmentBandClassifier $bandClassifier,
        private readonly CareerRecommendationExplainer $explainer,
    ) {
    }

    public function recommend(Request $request)
    {
        $sessionStudentId = $request->session()->get('user_id');
        if (empty($sessionStudentId)) {
            return response()->json(['status_code' => 0, 'message' => 'Unauthenticated.'], 401);
        }

        $requestedStudentId = $request->query('student_id');
        $studentId = (string) $sessionStudentId;

        if (! empty($requestedStudentId) && (string) $requestedStudentId !== (string) $sessionStudentId) {
            $isAdmin = (int) $request->session()->get('is_admin');
            if ($isAdmin !== 1 && $isAdmin !== 2) {
                return response()->json([
                    'status_code' => 0,
                    'message' => 'You do not have permission to view this student\'s career recommendation.',
                ], 403);
            }
            $studentId = (string) $requestedStudentId;
        }

        $aspiration = StudentAspiration::where('student_id', $studentId)
            ->where('is_current', true)
            ->orderByDesc('captured_at')
            ->first();

        if (! $aspiration || empty($aspiration->occupation_id)) {
            return $this->insufficientData($studentId, $aspiration
                ? 'Current aspiration has no occupation code to evaluate knowledge requirements against.'
                : 'No current aspiration on file for this student.');
        }

        $studentKnowledge = $this->knowledgeMatchService->buildStudentKnowledgeProfile($studentId);

        if (empty($studentKnowledge)) {
            return $this->insufficientData(
                $studentId,
                'No demonstrated knowledge could be derived from this student\'s active evidence.',
                $aspiration
            );
        }

        $alignment = $this->knowledgeMatchService->evaluateAlignment(
            $studentId,
            $aspiration->occupation_id,
            $studentKnowledge
        );

        $band = $this->bandClassifier->classify($alignment['matchPercentage']);

        $currentAspiration = [
            'occupation_code' => $aspiration->occupation_id,
            'occupation_name' => $aspiration->occupation_name,
            'matchPercentage' => $alignment['matchPercentage'],
            'alignmentBand' => $band['band'],
        ];

        $relatedCareers = [];
        $knowledgeDevelopmentAreas = $this->sortByImportanceDesc($alignment['missingKnowledge']);

        // Related careers are relevant regardless of band — a Partial Match
        // can still have something stronger; only Strong Match with nothing
        // scoring higher naturally yields an empty list.
        $allRanked = $this->recommender->rankAllOccupations($studentKnowledge, $aspiration->occupation_id, PHP_INT_MAX);
        $betterFits = $this->recommender->betterFitThan(
            $allRanked,
            $alignment['matchPercentage'],
            (int) config('career_recommendation.related_careers_limit')
        );

        $topDomainsLimit = (int) config('career_recommendation.top_matched_domains_limit');
        foreach ($betterFits as $occupation) {
            $topDomains = $this->sortByImportanceDesc($occupation['matched_knowledge'], 'importance');
            $topDomains = array_slice(array_map(
                fn (array $m) => $m['occupation_knowledge'],
                $topDomains
            ), 0, $topDomainsLimit);

            $relatedCareers[] = [
                'occupation_code' => $occupation['occupation_code'],
                'occupation_name' => $occupation['occupation_name'],
                'matchPercentage' => $occupation['score'],
                'scoreImprovement' => round($occupation['score'] - $alignment['matchPercentage'], 2),
                'topMatchedKnowledgeDomains' => $topDomains,
            ];
        }

        $response = [
            'student_id' => $studentId,
            'currentAspiration' => $currentAspiration,
            'relatedCareersWithBetterAlignment' => $relatedCareers,
            'knowledgeDevelopmentAreas' => $knowledgeDevelopmentAreas,
            'narrative' => [
                'alignmentSummary' => $this->explainer->alignmentSummary($band['band']),
                'relatedCareersGuidance' => $this->explainer->relatedCareersGuidance($relatedCareers),
                'knowledgeDevelopmentIntro' => $this->explainer->knowledgeDevelopmentIntro(
                    $aspiration->occupation_name ?? $aspiration->occupation_id,
                    $knowledgeDevelopmentAreas
                ),
            ],
            // Retained for API completeness / debugging — unchanged shape from the prior pass.
            'alignment' => $alignment['alignment'],
            'threshold' => $alignment['threshold'],
            'matchPercentage' => $alignment['matchPercentage'],
            'studentKnowledge' => $studentKnowledge,
            'matchedKnowledge' => $alignment['matchedKnowledge'],
            'missingKnowledge' => $alignment['missingKnowledge'],
            'extraKnowledge' => $alignment['extraKnowledge'],
        ];

        return response()->json(['status_code' => 1, 'message' => 'SUCCESS', 'data' => $response]);
    }

    private function insufficientData(string $studentId, string $reason, ?StudentAspiration $aspiration = null)
    {
        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => [
                'student_id' => $studentId,
                'currentAspiration' => null,
                'relatedCareersWithBetterAlignment' => [],
                'knowledgeDevelopmentAreas' => [],
                'narrative' => ['alignmentSummary' => null, 'relatedCareersGuidance' => null, 'knowledgeDevelopmentIntro' => null],
                'alignment' => 'INSUFFICIENT_DATA',
                'insufficient_data_reason' => $reason,
                'stated_aspiration' => $aspiration ? [
                    'occupation_id' => $aspiration->occupation_id,
                    'occupation_name' => $aspiration->occupation_name,
                ] : null,
            ],
        ]);
    }

    /** @param array<int, array<string, mixed>> $items */
    private function sortByImportanceDesc(array $items, string $key = 'importance'): array
    {
        usort($items, fn (array $a, array $b) => ($b[$key] ?? 0) <=> ($a[$key] ?? 0));

        return $items;
    }
}
