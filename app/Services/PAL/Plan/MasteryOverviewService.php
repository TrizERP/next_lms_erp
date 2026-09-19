<?php

namespace App\Services\PAL\Plan;

use App\Models\Eso\LearnerNodeState;
use App\Services\PAL\Questions\MasteryLadder;
use App\Services\PAL\Questions\McqPool;
use Illuminate\Support\Facades\DB;

/**
 * The last two stages: where mastery actually stands, and what is due back.
 *
 * ---------------------------------------------------------------------------
 * WHY IT READS TWO STORES AND RECONCILES THEM
 * ---------------------------------------------------------------------------
 * Mastery is recorded in two places that answer different questions, and a
 * screen showing only one of them misleads:
 *
 *   pal_concept_mastery  - the BKT estimate over ALL evidence for a concept
 *                          (diagnostic + practice), owned by BktEngine.
 *   learner_node_state   - the engine's per-node view: status, retention stage
 *                          and next_review_at, owned by EsoPolicyService.
 *
 * A concept can be high on BKT while the engine still holds it in `learning`
 * because the evidence floor is not met, and a concept can be `retained` in
 * the engine while BKT sits mid-band. Both are true. So this returns both,
 * plus the MasteryLadder verdict over real question stock, and lets the screen
 * show a learner where they genuinely are rather than picking whichever number
 * flatters.
 *
 * Owns no table and writes nothing - every value is derived on read.
 */
class MasteryOverviewService
{
    public function __construct(
        private MasteryLadder $ladder = new MasteryLadder(),
    ) {
    }

