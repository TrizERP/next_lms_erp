<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One worked example policy, two prompts and one report layout for each of the five new
 * LMS + PAL modules — the same shape
 * 2026_09_22_100400_seed_example_ai_policy_for_every_ai_stack_module.php and
 * 2026_09_26_100100_publish_remaining_module_ai_templates.php already gave every earlier
 * module, so a person opening any of these five modules' Policies or Templates tab sees a
 * real, module-specific worked example instead of "nothing published yet".
 *
 * TEMPLATE NAMES ARE THE ONES REQUESTED, NOT GENERIC ONES. Teach/Learn gets "Lesson
 * Summary" and "Lesson Plan"; Curriculum Planning gets "Curriculum Plan" and "Curriculum
 * Summary"; Engagement gets "Engagement Summary" and "Engagement Follow-up"; Interactions
 * gets "Interaction Summary" and "Communication Summary"; New PAL gets "Personalized
 * Learning Report" and "PAL Recommendation" — each module's own vocabulary, not one
 * template text with the module name substituted.
 *
 * PLATFORM-SCOPED, EDITABLE PER SCHOOL. Every row carries `sub_institute_id = null`;
 * editing one from inside a module forks a per-institute copy rather than mutating the
 * shared baseline, the same as every other module's examples.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_28_100400_seed_lms_pal_ai_policies_and_templates.php
 */
