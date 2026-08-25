<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds the platform-baseline ontology.
 *
 * Every non-virtual entity below is mapped to a table that was verified to exist in
 * this estate, with the column names it actually has — `tblstudent.sub_institute_id`,
 * `school_setup.Id`/`SchoolName`, `subject.subject_name`, `hrms_departments_mapping`
 * (not `hrms_departments`, which has no tenant column), and so on. Nothing here is
 * aspirational: an entity that could not be mapped is marked virtual rather than
 * pointed at a table that does not exist.
 *
 * Relationships carry their SQL join plan. `in_graph` is false everywhere for
 * learner-side edges because Neo4j migration phases 7 (People) and 8 (Assessment)
 * have not landed — see docs/neo4j-migration-status.md. Curriculum edges that the
 * completed phases 4/5 did land are marked true, so the KG layer can prefer the
 * graph exactly where it is trustworthy and fall back to SQL everywhere else.
 *
 * Idempotent: re-running updates the baseline rows and never touches tenant overrides.
 */
return new class extends Migration
{
    /**
     * Non-virtual entities, mapped to real tables.
     */
    private function entities(): array
    {
        return [
            // ---- Organisation & structure -------------------------------------
            [
                'entity_key' => 'organization', 'label' => 'Organization',
                'domain' => 'shared', 'category' => 'core', 'sort_order' => 10,
                'description' => 'The trust or group that owns one or more schools.',
                'source_table' => 'tblclient', 'primary_key_column' => 'id',
                'label_column' => 'client_name', 'client_column' => 'id',
                'attributes' => [
                    ['key' => 'short_code', 'column' => 'short_code', 'type' => 'string'],
                    ['key' => 'city', 'column' => 'city', 'type' => 'string'],
                ],
            ],
            [
                'entity_key' => 'school', 'label' => 'School',
                'domain' => 'k12', 'category' => 'core', 'sort_order' => 20,
                'description' => 'A sub-institute. This is the tenant boundary: sub_institute_id.',
                'source_table' => 'school_setup', 'primary_key_column' => 'Id',
                'label_column' => 'SchoolName', 'tenant_column' => 'Id', 'client_column' => 'client_id',
                'attributes' => [
                    ['key' => 'short_code', 'column' => 'ShortCode', 'type' => 'string', 'searchable' => true],
                    ['key' => 'academic_year', 'column' => 'syear', 'type' => 'string'],
                ],
            ],
            [
                'entity_key' => 'academic_year', 'label' => 'Academic year',
                'domain' => 'shared', 'category' => 'core', 'sort_order' => 30,
                'description' => 'Academic year and term window for a school.',
                'source_table' => 'academic_year', 'primary_key_column' => 'id',
                'label_column' => 'syear', 'tenant_column' => 'sub_institute_id',
                'attributes' => [
                    ['key' => 'term_id', 'column' => 'term_id', 'type' => 'integer'],
                    ['key' => 'start_date', 'column' => 'start_date', 'type' => 'date'],
                    ['key' => 'end_date', 'column' => 'end_date', 'type' => 'date'],
                ],
            ],
            [
                'entity_key' => 'department', 'label' => 'Department',
                'domain' => 'shared', 'category' => 'people', 'sort_order' => 40,
                'description' => 'HRMS department. Mapped to hrms_departments_mapping, which is the tenant-scoped table.',
                'source_table' => 'hrms_departments_mapping', 'primary_key_column' => 'id',
                'label_column' => 'department', 'tenant_column' => 'sub_institute_id',
                'attributes' => [
                    ['key' => 'department', 'column' => 'department', 'type' => 'string', 'searchable' => true],
                    ['key' => 'parent_id', 'column' => 'parent_id', 'type' => 'integer'],
                ],
            ],

            // ---- People ---------------------------------------------------------
            [
                'entity_key' => 'student', 'label' => 'Student',
                'domain' => 'k12', 'category' => 'people', 'sort_order' => 50,
                'description' => 'A learner enrolled at a school.',
                'source_table' => 'tblstudent', 'primary_key_column' => 'id',
                'label_column' => "CONCAT_WS(' ', first_name, last_name)",
                'tenant_column' => 'sub_institute_id',
                'attributes' => [
                    ['key' => 'first_name', 'column' => 'first_name', 'type' => 'string', 'searchable' => true],
                    ['key' => 'last_name', 'column' => 'last_name', 'type' => 'string', 'searchable' => true],
                    ['key' => 'enrollment_no', 'column' => 'enrollment_no', 'type' => 'string', 'searchable' => true],
                    ['key' => 'roll_no', 'column' => 'roll_no', 'type' => 'string', 'searchable' => true],
                    ['key' => 'gender', 'column' => 'gender', 'type' => 'string'],
                    ['key' => 'status', 'column' => 'status', 'type' => 'string'],
                ],
            ],
            [
                'entity_key' => 'enrollment', 'label' => 'Enrollment',
                'domain' => 'k12', 'category' => 'academic', 'sort_order' => 60,
                'description' => 'Placement of a student into a class for an academic year.',
                'source_table' => 'tblstudent_enrollment', 'primary_key_column' => 'id',
                'label_column' => 'enrollment_code', 'tenant_column' => 'sub_institute_id',
                'academic_year_column' => 'syear',
                'attributes' => [
                    ['key' => 'student_id', 'column' => 'student_id', 'type' => 'integer'],
                    ['key' => 'standard_id', 'column' => 'standard_id', 'type' => 'integer'],
                    ['key' => 'section_id', 'column' => 'section_id', 'type' => 'integer'],
                    ['key' => 'roll_no', 'column' => 'roll_no', 'type' => 'string'],
                    ['key' => 'term_id', 'column' => 'term_id', 'type' => 'integer'],
                ],
            ],
            [
                'entity_key' => 'employee', 'label' => 'Employee',
                'domain' => 'shared', 'category' => 'people', 'sort_order' => 70,
                'description' => 'A staff member. Teachers are employees with a teaching profile.',
                'source_table' => 'users', 'primary_key_column' => 'id',
                'label_column' => "CONCAT_WS(' ', first_name, last_name)",
                'tenant_column' => 'sub_institute_id',
                'attributes' => [
                    ['key' => 'first_name', 'column' => 'first_name', 'type' => 'string', 'searchable' => true],
                    ['key' => 'last_name', 'column' => 'last_name', 'type' => 'string', 'searchable' => true],
                    ['key' => 'email', 'column' => 'email', 'type' => 'string', 'searchable' => true],
                    ['key' => 'role', 'column' => 'role', 'type' => 'string'],
                ],
            ],

            // ---- Academic structure ---------------------------------------------
            [
                'entity_key' => 'standard', 'label' => 'Standard',
                'domain' => 'k12', 'category' => 'academic', 'sort_order' => 80,
                'description' => 'A grade level, e.g. Class 8.',
                'source_table' => 'standard', 'primary_key_column' => 'id',
                'label_column' => 'name', 'tenant_column' => 'sub_institute_id',
                'attributes' => [
                    ['key' => 'name', 'column' => 'name', 'type' => 'string', 'searchable' => true],
                    ['key' => 'short_name', 'column' => 'short_name', 'type' => 'string', 'searchable' => true],
                    ['key' => 'grade_id', 'column' => 'grade_id', 'type' => 'integer'],
                ],
            ],
            [
                'entity_key' => 'division', 'label' => 'Division',
                'domain' => 'k12', 'category' => 'academic', 'sort_order' => 90,
                'description' => 'A section within a standard, e.g. 8-B.',
                'source_table' => 'division', 'primary_key_column' => 'id',
                'label_column' => 'name', 'tenant_column' => 'sub_institute_id',
                'attributes' => [
                    ['key' => 'name', 'column' => 'name', 'type' => 'string', 'searchable' => true],
                ],
            ],
            [
                'entity_key' => 'subject', 'label' => 'Subject',
                'domain' => 'k12', 'category' => 'academic', 'sort_order' => 100,
                'description' => 'A taught subject.',
                'source_table' => 'subject', 'primary_key_column' => 'id',
                'label_column' => 'subject_name', 'tenant_column' => 'sub_institute_id',
                'attributes' => [
                    ['key' => 'subject_name', 'column' => 'subject_name', 'type' => 'string', 'searchable' => true],
                    ['key' => 'subject_code', 'column' => 'subject_code', 'type' => 'string', 'searchable' => true],
                    ['key' => 'subject_type', 'column' => 'subject_type', 'type' => 'string'],
                ],
            ],
            [
                'entity_key' => 'chapter', 'label' => 'Chapter',
                'domain' => 'k12', 'category' => 'academic', 'sort_order' => 110,
                'description' => 'A chapter within a subject for a standard.',
                'source_table' => 'chapter_master', 'primary_key_column' => 'id',
                'label_column' => 'chapter_name', 'tenant_column' => 'sub_institute_id',
                'academic_year_column' => 'syear',
                'attributes' => [
                    ['key' => 'chapter_name', 'column' => 'chapter_name', 'type' => 'string', 'searchable' => true],
                    ['key' => 'subject_id', 'column' => 'subject_id', 'type' => 'integer'],
                    ['key' => 'standard_id', 'column' => 'standard_id', 'type' => 'integer'],
                ],
            ],
            [
                'entity_key' => 'learning_concept', 'label' => 'Learning concept',
                'domain' => 'k12', 'category' => 'academic', 'sort_order' => 120,
                'description' => 'A teachable concept inside a chapter, with mastery thresholds.',
                'source_table' => 'lms_concept', 'primary_key_column' => 'id',
                'label_column' => 'name', 'tenant_column' => 'sub_institute_id',
                'academic_year_column' => 'syear',
                'attributes' => [
                    ['key' => 'name', 'column' => 'name', 'type' => 'string', 'searchable' => true],
                    ['key' => 'chapter_id', 'column' => 'chapter_id', 'type' => 'integer'],
                    ['key' => 'subject_id', 'column' => 'subject_id', 'type' => 'integer'],
                    ['key' => 'bloom_level', 'column' => 'bloom_level', 'type' => 'string'],
                    ['key' => 'mastery_threshold', 'column' => 'mastery_threshold', 'type' => 'float'],
                ],
            ],

            // ---- Activity -------------------------------------------------------
            [
                'entity_key' => 'assessment', 'label' => 'Assessment',
                'domain' => 'k12', 'category' => 'academic', 'sort_order' => 130,
                'description' => 'An online exam attempt by a student. Scoped through the student, as the table carries no tenant column of its own.',
                'source_table' => 'lms_online_exam', 'primary_key_column' => 'id',
                'label_column' => null, 'tenant_column' => null, 'is_tenant_scoped' => false,
                'attributes' => [
                    ['key' => 'student_id', 'column' => 'student_id', 'type' => 'integer'],
                    ['key' => 'question_paper_id', 'column' => 'question_paper_id', 'type' => 'integer'],
                    ['key' => 'obtain_marks', 'column' => 'obtain_marks', 'type' => 'float'],
                    ['key' => 'total_right', 'column' => 'total_right', 'type' => 'integer'],
                    ['key' => 'total_wrong', 'column' => 'total_wrong', 'type' => 'integer'],
                ],
            ],
            [
                'entity_key' => 'attendance_record', 'label' => 'Attendance record',
                'domain' => 'k12', 'category' => 'academic', 'sort_order' => 140,
                'description' => 'A single day of student attendance.',
                'source_table' => 'attendance_student', 'primary_key_column' => 'id',
                'label_column' => null, 'tenant_column' => 'sub_institute_id',
                'academic_year_column' => 'syear',
                'attributes' => [
                    ['key' => 'student_id', 'column' => 'student_id', 'type' => 'integer'],
                    ['key' => 'attendance_date', 'column' => 'attendance_date', 'type' => 'date'],
                    ['key' => 'attendance_code', 'column' => 'attendance_code', 'type' => 'string'],
                    ['key' => 'standard_id', 'column' => 'standard_id', 'type' => 'integer'],
                ],
            ],
            [
                'entity_key' => 'learning_evidence', 'label' => 'Learning evidence',
                'domain' => 'k12', 'category' => 'academic', 'sort_order' => 150,
                'description' => 'PAL learning evidence captured from content interaction.',
                'source_table' => 'pal_learning_evidence', 'primary_key_column' => 'id',
                'label_column' => 'evidence_type', 'tenant_column' => null, 'is_tenant_scoped' => false,
                'attributes' => [
                    ['key' => 'learner_id', 'column' => 'learner_id', 'type' => 'integer'],
                    ['key' => 'concept_id', 'column' => 'concept_id', 'type' => 'integer'],
                    ['key' => 'score', 'column' => 'score', 'type' => 'float'],
                    ['key' => 'completion', 'column' => 'completion', 'type' => 'boolean'],
                ],
            ],

            // ---- Intelligence (virtual: served from the ai_* tables) -------------
            ['entity_key' => 'signal', 'label' => 'Signal', 'domain' => 'shared', 'category' => 'intelligence', 'sort_order' => 200, 'is_virtual' => true, 'source_table' => 'ai_signals', 'label_column' => 'signal_key', 'tenant_column' => 'sub_institute_id', 'description' => 'A detected condition worth attention.'],
            ['entity_key' => 'evidence', 'label' => 'Evidence', 'domain' => 'shared', 'category' => 'intelligence', 'sort_order' => 210, 'is_virtual' => true, 'source_table' => 'ai_evidence', 'label_column' => 'summary', 'tenant_column' => 'sub_institute_id', 'description' => 'A cited, provenance-carrying observation.'],
            ['entity_key' => 'case', 'label' => 'Case', 'domain' => 'shared', 'category' => 'intelligence', 'sort_order' => 220, 'is_virtual' => true, 'source_table' => 'ai_cases', 'label_column' => 'title', 'tenant_column' => 'sub_institute_id', 'description' => 'Signals plus evidence gathered into a reviewable unit.'],
            ['entity_key' => 'hypothesis', 'label' => 'Hypothesis', 'domain' => 'shared', 'category' => 'intelligence', 'sort_order' => 230, 'is_virtual' => true, 'source_table' => 'ai_hypotheses', 'label_column' => 'statement', 'tenant_column' => 'sub_institute_id', 'description' => 'A candidate explanation, supported or refuted by evidence.'],
            ['entity_key' => 'explanation', 'label' => 'Explanation', 'domain' => 'shared', 'category' => 'intelligence', 'sort_order' => 240, 'is_virtual' => true, 'source_table' => 'ai_explanations', 'label_column' => null, 'tenant_column' => 'sub_institute_id', 'description' => 'A narrative whose every claim cites evidence.'],
            ['entity_key' => 'recommendation', 'label' => 'Recommendation', 'domain' => 'shared', 'category' => 'intelligence', 'sort_order' => 250, 'is_virtual' => true, 'source_table' => 'ai_recommendations', 'label_column' => 'title', 'tenant_column' => 'sub_institute_id', 'description' => 'A drafted action, never executed without a decision.'],
            ['entity_key' => 'decision', 'label' => 'Decision', 'domain' => 'shared', 'category' => 'intelligence', 'sort_order' => 260, 'is_virtual' => true, 'source_table' => 'ai_decisions', 'label_column' => 'decision', 'tenant_column' => 'sub_institute_id', 'description' => 'The durable record of a human approving or rejecting.'],
            ['entity_key' => 'agent', 'label' => 'Agent', 'domain' => 'shared', 'category' => 'intelligence', 'sort_order' => 270, 'is_virtual' => true, 'source_table' => 'ai_agents', 'label_column' => 'name', 'tenant_column' => 'sub_institute_id', 'description' => 'A governed analytical worker with a fixed tool reach.'],
            ['entity_key' => 'workflow', 'label' => 'Workflow', 'domain' => 'shared', 'category' => 'intelligence', 'sort_order' => 280, 'is_virtual' => true, 'source_table' => 'workflow_definitions', 'label_column' => 'name', 'tenant_column' => 'sub_institute_id', 'description' => 'A configured multi-step business process.'],
            ['entity_key' => 'outcome', 'label' => 'Outcome', 'domain' => 'shared', 'category' => 'intelligence', 'sort_order' => 290, 'is_virtual' => true, 'source_table' => 'ai_outcomes', 'label_column' => 'metric_label', 'tenant_column' => 'sub_institute_id', 'description' => 'The measured result of an action.'],
        ];
    }

    /**
     * Edges, each with the join it takes to walk in SQL.
     */
    private function relationships(): array
    {
        return [
            // Structure
            ['organization', 'has', 'school', 'from_column' => 'id', 'to_column' => 'client_id', 'cardinality' => 'one_to_many'],
            ['school', 'has', 'student', 'from_column' => 'Id', 'to_column' => 'sub_institute_id', 'cardinality' => 'one_to_many'],
            ['school', 'has', 'employee', 'from_column' => 'Id', 'to_column' => 'sub_institute_id', 'cardinality' => 'one_to_many'],
            ['school', 'has', 'department', 'from_column' => 'Id', 'to_column' => 'sub_institute_id', 'cardinality' => 'one_to_many'],
            ['school', 'has', 'standard', 'from_column' => 'Id', 'to_column' => 'sub_institute_id', 'cardinality' => 'one_to_many'],
            ['department', 'has', 'employee', 'from_column' => 'id', 'to_column' => 'department_id', 'cardinality' => 'one_to_many', 'traversable' => false, 'description' => 'Declared but not yet mapped: users has no department_id in this estate.'],
            ['standard', 'has', 'division', 'from_column' => 'id', 'join_table' => 'std_div_map', 'join_from_column' => 'standard_id', 'join_to_column' => 'division_id', 'to_column' => 'id', 'cardinality' => 'many_to_many'],

            // Student academic life — the Academic Risk traversal path
            ['student', 'enrolled_in', 'enrollment', 'from_column' => 'id', 'to_column' => 'student_id', 'cardinality' => 'one_to_many'],
            ['enrollment', 'in_standard', 'standard', 'from_column' => 'standard_id', 'to_column' => 'id', 'cardinality' => 'many_to_one'],
            ['enrollment', 'in_division', 'division', 'from_column' => 'section_id', 'to_column' => 'id', 'cardinality' => 'many_to_one'],
            ['standard', 'teaches', 'subject', 'from_column' => 'id', 'join_table' => 'chapter_master', 'join_from_column' => 'standard_id', 'join_to_column' => 'subject_id', 'to_column' => 'id', 'cardinality' => 'many_to_many'],
            ['subject', 'contains', 'chapter', 'from_column' => 'id', 'to_column' => 'subject_id', 'cardinality' => 'one_to_many', 'in_graph' => true, 'graph_relationship_type' => 'HAS_CHAPTER'],
            ['chapter', 'contains', 'learning_concept', 'from_column' => 'id', 'to_column' => 'chapter_id', 'cardinality' => 'one_to_many', 'in_graph' => true, 'graph_relationship_type' => 'BELONGS_TO'],
            ['student', 'attempts', 'assessment', 'from_column' => 'id', 'to_column' => 'student_id', 'cardinality' => 'one_to_many'],
            ['student', 'has_attendance', 'attendance_record', 'from_column' => 'id', 'to_column' => 'student_id', 'cardinality' => 'one_to_many'],
            ['student', 'produced', 'learning_evidence', 'from_column' => 'id', 'to_column' => 'learner_id', 'cardinality' => 'one_to_many'],
            ['learning_evidence', 'about', 'learning_concept', 'from_column' => 'concept_id', 'to_column' => 'id', 'cardinality' => 'many_to_one'],

            // Intelligence chain
            ['student', 'has_signal', 'signal', 'from_column' => 'id', 'to_column' => 'subject_id', 'cardinality' => 'one_to_many'],
            ['signal', 'creates', 'case', 'from_column' => 'id', 'join_table' => 'ai_case_signals', 'join_from_column' => 'signal_id', 'join_to_column' => 'case_id', 'to_column' => 'id', 'cardinality' => 'many_to_many'],
            ['case', 'has', 'evidence', 'from_column' => 'id', 'join_table' => 'ai_case_evidence', 'join_from_column' => 'case_id', 'join_to_column' => 'evidence_id', 'to_column' => 'id', 'cardinality' => 'many_to_many'],
            ['case', 'has', 'hypothesis', 'from_column' => 'id', 'to_column' => 'case_id', 'cardinality' => 'one_to_many'],
            ['case', 'explained_by', 'explanation', 'from_column' => 'id', 'to_column' => 'case_id', 'cardinality' => 'one_to_many'],
            ['case', 'produces', 'recommendation', 'from_column' => 'id', 'to_column' => 'case_id', 'cardinality' => 'one_to_many'],
            ['recommendation', 'requires', 'decision', 'from_column' => 'id', 'to_column' => 'recommendation_id', 'cardinality' => 'one_to_many'],
            ['recommendation', 'produces', 'outcome', 'from_column' => 'id', 'to_column' => 'recommendation_id', 'cardinality' => 'one_to_many'],
            ['case', 'produces', 'outcome', 'from_column' => 'id', 'to_column' => 'case_id', 'cardinality' => 'one_to_many'],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('ontology_entities') || ! Schema::hasTable('ontology_relationships')) {
            return;
        }

        $now = now();

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

    public function down(): void
    {
        if (Schema::hasTable('ontology_relationships')) {
            DB::table('ontology_relationships')->whereNull('sub_institute_id')->delete();
        }

        if (Schema::hasTable('ontology_entities')) {
            DB::table('ontology_entities')->whereNull('sub_institute_id')->delete();
        }
    }
};
