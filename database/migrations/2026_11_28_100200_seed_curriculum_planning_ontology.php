<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wires the Curriculum Planning module into the decentralized Knowledge Graph
 * (ontology_entities / ontology_relationships / ai_ontology_views), part of the
 * "Context Graph - Whole LMS Integration" task.
 *
 * The Neo4j data is ALREADY LIVE (config/neo4j.php's `lms_curriculum` and
 * `lms_units`/`chapter_master` specs) — this migration only registers the
 * ontology mapping, it does not change sync behaviour:
 *
 *   Curriculum -[HAS_UNIT]-> Unit -[HAS_CHAPTER]-> Chapter
 *   Subject -[BELONGS_TO_CURRICULUM]- Curriculum  (for discoverability from a subject)
 *
 * `unit -> has_chapter -> chapter` is sourced from `chapter_master.unit_id` — the
 * EXTRACTED link. `lms_units.unit_chapters` (the DECLARED JSON list of chapter
 * names) is deliberately NOT mapped into the graph: per
 * CurriculumPlanningApiController/lmsCurriculumController, the two disagree for
 * 50 of 60 units, and chapter_master.unit_id is the only authoritative join —
 * mapping the declared list would silently misrepresent the graph.
 *
 * `lms_units` carries no `sub_institute_id` of its own (confirmed against its
 * create-table migration) — it is reached only through `curriculum`, hence
 * `is_tenant_scoped=false`.
 *
 * Also resolves a dangling reference: `2026_10_31_130200_register_curriculum_
 * planning_agent_and_workflow.php` already names `curriculum`/`unit` in its
 * `ai_signal_definitions.subject_entity_key` and `ai_agents.allowed_entities`
 * rows, but no matching `ontology_entities` row existed until now.
 *
 * Idempotent, baseline-only (sub_institute_id IS NULL): safe to re-run.
 */
return new class extends Migration
{
    private const MODULE_KEY = 'curriculum_planning';

    private function entities(): array
    {
        return [
            [
                'entity_key' => 'curriculum', 'label' => 'Curriculum',
                'domain' => 'k12', 'category' => 'academic', 'sort_order' => 105,
                'description' => 'A subject curriculum definition for a standard/board (lms_curriculum). Live in Neo4j as :Curriculum.',
                'source_table' => 'lms_curriculum', 'primary_key_column' => 'id',
                'label_column' => 'curriculum_name', 'tenant_column' => 'sub_institute_id',
                'academic_year_column' => 'syear',
                'attributes' => [
                    ['key' => 'curriculum_name', 'column' => 'curriculum_name', 'type' => 'string', 'searchable' => true],
                    ['key' => 'board', 'column' => 'board', 'type' => 'string'],
                    ['key' => 'framework', 'column' => 'framework', 'type' => 'string'],
                    ['key' => 'subject_id', 'column' => 'subject_id', 'type' => 'integer'],
                    ['key' => 'standard_id', 'column' => 'standard_id', 'type' => 'integer'],
                    ['key' => 'total_marks', 'column' => 'total_marks', 'type' => 'integer'],
                    ['key' => 'status', 'column' => 'status', 'type' => 'string'],
                ],
            ],
            [
                'entity_key' => 'unit', 'label' => 'Unit',
                'domain' => 'k12', 'category' => 'academic', 'sort_order' => 107,
                'description' => 'A unit within a curriculum (lms_units). Live in Neo4j as :Unit. Not independently tenant-scoped — reached only through curriculum.',
                'source_table' => 'lms_units', 'primary_key_column' => 'id',
                'label_column' => 'name', 'tenant_column' => null, 'is_tenant_scoped' => false,
                'attributes' => [
                    ['key' => 'name', 'column' => 'name', 'type' => 'string', 'searchable' => true],
                    ['key' => 'curriculum_id', 'column' => 'curriculum_id', 'type' => 'integer'],
                    ['key' => 'unit_number', 'column' => 'unit_number', 'type' => 'integer'],
                    ['key' => 'total_marks', 'column' => 'total_marks', 'type' => 'integer'],
                ],
            ],
        ];
    }

    private function relationships(): array
    {
        return [
            ['curriculum', 'has_unit', 'unit', 'from_column' => 'id', 'to_column' => 'curriculum_id', 'cardinality' => 'one_to_many', 'in_graph' => true, 'graph_relationship_type' => 'HAS_UNIT'],
            ['unit', 'has_chapter', 'chapter', 'from_column' => 'id', 'to_column' => 'unit_id', 'cardinality' => 'one_to_many', 'in_graph' => true, 'graph_relationship_type' => 'HAS_CHAPTER', 'description' => 'Sourced from chapter_master.unit_id — the EXTRACTED link, not lms_units.unit_chapters (the declared list, which the two disagree on for 50 of 60 units).'],
            ['subject', 'belongs_to_curriculum', 'curriculum', 'from_column' => 'id', 'to_column' => 'subject_id', 'cardinality' => 'one_to_many', 'in_graph' => false, 'description' => 'Discoverability hop from a subject screen into its curricula. Not yet marked in_graph — GraphSchema has no reverse BELONGS_TO_CURRICULUM-from-Subject lookup wired here; SQL-traversable today.'],
        ];
    }

    private function views(): array
    {
        return [
            [
                'view_key' => 'curriculum-planning-structure',
                'label' => 'Curriculum planning structure',
                'module_key' => self::MODULE_KEY,
                'description' => 'The units and chapters that make up this curriculum.',
                'root_entity_key' => 'curriculum',
                'path' => [
                    ['relation' => 'has_unit', 'entity' => 'unit', 'label' => 'Units'],
                    ['relation' => 'has_chapter', 'entity' => 'chapter', 'label' => 'Chapters'],
                ],
                'max_per_hop' => 20,
                'sort_order' => 10,
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

        if (Schema::hasTable('ontology_entities')) {
            DB::table('ontology_entities')
                ->whereIn('entity_key', array_column($this->entities(), 'entity_key'))
                ->whereNull('sub_institute_id')
                ->delete();
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
