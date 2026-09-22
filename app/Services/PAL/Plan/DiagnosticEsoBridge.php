<?php

namespace App\Services\PAL\Plan;

use App\Models\PAL\ConceptNode;
use App\Models\PAL\DiagnosticAttempt;
use App\Services\Eso\EsoPolicyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Hands a finished chapter diagnostic to the Adaptive Learning Engine, so the
 * journey after it (Learn -> Check -> Practice -> Mastery -> Recall) starts
 * from what the learner has just demonstrated.
 *
 * ---------------------------------------------------------------------------
 * WHY A BRIDGE IS NEEDED AT ALL
 * ---------------------------------------------------------------------------
 * The chapter diagnostic and the engine measure the same learner against the
 * same concepts, but they do not share a state table:
 *
 *   - the diagnostic writes pal_diagnostic_response, keyed on lms_concept.id
 *   - the engine reads learner_node_state, keyed on pal_concept_nodes.id
 *
 * EsoPolicyService::conceptMasteryAverage() reads ONLY learner_node_state - it
 * does not consult pal_concept_mastery. So publishing the diagnostic to the
 * shared evidence ledger, while correct for reporting, would have no effect on
 * what the engine decides to do next: it would open on an unseen concept and
 * re-probe topics the learner answered fifteen questions about a minute ago.
 *
 * ---------------------------------------------------------------------------
 * WHY IT CALLS scoreDiagnostic() RATHER THAN WRITING learner_node_state
 * ---------------------------------------------------------------------------
 * learner_node_state is the engine's own state, and its fields interact:
 * `status`, `taught_at`, `cfu_passed_at`, `next_review_at` and the retention
 * ladder are set together, and the clean-sweep rule that grants mastery on a
 * diagnostic carries a documented departure from ADR-001 §4.1 that only
 * EsoPolicyService knows about.
 *
 * Writing those columns from outside would fork that logic immediately. So this
 * class does no scoring of its own: it translates (question -> node), then
 * hands the responses to the engine's own sanctioned entry point and lets it
 * apply its rules. Correctness is resolved inside the engine from
 * answer_master_id, never passed in.
 *
 * ---------------------------------------------------------------------------
 * IDEMPOTENCE
 * ---------------------------------------------------------------------------
 * scoreDiagnostic() applies a weighted update per response and appends to the
 * evidence ledger, which replays through BKT. Publishing the same attempt twice
 * would therefore count every answer twice and inflate the learner's mastery.
 *
 * There is no "published" column to set, and adding one is not worth a schema
 * change, so the guard reads the engine's own append-only log: a question this
 * student has already answered in diagnostic mode is skipped. That also makes
 * the bridge safe across two different attempts that happen to share a question.
 */
class DiagnosticEsoBridge
{
    public function __construct(private ?EsoPolicyService $eso = null)
    {
    }

    /**
     * Publish a submitted attempt into the engine.
     *
     * @return array{
     *     published: bool, reason: ?string,
     *     concepts: int, nodes: int, responses: int,
     *     skipped_already_published: int, skipped_no_node: int,
     *     results: array<int,array<string,mixed>>
     * }
     */
    public function publish(DiagnosticAttempt $attempt, $subInstituteId = null): array
    {
        $empty = [
            'published' => false, 'reason' => null,
            'concepts' => 0, 'nodes' => 0, 'responses' => 0,
            'skipped_already_published' => 0, 'skipped_no_node' => 0,
            'results' => [],
        ];

        // An in-progress paper has answers that may still change. Seeding the
        // engine from it would teach against a half-finished measurement.
        if (! $attempt->isSubmitted()) {
            return array_merge($empty, ['reason' => 'attempt_not_submitted']);
        }

        $subInstituteId = (int) ($subInstituteId ?? $attempt->sub_institute_id);
        $studentId = (int) $attempt->student_id;

        // Unanswered questions carry no signal. They are already counted as
        // `unanswered` on the attempt; feeding them in as evidence would score
        // a blank as a wrong answer.
        $responses = $attempt->responses()
            ->whereNotNull('answer_master_id')
            ->get(['question_id', 'answer_master_id']);

        if ($responses->isEmpty()) {
            return array_merge($empty, ['reason' => 'no_answered_responses']);
        }

        $questionIds = $responses->pluck('question_id')->map(fn ($id) => (int) $id)->all();

        $nodeByQuestion = $this->nodesForQuestions($questionIds, $subInstituteId);
        $alreadyPublished = $this->alreadyPublished($studentId, $questionIds);

        $byConcept = [];
        $skippedNoNode = 0;
        $skippedPublished = 0;

        foreach ($responses as $response) {
            $questionId = (int) $response->question_id;

            if (isset($alreadyPublished[$questionId])) {
                $skippedPublished++;

                continue;
            }

            $node = $nodeByQuestion[$questionId] ?? null;

            // No node means the engine has nothing to attach this answer to.
            // Counted, not guessed at - the fix is pal:eso-bootstrap, not an
            // invented mapping.
            if ($node === null) {
                $skippedNoNode++;

                continue;
            }

            // Grouped by the NODE's concept, not by the diagnostic's own
            // concept snapshot. The snapshot falls back to the chapter's first
            // concept when a question carries no concept_id, and handing the
            // engine that fallback would attribute answers to a concept they
            // were never about.
            $byConcept[(int) $node->concept_id][] = [
                'node_id' => (int) $node->id,
                'answer_master_id' => (int) $response->answer_master_id,
            ];
        }

        if ($byConcept === []) {
            return array_merge($empty, [
                'reason' => $skippedPublished > 0 ? 'already_published' : 'no_eso_nodes',
                'skipped_already_published' => $skippedPublished,
                'skipped_no_node' => $skippedNoNode,
            ]);
        }

        $eso = $this->eso ?? app(EsoPolicyService::class);
        $results = [];
        $nodes = 0;
        $published = 0;

        foreach ($byConcept as $conceptId => $conceptResponses) {
            try {
                $scored = $eso->scoreDiagnostic($studentId, (int) $conceptId, $subInstituteId, $conceptResponses);

                $results[] = ['concept_id' => (int) $conceptId, 'nodes' => $scored];
                $nodes += count($scored);
                $published += count($conceptResponses);
            } catch (\Throwable $e) {
                // One concept failing must not lose the others, and must never
                // fail the learner's submit - the diagnostic itself is already
                // durable in pal_diagnostic_response.
                Log::warning('PAL diagnostic -> ESO hand-off failed for one concept', [
                    'student_id' => $studentId,
                    'concept_id' => $conceptId,
                    'attempt_id' => $attempt->id,
                    'error' => $e->getMessage(),
                ]);

                $results[] = ['concept_id' => (int) $conceptId, 'error' => $e->getMessage()];
            }
        }

        return [
            'published' => $published > 0,
            'reason' => null,
            'concepts' => count($byConcept),
            'nodes' => $nodes,
            'responses' => $published,
            'skipped_already_published' => $skippedPublished,
            'skipped_no_node' => $skippedNoNode,
            'results' => $results,
        ];
    }

