<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wires the Engagement module's live, correctly-named edge into the
 * decentralized Knowledge Graph, part of the "Context Graph - Whole LMS
 * Integration" task.
 *
 * SCOPE, DELIBERATELY NARROW: only `student -[engaged_with]-> learning_concept`
 * and `student -[engaged_with]-> content` are wired here, reusing the existing
 * `student`/`learning_concept`/`content` entities (no new entity needed) and
 * the already-live `(:StuDetail)-[:ENGAGED_WITH]->(:Concept|:TeachingResource)`
 * edge (EngagementGraphProjection, Pipeline A). No new sync work.
 *
 * `ATTENDED` and `HAS_INCIDENT` are explicitly NOT touched here, by decision:
 *  - `ATTENDED` ((:Student)-[:ATTENDED]->(:Lesson)) is a one-time Pipeline-C
 *    cross-join — every student in a standard x every lesson in that standard —
 *    not derived from any attendance table. It does not mean "was present."
 *  - `HAS_INCIDENT` ((:Student)-[:HAS_INCIDENT]->(:DisciplineCategory)) is a
 *    disciplinary-record edge, not an engagement/participation signal.
 * Wiring either into the ontology layer under an "engagement" view would
 * misrepresent what the data actually is. Real attendance already has its own
 * correct ontology entity (`attendance_record`, source `attendance_student`) —
 * reuse that for any attendance-adjacent context, don't duplicate it here.
 * Fixing the ATTENDED/HAS_INCIDENT naming problem (which would require a new
 * live attendance_student -> Neo4j projection) is left to a dedicated
 * follow-up, by explicit decision for this pass.
 *
 * Two edges share the relation name `engaged_with` toward two different target
 * entities, so this needs two separate views (same pattern as the existing
 * `curriculum-structure` / `curriculum-structure-resources` split) rather than
 * one view with an ambiguous single target.
 *
 * Requires GraphQueryService's `FROM_LABEL_OVERRIDE['student']['ENGAGED_WITH']
 * = 'StuDetail'` fix (landed separately) — without it this edge would silently
 * resolve to the wrong Neo4j node and report permanent-empty engagement data.
 *
 * Idempotent, baseline-only (sub_institute_id IS NULL): safe to re-run.
 */
return new class extends Migration
{
    private const MODULE_KEY = 'engagement';

    private function relationships(): array
    {
        return [
            ['student', 'engaged_with', 'learning_concept', 'cardinality' => 'many_to_many', 'in_graph' => true, 'graph_relationship_type' => 'ENGAGED_WITH', 'description' => 'lms_student_engagement.activity_id means a different target table per activity_type — no honest single-join SQL fallback exists (the same reason EngagementGraphProjection is bespoke PHP), graph-only.'],
            ['student', 'engaged_with', 'content', 'cardinality' => 'many_to_many', 'in_graph' => true, 'graph_relationship_type' => 'ENGAGED_WITH', 'description' => 'Same edge type as above, different target entity — see curriculum_id docblock for why this needs its own view.'],
        ];
    }

    private function views(): array
    {
        return [
            [
                'view_key' => 'engagement-concepts',
                'label' => 'Concepts engaged with',
                'module_key' => self::MODULE_KEY,
                'description' => 'Concepts this student has engaged with via practice or quiz activity.',
                'root_entity_key' => 'student',
                'path' => [
                    ['relation' => 'engaged_with', 'entity' => 'learning_concept', 'label' => 'Concepts'],
                ],
                'max_per_hop' => 25,
                'sort_order' => 10,
            ],
            [
                'view_key' => 'engagement-resources',
                'label' => 'Resources engaged with',
                'module_key' => self::MODULE_KEY,
                'description' => 'Teaching resources this student has viewed.',
                'root_entity_key' => 'student',
                'path' => [
                    ['relation' => 'engaged_with', 'entity' => 'content', 'label' => 'Resources'],
                ],
                'max_per_hop' => 25,
                'sort_order' => 20,
            ],
        ];
    }

    public function up(): void
    {
        $now = now();

        if (Schema::hasTable('ontology_relationships')) {
            foreach ($this->relationships() as $relationship) {
                [$from, $relation, $to] = $relationship;
                $key = sprintf('%s_%s_%s', $from, $relation, $to);

                $payload = [
                    'from_entity_key' => $from,
                    'relation' => $relation,
                    'to_entity_key' => $to,
                    'cardinality' => $relationship['cardinality'] ?? 'one_to_many',
                    'description' => $relationship['description'] ?? null,
                    'from_column' => $relationship['from_column'] ?? null,
                    'join_table' => $relationship['join_table'] ?? null,
                    'join_from_column' => $relationship['join_from_column'] ?? null,
                    'join_to_column' => $relationship['join_to_column'] ?? null,
                    'to_column' => $relationship['to_column'] ?? null,
                    'graph_relationship_type' => $relationship['graph_relationship_type'] ?? null,
                    'in_graph' => $relationship['in_graph'] ?? false,
                    'traversable' => $relationship['traversable'] ?? true,
                    'traversal_cost' => $relationship['traversal_cost'] ?? (isset($relationship['join_table']) ? 2 : 1),
                    'attributes' => json_encode($relationship['attributes'] ?? []),
                    'status' => 1,
                    'updated_at' => $now,
                ];

                $existing = DB::table('ontology_relationships')
                    ->where('relationship_key', $key)
                    ->whereNull('sub_institute_id')
                    ->first();

                if ($existing) {
                    DB::table('ontology_relationships')->where('id', $existing->id)->update($payload);

                    continue;
                }

                DB::table('ontology_relationships')->insert($payload + [
                    'relationship_key' => $key,
                    'sub_institute_id' => null,
                    'client_id' => null,
                    'created_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('ai_ontology_views')) {
            foreach ($this->views() as $view) {
                $payload = [
                    'label' => $view['label'],
                    'module_key' => $view['module_key'],
                    'description' => $view['description'],
                    'root_entity_key' => $view['root_entity_key'],
                    'path' => json_encode($view['path']),
                    'max_per_hop' => $view['max_per_hop'],
                    'allowed_roles' => null,
                    'sort_order' => $view['sort_order'],
                    'status' => 1,
                    'updated_at' => $now,
                ];

                $existing = DB::table('ai_ontology_views')
                    ->where('view_key', $view['view_key'])
                    ->whereNull('sub_institute_id')
                    ->first();

                if ($existing) {
                    DB::table('ai_ontology_views')->where('id', $existing->id)->update($payload);

                    continue;
                }

                DB::table('ai_ontology_views')->insert($payload + [
                    'view_key' => $view['view_key'],
                    'sub_institute_id' => null,
                    'client_id' => null,
                    'created_at' => $now,
                ]);
            }
        }

        $this->setModuleOntologyCapability(true);
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_ontology_views')) {
            DB::table('ai_ontology_views')
                ->whereIn('view_key', array_column($this->views(), 'view_key'))
                ->whereNull('sub_institute_id')
                ->delete();
        }

        if (Schema::hasTable('ontology_relationships')) {
            foreach ($this->relationships() as $relationship) {
                [$from, $relation, $to] = $relationship;
                DB::table('ontology_relationships')
                    ->where('relationship_key', sprintf('%s_%s_%s', $from, $relation, $to))
                    ->whereNull('sub_institute_id')
                    ->delete();
            }
        }

        $this->setModuleOntologyCapability(false);
    }

    private function setModuleOntologyCapability(bool $enabled): void
    {
        if (! Schema::hasTable('ai_modules')) {
            return;
        }

        $row = DB::table('ai_modules')->where('module_key', self::MODULE_KEY)->whereNull('sub_institute_id')->first();

        if (! $row) {
            return;
        }

        $current = $row->capabilities ? json_decode((string) $row->capabilities, true) : [];
        $current = is_array($current) ? $current : [];
        $merged = array_merge($current, ['ontology' => $enabled]);

        DB::table('ai_modules')->where('id', $row->id)->update([
            'capabilities' => json_encode($merged),
            'updated_at' => now(),
        ]);
    }
};
