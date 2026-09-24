<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Student module's prompts and report layouts.
 *
 * WHAT A PROMPT IS AND WHAT A REPORT IS, AND WHY THEY ARE BOTH HERE
 *
 * Both are `ai_templates` rows with `module_key = 'students'`, separated by `kind`. A
 * prompt is text sent to a model, which writes prose and may not state a figure on its own
 * authority. A report is an HTML layout whose placeholders are filled by substitution from
 * rows a tool fetched, with no model anywhere near the numbers. That is why the reports
 * below can print a roll number and the prompts below cannot.
 *
 * WHY `students` AND NOT `student`
 *
 * `ai_modules` carries both. `student` is the entity-bound module — one child's record,
 * reached from `/lms/student-analysis/:studentId` — and it already has the academic-risk
 * agent, its workflow and its ontology views. `students` is the module the Student menu
 * actually is: lists, directories, registers and reports over a cohort. The AI Stack being
 * added is the module's, so everything here is filed under `students`, and the per-child
 * module is left exactly as it is.
 *
 * NO STUDENT, NO ROLL NUMBER AND NO CLASS APPEARS IN THIS FILE
 *
 * Every value is a placeholder. The cohort prompts are filled by `ModuleToolData`, which
 * reads the module's bound read tools — `students.directory` first — as the person who
 * asked, scoped to their institute and academic year. The per-student prompts are filled
 * by the screen that opened them from the record in front of the operator. The reports are
 * filled by `AiReportGenerator` from whatever `students.directory` returns. There is no
 * sample cohort and no seeded child anywhere in the AI stack, and there must not be.
 *
 * EVERY PLACEHOLDER IN A LAYOUT IS A FIELD THE TOOL REALLY RETURNS
 *
 * `PeopleDirectoryService::students()` maps each row to exactly
 * `student_id, student_name, enrollment_no, roll_no, grade, standard_name, division_name,
 * standard_id, division_id, mobile, email` and reports `academic_year`, `count`,
 * `returned_count` and `limit` beside them. The layouts below use those names and nothing
 * else, so no cell can render blank because somebody guessed at a column.
 *
 * `count` IS THE COHORT, `row_count` IS THE PAGE
 *
 * The service deliberately counts the whole cohort before applying the limit, so a
 * directory read of 50 rows out of 812 reports `count: 812`. Both are printed and both are
 * labelled, because a report that said "812 students" over a table of 50 would be wrong in
 * the one way nobody checks.
 *
 * GROUNDING IS DECLARED, NOT ASSUMED
 *
 * `GroundingCheck` refuses a generation when every variable marked `grounding` is empty —
 * which is what stops a model writing "the cohort is doing well" from an empty prompt. So
 * the cohort prompts mark `records` and `metrics`, which are what `ModuleToolData` fills,
 * and the per-student prompts mark the fields their screen genuinely supplies.
 *
 * THE SAFETY RULES ARE ABOUT CHILDREN
 *
 * A student record holds a name, a class, a contact number and an enrolment. It holds no
 * judgement about the child, and the rules below forbid the model supplying one: no
 * assessment of ability, no characterisation of behaviour, no inference about a family
 * from an address or a phone number. Anything a family will read requires a person to
 * approve it first.
 *
 * FEES AND ATTENDANCE ARE NOT TOUCHED. Every write is keyed on a template key beginning
 * `k12.students.`, which does not exist yet, and every suggestion row is written with
 * `module_key = 'students'`.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_21_100200_publish_students_ai_templates.php
 */
