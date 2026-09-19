<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Attendance module's prompts and report layouts.
 *
 * WHAT A PROMPT IS AND WHAT A REPORT IS, AND WHY THEY ARE BOTH HERE
 *
 * Both are `ai_templates` rows with `module_key = 'attendance'`, separated by `kind`.
 * A prompt is text sent to a model, which writes prose and may not state a figure on its
 * own authority. A report is an HTML layout whose placeholders are filled by substitution
 * from rows a tool fetched, with no model anywhere near the numbers. That is why the
 * report below can print a percentage and the prompts below cannot.
 *
 * NO STUDENT, NO PERCENTAGE AND NO DATE APPEARS IN THIS FILE
 *
 * Every value is a placeholder. The cohort prompts are filled by `ModuleToolData`, which
 * reads the module's bound read tools — `attendance.overview` first — as the person who
 * asked, scoped to their institute and academic year. The per-student prompts are filled
 * by the screen that opened them from the record in front of the operator. The report is
 * filled by `AiReportGenerator` from whatever `attendance.overview` returns. There is no
 * sample cohort and no seeded child anywhere in the AI stack, and there must not be.
 *
 * GROUNDING IS DECLARED, NOT ASSUMED
 *
 * `GroundingCheck` refuses a generation when every variable marked `grounding` is empty —
 * which is what stops a model writing "attendance is excellent this term" from an empty
 * prompt. So the cohort prompts mark `records` and `metrics`, which are what
 * `ModuleToolData` fills, and the per-student prompts mark the fields their screen
 * genuinely supplies. Getting this wrong is not cosmetic: publishing a summary prompt and
 * then asking it to write one parent's message is exactly the mismatch that made every
 * Fees remark refuse with a 422, and it is avoided here by publishing both kinds up front.
 *
 * THE SAFETY RULES ARE ABOUT CHILDREN, NOT MONEY
 *
 * Attendance data says where a child was. Some of the absences in it will have reasons
 * the school has not been told — illness, bereavement, a care arrangement — so the rules
 * below forbid inferring a reason as firmly as they forbid inventing a date. A model that
 * guesses why a child was away is worse than one that admits it does not know.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_19_100200_publish_attendance_ai_templates.php
 */
