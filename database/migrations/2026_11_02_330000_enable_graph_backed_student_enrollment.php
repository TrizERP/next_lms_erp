<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the Knowledge Graph's "student-learning" view pull a student's class
 * from Neo4j rather than always from SQL — the one hop in that view verified
 * live to have real, substantial graph coverage (191,414 :Student nodes,
 * 185,988 :ENROLLED_IN edges to :Standard, confirmed by direct Cypher query
 * against bolt://dev.triz.co.in:7688).
 *
 * Deliberately scoped. Two things verified live and NOT done here, by
 * decision:
 *   - :HAS_CHAPTER coverage is patchy (a real subject with 5 chapters in
 *     MariaDB had 0 in Neo4j), so the existing `subject contains chapter`
 *     in_graph flag is left as-is — that risk was already accepted when it
 *     was set, not introduced here.
 *   - GraphQueryService::expandViaGraph() returns an empty (not null) result
 *     when a graph query succeeds with zero rows, so a student/tenant Neo4j
 *     has not synced will show no class here rather than falling back to
 *     SQL. Fixing that is a separate, larger change to a service shared by
 *     the whole AI Workspace/evidence/recommendation system — out of scope
 *     for this migration.
 *
 * WHY A NEW RELATION RATHER THAN EDITING THE EXISTING ONES. The ontology
 * models enrollment as two SQL hops (student -enrolled_in-> enrollment
 * -in_standard-> standard) because that is the real MariaDB shape
 * (tblstudent_enrollment has its own id). Neo4j has no :Enrollment node —
 * student -[:ENROLLED_IN {syear}]-> standard is one direct edge (see
 * docs/neo4j-graph-modules.md's note on academic-year-scoped facts living on
 * the relationship, not a node). Marking the existing two-hop relations
 * in_graph=true would have nothing real to point at; this adds the one-hop
 * relation Neo4j actually has, with its own SQL fallback (a join through
 * tblstudent_enrollment), and leaves `enrolled_in`/`in_standard` exactly as
 * they were for anything else that walks them.
 *
 * `student-learning` (seeded in 2026_08_20_000012_seed_ai_workspace_config.php,
 * already applied) is repointed here rather than editing that migration —
 * editing an already-run migration does not reapply it, and the project's
 * own convention (the 2026_09_21_* follow-ups) is a new migration that
 * updates the seeded row.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addRelationship();
        $this->repointStudentLearningView();
    }

    public function down(): void
    {
        if (Schema::hasTable('ontology_relationships')) {
            DB::table('ontology_relationships')
                ->where('relationship_key', 'student_enrolled_in_standard_standard')
                ->whereNull('sub_institute_id')
                ->delete();
        }

        if (Schema::hasTable('ai_ontology_views')) {
            $view = DB::table('ai_ontology_views')
                ->where('view_key', 'student-learning')
                ->whereNull('sub_institute_id')
                ->first();

            if ($view) {
                DB::table('ai_ontology_views')->where('id', $view->id)->update([
                    'path' => json_encode($this->originalPath()),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function addRelationship(): void
    {
        if (! Schema::hasTable('ontology_relationships')) {
            return;
        }

        $now = now();

        $payload = [
            'from_entity_key' => 'student',
            'relation' => 'enrolled_in_standard',
            'to_entity_key' => 'standard',
            'cardinality' => 'many_to_many',
            'description' => 'The class a student is enrolled in, read from Neo4j where synced (ENROLLED_IN), from tblstudent_enrollment otherwise.',
            'from_column' => 'id',
            'join_table' => 'tblstudent_enrollment',
            'join_from_column' => 'student_id',
            'join_to_column' => 'standard_id',
            'to_column' => 'id',
            'graph_relationship_type' => 'ENROLLED_IN',
            'in_graph' => true,
            'traversable' => true,
            'traversal_cost' => 2,
            'attributes' => json_encode([]),
            'status' => 1,
            'updated_at' => $now,
        ];

        $existing = DB::table('ontology_relationships')
            ->where('relationship_key', 'student_enrolled_in_standard_standard')
            ->whereNull('sub_institute_id')
            ->first();

        if ($existing) {
            DB::table('ontology_relationships')->where('id', $existing->id)->update($payload);

            return;
        }

        DB::table('ontology_relationships')->insert($payload + [
            'relationship_key' => 'student_enrolled_in_standard_standard',
            'sub_institute_id' => null,
            'client_id' => null,
            'created_at' => $now,
        ]);
    }

    private function repointStudentLearningView(): void
    {
        if (! Schema::hasTable('ai_ontology_views')) {
            return;
        }

        $view = DB::table('ai_ontology_views')
            ->where('view_key', 'student-learning')
            ->whereNull('sub_institute_id')
            ->first();

        if (! $view) {
            return;
        }

        DB::table('ai_ontology_views')->where('id', $view->id)->update([
            'path' => json_encode([
                ['relation' => 'enrolled_in_standard', 'entity' => 'standard', 'label' => 'Class'],
                ['relation' => 'teaches', 'entity' => 'subject', 'label' => 'Subjects'],
                ['relation' => 'contains', 'entity' => 'chapter', 'label' => 'Chapters'],
            ]),
            'updated_at' => now(),
        ]);
    }

    /** The path as 2026_08_20_000012_seed_ai_workspace_config.php originally seeded it. */
    private function originalPath(): array
    {
        return [
            ['relation' => 'enrolled_in', 'entity' => 'enrollment', 'label' => 'Enrolment'],
            ['relation' => 'in_standard', 'entity' => 'standard', 'label' => 'Class'],
            ['relation' => 'teaches', 'entity' => 'subject', 'label' => 'Subjects'],
            ['relation' => 'contains', 'entity' => 'chapter', 'label' => 'Chapters'],
        ];
    }
};
