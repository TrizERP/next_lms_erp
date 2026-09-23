<?php

namespace App\Services\PAL\Coherence;

use App\Services\Neo4jService;
use Illuminate\Support\Facades\DB;

/**
 * Reads the Set Coherence Map back out of Neo4j.
 *
 * ---------------------------------------------------------------------------
 * SIGNATURES ARE NOT MINE TO CHOOSE
 * ---------------------------------------------------------------------------
 * Every public method here is called by code that already shipped -
 * `CoherenceMapController` (6 routes) and `CoherenceSyncCommand::gate()` - so
 * the argument lists and the exact array keys returned are fixed by those call
 * sites, not by preference. `health()` in particular must return
 * concepts / roots / without_content / without_questions / acyclic / cycles,
 * because the command tabulates all six by name.
 *
 * Tenancy is the one thing the call sites get wrong: `map($standard, $subject,
 * $learner)` passes no `sub_institute_id`. In this schema standard_id and
 * subject_id happen to be globally unique, so the pair does imply one
 * institute - but relying on that is how a cross-tenant leak gets shipped. Each
 * method therefore takes an OPTIONAL trailing $tenant and, when it is null,
 * resolves it from `lms_concept` and filters on it anyway. Existing callers keep
 * working; the guard is there regardless.
 *
 * ---------------------------------------------------------------------------
 * WHY THE TRAVERSAL IS DONE IN PHP AND NOT IN CYPHER
 * ---------------------------------------------------------------------------
 * The prerequisite graph in this database is NOT acyclic - measured 2026-08-24,
 * `pal_concept_relations` holds 41 reciprocal `requires` pairs, 6 of which are
 * inside the one projected scope. A variable-length Cypher traversal over a
 * cyclic graph either has to be depth-capped (silently truncating real chains)
 * or run per-node (one round trip each). The whole scope is 118 concepts and
 * 192 edges, so it is cheaper and far more predictable to fetch the edge list
 * once and walk it here with an explicit visited-set. That also means the cycle
 * report `health()` owes the command is a by-product rather than a second query.
 *
 * Depth cap on the closure walks is DEPTH_CAP, which exists only as a
 * runaway guard: measured longest real chain in the live scope is 6.
 */
class CoherenceMapRepository
{
    /**
     * Hard ceiling on closure depth. Not a pedagogy decision - a guard, so a
     * cycle that slips past the visited-set cannot spin. The longest genuine
     * prerequisite chain measured live is 6.
     */
    private const DEPTH_CAP = 24;

    /** The default gate when neither PAL nor the legacy column authors one. */
    private const GATE_FLOOR = 0.70;

    /**
     * Per-request memo of the scope graph, keyed tenant:standard:subject.
     *
     * `map()` and `readiness()` are both called inside one request by
     * `CoherenceMapController::learner()`, and `next()` calls the recommender
     * which calls `readiness()` again. Without this the same 118-node scope is
     * pulled out of Neo4j three times per page load.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $scopeCache = [];

    public function __construct(private readonly Neo4jService $neo4j)
    {
    }

    // ══════════════════════════════════════════════════════════════════
    // The map
    // ══════════════════════════════════════════════════════════════════

    /**
     * The authored map for one standard + subject, optionally overlaid with one
     * learner's mastery.
     *
     * `available` is false rather than throwing when the scope has no concepts:
     * the controller turns that into a 404 with an actionable message, and an
     * unprojected scope is an expected state during rollout, not an error.
     *
     * @return array{available: bool, nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>, chapters: array<int, array<string, mixed>>, stats: array<string, mixed>}
     */
    public function map(int $standardId, int $subjectId, ?int $learnerId = null, ?int $tenant = null): array
    {
        $scope = $this->scope($standardId, $subjectId, $tenant);

        if ($scope['nodes'] === []) {
            return [
                'available' => false,
                'nodes'     => [],
                'edges'     => [],
                'chapters'  => [],
                'stats'     => ['concepts' => 0, 'requires' => 0, 'cross_links' => 0],
            ];
        }

        $mastery = $learnerId === null ? [] : $this->masteryFor($learnerId, array_keys($scope['nodes']));

        $nodes = [];

        foreach ($scope['nodes'] as $id => $n) {
            $m = $mastery[$id] ?? null;

            $nodes[] = [
                'id'          => $id,
                'name'        => $n['name'],
                'code'        => $n['code'],
                'description' => $n['description'],
                'chapter_id'  => $n['chapter_id'],
                'chapter'     => $n['chapter'],
                'chapter_order' => $n['chapter_order'],
                'standard_id' => $n['standard_id'],
                'subject_id'  => $n['subject_id'],
                'bloom'       => $n['bloom'],
                'priority'    => $n['priority'],
                'gate'        => $n['gate'],
                'minutes'     => $n['minutes'],
                'status'      => $n['status'],
                // Structural position. `depth` is how many prerequisites deep
                // this concept sits, and is what the client lays the graph out
                // on - it is the only ordering signal the data actually has.
                'depth'       => $scope['depth'][$id] ?? 0,
                'prereq_ids'  => array_values($scope['requires'][$id] ?? []),
                'unlocks_ids' => array_values($scope['unlocked_by'][$id] ?? []),
                'content_n'   => $n['content_n'],
                'question_n'  => $n['question_n'],
                'on_cycle'    => in_array($id, $scope['cycle_nodes'], true),
                // Present only when a learner was named, so a client can tell
                // "no overlay requested" from "overlay requested, no evidence".
                'mastery'     => $m === null ? null : round($m['p'], 4),
                'attempts'    => $m === null ? null : $m['attempts'],
                'band'        => $m === null ? null : $m['band'],
            ];
        }

        return [
            'available' => true,
            'nodes'     => $nodes,
            'edges'     => $scope['edges'],
            'chapters'  => array_values($scope['chapters']),
            'stats'     => [
                'concepts'     => count($nodes),
                'requires'     => $scope['requires_count'],
                'cross_links'  => $scope['cross_count'],
                'chapters'     => count($scope['chapters']),
                'roots'        => count($scope['roots']),
                'isolated'     => $scope['isolated'],
                'max_depth'    => $scope['depth'] === [] ? 0 : max($scope['depth']),
                'acyclic'      => $scope['cycle_nodes'] === [],
                'draft_edges'  => $scope['draft_edges'],
                'with_content' => $scope['with_content'],
                'with_questions' => $scope['with_questions'],
            ],
        ];
    }

