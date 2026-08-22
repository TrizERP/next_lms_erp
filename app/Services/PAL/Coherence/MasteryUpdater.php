<?php

namespace App\Services\PAL\Coherence;

use App\Services\Graph\CoherenceGraphProjection;
use App\Services\PAL\Administration\ArchitectureRegistry;
use App\Services\PAL\Runtime\BktEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns one answered question into learner state, in MariaDB and then in the
 * graph. This is the write path that makes the coherence map move in real time.
 *
 * ---------------------------------------------------------------------------
 * ORDER OF OPERATIONS, AND WHY
 * ---------------------------------------------------------------------------
 *   1. INSIDE a transaction:  append the evidence row, replay BKT over the
 *      learner's full history for that concept, upsert pal_concept_mastery
 *      with graph_synced_at = NULL, append the exposure row.
 *   2. AFTER it commits:      push the new mastery edge to Neo4j and stamp
 *      graph_synced_at on success.
 *
 * The split is the same discipline as GraphSync and for the same two reasons: a
 * bolt round-trip inside an open transaction holds MariaDB row locks across a
 * remote network call, and a crash between COMMIT and the graph write must not
 * lose the event. `graph_synced_at IS NULL OR < updated_at` IS the outbox - any
 * row in that state is owed to the graph and `pal:coherence-sync --mastery`
 * sweeps it. Nothing is ever lost; it is only ever late.
 *
 * ---------------------------------------------------------------------------
 * WHY REPLAY THE WHOLE HISTORY INSTEAD OF UPDATING IN PLACE
 * ---------------------------------------------------------------------------
 * BktEngine::trace() is defined over an ordered response sequence starting from
 * p_init, not as an incremental step. Feeding it only the newest answer would
 * restart every learner at the prior on every call. Replaying is also what
 * makes the number reproducible: the same history always yields the same
 * mastery, so a support question can be answered by re-running the trace rather
 * than by trusting a stored float nobody can audit.
 *
 * A learner's history for ONE concept is tens of rows, not thousands - the
 * replay is cheap. If it ever stops being cheap, the fix is a checkpoint
 * column, not an incremental update that silently diverges.
 */
class MasteryUpdater
{
    public function __construct(
        private readonly ArchitectureRegistry $registry,
        private readonly CoherenceGraphProjection $projection,
    ) {
    }

    /**
     * Record one response and re-estimate mastery for its concept.
     *
     * @param  array{
     *     question_id?: int|null,
     *     content_id?: int|null,
     *     session_id?: int|null,
     *     correct: bool,
     *     misconception_tag?: string|null,
     *     duration_seconds?: int|null
     * }  $evidence
     * @return array<string, mixed>  the new state, for the API response
     */
    public function record(int $learnerId, int $conceptId, int $tenantId, array $evidence): array
    {
        $state = DB::transaction(function () use ($learnerId, $conceptId, $tenantId, $evidence) {
            $this->appendEvidence($learnerId, $conceptId, $evidence);

            $trace = $this->replay($learnerId, $conceptId, $tenantId);

            $this->upsertMastery($learnerId, $conceptId, $tenantId, $trace);
            $this->appendExposure($learnerId, $conceptId, $tenantId, $evidence);

            return $trace;
        });

        // After the commit: never inside it.
        $this->pushToGraph($learnerId, $conceptId);

        return $state;
    }

    /**
     * Rebuild mastery for every concept a learner has evidence on.
     *
     * This is how a learner who has been using the LMS for years arrives in the
     * coherence map with a real starting position instead of a blank one - see
     * pal:coherence-replay.
     *
     * @return array{concepts: int, evidence_rows: int}
     */
    public function replayLearner(int $learnerId, int $tenantId): array
    {
        $conceptIds = DB::table('pal_learning_evidence')
            ->where('learner_id', $learnerId)
            ->whereNotNull('concept_id')
            ->distinct()
            ->pluck('concept_id');

        $rows = 0;

        foreach ($conceptIds as $conceptId) {
            $trace = $this->replay($learnerId, (int) $conceptId, $tenantId);
            $this->upsertMastery($learnerId, (int) $conceptId, $tenantId, $trace);
            $rows += (int) $trace['attempts'];
        }

        return ['concepts' => $conceptIds->count(), 'evidence_rows' => $rows];
    }

    // ==================================================================

