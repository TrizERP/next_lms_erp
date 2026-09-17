<?php

namespace App\Services\PAL\Coherence;

use Illuminate\Support\Facades\DB;

/**
 * The Coherence Map's graph, read straight out of MariaDB.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS DOES NOT GO THROUGH NEO4J
 * ---------------------------------------------------------------------------
 * CoherenceMapRepository already reads a concept graph, but out of the Neo4j
 * projection written by `pal:coherence-sync`, and that projection cannot answer
 * this question for two independent reasons.
 *
 * First, it has no Unit and no Topic. CoherenceGraphProjection writes
 * (:Subject)-[:COVERS_CHAPTER]->(:Chapter)-[:HAS_CONCEPT]->(:Concept) and nothing
 * above or between; `topic_master` has no label anywhere in config/neo4j.php. The
 * map specified here is four levels deep, so two of them simply are not there.
 *
 * Second, the sync is a deployment gap rather than a guarantee - it runs where the
 * command has been scheduled, and a teacher-facing screen that renders blank on a
 * host where it has not is worse than one that reads the tables directly.
 *
 * Nothing is lost by reading MariaDB: the repository fetches the whole edge list
 * and walks it in PHP anyway, because the graph is cyclic. The two share that walk
 * through Concerns\WalksPrerequisiteGraph so they cannot disagree about depth.
 *
 * ---------------------------------------------------------------------------
 * EDGE DIRECTION IS FLIPPED ON THE WAY OUT, DELIBERATELY
 * ---------------------------------------------------------------------------
 * Storage convention (EsoPolicyService.php:960-963): a `pal_concept_relations` row
 * reads "from REQUIRES to" - `from_concept_id` is the concept being learned and
 * `to_concept_id` is its prerequisite.
 *
 * A coherence map is read as a progression, so the arrows must point the way
 * learning travels: prerequisite first, dependent after. Every prerequisite edge
 * emitted here therefore has `source` = the PREREQUISITE and `target` = the
 * DEPENDENT, which is the storage row reversed. The adjacency handed to the
 * traversals keeps the storage direction, because "what must come before this" is
 * what depth means.
 *
 * Get this backwards and nothing errors - the map simply teaches the curriculum in
 * reverse. It is the single easiest thing to break in this file.
 *
 * ---------------------------------------------------------------------------
 * WHAT IT TOLERATES, BECAUSE THE DATA DOES IT
 * ---------------------------------------------------------------------------
 * - Chapters with no unit. `/course-master` creates chapters directly, so `unit_id`
 *   is often null and those chapters are unreachable from lms_curriculum. They are
 *   bucketed under a synthetic unit rather than dropped, because dropping them
 *   would hide real curriculum.
 * - Concepts with no topic. `lms_concept.topic_id` is populated on 1,410 of 2,571
 *   concepts estate-wide but on NONE of subject 3976/42 (measured 2026-09-16).
 *   Those concepts attach directly to their chapter and `topic_level_available`
 *   tells the UI not to promise a level that is not there.
 * - Cycles. 41 reciprocal `requires` pairs exist. They are reported, not repaired.
 */
class CurriculumGraphBuilder
{
    use Concerns\WalksPrerequisiteGraph;

    /** Bucket for chapters whose unit_id is null - see the class docblock. */
    private const UNASSIGNED_UNIT = 'unit:0';

