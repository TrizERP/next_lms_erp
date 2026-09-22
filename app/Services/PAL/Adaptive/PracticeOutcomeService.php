<?php

namespace App\Services\PAL\Adaptive;

use App\Models\PAL\ConceptNode;
use App\Services\PAL\Coherence\MasteryUpdater;
use App\Services\PAL\Content\MisconceptionLibraryService;
use App\Services\PAL\Questions\DifficultyBands;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * What happens when a practice set is finished.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS EXISTS
 * ---------------------------------------------------------------------------
 * Two things were missing at the end of practice.
 *
 * 1. THE EVIDENCE WENT NOWHERE. recordAnswer() writes pal_adaptive_response and
 *    stops. The chapter diagnostic reaches BKT through DiagnosticEsoBridge, but
 *    practice did not reach it at all - so a learner could answer fifty
 *    practice questions and their p_mastery would not move by a thousandth,
 *    while fifteen diagnostic questions moved it immediately. Mastery that
 *    ignores the activity most likely to produce it is not mastery.
 *
 * 2. NOTHING DECIDED WHAT CAME NEXT. The concept result screen offered three
 *    buttons - practise again, all concepts, view plan - and left the choice to
 *    the learner. That is a menu, not an adaptive system. The point of the
 *    engine is that it knows whether this learner needs re-teaching, more
 *    practice, a harder band, or a mastery verdict.
 *
 * ---------------------------------------------------------------------------
 * WHAT IT DOES NOT DO
 * ---------------------------------------------------------------------------
 * It does not teach, check, grant mastery or schedule retention. Those belong
 * to EsoPolicyService and stay there. This routes TO them, and hands over the
 * evidence they reason from. The one verdict it owns is "which of those should
 * happen next", and every threshold it uses is borrowed from whatever already
 * owns it - MasteryLadder for bands, the 40/70 cuts the scorer and the
 * difficulty rule already share, and the engine for the mastery verdict.
 */
class PracticeOutcomeService
{
    /** Names this producer in pal_learning_evidence.evidence_source. */
    public const SOURCE = 'pal_practice';

    /** Below this accuracy the band is not the problem, the concept is. */
    private const RETEACH_CUT = 40.0;

    /** Answers needed before a low score means anything. */
    private const MIN_FOR_VERDICT = 3;

    public function __construct(
        private AdaptiveLearningService $adaptive = new AdaptiveLearningService(),
    ) {
    }

    /**
     * Close out a practice set: publish what was learned, then say what next.
     *
     * @return array{
     *     concept_id: int, result: array, published: int,
     *     misconception: ?array, next: array
     * }
     */
    public function complete($studentId, int $conceptId, $subInstituteId): array
    {
        $published = $this->publishEvidence($studentId, $conceptId, $subInstituteId);
        $misconception = $this->detect($studentId, $conceptId, $subInstituteId, $published['new_wrong']);

        $result = $this->adaptive->conceptResult($studentId, $conceptId, $subInstituteId);

        return [
            'concept_id' => $conceptId,
            'result' => $result,
            'published' => $published['count'],
            'misconception' => $misconception,
            'next' => $this->decide($result, $misconception, $subInstituteId),
        ];
    }

    /**
     * Send practice answers to the shared mastery ledger.
     *
     * IDEMPOTENT. pal_learning_evidence is append-only and replay() recomputes
     * BKT over every row, so publishing the same answer twice would inflate the
     * learner's mastery. Each row records its question_id in context_data, so
     * the ones already sent are subtracted before anything is written.
     *
     * @return array{count: int, new_wrong: array<int,int>}
     */
    private function publishEvidence($studentId, int $conceptId, $subInstituteId): array
    {
        $answers = DB::table('pal_adaptive_response')
            ->where('student_id', (int) $studentId)
            ->where('concept_id', $conceptId)
            ->whereNotNull('answer_master_id')
            ->orderBy('id')
            ->get(['question_id', 'answer_master_id', 'is_correct']);

        if ($answers->isEmpty()) {
            return ['count' => 0, 'new_wrong' => []];
        }

        $already = $this->publishedQuestionIds($studentId, $conceptId);

        $batch = [];
        $newWrong = [];

        foreach ($answers as $row) {
            $questionId = (int) $row->question_id;

            if (isset($already[$questionId])) {
                continue;
            }

            $correct = (int) $row->is_correct === 1;

            $batch[] = [
                'question_id' => $questionId,
                'correct' => $correct,
                'evidence_source' => self::SOURCE,
            ];

            if (! $correct) {
                $newWrong[$questionId] = (int) $row->answer_master_id;
            }
        }

        if ($batch === []) {
            return ['count' => 0, 'new_wrong' => []];
        }

        try {
            // recordBatch appends, replays BKT and upserts pal_concept_mastery
            // in one transaction, then pushes to the graph after commit.
            app(MasteryUpdater::class)->recordBatch(
                (int) $studentId,
                $conceptId,
                (int) $subInstituteId,
                $batch
            );
        } catch (\Throwable $e) {
            // The answers are already durable in pal_adaptive_response. A
            // reporting hand-off must never cost a learner their practice.
            Log::warning('PAL practice evidence hand-off failed', [
                'student_id' => $studentId,
                'concept_id' => $conceptId,
                'error' => $e->getMessage(),
            ]);

            return ['count' => 0, 'new_wrong' => []];
        }

        return ['count' => count($batch), 'new_wrong' => $newWrong];
    }