    // ══════════════════════════════════════════════════════════════════
    // Readiness
    // ══════════════════════════════════════════════════════════════════

    /**
     * Classify every concept in scope for one learner.
     *
     *   mastered - p >= the concept's gate
     *   ready    - not mastered, and every DIRECT prerequisite is mastered
     *   blocked  - not mastered, and at least one prerequisite is not
     *
     * A concept with no prerequisites is `ready` from the first page load, which
     * is what makes the map usable before any evidence exists.
     *
     * Keyed by concept id because the controller does `$states[$id]`.
     *
     * @return array<int, array{state: string, unmet: array<int, int>, unlocks: int, mastery: float, gate: float}>
     */
    public function readiness(int $standardId, int $subjectId, int $learnerId, ?int $tenant = null): array
    {
        $scope = $this->scope($standardId, $subjectId, $tenant);

        if ($scope['nodes'] === []) {
            return [];
        }

        $mastery = $this->masteryFor($learnerId, array_keys($scope['nodes']));
        $out = [];

        foreach ($scope['nodes'] as $id => $n) {
            $gate = $n['gate'] ?: self::GATE_FLOOR;
            $p = $mastery[$id]['p'] ?? 0.0;

            $unmet = [];

            foreach ($scope['requires'][$id] ?? [] as $prereq) {
                $pg = ($scope['nodes'][$prereq]['gate'] ?? 0) ?: self::GATE_FLOOR;

                if (($mastery[$prereq]['p'] ?? 0.0) < $pg) {
                    $unmet[] = $prereq;
                }
            }

            $out[$id] = [
                'state'   => $p >= $gate ? 'mastered' : ($unmet === [] ? 'ready' : 'blocked'),
                'unmet'   => $unmet,
                // The postrequisite CLOSURE, not the direct count: "fix this and
                // 6 things open up" is the prioritisation argument, and only the
                // closure answers it.
                'unlocks' => count($this->walk($id, $scope['unlocked_by'])),
                'mastery' => round($p, 4),
                'gate'    => round($gate, 4),
            ];
        }

        return $out;
    }

    // ══════════════════════════════════════════════════════════════════
    // Remediation
    // ══════════════════════════════════════════════════════════════════

