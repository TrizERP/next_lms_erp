<?php

namespace App\Services\Graph;

use App\Services\Neo4jService;
use Illuminate\Support\Facades\DB;

/**
 * Projects the Set Coherence Map from MariaDB into Neo4j.
 *
 * ---------------------------------------------------------------------------
 * THE SHAPE
 * ---------------------------------------------------------------------------
 *   (:Subject)-[:COVERS_CHAPTER]->(:Chapter)-[:HAS_CONCEPT]->(:Concept)
 *   (:Concept)-[:REQUIRES]->(:Concept)        the coherence spine
 *   (:Concept)-[:CROSS_LINKS]->(:Concept)     cross-curricular transfer
 *   (:Content)-[:TEACHES]->(:Concept)
 *   (:Question)-[:ASSESSES]->(:Concept)
 *   (:StuDetail)-[:HAS_MASTERY {p, attempts, band}]->(:Concept)
 *
 * ---------------------------------------------------------------------------
 * KEY TYPES ARE NOT NEGOTIABLE - MEASURED LIVE 2026-08-18
 * ---------------------------------------------------------------------------
 * This graph stores ids under two conventions AND two PHP types, and Neo4j
 * treats `8038` and `'8038'` as different values - which is why the uniqueness
 * constraint on `Chapter.chId` never fired and every Class 7 chapter exists
 * twice. Getting a cast wrong here does not error; it silently mints a parallel
 * node set. Verified counts:
 *
 *   :Concept   conceptId  INTEGER   1,381 nodes / 1,381 distinct (UNIQUE constraint)
 *   :Question  qId        INTEGER  31,753 nodes / 31,753 distinct (UNIQUE constraint)
 *   :Content   id         STRING   31,362 nodes / 31,362 distinct (no constraint)
 *   :StuDetail sdId       INTEGER
 *   :Chapter   chId       INTEGER on legacy nodes, STRING on uid nodes - so this
 *              class NEVER keys a chapter on chId. It matches on `uid`
 *              ('Chapter:{tenant}:0:{id}'), which only the migration-loaded node
 *              carries and which is where HAS_CONTENT already points.
 *
 * ---------------------------------------------------------------------------
 * WHY BULK CYPHER AND NOT THE OUTBOX
 * ---------------------------------------------------------------------------
 * `GraphOutbox`/`GraphDrain` exist for LIVE per-request events, where the point
 * is that the intent commits atomically with a business row. The coherence map
 * is authored content: it changes when an extraction runs or a reviewer
 * approves an edge, not on the request path. Bulk, batched, idempotent MERGE is
 * the right tool, and it keeps the student sync - the one pipeline currently
 * carrying production traffic - untouched.
 *
 * Every label and relationship type below is a hardcoded literal. Nothing from
 * the database reaches Cypher except as a bound parameter, so this class has no
 * injection surface and needs no whitelist check.
 */
class CoherenceGraphProjection
{
    /** UNWIND batch size: large enough to amortise the round-trip, small enough to keep transactions short. */
    private const BATCH = 500;

    public function __construct(private readonly Neo4jService $neo4j)
    {
    }

    // ==================================================================
    // 1. Concept nodes + (:Chapter)-[:HAS_CONCEPT]->(:Concept)
    // ==================================================================