    /**
     * Build the whole map for one subject + grade.
     *
     * @param  array{include_suggested?: bool, include_cross_grade?: bool}  $options
     * @return array{meta: array, nodes: array, edges: array, stats: array}
     */
    public function build(
        int $subInstituteId,
        int $subjectId,
        int $standardId,
        ?int $syear = null,
        array $options = []
    ): array {
        $includeSuggested = $options['include_suggested'] ?? true;
        $includeCrossGrade = $options['include_cross_grade'] ?? true;

        $curriculum = $this->curriculum($subInstituteId, $subjectId, $standardId, $syear);
        $syear = $syear ?? ($curriculum->syear ?? null);

        $chapters = $this->chapters($subInstituteId, $subjectId, $standardId, $syear);

        // An empty chapter list is a real answer, not an error: a subject can exist
        // with no curriculum authored yet. Return the shape with nothing in it so
        // the UI shows an empty state rather than a failure.
        if ($chapters === []) {
            return [
                'meta' => $this->meta($subInstituteId, $subjectId, $standardId, $syear, $curriculum, false),
                'nodes' => [],
                'edges' => [],
                'stats' => $this->emptyStats(),
            ];
        }

        $chapterIds = array_keys($chapters);
        $units = $this->units($curriculum->id ?? null);
        $topics = $this->topics($chapterIds);
        $concepts = $this->concepts($chapterIds);

        $conceptIds = array_keys($concepts);

        [$conceptEdges, $offMapIds] = $this->conceptRelations($subInstituteId, $conceptIds, $includeCrossGrade);
        $nodeEdges = $this->learningRelations($subInstituteId, array_keys($topics), $chapterIds, array_keys($units));

        $offMap = $includeCrossGrade && $offMapIds !== []
            ? $this->offMapConcepts($offMapIds)
            : [];

        $relations = array_merge($conceptEdges, $nodeEdges);

        if (! $includeSuggested) {
            $relations = array_values(array_filter(
                $relations,
                static fn (array $e): bool => $e['status'] === 'approved'
            ));
        }

        // Adjacency in STORAGE direction: requires[node] = its prerequisites.
        $requires = [];

        foreach ($relations as $edge) {
            if ($edge['kind'] !== 'prerequisite') {
                continue;
            }

            $requires[$edge['_dependent']][] = $edge['_prerequisite'];
        }

        $cycleNodes = $this->cycleNodes($requires);
        $depths = $this->depths($requires, $cycleNodes);

        $nodes = $this->assembleNodes(
            $units, $chapters, $topics, $concepts, $offMap, $depths, $cycleNodes, $requires, $relations
        );

        $edges = array_merge(
            $this->hierarchyEdges($nodes),
            $this->presentRelations($relations)
        );

        return [
            'meta' => $this->meta(
                $subInstituteId, $subjectId, $standardId, $syear, $curriculum,
                $this->anyConceptHasTopic($concepts)
            ),
            'nodes' => array_values($nodes),
            'edges' => $edges,
            'stats' => $this->stats($nodes, $relations, $depths, $cycleNodes, $requires),
        ];
    }

    // ══════════════════════════════════════════════════════════════════
    // Reads
    // ══════════════════════════════════════════════════════════════════

    private function curriculum(int $tenant, int $subjectId, int $standardId, ?int $syear): ?object
    {
        $q = DB::table('lms_curriculum')
            ->where('sub_institute_id', $tenant)
            ->where('subject_id', $subjectId)
            ->where('standard_id', $standardId);

        if ($syear !== null) {
            $q->where('syear', $syear);
        }

        // uq_curriculum is (subject_id, standard_id, syear), so without a syear this
        // can match several years. Newest wins - the same rule the curriculum screen
        // uses when a tenant has carried a subject across years.
        return $q->orderByDesc('syear')->orderByDesc('id')->first();
    }

    /**
     * Chapters, keyed by id.
     *
     * Read by (tenant, subject, standard) and NOT through lms_curriculum -> lms_units,
     * because chapters created from /course-master carry no unit_id and would vanish
     * from a unit-driven read.
     *
     * @return array<int, object>
     */
    private function chapters(int $tenant, int $subjectId, int $standardId, ?int $syear): array
    {
        // Columns verified against the live table 2026-09-16. The migrations do not
        // describe this table accurately - create_chapter_master + its add_fields
        // migration declare `planned_periods`, which does not exist here; the real
        // column is `no_of_periods`. Select explicitly so a drift like that fails
        // loudly at the query rather than silently returning nulls.
        $q = DB::table('chapter_master')
            ->select('id', 'chapter_name', 'unit_id', 'sort_order', 'no_of_periods', 'chapter_desc')
            ->where('sub_institute_id', $tenant)
            ->where('subject_id', $subjectId)
            ->where('standard_id', $standardId)
            // show_hide is the chapter's own visibility flag, honoured by every other
            // curriculum read; a chapter hidden there must not surface here.
            ->where(function ($q) {
                $q->whereNull('show_hide')->orWhere('show_hide', '!=', 'hide');
            });

        if ($syear !== null) {
            $q->where('syear', $syear);
        }

        return $q->orderBy('sort_order')->orderBy('id')->get()->keyBy('id')->all();
    }

