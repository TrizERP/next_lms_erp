<?php

namespace App\Services\PAL\Content;

use App\Models\PAL\ConceptMetadata;
use App\Models\PAL\LearnerContentExposure;
use App\Models\PAL\QuestionMetadata;
use Illuminate\Support\Facades\DB;

/**
 * The 5-level practice ladder — spec §3.1/§3.2.
 *
 * Two axes, deliberately kept apart (plan §8, R2):
 *
 *   Bloom / practice level  = COGNITIVE DEMAND. What this service routes on.
 *   Legacy PAL difficulty   = SCORE BAND (<40 easy / 40-70 medium / >=70 hard),
 *                             driven by palController for the /lms/* Blade UI.
 *
 * These are not the same thing and this service does not replace the legacy
 * bands. Nothing here writes to question_paper or changes what /lms/* serves;
 * the V4 engine routes on Bloom, legacy keeps its score bands, and they coexist
 * until someone decides otherwise. That decision is explicitly out of scope here.
 */
class BloomLadderService
{
    /**
     * Where a learner currently sits on the ladder for a concept, and whether
     * they may advance.
     *
     * @param  array  $signals  ['net_fluency' => float, 'bkt_mastery' => float,
     *                           'items_at_level' => int, 'hpc_level' => string]
     */
    public function evaluate(int $learnerId, int $conceptId, int $currentLevel, array $signals = [], ?int $subInstituteId = null): array
    {
        $levels = config('pal_content.practice_levels');

        if (! isset($levels[$currentLevel])) {
            throw new \InvalidArgumentException("Practice level {$currentLevel} is not in the 1-5 ladder.");
        }

        $ceiling = $this->ceilingFor($conceptId, $signals['hpc_level'] ?? null, $subInstituteId);
        $gate = $levels[$currentLevel]['gate'];

        $decision = [
            'learner_id' => $learnerId,
            'concept_id' => $conceptId,
            'current_level' => $currentLevel,
            'current_bloom' => $levels[$currentLevel]['bloom_level'],
            'ceiling_level' => $ceiling['level'],
            'ceiling_reason' => $ceiling['reason'],
            'gate' => $gate,
            'can_advance' => false,
            'next_level' => null,
            'blocked_by' => null,
        ];

        if ($currentLevel >= $ceiling['level']) {
            $decision['blocked_by'] = 'hpc_ceiling';

            return $decision;
        }

        if ($gate === null) {
            // L5 is terminal — there is nothing above it.
            $decision['blocked_by'] = 'terminal_level';

            return $decision;
        }

        $metric = $gate['metric'];
        $observed = $signals[$metric] ?? null;

        if ($observed === null) {
            $decision['blocked_by'] = "missing_signal:{$metric}";

            return $decision;
        }

        $decision['observed'] = round((float) $observed, 4);

        if ((float) $observed < (float) $gate['threshold']) {
            $decision['blocked_by'] = "{$metric}_below_threshold";

            return $decision;
        }

        // Fluency gates additionally require a minimum sample — a single lucky
        // answer is not evidence of fluency.
        if (isset($gate['min_items'])) {
            $items = (int) ($signals['items_at_level'] ?? 0);
            $decision['items_at_level'] = $items;
            if ($items < $gate['min_items']) {
                $decision['blocked_by'] = 'insufficient_items';

                return $decision;
            }
        }

        $decision['can_advance'] = true;
        $decision['next_level'] = $currentLevel + 1;
        $decision['next_bloom'] = $levels[$currentLevel + 1]['bloom_level'] ?? null;

        return $decision;
    }