    /**
     * MERGE a :Concept per lms_concept row in scope and enrich it with the
     * coherence properties the recommender reads.
     *
     * `SET c += $props` is deliberate, exactly as GraphDrain does it: the
     * curriculum load already wrote name/description/chapter_id onto these
     * nodes and PAL may have written others. This pass owns only the coherence
     * columns and must not blank the rest.
     *
     * @return array{concepts: int, has_concept: int, chapters_missing: int}
     */
    public function projectConcepts(int $tenant, int $standardId, int $subjectId): array
    {
        $rows = DB::table('lms_concept as c')
            ->leftJoin('pal_concept_metadata as m', function ($join) use ($tenant) {
                $join->on('m.concept_ref_id', '=', 'c.id')
                    ->whereIn('m.sub_institute_id', [$tenant, 0]);
            })
            ->where('c.sub_institute_id', $tenant)
            ->where('c.standard_id', $standardId)
            ->where('c.subject_id', $subjectId)
            ->select([
                'c.id', 'c.name', 'c.chapter_id', 'c.mastery_threshold',
                'c.estimated_mastery_minutes',
                'm.concept_code', 'm.bloom_ceiling', 'm.practice_ceiling',
                'm.priority_score', 'm.stage_gate', 'm.hpc_lens',
                'm.mastery_gate', 'm.quality_status',
            ])
            ->get();

        $concepts = 0;
        $hasConcept = 0;
        $chaptersMissing = 0;

        foreach ($rows->chunk(self::BATCH) as $chunk) {
            $payload = [];

            foreach ($chunk as $r) {
                $payload[] = [
                    'conceptId'  => (int) $r->id,
                    'chapterUid' => 'Chapter:' . $tenant . ':0:' . (int) $r->chapter_id,
                    'props'      => array_filter([
                        'name'             => $this->str($r->name),
                        'concept_code'     => $this->str($r->concept_code) ?: null,
                        'bloom_ceiling'    => $this->str($r->bloom_ceiling) ?: null,
                        'stage_gate'       => $this->str($r->stage_gate) ?: null,
                        'hpc_lens'         => $this->str($r->hpc_lens) ?: null,
                        'practice_ceiling' => $this->intOrNull($r->practice_ceiling),
                        'priority_score'   => $this->intOrNull($r->priority_score),
                        'mastery_gate'     => $this->gate($r),
                        'quality_status'   => $this->str($r->quality_status) ?: 'draft',
                        'est_minutes'      => $this->intOrNull($r->estimated_mastery_minutes),
                        'standard_id'      => (string) $standardId,
                        'subject_id'       => (string) $subjectId,
                        'sub_institute_id' => $tenant,
                        'in_coherence_map' => true,
                    ], fn ($v) => $v !== null),
                ];
            }

            $cypher = 'UNWIND $rows AS row '
                . 'MERGE (c:Concept {conceptId: row.conceptId}) '
                . 'SET c += row.props, c.coherence_synced_at = datetime() '
                . 'WITH c, row '
                . 'OPTIONAL MATCH (ch:Chapter {uid: row.chapterUid}) '
                . 'FOREACH (_ IN CASE WHEN ch IS NULL THEN [] ELSE [1] END | '
                . '    MERGE (ch)-[:HAS_CONCEPT]->(c) ) '
                . 'RETURN count(c) AS concepts, count(ch) AS linked';

            $first = $this->neo4j->run($cypher, ['rows' => $payload])->first();

            $made = $first ? (int) $first->get('concepts') : 0;
            $linked = $first ? (int) $first->get('linked') : 0;

            $concepts += $made;
            $hasConcept += $linked;
            $chaptersMissing += count($payload) - $linked;
        }

        return [
            'concepts'         => $concepts,
            'has_concept'      => $hasConcept,
            'chapters_missing' => $chaptersMissing,
        ];
    }

    // ==================================================================
    // 2. The coherence spine - REQUIRES / CROSS_LINKS
    // ==================================================================