    /** @return array<int, object> */
    private function units(?int $curriculumId): array
    {
        if ($curriculumId === null) {
            return [];
        }

        return DB::table('lms_units')
            ->select('id', 'unit_number', 'name', 'total_marks', 'planned_periods')
            ->where('curriculum_id', $curriculumId)
            ->orderBy('unit_number')
            ->get()
            ->keyBy('id')
            ->all();
    }

    /** @return array<int, object> */
    private function topics(array $chapterIds): array
    {
        return DB::table('topic_master')
            ->select('id', 'chapter_id', 'name', 'description', 'topic_sort_order', 'main_topic_id')
            ->whereIn('chapter_id', $chapterIds)
            // topic_show_hide is the curriculum screen's own visibility flag; a topic
            // hidden there must not reappear here.
            ->where(function ($q) {
                $q->whereNull('topic_show_hide')->orWhere('topic_show_hide', '!=', 'hide');
            })
            ->orderBy('topic_sort_order')
            ->orderBy('id')
            ->get()
            ->keyBy('id')
            ->all();
    }

    /** @return array<int, object> */
    private function concepts(array $chapterIds): array
    {
        // Columns verified live 2026-09-16. LmsConceptModel::$fillable lists
        // difficulty_level, bloom_level, pedagogy_tag and lesson_id - NONE of which
        // exist on this estate. `learning_pattern` is what the table actually carries.
        // Selecting the fillable list here would throw on every request.
        return DB::table('lms_concept')
            ->select(
                'id', 'chapter_id', 'topic_id', 'name', 'description',
                'learning_pattern', 'mastery_threshold', 'estimated_mastery_minutes'
            )
            ->whereIn('chapter_id', $chapterIds)
            ->orderBy('id')
            ->get()
            ->keyBy('id')
            ->all();
    }

    /**
     * Concept prerequisite edges touching this scope.
     *
     * An edge is in scope if EITHER endpoint is, so a prerequisite sitting in a lower
     * grade is found rather than silently dropped. The out-of-scope endpoints come
     * back as the second return value for off-map resolution.
     *
     * @return array{0: array<int, array>, 1: array<int, int>}
     */
    private function conceptRelations(int $tenant, array $conceptIds, bool $includeCrossGrade): array
    {
        if ($conceptIds === []) {
            return [[], []];
        }

        $inScope = array_flip($conceptIds);

        $rows = DB::table('pal_concept_relations')
            ->whereIn('sub_institute_id', array_unique([$tenant, 0]))
            ->where(function ($q) use ($conceptIds) {
                $q->whereIn('from_concept_id', $conceptIds)
                    ->orWhereIn('to_concept_id', $conceptIds);
            })
            ->get();

        $edges = [];
        $offMap = [];

        foreach ($rows as $r) {
            $dependent = (int) $r->from_concept_id;
            $prerequisite = (int) $r->to_concept_id;

            // A self-edge is never meaningful and would show as a loop on the node.
            if ($dependent === $prerequisite) {
                continue;
            }

            $dependentIn = isset($inScope[$dependent]);
            $prerequisiteIn = isset($inScope[$prerequisite]);

            if (! $dependentIn && ! $prerequisiteIn) {
                continue;
            }

            if (! $dependentIn || ! $prerequisiteIn) {
                if (! $includeCrossGrade) {
                    continue;
                }

                $offMap[] = $dependentIn ? $prerequisite : $dependent;
            }

            $edges[] = [
                'id' => 'concept-rel:'.$r->id,
                'source_table' => 'concept',
                'relation_id' => (int) $r->id,
                '_dependent' => 'concept:'.$dependent,
                '_prerequisite' => 'concept:'.$prerequisite,
                'kind' => $r->relation_type === 'cross_curricular' ? 'cross_curricular' : 'prerequisite',
                'relation_type' => (string) $r->relation_type,
                'status' => $this->status($r->quality_status),
                'tagged_by' => (string) ($r->tagged_by ?: 'human'),
                'link_type' => $r->link_type ?: null,
                'confidence' => null,
                'note' => null,
            ];
        }

        return [$edges, array_values(array_unique($offMap))];
    }