return new class extends Migration
{
    private const MODULE = 'attendance';

    private const REPORT_KEY = 'k12.attendance.low_attendance_report';

    private const SUMMARY_REPORT_KEY = 'k12.attendance.class_attendance_report';

    private const DATA_SOURCE = 'attendance.overview';

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
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_templates')) {
            return;
        }

        $keys = array_column($this->templates(), 'template_key');

        if (Schema::hasTable('ai_suggestions')) {
            // Matched on label, because the report action deliberately names no template
            // and so cannot be found by `action_ref`. The labels are the ones written by
            // bindSuggestions() and nothing else uses them.
            DB::table('ai_suggestions')
                ->where('module_key', self::MODULE)
                ->whereIn('label', [
                    'Summarise attendance',
                    'Analyse low attendance',
                    'Explain attendance figures',
                    'Attendance report',
                ])
                ->whereNull('sub_institute_id')
                ->delete();
        }

        // Retired, not deleted: a generation request row references the template it ran
        // against, and deleting the row would orphan the audit trail that explains what
        // was written and from which prompt.
        DB::table('ai_templates')
            ->whereIn('template_key', $keys)
            ->whereNull('sub_institute_id')
            ->update(['status' => 'retired', 'updated_at' => now()]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templates(): array
    {
        $cohortRules = json_encode([
            'Do not invent a student name, a date, a percentage or a class.',
            'Do not state a reason for an absence. The register records that a student was away, never why.',
            'Do not describe attendance as empty or perfect when no records were provided; say only that none were.',
            'Say when the figures cover a sample rather than the whole cohort.',
        ]);

        $familyRules = json_encode([
            'Do not invent a student name, a date, a percentage or a class.',
            'Do not state or imply a reason for an absence, and do not suggest the family is at fault.',
            'Do not threaten, shame or pressure. This text may be read by a parent.',
            'Ask rather than assert: the school does not yet know why the child was away.',
        ]);

        return [
            // ---- Cohort prompts, grounded by ModuleToolData ----------------------
            [
                'template_key' => 'k12.attendance.summary',
                'name' => 'Attendance summary',
                'description' => 'A short summary of attendance for the cohort on screen.',
                'module_key' => self::MODULE,
                'domain' => 'k12',
                'category' => 'report',
                'kind' => 'prompt',
                'version' => 1,
                'status' => 'published',
                'output_format' => 'text',
                'system_prompt' => 'You summarise school attendance for administrators and class teachers. '
                    . 'Work only from the records given below. Never state a rate, a student name, a date or a '
                    . 'class that is not in them, and never estimate. A student with too few marked days to '
                    . 'judge is not a student with poor attendance — report them as unjudgeable rather than as '
                    . 'zero. Never state or imply why anybody was absent.',
                'user_prompt' => "Summarise the attendance on this page.\n\n"
                    . "Page: {{page_title}}\n"
                    . "Active filters: {{filters}}\n"
                    . "Search: {{search_query}}\n"
                    . "Figures on screen: {{metrics}}\n"
                    . "Records shown: {{rows_shown}} of {{record_count}} (partial view: {{is_partial}})\n"
                    . "Rows:\n{{records}}\n\n"
                    . "Cover:\n"
                    . "1. The overall attendance rate and how many students it was computed from.\n"
                    . "2. How many students could not be judged, and say plainly that this reflects what has "
                    . "been marked rather than how those students attend.\n"
                    . "3. Which students or classes stand out at the low end.\n\n"
                    . 'If no attendance records were reported, say that none were provided.',
                'variables' => json_encode([
                    ['key' => 'records', 'label' => 'Attendance records', 'required' => true, 'type' => 'text', 'grounding' => true],
                    ['key' => 'metrics', 'label' => 'Attendance figures', 'required' => false, 'type' => 'text', 'grounding' => true],
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
                'template_key' => 'k12.attendance.low_attendance',
                'name' => 'Low attendance analysis',
                'description' => 'Identifies the students attending least, and what the figures do and do '
                    . 'not support saying about them.',
                'module_key' => self::MODULE,
                'domain' => 'k12',
                'category' => 'report',
                'kind' => 'prompt',
                'version' => 1,
                'status' => 'published',
                'output_format' => 'text',
                'system_prompt' => 'You analyse low school attendance for a class teacher. Work only from the '
                    . 'records given. Never state a rate, a name, a date or a class that is not in them. Never '
                    . 'state or imply a reason for an absence — the register records that a child was away and '
                    . 'nothing about why. Distinguish clearly between a student who attends poorly and a '
                    . 'student whose register has barely been marked.',
                'user_prompt' => "Analyse the low attendance in these records.\n\n"
                    . "Page: {{page_title}}\n"
                    . "Active filters: {{filters}}\n"
                    . "Figures on screen: {{metrics}}\n"
                    . "Records shown: {{rows_shown}} of {{record_count}} (partial view: {{is_partial}})\n"
                    . "Rows:\n{{records}}\n\n"
                    . "Cover:\n"
                    . "1. Which students are attending least, with the rate each record actually shows.\n"
                    . "2. Whether any class or division is over-represented among them.\n"
                    . "3. How many students could not be judged at all, and what that means.\n"
                    . "4. One or two concrete next steps a class teacher could take this week.\n\n"
                    . 'If no attendance records were reported, say that none were provided rather than that '
                    . 'attendance is good.',
                'variables' => json_encode([
                    ['key' => 'records', 'label' => 'Attendance records', 'required' => true, 'type' => 'text', 'grounding' => true],
                    ['key' => 'metrics', 'label' => 'Attendance figures', 'required' => false, 'type' => 'text', 'grounding' => true],
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
                'template_key' => 'k12.attendance.trend',
                'name' => 'Attendance trend explanation',
                'description' => 'Explains what the attendance figures on screen do and do not show over time.',
                'module_key' => self::MODULE,
                'domain' => 'k12',
                'category' => 'report',
                'kind' => 'prompt',
                'version' => 1,
                'status' => 'published',
                'output_format' => 'text',
                'system_prompt' => 'You explain attendance figures to a school leader. Work only from the '
                    . 'records given. A trend needs more than one period: if the data covers a single window, '
                    . 'say that no trend can be read from it rather than describing one. Never invent a '
                    . 'comparison period, and never state or imply a reason for an absence.',
                'user_prompt' => "Explain what these attendance figures show.\n\n"
                    . "Page: {{page_title}}\n"
                    . "Window and filters: {{filters}}\n"
                    . "Figures: {{metrics}}\n"
                    . "Rows:\n{{records}}\n\n"
                    . "Say what the figures support concluding, what they do not, and what would have to be "
                    . "recorded for the remaining question to be answerable.",
                'variables' => json_encode([
                    ['key' => 'records', 'label' => 'Attendance records', 'required' => true, 'type' => 'text', 'grounding' => true],
                    ['key' => 'metrics', 'label' => 'Attendance figures', 'required' => false, 'type' => 'text', 'grounding' => true],
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
                'template_key' => 'k12.attendance.parent_notification',
                'name' => 'Attendance message to a parent',
                'description' => 'A short message to one family about their child\'s attendance.',
                'module_key' => self::MODULE,
                'domain' => 'k12',
                'category' => 'message',
                'kind' => 'prompt',
                'version' => 1,
                'status' => 'published',
                'output_format' => 'text',
                'system_prompt' => 'You write short messages from a school office to a parent about their '
                    . 'child\'s attendance. Work only from the figures given; never state a rate, a name or a '
                    . 'date that is not in them. The school does not know why the child was away, so ask '
                    . 'rather than assume, and never suggest the family is at fault. Warm, brief and specific. '
                    . 'Return the message only — no preamble, no quotes, no labels.',
                'user_prompt' => "Write the message.\n\n"
                    . "Student: {{student_name}}, class {{standard_division}}\n"
                    . "Attendance: {{attendance_rate}} over the last {{window_days}} days\n"
                    . "Days present: {{present_days}} · days absent: {{absent_days}}\n"
                    . "Dates recorded absent: {{absence_dates}}\n"
                    . "What this message is about: {{purpose}}\n\n"
                    . "Two or three sentences. Address the parent, not the student. Invite them to tell the "
                    . "school if there is something it should know.",
                'variables' => json_encode([
                    ['key' => 'student_name', 'label' => 'Student', 'required' => true, 'type' => 'string', 'grounding' => true],
                    ['key' => 'attendance_rate', 'label' => 'Attendance rate', 'required' => false, 'type' => 'string', 'grounding' => true],
                    ['key' => 'absent_days', 'label' => 'Days absent', 'required' => false, 'type' => 'string', 'grounding' => true],
                    ['key' => 'present_days', 'label' => 'Days present', 'required' => false, 'type' => 'string'],
                    ['key' => 'absence_dates', 'label' => 'Dates absent', 'required' => false, 'type' => 'string'],
                    ['key' => 'standard_division', 'label' => 'Class', 'required' => false, 'type' => 'string'],
                    ['key' => 'window_days', 'label' => 'Window (days)', 'required' => false, 'type' => 'string'],
                    ['key' => 'purpose', 'label' => 'What it is about', 'required' => false, 'type' => 'string'],
                ]),
                'safety_rules' => $familyRules,
                // A message about a child's absence goes to a family. Somebody reads it
                // before it is sent.
                'requires_review' => 1,
                'allow_as_evidence' => 0,
                'sub_institute_id' => null,
                'client_id' => null,
            ],
            [
                'template_key' => 'k12.attendance.teacher_follow_up',
                'name' => 'Attendance follow-up note for a teacher',
                'description' => 'An internal note for the class teacher on one student\'s attendance and '
                    . 'what to do next.',
                'module_key' => self::MODULE,
                'domain' => 'k12',
                'category' => 'note',
                'kind' => 'prompt',
                'version' => 1,
                'status' => 'published',
                'output_format' => 'text',
                'system_prompt' => 'You write short internal notes for a class teacher about a student\'s '
                    . 'attendance. Work only from the figures given; never state a rate, a name or a date that '
                    . 'is not in them. This is an internal note, so it may be direct — but it still may not '
                    . 'guess why the child was away. Suggest what to find out, not what to conclude. Return '
                    . 'the note only.',
                'user_prompt' => "Draft the follow-up note.\n\n"
                    . "Student: {{student_name}}, class {{standard_division}}\n"
                    . "Attendance: {{attendance_rate}} over the last {{window_days}} days\n"
                    . "Days present: {{present_days}} · days absent: {{absent_days}}\n"
                    . "Dates recorded absent: {{absence_dates}}\n"
                    . "Anything already known: {{context}}\n\n"
                    . "Three or four lines: what the register shows, what is not yet known, and the next step.",
                'variables' => json_encode([
                    ['key' => 'student_name', 'label' => 'Student', 'required' => true, 'type' => 'string', 'grounding' => true],
                    ['key' => 'attendance_rate', 'label' => 'Attendance rate', 'required' => false, 'type' => 'string', 'grounding' => true],
                    ['key' => 'absent_days', 'label' => 'Days absent', 'required' => false, 'type' => 'string', 'grounding' => true],
                    ['key' => 'present_days', 'label' => 'Days present', 'required' => false, 'type' => 'string'],
                    ['key' => 'absence_dates', 'label' => 'Dates absent', 'required' => false, 'type' => 'string'],
                    ['key' => 'standard_division', 'label' => 'Class', 'required' => false, 'type' => 'string'],
                    ['key' => 'window_days', 'label' => 'Window (days)', 'required' => false, 'type' => 'string'],
                    ['key' => 'context', 'label' => 'Already known', 'required' => false, 'type' => 'string'],
                ]),
                'safety_rules' => $familyRules,
                'requires_review' => 0,
                'allow_as_evidence' => 0,
                'sub_institute_id' => null,
                'client_id' => null,
            ],

            // ---- Report layouts, filled by substitution from attendance.overview --
            [
                'template_key' => self::REPORT_KEY,
                'name' => 'Low Attendance Report',
                'description' => 'Students attending least over the window, worst first, with the cohort rate '
                    . 'and how many could not be judged.',
                'module_key' => self::MODULE,
                'domain' => 'k12',
                'category' => 'report',
                'kind' => 'report',
                // Version 2, and the reason is `ReportTemplateResolver::find()`.
                //
                // It resolves ONE layout per module — school row first, then highest
                // version — so two published platform layouts at the same version would
                // leave the module's default report to whatever order MySQL happened to
                // return. The version is the only tiebreak the resolver offers, so it is
                // what decides: this is the layout an attendance report is built with,
                // and the class sheet below sits at version 1 as the alternative a school
                // can promote from the Templates tab.
                'version' => 2,
                'status' => 'published',
                // NOT NULL on this table, and a report has no prompt.
                'user_prompt' => '',
                'html_layout' => $this->lowAttendanceLayout(),
                'data_source' => self::DATA_SOURCE,
                // The floor the question's own arguments are merged over. `attendance.overview`
                // already returns worst-first, so a limit is a page length and not a filter
                // that could hide the students this report exists to find.
                'data_arguments' => json_encode(['days' => 30, 'limit' => 50]),
                'output_format' => 'text',
                'requires_review' => 0,
                'allow_as_evidence' => 0,
                'sub_institute_id' => null,
                'client_id' => null,
            ],
            [
                'template_key' => self::SUMMARY_REPORT_KEY,
                'name' => 'Class Attendance Report',
                'description' => 'Attendance for a class or division over the window, with present and absent '
                    . 'day counts per student.',
                'module_key' => self::MODULE,
                'domain' => 'k12',
                'category' => 'report',
                'kind' => 'report',
                'version' => 1,
                'status' => 'published',
                'user_prompt' => '',
                'html_layout' => $this->classAttendanceLayout(),
                'data_source' => self::DATA_SOURCE,
                'data_arguments' => json_encode(['days' => 30, 'limit' => 200]),
                'output_format' => 'text',
                'requires_review' => 0,
                'allow_as_evidence' => 0,
                'sub_institute_id' => null,
                'client_id' => null,
            ],
        ];
    }

    /**
     * Offer the module's actions in the Attendance AI panel.
     *
     * `ai_templates` stores a template; `ai_suggestions` is what makes the module's panel
     * offer it as a button. Both are needed, which is why they are written together.
     *
     * THREE PROSE ACTIONS AND ONE DOCUMENT ACTION, AND THE SPLIT IS DELIBERATE
     *
     * `action_type = 'generate'` renders a *prompt* and hands back text to read — right
     * for a summary, an analysis and a trend explanation. `action_type = 'report'` builds
     * a saved document that `/ai-reports/{id}` previews, edits, refreshes, prints and
     * sends, and it names no template: `AiReportGenerator` resolves the module's layout
     * itself. That is the same correction 2026_09_18_120000 made for Fees, applied here
     * from the start so attendance never ships the wrong one.
     *
     * There is one report button, not two, because a `report` action always builds the
     * module's resolved layout — a second button beside it would produce the identical
     * document under a different name. The class sheet is a second layout in the Templates
     * tab, not a second button here.
     */
    private function bindSuggestions(): void
    {
        if (! Schema::hasTable('ai_suggestions')) {
            return;
        }

        $suggestions = [
            ['generate', 'k12.attendance.summary', 'Summarise attendance', 'Summarise the attendance on this page from the marked register.'],
            ['generate', 'k12.attendance.low_attendance', 'Analyse low attendance', 'Identify who is attending least, and what the figures support saying.'],
            ['generate', 'k12.attendance.trend', 'Explain attendance figures', 'Explain what these attendance figures do and do not show.'],
            ['report', null, 'Attendance report', 'Build the attendance report document from the marked register.'],
        ];

        foreach ($suggestions as $index => [$actionType, $ref, $label, $description]) {
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
                // Cohort actions, not per-record ones: offered on the attendance list
                // pages rather than gated behind selecting one student.
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
     * Placeholders come in three kinds, and this uses all three:
     *
     *   `<<report_title>>`              report-level. One value per report.
     *   `<<cohort_attendance_rate>>`    a figure the DATA SOURCE computed. Not recomputed
     *                                   from the rows below, which are often a window onto
     *                                   a larger cohort — the two would disagree.
     *   `<<#rows>> … <</rows>>`         repeated once per student in `students`.
     *
     * Written with plain `<<token>>` delimiters. The HTML editor stores the same
     * placeholders HTML-encoded when a person edits this in the browser, and the renderer
     * accepts either spelling — so editing and re-saving does not break it.
     */
    private function lowAttendanceLayout(): string
    {
        return <<<'HTML'
<div style="font-family:Segoe UI,Arial,sans-serif;color:#0f172a">

  <h2 style="margin:0 0 4px 0;font-size:20px"><<report_title>></h2>
  <p style="margin:0 0 16px 0;color:#475569;font-size:13px">
    <<row_count>> student(s) listed, lowest attendance first &middot;
    window of <<window_days>> day(s) from <<since>> &middot; generated <<generated_at>>
  </p>

  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:#f1f5f9">
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left;width:36px">#</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Student</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Std</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Div</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:right">Present</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:right">Absent</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:right">Rate</th>
      </tr>
    </thead>
    <tbody>
      <<#rows>>
      <tr>
        <td style="border:1px solid #cbd5e1;padding:6px"><<row_number>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<student_name>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<standard_name>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<division_name>></td>
        <td style="border:1px solid #cbd5e1;padding:6px;text-align:right"><<present_days>></td>
        <td style="border:1px solid #cbd5e1;padding:6px;text-align:right"><<absent_days>></td>
        <td style="border:1px solid #cbd5e1;padding:6px;text-align:right"><<attendance_percent>></td>
      </tr>
      <</rows>>
    </tbody>
  </table>

  <p style="margin:16px 0 0 0;font-size:13px">
    <strong>Cohort attendance rate: <<cohort_attendance_percent>></strong>
    across <<students_judged>> student(s) with enough marked days to judge.
    <<students_with_insufficient_data>> student(s) had too few marked days and are excluded
    rather than shown as zero.
  </p>

  <p style="margin:20px 0 0 0;color:#64748b;font-size:11px">
    <<rule>>
  </p>

</div>
HTML;
    }

    private function classAttendanceLayout(): string
    {
        return <<<'HTML'
<div style="font-family:Segoe UI,Arial,sans-serif;color:#0f172a">

  <h2 style="margin:0 0 4px 0;font-size:20px"><<report_title>></h2>
  <p style="margin:0 0 16px 0;color:#475569;font-size:13px">
    <<row_count>> student(s) &middot; window of <<window_days>> day(s) from <<since>> &middot;
    academic year <<academic_year>> &middot; generated <<generated_at>>
  </p>

  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:#f1f5f9">
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left;width:36px">#</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Student</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Std / Div</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:right">Present</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:right">Absent</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:right">Uncoded</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:right">Rate</th>
      </tr>
    </thead>
    <tbody>
      <<#rows>>
      <tr>
        <td style="border:1px solid #cbd5e1;padding:6px"><<row_number>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<student_name>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<standard_name>> / <<division_name>></td>
        <td style="border:1px solid #cbd5e1;padding:6px;text-align:right"><<present_days>></td>
        <td style="border:1px solid #cbd5e1;padding:6px;text-align:right"><<absent_days>></td>
        <td style="border:1px solid #cbd5e1;padding:6px;text-align:right"><<uncoded_days>></td>
        <td style="border:1px solid #cbd5e1;padding:6px;text-align:right"><<attendance_percent>></td>
      </tr>
      <</rows>>
    </tbody>
  </table>

  <p style="margin:16px 0 0 0;font-size:13px">
    <strong>Cohort attendance rate: <<cohort_attendance_percent>></strong>
    across <<students_judged>> student(s) judged;
    <<students_with_insufficient_data>> had too few marked days to include.
  </p>

  <p style="margin:20px 0 0 0;color:#64748b;font-size:11px">
    <<rule>>
  </p>

</div>
HTML;
    }
};