    /**
     * Project `pal_concept_relations` as typed edges.
     *
     * Both endpoints must already exist as :Concept - an edge is never allowed
     * to CREATE one. A prerequisite pointing at a concept outside the map is a
     * content defect, and minting a bare node for it would hide exactly what a
     * reviewer needs to see. Unmatched edges are counted and returned instead.
     *
     * DIRECTION. `pal_concept_relations` reads "from REQUIRES to" as: to learn
     * `to_concept_id`, you first need `from_concept_id`. The graph edge is
     * drawn the way the recommender walks it - from the concept being attempted
     * OUT to its prerequisites:
     *
     *     (to)-[:REQUIRES]->(from)
     *
     * @return array{requires: int, cross_links: int, unresolved: int}
     */
    public function projectRelations(int $tenant, int $standardId, int $subjectId): array
    {
        $rows = DB::table('pal_concept_relations as r')
            ->join('lms_concept as f', 'f.id', '=', 'r.from_concept_id')
            ->join('lms_concept as t', 't.id', '=', 'r.to_concept_id')
            ->whereIn('r.sub_institute_id', [$tenant, 0])
            // Scoped on the TARGET concept only: a Class 7 concept may
            // legitimately require a Class 6 one, and filtering both ends to
            // the same standard would delete the very chain the map exists for.
            ->where('t.standard_id', $standardId)
            ->where('t.subject_id', $subjectId)
            ->where('t.sub_institute_id', $tenant)
            ->select([
                'r.from_concept_id', 'r.to_concept_id', 'r.relation_type',
                'r.link_type', 'r.transfer_direction', 'r.mastery_gate',
                'r.auto_suggest', 'r.suggestion_trigger_mastery',
                'r.quality_status', 'r.tagged_by',
            ])
            ->get();

        $counts = ['requires' => 0, 'cross_links' => 0, 'unresolved' => 0];

        // The relationship type is part of the query TEXT and must never be
        // interpolated from a database column, so the two types run as two
        // separate passes over two hardcoded statements.
        $passes = [
            'requires' => [
                'bucket' => 'requires',
                'cypher' => 'UNWIND $rows AS row '
                    . 'MATCH (target:Concept {conceptId: row.toId}) '
                    . 'MATCH (source:Concept {conceptId: row.fromId}) '
                    . 'MERGE (target)-[e:REQUIRES]->(source) '
                    . 'SET e += row.props '
                    . 'RETURN count(e) AS c',
            ],
            'cross_curricular' => [
                'bucket' => 'cross_links',
                'cypher' => 'UNWIND $rows AS row '
                    . 'MATCH (target:Concept {conceptId: row.toId}) '
                    . 'MATCH (source:Concept {conceptId: row.fromId}) '
                    . 'MERGE (source)-[e:CROSS_LINKS]->(target) '
                    . 'SET e += row.props '
                    . 'RETURN count(e) AS c',
            ],
        ];

        foreach ($passes as $sourceType => $pass) {
            $subset = $rows->where('relation_type', $sourceType);

            foreach ($subset->chunk(self::BATCH) as $chunk) {
                $payload = [];

                foreach ($chunk as $r) {
                    $payload[] = [
                        'fromId' => (int) $r->from_concept_id,
                        'toId'   => (int) $r->to_concept_id,
                        'props'  => array_filter([
                            'link_type'          => $this->str($r->link_type) ?: null,
                            'transfer_direction' => $this->str($r->transfer_direction) ?: null,
                            'mastery_gate'       => $r->mastery_gate === null ? null : (float) $r->mastery_gate,
                            'auto_suggest'       => (bool) $r->auto_suggest,
                            'trigger_mastery'    => $r->suggestion_trigger_mastery === null
                                ? null
                                : (float) $r->suggestion_trigger_mastery,
                            'quality_status'     => $this->str($r->quality_status) ?: 'draft',
                            'tagged_by'          => $this->str($r->tagged_by) ?: 'human',
                        ], fn ($v) => $v !== null),
                    ];
                }

                $first = $this->neo4j->run($pass['cypher'], ['rows' => $payload])->first();
                $made = $first ? (int) $first->get('c') : 0;

                $counts[$pass['bucket']] += $made;
                $counts['unresolved'] += count($payload) - $made;
            }
        }

        return $counts;
    }

    // ==================================================================
    // 3. Delivery edges - what teaches a concept, what assesses it
    // ==================================================================

    /**
     * (:Content)-[:TEACHES]->(:Concept) from pal_content_metadata.concept_ref_id.
     *
     * Content is matched on `id` AS A STRING - every one of the 31,362 :Content
     * nodes stores it that way. Binding an integer matches nothing and the pass
     * silently reports zero rather than failing.
     *
     * @return array{teaches: int, content_missing: int}
     */
    public function projectTeaches(int $tenant, int $standardId, int $subjectId): array
    {
        $rows = DB::table('pal_content_metadata as m')
            ->join('content_master as c', 'c.id', '=', 'm.content_master_id')
            ->join('lms_concept as k', 'k.id', '=', 'm.concept_ref_id')
            ->whereNotNull('m.concept_ref_id')
            ->where('c.sub_institute_id', $tenant)
            ->where('k.standard_id', $standardId)
            ->where('k.subject_id', $subjectId)
            ->select([
                'm.content_master_id', 'm.concept_ref_id', 'm.content_type',
                'm.variant_number', 'm.format', 'm.bloom_level_served',
                'm.difficulty_1_to_5', 'm.estimated_duration_minutes',
                'm.quality_status', 'm.h5p_type',
            ])
            ->get();

        $cypher = 'UNWIND $rows AS row '
            . 'MATCH (n:Content {id: row.nodeKey}) '
            . 'MATCH (c:Concept {conceptId: row.conceptId}) '
            . 'MERGE (n)-[e:TEACHES]->(c) '
            . 'SET e += row.props '
            . 'RETURN count(e) AS c';

        return $this->linkDelivery(
            $rows,
            fn ($r) => [
                'nodeKey'   => (string) (int) $r->content_master_id,   // STRING key
                'conceptId' => (int) $r->concept_ref_id,
                'props'     => array_filter([
                    'content_type'   => $this->str($r->content_type) ?: null,
                    'variant_number' => $this->intOrNull($r->variant_number),
                    'format'         => $this->str($r->format) ?: null,
                    'bloom_level'    => $this->str($r->bloom_level_served) ?: null,
                    'difficulty'     => $this->intOrNull($r->difficulty_1_to_5),
                    'duration_min'   => $this->intOrNull($r->estimated_duration_minutes),
                    'h5p_type'       => $this->str($r->h5p_type) ?: null,
                    'quality_status' => $this->str($r->quality_status) ?: 'draft',
                ], fn ($v) => $v !== null),
            ],
            $cypher,
            'teaches',
            'content_missing'
        );
    }