    /**
     * The deepest unmastered ancestors of one concept: unmastered themselves,
     * but with nothing unmastered beneath them.
     *
     * This is the answer a mark sheet cannot give - not "you got this wrong" but
     * "you got this wrong because THIS earlier thing is not in place". Sorted
     * weakest-first so `$roots[0]` is where the controller sends the learner.
     *
     * Scope is derived from the concept itself, because the controller calls
     * this with a concept id and a learner id and nothing else.
     *
     * @return array<int, array{id: int, name: string, mastery: float, gate: float, depth: int}>
     */
    public function rootBlockers(int $conceptId, int $learnerId): array
    {
        $home = DB::table('lms_concept')
            ->where('id', $conceptId)
            ->first(['standard_id', 'subject_id', 'sub_institute_id']);

        if ($home === null) {
            return [];
        }

        $scope = $this->scope(
            (int) $home->standard_id,
            (int) $home->subject_id,
            (int) $home->sub_institute_id
        );

        if (! isset($scope['nodes'][$conceptId])) {
            return [];
        }

        // Ancestors with their distance, so the caller can say how far back the
        // real problem sits.
        $depths = $this->walkWithDepth($conceptId, $scope['requires']);

        if ($depths === []) {
            return [];
        }

        $mastery = $this->masteryFor($learnerId, array_keys($depths));

        $unmastered = [];

        foreach ($depths as $id => $depth) {
            $gate = ($scope['nodes'][$id]['gate'] ?? 0) ?: self::GATE_FLOOR;
            $p = $mastery[$id]['p'] ?? 0.0;

            if ($p < $gate) {
                $unmastered[$id] = ['p' => $p, 'gate' => $gate, 'depth' => $depth];
            }
        }

        $roots = [];

        foreach ($unmastered as $id => $row) {
            // A root is an unmastered ancestor with no unmastered prerequisite
            // of its own. Sending a learner anywhere else means sending them to
            // something they are also not ready for.
            foreach ($scope['requires'][$id] ?? [] as $below) {
                if (isset($unmastered[$below])) {
                    continue 2;
                }
            }

            $roots[] = [
                'id'      => $id,
                'name'    => (string) ($scope['nodes'][$id]['name'] ?? ''),
                'mastery' => round($row['p'], 4),
                'gate'    => round($row['gate'], 4),
                'depth'   => $row['depth'],
            ];
        }

        usort($roots, fn ($a, $b) => $a['mastery'] <=> $b['mastery'] ?: $b['depth'] <=> $a['depth']);

        return $roots;
    }

    // ══════════════════════════════════════════════════════════════════
    // Delivery
    // ══════════════════════════════════════════════════════════════════

    /**
     * What can be shown to teach one concept, best first.
     *
     * Reads (:Content)-[:TEACHES]->(:Concept) from the graph rather than
     * re-joining `pal_content_metadata`, so the map and the recommendation can
     * never disagree about what is attached to a concept.
     *
     * `$exclude` is content the learner has already seen - the recommender
     * passes it so a second attempt does not serve the same asset again.
     *
     * @param  array<int, int|string>  $exclude
     * @return array<int, array<string, mixed>>
     */
    public function contentFor(int $conceptId, array $exclude = [], int $limit = 5): array
    {
        // :Content is keyed on `id` AS A STRING - all 31,362 nodes store it that
        // way. Binding integers here matches nothing and returns silently empty.
        $skip = array_values(array_map(fn ($v) => (string) (int) $v, $exclude));

        $cypher = 'MATCH (n:Content)-[e:TEACHES]->(c:Concept {conceptId: $conceptId}) '
            . 'WHERE NOT n.id IN $skip '
            . 'RETURN n.id AS id, n.title AS title, n.displayLabel AS label, '
            . '       e.content_type AS content_type, e.format AS format, '
            . '       e.h5p_type AS h5p_type, e.bloom_level AS bloom, '
            . '       e.difficulty AS difficulty, e.duration_min AS minutes, '
            . '       e.variant_number AS variant, e.quality_status AS status '
            // Approved before draft, then easiest first: a learner arriving here
            // is by definition struggling, so the gentle variant goes first.
            . 'ORDER BY CASE e.quality_status WHEN \'approved\' THEN 0 ELSE 1 END, '
            . '         coalesce(e.difficulty, 3) ASC, coalesce(e.variant_number, 1) ASC '
            . 'LIMIT $limit';

        $out = [];

        foreach ($this->neo4j->run($cypher, [
            'conceptId' => $conceptId,
            'skip'      => $skip,
            'limit'     => max(1, $limit),
        ]) as $r) {
            $out[] = [
                'id'           => (int) $r->get('id'),
                'title'        => $this->text($r->get('title')) ?: $this->text($r->get('label')),
                'content_type' => $this->text($r->get('content_type')),
                'format'       => $this->text($r->get('format')),
                'h5p_type'     => $this->text($r->get('h5p_type')),
                'bloom'        => $this->text($r->get('bloom')),
                'difficulty'   => $this->intOrNull($r->get('difficulty')),
                'minutes'      => $this->intOrNull($r->get('minutes')),
                'status'       => $this->text($r->get('status')) ?: 'draft',
            ];
        }

        return $out;
    }

