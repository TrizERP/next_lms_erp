<?php

namespace App\CareerIntelligence;

use Illuminate\Support\Facades\DB;

/**
 * Knowledge-Based Career Recommendation Engine — Steps 5-6.
 *
 * Evaluates a student's knowledge profile against EVERY occupation cached in
 * onet_occupation_data (no occupation family filter, no curated shortlist —
 * the same onet_content_model_reference taxonomy every occupation already
 * shares, per the underlying O*NET data model). Ranking is entirely a
 * function of KnowledgeMatchService::matchKnowledge()'s output; this class
 * adds no scoring logic of its own beyond sorting.
 */
class AlternativeOccupationRecommender
{
    public function __construct(
        private readonly KnowledgeMatchService $knowledgeMatchService = new KnowledgeMatchService(),
    ) {
    }

    /**
     * Step 5 — evaluate every occupation, ranked descending by matchPercentage.
     *
     * One bulk query for all occupations' knowledge rows (not one query per
     * occupation) — this is what makes scoring ~900 occupations per request
     * feasible without hammering the database.
     *
     * @return array<int, array{
     *   occupation_code: string, occupation_name: string, score: float,
     *   matched_knowledge: array,
     * }>
     */
    public function rankAllOccupations(array $studentKnowledge, ?string $excludeCode = null, ?int $limit = null): array
    {
        $limit ??= (int) config('career_recommendation.top_matches');

        $rows = DB::table('onet_knowledge as k')
            ->join('onet_content_model_reference as r', 'r.element_id', '=', 'k.element_id')
            ->join('onet_occupation_data as od', 'od.onetsoc_code', '=', 'k.onetsoc_code')
            ->select('od.onetsoc_code', 'od.title', 'r.element_name', 'k.scale_id', 'k.data_value')
            ->get();

        $byOccupation = [];
        foreach ($rows as $row) {
            if ($excludeCode !== null && $row->onetsoc_code === $excludeCode) {
                continue;
            }

            $byOccupation[$row->onetsoc_code]['title'] = $row->title;
            $byOccupation[$row->onetsoc_code]['knowledge'][$row->element_name]['importance'] ??= 0.0;
            $byOccupation[$row->onetsoc_code]['knowledge'][$row->element_name]['level'] ??= 0.0;
            if ($row->scale_id === 'IM') {
                $byOccupation[$row->onetsoc_code]['knowledge'][$row->element_name]['importance'] = (float) $row->data_value;
            } elseif ($row->scale_id === 'LV') {
                $byOccupation[$row->onetsoc_code]['knowledge'][$row->element_name]['level'] = (float) $row->data_value;
            }
        }

        $ranked = [];
        foreach ($byOccupation as $code => $occupation) {
            $occupationKnowledge = [];
            foreach ($occupation['knowledge'] as $elementName => $scores) {
                $occupationKnowledge[] = [
                    'knowledge' => $elementName,
                    'importance' => $scores['importance'],
                    'level' => $scores['level'],
                ];
            }

            $match = $this->knowledgeMatchService->matchKnowledge($studentKnowledge, $occupationKnowledge);

            $ranked[] = [
                'occupation_code' => $code,
                'occupation_name' => $occupation['title'],
                'score' => $match['matchPercentage'],
                'matched_knowledge' => $match['matchedKnowledge'],
            ];
        }

        usort($ranked, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_slice($ranked, 0, $limit);
    }

    /**
     * Step 6 — occupations that genuinely score higher than the student's
     * current aspiration. Never a curated list: purely a filter over the
     * same calculated scores rankAllOccupations() already produced.
     *
     * @param array $rankedOccupations rankAllOccupations() output (pass a
     *                                 large/unlimited ranking in, so
     *                                 "better than aspiration" isn't
     *                                 truncated before the comparison)
     */
    public function betterFitThan(array $rankedOccupations, float $aspirationScore, ?int $limit = null): array
    {
        $limit ??= (int) config('career_recommendation.top_matches');

        $better = array_values(array_filter(
            $rankedOccupations,
            fn (array $occupation) => $occupation['score'] > $aspirationScore
        ));

        usort($better, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_slice($better, 0, $limit);
    }
}