    /**
     * Topic / unit / chapter prerequisite edges.
     *
     * Both endpoints must be in scope: unlike concepts, there is no off-map story for
     * a topic yet, and half an edge would render as an arrow into nothing.
     *
     * @return array<int, array>
     */
    private function learningRelations(int $tenant, array $topicIds, array $chapterIds, array $unitIds): array
    {
        $inScope = [];

        foreach (['topic' => $topicIds, 'chapter' => $chapterIds, 'unit' => $unitIds] as $type => $ids) {
            foreach ($ids as $id) {
                $inScope[$type.':'.$id] = true;
            }
        }

        if ($inScope === []) {
            return [];
        }

        $rows = DB::table('pal_learning_relations')
            ->whereIn('sub_institute_id', array_unique([$tenant, 0]))
            ->where('quality_status', '!=', 'rejected')
            ->get();

        $edges = [];

        foreach ($rows as $r) {
            $dependent = $r->from_node_type.':'.$r->from_node_id;
            $prerequisite = $r->to_node_type.':'.$r->to_node_id;

            if ($dependent === $prerequisite) {
                continue;
            }

            if (! isset($inScope[$dependent]) || ! isset($inScope[$prerequisite])) {
                continue;
            }

            $edges[] = [
                'id' => 'learning-rel:'.$r->id,
                'source_table' => 'learning',
                'relation_id' => (int) $r->id,
                '_dependent' => $dependent,
                '_prerequisite' => $prerequisite,
                'kind' => $r->relation_type === 'cross_curricular' ? 'cross_curricular' : 'prerequisite',
                'relation_type' => (string) $r->relation_type,
                'status' => $this->status($r->quality_status),
                'tagged_by' => (string) ($r->tagged_by ?: 'human'),
                'link_type' => null,
                'confidence' => $r->confidence === null ? null : (float) $r->confidence,
                'note' => $r->note ?: null,
            ];
        }

        return $edges;
    }

    /**
     * The concepts an in-scope edge points at that live outside this subject+grade.
     *
     * Carries enough to label them ("Grade 9 - Science") so a teacher can see the
     * progression leaves this course without having to open another one.
     *
     * @return array<int, object>
     */
    private function offMapConcepts(array $conceptIds): array
    {
        // sub_std_map is joined on the PAIR, not on subject_id alone: the same subject
        // id appears once per grade, so joining on one column would multiply each
        // off-map concept by the number of grades that teach it.
        return DB::table('lms_concept as c')
            ->leftJoin('chapter_master as ch', 'ch.id', '=', 'c.chapter_id')
            ->leftJoin('standard as s', 's.id', '=', 'c.standard_id')
            ->leftJoin('sub_std_map as m', function ($join) {
                $join->on('m.subject_id', '=', 'c.subject_id')
                    ->on('m.standard_id', '=', 'c.standard_id')
                    ->on('m.sub_institute_id', '=', 'c.sub_institute_id');
            })
            ->whereIn('c.id', $conceptIds)
            ->select(
                'c.id', 'c.name', 'c.chapter_id', 'c.standard_id', 'c.subject_id',
                'ch.chapter_name',
                DB::raw('s.name as standard_name'),
                DB::raw('m.display_name as subject_name')
            )
            ->groupBy('c.id')
            ->get()
            ->keyBy('id')
            ->all();
    }