    /**
     * Questions that assess one concept, easiest first.
     *
     * :Question is keyed on `qId` AS AN INTEGER - the opposite cast from
     * :Content directly above. Both are correct; both were measured.
     *
     * @return array<int, array<string, mixed>>
     */
    public function questionsFor(int $conceptId, int $limit = 5): array
    {
        $cypher = 'MATCH (q:Question)-[e:ASSESSES]->(c:Concept {conceptId: $conceptId}) '
            . 'RETURN q.qId AS id, q.question_title AS title, '
            . '       e.bloom_level AS bloom, e.difficulty AS difficulty, '
            . '       e.practice_level AS practice, e.irt_b AS irt_b, '
            . '       e.assessment_type AS assessment_type, '
            . '       e.misconception_tags AS misconception_tags, '
            . '       e.quality_status AS status '
            . 'ORDER BY coalesce(e.irt_b, 0.0) ASC, coalesce(e.difficulty, 3) ASC '
            . 'LIMIT $limit';

        $out = [];

        foreach ($this->neo4j->run($cypher, ['conceptId' => $conceptId, 'limit' => max(1, $limit)]) as $r) {
            $out[] = [
                'id'                 => (int) $r->get('id'),
                'title'              => $this->text($r->get('title')),
                'bloom'              => $this->text($r->get('bloom')),
                'difficulty'         => $this->intOrNull($r->get('difficulty')),
                'practice_level'     => $this->intOrNull($r->get('practice')),
                'irt_b'              => $r->get('irt_b') === null ? null : (float) $r->get('irt_b'),
                'assessment_type'    => $this->text($r->get('assessment_type')),
                'misconception_tags' => $this->text($r->get('misconception_tags')),
                'status'             => $this->text($r->get('status')) ?: 'draft',
            ];
        }

        return $out;
    }

    // ══════════════════════════════════════════════════════════════════
    // Health
    // ══════════════════════════════════════════════════════════════════

    /**
     * The structural gate. Six keys, all named by `CoherenceSyncCommand::gate()`
     * and by `CoherenceMapController::health()`.
     *
     * A cycle is the one defect that makes the map actively wrong rather than
     * merely incomplete: every concept on the ring is permanently blocked, so
     * the recommender can never offer any of them. `cycles` therefore carries
     * the offending nodes, not just a boolean, so a reviewer can go and break
     * them.
     *
     * @return array{concepts: int, roots: int, without_content: int, without_questions: int, acyclic: bool, cycles: array<int, array{id: int, name: string}>, edges: int, isolated: int, max_depth: int, draft_edges: int}
     */
    public function health(int $standardId, int $subjectId, ?int $tenant = null): array
    {
        $scope = $this->scope($standardId, $subjectId, $tenant);

        $cycles = array_map(
            fn ($id) => ['id' => $id, 'name' => (string) ($scope['nodes'][$id]['name'] ?? '')],
            $scope['cycle_nodes']
        );

        return [
            'concepts'          => count($scope['nodes']),
            'roots'             => count($scope['roots']),
            'without_content'   => count($scope['nodes']) - $scope['with_content'],
            'without_questions' => count($scope['nodes']) - $scope['with_questions'],
            'acyclic'           => $scope['cycle_nodes'] === [],
            'cycles'            => $cycles,
            'edges'             => $scope['requires_count'] + $scope['cross_count'],
            'isolated'          => $scope['isolated'],
            'max_depth'         => $scope['depth'] === [] ? 0 : max($scope['depth']),
            'draft_edges'       => $scope['draft_edges'],
        ];
    }