    /**
     * Regression check — spec §3.2.
     *
     * Reads the learner's recent attempts at this level and decides whether to
     * fire a misconception corrective, demote a level, or raise a teacher alert.
     *
     * @param  array<int,bool>  $recentOutcomes  most-recent-first list of correct/incorrect
     */
    public function checkRegression(
        int $learnerId,
        int $conceptId,
        int $currentLevel,
        array $recentOutcomes,
        int $correctiveAttempts = 0
    ): array {
        $rules = config('pal_content.regression');

        $consecutiveWrong = 0;
        foreach ($recentOutcomes as $correct) {
            if ($correct) {
                break;
            }
            $consecutiveWrong++;
        }

        $result = [
            'learner_id' => $learnerId,
            'concept_id' => $conceptId,
            'current_level' => $currentLevel,
            'consecutive_wrong' => $consecutiveWrong,
            'corrective_attempts' => $correctiveAttempts,
            'action' => 'continue',
            'demote_to' => null,
            'teacher_alert' => false,
            'reason' => null,
        ];

        // A misconception that survived N corrective attempts is not a
        // misconception problem any more — the prerequisite below it is missing.
        if ($correctiveAttempts >= $rules['corrective_attempts_before_demotion'] && $currentLevel > 1) {
            $result['action'] = 'demote';
            $result['demote_to'] = $currentLevel - 1;
            $result['reason'] = 'misconception_unresolved_after_' . $correctiveAttempts . '_correctives';
        } elseif ($consecutiveWrong >= $rules['consecutive_wrong_to_misconception']) {
            $result['action'] = 'serve_misconception';
            $result['reason'] = $consecutiveWrong . '_consecutive_wrong_at_L' . $currentLevel;
        }

        // Mastery-collapse signal: the learner has fallen all the way from
        // application back to recall.
        if ($result['demote_to'] !== null
            && $currentLevel >= $rules['teacher_alert_on_drop_from']
            && $result['demote_to'] <= $rules['teacher_alert_on_drop_to']) {
            $result['teacher_alert'] = true;
            $result['reason'] .= '; mastery_collapse_L' . $currentLevel . '_to_L' . $result['demote_to'];
        }

        // A demotion below L3 that started at L3+ is also a collapse signal even
        // when it lands at L2 first — the historical low-water mark decides.
        $lowest = $this->lowestLevelSeen($learnerId, $conceptId);
        if (! $result['teacher_alert'] && $lowest !== null
            && $lowest <= $rules['teacher_alert_on_drop_to']
            && $currentLevel >= $rules['teacher_alert_on_drop_from']) {
            $result['teacher_alert'] = true;
        }

        return $result;
    }

    /**
     * The highest practice level this learner may be served for this concept.
     *
     * Two independent caps apply and the LOWER wins:
     *   - the concept's own authored ceiling (pal_concept_metadata)
     *   - the learner's HPC level (Stream 3 / Mountain 4 / Sky 5)
     */
    public function ceilingFor(int $conceptId, ?string $hpcLevel = null, ?int $subInstituteId = null): array
    {
        $max = max(array_keys(config('pal_content.practice_levels')));

        $conceptCeiling = ConceptMetadata::where('concept_ref_id', $conceptId)
            ->forTenant($subInstituteId)
            ->value('practice_ceiling');

        $level = $conceptCeiling ? (int) $conceptCeiling : $max;
        $reason = $conceptCeiling ? 'concept_ceiling' : 'default_max';

        if ($hpcLevel !== null) {
            $hpcCap = config('pal_content.hpc_ceilings')[$hpcLevel] ?? null;
            if ($hpcCap !== null && $hpcCap < $level) {
                $level = (int) $hpcCap;
                $reason = 'hpc_' . strtolower($hpcLevel);
            }
        }

        return ['level' => $level, 'reason' => $reason];
    }

