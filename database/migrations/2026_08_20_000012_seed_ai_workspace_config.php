<?php

use App\Domain\K12\AcademicRisk\AcademicRiskAgent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds the AI Workspace configuration.
 *
 * The route patterns below are the ones this estate actually has. The brief's example
 * of `/students/123` does not exist here — student records are reached through
 * `/fees/collect/[studentId]` and `/lms/student-analysis/[studentId]` — so those are
 * what the student module is mapped to. Guessing at a tidier URL would have produced a
 * resolver that never matches anything.
 *
 * Suggestions are worded for the person using them. A teacher sees "Why is this student
 * at risk?", never "invoke academic-risk agent"; the agent key is the binding behind
 * the label, not the label itself.
 *
 * Only capabilities with something real behind them are switched on. Modules where the
 * intelligence layer has no agent, workflow or template yet get conversational only —
 * an empty tab is worse than an absent one.
 *
 * Idempotent: baseline rows (sub_institute_id IS NULL) are replaced, tenant overrides
 * are never touched.
 */
return new class extends Migration
{
    private function modules(): array
    {
        return [
            [
                'module_key' => 'dashboard',
                'label' => 'Dashboard',
                'description' => 'Institute overview and KPIs.',
                'route_patterns' => ['/dashboard', '/dashboard/**', '/'],
                'entity_key' => null,
                'entity_param' => null,
                'capabilities' => ['conversational' => true, 'generative' => true, 'agent' => false, 'workflow' => false, 'ontology' => false],
                'icon' => 'layout-dashboard',
                'sort_order' => 10,
                'match_priority' => 50,
            ],
            [
                'module_key' => 'student',
                'label' => 'Student',
                'description' => 'A single student record.',
                // The real entity-bearing student routes in this estate.
                'route_patterns' => [
                    '/lms/student-analysis/:studentId',
                    '/fees/collect/:studentId',
                ],
                'entity_key' => 'student',
                'entity_param' => 'studentId',
                'capabilities' => ['conversational' => true, 'generative' => true, 'agent' => true, 'workflow' => true, 'ontology' => true],
                'icon' => 'user',
                'sort_order' => 20,
                // Beats the broader /fees/** pattern for the same URL.
                'match_priority' => 10,
            ],
            [
                'module_key' => 'students',
                'label' => 'Students',
                'description' => 'Student lists and administration.',
                'route_patterns' => ['/students', '/students/**', '/student', '/student/**'],
                'entity_key' => null,
                'entity_param' => null,
                'capabilities' => ['conversational' => true, 'generative' => false, 'agent' => true, 'workflow' => false, 'ontology' => false],
                'icon' => 'users',
                'sort_order' => 30,
                'match_priority' => 60,
            ],
            [
                'module_key' => 'fees',
                'label' => 'Fees',
                'description' => 'Fee collection, defaulters and reports.',
                'route_patterns' => ['/fees', '/fees/**'],
                'entity_key' => null,
                'entity_param' => null,
                'capabilities' => ['conversational' => true, 'generative' => false, 'agent' => false, 'workflow' => false, 'ontology' => false],
                'icon' => 'receipt',
                'sort_order' => 40,
                'match_priority' => 70,
            ],
            [
                'module_key' => 'attendance',
                'label' => 'Attendance',
                'description' => 'Attendance recording and reports.',
                'route_patterns' => ['/attendance', '/attendance/**'],
                'entity_key' => null,
                'entity_param' => null,
                'capabilities' => ['conversational' => true, 'generative' => false, 'agent' => false, 'workflow' => false, 'ontology' => false],
                'icon' => 'calendar-check',
                'sort_order' => 50,
                'match_priority' => 70,
            ],
            [
                'module_key' => 'admissions',
                'label' => 'Admissions',
                'description' => 'Enquiries, registration and confirmation.',
                'route_patterns' => ['/admissions', '/admissions/**', '/admission-Enquiry', '/admission-Enquiry/**'],
                'entity_key' => null,
                'entity_param' => null,
                'capabilities' => ['conversational' => true, 'generative' => false, 'agent' => false, 'workflow' => false, 'ontology' => false],
                'icon' => 'user-plus',
                'sort_order' => 60,
                'match_priority' => 70,
            ],
            [
                'module_key' => 'course',
                'label' => 'Course',
                'description' => 'A single course and its lesson plan.',
                'route_patterns' => ['/course-master/:courseId', '/course-master/lesson-plan/:courseId', '/course-master/lesson-plan/:courseId/**'],
                'entity_key' => null,
                'entity_param' => null,
                'capabilities' => ['conversational' => true, 'generative' => true, 'agent' => false, 'workflow' => false, 'ontology' => false],
                'icon' => 'book-open',
                'sort_order' => 70,
                'match_priority' => 20,
            ],
            [
                'module_key' => 'exam',
                'label' => 'Exams & results',
                'description' => 'Exam setup, marks entry and results.',
                'route_patterns' => ['/exam', '/exam/**', '/result', '/result/**'],
                'entity_key' => null,
                'entity_param' => null,
                'capabilities' => ['conversational' => true, 'generative' => false, 'agent' => false, 'workflow' => false, 'ontology' => false],
                'icon' => 'clipboard-list',
                'sort_order' => 80,
                'match_priority' => 70,
            ],
        ];
    }

    /**
     * The plain-language actions each tab shows.
     *
     * `action_ref` binds to something that already exists: an agent key in
     * `ai_agents`, a workflow key in `workflow_definitions`, a template key in
     * `ai_templates`, or a view key in `ai_ontology_views`. CapabilityResolver drops
     * any suggestion whose binding is missing, so a half-configured estate degrades
     * to fewer buttons rather than broken ones.
     */
    private function suggestions(): array
    {
        return [
            // ---- Student (entity present) --------------------------------------
            ['student', 'conversational', 'Why is this student at risk?', 'prompt', null, 'Why is {{entity_label}} at academic risk? Explain using the recorded evidence.', true, 10],
            ['student', 'conversational', 'Show academic evidence', 'prompt', null, 'Show the assessment, attendance and assignment evidence recorded for {{entity_label}}.', true, 20],
            ['student', 'conversational', 'Summarize performance', 'prompt', null, 'Summarise recent academic performance for {{entity_label}}.', true, 30],
            ['student', 'conversational', 'What should we do next?', 'prompt', null, 'Based on the available evidence, what recommendation should be given for {{entity_label}}?', true, 40],

            ['student', 'generative', 'Generate intervention activity', 'generate', 'k12.intervention_activity', null, true, 10],

            ['student', 'agent', 'Analyse academic risk', 'run_agent', 'k12_academic_risk', null, true, 10],

            ['student', 'workflow', 'Academic intervention', 'start_workflow', AcademicRiskAgent::WORKFLOW_KEY, null, true, 10],

            ['student', 'ontology', 'View student relationships', 'ontology_view', 'student-learning', null, true, 10],
            ['student', 'ontology', 'View evidence relationships', 'ontology_view', 'student-evidence', null, true, 20],

            // ---- Students (list; no single subject) ----------------------------
            ['students', 'conversational', 'Which students need intervention?', 'prompt', null, 'Which students are showing academic risk and need intervention?', false, 10],
            ['students', 'conversational', 'Search for a student', 'prompt', null, 'Find a student by name or enrollment number.', false, 20],
            ['students', 'agent', 'Find students at risk', 'run_agent', 'k12_academic_risk', null, false, 10],

            // ---- Dashboard ------------------------------------------------------
            ['dashboard', 'conversational', 'Explain this dashboard', 'prompt', null, 'Explain what the current dashboard is showing and which figures stand out.', false, 10],
            ['dashboard', 'conversational', 'Which KPI needs attention?', 'prompt', null, 'Which indicator on the dashboard most needs attention right now, and why?', false, 20],
            ['dashboard', 'conversational', "Today's activity", 'prompt', null, 'What is in my activity stream today?', false, 30],

            // ---- Fees -----------------------------------------------------------
            ['fees', 'conversational', 'Which students have pending fees?', 'prompt', null, 'Which students currently have pending or unpaid fees?', false, 10],
            ['fees', 'conversational', 'Fee collection summary', 'prompt', null, 'Summarise fee collection for the current period.', false, 20],
            ['fees', 'conversational', 'Show defaulters', 'prompt', null, 'Show the fee defaulter report.', false, 30],

            // ---- Attendance -----------------------------------------------------
            ['attendance', 'conversational', "Today's attendance", 'prompt', null, 'Summarise attendance recorded today.', false, 10],
            ['attendance', 'conversational', 'Who has low attendance?', 'prompt', null, 'Which students have the lowest attendance in the recent period?', false, 20],

            // ---- Admissions -----------------------------------------------------
            ['admissions', 'conversational', 'Pending admissions', 'prompt', null, 'Which admission enquiries are still pending?', false, 10],
            ['admissions', 'conversational', 'Recent enquiries', 'prompt', null, 'Show recent admission enquiries.', false, 20],

            // ---- Course ---------------------------------------------------------
            ['course', 'conversational', 'Summarise this course', 'prompt', null, 'Summarise the chapters and lesson plan for this course.', false, 10],

            // ---- Exams ----------------------------------------------------------
            ['exam', 'conversational', 'Show result report', 'prompt', null, 'Generate the class-wise result report for the current term.', false, 10],
            ['exam', 'conversational', 'Which subjects are weakest?', 'prompt', null, 'Which subjects show the weakest assessment performance this term?', false, 20],
        ];
    }

    /**
     * Relationship views, expressed in the ontology's own relation names so each hop
     * traverses real rows through GraphQueryService.
     */
    private function ontologyViews(): array
    {
        return [
            [
                'view_key' => 'student-learning',
                'label' => 'Student learning path',
                'module_key' => 'student',
                'description' => 'How this student connects to their class, subjects and assessments.',
                'root_entity_key' => 'student',
                'path' => [
                    ['relation' => 'enrolled_in', 'entity' => 'enrollment', 'label' => 'Enrolment'],
                    ['relation' => 'in_standard', 'entity' => 'standard', 'label' => 'Class'],
                    ['relation' => 'teaches', 'entity' => 'subject', 'label' => 'Subjects'],
                    ['relation' => 'contains', 'entity' => 'chapter', 'label' => 'Chapters'],
                ],
                'max_per_hop' => 8,
                'sort_order' => 10,
            ],
            [
                'view_key' => 'student-evidence',
                'label' => 'Risk and evidence trail',
                'module_key' => 'student',
                'description' => 'The signals raised for this student, the case they opened, and what was recommended.',
                'root_entity_key' => 'student',
                'path' => [
                    ['relation' => 'has_signal', 'entity' => 'signal', 'label' => 'Signals'],
                    ['relation' => 'creates', 'entity' => 'case', 'label' => 'Case'],
                    ['relation' => 'has', 'entity' => 'evidence', 'label' => 'Evidence'],
                ],
                'max_per_hop' => 10,
                'sort_order' => 20,
            ],
            [
                'view_key' => 'student-assessments',
                'label' => 'Assessment history',
                'module_key' => 'student',
                'description' => 'Assessment attempts recorded for this student.',
                'root_entity_key' => 'student',
                'path' => [
                    ['relation' => 'attempts', 'entity' => 'assessment', 'label' => 'Assessments'],
                ],
                'max_per_hop' => 15,
                'sort_order' => 30,
            ],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('ai_modules')) {
            return;
        }

        $now = now();

        foreach ($this->modules() as $module) {
            $this->upsert('ai_modules', ['module_key' => $module['module_key']], [
                'label' => $module['label'],
                'domain' => 'k12',
                'description' => $module['description'],
                'route_patterns' => json_encode($module['route_patterns']),
                'entity_key' => $module['entity_key'],
                'entity_param' => $module['entity_param'],
                'capabilities' => json_encode($module['capabilities']),
                'allowed_roles' => null,
                'icon' => $module['icon'],
                'sort_order' => $module['sort_order'],
                'match_priority' => $module['match_priority'],
                'status' => 1,
                'updated_at' => $now,
            ], ['module_key' => $module['module_key']]);
        }

        if (Schema::hasTable('ai_suggestions')) {
            // Replace the baseline set wholesale so a re-run cannot leave orphans
            // behind when a suggestion is renamed or dropped.
            DB::table('ai_suggestions')->whereNull('sub_institute_id')->delete();

            foreach ($this->suggestions() as $suggestion) {
                [$module, $capability, $label, $actionType, $actionRef, $prompt, $requiresEntity, $sortOrder] = $suggestion;

                DB::table('ai_suggestions')->insert([
                    'module_key' => $module,
                    'capability' => $capability,
                    'label' => $label,
                    'description' => null,
                    'icon' => null,
                    'action_type' => $actionType,
                    'action_ref' => $actionRef,
                    'prompt' => $prompt,
                    'payload' => null,
                    'requires_entity' => $requiresEntity,
                    'allowed_roles' => null,
                    'required_permissions' => null,
                    'sort_order' => $sortOrder,
                    'status' => 1,
                    'sub_institute_id' => null,
                    'client_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('ai_ontology_views')) {
            foreach ($this->ontologyViews() as $view) {
                $this->upsert('ai_ontology_views', ['view_key' => $view['view_key']], [
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
                ], ['view_key' => $view['view_key']]);
            }
        }
    }

    public function down(): void
    {
        foreach (['ai_suggestions', 'ai_ontology_views', 'ai_modules'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->whereNull('sub_institute_id')->delete();
            }
        }
    }

    private function upsert(string $table, array $match, array $payload, array $insertOnly): void
    {
        $query = DB::table($table)->whereNull('sub_institute_id');

        foreach ($match as $column => $value) {
            $query->where($column, $value);
        }

        $existing = $query->first();

        if ($existing) {
            DB::table($table)->where('id', $existing->id)->update($payload);

            return;
        }

        DB::table($table)->insert($payload + $insertOnly + [
            'sub_institute_id' => null,
            'client_id' => null,
            'created_at' => now(),
        ]);
    }
};