    /**
     * Which (standard, subject) pairs actually have a projected map.
     *
     * Powers the scope picker, so the UI never offers a combination that
     * returns 404. Reads the graph, not MariaDB: a scope only counts as
     * available once `pal:coherence-sync` has run for it.
     *
     * @return array<int, array<string, mixed>>
     */
    public function scopes(?int $tenant = null): array
    {
        $cypher = 'MATCH (c:Concept) '
            . 'WHERE ($tenant IS NULL OR c.sub_institute_id = $tenant) '
            . '  AND c.standard_id IS NOT NULL AND c.subject_id IS NOT NULL '
            . 'OPTIONAL MATCH (c)-[r:REQUIRES]->(:Concept) '
            . 'WITH c.sub_institute_id AS tenant, '
            . '     toInteger(c.standard_id) AS standardId, '
            . '     toInteger(c.subject_id) AS subjectId, '
            . '     count(DISTINCT c) AS concepts, count(r) AS requires '
            . 'WHERE concepts > 0 '
            . 'RETURN tenant, standardId, subjectId, concepts, requires '
            . 'ORDER BY requires DESC, concepts DESC';

        $rows = [];

        foreach ($this->neo4j->run($cypher, ['tenant' => $tenant]) as $r) {
            $rows[] = [
                'sub_institute_id' => (int) $r->get('tenant'),
                'standard_id'      => (int) $r->get('standardId'),
                'subject_id'       => (int) $r->get('subjectId'),
                'concepts'         => (int) $r->get('concepts'),
                'requires'         => (int) $r->get('requires'),
            ];
        }

        // Names come from MariaDB: the graph's :Standard / :Subject nodes carry
        // two key conventions and matching them here would silently drop rows.
        $standards = DB::table('standard')->whereIn('id', array_column($rows, 'standard_id'))
            ->pluck('name', 'id');
        $subjects = DB::table('sub_std_map')->whereIn('subject_id', array_column($rows, 'subject_id'))
            ->pluck('display_name', 'subject_id');

        return array_map(fn ($r) => $r + [
            'standard_name' => (string) ($standards[$r['standard_id']] ?? ('Standard ' . $r['standard_id'])),
            'subject_name'  => (string) ($subjects[$r['subject_id']] ?? ('Subject ' . $r['subject_id'])),
        ], $rows);
    }

    // ══════════════════════════════════════════════════════════════════
    // The one read that everything above shares
    // ══════════════════════════════════════════════════════════════════

    /**
     * Pull one scope's concepts and coherence edges, and derive its structure.
     *
     * ONE query for nodes, one for edges, then everything else in PHP. See the
     * class docblock for why the traversal is not Cypher.
     *
     * `PREREQUISITE_OF` is deliberately absent from the edge pattern. It is the
     * largest edge set in the graph (9,499) and it is a machine artefact -
     * every pair of concepts inside a chapter, ordered by id, which is the
     * transitive closure of a sort and not pedagogy. Including it turns every
     * chapter into a solid blob. `ReconcileCommand` documents its origin.
     *
     * @return array<string, mixed>
     */
    private function scope(int $standardId, int $subjectId, ?int $tenant): array
    {
        $tenant ??= $this->tenantOf($standardId, $subjectId);
        $key = $tenant . ':' . $standardId . ':' . $subjectId;

        if (isset($this->scopeCache[$key])) {
            return $this->scopeCache[$key];
        }

        $nodes = $this->readNodes($standardId, $subjectId, $tenant);

        if ($nodes === []) {
            return $this->scopeCache[$key] = [
                'nodes' => [], 'edges' => [], 'chapters' => [],
                'requires' => [], 'unlocked_by' => [], 'depth' => [],
                'roots' => [], 'cycle_nodes' => [], 'isolated' => 0,
                'requires_count' => 0, 'cross_count' => 0, 'draft_edges' => 0,
                'with_content' => 0, 'with_questions' => 0,
            ];
        }

        [$edges, $requires, $unlockedBy, $counts] = $this->readEdges($standardId, $subjectId, $tenant, $nodes);

        $cycleNodes = $this->cycleNodes($requires);
        $depth = $this->depths($requires, $cycleNodes);

        $chapters = [];

        foreach ($nodes as $n) {
            $cid = $n['chapter_id'];

            if ($cid === null) {
                continue;
            }

            $chapters[$cid] ??= [
                'id'       => $cid,
                'name'     => $n['chapter'],
                'order'    => $n['chapter_order'],
                'concepts' => 0,
            ];
            $chapters[$cid]['concepts']++;
        }

        uasort($chapters, fn ($a, $b) => ($a['order'] ?? 999) <=> ($b['order'] ?? 999));

        $roots = [];
        $isolated = 0;

        foreach ($nodes as $id => $_) {
            $hasPrereq = ($requires[$id] ?? []) !== [];
            $hasPost = ($unlockedBy[$id] ?? []) !== [];

            if (! $hasPrereq) {
                $roots[] = $id;
            }

            if (! $hasPrereq && ! $hasPost) {
                $isolated++;
            }
        }

        return $this->scopeCache[$key] = [
            'nodes'          => $nodes,
            'edges'          => $edges,
            'chapters'       => $chapters,
            'requires'       => $requires,
            'unlocked_by'    => $unlockedBy,
            'depth'          => $depth,
            'roots'          => $roots,
            'cycle_nodes'    => $cycleNodes,
            'isolated'       => $isolated,
            'requires_count' => $counts['requires'],
            'cross_count'    => $counts['cross'],
            'draft_edges'    => $counts['draft'],
            'with_content'   => count(array_filter($nodes, fn ($n) => $n['content_n'] > 0)),
            'with_questions' => count(array_filter($nodes, fn ($n) => $n['question_n'] > 0)),
        ];
    }

