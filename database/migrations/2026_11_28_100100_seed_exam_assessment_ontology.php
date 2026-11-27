<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wires the Exam & Assessment module into the decentralized Knowledge Graph
 * (ontology_entities / ontology_relationships / ai_ontology_views), part of the
 * "Context Graph - Whole LMS Integration" task.
 *
 * All the Neo4j data this migration points at is ALREADY LIVE and synced by
 * Pipeline A (config/neo4j.php's `question_paper` / `lms_question_master` specs
 * and the bespoke ResultGraphProjection) — this migration does not create or
 * change any sync behaviour, it only registers the mapping the Knowledge Graph
 * tab and GraphQueryService read from:
 *
 *   Subject -[HAS_ASSESSMENT]-> Assessment -[HAS_QUESTION]-> Question
 *   Question -[BELONGS_TO]-> Chapter        Question -[ASSESSES]-> Concept
 *   Student  -[ATTEMPTED]-> Assessment      (via lms_online_exam)
 *
 * `exam_paper` is a NEW entity, deliberately distinct from the existing
 * `assessment` entity (source lms_online_exam, the attempt row, already used
 * by the `student-assessments` view). Neo4j's `:Assessment` node is keyed by
 * `question_paper.id`, not `lms_online_exam.id` (config/neo4j.php's
 * `question_paper` spec + ResultGraphProjection both key Assessment this way) —
 * repointing the existing `assessment` entity to `question_paper` would break
 * that live view's grain, so this adds a second entity instead of reusing it.
 *
 * `exam_paper -[has_question]-> question` is declared graph-only (no
 * from/to column): `question_paper.question_ids` is a comma-separated string
 * with no honest SQL-joinable fallback, matching the existing
 * `department-has-employee` precedent for "declared but not SQL-traversable".
 * It requires GraphQueryService's `LABEL_OVERRIDE['exam_paper'] = 'Assessment'`
 * fix (landed separately) to actually resolve on the graph side.
 *
 * Idempotent, baseline-only (sub_institute_id IS NULL): safe to re-run.
 */
return new class extends Migration
{
    private const MODULE_KEY = 'exam_assessment';

    private function entities(): array
    {
        return [
            [
                'entity_key' => 'exam_paper', 'label' => 'Exam Paper',
                'domain' => 'k12', 'category' => 'academic', 'sort_order' => 135,
                'description' => 'An assembled exam/question paper (question_paper). Live in Neo4j as :Assessment, keyed by question_paper.id — distinct from the existing "assessment" entity, which maps the attempt row (lms_online_exam).',
                'source_table' => 'question_paper', 'primary_key_column' => 'id',
                'label_column' => 'paper_name', 'tenant_column' => 'sub_institute_id',
                'academic_year_column' => 'syear',
                'attributes' => [
                    ['key' => 'paper_name', 'column' => 'paper_name', 'type' => 'string', 'searchable' => true],
                    ['key' => 'exam_type', 'column' => 'exam_type', 'type' => 'string'],
                    ['key' => 'total_marks', 'column' => 'total_marks', 'type' => 'integer'],
                    ['key' => 'total_ques', 'column' => 'total_ques', 'type' => 'integer'],
                    ['key' => 'standard_id', 'column' => 'standard_id', 'type' => 'integer'],
                    ['key' => 'subject_id', 'column' => 'subject_id', 'type' => 'integer'],
                ],
            ],
            [
                'entity_key' => 'question', 'label' => 'Question',
                'domain' => 'k12', 'category' => 'academic', 'sort_order' => 145,
                'description' => 'A question-bank item (lms_question_master). Live in Neo4j as :Question.',
                'source_table' => 'lms_question_master', 'primary_key_column' => 'id',
                'label_column' => 'question_title', 'tenant_column' => 'sub_institute_id',
                'attributes' => [
                    ['key' => 'question_title', 'column' => 'question_title', 'type' => 'string', 'searchable' => true],
                    ['key' => 'points', 'column' => 'points', 'type' => 'integer'],
                    ['key' => 'chapter_id', 'column' => 'chapter_id', 'type' => 'integer'],
                    ['key' => 'concept_id', 'column' => 'concept_id', 'type' => 'integer'],
                    ['key' => 'standard_id', 'column' => 'standard_id', 'type' => 'integer'],
                    ['key' => 'subject_id', 'column' => 'subject_id', 'type' => 'integer'],
                ],
            ],
        ];
    }

    private function relationships(): array
    {
        return [
            ['subject', 'has_assessment', 'exam_paper', 'from_column' => 'id', 'to_column' => 'subject_id', 'cardinality' => 'one_to_many', 'in_graph' => true, 'graph_relationship_type' => 'HAS_ASSESSMENT'],
            ['exam_paper', 'has_question', 'question', 'cardinality' => 'one_to_many', 'in_graph' => true, 'graph_relationship_type' => 'HAS_QUESTION', 'description' => 'question_paper.question_ids is a comma-separated string — no honest SQL join exists, graph-only.'],
            ['question', 'belongs_to_chapter', 'chapter', 'from_column' => 'chapter_id', 'to_column' => 'id', 'cardinality' => 'many_to_one', 'in_graph' => true, 'graph_relationship_type' => 'BELONGS_TO'],
            ['question', 'assesses', 'learning_concept', 'from_column' => 'concept_id', 'to_column' => 'id', 'cardinality' => 'many_to_one', 'in_graph' => true, 'graph_relationship_type' => 'ASSESSES'],
            ['student', 'attempted', 'exam_paper', 'from_column' => 'id', 'join_table' => 'lms_online_exam', 'join_from_column' => 'student_id', 'join_to_column' => 'question_paper_id', 'to_column' => 'id', 'cardinality' => 'many_to_many', 'in_graph' => true, 'graph_relationship_type' => 'ATTEMPTED'],
        ];
    }

    private function views(): array
    {
        return [
            [
                'view_key' => 'exam-assessment-structure',
                'label' => 'Exam & Assessment structure',
                'module_key' => self::MODULE_KEY,
                'description' => 'The assessments and questions set for this subject.',
                'root_entity_key' => 'subject',
                'path' => [
                    ['relation' => 'has_assessment', 'entity' => 'exam_paper', 'label' => 'Assessments'],
                    ['relation' => 'has_question', 'entity' => 'question', 'label' => 'Questions'],
                ],
                'max_per_hop' => 15,
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