    /**
     * (:Question)-[:ASSESSES]->(:Concept) from pal_question_metadata.concept_ref_id.
     *
     * Question is matched on `qId` AS AN INTEGER - the opposite cast from
     * Content directly above. Both are correct; both were measured.
     *
     * @return array{assesses: int, questions_missing: int}
     */
    public function projectAssesses(int $tenant, int $standardId, int $subjectId): array
    {
        $rows = DB::table('pal_question_metadata as m')
            ->join('lms_question_master as q', 'q.id', '=', 'm.question_id')
            ->join('lms_concept as k', 'k.id', '=', 'm.concept_ref_id')
            ->whereNotNull('m.concept_ref_id')
            ->where('q.sub_institute_id', $tenant)
            ->where('k.standard_id', $standardId)
            ->where('k.subject_id', $subjectId)
            ->select([
                'm.question_id', 'm.concept_ref_id', 'm.bloom_level',
                'm.difficulty_1_to_5', 'm.practice_level', 'm.irt_b',
                'm.misconception_tags', 'm.assessment_type', 'm.quality_status',
            ])
            ->get();

        $cypher = 'UNWIND $rows AS row '
            . 'MATCH (n:Question {qId: row.nodeKey}) '
            . 'MATCH (c:Concept {conceptId: row.conceptId}) '
            . 'MERGE (n)-[e:ASSESSES]->(c) '
            . 'SET e += row.props '
            . 'RETURN count(e) AS c';

        return $this->linkDelivery(
            $rows,
            fn ($r) => [
                'nodeKey'   => (int) $r->question_id,                  // INTEGER key
                'conceptId' => (int) $r->concept_ref_id,
                'props'     => array_filter([
                    'bloom_level'     => $this->str($r->bloom_level) ?: null,
                    'difficulty'      => $this->intOrNull($r->difficulty_1_to_5),
                    'practice_level'  => $this->intOrNull($r->practice_level),
                    'irt_b'           => $r->irt_b === null ? null : (float) $r->irt_b,
                    'assessment_type' => $this->str($r->assessment_type) ?: null,
                    'quality_status'  => $this->str($r->quality_status) ?: 'draft',
                    // Flattened to a CSV scalar: Neo4j rejects nested objects,
                    // and a raw JSON string is not queryable from Cypher.
                    'misconception_tags' => $this->tagCsv($r->misconception_tags),
                ], fn ($v) => $v !== null),
            ],
            $cypher,
            'assesses',
            'questions_missing'
        );
    }

    // ==================================================================
    // 4. Learner overlay - (:StuDetail)-[:HAS_MASTERY]->(:Concept)
    // ==================================================================