    /**
     * @return array<int, array<string, mixed>> keyed by conceptId
     */
    private function readNodes(int $standardId, int $subjectId, ?int $tenant): array
    {
        // standard_id / subject_id are stored as INTEGER on some nodes and
        // STRING on others (two key conventions, measured live), so both sides
        // are pushed through toInteger rather than compared raw. Comparing raw
        // matches roughly half the scope and looks like missing data.
        $cypher = 'MATCH (c:Concept) '
            . 'WHERE toInteger(c.standard_id) = $standardId '
            . '  AND toInteger(c.subject_id) = $subjectId '
            . '  AND ($tenant IS NULL OR c.sub_institute_id = $tenant) '
            . 'OPTIONAL MATCH (ch:Chapter)-[:HAS_CONCEPT]->(c) '
            . 'OPTIONAL MATCH (ct:Content)-[:TEACHES]->(c) '
            . 'OPTIONAL MATCH (q:Question)-[:ASSESSES]->(c) '
            . 'RETURN c.conceptId AS id, c.name AS name, c.description AS description, '
            . '       c.concept_code AS code, c.chapter_id AS chapterId, '
            . '       ch.chapter_name AS chapter, toInteger(ch.sort_order) AS chapterOrder, '
            . '       toInteger(c.standard_id) AS standardId, toInteger(c.subject_id) AS subjectId, '
            . '       c.bloom_ceiling AS bloom, c.priority_score AS priority, '
            . '       c.mastery_gate AS gate, c.est_minutes AS minutes, '
            . '       c.quality_status AS status, '
            . '       count(DISTINCT ct) AS contentN, count(DISTINCT q) AS questionN '
            . 'ORDER BY chapterOrder, id';

        $nodes = [];

        foreach ($this->neo4j->run($cypher, [
            'standardId' => $standardId,
            'subjectId'  => $subjectId,
            'tenant'     => $tenant,
        ]) as $r) {
            $id = (int) $r->get('id');

            $nodes[$id] = [
                'name'          => $this->text($r->get('name')),
                'description'   => $this->text($r->get('description')),
                'code'          => $this->text($r->get('code')),
                'chapter_id'    => $r->get('chapterId') === null ? null : (int) $r->get('chapterId'),
                'chapter'       => $this->text($r->get('chapter')) ?: null,
                'chapter_order' => $r->get('chapterOrder') === null ? null : (int) $r->get('chapterOrder'),
                'standard_id'   => (int) $r->get('standardId'),
                'subject_id'    => (int) $r->get('subjectId'),
                'bloom'         => $this->text($r->get('bloom')) ?: null,
                'priority'      => $this->intOrNull($r->get('priority')),
                'gate'          => $r->get('gate') === null ? self::GATE_FLOOR : (float) $r->get('gate'),
                'minutes'       => $this->intOrNull($r->get('minutes')),
                'status'        => $this->text($r->get('status')) ?: 'draft',
                'content_n'     => (int) $r->get('contentN'),
                'question_n'    => (int) $r->get('questionN'),
            ];
        }

        return $nodes;
    }

