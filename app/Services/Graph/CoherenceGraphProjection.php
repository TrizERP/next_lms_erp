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
 *   (:Chapter)-[:REQUIRES]->(:Chapter)        chapter-grain prerequisites, added 2026-09-28
 *                                              (pal_learning_relations; chapter-grain only, see projectLearningRelations())
 *   (:Misconception)-[:AFFECTS]->(:Concept)   added 2026-09-28 (pal_misconception_library)
 *   (:Misconception)-[:CORRECTS_WITH]->(:Content)  added 2026-09-28 (pal_misconception_corrective,
 *                                              content_master_id set - 0/7,307 rows today)
 *   (:Misconception)-[:CORRECTS_WITH]->(:CorrectiveContent)  added 2026-09-29 (same table,
 *                                              content_master_id NULL - 7,307/7,307 rows today,
 *                                              i.e. this is the one that actually carries the data)
 *
 * Every edge type above except the two chapter/misconception ones added
 * 2026-09-28 is MERGE-only. As of that date, projectRelations/projectTeaches/
 * projectAssesses/projectLearningRelations/projectMisconceptions also
 * RETRACT: a row that becomes `quality_status = 'rejected'`, or is deleted
 * outright, has its corresponding edge removed on the next call for that
 * scope, not left stranded. See retract() below and the design review this
 * closes out (a rejected/deleted PAL relation previously had no path back
 * out of the graph at all).
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
                    'chapterId'  => (int) $r->chapter_id,
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

            // Chapter fallback + self-heal (2026-09-29): measured live that only
            // 5,625 of 7,981 :Chapter nodes carry `uid` at all - pipeline A's
            // live trigger sync (config/neo4j.php) keys Chapter on `chId` only
            // and has never set `uid`, so any chapter created purely through
            // that live path (as opposed to the historical bulk load) was
            // invisible to this method, and 5,802 of 7,756 concepts
            // (`chapters_missing`) silently failed to link on the first full
            // backfill because of it - not because the chapter didn't exist.
            // `chByUid` is tried first (unchanged priority); `chByChId` is a
            // fallback for exactly that gap, and when it's the one that
            // matched, this also backfills `uid` onto it so every subsequent
            // run (and any other uid-based match elsewhere in this class)
            // finds it directly. This only ever SETs one property on a node
            // pipeline A already owns creating/deleting - it does not take
            // over or duplicate that ownership.
            $cypher = 'UNWIND $rows AS row '
                . 'MERGE (c:Concept {conceptId: row.conceptId}) '
                . 'SET c += row.props, c.coherence_synced_at = datetime() '
                . 'WITH c, row '
                . 'OPTIONAL MATCH (chByUid:Chapter {uid: row.chapterUid}) '
                . 'OPTIONAL MATCH (chByChId:Chapter {chId: row.chapterId}) '
                . 'WITH c, row, chByUid, chByChId, '
                . '     CASE WHEN chByUid IS NOT NULL THEN chByUid ELSE chByChId END AS ch '
                . 'FOREACH (_ IN CASE WHEN ch IS NULL THEN [] ELSE [1] END | '
                . '    MERGE (ch)-[:HAS_CONCEPT]->(c) ) '
                . 'FOREACH (_ IN CASE WHEN chByUid IS NULL AND chByChId IS NOT NULL THEN [1] ELSE [] END | '
                . '    SET chByChId.uid = row.chapterUid ) '
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
     * Project `concept_prerequisite` (expert-authored, reviewed) AND
     * `pal_concept_relations` (AI-drafted, unreviewed) as typed edges.
     *
     * CORRECTION, 2026-09-29: until this date this method read only
     * `pal_concept_relations`. `CurriculumGraphBuilder.php:301-314` (the
     * Teach/Learn coherence-map authoring screen's own code) documents that
     * table as the *unreviewed* one - "every row on this estate is `draft` +
     * `tagged_by=ai`; none have been reviewed" - while `concept_prerequisite`
     * is "the authored map, written by the curriculum team, reviewed before
     * it lands." Verified live the same day: `concept_prerequisite` has 7,091
     * rows, 100% `status='approved'`, 100% `origin='expert'`, every row
     * carrying a human-written, non-empty `reason` (NOT NULL at the DB
     * level, enforced at import time by `PrereqImportCommand` alongside a
     * check that both ids are real `lms_concept` rows). Only ~1.5% of pairs
     * (110/7,091) overlap with `pal_concept_relations` - this is mostly
     * *additional* coverage, not a duplicate source. The graph's prerequisite
     * spine was therefore missing the reviewed map entirely for as long as
     * this method has existed.
     *
     * Both sources are queried and merged here (not read by two separate
     * methods) because they feed the exact same edge types
     * (REQUIRES/CROSS_LINKS) between the exact same :Concept nodes, and this
     * method is the sole owner of both - unlike ASSESSES below, there is no
     * external pipeline to guard against, so one unioned qualifying set per
     * edge type is correct for retraction.
     *
     * `concept_prerequisite` is queried first and treated as authoritative;
     * `pal_concept_relations` is then queried for GAP-FILLING ONLY, excluding
     * any (prerequisite, concept) pair `concept_prerequisite` already covers.
     * Tested live against a real overlap (tenant 1, standard 42, subject
     * 4469): the two sources don't only disagree on quality for a shared
     * pair, they sometimes disagree on the relationship's *nature* -
     * `concept_prerequisite` classing a pair `requires` (same-subject
     * progression) while the unreviewed `pal_concept_relations` draft classes
     * the identical pair `cross_curricular`. Those route to different edge
     * types/directions, so a plain "later SET wins" merge would not override
     * anything - it would silently write BOTH a REQUIRES and a CROSS_LINKS
     * edge between the same two concepts from two sources contradicting each
     * other. Whole-pair exclusion is what actually gives the reviewed source
     * priority in that case, not just on shared props.
     *
     * Both endpoints must already exist as :Concept - an edge is never allowed
     * to CREATE one. A prerequisite pointing at a concept outside the map is a
     * content defect, and minting a bare node for it would hide exactly what a
     * reviewer needs to see. Unmatched edges are counted and returned instead.
     *
     * DIRECTION. `pal_concept_relations` reads "from REQUIRES to" as: to learn
     * `to_concept_id`, you first need `from_concept_id`. `concept_prerequisite`
     * uses the identical convention (`concept_id` = the later/dependent
     * concept, `prerequisite_id` = the earlier one - see its migration
     * comment). The graph edge is drawn the way the recommender walks it -
     * from the concept being attempted OUT to its prerequisites:
     *
     *     (to)-[:REQUIRES]->(from)
     *
     * `link_type='cross_subject'` (concept_prerequisite) and
     * `relation_type='cross_curricular'` (pal_concept_relations) both route
     * to CROSS_LINKS instead, direction reversed to (from)-[:CROSS_LINKS]->(to)
     * exactly as the existing cross_curricular pass already draws it.
     *
     * @return array{requires: int, cross_links: int, unresolved: int, retracted: int}
     */
    public function projectRelations(int $tenant, int $standardId, int $subjectId): array
    {
        $expertRows = DB::table('concept_prerequisite as cp')
            ->join('lms_concept as t', 't.id', '=', 'cp.concept_id')
            ->join('lms_concept as f', 'f.id', '=', 'cp.prerequisite_id')
            ->whereIn('cp.sub_institute_id', [$tenant, 0])
            ->where('t.standard_id', $standardId)
            ->where('t.subject_id', $subjectId)
            ->where('t.sub_institute_id', $tenant)
            // Only draft|approved exist in this table (no `rejected` state);
            // approved-only is the deliberate point of syncing this source at
            // all - it is what makes this the reviewed map, not a mirror of
            // pal_concept_relations' looser "not yet rejected" bar below.
            ->where('cp.status', 'approved')
            ->select([
                'cp.concept_id', 'cp.prerequisite_id', 'cp.link_type',
                'cp.is_gate', 'cp.reason', 'cp.source_ref', 'cp.origin', 'cp.status',
            ])
            ->get()
            ->map(fn ($r) => [
                'fromId' => (int) $r->prerequisite_id,
                'toId'   => (int) $r->concept_id,
                'bucket' => $r->link_type === 'cross_subject' ? 'cross_curricular' : 'requires',
                'props'  => array_filter([
                    'link_type'      => $this->str($r->link_type) ?: null,
                    'is_gate'        => (bool) $r->is_gate,
                    'reason'         => $this->str($r->reason) ?: null,
                    'source_ref'     => $this->str($r->source_ref) ?: null,
                    'quality_status' => $this->str($r->status) ?: 'approved',
                    'tagged_by'      => $this->str($r->origin) ?: 'expert',
                    'source'         => 'concept_prerequisite',
                ], fn ($v) => $v !== null),
            ]);

        // "prerequisite:concept" pairs concept_prerequisite already covers -
        // pal_concept_relations is filtered against this set below so an
        // unreviewed AI classification never gets written alongside (and
        // possibly contradicting) an already-reviewed one for the same pair.
        $expertPairs = $expertRows->map(fn ($r) => $r['fromId'] . ':' . $r['toId'])->flip();

        $aiRows = DB::table('pal_concept_relations as r')
            ->join('lms_concept as f', 'f.id', '=', 'r.from_concept_id')
            ->join('lms_concept as t', 't.id', '=', 'r.to_concept_id')
            ->whereIn('r.sub_institute_id', [$tenant, 0])
            // Scoped on the TARGET concept only: a Class 7 concept may
            // legitimately require a Class 6 one, and filtering both ends to
            // the same standard would delete the very chain the map exists for.
            ->where('t.standard_id', $standardId)
            ->where('t.subject_id', $subjectId)
            ->where('t.sub_institute_id', $tenant)
            // Interim quality bar: exclude only `rejected`. Filtering to
            // `approved`-only would currently drop this entire edge type to
            // zero (review has barely started across the estate, measured
            // 2026-09-28) — `draft` still counts as "not yet disqualified"
            // until per-type approved volume justifies tightening this.
            // retract() below is the other half: a row that becomes
            // `rejected`, or is deleted outright, must also stop being a
            // live edge, not just stop being re-written.
            ->where('r.quality_status', '!=', 'rejected')
            ->select([
                'r.from_concept_id', 'r.to_concept_id', 'r.relation_type',
                'r.link_type', 'r.transfer_direction', 'r.mastery_gate',
                'r.auto_suggest', 'r.suggestion_trigger_mastery',
                'r.quality_status', 'r.tagged_by',
            ])
            ->get()
            ->reject(fn ($r) => $expertPairs->has($r->from_concept_id . ':' . $r->to_concept_id))
            ->map(fn ($r) => [
                'fromId' => (int) $r->from_concept_id,
                'toId'   => (int) $r->to_concept_id,
                'bucket' => $r->relation_type === 'cross_curricular' ? 'cross_curricular' : 'requires',
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
                    'source'             => 'pal_concept_relations',
                ], fn ($v) => $v !== null),
            ]);

        $rows = $expertRows->concat($aiRows);

        $counts = ['requires' => 0, 'cross_links' => 0, 'unresolved' => 0, 'retracted' => 0];

        // The relationship type is part of the query TEXT and must never be
        // interpolated from a database column, so the two types run as two
        // separate passes over two hardcoded statements. `live`/`delete` are
        // the retraction pair: `live` reads every edge of that type already
        // in this scope, `delete` removes whichever ones the current
        // qualifying set (built below, per pass, UNIONED ACROSS BOTH SOURCE
        // TABLES ABOVE) no longer accounts for. Both are scoped on the
        // `target` node (conceptId = to_concept_id) exactly like the SQL
        // queries above, so retraction can never reach outside this call's
        // own scope.
        $passes = [
            'requires' => [
                'bucket' => 'requires',
                'cypher' => 'UNWIND $rows AS row '
                    . 'MATCH (target:Concept {conceptId: row.toId}) '
                    . 'MATCH (source:Concept {conceptId: row.fromId}) '
                    . 'MERGE (target)-[e:REQUIRES]->(source) '
                    . 'SET e += row.props '
                    . 'RETURN count(e) AS c',
                'live' => 'MATCH (target:Concept {sub_institute_id: $tenant, standard_id: $standardId, subject_id: $subjectId})-[e:REQUIRES]->(source:Concept) '
                    . 'RETURN target.conceptId AS toId, source.conceptId AS fromId',
                'delete' => 'UNWIND $rows AS row '
                    . 'MATCH (target:Concept {conceptId: row.toId})-[e:REQUIRES]->(source:Concept {conceptId: row.fromId}) '
                    . 'DELETE e',
            ],
            'cross_curricular' => [
                'bucket' => 'cross_links',
                'cypher' => 'UNWIND $rows AS row '
                    . 'MATCH (target:Concept {conceptId: row.toId}) '
                    . 'MATCH (source:Concept {conceptId: row.fromId}) '
                    . 'MERGE (source)-[e:CROSS_LINKS]->(target) '
                    . 'SET e += row.props '
                    . 'RETURN count(e) AS c',
                'live' => 'MATCH (source:Concept)-[e:CROSS_LINKS]->(target:Concept {sub_institute_id: $tenant, standard_id: $standardId, subject_id: $subjectId}) '
                    . 'RETURN source.conceptId AS fromId, target.conceptId AS toId',
                'delete' => 'UNWIND $rows AS row '
                    . 'MATCH (source:Concept {conceptId: row.fromId})-[e:CROSS_LINKS]->(target:Concept {conceptId: row.toId}) '
                    . 'DELETE e',
            ],
        ];

        foreach ($passes as $sourceType => $pass) {
            // Both sources were normalised into {fromId, toId, bucket, props}
            // above, so this loop no longer cares which table a row came
            // from - it only needs to know which edge type ($sourceType) the
            // row's bucket already resolved to.
            $subset = $rows->where('bucket', $sourceType);
            $qualifying = [];

            foreach ($subset->chunk(self::BATCH) as $chunk) {
                $payload = [];

                foreach ($chunk as $r) {
                    $qualifying[$r['fromId'] . ':' . $r['toId']] = true;
                    $payload[] = $r;
                }

                $first = $this->neo4j->run($pass['cypher'], ['rows' => $payload])->first();
                $made = $first ? (int) $first->get('c') : 0;

                $counts[$pass['bucket']] += $made;
                $counts['unresolved'] += count($payload) - $made;
            }

            $counts['retracted'] += $this->retract(
                $pass['live'],
                ['tenant' => $tenant, 'standardId' => (string) $standardId, 'subjectId' => (string) $subjectId],
                $pass['delete'],
                $qualifying
            );
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
            // Interim quality bar - see projectRelations() for the reasoning.
            ->where('m.quality_status', '!=', 'rejected')
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

        $qualifying = [];
        foreach ($rows as $r) {
            $qualifying[(string) (int) $r->content_master_id . ':' . (int) $r->concept_ref_id] = true;
        }

        $result = $this->linkDelivery(
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

        // TEACHES is exclusively this pipeline's edge type (no other pipeline
        // writes it), so no ownership guard is needed here the way ASSESSES
        // needs one below.
        $result['retracted'] = $this->retract(
            'MATCH (n:Content)-[e:TEACHES]->(c:Concept {sub_institute_id: $tenant, standard_id: $standardId, subject_id: $subjectId}) '
                . 'RETURN n.id AS fromId, c.conceptId AS toId',
            ['tenant' => $tenant, 'standardId' => (string) $standardId, 'subjectId' => (string) $subjectId],
            'UNWIND $rows AS row '
                . 'MATCH (n:Content {id: row.fromId})-[e:TEACHES]->(c:Concept {conceptId: row.toId}) '
                . 'DELETE e',
            $qualifying
        );

        return $result;
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
            // Interim quality bar - see projectRelations() for the reasoning.
            ->where('m.quality_status', '!=', 'rejected')
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

        $qualifying = [];
        foreach ($rows as $r) {
            $qualifying[(int) $r->question_id . ':' . (int) $r->concept_ref_id] = true;
        }

        $result = $this->linkDelivery(
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

        // CRITICAL OWNERSHIP GUARD. Unlike TEACHES/REQUIRES/CROSS_LINKS, this
        // edge type is ALSO written by the unrelated declarative outbox
        // (config/neo4j.php -> lms_question_master.concept_id), which owns
        // ~99.9% of the live ASSESSES edges and never sets `quality_status`.
        // `WHERE e.quality_status IS NOT NULL` on both the live-read and the
        // delete scopes retraction to edges THIS pipeline actually wrote.
        // Dropping this guard would retract every edge the other pipeline
        // owns the moment this method runs, on the mistaken belief they were
        // this pipeline's own stale writes - measured live 2026-09-28: 32,302
        // of 32,328 ASSESSES edges belong to that other pipeline.
        $result['retracted'] = $this->retract(
            'MATCH (n:Question)-[e:ASSESSES]->(c:Concept {sub_institute_id: $tenant, standard_id: $standardId, subject_id: $subjectId}) '
                . 'WHERE e.quality_status IS NOT NULL '
                . 'RETURN n.qId AS fromId, c.conceptId AS toId',
            ['tenant' => $tenant, 'standardId' => (string) $standardId, 'subjectId' => (string) $subjectId],
            'UNWIND $rows AS row '
                . 'MATCH (n:Question {qId: row.fromId})-[e:ASSESSES]->(c:Concept {conceptId: row.toId}) '
                . 'WHERE e.quality_status IS NOT NULL '
                . 'DELETE e',
            $qualifying
        );

        return $result;
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
    // 5. pal_learning_relations - the non-concept-grain prerequisite edges
    // ==================================================================

    /**
     * Project `pal_learning_relations` - added 2026-09-16, previously
     * unwired entirely; chapter-grain added 2026-09-28; topic-grain added
     * 2026-09-29 once `:Topic` was confirmed to actually exist (a correction
     * to this doc's own earlier claim that the label didn't exist at all -
     * see the design review's round-2 audit).
     *
     * TOPIC-GRAIN IS WIRED BUT WILL REPORT 0 RESOLVED TODAY, AND THAT IS
     * CORRECT, NOT A BUG. Verified 2026-09-29: all 52 distinct topic ids
     * `pal_learning_relations` references are real, current `topic_master`
     * rows (100% match) - but `:Topic` itself is a one-time historical bulk
     * load (13,561 nodes) that was never kept current by any live sync, and
     * none of those 52 ids are among the ones it happened to capture. This
     * is a `:Topic` sync-freshness gap, not a data-quality gap the way the
     * old `:Chapter` population was - the fix is a live `topic_master` sync
     * (out of scope here, a separate piece of work), not a reason to skip
     * wiring this edge. The method is correct and ready; it just has nothing
     * to resolve against until that sync exists.
     *
     * Unit-grain: still not attempted. No live rows exist to test against
     * (checked live 2026-09-28), and `:Unit`'s own key is split the same
     * dual-key way `:Chapter` is (134 `unitId`-keyed, 60 legacy `id`/`uid`) -
     * writing untested Cypher against zero real rows would be guessing, not
     * verifying, so left for whoever adds it once a real row exists.
     *
     * KEY CONVENTIONS, NOT INTERCHANGEABLE. Chapter matches on `uid`
     * ('Chapter:{tenant}:0:{id}'), never on `chId` - the class docblock's
     * non-negotiable rule. Topic matches on a plain `id` property (STRING,
     * confirmed live - no dual-key split exists for this label, unlike
     * Chapter). Getting these swapped would silently match nothing on one
     * grain while looking like it works on the other - verified both
     * conventions against live Neo4j before writing this, not assumed.
     * `topic_master` has no `standard_id`/`subject_id` of its own (unlike
     * `chapter_master`); both queries below join through `chapter_master`
     * for scoping where the grain needs it.
     *
     * @return array{requires: int, unresolved: int, retracted: int}
     */
    public function projectLearningRelations(int $tenant, int $standardId, int $subjectId): array
    {
        $chapterRows = DB::table('pal_learning_relations as r')
            ->join('chapter_master as f', 'f.id', '=', 'r.from_node_id')
            ->join('chapter_master as t', 't.id', '=', 'r.to_node_id')
            ->where('r.from_node_type', 'chapter')
            ->where('r.to_node_type', 'chapter')
            ->where('r.relation_type', 'requires')
            ->whereIn('r.sub_institute_id', [$tenant, 0])
            ->where('t.standard_id', $standardId)
            ->where('t.subject_id', $subjectId)
            ->where('r.quality_status', '!=', 'rejected')
            ->select(['r.from_node_id', 'r.to_node_id', 'r.quality_status', 'r.tagged_by', 'r.confidence'])
            ->get();

        $chapterCypher = 'UNWIND $rows AS row '
            . 'MATCH (target:Chapter {uid: row.toKey}) '
            . 'MATCH (source:Chapter {uid: row.fromKey}) '
            . 'MERGE (target)-[e:REQUIRES]->(source) '
            . 'SET e += row.props '
            . 'RETURN count(e) AS c';

        $chapterQualifying = [];
        foreach ($chapterRows as $r) {
            $fromKey = 'Chapter:' . $tenant . ':0:' . (int) $r->from_node_id;
            $toKey = 'Chapter:' . $tenant . ':0:' . (int) $r->to_node_id;
            $chapterQualifying[$fromKey . ':' . $toKey] = true;
        }

        $chapterResult = $this->linkDelivery(
            $chapterRows,
            fn ($r) => [
                'fromKey' => 'Chapter:' . $tenant . ':0:' . (int) $r->from_node_id,
                'toKey'   => 'Chapter:' . $tenant . ':0:' . (int) $r->to_node_id,
                'props'   => array_filter([
                    'quality_status' => $this->str($r->quality_status) ?: 'draft',
                    'tagged_by'      => $this->str($r->tagged_by) ?: 'structural',
                    'confidence'     => $r->confidence === null ? null : (float) $r->confidence,
                    'grain'          => 'chapter',
                ], fn ($v) => $v !== null),
            ],
            $chapterCypher,
            'requires',
            'unresolved'
        );

        $chapterScopeKeys = DB::table('chapter_master')
            ->where('standard_id', $standardId)
            ->where('subject_id', $subjectId)
            ->pluck('id')
            ->map(fn ($id) => 'Chapter:' . $tenant . ':0:' . (int) $id)
            ->values()
            ->all();

        $chapterResult['retracted'] = $chapterScopeKeys === [] ? 0 : $this->retract(
            'MATCH (target:Chapter)-[e:REQUIRES]->(source:Chapter) '
                . 'WHERE target.uid IN $scopeKeys '
                . 'RETURN target.uid AS toId, source.uid AS fromId',
            ['scopeKeys' => $chapterScopeKeys],
            'UNWIND $rows AS row '
                . 'MATCH (target:Chapter {uid: row.toId})-[e:REQUIRES]->(source:Chapter {uid: row.fromId}) '
                . 'DELETE e',
            $chapterQualifying
        );

        // ---- topic grain -----------------------------------------------
        $topicRows = DB::table('pal_learning_relations as r')
            ->join('topic_master as f', 'f.id', '=', 'r.from_node_id')
            ->join('topic_master as t', 't.id', '=', 'r.to_node_id')
            ->join('chapter_master as tc', 'tc.id', '=', 't.chapter_id')
            ->where('r.from_node_type', 'topic')
            ->where('r.to_node_type', 'topic')
            ->where('r.relation_type', 'requires')
            ->whereIn('r.sub_institute_id', [$tenant, 0])
            ->where('tc.standard_id', $standardId)
            ->where('tc.subject_id', $subjectId)
            ->where('r.quality_status', '!=', 'rejected')
            ->select(['r.from_node_id', 'r.to_node_id', 'r.quality_status', 'r.tagged_by', 'r.confidence'])
            ->get();

        $topicCypher = 'UNWIND $rows AS row '
            . 'MATCH (target:Topic {id: row.toKey}) '
            . 'MATCH (source:Topic {id: row.fromKey}) '
            . 'MERGE (target)-[e:REQUIRES]->(source) '
            . 'SET e += row.props '
            . 'RETURN count(e) AS c';

        $topicQualifying = [];
        foreach ($topicRows as $r) {
            $fromKey = (string) (int) $r->from_node_id;
            $toKey = (string) (int) $r->to_node_id;
            $topicQualifying[$fromKey . ':' . $toKey] = true;
        }

        $topicResult = $this->linkDelivery(
            $topicRows,
            fn ($r) => [
                'fromKey' => (string) (int) $r->from_node_id,
                'toKey'   => (string) (int) $r->to_node_id,
                'props'   => array_filter([
                    'quality_status' => $this->str($r->quality_status) ?: 'draft',
                    'tagged_by'      => $this->str($r->tagged_by) ?: 'structural',
                    'confidence'     => $r->confidence === null ? null : (float) $r->confidence,
                    'grain'          => 'topic',
                ], fn ($v) => $v !== null),
            ],
            $topicCypher,
            'requires',
            'unresolved'
        );

        $topicScopeKeys = DB::table('topic_master as tp')
            ->join('chapter_master as c', 'c.id', '=', 'tp.chapter_id')
            ->where('c.standard_id', $standardId)
            ->where('c.subject_id', $subjectId)
            ->pluck('tp.id')
            ->map(fn ($id) => (string) (int) $id)
            ->values()
            ->all();

        $topicResult['retracted'] = $topicScopeKeys === [] ? 0 : $this->retract(
            'MATCH (target:Topic)-[e:REQUIRES]->(source:Topic) '
                . 'WHERE target.id IN $scopeKeys '
                . 'RETURN target.id AS toId, source.id AS fromId',
            ['scopeKeys' => $topicScopeKeys],
            'UNWIND $rows AS row '
                . 'MATCH (target:Topic {id: row.toId})-[e:REQUIRES]->(source:Topic {id: row.fromId}) '
                . 'DELETE e',
            $topicQualifying
        );

        return [
            'requires'   => $chapterResult['requires'] + $topicResult['requires'],
            'unresolved' => $chapterResult['unresolved'] + $topicResult['unresolved'],
            'retracted'  => $chapterResult['retracted'] + $topicResult['retracted'],
        ];
    }

    // ==================================================================
    // 6. Misconception layer - pal_misconception_library / _corrective
    // ==================================================================

    /**
     * Project the misconception RELATIONSHIP layer, previously entirely
     * missing:
     *
     *   (:Misconception)-[:AFFECTS]->(:Concept)             from pal_misconception_library
     *   (:Misconception)-[:CORRECTS_WITH]->(:Content)       from pal_misconception_corrective,
     *                                                        content_master_id set (0/7,307 today)
     *   (:Misconception)-[:CORRECTS_WITH]->(:CorrectiveContent)  same table, content_master_id
     *                                                        NULL (7,307/7,307 today) - see below
     *
     * `:Misconception` ALREADY EXISTS LIVE as 3,664 nodes, keyed on
     * `misconceptionId` - confirmed 2026-09-28 to be a 1:1, exact snapshot of
     * `pal_misconception_library.id` (spot-checked ids 1-5: tag and
     * description match verbatim), almost certainly loaded by one of the
     * historical bulk `.cypher` modules. Those nodes carry rich properties
     * already but had ZERO `AFFECTS`/`CORRECTS_WITH`/`HAS_CONCEPT` edges
     * (confirmed live) - the load populated nodes only, never the
     * relationship layer this method adds.
     *
     * THIS KEY CHOICE MATTERS. An earlier version of this method keyed on
     * `tag` instead, reasoning from the migration's own "tag is stable
     * forever" comment without first checking what the graph already had.
     * That would have MINTED A SECOND, PARALLEL :Misconception POPULATION
     * under the same label - exactly the dual-key defect this whole design
     * review exists to stop elsewhere in the graph (Chapter, Unit, ...).
     * Caught before it ran against the live database. `misconceptionId` is
     * therefore the ONLY correct key here, even though `tag` is arguably the
     * better business key in isolation - matching what already exists beats
     * a theoretically cleaner key that would fork the population.
     *
     * Only misconceptions with a resolvable `concept_ref_id` are in scope
     * here, the same "an edge is never allowed to create its own endpoint"
     * rule projectRelations() applies to a missing :Concept. A misconception
     * not yet linked to any concept is counted under `unlinked`, not
     * silently skipped.
     *
     * A corrective row with `content_master_id` set links to the existing
     * `:Content` node it names. A corrective authored inline (no
     * content_master_id - the migration explicitly allows this: "the
     * corrective may be existing LMS content OR authored inline here") MERGEs
     * its own `:CorrectiveContent` node instead (added 2026-09-29 - see the
     * second half of this method) - found via `neo4j:completeness` reporting
     * 0 live CORRECTS_WITH edges despite this method being wired: verified
     * live that ALL 7,307 corrective rows have `content_master_id` NULL, not
     * some of them, so the :Content-linking pass alone left the entire
     * corrective-content layer invisible in the graph. `correctives_missing_
     * content` now means what it always should have: a row THAT DOES have
     * `content_master_id` set but pointing at a `:Content` node that doesn't
     * exist. A row with content_master_id NULL that also fails to resolve its
     * own :Misconception is counted under `correctives_unresolved_
     * misconception` instead - equally rare, kept separate for the same
     * reason `unresolved` and `unlinked` are separate above.
     *
     * @return array{misconceptions: int, unresolved_affects: int, unlinked: int, corrects_with: int, correctives_missing_content: int, corrects_with_inline: int, correctives_unresolved_misconception: int, retracted: int}
     */
    public function projectMisconceptions(int $tenant, int $standardId, int $subjectId): array
    {
        $rows = DB::table('pal_misconception_library as m')
            ->join('lms_concept as k', 'k.id', '=', 'm.concept_ref_id')
            ->whereIn('m.sub_institute_id', [$tenant, 0])
            ->where('k.standard_id', $standardId)
            ->where('k.subject_id', $subjectId)
            ->where('m.quality_status', '!=', 'rejected')
            ->select([
                'm.id', 'm.tag', 'm.concept_ref_id', 'm.description', 'm.error_pattern',
                'm.corrective_action', 'm.prevalence_rate', 'm.teacher_confirmed',
                'm.priority_level', 'm.quality_status', 'm.tagged_by', 'm.detection_count',
            ])
            ->get();

        $unlinked = DB::table('pal_misconception_library')
            ->whereIn('sub_institute_id', [$tenant, 0])
            ->whereNull('concept_ref_id')
            ->where('quality_status', '!=', 'rejected')
            ->count();

        // MATCH, not MERGE, on :Misconception - this method never creates the
        // node (the bulk load already did), only enriches it and attaches the
        // edge. A library row with no matching misconceptionId node is a real
        // gap (the bulk snapshot predates it), counted under
        // `unresolved_affects` exactly like a missing :Concept would be,
        // rather than silently minted here as a second population.
        $affectsCypher = 'UNWIND $rows AS row '
            . 'MATCH (c:Concept {conceptId: row.conceptId}) '
            . 'MATCH (mc:Misconception {misconceptionId: row.misconceptionId}) '
            . 'SET mc += row.props, mc.coherence_synced_at = datetime() '
            . 'MERGE (mc)-[e:AFFECTS]->(c) '
            . 'RETURN count(e) AS c';

        $qualifying = [];
        foreach ($rows as $r) {
            $qualifying[(int) $r->id . ':' . (int) $r->concept_ref_id] = true;
        }

        $result = $this->linkDelivery(
            $rows,
            fn ($r) => [
                'misconceptionId' => (int) $r->id,
                'conceptId'       => (int) $r->concept_ref_id,
                'props'           => array_filter([
                    'tag'                => $this->str($r->tag) ?: null,
                    'description'        => $this->str($r->description) ?: null,
                    'error_pattern'      => $this->str($r->error_pattern) ?: null,
                    'corrective_note'    => $this->str($r->corrective_action) ?: null,
                    'prevalence_rate'    => $r->prevalence_rate === null ? null : (float) $r->prevalence_rate,
                    'teacher_confirmed'  => (bool) $r->teacher_confirmed,
                    'priority_level'     => $this->intOrNull($r->priority_level),
                    'quality_status'     => $this->str($r->quality_status) ?: 'draft',
                    'tagged_by'          => $this->str($r->tagged_by) ?: 'human',
                    'detection_count'    => $this->intOrNull($r->detection_count),
                ], fn ($v) => $v !== null),
            ],
            $affectsCypher,
            'misconceptions',
            'unresolved_affects'
        );

        $result['retracted'] = $this->retract(
            'MATCH (mc:Misconception)-[e:AFFECTS]->(c:Concept {sub_institute_id: $tenant, standard_id: $standardId, subject_id: $subjectId}) '
                . 'RETURN mc.misconceptionId AS fromId, c.conceptId AS toId',
            ['tenant' => $tenant, 'standardId' => (string) $standardId, 'subjectId' => (string) $subjectId],
            'UNWIND $rows AS row '
                . 'MATCH (mc:Misconception {misconceptionId: row.fromId})-[e:AFFECTS]->(c:Concept {conceptId: row.toId}) '
                . 'DELETE e',
            $qualifying
        );

        $correctives = DB::table('pal_misconception_corrective as co')
            ->join('pal_misconception_library as m', 'm.id', '=', 'co.misconception_id')
            ->join('lms_concept as k', 'k.id', '=', 'm.concept_ref_id')
            ->whereIn('co.sub_institute_id', [$tenant, 0])
            ->where('k.standard_id', $standardId)
            ->where('k.subject_id', $subjectId)
            ->where('co.quality_status', '!=', 'rejected')
            ->whereNotNull('co.content_master_id')
            ->select(['m.id as misconception_id', 'co.content_master_id', 'co.title', 'co.format', 'co.priority_level', 'co.quality_status'])
            ->get();

        $correctiveCypher = 'UNWIND $rows AS row '
            . 'MATCH (mc:Misconception {misconceptionId: row.misconceptionId}) '
            . 'MATCH (n:Content {id: row.nodeKey}) '
            . 'MERGE (mc)-[e:CORRECTS_WITH]->(n) '
            . 'SET e += row.props '
            . 'RETURN count(e) AS c';

        $qualifyingCorrectives = [];
        foreach ($correctives as $r) {
            $qualifyingCorrectives[(int) $r->misconception_id . ':' . (string) (int) $r->content_master_id] = true;
        }

        $correctiveResult = $this->linkDelivery(
            $correctives,
            fn ($r) => [
                'misconceptionId' => (int) $r->misconception_id,
                'nodeKey'         => (string) (int) $r->content_master_id,   // STRING key, matches :Content.id everywhere else in this class
                'props'           => array_filter([
                    'title'          => $this->str($r->title) ?: null,
                    'format'         => $this->str($r->format) ?: null,
                    'priority_level' => $this->intOrNull($r->priority_level),
                    'quality_status' => $this->str($r->quality_status) ?: 'draft',
                ], fn ($v) => $v !== null),
            ],
            $correctiveCypher,
            'corrects_with',
            'correctives_missing_content'
        );

        // Scoped to the misconceptions this call already resolved above
        // (their misconceptionIds), not to every :Misconception in the graph
        // - a corrective belonging to a misconception outside this
        // standard/subject scope must never be touched by this call.
        $scopeIds = array_values(array_unique($rows->pluck('id')->map(fn ($id) => (int) $id)->all()));

        $correctiveResult['retracted'] = $scopeIds === [] ? 0 : $this->retract(
            'MATCH (mc:Misconception)-[e:CORRECTS_WITH]->(n:Content) '
                . 'WHERE mc.misconceptionId IN $ids '
                . 'RETURN mc.misconceptionId AS fromId, n.id AS toId',
            ['ids' => $scopeIds],
            'UNWIND $rows AS row '
                . 'MATCH (mc:Misconception {misconceptionId: row.fromId})-[e:CORRECTS_WITH]->(n:Content {id: row.toId}) '
                . 'DELETE e',
            $qualifyingCorrectives
        );

        // ---- inline-authored correctives -> :CorrectiveContent ------------
        //
        // Added 2026-09-29. Found via `neo4j:completeness`: CORRECTS_WITH was
        // reporting 0 live edges globally despite the pass above being
        // correct code - because 100% of pal_misconception_corrective's rows
        // (7,307/7,307, verified live) have `content_master_id` NULL. The
        // migration's own comment allows this on purpose ("the corrective
        // may be existing LMS content OR authored inline here"); in practice
        // authored-inline is not the edge case, it is the entire dataset -
        // real, reviewed, ready-to-show explanations (title/body/media_url/
        // h5p_type), most with a human `reviewed_by`, written specifically to
        // fix one misconception. There was no :Content node for these to
        // attach to, so the pass above always skipped them (counted under
        // `correctives_missing_content`, never surfaced anywhere a human
        // would see the number). `:CorrectiveContent` is a brand-new label
        // (confirmed empty before this - no historical population to fork,
        // unlike the :Misconception near-miss), keyed on the row's own `id`
        // as `correctiveId` - nothing else in this graph uses that id space.
        $inlineCorrectives = DB::table('pal_misconception_corrective as co')
            ->join('pal_misconception_library as m', 'm.id', '=', 'co.misconception_id')
            ->join('lms_concept as k', 'k.id', '=', 'm.concept_ref_id')
            ->whereIn('co.sub_institute_id', [$tenant, 0])
            ->where('k.standard_id', $standardId)
            ->where('k.subject_id', $subjectId)
            ->where('co.quality_status', '!=', 'rejected')
            ->whereNull('co.content_master_id')
            ->select([
                'co.id', 'm.id as misconception_id', 'co.sub_institute_id', 'co.scope',
                'co.title', 'co.body', 'co.media_url', 'co.format', 'co.h5p_type', 'co.language',
                'co.estimated_duration_minutes', 'co.priority_level', 'co.quality_status',
                'co.tagged_by', 'co.reviewed_by', 'co.served_count', 'co.resolution_rate',
            ])
            ->get();

        $inlineCypher = 'UNWIND $rows AS row '
            . 'MATCH (mc:Misconception {misconceptionId: row.misconceptionId}) '
            . 'MERGE (n:CorrectiveContent {correctiveId: row.correctiveId}) '
            . 'SET n += row.props '
            . 'MERGE (mc)-[e:CORRECTS_WITH]->(n) '
            . 'RETURN count(e) AS c';

        $qualifyingInline = [];
        foreach ($inlineCorrectives as $r) {
            $qualifyingInline[(int) $r->misconception_id . ':' . (int) $r->id] = true;
        }

        $inlineResult = $this->linkDelivery(
            $inlineCorrectives,
            fn ($r) => [
                'misconceptionId' => (int) $r->misconception_id,
                'correctiveId'    => (int) $r->id,
                'props'           => array_filter([
                    'sub_institute_id'          => (int) $r->sub_institute_id,
                    'scope'                     => $this->str($r->scope) ?: null,
                    'title'                     => $this->str($r->title) ?: null,
                    'body'                      => $this->str($r->body) ?: null,
                    'media_url'                 => $this->str($r->media_url) ?: null,
                    'format'                    => $this->str($r->format) ?: null,
                    'h5p_type'                  => $this->str($r->h5p_type) ?: null,
                    'language'                  => $this->str($r->language) ?: null,
                    'estimated_duration_minutes' => $this->intOrNull($r->estimated_duration_minutes),
                    'priority_level'            => $this->intOrNull($r->priority_level),
                    'quality_status'            => $this->str($r->quality_status) ?: 'draft',
                    'tagged_by'                 => $this->str($r->tagged_by) ?: 'human',
                    'reviewed_by'               => $this->intOrNull($r->reviewed_by),
                    'served_count'              => $this->intOrNull($r->served_count),
                    'resolution_rate'           => $r->resolution_rate === null ? null : (float) $r->resolution_rate,
                ], fn ($v) => $v !== null),
            ],
            $inlineCypher,
            'corrects_with_inline',
            'correctives_unresolved_misconception'
        );

        $inlineResult['retracted'] = $scopeIds === [] ? 0 : $this->retract(
            'MATCH (mc:Misconception)-[e:CORRECTS_WITH]->(n:CorrectiveContent) '
                . 'WHERE mc.misconceptionId IN $ids '
                . 'RETURN mc.misconceptionId AS fromId, n.correctiveId AS toId',
            ['ids' => $scopeIds],
            'UNWIND $rows AS row '
                . 'MATCH (mc:Misconception {misconceptionId: row.fromId})-[e:CORRECTS_WITH]->(n:CorrectiveContent {correctiveId: row.toId}) '
                . 'DELETE e',
            $qualifyingInline
        );

        return [
            'misconceptions'                       => $result['misconceptions'],
            'unresolved_affects'                    => $result['unresolved_affects'],
            'unlinked'                               => $unlinked,
            'corrects_with'                          => $correctiveResult['corrects_with'],
            'correctives_missing_content'            => $correctiveResult['correctives_missing_content'],
            'corrects_with_inline'                   => $inlineResult['corrects_with_inline'],
            'correctives_unresolved_misconception'   => $inlineResult['correctives_unresolved_misconception'],
            'retracted'                              => $result['retracted'] + $correctiveResult['retracted'] + $inlineResult['retracted'],
        ];
    }

    // ==================================================================
    // 7. Node-level (K/A/S) mastery - pal_concept_nodes / learner_node_state
    // ==================================================================

    /**
     * Project the K/A/S sub-concept identity layer, previously entirely
     * unrepresented in Neo4j:
     *
     *   (:Concept)-[:HAS_NODE]->(:ConceptNode)   from pal_concept_nodes
     *
     * `:ConceptNode` is a new label - nothing in this graph wrote it before
     * 2026-09-29. Keyed on `nodeId` (native `pal_concept_nodes.id`), the same
     * "native id, no pre-existing convention to clash with" reasoning already
     * used for `:Misconception`'s key choice, since no other pipeline touches
     * this label at all.
     *
     * `standard_id`/`subject_id`/`sub_institute_id` are denormalised onto the
     * node from the joined `lms_concept` row (this table has no such columns
     * of its own) specifically so `projectNodeMastery()`'s retraction below
     * can scope its live-read the same way every other retract() call in this
     * class does - matching a Concept's own properties rather than requiring
     * a second join at retraction time.
     *
     * @return array{nodes: int, unresolved_has_node: int, retracted: int}
     */
    public function projectConceptNodes(int $tenant, int $standardId, int $subjectId): array
    {
        $rows = DB::table('pal_concept_nodes as n')
            ->join('lms_concept as k', 'k.id', '=', 'n.concept_id')
            ->where('n.sub_institute_id', $tenant)
            ->where('k.standard_id', $standardId)
            ->where('k.subject_id', $subjectId)
            ->select(['n.id', 'n.concept_id', 'n.node_type', 'n.label', 'n.description', 'n.mastery_threshold', 'n.sort_order'])
            ->get();

        $cypher = 'UNWIND $rows AS row '
            . 'MATCH (c:Concept {conceptId: row.conceptId}) '
            . 'MERGE (n:ConceptNode {nodeId: row.nodeId}) '
            . 'SET n += row.props, n.coherence_synced_at = datetime() '
            . 'MERGE (c)-[e:HAS_NODE]->(n) '
            . 'RETURN count(e) AS c';

        $qualifying = [];
        foreach ($rows as $r) {
            $qualifying[(int) $r->concept_id . ':' . (int) $r->id] = true;
        }

        $result = $this->linkDelivery(
            $rows,
            fn ($r) => [
                'nodeId'    => (int) $r->id,
                'conceptId' => (int) $r->concept_id,
                'props'     => array_filter([
                    'nodeType'          => $this->str($r->node_type) ?: null,
                    'label'             => $this->str($r->label) ?: null,
                    'description'       => $this->str($r->description) ?: null,
                    'mastery_threshold' => $r->mastery_threshold === null ? null : (float) $r->mastery_threshold,
                    'sort_order'        => $this->intOrNull($r->sort_order),
                    'sub_institute_id'  => $tenant,
                    'standard_id'       => (string) $standardId,
                    'subject_id'        => (string) $subjectId,
                ], fn ($v) => $v !== null),
            ],
            $cypher,
            'nodes',
            'unresolved_has_node'
        );

        $result['retracted'] = $this->retract(
            'MATCH (c:Concept)-[e:HAS_NODE]->(n:ConceptNode {sub_institute_id: $tenant, standard_id: $standardId, subject_id: $subjectId}) '
                . 'RETURN c.conceptId AS fromId, n.nodeId AS toId',
            ['tenant' => $tenant, 'standardId' => (string) $standardId, 'subjectId' => (string) $subjectId],
            'UNWIND $rows AS row '
                . 'MATCH (c:Concept {conceptId: row.fromId})-[e:HAS_NODE]->(n:ConceptNode {nodeId: row.toId}) '
                . 'DELETE e',
            $qualifying
        );

        return $result;
    }

    /**
     * Project node-level (K/A/S) mastery:
     *
     *   (:StuDetail)-[:MASTERS_NODE]->(:ConceptNode)   from learner_node_state
     *
     * Confirmed 2026-09-29 that `learner_node_state` is the live,
     * currently-updating companion to `pal_concept_mastery` (both tables'
     * `updated_at` landed in the same second when checked) - two real grains
     * of the same underlying idea, both live, only the coarser one
     * (`pal_concept_mastery` -> `HAS_MASTERY`, projectMastery() above) had a
     * graph home before this method.
     *
     * MATCH, not MERGE, on both endpoints - a state row for a student or node
     * not yet in the graph is a real gap (`unresolved`), not something to
     * paper over by minting either endpoint here. Call projectConceptNodes()
     * for this same scope first; this method depends on its `:ConceptNode`
     * nodes already existing.
     *
     * @return array{mastered_nodes: int, unresolved: int, retracted: int}
     */
    public function projectNodeMastery(int $tenant, int $standardId, int $subjectId): array
    {
        $rows = DB::table('learner_node_state as s')
            ->join('pal_concept_nodes as n', 'n.id', '=', 's.node_id')
            ->join('lms_concept as k', 'k.id', '=', 'n.concept_id')
            ->where('s.sub_institute_id', $tenant)
            ->where('k.standard_id', $standardId)
            ->where('k.subject_id', $subjectId)
            ->select(['s.student_id', 's.node_id', 's.mastery_estimate', 's.attempts', 's.consecutive_correct', 's.status', 's.retention_stage'])
            ->get();

        $cypher = 'UNWIND $rows AS row '
            . 'MATCH (sd:StuDetail {sdId: row.studentId}) '
            . 'MATCH (n:ConceptNode {nodeId: row.nodeId}) '
            . 'MERGE (sd)-[e:MASTERS_NODE]->(n) '
            . 'SET e += row.props '
            . 'RETURN count(e) AS c';

        $qualifying = [];
        foreach ($rows as $r) {
            $qualifying[(int) $r->student_id . ':' . (int) $r->node_id] = true;
        }

        $result = $this->linkDelivery(
            $rows,
            fn ($r) => [
                'studentId' => (int) $r->student_id,
                'nodeId'    => (int) $r->node_id,
                'props'     => array_filter([
                    'mastery_estimate'    => $r->mastery_estimate === null ? null : (float) $r->mastery_estimate,
                    'attempts'            => $this->intOrNull($r->attempts),
                    'consecutive_correct' => $this->intOrNull($r->consecutive_correct),
                    'status'              => $this->str($r->status) ?: null,
                    'retention_stage'     => $this->intOrNull($r->retention_stage),
                ], fn ($v) => $v !== null),
            ],
            $cypher,
            'mastered_nodes',
            'unresolved'
        );

        $result['retracted'] = $this->retract(
            'MATCH (sd:StuDetail)-[e:MASTERS_NODE]->(n:ConceptNode {sub_institute_id: $tenant, standard_id: $standardId, subject_id: $subjectId}) '
                . 'RETURN sd.sdId AS fromId, n.nodeId AS toId',
            ['tenant' => $tenant, 'standardId' => (string) $standardId, 'subjectId' => (string) $subjectId],
            'UNWIND $rows AS row '
                . 'MATCH (sd:StuDetail {sdId: row.fromId})-[e:MASTERS_NODE]->(n:ConceptNode {nodeId: row.toId}) '
                . 'DELETE e',
            $qualifying
        );

        return $result;
    }

    // ==================================================================
    // 8. Curriculum outcomes - lms_learning_outcomes / lms_concept_outcome
    // ==================================================================

    /**
     * Project the NCF/NCERT goal -> competency -> learning-outcome hierarchy,
     * and the concept-to-outcome bridge, both previously entirely absent:
     *
     *   (:CurriculumOutcome)-[:PART_OF]->(:CurriculumOutcome)  competency->goal,
     *                                                           learning_outcome->competency
     *   (:Chapter)-[:HAS_OUTCOME]->(:CurriculumOutcome)        chapter_id != 0 rows only
     *   (:Concept)-[:ADDRESSES]->(:CurriculumOutcome)          from lms_concept_outcome
     *
     * NOT called `:LearningOutcome` - that label already has 1 live node,
     * confirmed 2026-09-29 to be a completely different, older thing: keyed
     * on `uid` ('LearningOutcome:47:0:1'), carrying `lomaster_id`/`indicator`/
     * `grade_id` properties and a HAS_INDICATOR edge to :LOCategory - the
     * generic `lo_master` K12 platform framework, tenant 47, unrelated to the
     * NCERT-extraction pipeline this method reads. Reusing the label would
     * mix two unrelated datasets under one name the way `:Subject` already
     * does by historical accident; `:CurriculumOutcome` avoids repeating that
     * on a label that still has a clean choice available. Caught by checking
     * live before writing this Cypher, same discipline as the :Misconception
     * key choice above.
     *
     * `lms_learning_outcomes` has no `sub_institute_id` of its own - verified
     * live 2026-09-29 that all 6,763 rows resolve via `curriculum_id ->
     * lms_curriculum.sub_institute_id` to tenant 1, currently the only
     * tenant this table has data for. `standard_id`/`subject_id` ARE real
     * columns on the table itself, so scoping this method the same
     * (tenant, standard, subject) way as every other method here needs no
     * join for that part - only the `sub_institute_id` PROPERTY stamped onto
     * each node is taken from the parameter, same pattern `:ConceptNode`
     * above already established for a table with the same gap.
     *
     * The 3-tier chain is clean: verified live that every `competency` row's
     * parent is a `goal` row and every `learning_outcome` row's parent is a
     * `competency` row, zero cross-tier or dangling exceptions across all
     * 6,763 rows. `chapter_id = 0` is a real sentinel (458 rows, curriculum-
     * level goal/competency rows with no single chapter) and is never
     * treated as a real Chapter reference - verified live that 100% of the
     * 6,305 non-zero `chapter_id` values resolve to a real `chapter_master`
     * row, but the Chapter match still uses the same uid-then-chId fallback
     * `projectConcepts()` established, for the same reason (pipeline A only
     * ever sets `chId`).
     *
     * `lms_concept_outcome` (the bridge) has equally clean referential
     * integrity - verified live that 100% of its 31,743 rows resolve both
     * `concept_id` and `outcome_id`, and it carries a real numeric
     * `match_score` column (not just the `match_source` label), copied onto
     * the edge alongside `outcome_type`/`match_source` so an automated
     * match's provenance (llm/token_overlap/chapter_scope/inherited) is
     * never lost in the graph.
     *
     * @return array{outcomes: int, part_of: int, part_of_retracted: int, has_outcome: int, has_outcome_retracted: int, addresses: int, unresolved_addresses: int, retracted: int}
     */
    public function projectLearningOutcomes(int $tenant, int $standardId, int $subjectId): array
    {
        $rows = DB::table('lms_learning_outcomes as lo')
            ->where('lo.standard_id', $standardId)
            ->where('lo.subject_id', $subjectId)
            ->select(['lo.id', 'lo.parent_id', 'lo.chapter_id', 'lo.code', 'lo.type', 'lo.description', 'lo.curriculum_id'])
            ->get();

        $nodeCypher = 'UNWIND $rows AS row '
            . 'MERGE (lo:CurriculumOutcome {outcomeId: row.outcomeId}) '
            . 'SET lo += row.props, lo.coherence_synced_at = datetime() '
            . 'RETURN count(lo) AS c';

        $outcomes = 0;

        foreach ($rows->chunk(self::BATCH) as $chunk) {
            $payload = [];

            foreach ($chunk as $r) {
                $payload[] = [
                    'outcomeId' => (int) $r->id,
                    'props'     => array_filter([
                        'type'             => $this->str($r->type) ?: null,
                        'code'             => $this->str($r->code) ?: null,
                        'description'      => $this->str($r->description) ?: null,
                        'curriculum_id'    => $this->intOrNull($r->curriculum_id),
                        'chapter_id'       => (int) $r->chapter_id,
                        'sub_institute_id' => $tenant,
                        'standard_id'      => (string) $standardId,
                        'subject_id'       => (string) $subjectId,
                    ], fn ($v) => $v !== null),
                ];
            }

            $first = $this->neo4j->run($nodeCypher, ['rows' => $payload])->first();
            $outcomes += $first ? (int) $first->get('c') : 0;
        }

        // ---- PART_OF: child -> parent (competency->goal, LO->competency) --
        $partOfRows = $rows->filter(fn ($r) => $r->parent_id !== null);
        $partOfCypher = 'UNWIND $rows AS row '
            . 'MATCH (child:CurriculumOutcome {outcomeId: row.childId}) '
            . 'MATCH (parent:CurriculumOutcome {outcomeId: row.parentId}) '
            . 'MERGE (child)-[e:PART_OF]->(parent) '
            . 'RETURN count(e) AS c';

        $partOfQualifying = [];
        $partOf = 0;

        foreach ($partOfRows->chunk(self::BATCH) as $chunk) {
            $payload = [];

            foreach ($chunk as $r) {
                $partOfQualifying[(int) $r->id . ':' . (int) $r->parent_id] = true;
                $payload[] = ['childId' => (int) $r->id, 'parentId' => (int) $r->parent_id];
            }

            $first = $this->neo4j->run($partOfCypher, ['rows' => $payload])->first();
            $partOf += $first ? (int) $first->get('c') : 0;
        }

        $partOfRetracted = $this->retract(
            'MATCH (child:CurriculumOutcome {sub_institute_id: $tenant, standard_id: $standardId, subject_id: $subjectId})-[e:PART_OF]->(parent:CurriculumOutcome) '
                . 'RETURN child.outcomeId AS fromId, parent.outcomeId AS toId',
            ['tenant' => $tenant, 'standardId' => (string) $standardId, 'subjectId' => (string) $subjectId],
            'UNWIND $rows AS row '
                . 'MATCH (child:CurriculumOutcome {outcomeId: row.fromId})-[e:PART_OF]->(parent:CurriculumOutcome {outcomeId: row.toId}) '
                . 'DELETE e',
            $partOfQualifying
        );

        // ---- HAS_OUTCOME: Chapter -> CurriculumOutcome, chapter_id != 0 ---
        $chapterRows = $rows->filter(fn ($r) => (int) $r->chapter_id !== 0);
        $hasOutcome = 0;
        $chapterQualifying = [];

        // The chapter's own numeric id is stamped onto the EDGE itself
        // (`e.chapterRef`), not read back off the Chapter node's `chId`/`uid`
        // properties, for retraction to key on. Found live 2026-09-29 that
        // reading `ch.chId` back for retraction is unsafe here: a chapter
        // matched via the `uid` fallback (below) is not guaranteed to carry
        // `chId` too - only `projectConcepts()`'s own self-healing SET
        // backfills that, and this method doesn't share that side effect -
        // so `ch.chId` came back NULL for some rows, every one of those was
        // wrongly classed "stale" by retract(), and only failed to actually
        // delete anything because the delete cypher's own `{chId: row.fromId}`
        // match (fromId being NULL) also matched nothing - harmless this
        // time, but not a retraction path to leave silently broken.
        $chapterCypher = 'UNWIND $rows AS row '
            . 'MATCH (lo:CurriculumOutcome {outcomeId: row.outcomeId}) '
            . 'OPTIONAL MATCH (chByUid:Chapter {uid: row.chapterUid}) '
            . 'OPTIONAL MATCH (chByChId:Chapter {chId: row.chapterId}) '
            . 'WITH lo, row, chByUid, chByChId, '
            . '     CASE WHEN chByUid IS NOT NULL THEN chByUid ELSE chByChId END AS ch '
            . 'FOREACH (_ IN CASE WHEN ch IS NULL THEN [] ELSE [1] END | '
            . '    MERGE (ch)-[e2:HAS_OUTCOME]->(lo) '
            . '    SET e2.chapterRef = row.chapterId ) '
            . 'RETURN count(ch) AS linked';

        foreach ($chapterRows->chunk(self::BATCH) as $chunk) {
            $payload = [];

            foreach ($chunk as $r) {
                $chapterQualifying[(int) $r->chapter_id . ':' . (int) $r->id] = true;
                $payload[] = [
                    'outcomeId'  => (int) $r->id,
                    'chapterUid' => 'Chapter:' . $tenant . ':0:' . (int) $r->chapter_id,
                    'chapterId'  => (int) $r->chapter_id,
                ];
            }

            $first = $this->neo4j->run($chapterCypher, ['rows' => $payload])->first();
            $hasOutcome += $first ? (int) $first->get('linked') : 0;
        }

        $hasOutcomeRetracted = $this->retract(
            'MATCH (ch:Chapter)-[e:HAS_OUTCOME]->(lo:CurriculumOutcome {sub_institute_id: $tenant, standard_id: $standardId, subject_id: $subjectId}) '
                . 'RETURN e.chapterRef AS fromId, lo.outcomeId AS toId',
            ['tenant' => $tenant, 'standardId' => (string) $standardId, 'subjectId' => (string) $subjectId],
            'UNWIND $rows AS row '
                . 'MATCH (lo:CurriculumOutcome {outcomeId: row.toId})<-[e:HAS_OUTCOME {chapterRef: row.fromId}]-(:Chapter) '
                . 'DELETE e',
            $chapterQualifying
        );

        // ---- HAS_OUTCOME: Curriculum -> CurriculumOutcome, chapter_id = 0 ----
        // The top-level Goals (CG-n) hang off the CURRICULUM, not a chapter (see
        // the chapter_id != 0 block above) - the controller reads these via
        // `outcomesByCurriculum` (CurriculumPlanningApiController::index(),
        // keyed by curriculum_id). Before this, a Goal row's curriculum_id was
        // only a PROPERTY on :CurriculumOutcome, never a traversable edge, so
        // "every competency this curriculum declares" had no graph path from
        // :Curriculum at all. Same chId-unsafe-for-retraction lesson as the
        // chapter block: `curriculumRef` is stamped onto the edge itself.
        $curriculumRows = $rows->filter(fn ($r) => (int) $r->chapter_id === 0 && (int) $r->curriculum_id !== 0);
        $hasOutcomeByCurriculum = 0;
        $curriculumQualifying = [];

        $curriculumCypher = 'UNWIND $rows AS row '
            . 'MATCH (lo:CurriculumOutcome {outcomeId: row.outcomeId}) '
            . 'OPTIONAL MATCH (curByUid:Curriculum {uid: row.curriculumUid}) '
            . 'OPTIONAL MATCH (curByCurId:Curriculum {curriculumId: row.curriculumId}) '
            . 'WITH lo, row, curByUid, curByCurId, '
            . '     CASE WHEN curByUid IS NOT NULL THEN curByUid ELSE curByCurId END AS cur '
            . 'FOREACH (_ IN CASE WHEN cur IS NULL THEN [] ELSE [1] END | '
            . '    MERGE (cur)-[e3:HAS_OUTCOME]->(lo) '
            . '    SET e3.curriculumRef = row.curriculumId ) '
            . 'RETURN count(cur) AS linked';

        foreach ($curriculumRows->chunk(self::BATCH) as $chunk) {
            $payload = [];

            foreach ($chunk as $r) {
                $curriculumQualifying[(int) $r->curriculum_id . ':' . (int) $r->id] = true;
                $payload[] = [
                    'outcomeId'     => (int) $r->id,
                    'curriculumUid' => 'Curriculum:' . $tenant . ':0:' . (int) $r->curriculum_id,
                    'curriculumId'  => (int) $r->curriculum_id,
                ];
            }

            $first = $this->neo4j->run($curriculumCypher, ['rows' => $payload])->first();
            $hasOutcomeByCurriculum += $first ? (int) $first->get('linked') : 0;
        }

        $hasOutcomeByCurriculumRetracted = $this->retract(
            'MATCH (cur:Curriculum)-[e:HAS_OUTCOME]->(lo:CurriculumOutcome {sub_institute_id: $tenant, standard_id: $standardId, subject_id: $subjectId}) '
                . 'RETURN e.curriculumRef AS fromId, lo.outcomeId AS toId',
            ['tenant' => $tenant, 'standardId' => (string) $standardId, 'subjectId' => (string) $subjectId],
            'UNWIND $rows AS row '
                . 'MATCH (lo:CurriculumOutcome {outcomeId: row.toId})<-[e:HAS_OUTCOME {curriculumRef: row.fromId}]-(:Curriculum) '
                . 'DELETE e',
            $curriculumQualifying
        );

        // ---- ADDRESSES: Concept -> CurriculumOutcome, from lms_concept_outcome
        // Scoped on the OUTCOME side (lo.standard_id/subject_id), not the
        // concept side - verified live 2026-09-29 that `lms_concept_outcome`
        // and `lms_learning_outcomes` always agree on `standard_id` (0
        // disagreements across 31,743 rows) but NOT always on `subject_id`
        // (1,340 rows / 4.2% disagree, concentrated in one std-42 subject
        // pair that shares real chapters - almost certainly two `subject_id`
        // values in `sub_std_map` both meaning the same subject). Scoping by
        // the concept's subject_id instead left those 1,340 rows permanently
        // unresolved (their outcome never gets created under the concept's
        // subject_id, because the outcome doesn't have that subject_id).
        // Scoping by the outcome's own subject_id matches exactly how this
        // method's own node-creation pass above keys `:CurriculumOutcome`,
        // so the outcome side is always guaranteed to already exist by the
        // time this bridge query runs in the same call - the same "scope the
        // side that actually needs it" reasoning `projectRelations()` uses
        // for cross-standard prerequisites.
        $bridgeRows = DB::table('lms_concept_outcome as co')
            ->join('lms_concept as k', 'k.id', '=', 'co.concept_id')
            ->join('lms_learning_outcomes as lo', 'lo.id', '=', 'co.outcome_id')
            ->where('lo.standard_id', $standardId)
            ->where('lo.subject_id', $subjectId)
            ->where('k.sub_institute_id', $tenant)
            ->select(['co.concept_id', 'co.outcome_id', 'co.outcome_type', 'co.match_source', 'co.match_score'])
            ->get();

        $addressesCypher = 'UNWIND $rows AS row '
            . 'MATCH (c:Concept {conceptId: row.conceptId}) '
            . 'MATCH (lo:CurriculumOutcome {outcomeId: row.outcomeId}) '
            . 'MERGE (c)-[e:ADDRESSES]->(lo) '
            . 'SET e += row.props '
            . 'RETURN count(e) AS c';

        $bridgeQualifying = [];
        foreach ($bridgeRows as $r) {
            $bridgeQualifying[(int) $r->concept_id . ':' . (int) $r->outcome_id] = true;
        }

        $bridgeResult = $this->linkDelivery(
            $bridgeRows,
            fn ($r) => [
                'conceptId' => (int) $r->concept_id,
                'outcomeId' => (int) $r->outcome_id,
                'props'     => array_filter([
                    'outcome_type' => $this->str($r->outcome_type) ?: null,
                    'match_source' => $this->str($r->match_source) ?: null,
                    'match_score'  => $r->match_score === null ? null : (float) $r->match_score,
                ], fn ($v) => $v !== null),
            ],
            $addressesCypher,
            'addresses',
            'unresolved_addresses'
        );

        // Scoped by the OUTCOME side (lo.sub_institute_id/standard_id/
        // subject_id), matching the query above exactly - not by the
        // Concept's own scope. Scoping this by the Concept instead would
        // retract the very cross-subject edges the fix above exists to
        // create: a concept in (42,4469) whose matched outcome lives in
        // (42,4064) only ever appears in $bridgeRows during THAT scope's
        // call, so a Concept-scoped retraction running afterwards for
        // (42,4469) would see the edge as live, find it absent from this
        // call's (differently-scoped) qualifying set, and delete it.
        $bridgeResult['retracted'] = $this->retract(
            'MATCH (c:Concept)-[e:ADDRESSES]->(lo:CurriculumOutcome {sub_institute_id: $tenant, standard_id: $standardId, subject_id: $subjectId}) '
                . 'RETURN c.conceptId AS fromId, lo.outcomeId AS toId',
            ['tenant' => $tenant, 'standardId' => (string) $standardId, 'subjectId' => (string) $subjectId],
            'UNWIND $rows AS row '
                . 'MATCH (c:Concept {conceptId: row.fromId})-[e:ADDRESSES]->(lo:CurriculumOutcome {outcomeId: row.toId}) '
                . 'DELETE e',
            $bridgeQualifying
        );

        return [
            'outcomes'                   => $outcomes,
            'part_of'                    => $partOf,
            'part_of_retracted'          => $partOfRetracted,
            'has_outcome'                => $hasOutcome,
            'has_outcome_retracted'      => $hasOutcomeRetracted,
            'has_outcome_by_curriculum'  => $hasOutcomeByCurriculum,
            'addresses'                  => $bridgeResult['addresses'],
            'unresolved_addresses'       => $bridgeResult['unresolved_addresses'],
            'retracted'                  => $partOfRetracted + $hasOutcomeRetracted
                + $hasOutcomeByCurriculumRetracted + $bridgeResult['retracted'],
        ];
    }

    // ==================================================================
    // 8. Topic nodes - topic_master, previously entirely unrepresented
    // ==================================================================

    /**
     * Project topic_master into the graph as its own label:
     *
     *   (:Chapter)-[:HAS_TOPIC]->(:Topic)-[:HAS_CONCEPT]->(:Concept)
     *
     * `CurriculumPlanningApiController` (the live `/lms/curriculum-planning`
     * screen) already renders a full Curriculum->Unit->Chapter->Topic->Concept
     * tree straight from SQL; Neo4j had every level except Topic (Unit has had
     * a pipeline-A spec since the original load - `config/neo4j.php`'s
     * `lms_units` entry - this was the one level actually missing).
     *
     * Keyed on `topicId` (native `topic_master.id`), same "native id, no
     * pre-existing convention to clash with" reasoning :Misconception and
     * :ConceptNode already use.
     *
     * SCOPING. topic_master carries no standard_id/subject_id of its own, only
     * chapter_id, so this INNER JOINs chapter_master to resolve scope - which
     * as a side effect only ever projects topics whose chapter_id resolves to
     * a real row. Measured live 2026-10-06: only 2,597 of 16,116 topic_master
     * rows do (83.9% dangling chapter_id - an upstream data-quality defect,
     * not something to paper over by inventing a scope for an orphan row).
     * `lms_concept.topic_id`, by contrast, is 100% healthy (7,532/7,532
     * resolve), so the Topic->Concept edge below is not weakened the same way.
     *
     * @return array{topics: int, has_topic: int, has_topic_retracted: int, has_concept: int, unresolved_has_concept: int, retracted: int}
     */
    public function projectTopics(int $tenant, int $standardId, int $subjectId): array
    {
        $rows = DB::table('topic_master as t')
            ->join('chapter_master as c', 'c.id', '=', 't.chapter_id')
            ->where('t.sub_institute_id', $tenant)
            ->where('c.standard_id', $standardId)
            ->where('c.subject_id', $subjectId)
            ->select(['t.id', 't.chapter_id', 't.main_topic_id', 't.name', 't.description', 't.topic_show_hide', 't.topic_sort_order'])
            ->get();

        $nodeCypher = 'UNWIND $rows AS row '
            . 'MERGE (t:Topic {topicId: row.topicId}) '
            . 'SET t += row.props, t.coherence_synced_at = datetime() '
            . 'RETURN count(t) AS c';

        $topics = 0;

        foreach ($rows->chunk(self::BATCH) as $chunk) {
            $payload = [];

            foreach ($chunk as $r) {
                $payload[] = [
                    'topicId' => (int) $r->id,
                    'props'   => array_filter([
                        'name'             => $this->str($r->name) ?: null,
                        'description'      => $this->str($r->description) ?: null,
                        'chapter_id'       => (int) $r->chapter_id,
                        'main_topic_id'    => $this->intOrNull($r->main_topic_id),
                        'show_hide'        => $this->intOrNull($r->topic_show_hide),
                        'sort_order'       => $this->intOrNull($r->topic_sort_order),
                        'sub_institute_id' => $tenant,
                        'standard_id'      => (string) $standardId,
                        'subject_id'       => (string) $subjectId,
                    ], fn ($v) => $v !== null),
                ];
            }

            $first = $this->neo4j->run($nodeCypher, ['rows' => $payload])->first();
            $topics += $first ? (int) $first->get('c') : 0;
        }

        // ---- HAS_TOPIC: Chapter -> Topic ----
        // Same dual uid/chId MATCH and edge-stamped-ref retraction pattern as
        // projectLearningOutcomes()'s HAS_OUTCOME block, for the same reason:
        // a chapter matched only via the uid fallback is not guaranteed to
        // carry `chId`, so reading it back off the Chapter node for retraction
        // is unsafe - the id travels on the edge itself instead.
        $hasTopicCypher = 'UNWIND $rows AS row '
            . 'MATCH (t:Topic {topicId: row.topicId}) '
            . 'OPTIONAL MATCH (chByUid:Chapter {uid: row.chapterUid}) '
            . 'OPTIONAL MATCH (chByChId:Chapter {chId: row.chapterId}) '
            . 'WITH t, row, chByUid, chByChId, '
            . '     CASE WHEN chByUid IS NOT NULL THEN chByUid ELSE chByChId END AS ch '
            . 'FOREACH (_ IN CASE WHEN ch IS NULL THEN [] ELSE [1] END | '
            . '    MERGE (ch)-[e:HAS_TOPIC]->(t) '
            . '    SET e.chapterRef = row.chapterId ) '
            . 'RETURN count(ch) AS linked';

        $hasTopic = 0;
        $hasTopicQualifying = [];

        foreach ($rows->chunk(self::BATCH) as $chunk) {
            $payload = [];

            foreach ($chunk as $r) {
                $hasTopicQualifying[(int) $r->chapter_id . ':' . (int) $r->id] = true;
                $payload[] = [
                    'topicId'    => (int) $r->id,
                    'chapterUid' => 'Chapter:' . $tenant . ':0:' . (int) $r->chapter_id,
                    'chapterId'  => (int) $r->chapter_id,
                ];
            }

            $first = $this->neo4j->run($hasTopicCypher, ['rows' => $payload])->first();
            $hasTopic += $first ? (int) $first->get('linked') : 0;
        }

        $hasTopicRetracted = $this->retract(
            'MATCH (ch:Chapter)-[e:HAS_TOPIC]->(t:Topic {sub_institute_id: $tenant, standard_id: $standardId, subject_id: $subjectId}) '
                . 'RETURN e.chapterRef AS fromId, t.topicId AS toId',
            ['tenant' => $tenant, 'standardId' => (string) $standardId, 'subjectId' => (string) $subjectId],
            'UNWIND $rows AS row '
                . 'MATCH (t:Topic {topicId: row.toId})<-[e:HAS_TOPIC {chapterRef: row.fromId}]-(:Chapter) '
                . 'DELETE e',
            $hasTopicQualifying
        );

        // ---- HAS_CONCEPT: Topic -> Concept, from lms_concept.topic_id ----
        // Scoped by lms_concept directly (it carries standard_id/subject_id of
        // its own), unlike the chapter-scoped block above - no dangling-FK
        // concern here, topic_id resolves 100% of the time where set.
        $conceptRows = DB::table('lms_concept as k')
            ->where('k.sub_institute_id', $tenant)
            ->where('k.standard_id', $standardId)
            ->where('k.subject_id', $subjectId)
            ->whereNotNull('k.topic_id')
            ->where('k.topic_id', '!=', 0)
            ->select(['k.id', 'k.topic_id'])
            ->get();

        $topicConceptCypher = 'UNWIND $rows AS row '
            . 'MATCH (t:Topic {topicId: row.topicId}) '
            . 'MATCH (c:Concept {conceptId: row.conceptId}) '
            . 'MERGE (t)-[e:HAS_CONCEPT]->(c) '
            . 'RETURN count(e) AS c';

        $conceptQualifying = [];
        foreach ($conceptRows as $r) {
            $conceptQualifying[(int) $r->topic_id . ':' . (int) $r->id] = true;
        }

        $conceptResult = $this->linkDelivery(
            $conceptRows,
            fn ($r) => ['topicId' => (int) $r->topic_id, 'conceptId' => (int) $r->id],
            $topicConceptCypher,
            'has_concept',
            'unresolved_has_concept'
        );

        $conceptRetracted = $this->retract(
            'MATCH (t:Topic)-[e:HAS_CONCEPT]->(c:Concept {sub_institute_id: $tenant, standard_id: $standardId, subject_id: $subjectId}) '
                . 'RETURN t.topicId AS fromId, c.conceptId AS toId',
            ['tenant' => $tenant, 'standardId' => (string) $standardId, 'subjectId' => (string) $subjectId],
            'UNWIND $rows AS row '
                . 'MATCH (t:Topic {topicId: row.fromId})-[e:HAS_CONCEPT]->(c:Concept {conceptId: row.toId}) '
                . 'DELETE e',
            $conceptQualifying
        );

        return [
            'topics'                  => $topics,
            'has_topic'               => $hasTopic,
            'has_topic_retracted'     => $hasTopicRetracted,
            'has_concept'             => $conceptResult['has_concept'],
            'unresolved_has_concept'  => $conceptResult['unresolved_has_concept'],
            'retracted'               => $hasTopicRetracted + $conceptRetracted,
        ];
    }

    // ==================================================================
    // 9. Chapter enrichment - semantic_intelligence + document_extractions
    // ==================================================================

    /**
     * SET-only enrichment of existing :Chapter nodes from the document
     * extraction / AI chapter-intelligence pipeline - no new label, no new
     * edge, just properties the curriculum-planning screen already surfaces
     * per chapter (`learning_objective`, `total_concepts`, document
     * provenance). This is the other half of the "document-extraction/
     * semantic-intelligence" gap alongside Topic above.
     *
     * `blooms_level` is deliberately NOT copied: it is a per-concept JSON blob
     * on this table, not a chapter-level scalar - the controller itself keeps
     * it out of the roll-up for the same reason (its own comment: "reads like
     * a scalar but holds a per-concept JSON blob"). `pdf_url`/`md_content`
     * stay in MariaDB too - large, and not something a graph traversal needs.
     *
     * @return array{enriched: int, unresolved: int}
     */
    public function projectChapterIntelligence(int $tenant, int $standardId, int $subjectId): array
    {
        $rows = DB::table('chapter_master as c')
            ->leftJoin('semantic_intelligence as si', 'si.chapter_id', '=', 'c.id')
            ->leftJoin('document_extractions as de', 'de.id', '=', 'c.extraction_id')
            ->where('c.sub_institute_id', $tenant)
            ->where('c.standard_id', $standardId)
            ->where('c.subject_id', $subjectId)
            ->select([
                'c.id as chapter_id',
                'si.learning_objective', 'si.total_concepts',
                'de.document_type', 'de.document_tittle as document_title', 'de.board', 'de.page_count',
            ])
            ->get();

        $cypher = 'UNWIND $rows AS row '
            . 'OPTIONAL MATCH (chByUid:Chapter {uid: row.chapterUid}) '
            . 'OPTIONAL MATCH (chByChId:Chapter {chId: row.chapterId}) '
            . 'WITH row, CASE WHEN chByUid IS NOT NULL THEN chByUid ELSE chByChId END AS ch '
            . 'FOREACH (_ IN CASE WHEN ch IS NULL THEN [] ELSE [1] END | SET ch += row.props) '
            . 'RETURN count(ch) AS c';

        // A chapter with neither a semantic_intelligence nor a document_extractions
        // row would otherwise build a completely empty `props` map. The Bolt
        // driver (unlike the HTTP transaction API copy-lms-pal now uses) cannot
        // tell an empty PHP array bound as a map parameter from an empty list and
        // sends it as the latter, which Neo4j's `SET ch += row.props` then
        // rejects outright ("Expected row.props to be a map, but it was List{}")
        // - measured live 2026-10-06 on 5/40 scopes. Nothing to enrich for such a
        // chapter anyway, so it is simply excluded rather than sent empty.
        $enrichable = $rows
            ->map(fn ($r) => [
                'chapterUid' => 'Chapter:' . $tenant . ':0:' . (int) $r->chapter_id,
                'chapterId'  => (int) $r->chapter_id,
                'props'      => array_filter([
                    'learning_objective' => $this->str($r->learning_objective) ?: null,
                    'total_concepts'     => $this->intOrNull($r->total_concepts),
                    'document_type'      => $this->str($r->document_type) ?: null,
                    'document_title'     => $this->str($r->document_title) ?: null,
                    'document_board'     => $this->str($r->board) ?: null,
                    'document_pages'     => $this->intOrNull($r->page_count),
                ], fn ($v) => $v !== null),
            ])
            ->filter(fn ($row) => $row['props'] !== []);

        return $this->linkDelivery(
            $enrichable,
            fn ($row) => $row,
            $cypher,
            'enriched',
            'unresolved'
        );
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
     * Retract edges that are live in Neo4j but no longer belong there -
     * their SQL row was rejected by the review workflow, or deleted outright,
     * since the last sync.
     *
     * Read-then-diff-then-delete: `$liveCypher` returns every currently-live
     * edge of one type within the caller's scope as `fromId`/`toId` pairs,
     * each is checked against `$qualifying` (the set the caller just
     * projected THIS run, already filtered to non-rejected rows), and
     * anything not in that set is deleted via `$deleteCypher`. This is the
     * fix for the gap the design review found: `CoherenceGraphProjection`
     * previously only ever MERGEd, so a `pal_concept_relations` /
     * `pal_content_metadata` / `pal_question_metadata` row that became
     * `rejected` or was deleted left its edge stranded in the graph forever.
     *
     * Never guesses: an edge is only ever removed because the exact same
     * scoped query that would have re-written it no longer produced it.
     *
     * @param  string  $liveCypher  returns `fromId`, `toId` for every live edge in scope
     * @param  array<string, mixed>  $liveParams
     * @param  string  $deleteCypher  `UNWIND $rows AS row ... DELETE e`, keyed the same way as `$liveCypher`
     * @param  array<string, true>  $qualifying  set of "fromId:toId" keys allowed to survive, as built by the caller
     */
    private function retract(string $liveCypher, array $liveParams, string $deleteCypher, array $qualifying): int
    {
        $stale = [];

        foreach ($this->neo4j->run($liveCypher, $liveParams) as $row) {
            $fromId = $row->get('fromId');
            $toId = $row->get('toId');

            if (! isset($qualifying[$fromId . ':' . $toId])) {
                $stale[] = ['fromId' => $fromId, 'toId' => $toId];
            }
        }

        if ($stale === []) {
            return 0;
        }

        foreach (array_chunk($stale, self::BATCH) as $chunk) {
            $this->neo4j->run($deleteCypher, ['rows' => $chunk]);
        }

        return count($stale);
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