    /**
     * Push mastery rows to the graph.
     *
     * Attached to :StuDetail (the PERSON, sdId = tblstudent.id), NOT to
     * :Student (one ENROLLMENT, stuId = tblstudent_enrollment.id). Concept
     * mastery is longitudinal - a learner who mastered fractions in Class 6
     * still has - while the enrollment node is replaced every academic year.
     * It also makes the key identical to the PAL API's `learnerId`, which
     * PalApiAuth already ownership-checks.
     *
     * RETURNS THE PAIRS IT ACTUALLY WROTE, not just a count. The caller stamps
     * `graph_synced_at` from this list and nothing else: stamping a row whose
     * edge never landed marks an undelivered write as delivered, the sweeper
     * stops retrying it, and it is lost silently. That is precisely how the
     * April 2026 outbox stranded 8 rows for four months, and it is not a
     * mistake worth making twice.
     *
     * @param  iterable<object>  $rows  pal_concept_mastery rows
     * @return array{mastery: int, learners_missing: int, written: array<int, array{learner: int, concept: int}>}
     */
    public function projectMastery(iterable $rows): array
    {
        $cypher = 'UNWIND $rows AS row '
            . 'MATCH (sd:StuDetail {sdId: row.learner}) '
            . 'MATCH (c:Concept {conceptId: row.conceptId}) '
            . 'MERGE (sd)-[m:HAS_MASTERY]->(c) '
            . 'SET m.p = row.p, '
            . '    m.band = row.band, '
            . '    m.attempts = row.attempts, '
            . '    m.correct = row.correct, '
            . '    m.streak = row.streak, '
            . '    m.mastery_gate = row.gate, '
            . '    m.mastered = row.p >= row.gate, '
            . '    m.updated_at = datetime() '
            . 'RETURN row.learner AS learner, row.conceptId AS concept';

        $written = [];
        $sent = 0;
        $payload = [];

        $flush = function () use (&$payload, &$written, $cypher) {
            if ($payload === []) {
                return;
            }

            foreach ($this->neo4j->run($cypher, ['rows' => $payload]) as $record) {
                $written[] = [
                    'learner' => (int) $record->get('learner'),
                    'concept' => (int) $record->get('concept'),
                ];
            }

            $payload = [];
        };

        foreach ($rows as $r) {
            $payload[] = [
                'learner'   => (int) $r->learner_id,
                'conceptId' => (int) $r->concept_ref_id,
                'p'         => (float) $r->p_mastery,
                'band'      => (string) ($r->band ?? ''),
                'attempts'  => (int) $r->attempts,
                'correct'   => (int) $r->correct,
                'streak'    => (int) $r->streak,
                'gate'      => (float) $r->mastery_gate,
            ];
            $sent++;

            if (count($payload) >= self::BATCH) {
                $flush();
            }
        }

        $flush();

        return [
            'mastery'          => count($written),
            'learners_missing' => $sent - count($written),
            'written'          => $written,
        ];
    }

    // ==================================================================

    /**
     * Shared body for every edge pass: batch, run, count what did not match.
     *
     * A miss is never an exception. Both endpoints of these edges are loaded by
     * other phases of the migration, so an absent endpoint is expected during a
     * partial rollout and the caller needs the number, not a stack trace.
     *
     * @param  iterable<object>  $rows
     * @return array<string, int>
     */
    private function linkDelivery(
        iterable $rows,
        callable $map,
        string $cypher,
        string $madeKey,
        string $missingKey
    ): array {
        $made = 0;
        $missing = 0;
        $payload = [];

        $flush = function () use (&$payload, &$made, &$missing, $cypher) {
            if ($payload === []) {
                return;
            }

            $first = $this->neo4j->run($cypher, ['rows' => $payload])->first();
            $n = $first ? (int) $first->get('c') : 0;

            $made += $n;
            $missing += count($payload) - $n;
            $payload = [];
        };

        foreach ($rows as $r) {
            $payload[] = $map($r);

            if (count($payload) >= self::BATCH) {
                $flush();
            }
        }

        $flush();

        return [$madeKey => $made, $missingKey => $missing];
    }

    /**
     * The gate the recommender compares p_mastery against.
     *
     * `lms_concept.mastery_threshold` is the legacy column and reads '0.00' on
     * almost every row; taking it at face value would mark every concept
     * mastered before the learner answered anything. `pal_concept_metadata`
     * .mastery_gate is the PAL V4 column and defaults to 0.70 - that is the
     * floor when nothing better is authored.
     */
    private function gate(object $r): float
    {
        $palGate = $r->mastery_gate ?? null;

        if ($palGate !== null && (float) $palGate > 0) {
            return (float) $palGate;
        }

        $legacy = (float) ($r->mastery_threshold ?? 0);

        // The legacy column is stored 0-100 in some rows and 0-1 in others.
        if ($legacy > 1) {
            $legacy /= 100;
        }

        return $legacy > 0 ? $legacy : 0.70;
    }

    /** JSON array column -> CSV scalar, because Neo4j will not store an object. */
    private function tagCsv($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode((string) $value, true);

        if (! is_array($decoded)) {
            return $this->str($value) ?: null;
        }

        $flat = array_filter(
            array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : null, $decoded),
            'strlen'
        );

        return $flat === [] ? null : implode(',', $flat);
    }

    private function intOrNull($value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }

    private function str($value): string
    {
        return trim((string) ($value ?? ''));
    }
}