    /**
     * Mastery across one chapter, concept by concept.
     *
     * @return array{
     *     chapter_id: int, chapter_name: ?string, summary: array,
     *     concepts: array<int,array<string,mixed>>, generated_at: string
     * }
     */
    public function forChapter($studentId, int $chapterId, $subInstituteId): array
    {
        $chapter = DB::table('chapter_master')->where('id', $chapterId)->first(['id', 'chapter_name']);

        $concepts = DB::table('lms_concept')
            ->where('chapter_id', $chapterId)
            ->whereIn('sub_institute_id', [(int) $subInstituteId, 0])
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($concepts->isEmpty()) {
            return [
                'chapter_id' => $chapterId,
                'chapter_name' => $chapter->chapter_name ?? null,
                'summary' => $this->emptySummary(),
                'concepts' => [],
                'generated_at' => now()->toIso8601String(),
            ];
        }

        $conceptIds = $concepts->pluck('id')->map(fn ($id) => (int) $id)->all();

        // Four batched reads rather than four per concept - a chapter carries
        // up to 60 concepts and the database is remote in every environment.
        $bkt = $this->masteryByConcept($studentId, $conceptIds);
        $nodes = $this->nodeStateByConcept($studentId, $conceptIds, $subInstituteId);
        $practice = $this->practiceByConcept($studentId, $conceptIds);
        $availability = McqPool::availabilityForConcepts($conceptIds, $subInstituteId);

        $rows = [];

        foreach ($concepts as $concept) {
            $id = (int) $concept->id;
            $ladder = $this->ladder->evaluate($availability[$id] ?? [], $practice[$id] ?? []);
            $node = $nodes[$id] ?? null;
            $mastery = $bkt[$id] ?? null;

            $rows[] = [
                'concept_id' => $id,
                'name' => $concept->name,

                // BKT: the estimate over every recorded answer.
                'p_mastery' => $mastery !== null ? (float) $mastery->p_mastery : null,
                'band' => $mastery->band ?? null,
                'attempts' => $mastery !== null ? (int) $mastery->attempts : 0,
                'correct' => $mastery !== null ? (int) $mastery->correct : 0,
                'gate' => $mastery !== null ? (float) $mastery->mastery_gate : null,
                'bkt_mastered' => $mastery !== null
                    && (float) $mastery->p_mastery >= (float) $mastery->mastery_gate,

                // The engine: its own verdict and the retention position.
                'engine_status' => $node['status'] ?? null,
                'retention_stage' => $node['retention_stage'] ?? null,
                'next_review_at' => $node['next_review_at'] ?? null,
                'review_due' => $this->isDue($node['next_review_at'] ?? null),
                'eso_ready' => $node !== null,

                // The ladder: what the question bank can actually prove.
                'ladder' => $ladder,
                'stage' => $this->stageFor($ladder, $node, $mastery),
            ];
        }

        return [
            'chapter_id' => $chapterId,
            'chapter_name' => $chapter->chapter_name ?? null,
            'summary' => $this->summarise($rows),
            'concepts' => $rows,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Everything the learner owes a review on, soonest first.
     *
     * Read straight from learner_node_state.next_review_at, which
     * EsoPolicyService sets when it grants mastery and moves on every passed
     * retrieval check. Nothing is scheduled here - this only reports it.
     *
     * @return array{due: array, upcoming: array, counts: array, generated_at: string}
     */
    public function recallQueue($studentId, $subInstituteId, ?int $chapterId = null): array
    {
        $query = DB::table('learner_node_state as s')
            ->join('pal_concept_nodes as n', 'n.id', '=', 's.node_id')
            ->join('lms_concept as c', 'c.id', '=', 'n.concept_id')
            ->leftJoin('chapter_master as ch', 'ch.id', '=', 'c.chapter_id')
            ->where('s.student_id', (int) $studentId)
            ->whereIn('s.status', [LearnerNodeState::STATUS_MASTERED, LearnerNodeState::STATUS_RETAINED])
            ->whereNotNull('s.next_review_at');

        if ($chapterId !== null) {
            $query->where('c.chapter_id', $chapterId);
        }

        $rows = $query
            ->orderBy('s.next_review_at')
            ->get([
                's.node_id', 's.status', 's.next_review_at', 's.retention_stage', 's.mastery_estimate',
                'c.id as concept_id', 'c.name as concept_name', 'c.chapter_id',
                'ch.chapter_name',
            ]);

        $due = [];
        $upcoming = [];

        foreach ($rows as $row) {
            $item = [
                'concept_id' => (int) $row->concept_id,
                'concept_name' => $row->concept_name,
                'chapter_id' => (int) $row->chapter_id,
                'chapter_name' => $row->chapter_name,
                'node_id' => (int) $row->node_id,
                'status' => $row->status,
                'retention_stage' => (int) $row->retention_stage,
                'mastery_estimate' => (float) $row->mastery_estimate,
                'next_review_at' => $row->next_review_at,
                'days_until' => $this->daysUntil($row->next_review_at),
            ];

            if ($this->isDue($row->next_review_at)) {
                $due[] = $item;
            } else {
                $upcoming[] = $item;
            }
        }

        return [
            'due' => $due,
            // A short horizon only. A review 180 days out is true but useless
            // on a screen about what to do now.
            'upcoming' => array_slice($upcoming, 0, 10),
            'counts' => [
                'due' => count($due),
                'upcoming' => count($upcoming),
                'total_tracked' => $rows->count(),
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * One word for where a concept sits, reconciling all three signals.
     *
     * The engine outranks BKT: it applies the evidence floor and the
     * Knowledge/Application split, so "the engine says mastered" is a stronger
     * claim than "the estimate is high".
     *
     * @param  array<string,mixed>  $ladder
     * @param  array<string,mixed>|null  $node
     */
    private function stageFor(array $ladder, ?array $node, $mastery): string
    {
        $status = $node['status'] ?? null;
        $due = $this->isDue($node['next_review_at'] ?? null);

        if ($status === LearnerNodeState::STATUS_RETAINED) {
            return $due ? 'recall_due' : 'retained';
        }

        if ($status === LearnerNodeState::STATUS_MASTERED) {
            return $due ? 'recall_due' : 'mastered';
        }

        if (! empty($ladder['mastered'])) {
            // Every band the bank can test is cleared, but the engine has not
            // signed it off - usually the evidence floor.
            return 'awaiting_mastery_check';
        }

        if (($mastery !== null && (int) $mastery->attempts > 0) || ($ladder['bands_cleared'] ?? []) !== []) {
            return 'in_progress';
        }

        return ($ladder['bands_required'] ?? []) === [] ? 'no_questions' : 'not_started';
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function summarise(array $rows): array
    {
        $count = fn (callable $fn) => count(array_filter($rows, $fn));

        return [
            'concepts_total' => count($rows),
            'mastered' => $count(fn ($r) => in_array($r['stage'], ['mastered', 'retained', 'recall_due'], true)),
            'retained' => $count(fn ($r) => $r['stage'] === 'retained'),
            'recall_due' => $count(fn ($r) => $r['stage'] === 'recall_due'),
            'awaiting_check' => $count(fn ($r) => $r['stage'] === 'awaiting_mastery_check'),
            'in_progress' => $count(fn ($r) => $r['stage'] === 'in_progress'),
            'not_started' => $count(fn ($r) => $r['stage'] === 'not_started'),
            'no_questions' => $count(fn ($r) => $r['stage'] === 'no_questions'),
        ];
    }

    private function emptySummary(): array
    {
        return [
            'concepts_total' => 0, 'mastered' => 0, 'retained' => 0, 'recall_due' => 0,
            'awaiting_check' => 0, 'in_progress' => 0, 'not_started' => 0, 'no_questions' => 0,
        ];
    }

    /** @param array<int,int> $conceptIds */
    private function masteryByConcept($studentId, array $conceptIds): array
    {
        $out = [];

        foreach (DB::table('pal_concept_mastery')
            ->where('learner_id', (int) $studentId)
            ->whereIn('concept_ref_id', $conceptIds)
            ->get(['concept_ref_id', 'p_mastery', 'band', 'attempts', 'correct', 'mastery_gate']) as $row) {
            $out[(int) $row->concept_ref_id] = $row;
        }

        return $out;
    }

    /**
     * The engine's state per concept.
     *
     * A concept can hold several nodes (K, A, S), so the LEAST advanced one
     * wins - a concept is not mastered while any part of it is still being
     * learned.
     *
     * @param  array<int,int>  $conceptIds
     */
    private function nodeStateByConcept($studentId, array $conceptIds, $subInstituteId): array
    {
        $rank = [
            LearnerNodeState::STATUS_UNSEEN => 0,
            LearnerNodeState::STATUS_MISCONCEPTION_FLAGGED => 1,
            LearnerNodeState::STATUS_LEARNING => 2,
            LearnerNodeState::STATUS_MASTERED => 3,
            LearnerNodeState::STATUS_RETAINED => 4,
        ];

        $rows = DB::table('learner_node_state as s')
            ->join('pal_concept_nodes as n', 'n.id', '=', 's.node_id')
            ->where('s.student_id', (int) $studentId)
            ->whereIn('n.concept_id', $conceptIds)
            ->whereIn('n.sub_institute_id', [(int) $subInstituteId, 0])
            ->get(['n.concept_id', 's.status', 's.next_review_at', 's.retention_stage']);

        $out = [];

        foreach ($rows as $row) {
            $conceptId = (int) $row->concept_id;
            $current = $out[$conceptId] ?? null;

            $candidate = [
                'status' => $row->status,
                'next_review_at' => $row->next_review_at,
                'retention_stage' => (int) $row->retention_stage,
            ];

            if ($current === null || ($rank[$row->status] ?? 0) < ($rank[$current['status']] ?? 0)) {
                $out[$conceptId] = $candidate;
            }
        }

        return $out;
    }

    /** @param array<int,int> $conceptIds */
    private function practiceByConcept($studentId, array $conceptIds): array
    {
        $rows = DB::table('pal_adaptive_response')
            ->where('student_id', (int) $studentId)
            ->whereIn('concept_id', $conceptIds)
            ->groupBy('concept_id', 'difficulty_served')
            ->get([
                'concept_id', 'difficulty_served',
                DB::raw('COUNT(*) as attempted'),
                DB::raw('SUM(is_correct) as correct'),
            ]);

        $out = [];

        foreach ($rows as $row) {
            $attempted = (int) $row->attempted;
            $correct = (int) $row->correct;

            $out[(int) $row->concept_id][$row->difficulty_served] = [
                'attempted' => $attempted,
                'correct' => $correct,
                'accuracy' => $attempted > 0 ? round($correct / $attempted * 100, 2) : 0.0,
            ];
        }

        return $out;
    }

    private function isDue(?string $at): bool
    {
        return $at !== null && strtotime($at) !== false && strtotime($at) <= time();
    }

    private function daysUntil(?string $at): ?int
    {
        if ($at === null || strtotime($at) === false) {
            return null;
        }

        return (int) ceil((strtotime($at) - time()) / 86400);
    }
}