    /**
     * Which concepts of a chapter the engine can actually run.
     *
     * The UI needs this to avoid offering Plan/Learn/Check on a concept the
     * engine would refuse - a dead button is worse than an absent one, and
     * "not ready yet" is a true and useful thing to say.
     *
     * @return array{
     *     chapter_id: int, concepts_total: int, concepts_ready: int,
     *     ready: array<int,int>, not_ready: array<int,int>, eso_available: bool
     * }
     */
    public function readiness(int $chapterId, $subInstituteId): array
    {
        $conceptIds = DB::table('lms_concept')
            ->where('chapter_id', $chapterId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($conceptIds === []) {
            return [
                'chapter_id' => $chapterId, 'concepts_total' => 0, 'concepts_ready' => 0,
                'ready' => [], 'not_ready' => [], 'eso_available' => false,
            ];
        }

        $ready = ConceptNode::query()
            ->whereIn('concept_id', $conceptIds)
            ->forTenant($subInstituteId !== null ? (int) $subInstituteId : null)
            ->distinct()
            ->pluck('concept_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return [
            'chapter_id' => $chapterId,
            'concepts_total' => count($conceptIds),
            'concepts_ready' => count($ready),
            'ready' => array_values($ready),
            'not_ready' => array_values(array_diff($conceptIds, $ready)),
            'eso_available' => $ready !== [],
        ];
    }

    /**
     * question_id => node row, in one query for the whole paper.
     *
     * Prefers an explicit pal_question_metadata.node_id (what
     * pal:eso-bootstrap writes). Falls back to the question's own concept_id
     * resolved to that concept's node, which covers questions tagged before the
     * node mapping existed.
     *
     * @param  array<int,int>  $questionIds
     * @return array<int,object>
     */
    private function nodesForQuestions(array $questionIds, int $subInstituteId): array
    {
        $questionIds = array_values(array_unique($questionIds));

        if ($questionIds === []) {
            return [];
        }

        $out = [];

        $mapped = DB::table('pal_question_metadata as m')
            ->join('pal_concept_nodes as n', 'n.id', '=', 'm.node_id')
            ->whereIn('m.question_id', $questionIds)
            ->whereNotNull('m.node_id')
            ->whereIn('n.sub_institute_id', array_unique([$subInstituteId, 0]))
            ->get(['m.question_id', 'n.id', 'n.concept_id']);

        foreach ($mapped as $row) {
            $out[(int) $row->question_id] ??= $row;
        }

        $missing = array_values(array_diff($questionIds, array_keys($out)));

        if ($missing === []) {
            return $out;
        }

        // Fallback: the question declares a concept, and that concept has a
        // node. Ordered by id so the same question always resolves to the same
        // node when a concept carries several (K before A before S, as
        // pal:eso-bootstrap writes them).
        $viaConcept = DB::table('lms_question_master as q')
            ->join('pal_concept_nodes as n', 'n.concept_id', '=', 'q.concept_id')
            ->whereIn('q.id', $missing)
            ->whereNotNull('q.concept_id')
            ->whereIn('n.sub_institute_id', array_unique([$subInstituteId, 0]))
            ->orderBy('n.id')
            ->get(['q.id as question_id', 'n.id', 'n.concept_id']);

        foreach ($viaConcept as $row) {
            $out[(int) $row->question_id] ??= $row;
        }

        return $out;
    }

    /**
     * Questions this learner has already answered in diagnostic mode.
     *
     * @param  array<int,int>  $questionIds
     * @return array<int,true>
     */
    private function alreadyPublished(int $studentId, array $questionIds): array
    {
        if ($questionIds === []) {
            return [];
        }

        $rows = DB::table('eso_response_log')
            ->where('student_id', $studentId)
            ->where('mode', EsoPolicyService::RESPONSE_MODE_DIAGNOSTIC)
            ->whereIn('question_id', array_values(array_unique($questionIds)))
            ->distinct()
            ->pluck('question_id');

        $out = [];

        foreach ($rows as $id) {
            $out[(int) $id] = true;
        }

        return $out;
    }
}