    // ══════════════════════════════════════════════════════════════════
    // Assembly
    // ══════════════════════════════════════════════════════════════════

    /**
     * One flat node list, parented by ref.
     *
     * Flat rather than nested because the client renders a graph, not a tree: a node
     * needs a parent for grouping AND arbitrary edges to nodes in other branches.
     *
     * @return array<string, array>
     */
    private function assembleNodes(
        array $units, array $chapters, array $topics, array $concepts, array $offMap,
        array $depths, array $cycleNodes, array $requires, array $relations
    ): array {
        $onCycle = array_flip($cycleNodes);
        [$prereqCount, $dependentCount] = $this->degrees($relations);

        $nodes = [];

        // Only emit units that actually hold a chapter. lms_units is authored per
        // curriculum and routinely lists units this subject never uses.
        $usedUnits = [];

        foreach ($chapters as $ch) {
            $usedUnits[$ch->unit_id ? 'unit:'.$ch->unit_id : self::UNASSIGNED_UNIT] = true;
        }

        foreach ($units as $u) {
            $ref = 'unit:'.$u->id;

            if (! isset($usedUnits[$ref])) {
                continue;
            }

            $nodes[$ref] = $this->node($ref, 'unit', (int) $u->id, (string) $u->name, null, (int) $u->unit_number, [
                'total_marks' => $u->total_marks,
                'planned_periods' => $u->planned_periods,
            ]);
        }

        if (isset($usedUnits[self::UNASSIGNED_UNIT])) {
            // Named for what it means to a teacher, not for the null it came from.
            $nodes[self::UNASSIGNED_UNIT] = $this->node(
                self::UNASSIGNED_UNIT, 'unit', 0, 'Not assigned to a unit', null, 9999,
                ['synthetic' => true]
            );
        }

        foreach ($chapters as $ch) {
            $ref = 'chapter:'.$ch->id;
            $parent = $ch->unit_id && isset($nodes['unit:'.$ch->unit_id])
                ? 'unit:'.$ch->unit_id
                : self::UNASSIGNED_UNIT;

            $nodes[$ref] = $this->node($ref, 'chapter', (int) $ch->id, (string) $ch->chapter_name, $parent, (int) $ch->sort_order, [
                'periods' => $ch->no_of_periods ?: null,
                'description' => $ch->chapter_desc ?: null,
            ]);
        }

        foreach ($topics as $t) {
            $ref = 'topic:'.$t->id;
            $parent = 'chapter:'.$t->chapter_id;

            if (! isset($nodes[$parent])) {
                continue;
            }

            $nodes[$ref] = $this->node($ref, 'topic', (int) $t->id, (string) $t->name, $parent, (int) $t->topic_sort_order, [
                'description' => $t->description ?: null,
            ]);
        }

        foreach ($concepts as $c) {
            $ref = 'concept:'.$c->id;

            // The graceful degradation that makes this work on 3976/42 today: with no
            // topic_id, the concept parents to its chapter instead of disappearing.
            $topicRef = $c->topic_id ? 'topic:'.$c->topic_id : null;
            $parent = $topicRef && isset($nodes[$topicRef]) ? $topicRef : 'chapter:'.$c->chapter_id;

            if (! isset($nodes[$parent])) {
                continue;
            }

            $nodes[$ref] = $this->node($ref, 'concept', (int) $c->id, (string) $c->name, $parent, (int) $c->id, [
                'description' => $c->description ?: null,
                'learning_pattern' => $c->learning_pattern ?: null,
                'mastery_threshold' => $c->mastery_threshold === null ? null : (float) $c->mastery_threshold,
                'estimated_minutes' => $c->estimated_mastery_minutes ?: null,
            ]);
        }

        foreach ($offMap as $c) {
            $ref = 'concept:'.$c->id;

            if (isset($nodes[$ref])) {
                continue;
            }

            $nodes[$ref] = $this->node($ref, 'concept', (int) $c->id, (string) $c->name, null, 0, [
                'chapter_name' => $c->chapter_name ?: null,
                'standard_name' => $c->standard_name ?: null,
                'subject_name' => $c->subject_name ?: null,
            ]);
            $nodes[$ref]['off_map'] = true;
        }

        foreach ($nodes as $ref => &$node) {
            $node['depth'] = $depths[$ref] ?? 0;
            $node['on_cycle'] = isset($onCycle[$ref]);
            $node['prereq_count'] = $prereqCount[$ref] ?? 0;
            $node['dependent_count'] = $dependentCount[$ref] ?? 0;
        }
        unset($node);

        return $nodes;
    }