return new class extends Migration
{
    private const MODULE = 'students';

    private const ROLL_REPORT_KEY = 'k12.students.class_roll_report';

    private const CONTACT_REPORT_KEY = 'k12.students.contact_sheet_report';

    private const DATA_SOURCE = 'students.directory';

    public function up(): void
    {
        if (! Schema::hasTable('ai_templates') || ! Schema::hasColumn('ai_templates', 'kind')) {
            return;
        }

        foreach ($this->templates() as $template) {
            $existing = DB::table('ai_templates')
                ->where('template_key', $template['template_key'])
                ->whereNull('sub_institute_id')
                ->first();

            if ($existing !== null) {
                // Never touch a version an institute has forked: those rows carry a
                // sub_institute_id and are excluded by the query above.
                DB::table('ai_templates')->where('id', $existing->id)->update($template + ['updated_at' => now()]);

                continue;
            }

            DB::table('ai_templates')->insert($template + ['created_at' => now(), 'updated_at' => now()]);
        }

        $this->bindSuggestions();

        // `students` shipped with `generative: false`, which was true while it had no
        // template to generate from. It has five now.
        $this->setModuleCapabilities(['generative' => true]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_templates')) {
            return;
        }

        $keys = array_column($this->templates(), 'template_key');

        if (Schema::hasTable('ai_suggestions')) {
            DB::table('ai_suggestions')
                ->where('module_key', self::MODULE)
                ->whereIn('label', array_column($this->suggestions(), 2))
                ->whereNull('sub_institute_id')
                ->delete();
        }

        // Retired, not deleted: a generation request row references the template it ran
        // against, and deleting the row would orphan the audit trail.
        DB::table('ai_templates')
            ->whereIn('template_key', $keys)
            ->whereNull('sub_institute_id')
            ->update(['status' => 'retired', 'updated_at' => now()]);

        $this->setModuleCapabilities(['generative' => false]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templates(): array
    {
        $cohortRules = json_encode([
            'Do not invent a student name, a roll number, an enrolment number, a class or a count.',
            'Do not assess a student\'s ability, behaviour or character. The directory holds none of that.',
            'Do not infer anything about a family from an address, a phone number or a name.',
            'Do not describe the cohort as empty when no records were provided; say only that none were.',
            'Say when the rows cover a sample rather than the whole cohort.',
        ]);

        $familyRules = json_encode([
            'Do not invent a student name, a class, a date or a number of any kind.',
            'Do not assess the student\'s ability, behaviour or character.',
            'Do not threaten, shame or pressure. This text may be read by a parent.',
            'Ask rather than assert where the school does not already hold the answer.',
        ]);

        return [
            // ---- Cohort prompts, grounded by ModuleToolData ----------------------
            [
                'template_key' => 'k12.students.summary',
                'name' => 'Student cohort summary',
                'description' => 'A short summary of the students listed on the screen.',
                'module_key' => self::MODULE,
                'domain' => 'k12',
                'category' => 'report',
                'kind' => 'prompt',
                'version' => 1,
                'status' => 'published',
                'output_format' => 'text',
                'system_prompt' => 'You summarise a school\'s student records for an administrator. Work only '
                    . 'from the records given below. Never state a name, a roll number, a class or a count that '
                    . 'is not in them, and never estimate. The directory says who is enrolled and where they '
                    . 'sit; it says nothing about how any of them are doing, so never characterise a student.',
                'user_prompt' => "Summarise the students on this page.\n\n"
                    . "Page: {{page_title}}\n"
                    . "Active filters: {{filters}}\n"
                    . "Search: {{search_query}}\n"
                    . "Figures on screen: {{metrics}}\n"
                    . "Records shown: {{rows_shown}} of {{record_count}} (partial view: {{is_partial}})\n"
                    . "Rows:\n{{records}}\n\n"
                    . "Cover:\n"
                    . "1. How many students are listed and how that compares with the total reported.\n"
                    . "2. How they are distributed across the standards and divisions in the rows.\n"
                    . "3. Which records are missing a contact number or an enrolment number.\n\n"
                    . 'If no student records were reported, say that none were provided.',
                'variables' => json_encode([
                    ['key' => 'records', 'label' => 'Student records', 'required' => true, 'type' => 'text', 'grounding' => true],
                    ['key' => 'metrics', 'label' => 'Cohort figures', 'required' => false, 'type' => 'text', 'grounding' => true],
                    ['key' => 'page_title', 'label' => 'Page title', 'required' => false, 'type' => 'string'],
                    ['key' => 'filters', 'label' => 'Filters', 'required' => false, 'type' => 'string'],
                    ['key' => 'search_query', 'label' => 'Search', 'required' => false, 'type' => 'string'],
                    ['key' => 'record_count', 'label' => 'Total records', 'required' => false, 'type' => 'string'],
                    ['key' => 'rows_shown', 'label' => 'Rows shown', 'required' => false, 'type' => 'string'],
                    ['key' => 'is_partial', 'label' => 'Partial view', 'required' => false, 'type' => 'string'],
                ]),
                'safety_rules' => $cohortRules,
                'requires_review' => 0,
                'allow_as_evidence' => 0,
                'sub_institute_id' => null,
                'client_id' => null,
            ],
            [
                'template_key' => 'k12.students.record_quality',
                'name' => 'Student record completeness analysis',
                'description' => 'Which student records are incomplete, and what the data does and does not '
                    . 'support saying about them.',
                'module_key' => self::MODULE,
                'domain' => 'k12',
                'category' => 'report',
                'kind' => 'prompt',
                'version' => 1,
                'status' => 'published',
                'output_format' => 'text',
                'system_prompt' => 'You review the completeness of a school\'s student records for a registrar. '
                    . 'Work only from the records given. Never state a name, a number or a class that is not in '
                    . 'them. A blank field means the school has not recorded that detail — it does not mean the '
                    . 'detail does not exist, and you must never say that it does not.',
                'user_prompt' => "Review these student records for missing details.\n\n"
                    . "Page: {{page_title}}\n"
                    . "Active filters: {{filters}}\n"
                    . "Figures on screen: {{metrics}}\n"
                    . "Records shown: {{rows_shown}} of {{record_count}} (partial view: {{is_partial}})\n"
                    . "Rows:\n{{records}}\n\n"
                    . "Cover:\n"
                    . "1. Which fields are blank most often across the rows given.\n"
                    . "2. Whether any standard or division is over-represented among the incomplete records.\n"
                    . "3. What a registrar should collect first, and from whom.\n\n"
                    . 'If no student records were reported, say that none were provided rather than that the '
                    . 'records are complete.',
                'variables' => json_encode([
                    ['key' => 'records', 'label' => 'Student records', 'required' => true, 'type' => 'text', 'grounding' => true],
                    ['key' => 'metrics', 'label' => 'Cohort figures', 'required' => false, 'type' => 'text', 'grounding' => true],
                    ['key' => 'page_title', 'label' => 'Page title', 'required' => false, 'type' => 'string'],
                    ['key' => 'filters', 'label' => 'Filters', 'required' => false, 'type' => 'string'],
                    ['key' => 'record_count', 'label' => 'Total records', 'required' => false, 'type' => 'string'],
                    ['key' => 'rows_shown', 'label' => 'Rows shown', 'required' => false, 'type' => 'string'],
                    ['key' => 'is_partial', 'label' => 'Partial view', 'required' => false, 'type' => 'string'],
                ]),
                'safety_rules' => $cohortRules,
                'requires_review' => 0,
                'allow_as_evidence' => 0,
                'sub_institute_id' => null,
                'client_id' => null,
            ],
            [
                'template_key' => 'k12.students.strength_explanation',
                'name' => 'Student strength explanation',
                'description' => 'Explains what the enrolment figures on screen do and do not show.',
                'module_key' => self::MODULE,
                'domain' => 'k12',
                'category' => 'report',
                'kind' => 'prompt',
                'version' => 1,
                'status' => 'published',
                'output_format' => 'text',
                'system_prompt' => 'You explain enrolment figures to a school leader. Work only from the '
                    . 'records given. A trend needs more than one period: if the data covers a single academic '
                    . 'year, say that no trend can be read from it rather than describing one. Never invent a '
                    . 'comparison year, and never state a capacity or a target the records do not contain.',
                'user_prompt' => "Explain what these enrolment figures show.\n\n"
                    . "Page: {{page_title}}\n"
                    . "Filters: {{filters}}\n"
                    . "Figures: {{metrics}}\n"
                    . "Rows:\n{{records}}\n\n"
                    . 'Say what the figures support concluding, what they do not, and what would have to be '
                    . 'recorded for the remaining question to be answerable.',
                'variables' => json_encode([
                    ['key' => 'records', 'label' => 'Student records', 'required' => true, 'type' => 'text', 'grounding' => true],
                    ['key' => 'metrics', 'label' => 'Cohort figures', 'required' => false, 'type' => 'text', 'grounding' => true],
                    ['key' => 'page_title', 'label' => 'Page title', 'required' => false, 'type' => 'string'],
                    ['key' => 'filters', 'label' => 'Filters', 'required' => false, 'type' => 'string'],
                ]),
                'safety_rules' => $cohortRules,
                'requires_review' => 0,
                'allow_as_evidence' => 0,
                'sub_institute_id' => null,
                'client_id' => null,
            ],

            // ---- Per-student prompts, grounded by the screen that opens them ------
            [
                'template_key' => 'k12.students.profile_note',
                'name' => 'Student profile note',
                'description' => 'An internal note describing what one student\'s record holds and what is '
                    . 'missing from it.',
                'module_key' => self::MODULE,
                'domain' => 'k12',
                'category' => 'note',
                'kind' => 'prompt',
                'version' => 1,
                'status' => 'published',
                'output_format' => 'text',
                'system_prompt' => 'You write short internal notes about a student\'s record for a class '
                    . 'teacher or registrar. Work only from the details given; never state a name, a class, a '
                    . 'number or a date that is not in them. The record holds enrolment facts and nothing '
                    . 'about the child\'s ability or behaviour, so never offer one. Return the note only.',
                'user_prompt' => "Draft the note.\n\n"
                    . "Student: {{student_name}}\n"
                    . "Class: {{standard_division}}\n"
                    . "Roll number: {{roll_no}} &middot; enrolment number: {{enrollment_no}}\n"
                    . "Contact on file: {{contact}}\n"
                    . "Details still missing from the record: {{missing_fields}}\n"
                    . "Anything already known: {{context}}\n\n"
                    . 'Three or four lines: what the record holds, what is not yet recorded, and the next step '
                    . 'for whoever maintains it.',
                'variables' => json_encode([
                    ['key' => 'student_name', 'label' => 'Student', 'required' => true, 'type' => 'string', 'grounding' => true],
                    ['key' => 'standard_division', 'label' => 'Class', 'required' => false, 'type' => 'string', 'grounding' => true],
                    ['key' => 'roll_no', 'label' => 'Roll number', 'required' => false, 'type' => 'string'],
                    ['key' => 'enrollment_no', 'label' => 'Enrolment number', 'required' => false, 'type' => 'string'],
                    ['key' => 'contact', 'label' => 'Contact on file', 'required' => false, 'type' => 'string'],
                    ['key' => 'missing_fields', 'label' => 'Missing details', 'required' => false, 'type' => 'string'],
                    ['key' => 'context', 'label' => 'Already known', 'required' => false, 'type' => 'string'],
                ]),
                'safety_rules' => $cohortRules,
                'requires_review' => 0,
                'allow_as_evidence' => 0,
                'sub_institute_id' => null,
                'client_id' => null,
            ],
            [
                'template_key' => 'k12.students.parent_record_request',
                'name' => 'Record request message to a parent',
                'description' => 'A short message asking one family to supply a detail the student record is '
                    . 'missing.',
                'module_key' => self::MODULE,
                'domain' => 'k12',
                'category' => 'message',
                'kind' => 'prompt',
                'version' => 1,
                'status' => 'published',
                'output_format' => 'text',
                'system_prompt' => 'You write short messages from a school office to a parent, asking for a '
                    . 'detail the school\'s records are missing. Work only from the details given; never state '
                    . 'a name, a class or a number that is not in them. Never say anything about how the child '
                    . 'is doing. Warm, brief and specific about exactly what is needed. Return the message only '
                    . '— no preamble, no quotes, no labels.',
                'user_prompt' => "Write the message.\n\n"
                    . "Student: {{student_name}}, class {{standard_division}}\n"
                    . "What the school needs: {{missing_fields}}\n"
                    . "Why it is needed: {{purpose}}\n"
                    . "How to send it back: {{return_instructions}}\n\n"
                    . 'Two or three sentences. Address the parent, not the student. Say exactly what is needed '
                    . 'and how to provide it.',
                'variables' => json_encode([
                    ['key' => 'student_name', 'label' => 'Student', 'required' => true, 'type' => 'string', 'grounding' => true],
                    ['key' => 'missing_fields', 'label' => 'What is needed', 'required' => true, 'type' => 'string', 'grounding' => true],
                    ['key' => 'standard_division', 'label' => 'Class', 'required' => false, 'type' => 'string'],
                    ['key' => 'purpose', 'label' => 'Why it is needed', 'required' => false, 'type' => 'string'],
                    ['key' => 'return_instructions', 'label' => 'How to return it', 'required' => false, 'type' => 'string'],
                ]),
                'safety_rules' => $familyRules,
                // Anything a family receives is read by a person first.
                'requires_review' => 1,
                'allow_as_evidence' => 0,
                'sub_institute_id' => null,
                'client_id' => null,
            ],

            // ---- Report layouts, filled by substitution from students.directory ---
            [
                'template_key' => self::ROLL_REPORT_KEY,
                'name' => 'Class Roll Report',
                'description' => 'The students enrolled in a class this academic year, by roll number, with '
                    . 'their enrolment numbers.',
                'module_key' => self::MODULE,
                'domain' => 'k12',
                'category' => 'report',
                'kind' => 'report',
                // Version 2 — `ReportTemplateResolver::find()` resolves ONE layout per
                // module, school row first and then highest version, so the version is what
                // decides which of two published platform layouts a report is built with.
                // This is the module's default; the contact sheet below sits at version 1
                // as the alternative a school can promote from the Templates tab.
                'version' => 2,
                'status' => 'published',
                // NOT NULL on this table, and a report has no prompt.
                'user_prompt' => '',
                'html_layout' => $this->classRollLayout(),
                'data_source' => self::DATA_SOURCE,
                // The floor the question's own arguments are merged over. 200 is the tool's
                // own maximum, so a class sheet is never silently truncated at 50.
                'data_arguments' => json_encode(['active_only' => true, 'limit' => 200]),
                'output_format' => 'text',
                'requires_review' => 0,
                'allow_as_evidence' => 0,
                'sub_institute_id' => null,
                'client_id' => null,
            ],
            [
                'template_key' => self::CONTACT_REPORT_KEY,
                'name' => 'Student Contact Sheet',
                'description' => 'The students enrolled this academic year with the contact details on file, '
                    . 'and a blank where the school holds none.',
                'module_key' => self::MODULE,
                'domain' => 'k12',
                'category' => 'report',
                'kind' => 'report',
                'version' => 1,
                'status' => 'published',
                'user_prompt' => '',
                'html_layout' => $this->contactSheetLayout(),
                'data_source' => self::DATA_SOURCE,
                'data_arguments' => json_encode(['active_only' => true, 'limit' => 200]),
                'output_format' => 'text',
                'requires_review' => 0,
                'allow_as_evidence' => 0,
                'sub_institute_id' => null,
                'client_id' => null,
            ],
        ];
    }

    /**
     * The suggestion rows, as [action_type, action_ref, label, description].
     *
     * Declared once so `down()` can remove exactly what `up()` wrote.
     *
     * @return array<int, array{0:string,1:?string,2:string,3:string}>
     */
    private function suggestions(): array
    {
        return [
            ['generate', 'k12.students.summary', 'Summarise these students', 'Summarise the students on this page from the enrolment records.'],
            ['generate', 'k12.students.record_quality', 'Check record completeness', 'Find which student records are missing details, and what to collect first.'],
            ['generate', 'k12.students.strength_explanation', 'Explain enrolment figures', 'Explain what these enrolment figures do and do not show.'],
            ['report', null, 'Student report', 'Build the student report document from the enrolment records.'],
        ];
    }

    /**
     * Offer the module's actions in the Student AI panel.
     *
     * `ai_templates` stores a template; `ai_suggestions` is what makes the module's panel
     * offer it as a button. Both are needed, which is why they are written together.
     * `action_type = 'generate'` renders a prompt and returns prose; `action_type =
     * 'report'` builds the saved document `/ai-reports/{id}` opens, and names no template
     * because `AiReportGenerator` resolves the module's layout itself.
     */
    private function bindSuggestions(): void
    {
        if (! Schema::hasTable('ai_suggestions')) {
            return;
        }

        foreach ($this->suggestions() as $index => [$actionType, $ref, $label, $description]) {
            $exists = DB::table('ai_suggestions')
                ->where('module_key', self::MODULE)
                ->where('capability', 'generative')
                ->where('label', $label)
                ->whereNull('sub_institute_id')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('ai_suggestions')->insert([
                'module_key' => self::MODULE,
                'capability' => 'generative',
                'label' => $label,
                'description' => $description,
                'icon' => null,
                'action_type' => $actionType,
                'action_ref' => $ref,
                'prompt' => null,
                'payload' => null,
                // Cohort actions, offered on the student list pages rather than gated
                // behind selecting one child.
                'requires_entity' => false,
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

    /**
     * Flip only the named flags, merging into whatever the row already holds.
     *
     * @param  array<string, bool>  $flags
     */
    private function setModuleCapabilities(array $flags): void
    {
        if (! Schema::hasTable('ai_modules')) {
            return;
        }

        $rows = DB::table('ai_modules')->where('module_key', self::MODULE)->get(['id', 'capabilities']);

        foreach ($rows as $row) {
            $existing = json_decode((string) $row->capabilities, true);

            if (! is_array($existing)) {
                $existing = ['conversational' => true];
            }

            DB::table('ai_modules')->where('id', $row->id)->update([
                'capabilities' => json_encode(array_merge($existing, $flags)),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * `<<count>>` is the whole cohort and `<<row_count>>` is this page of it — see the
     * note at the top of the file. Both are printed and both are labelled.
     */
    private function classRollLayout(): string
    {
        return <<<'HTML'
<div style="font-family:Segoe UI,Arial,sans-serif;color:#0f172a">

  <h2 style="margin:0 0 4px 0;font-size:20px"><<report_title>></h2>
  <p style="margin:0 0 16px 0;color:#475569;font-size:13px">
    <<row_count>> student(s) listed &middot; academic year <<academic_year>> &middot;
    generated <<generated_at>>
  </p>

  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:#f1f5f9">
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left;width:36px">#</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Roll</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Student</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Enrolment no.</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Grade</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Std</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Div</th>
      </tr>
    </thead>
    <tbody>
      <<#rows>>
      <tr>
        <td style="border:1px solid #cbd5e1;padding:6px"><<row_number>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<roll_no>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<student_name>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<enrollment_no>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<grade>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<standard_name>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<division_name>></td>
      </tr>
      <</rows>>
    </tbody>
  </table>

  <p style="margin:16px 0 0 0;font-size:13px">
    <strong>Students matching these filters: <<count>></strong> &mdash; of which
    <<row_count>> are listed above. A blank cell is a detail the school has not recorded,
    not a detail that does not exist.
  </p>

</div>
HTML;
    }

    private function contactSheetLayout(): string
    {
        return <<<'HTML'
<div style="font-family:Segoe UI,Arial,sans-serif;color:#0f172a">

  <h2 style="margin:0 0 4px 0;font-size:20px"><<report_title>></h2>
  <p style="margin:0 0 16px 0;color:#475569;font-size:13px">
    <<row_count>> student(s) listed &middot; academic year <<academic_year>> &middot;
    generated <<generated_at>>
  </p>

  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:#f1f5f9">
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left;width:36px">#</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Student</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Std / Div</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Roll</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Enrolment no.</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Mobile</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Email</th>
      </tr>
    </thead>
    <tbody>
      <<#rows>>
      <tr>
        <td style="border:1px solid #cbd5e1;padding:6px"><<row_number>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<student_name>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<standard_name>> / <<division_name>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<roll_no>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<enrollment_no>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<mobile>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<email>></td>
      </tr>
      <</rows>>
    </tbody>
  </table>

  <p style="margin:16px 0 0 0;font-size:13px">
    <strong>Students matching these filters: <<count>></strong> &mdash; of which
    <<row_count>> are listed above. An empty mobile or email column is a contact the school
    has not recorded; it is not a contact that does not exist.
  </p>

</div>
HTML;
    }
};