    /**
     * Approved questions at a given rung of the ladder for a concept.
     *
     * CONTENT LAW C4: only 'approved' rows are returned. C7: anything already in
     * the learner's shown-set is excluded, so a learner never re-sees an item.
     */
    public function itemsForLevel(
        int $conceptId,
        int $practiceLevel,
        ?int $subInstituteId = null,
        ?int $learnerId = null,
        int $limit = 10,
        ?int $chapterId = null
    ): array {
        $query = QuestionMetadata::query()
            ->forCurriculum($conceptId ?: null, $chapterId)
            ->where('practice_level', $practiceLevel)
            ->servable()
            ->forTenant($subInstituteId);

        if ($learnerId !== null) {
            $seen = LearnerContentExposure::where('learner_id', $learnerId)
                ->when($conceptId > 0,
                    fn ($q) => $q->where('concept_ref_id', $conceptId),
                    fn ($q) => $q->where('chapter_ref_id', $chapterId))
                ->whereNotNull('question_id')
                ->pluck('question_id')
                ->all();

            if ($seen !== []) {
                $query->whereNotIn('question_id', $seen);
            }
        }

        // Prefer items that actually separate learners (spec §7.1 stage 5).
        $rows = $query->orderByRaw('discrimination_index IS NULL, discrimination_index DESC')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $titles = DB::table('lms_question_master')
            ->whereIn('id', $rows->pluck('question_id'))
            ->pluck('question_title', 'id');

        return $rows->map(fn ($m) => [
            'question_id' => (int) $m->question_id,
            'title' => $titles[$m->question_id] ?? null,
            'bloom_level' => $m->bloom_level,
            'practice_level' => (int) $m->practice_level,
            'difficulty' => $m->difficulty_1_to_5,
            'irt_b' => $m->irt_b,
            'discrimination_index' => $m->discrimination_index,
            'avg_time_seconds' => $m->avg_time_seconds,
            'scaffold_type' => $m->scaffold_type,
            'misconception_tags' => $m->misconception_tags ?? [],
        ])->all();
    }

    /**
     * The full ladder for a concept with per-level availability — what a teacher
     * dashboard or the authoring console needs to see where the content gaps are.
     */
    public function ladderStatus(int $conceptId, ?int $subInstituteId = null, ?int $chapterId = null): array
    {
        $counts = QuestionMetadata::query()
            ->forCurriculum($conceptId ?: null, $chapterId)
            ->forTenant($subInstituteId)
            ->selectRaw('practice_level, quality_status, COUNT(*) AS c')
            ->groupBy('practice_level', 'quality_status')
            ->get();

        $out = [];
        foreach (config('pal_content.practice_levels') as $level => $def) {
            $approved = (int) $counts->where('practice_level', $level)->where('quality_status', 'approved')->sum('c');
            $total = (int) $counts->where('practice_level', $level)->sum('c');

            $out[] = [
                'level' => $level,
                'name' => $def['name'],
                'bloom_level' => $def['bloom_level'],
                'scaffold' => $def['scaffold'],
                'recommended_h5p' => $def['h5p'],
                'gate' => $def['gate'],
                'items_total' => $total,
                'items_approved' => $approved,
                // The gate needs min_items to even be evaluable; a level with
                // fewer approved items than that can never be passed.
                'servable' => $approved >= (int) ($def['gate']['min_items'] ?? 1),
            ];
        }

        return ['concept_id' => $conceptId, 'levels' => $out];
    }

    /**
     * Record that an item was served, so C7's shown-set stays accurate.
     */
    public function recordExposure(
        int $learnerId,
        int $conceptId,
        int $questionId,
        int $practiceLevel,
        int $subInstituteId,
        string $reason = 'first_delivery',
        ?int $sessionId = null,
        ?int $chapterId = null
    ): void {
        LearnerContentExposure::create([
            'learner_id' => $learnerId,
            'sub_institute_id' => $subInstituteId,
            'concept_ref_id' => $conceptId ?: null,
            'chapter_ref_id' => $chapterId,
            'content_type' => 'assessment',
            'question_id' => $questionId,
            'practice_level' => $practiceLevel,
            'reason' => $reason,
            'session_id' => $sessionId,
            'served_at' => now(),
        ]);
    }

    /** Lowest practice level this learner has ever been served for a concept. */
    protected function lowestLevelSeen(int $learnerId, int $conceptId): ?int
    {
        $v = LearnerContentExposure::where('learner_id', $learnerId)
            ->where('concept_ref_id', $conceptId)
            ->whereNotNull('practice_level')
            ->min('practice_level');

        return $v === null ? null : (int) $v;
    }
}