    /**
     * @return array{0: array<string,int>, 1: array<string,int>}
     */
    private function degrees(array $relations): array
    {
        $prereq = [];
        $dependent = [];

        foreach ($relations as $e) {
            if ($e['kind'] !== 'prerequisite') {
                continue;
            }

            $prereq[$e['_dependent']] = ($prereq[$e['_dependent']] ?? 0) + 1;
            $dependent[$e['_prerequisite']] = ($dependent[$e['_prerequisite']] ?? 0) + 1;
        }

        return [$prereq, $dependent];
    }

    private function node(string $ref, string $type, int $entityId, string $label, ?string $parent, int $order, array $extra): array
    {
        return [
            'id' => $ref,
            'type' => $type,
            'entity_id' => $entityId,
            'label' => trim($label) !== '' ? trim($label) : ucfirst($type).' '.$entityId,
            'parent_id' => $parent,
            'order' => $order,
            'off_map' => false,
            'depth' => 0,
            'on_cycle' => false,
            'prereq_count' => 0,
            'dependent_count' => 0,
            'meta' => array_filter($extra, static fn ($v): bool => $v !== null),
        ];
    }

    /**
     * Containment edges, one per parented node.
     *
     * Emitted alongside the parent_id rather than instead of it: the renderer uses
     * parent_id for grouping and these for the visible connectors, and the two must
     * describe the same tree.
     *
     * @return array<int, array>
     */
    private function hierarchyEdges(array $nodes): array
    {
        $edges = [];

        foreach ($nodes as $ref => $node) {
            if ($node['parent_id'] === null) {
                continue;
            }

            $edges[] = [
                'id' => 'h:'.$node['parent_id'].'>'.$ref,
                'source' => $node['parent_id'],
                'target' => $ref,
                'kind' => 'hierarchy',
                'status' => 'approved',
                'tagged_by' => 'structure',
                'relation_id' => null,
                'source_table' => null,
                'relation_type' => null,
                'link_type' => null,
                'confidence' => null,
                'note' => null,
            ];
        }

        return $edges;
    }

    /**
     * Prerequisite edges, flipped into learning order - see the class docblock.
     *
     * @return array<int, array>
     */
    private function presentRelations(array $relations): array
    {
        $out = [];

        foreach ($relations as $e) {
            $out[] = [
                'id' => $e['id'],
                // source = prerequisite, target = dependent. The storage row is the
                // other way round.
                'source' => $e['_prerequisite'],
                'target' => $e['_dependent'],
                'kind' => $e['kind'],
                'status' => $e['status'],
                'tagged_by' => $e['tagged_by'],
                'relation_id' => $e['relation_id'],
                'source_table' => $e['source_table'],
                'relation_type' => $e['relation_type'],
                'link_type' => $e['link_type'],
                'confidence' => $e['confidence'],
                'note' => $e['note'],
            ];
        }

        return $out;
    }

    // ══════════════════════════════════════════════════════════════════
    // Meta + stats
    // ══════════════════════════════════════════════════════════════════