    /**
     * Read REQUIRES and CROSS_LINKS, and build both adjacency directions.
     *
     * DIRECTION, stated once so nothing downstream has to guess. The projection
     * writes `(later)-[:REQUIRES]->(earlier)` - the arrow points BACKWARDS in
     * time, at the prerequisite, because that is the direction a recommender
     * walks when it asks "what is underneath this?".
     *
     *   $requires[later]   = [earlier, ...]   what `later` depends on
     *   $unlockedBy[earlier] = [later, ...]   what mastering `earlier` opens up
     *
     * The emitted edge list is flipped to source=earlier, target=later so a
     * client draws left-to-right / top-to-bottom in teaching order without
     * having to know any of this.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<int, int>>, 2: array<int, array<int, int>>, 3: array<string, int>}
     */
    private function readEdges(int $standardId, int $subjectId, ?int $tenant, array $nodes): array
    {
        $cypher = 'MATCH (a:Concept)-[r:REQUIRES|CROSS_LINKS]->(b:Concept) '
            . 'WHERE toInteger(a.standard_id) = $standardId '
            . '  AND toInteger(a.subject_id) = $subjectId '
            . '  AND ($tenant IS NULL OR a.sub_institute_id = $tenant) '
            . 'RETURN a.conceptId AS a, b.conceptId AS b, type(r) AS kind, '
            . '       r.link_type AS linkType, r.mastery_gate AS gate, '
            . '       r.quality_status AS status, r.tagged_by AS taggedBy';

        $edges = [];
        $requires = [];
        $unlockedBy = [];
        $counts = ['requires' => 0, 'cross' => 0, 'draft' => 0];

        foreach ($this->neo4j->run($cypher, [
            'standardId' => $standardId,
            'subjectId'  => $subjectId,
            'tenant'     => $tenant,
        ]) as $r) {
            $a = (int) $r->get('a');
            $b = (int) $r->get('b');
            $kind = (string) $r->get('kind');

            // Both endpoints must be inside the scope we drew. An edge to a
            // concept that is not on the canvas is a dangling reference, and
            // rendering it produces a node the user cannot click.
            if (! isset($nodes[$a], $nodes[$b]) || $a === $b) {
                continue;
            }

            $status = $this->text($r->get('status')) ?: 'draft';

            if ($status !== 'approved') {
                $counts['draft']++;
            }

            if ($kind === 'REQUIRES') {
                $counts['requires']++;
                $requires[$a][$b] = $b;
                $unlockedBy[$b][$a] = $a;

                $source = $b;   // the prerequisite - earlier
                $target = $a;   // the dependent   - later
            } else {
                $counts['cross']++;

                // CROSS_LINKS is projected source->target already and is NOT a
                // dependency: it must never reach $requires, or a lateral
                // "related to" link would block a concept.
                $source = $a;
                $target = $b;
            }

            $edges[] = [
                'source'    => $source,
                'target'    => $target,
                'kind'      => $kind,
                'link_type' => $this->text($r->get('linkType')) ?: null,
                'gate'      => $r->get('gate') === null ? null : (float) $r->get('gate'),
                'status'    => $status,
                'tagged_by' => $this->text($r->get('taggedBy')) ?: 'human',
            ];
        }

        return [$edges, $requires, $unlockedBy, $counts];
    }

    // ══════════════════════════════════════════════════════════════════
    // Structure, derived in PHP
    // ══════════════════════════════════════════════════════════════════

    /**
     * Every node that lies on a REQUIRES cycle.
     *
     * Iterative colour-marking DFS - white/grey/black - rather than recursion,
     * because a pathological chain should not be able to blow the PHP stack.
     * A grey node reached again is a back edge, and everything currently on the
     * stack from that node onwards is on the ring.
     *
     * @param  array<int, array<int, int>>  $requires
     * @return array<int, int>
     */
    private function cycleNodes(array $requires): array
    {
        $state = [];      // 0 unvisited, 1 on stack, 2 done
        $onCycle = [];

        foreach (array_keys($requires) as $start) {
            if (($state[$start] ?? 0) !== 0) {
                continue;
            }

            // Each frame: [node, remaining neighbours]
            $stack = [[$start, array_values($requires[$start] ?? [])]];
            $path = [$start];
            $state[$start] = 1;

            while ($stack !== []) {
                $top = count($stack) - 1;

                if ($stack[$top][1] === []) {
                    $state[$stack[$top][0]] = 2;
                    array_pop($stack);
                    array_pop($path);

                    continue;
                }

                $next = array_shift($stack[$top][1]);
                $s = $state[$next] ?? 0;

                if ($s === 1) {
                    // Back edge: mark the ring from $next to the top of $path.
                    $from = array_search($next, $path, true);

                    if ($from !== false) {
                        foreach (array_slice($path, $from) as $n) {
                            $onCycle[$n] = $n;
                        }
                    }

                    continue;
                }

                if ($s === 2) {
                    continue;
                }

                $state[$next] = 1;
                $path[] = $next;
                $stack[] = [$next, array_values($requires[$next] ?? [])];
            }
        }

        return array_values($onCycle);
    }