    private function appendEvidence(int $learnerId, int $conceptId, array $evidence): void
    {
        DB::table('pal_learning_evidence')->insert([
            'learner_id'    => $learnerId,
            'concept_id'    => $conceptId,
            'content_id'    => $evidence['content_id'] ?? null,
            'session_id'    => $evidence['session_id'] ?? null,
            'evidence_type' => 'question_response',
            // score carries correctness on a 0..1 axis so a partially-credited
            // response can be recorded later without a schema change.
            'score'      => ! empty($evidence['correct']) ? 1.0 : 0.0,
            'completion' => true,
            // NOT NULL with a default of 0 on this table - passing null is a
            // constraint violation, not an "unknown".
            'duration_seconds' => (int) ($evidence['duration_seconds'] ?? 0),
            'evidence_source'  => 'coherence_map',
            'context_data'     => json_encode(array_filter([
                'question_id'       => $evidence['question_id'] ?? null,
                'misconception_tag' => $evidence['misconception_tag'] ?? null,
            ], fn ($v) => $v !== null)),
            'recorded_at' => now(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /**
     * Replay every recorded response for this (learner, concept) through BKT.
     *
     * @return array<string, mixed>
     */
    private function replay(int $learnerId, int $conceptId, int $tenantId): array
    {
        $responses = DB::table('pal_learning_evidence')
            ->where('learner_id', $learnerId)
            ->where('concept_id', $conceptId)
            ->where('evidence_type', 'question_response')
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get(['score'])
            ->map(fn ($r) => ['correct' => ((float) $r->score) >= 0.5])
            ->all();

        $bkt = BktEngine::fromSettings((array) $this->registry->settings('mastery-model', 'bkt', $tenantId));
        $bands = (array) $this->registry->settings('mastery-model', 'bands', $tenantId);

        $gate = $this->gateFor($conceptId, $tenantId);
        $trace = $bkt->trace($responses);
        $band = $bkt->band($trace['mastery'], $bands, $gate);

        return $trace + [
            'band'     => $band['key'] ?? null,
            'tier'     => $band['tier'] ?? null,
            'gate'     => $gate,
            'mastered' => $trace['mastery'] >= $gate && $trace['credited'],
            'streak'   => $this->trailingStreak($responses),
        ];
    }

    private function upsertMastery(int $learnerId, int $conceptId, int $tenantId, array $trace): void
    {
        DB::table('pal_concept_mastery')->updateOrInsert(
            ['learner_id' => $learnerId, 'concept_ref_id' => $conceptId],
            [
                'sub_institute_id'  => $tenantId,
                'p_mastery'         => $trace['mastery'],
                'band'              => $trace['band'],
                'attempts'          => $trace['attempts'],
                'correct'           => $trace['correct'],
                'streak'            => $trace['streak'],
                'mastery_gate'      => $trace['gate'],
                'last_evidence_at'  => now(),
                'first_evidence_at' => DB::raw('COALESCE(first_evidence_at, NOW())'),
                // Mark the row owed to the graph. pushToGraph() stamps it on
                // success; if that never happens the sweeper finds it here.
                'graph_synced_at' => null,
                'updated_at'      => now(),
                'created_at'      => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );
    }

    private function appendExposure(int $learnerId, int $conceptId, int $tenantId, array $evidence): void
    {
        DB::table('pal_learner_content_exposure')->insert([
            'learner_id'        => $learnerId,
            'sub_institute_id'  => $tenantId,
            'concept_ref_id'    => $conceptId,
            'content_type'      => isset($evidence['question_id']) ? 'assessment' : 'content',
            'content_master_id' => $evidence['content_id'] ?? null,
            'question_id'       => $evidence['question_id'] ?? null,
            'session_id'        => $evidence['session_id'] ?? null,
            'misconception_tag' => $evidence['misconception_tag'] ?? null,
            'reason'            => 'first_delivery',
            'served_at'         => now(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    /**
     * Push this one mastery row to Neo4j. Never throws: the row is already
     * durable in MariaDB and flagged as owed, so a graph outage must not turn a
     * recorded answer into a 500 for the learner.
     */
    private function pushToGraph(int $learnerId, int $conceptId): void
    {
        try {
            $row = DB::table('pal_concept_mastery')
                ->where('learner_id', $learnerId)
                ->where('concept_ref_id', $conceptId)
                ->first();

            if ($row === null) {
                return;
            }

            $result = $this->projection->projectMastery([$row]);

            if (($result['mastery'] ?? 0) > 0) {
                DB::table('pal_concept_mastery')
                    ->where('learner_id', $learnerId)
                    ->where('concept_ref_id', $conceptId)
                    ->update(['graph_synced_at' => now()]);

                return;
            }

            // Zero written means an endpoint is absent - almost always a
            // :StuDetail that was never backfilled. Distinct from an outage and
            // worth its own line, because the fix is different.
            Log::warning('Coherence mastery not written: graph endpoint missing', [
                'learner_id' => $learnerId,
                'concept_id' => $conceptId,
                'hint'       => 'run neo4j:reconcile --entity=tblstudent --tenant=<id> --fix, then pal:coherence-sync --mastery',
            ]);
        } catch (Throwable $e) {
            Log::error('Coherence mastery graph push failed; row remains queued', [
                'learner_id' => $learnerId,
                'concept_id' => $conceptId,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * The concept's mastery gate. Mirrors CoherenceGraphProjection::gate() -
     * the two must agree or a learner would be judged mastered by one component
     * and not the other.
     */
    private function gateFor(int $conceptId, int $tenantId): float
    {
        $gate = DB::table('pal_concept_metadata')
            ->where('concept_ref_id', $conceptId)
            ->whereIn('sub_institute_id', [$tenantId, 0])
            ->orderByRaw('sub_institute_id = ? DESC', [$tenantId])
            ->value('mastery_gate');

        return ($gate !== null && (float) $gate > 0) ? (float) $gate : 0.70;
    }

    /**
     * Consecutive correct answers at the end of the sequence; negative for a
     * run of wrong ones. Frustration detection reads the sign, not the value.
     *
     * @param  array<int, array{correct: bool}>  $responses
     */
    private function trailingStreak(array $responses): int
    {
        if ($responses === []) {
            return 0;
        }

        $last = (bool) end($responses)['correct'];
        $streak = 0;

        foreach (array_reverse($responses) as $r) {
            if ((bool) $r['correct'] !== $last) {
                break;
            }

            $streak++;
        }

        return $last ? $streak : -$streak;
    }
}