    private function meta(int $tenant, int $subjectId, int $standardId, ?int $syear, ?object $curriculum, bool $topicLevel): array
    {
        // There is no `subjects` table on this estate: the subject's display name
        // lives on sub_std_map itself, and only `standard` carries a grade name.
        // Same pair of joins ApiLmsCourseController:766 uses.
        $names = DB::table('sub_std_map as m')
            ->leftJoin('standard as st', 'st.id', '=', 'm.standard_id')
            ->where('m.subject_id', $subjectId)
            ->where('m.standard_id', $standardId)
            ->where('m.sub_institute_id', $tenant)
            ->select(DB::raw('m.display_name as subject_name'), DB::raw('st.name as standard_name'))
            ->first();

        return [
            'sub_institute_id' => $tenant,
            'subject_id' => $subjectId,
            'standard_id' => $standardId,
            'subject_name' => $names->subject_name ?? null,
            'standard_name' => $names->standard_name ?? null,
            'syear' => $syear,
            'curriculum_id' => $curriculum->id ?? null,
            'curriculum_name' => $curriculum->curriculum_name ?? null,
            'board' => $curriculum->board ?? null,
            // False means concepts are parented to chapters because no concept in
            // this scope carries a topic_id. The UI must not offer a topic filter it
            // cannot honour.
            'topic_level_available' => $topicLevel,
        ];
    }

    private function anyConceptHasTopic(array $concepts): bool
    {
        foreach ($concepts as $c) {
            if (! empty($c->topic_id)) {
                return true;
            }
        }

        return false;
    }

    private function stats(array $nodes, array $relations, array $depths, array $cycleNodes, array $requires): array
    {
        $byType = ['unit' => 0, 'chapter' => 0, 'topic' => 0, 'concept' => 0];
        $isolated = 0;

        foreach ($nodes as $node) {
            if (isset($byType[$node['type']])) {
                $byType[$node['type']]++;
            }

            if ($node['type'] === 'concept' && $node['prereq_count'] === 0 && $node['dependent_count'] === 0) {
                $isolated++;
            }
        }

        $approved = 0;
        $draft = 0;
        $prerequisite = 0;
        $crossCurricular = 0;

        foreach ($relations as $e) {
            $e['status'] === 'approved' ? $approved++ : $draft++;
            $e['kind'] === 'cross_curricular' ? $crossCurricular++ : $prerequisite++;
        }

        // Roots are concepts nothing precedes - where a teacher can start.
        $roots = 0;

        foreach ($nodes as $ref => $node) {
            if ($node['type'] === 'concept' && ($requires[$ref] ?? []) === []) {
                $roots++;
            }
        }

        return [
            'units' => $byType['unit'],
            'chapters' => $byType['chapter'],
            'topics' => $byType['topic'],
            'concepts' => $byType['concept'],
            'prerequisite_edges' => $prerequisite,
            'cross_curricular_edges' => $crossCurricular,
            'approved' => $approved,
            'draft' => $draft,
            'roots' => $roots,
            'isolated' => $isolated,
            'max_depth' => $depths === [] ? 0 : max($depths),
            'acyclic' => $cycleNodes === [],
            'cycle_nodes' => $cycleNodes,
        ];
    }

    private function emptyStats(): array
    {
        return [
            'units' => 0, 'chapters' => 0, 'topics' => 0, 'concepts' => 0,
            'prerequisite_edges' => 0, 'cross_curricular_edges' => 0,
            'approved' => 0, 'draft' => 0, 'roots' => 0, 'isolated' => 0,
            'max_depth' => 0, 'acyclic' => true, 'cycle_nodes' => [],
        ];
    }

    /**
     * Normalise the stored status into the two states the map renders.
     *
     * Anything that is not explicitly approved is a suggestion, including values a
     * future tagging pass might invent. Erring this way means an unreviewed edge can
     * never be drawn as confirmed curriculum.
     */
    private function status(?string $stored): string
    {
        return $stored === 'approved' ? 'approved' : 'draft';
    }
}