return new class extends Migration
{
    private const PERMISSIVE_BASELINE = [
        'use_ai_for_brainstorming' => true,
        'use_ai_for_grammar_spelling' => true,
        'use_ai_for_explanations' => true,
        'use_ai_for_summarization' => true,
        'use_ai_for_rewriting' => true,
        'use_ai_for_generating_answers' => false,
        'use_ai_for_generating_code' => false,
        'use_ai_for_generating_images' => false,
        'use_ai_for_completing_assignments' => false,
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    private function policies(): array
    {
        return [
            'teach_learn' => [
                'name' => 'Teach/Learn content communication policy',
                'description' => 'AI may summarise configured courses, chapters and learning activities and '
                    .'draft a lesson-adjacent note for a person to review. Course and chapter configuration '
                    .'is not achievement, so AI may never report a chapter being set up as something a '
                    .'student learned or completed.',
                'disclosure' => 1,
                'acknowledgement' => 0,
                'overrides' => [],
            ],
            'curriculum_planning' => [
                'name' => 'Curriculum Planning communication policy',
                'description' => 'AI may summarise curriculum, unit and chapter coverage and draft a planning '
                    .'note for a person to review. A chapter marked planned or completed describes '
                    .'scheduling status, so AI may never report it as evidence of what students learned.',
                'disclosure' => 1,
                'acknowledgement' => 0,
                'overrides' => [],
            ],
            'engagement' => [
                'name' => 'Engagement communication policy',
                'description' => 'AI may report computed attendance, homework and assignment completion '
                    .'figures and draft a follow-up note for a person to review. A student with no records '
                    .'in the window has no data, never a zero, and AI may never characterise a student\'s '
                    .'effort, character or ability from a computed percentage.',
                'disclosure' => 1,
                'acknowledgement' => 1,
                'overrides' => [],
            ],
            'interactions' => [
                'name' => 'Interactions communication policy',
                'description' => 'AI may summarise logged interactions and draft a follow-up note for a '
                    .'person to review. A logged note is one person\'s record of one conversation, so AI '
                    .'may never treat it as a verified agreement or infer anything about a family beyond '
                    .'what the note itself says.',
                'disclosure' => 1,
                'acknowledgement' => 1,
                'overrides' => [],
            ],
            'new_pal' => [
                'name' => 'New PAL communication policy',
                'description' => 'AI may summarise content-model coverage, gamification activity and '
                    .'coherence-map readings and draft a recommendation for a person to review. These '
                    .'figures describe recorded activity and structure, so AI may never characterise a '
                    .'learner as gifted, struggling or behind from them alone.',
                'disclosure' => 1,
                'acknowledgement' => 1,
                'overrides' => [],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function modules(): array
    {
        return [
            'teach_learn' => [
                'label' => 'Teach/Learn',
                'noun' => 'course record',
                'plural' => 'courses and learning activities',
                'data_source' => 'teach_learn.courses',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Learning Activity Report',
                'report_description' => 'Configured courses, chapters and the learning activities recorded against them.',
                'prompts' => [
                    ['slug' => 'summary', 'name' => 'Lesson Summary', 'description' => 'A short summary of the lessons and courses on the screen.', 'instruction' => 'Summarise the courses and lessons on this page.', 'focus' => [
                        'How many courses and chapters are configured, for which classes.',
                        'What learning activities are recorded against them.',
                        'Which courses have no activity recorded at all.',
                    ]],
                    ['slug' => 'lesson_plan', 'name' => 'Lesson Plan', 'description' => 'A structured lesson plan drafted from configured chapter and activity records.', 'instruction' => 'Draft a lesson plan outline from these chapter and activity records, for a person to review.', 'focus' => [
                        'Which chapter and topics the plan should cover, from what is configured.',
                        'What activities are already recorded that the plan should build on.',
                        'What is missing that a teacher would need to fill in themselves.',
                    ]],
                ],
                'system_role' => 'You summarise course and learning-activity configuration for a teacher or coordinator.',
                'refusals' => [
                    'Do not invent a course, a chapter, an activity, a class or a count.',
                    'These records are CONFIGURATION — what has been set up. They are not achievement: never state what a child learned, how well anybody did, or that a course was effective.',
                    'A course or chapter with no activity recorded is one with no activity RECORDED. Never describe it as neglected or a teacher as inactive.',
                    'Exam results and attendance belong to other modules and you have not been given them. Never infer either from a course or activity record.',
                ],
                'columns' => [
                    ['Course', 'title'], ['Subject', 'subject_name'], ['Standard', 'standard_name'], ['Chapters', 'chapter_count'], ['Status', 'status'],
                ],
                'footer' => 'Courses matched: <<count>>. These rows are how learning has been CONFIGURED; they record nothing about what any child learned or how well.',
            ],
            'curriculum_planning' => [
                'label' => 'Curriculum Planning',
                'noun' => 'curriculum coverage record',
                'plural' => 'curriculum, unit and chapter coverage records',
                'data_source' => 'curriculum_planning.status',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Curriculum Summary',
                'report_description' => 'Curriculum, unit and chapter coverage for a subject and standard, this school year.',
                'prompts' => [
                    ['slug' => 'plan', 'name' => 'Curriculum Plan', 'description' => 'A structured overview of a curriculum’s units and chapters.', 'instruction' => 'Lay out the curriculum plan from these unit and chapter coverage records.', 'focus' => [
                        'How the curriculum is organised into units and chapters.',
                        'How many chapters are completed versus planned in each unit.',
                        'Which units have the least coverage recorded.',
                    ]],
                    ['slug' => 'outcome_report', 'name' => 'Learning Objective Report', 'description' => 'The learning outcomes and competencies declared against a curriculum.', 'instruction' => 'Report the learning outcomes and competencies in these records.', 'focus' => [
                        'Which outcomes and competencies are declared, with their codes.',
                        'How they group under their parent goals.',
                        'What this record does not show about whether outcomes were achieved.',
                    ]],
                ],
                'system_role' => 'You summarise curriculum coverage for a coordinator or teacher.',
                'refusals' => [
                    'Do not invent a curriculum, a unit, a chapter, an outcome or a count.',
                    'Coverage and completion figures are administrative facts about PLANNING, not evidence of what students learned. Never report them as achievement.',
                    'A chapter with no completion recorded is one with no completion RECORDED. Never describe a teacher as behind or a class as underperforming from it.',
                ],
                'columns' => [
                    ['Subject', 'subject_name'], ['Standard', 'standard_name'], ['Units', 'units'], ['Chapters', 'chapters'], ['Coverage %', 'coverage_pct'],
                ],
                'footer' => 'Curricula matched: <<count>>. Coverage figures describe planning and completion status, not what students learned.',
            ],
            'engagement' => [
                'label' => 'Engagement',
                'noun' => 'engagement signal',
                'plural' => 'computed engagement signals',
                'data_source' => 'engagement.students_needing_attention',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Student Engagement Report',
                'report_description' => 'Students whose computed attendance, homework or assignment completion falls below a threshold.',
                'prompts' => [
                    ['slug' => 'summary', 'name' => 'Engagement Summary', 'description' => 'A short summary of computed engagement for a class or student.', 'instruction' => 'Summarise the engagement figures on this page.', 'focus' => [
                        'How many students are below the threshold, and on which signal.',
                        'Whether attendance, homework or assignments is the more common gap.',
                        'What this record does not show about why engagement is low.',
                    ]],
                    ['slug' => 'followup', 'name' => 'Engagement Follow-up', 'description' => 'A follow-up note for a student flagged with low engagement, for a person to review.', 'instruction' => 'Draft a short, factual follow-up note about this student\'s engagement figures, for a person to review before sending.', 'focus' => [
                        'State the figures exactly as computed, and which fell below the threshold.',
                        'Do not speculate about cause.',
                        'Suggest the note be reviewed and personalised by a person before it is sent.',
                    ]],
                ],
                'system_role' => 'You summarise and draft follow-ups on computed student engagement for a teacher or coordinator.',
                'refusals' => [
                    'Do not invent a student, a figure or a count.',
                    'A student with no data in the window has NO DATA, not a zero. Never treat an absence of records as low engagement.',
                    'Never characterise a student\'s effort, character or ability from a computed percentage. These are activity signals, not a judgement.',
                ],
                'columns' => [
                    ['Student', 'student_name'], ['Attendance %', 'attendance'], ['Homework %', 'homework'], ['Assignments %', 'assignments'], ['Below threshold', 'below_threshold'],
                ],
                'footer' => 'Students matched: <<count>>. A student with no data in the window has no data recorded, not a zero.',
            ],
            'interactions' => [
                'label' => 'Interactions',
                'noun' => 'interaction',
                'plural' => 'logged interactions',
                'data_source' => 'interactions.list',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Interaction Summary',
                'report_description' => 'Logged touchpoints with students, parents and staff, and their status.',
                'prompts' => [
                    ['slug' => 'communication_summary', 'name' => 'Communication Summary', 'description' => 'A short summary of logged interactions by type and status.', 'instruction' => 'Summarise the interactions in these records.', 'focus' => [
                        'How many interactions were logged, by type.',
                        'How many are still open and how many are closed.',
                        'Which follow-ups are overdue based on their follow-up date.',
                    ]],
                    ['slug' => 'followup', 'name' => 'Interaction Follow-up', 'description' => 'A follow-up note drafted from a logged interaction, for a person to review.', 'instruction' => 'Draft a short follow-up note from this logged interaction, for a person to review before sending.', 'focus' => [
                        'State only what the logged note says.',
                        'Do not infer a commitment the school or family did not make in writing.',
                        'Suggest the note be reviewed and personalised before it is sent.',
                    ]],
                ],
                'system_role' => 'You summarise and draft follow-ups on logged staff/parent/student interactions.',
                'refusals' => [
                    'Do not invent an interaction, a person, a date or a count.',
                    'A logged note is one person\'s record of one conversation. Never treat it as a verified agreement or promise.',
                    'Never infer anything about a student, parent or staff member beyond what the note itself says.',
                ],
                'columns' => [
                    ['Type', 'interaction_type'], ['About', 'related_type'], ['Subject', 'subject'], ['Occurred', 'occurred_at'], ['Status', 'status'],
                ],
                'footer' => 'Interactions matched: <<count>>. Each note is one person\'s record of one conversation.',
            ],
            'new_pal' => [
                'label' => 'New PAL',
                'noun' => 'PAL record',
                'plural' => 'personalised-learning records',
                'data_source' => 'new_pal.content_model_status',
                'data_arguments' => [],
                'report_name' => 'Learning Progress Summary',
                'report_description' => 'New PAL content-model coverage across chapters, by type.',
                'prompts' => [
                    ['slug' => 'summary', 'name' => 'Personalized Learning Report', 'description' => 'A summary of a learner’s recorded personalised-learning activity.', 'instruction' => 'Summarise this learner’s recorded gamification and content-model activity.', 'focus' => [
                        'What learning events, streaks and badges are recorded.',
                        'How the content model\'s coverage looks for the chapters involved.',
                        'What this record does not show about the learner\'s ability.',
                    ]],
                    ['slug' => 'recommendation', 'name' => 'PAL Recommendation', 'description' => 'A recommended next step drafted from recorded PAL activity, for a person to review.', 'instruction' => 'Draft one or two recommended next steps from these recorded PAL records, for a person to review.', 'focus' => [
                        'Base every recommendation on a figure actually given.',
                        'Do not characterise the learner\'s ability.',
                        'State this is a suggestion for a person to confirm, not a decision.',
                    ]],
                ],
                'system_role' => 'You summarise New PAL\'s own content-model, gamification and coherence records for a teacher or coordinator.',
                'refusals' => [
                    'Do not invent a learner, a chapter, an event or a count.',
                    'Never characterise a learner as gifted, struggling or behind from a gamification or coherence figure alone.',
                    'These are New PAL\'s own records. Never confuse them with the older, separate `pal` module\'s records.',
                ],
                'columns' => [
                    ['Type', 'label'], ['Chapters covered', 'chapters_covered'], ['Coverage %', 'coverage_pct'], ['Source', 'source'],
                ],
                'footer' => 'New PAL content-model rows shown above. These describe recorded activity and structure, not a verdict on any learner\'s ability.',
            ],
        ];
    }

    public function up(): void
    {
        $this->seedPolicies();
        $this->seedTemplates();
    }

    public function down(): void
    {
        $this->unseedTemplates();
        $this->unseedPolicies();
    }

    // ------------------------------------------------------------------ policies

    private function seedPolicies(): void
    {
        if (! Schema::hasTable('ai_policies')
            || ! Schema::hasTable('ai_policy_rules')
            || ! Schema::hasTable('ai_policy_assignments')
            || ! Schema::hasTable('ai_modules')) {
            return;
        }

        foreach ($this->policies() as $moduleKey => $policy) {
            $moduleId = $this->platformModuleId($moduleKey);

            if ($moduleId === null) {
                continue;
            }

            $existing = DB::table('ai_policies')
                ->where('name', $policy['name'])
                ->whereNull('sub_institute_id')
                ->where('is_example', 1)
                ->value('id');

            if ($existing !== null) {
                continue;
            }

            $policyId = DB::table('ai_policies')->insertGetId([
                'sub_institute_id' => null,
                'name' => $policy['name'],
                'description' => $policy['description'],
                'policy_type' => 'ai_assisted',
                'is_example' => 1,
                'status' => 1,
                'require_disclosure' => $policy['disclosure'],
                'require_acknowledgement' => $policy['acknowledgement'],
                'ai_detection_required' => 0,
                'plagiarism_check_required' => 0,
                'detection_provider' => null,
                'detection_threshold' => null,
                'created_by' => null,
                'updated_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (array_merge(self::PERMISSIVE_BASELINE, $policy['overrides']) as $rule => $enabled) {
                DB::table('ai_policy_rules')->insert([
                    'policy_id' => $policyId,
                    'rule_key' => $rule,
                    'rule_value' => $enabled ? 1 : 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('ai_policy_assignments')->insert([
                'policy_id' => $policyId,
                'scope_type' => 'module',
                'scope_id' => $moduleId,
                'sub_institute_id' => null,
                'status' => 1,
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function unseedPolicies(): void
    {
        if (! Schema::hasTable('ai_policies')) {
            return;
        }

        $ids = DB::table('ai_policies')
            ->whereIn('name', array_column($this->policies(), 'name'))
            ->whereNull('sub_institute_id')
            ->where('is_example', 1)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        foreach (['ai_policy_rules', 'ai_policy_assignments'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->whereIn('policy_id', $ids)->delete();
            }
        }

        DB::table('ai_policies')->whereIn('id', $ids)->delete();
    }

    private function platformModuleId(string $moduleKey): ?int
    {
        $id = DB::table('ai_modules')
            ->where('module_key', $moduleKey)
            ->whereNull('sub_institute_id')
            ->where('status', 1)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    // ------------------------------------------------------------------ templates

    private function seedTemplates(): void
    {
        if (! Schema::hasTable('ai_templates') || ! Schema::hasColumn('ai_templates', 'kind')) {
            return;
        }

        foreach ($this->rows() as $template) {
            $existing = DB::table('ai_templates')
                ->where('template_key', $template['template_key'])
                ->whereNull('sub_institute_id')
                ->first();

            if ($existing !== null) {
                DB::table('ai_templates')->where('id', $existing->id)->update($template + ['updated_at' => now()]);

                continue;
            }

            DB::table('ai_templates')->insert($template + ['created_at' => now(), 'updated_at' => now()]);
        }

        $this->bindSuggestions();
    }

    private function unseedTemplates(): void
    {
        if (! Schema::hasTable('ai_templates')) {
            return;
        }

        if (Schema::hasTable('ai_suggestions')) {
            foreach ($this->modules() as $moduleKey => $module) {
                DB::table('ai_suggestions')
                    ->where('module_key', $moduleKey)
                    ->where('capability', 'generative')
                    ->whereIn('label', array_column($this->suggestionsFor($moduleKey, $module), 2))
                    ->whereNull('sub_institute_id')
                    ->delete();
            }
        }

        DB::table('ai_templates')
            ->whereIn('template_key', array_column($this->rows(), 'template_key'))
            ->whereNull('sub_institute_id')
            ->update(['status' => 'retired', 'updated_at' => now()]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(): array
    {
        $rows = [];

        foreach ($this->modules() as $moduleKey => $module) {
            $rules = json_encode($module['refusals']);

            foreach ($module['prompts'] as $prompt) {
                $rows[] = $this->prompt($moduleKey, $module, $prompt, $rules);
            }

            $rows[] = $this->report($moduleKey, $module);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $module
     * @param  array<string, mixed>  $prompt
     */
    private function prompt(string $moduleKey, array $module, array $prompt, string $rules): array
    {
        $cover = '';

        foreach ($prompt['focus'] as $index => $line) {
            $cover .= ($index + 1).'. '.$line."\n";
        }

        return [
            'template_key' => 'k12.'.$moduleKey.'.'.$prompt['slug'],
            'name' => $prompt['name'],
            'description' => $prompt['description'],
            'module_key' => $moduleKey,
            'domain' => 'k12',
            'category' => 'report',
            'kind' => 'prompt',
            'version' => 1,
            'status' => 'published',
            'output_format' => 'text',
            'system_prompt' => $module['system_role'].' Work only from the records given below. '
                .'Never state a name, a date, a figure or a count that is not in them, and never '
                .'estimate one. '.implode(' ', $module['refusals']),
            'user_prompt' => $prompt['instruction']."\n\n"
                ."Page: {{page_title}}\n"
                ."Active filters: {{filters}}\n"
                ."Search: {{search_query}}\n"
                ."Figures on screen: {{metrics}}\n"
                ."Records shown: {{rows_shown}} of {{record_count}} (partial view: {{is_partial}})\n"
                ."Rows:\n{{records}}\n\n"
                ."Cover:\n".$cover."\n"
                .'If no '.$module['noun'].' records were reported, say that none were provided rather '
                .'than describing the module as empty.',
            'variables' => json_encode([
                ['key' => 'records', 'label' => ucfirst($module['noun']).' records', 'required' => true, 'type' => 'text', 'grounding' => true],
                ['key' => 'metrics', 'label' => $module['label'].' figures', 'required' => false, 'type' => 'text', 'grounding' => true],
                ['key' => 'page_title', 'label' => 'Page title', 'required' => false, 'type' => 'string'],
                ['key' => 'filters', 'label' => 'Filters', 'required' => false, 'type' => 'string'],
                ['key' => 'search_query', 'label' => 'Search', 'required' => false, 'type' => 'string'],
                ['key' => 'record_count', 'label' => 'Total records', 'required' => false, 'type' => 'string'],
                ['key' => 'rows_shown', 'label' => 'Rows shown', 'required' => false, 'type' => 'string'],
                ['key' => 'is_partial', 'label' => 'Partial view', 'required' => false, 'type' => 'string'],
            ]),
            'safety_rules' => $rules,
            'requires_review' => 0,
            'allow_as_evidence' => 0,
            'sub_institute_id' => null,
            'client_id' => null,
        ];
    }

    private function report(string $moduleKey, array $module): array
    {
        return [
            'template_key' => 'k12.'.$moduleKey.'.report',
            'name' => $module['report_name'],
            'description' => $module['report_description'],
            'module_key' => $moduleKey,
            'domain' => 'k12',
            'category' => 'report',
            'kind' => 'report',
            'version' => 1,
            'status' => 'published',
            'user_prompt' => '',
            'html_layout' => $this->layout($module),
            'data_source' => $module['data_source'],
            'data_arguments' => json_encode($module['data_arguments']),
            'output_format' => 'text',
            'requires_review' => 0,
            'allow_as_evidence' => 0,
            'sub_institute_id' => null,
            'client_id' => null,
        ];
    }

    private function layout(array $module): string
    {
        $cell = 'style="border:1px solid #cbd5e1;padding:6px;text-align:left"';

        $headings = '<th style="border:1px solid #cbd5e1;padding:6px;text-align:left;width:36px">#</th>';
        $cells = '<td '.$cell.'><<row_number>></td>';

        foreach ($module['columns'] as [$heading, $field]) {
            $headings .= "\n        <th ".$cell.'>'.$heading.'</th>';
            $cells .= "\n        <td ".$cell.'><<'.$field.'>></td>';
        }

        return <<<HTML
<div style="font-family:Segoe UI,Arial,sans-serif;color:#0f172a">

  <h2 style="margin:0 0 4px 0;font-size:20px"><<report_title>></h2>
  <p style="margin:0 0 16px 0;color:#475569;font-size:13px">
    <<row_count>> row(s) listed &middot; academic year <<academic_year>> &middot; generated <<generated_at>>
  </p>

  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:#f1f5f9">
        {$headings}
      </tr>
    </thead>
    <tbody>
      <<#rows>>
      <tr>
        {$cells}
      </tr>
      <</rows>>
    </tbody>
  </table>

  <p style="margin:16px 0 0 0;font-size:13px">
    <strong>{$module['footer']}</strong>
  </p>

</div>
HTML;
    }

    // ------------------------------------------------------------------ suggestions

    /**
     * @return array<int, array{0:string,1:?string,2:string,3:string}>
     */
    private function suggestionsFor(string $moduleKey, array $module): array
    {
        $rows = [];

        foreach ($module['prompts'] as $prompt) {
            $rows[] = ['generate', 'k12.'.$moduleKey.'.'.$prompt['slug'], $prompt['name'], $prompt['description']];
        }

        $rows[] = [
            'report',
            null,
            $module['report_name'],
            'Build the '.strtolower($module['report_name']).' document from the '.$module['noun'].' records.',
        ];

        return $rows;
    }

    private function bindSuggestions(): void
    {
        if (! Schema::hasTable('ai_suggestions')) {
            return;
        }

        foreach ($this->modules() as $moduleKey => $module) {
            foreach ($this->suggestionsFor($moduleKey, $module) as $index => [$actionType, $ref, $label, $description]) {
                $exists = DB::table('ai_suggestions')
                    ->where('module_key', $moduleKey)
                    ->where('capability', 'generative')
                    ->where('label', $label)
                    ->whereNull('sub_institute_id')
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('ai_suggestions')->insert([
                    'module_key' => $moduleKey,
                    'capability' => 'generative',
                    'label' => $label,
                    'description' => $description,
                    'icon' => null,
                    'action_type' => $actionType,
                    'action_ref' => $ref,
                    'prompt' => null,
                    'payload' => null,
                    'requires_entity' => 0,
                    'allowed_roles' => null,
                    'required_permissions' => null,
                    'sort_order' => ($index + 1) * 10,
                    'status' => 1,
                    'sub_institute_id' => null,
                    'client_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