    /**
     * Question ids already in the ledger for this learner and concept.
     *
     * @return array<int,true>
     */
    private function publishedQuestionIds($studentId, int $conceptId): array
    {
        $rows = DB::table('pal_learning_evidence')
            ->where('learner_id', (int) $studentId)
            ->where('concept_id', $conceptId)
            ->where('evidence_type', 'question_response')
            ->pluck('context_data');

        $out = [];

        foreach ($rows as $json) {
            $data = json_decode((string) $json, true);
            $id = is_array($data) ? ($data['question_id'] ?? null) : null;

            if ($id !== null) {
                $out[(int) $id] = true;
            }
        }

        return $out;
    }

    /**
     * Match newly-wrong answers against the curated misconception library.
     *
     * Only NEW wrong answers, because detectAndRoute() increments
     * detection_count and feeds prevalence and teacher alerts - re-running it
     * over the learner's whole history on every submit would inflate all three.
     *
     * @param  array<int,int>  $newWrong  question_id => answer_master_id
     * @return array<string,mixed>|null
     */
    private function detect($studentId, int $conceptId, $subInstituteId, array $newWrong): ?array
    {
        foreach ($newWrong as $questionId => $answerMasterId) {
            try {
                $hit = app(MisconceptionLibraryService::class)->detectAndRoute(
                    (int) $studentId,
                    $questionId,
                    $answerMasterId,
                    (int) $subInstituteId
                );
            } catch (\Throwable $e) {
                Log::warning('PAL practice misconception routing failed', [
                    'student_id' => $studentId,
                    'question_id' => $questionId,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            // The first real hit wins. Showing a learner two corrections at
            // once teaches neither.
            if (! empty($hit['misconception'])) {
                return $hit;
            }
        }

        return null;
    }

    /**
     * The single next step for this concept.
     *
     * Ordered by what blocks the learner most. A misconception outranks a low
     * score, because practising harder against a wrong mental model only
     * rehearses the wrong model.
     *
     * @param  array<string,mixed>  $result  from AdaptiveLearningService::conceptResult()
     * @param  array<string,mixed>|null  $misconception
     * @return array{action: string, band: ?string, reason: string, eso: bool, concept_id: int}
     */
    private function decide(array $result, ?array $misconception, $subInstituteId): array
    {
        $conceptId = (int) ($result['concept_id'] ?? 0);
        $esoReady = $this->esoReady($conceptId, $subInstituteId);
        $ladder = $result['ladder'] ?? [];
        $progress = $result['progress'] ?? [];
        $attempted = (int) ($progress['attempted'] ?? 0);
        $accuracy = (float) ($progress['accuracy'] ?? 0);

        // 1. A live misconception. Correct the model before drilling it.
        if ($misconception !== null) {
            return [
                'action' => 'remediate',
                'band' => null,
                'concept_id' => $conceptId,
                'eso' => $esoReady,
                'reason' => $misconception['misconception']['description']
                    ?? 'A specific mix-up showed up in your answers. Clearing that up comes first.',
            ];
        }

        // 2. Nothing answered yet - there is no verdict to give.
        if ($attempted === 0) {
            return [
                'action' => 'practice',
                'band' => $result['next_difficulty'] ?? DifficultyBands::EASY,
                'concept_id' => $conceptId,
                'eso' => $esoReady,
                'reason' => 'Start with a short practice set to see where you are.',
            ];
        }

        // 3. Well below the pass mark on enough answers: the band is not the
        //    problem, the concept is. Re-teach rather than serve more questions.
        if ($attempted >= self::MIN_FOR_VERDICT && $accuracy < self::RETEACH_CUT) {
            return [
                'action' => $esoReady ? 'reteach' : 'review_content',
                'band' => $result['next_difficulty'] ?? null,
                'concept_id' => $conceptId,
                'eso' => $esoReady,
                'reason' => sprintf(
                    'You are at %.0f%% on this concept. More questions will not help yet - it is worth going back over the idea first.',
                    $accuracy
                ),
            ];
        }

        // 4. Every band that HAS questions is cleared. Hand the mastery verdict
        //    to the engine, which judges Knowledge and Application separately
        //    and owns the evidence floor.
        if (! empty($ladder['mastered'])) {
            return [
                'action' => $esoReady ? 'mastery_check' : 'mastered',
                'band' => null,
                'concept_id' => $conceptId,
                'eso' => $esoReady,
                'reason' => $ladder['reason'] ?? 'Every level available for this concept is cleared.',
            ];
        }

        // 5. This band is done but harder ones remain.
        $nextBand = $ladder['next_band'] ?? null;
        $current = $result['current_difficulty'] ?? null;

        if ($nextBand !== null && $current !== null && $nextBand !== $current
            && in_array($current, $ladder['bands_cleared'] ?? [], true)) {
            return [
                'action' => 'advance_band',
                'band' => $nextBand,
                'concept_id' => $conceptId,
                'eso' => $esoReady,
                'reason' => sprintf('%s is cleared. Next up is %s.', ucfirst((string) $current), $nextBand),
            ];
        }

        // 6. Mid-range: keep going where they are.
        return [
            'action' => 'continue_practice',
            'band' => $nextBand ?? $result['next_difficulty'] ?? null,
            'concept_id' => $conceptId,
            'eso' => $esoReady,
            'reason' => $ladder['reason'] ?? 'Keep practising at this level.',
        ];
    }

    /** Whether the engine can actually run this concept. */
    private function esoReady(int $conceptId, $subInstituteId): bool
    {
        if ($conceptId <= 0) {
            return false;
        }

        return ConceptNode::query()
            ->forConcept($conceptId)
            ->forTenant($subInstituteId !== null ? (int) $subInstituteId : null)
            ->exists();
    }
}
