<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the `topic` and `content` ontology entities and two supplementary
 * Teach/Learn views (`curriculum-structure-topics`, `curriculum-structure-resources`),
 * found missing during the 2026-11-25 Teach/Learn SQL<->Neo4j sync audit
 * (docs/knowledge-graph-sync-coverage.md).
 *
 * Both labels are real and live in Neo4j already - `Topic` via
 * CoherenceGraphProjection::projectTopics() (Pipeline B, chapter_master join),
 * `TeachingResource`/Content via Pipeline A's `content_master` trigger - this
 * migration only registers them in the ontology layer the decentralized
 * Knowledge Graph tab reads, it does not create or change any sync behaviour.
 *
 * Deliberately additive: does NOT touch the existing `curriculum-structure`
 * view (subject->chapter->learning_concept) or its two relationship rows -
 * that view is confirmed live and working, and topic_master's own
 * 83.9%-dangling chapter_id rate (see projectTopics()'s docblock) would make
 * it a *worse* primary path, not a better one. These are separate, named
 * views instead, exactly how the three `student` views and three `fee-*`
 * views already coexist (per 2026_11_19_100000's own comment).
 *
 * Idempotent, baseline-only (sub_institute_id IS NULL): safe to re-run.
 */
return new class extends Migration
{
    private function entities(): array
    {
        return [
            [
                'entity_key' => 'topic', 'label' => 'Topic',
                'domain' => 'k12', 'category' => 'academic', 'sort_order' => 115,
                'description' => 'A topic within a chapter (topic_master). 83.9% of rows resolve to a live chapter - the rest are a pre-existing data-quality gap, not a sync defect.',
                'source_table' => 'topic_master', 'primary_key_column' => 'id',
                'label_column' => 'name', 'tenant_column' => 'sub_institute_id',
                'attributes' => [
                    ['key' => 'name', 'column' => 'name', 'type' => 'string', 'searchable' => true],
                    ['key' => 'chapter_id', 'column' => 'chapter_id', 'type' => 'integer'],
                ],
            ],
            [
                'entity_key' => 'content', 'label' => 'Content',
                'domain' => 'k12', 'category' => 'academic', 'sort_order' => 125,
                'description' => 'A teaching resource attached to a chapter (content_master). Live in Neo4j as :TeachingResource.',
                'source_table' => 'content_master', 'primary_key_column' => 'id',
                'label_column' => 'title', 'tenant_column' => 'sub_institute_id',
                'attributes' => [
                    ['key' => 'title', 'column' => 'title', 'type' => 'string', 'searchable' => true],
                    ['key' => 'chapter_id', 'column' => 'chapter_id', 'type' => 'integer'],
                ],
            ],
        ];
    }

    private function relationships(): array
    {
        return [
            ['chapter', 'contains', 'topic', 'from_column' => 'id', 'to_column' => 'chapter_id', 'cardinality' => 'one_to_many', 'in_graph' => true, 'graph_relationship_type' => 'HAS_TOPIC'],
            ['topic', 'contains', 'learning_concept', 'from_column' => 'id', 'to_column' => 'topic_id', 'cardinality' => 'one_to_many', 'in_graph' => true, 'graph_relationship_type' => 'HAS_CONCEPT'],
            ['chapter', 'has', 'content', 'from_column' => 'id', 'to_column' => 'chapter_id', 'cardinality' => 'one_to_many', 'in_graph' => true, 'graph_relationship_type' => 'HAS_CONTENT'],
        ];
    }

    private function views(): array
    {
        return [
            [
                'view_key' => 'curriculum-structure-topics',
                'label' => 'Curriculum structure (by topic)',
                'module_key' => 'teach_learn',
                'description' => 'The topics and concepts that make up this subject, grouped by topic.',
                'root_entity_key' => 'subject',
                'path' => [
                    ['relation' => 'contains', 'entity' => 'chapter', 'label' => 'Chapters'],
                    ['relation' => 'contains', 'entity' => 'topic', 'label' => 'Topics'],
                    ['relation' => 'contains', 'entity' => 'learning_concept', 'label' => 'Concepts'],
                ],
                'max_per_hop' => 15,
                'sort_order' => 20,
            ],
            [
                'view_key' => 'curriculum-structure-resources',
                'label' => 'Curriculum structure (teaching resources)',
                'module_key' => 'teach_learn',
                'description' => 'The teaching resources attached to each chapter in this subject.',
                'root_entity_key' => 'subject',
                'path' => [
                    ['relation' => 'contains', 'entity' => 'chapter', 'label' => 'Chapters'],
                    ['relation' => 'has', 'entity' => 'content', 'label' => 'Teaching resources'],
                ],
                'max_per_hop' => 15,
                'sort_order' => 30,
            ],
        ];
    }

    public function up(): void
    {
        $now = now();

        if (Schema::hasTable('ontology_entities')) {
            foreach ($this->entities() as $entity) {
                $payload = [
                    'label' => $entity['label'],
                    'domain' => $entity['domain'],
                    'category' => $entity['category'],
                    'description' => $entity['description'] ?? null,
                    'source_table' => $entity['source_table'] ?? null,
                    'primary_key_column' => $entity['primary_key_column'] ?? 'id',
                    'label_column' => $entity['label_column'] ?? null,
                    'tenant_column' => $entity['tenant_column'] ?? null,
                    'client_column' => $entity['client_column'] ?? null,
                    'academic_year_column' => $entity['academic_year_column'] ?? null,
                    'attributes' => json_encode($entity['attributes'] ?? []),
                    'is_virtual' => $entity['is_virtual'] ?? false,
                    'is_tenant_scoped' => $entity['is_tenant_scoped'] ?? true,
                    'sort_order' => $entity['sort_order'] ?? 0,
                    'status' => 1,
                    'updated_at' => $now,
                ];

                $existing = DB::table('ontology_entities')
                    ->where('entity_key', $entity['entity_key'])
                    ->whereNull('sub_institute_id')
                    ->first();

                if ($existing) {
                    DB::table('ontology_entities')->where('id', $existing->id)->update($payload);

                    continue;
                }

                DB::table('ontology_entities')->insert($payload + [
                    'entity_key' => $entity['entity_key'],
                    'sub_institute_id' => null,
                    'client_id' => null,
                    'created_at' => $now,
                ]);
            }
        }

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

        if (Schema::hasTable('ontology_entities')) {
            DB::table('ontology_entities')
                ->whereIn('entity_key', array_column($this->entities(), 'entity_key'))
                ->whereNull('sub_institute_id')
                ->delete();
        }
    }
};