    /**
     * Longest prerequisite chain beneath each concept - the layout's y axis.
     *
     * Memoised depth-first, with cycle nodes pinned to their shallowest
     * possible value: a node on a ring has no well-defined depth, and letting
     * the walk chase it produces a different answer depending on where the
     * traversal started. Pinning keeps the layout stable across requests, which
     * matters more than being philosophically right about a cycle.
     *
     * @param  array<int, array<int, int>>  $requires
     * @param  array<int, int>  $cycleNodes
     * @return array<int, int>
     */
    private function depths(array $requires, array $cycleNodes): array
    {
        $onCycle = array_flip($cycleNodes);
        $depth = [];

        $resolve = function (int $node, array $seen) use (&$resolve, $requires, $onCycle, &$depth): int {
            if (isset($depth[$node])) {
                return $depth[$node];
            }

            if (isset($seen[$node]) || count($seen) > self::DEPTH_CAP) {
                return 0;
            }

            $seen[$node] = true;
            $best = 0;

            foreach ($requires[$node] ?? [] as $prereq) {
                if (isset($onCycle[$prereq]) && isset($onCycle[$node])) {
                    // Same ring: do not descend, or the two nodes define each
                    // other's depth and the answer depends on traversal order.
                    continue;
                }

                $best = max($best, 1 + $resolve($prereq, $seen));
            }

            return $depth[$node] = $best;
        };

        foreach (array_keys($requires) as $node) {
            $resolve($node, []);
        }

        return $depth;
    }

    /**
     * Transitive closure of one node over an adjacency map, cycle-safe.
     *
     * @param  array<int, array<int, int>>  $adj
     * @return array<int, int>
     */
    private function walk(int $from, array $adj): array
    {
        $seen = [];
        $queue = array_values($adj[$from] ?? []);

        while ($queue !== []) {
            $n = array_pop($queue);

            if (isset($seen[$n]) || $n === $from) {
                continue;
            }

            $seen[$n] = $n;

            foreach ($adj[$n] ?? [] as $next) {
                if (! isset($seen[$next])) {
                    $queue[] = $next;
                }
            }
        }

        return $seen;
    }

    /**
     * Closure of one node WITH the shortest distance to each member - breadth
     * first, so `depth` means "how far back" and not "how the DFS happened to
     * arrive".
     *
     * @param  array<int, array<int, int>>  $adj
     * @return array<int, int> node => distance
     */
    private function walkWithDepth(int $from, array $adj): array
    {
        $dist = [];
        $frontier = array_values($adj[$from] ?? []);
        $d = 1;

        while ($frontier !== [] && $d <= self::DEPTH_CAP) {
            $next = [];

            foreach ($frontier as $n) {
                if ($n === $from || isset($dist[$n])) {
                    continue;
                }

                $dist[$n] = $d;

                foreach ($adj[$n] ?? [] as $further) {
                    if (! isset($dist[$further]) && $further !== $from) {
                        $next[] = $further;
                    }
                }
            }

            $frontier = $next;
            $d++;
        }

        return $dist;
    }

    // ══════════════════════════════════════════════════════════════════
    // Mastery + tenancy
    // ══════════════════════════════════════════════════════════════════

    /**
     * One learner's mastery over a set of concepts.
     *
     * Read from MariaDB, not from (:StuDetail)-[:HAS_MASTERY]->(:Concept), and
     * deliberately: `pal_concept_mastery` is the system of record and the graph
     * edge is a projection of it that can lag by up to one drain cycle. A map
     * that shows a learner as blocked because the projection has not caught up
     * yet is worse than one round trip to SQL.
     *
     * @param  array<int, int>  $conceptIds
     * @return array<int, array{p: float, attempts: int, band: string}>
     */
    private function masteryFor(int $learnerId, array $conceptIds): array
    {
        if ($conceptIds === []) {
            return [];
        }

        $rows = DB::table('pal_concept_mastery')
            ->where('learner_id', $learnerId)
            ->whereIn('concept_ref_id', $conceptIds)
            ->get(['concept_ref_id', 'p_mastery', 'attempts', 'band']);

        $out = [];

        foreach ($rows as $r) {
            $out[(int) $r->concept_ref_id] = [
                'p'        => (float) $r->p_mastery,
                'attempts' => (int) $r->attempts,
                'band'     => (string) ($r->band ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Resolve the institute that owns a standard + subject.
     *
     * Called only when a caller passed no tenant. Returns null when the pair
     * spans more than one institute, which in this schema does not happen - but
     * guessing would be worse than not filtering, and the caller that cares
     * (the web controller) always passes its own tenant explicitly.
     */
    private function tenantOf(int $standardId, int $subjectId): ?int
    {
        $ids = DB::table('lms_concept')
            ->where('standard_id', $standardId)
            ->where('subject_id', $subjectId)
            ->distinct()
            ->pluck('sub_institute_id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    private function text($value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function intOrNull($value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }
}
