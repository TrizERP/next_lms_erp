<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The prompts and report layouts for the modules bound by 2026_09_26_100000.
 *
 * Six modules, four of them newly given data tools and two — LMS and Institute — that had
 * tools bound since the workspace was seeded and have never had a prompt of their own.
 * Same shape as its three predecessors: one declaration, two cohort prompts and one report
 * layout per module.
 *
 * WHAT EACH MODULE MUST REFUSE TO SAY
 *
 *   Parent Communication - it is the INBOUND direction and is not the Communication
 *                          module. An unanswered message means nobody has replied yet,
 *                          never that the school refused. The body of a named family's
 *                          letter never appears in a cohort summary.
 *   SQAA                 - nothing scores a school. No rubric, weighting or grade boundary
 *                          exists, and the 1,534 document slots must always be reported
 *                          beside the handful of uploads.
 *   Users                - only account fields; the staff record's payroll and identity
 *                          columns are unreadable. `last_login` is the only activity
 *                          recorded, so nothing describes how much anybody uses the system.
 *   Library              - one row is a TITLE, not a book on the shelf. No fine,
 *                          reservation or renewal is recorded.
 *   LMS                  - courses and activities are configuration; nothing here reports
 *                          what a child learned or how well.
 *   Institute            - the academic structure and departments, and nothing about the
 *                          people in them beyond a count.
 *
 * WHY USERS IS THE ONE THAT NEEDS REVIEWING
 *
 * `requires_review = 1` is set for Users alone. Its output is about colleagues by name —
 * who has an account, who has never logged in — and the one mistake it is most likely to
 * make is the one that reads as a judgement on a person's work. Somebody should see it
 * before it travels.
 *
 * NO RECORD APPEARS IN THIS FILE. Every value is a placeholder filled at runtime from the
 * caller's own institute and year.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_26_100100_publish_remaining_module_ai_templates.php
 */
return new class extends Migration
{
    public function up(): void
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
                // Never touch a version an institute has forked: those rows carry a
                // sub_institute_id and are excluded by the query above.
                DB::table('ai_templates')->where('id', $existing->id)->update($template + ['updated_at' => now()]);

                continue;
            }

            DB::table('ai_templates')->insert($template + ['created_at' => now(), 'updated_at' => now()]);
        }

        $this->bindSuggestions();

        foreach (array_keys($this->modules()) as $moduleKey) {
            $this->setModuleCapabilities($moduleKey, ['generative' => true]);
        }
    }

    public function down(): void
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

        // Retired, not deleted: a generation request row references the template it ran
        // against, and deleting it would orphan the audit trail.
        DB::table('ai_templates')
            ->whereIn('template_key', array_column($this->rows(), 'template_key'))
            ->whereNull('sub_institute_id')
            ->update(['status' => 'retired', 'updated_at' => now()]);

        foreach (array_keys($this->modules()) as $moduleKey) {
            $this->setModuleCapabilities($moduleKey, ['generative' => false]);
        }
    }

    // ------------------------------------------------------------------ declaration

    /**
     * What differs between the six modules, and nothing else.
     *
     * @return array<string, array<string, mixed>>
     */
    private function modules(): array
    {
        return [
            'parent_communication' => [
                'label' => 'Parent Communication',
                'noun' => 'parent message',
                'plural' => 'messages from parents',
                'data_source' => 'parent_communication.messages',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Parent Message Register',
                'report_description' => 'Messages parents wrote to the school, with the student each '
                    .'concerns and whether a reply is recorded.',
                'summary_focus' => [
                    'How many messages arrived, and over what span of dates.',
                    'How many have a reply recorded and how many are still waiting.',
                    'How long the oldest unanswered message has been waiting.',
                ],
                'analysis_focus' => [
                    'Whether the unanswered messages cluster in a period or a class.',
                    'How quickly replies are being recorded where they exist.',
                    'What the register cannot tell you, including why anything is unanswered.',
                    'One or two concrete next steps the office could take.',
                ],
                'system_role' => 'You summarise messages parents wrote to a school, for the school office.',
                'refusals' => [
                    'Do not invent a message, a parent, a student, a date or a count.',
                    'This is the INBOUND direction — what parents sent IN. It is not the Communication module, which records what the school SENDS. Never combine the two or give one total for both.',
                    'A message with no reply means NOBODY HAS ANSWERED IT YET. Never report it as refused, declined, dismissed or ignored, and never infer why nobody has answered.',
                    'If you were given no message bodies, you were given none on purpose: this read covers more than one family. Never quote, paraphrase or characterise what a parent wrote, and never guess a message\'s content from its title.',
                    'Days since received is arithmetic on a date. This table records no promised response time, so it is not evidence that anything is late.',
                ],
                'columns' => [
                    ['Title', 'title'],
                    ['About', 'student_name'],
                    ['Received', 'received_on'],
                    ['Answered', 'answered'],
                    ['Replied by', 'replied_by'],
                    ['Days waiting', 'days_since_received'],
                ],
                'footer' => 'Messages matched: <<count>> &mdash; <<answered>> with a reply recorded and '
                    .'<<unanswered>> still waiting, over every matching message rather than only the rows '
                    .'listed. A message with no reply has not been refused; nobody has answered it yet. '
                    .'The message bodies are deliberately not reproduced in a register covering more than '
                    .'one family.',
            ],

            'sqaa' => [
                'label' => 'Quality assurance',
                'noun' => 'evidence record',
                'plural' => 'quality assurance evidence records',
                'data_source' => 'sqaa.evidence',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Quality Assurance Evidence Register',
                'report_description' => 'Evidence uploaded against the SQAA document slots, with whether a '
                    .'file is actually attached.',
                'summary_focus' => [
                    'How many document slots are defined and how many evidence rows exist against them.',
                    'How many of those evidence rows actually carry a file.',
                    'Which slots the evidence covers.',
                ],
                'analysis_focus' => [
                    'Where evidence is marked available but no file is attached.',
                    'How large the gap is between slots defined and evidence recorded.',
                    'What this register cannot tell you — above all, anything resembling a score.',
                    'One or two concrete next steps the quality team could take.',
                ],
                'system_role' => 'You summarise quality assurance evidence for a school quality team.',
                'refusals' => [
                    'Do not invent a criterion, a document slot, an evidence row or a count.',
                    'NOTHING HERE SCORES A SCHOOL. No rubric, weighting or grade boundary is recorded anywhere and the marks table holds a handful of rows. Never state a score, a rating, a band, a percentage of readiness, or that the school is ready or not ready for assessment.',
                    'The number of document slots and the number of evidence rows must ALWAYS be reported together. A count of uploads quoted alone reads as progress when most slots are empty.',
                    '`marked_available` is what somebody ticked; `file_attached` is whether a file is there. They are two different facts and neither stands for the other.',
                    'Do not judge the quality, sufficiency or relevance of any piece of evidence. That is an assessor\'s judgement and you have not seen the document.',
                ],
                'columns' => [
                    ['Evidence', 'title'],
                    ['Document slot', 'document_slot'],
                    ['Marked available', 'marked_available'],
                    ['File attached', 'file_attached'],
                    ['Uploaded by', 'uploaded_by'],
                    ['Uploaded on', 'uploaded_on'],
                ],
                'footer' => 'Document slots defined: <<document_slots_defined>>. Evidence rows recorded: '
                    .'<<evidence_rows_recorded>>, of which <<evidence_with_a_file>> carry a file. These '
                    .'two figures belong together: a count of uploads on its own reads as progress when '
                    .'most slots are empty. Nothing in this register scores the school.',
            ],

            'user' => [
                'label' => 'Users',
                'noun' => 'user account',
                'plural' => 'user accounts',
                'data_source' => 'user_accounts.directory',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'User Account Register',
                'report_description' => 'The ERP accounts in this institute, their profiles and their '
                    .'status.',
                'summary_focus' => [
                    'How many accounts there are and which profiles they sit under.',
                    'How many have never recorded a login.',
                    'How many are administrators.',
                ],
                'analysis_focus' => [
                    'Which profiles hold the most accounts.',
                    'How many accounts have no login recorded, and what that does and does not mean.',
                    'What the account register cannot tell you about how the system is used.',
                    'One or two concrete next steps an administrator could take.',
                ],
                'system_role' => 'You summarise ERP user accounts for a school administrator.',
                'refusals' => [
                    'Do not invent a person, a username, a profile or a count.',
                    'You have been given ACCOUNT fields only. The staff record also holds salary, bank, PAN, Aadhaar, provident fund and contract details — you have not been given any of them and must never refer to, estimate or ask about them.',
                    '`last_login` is the ONLY activity recorded. There is no session log, no page view and no action history, so never say how much anybody uses the system, who is most or least active, or that anybody is not doing their work.',
                    'A null last login means no login has been RECORDED against the account. It is NOT proof the person has never signed in — the column may simply not have been populated.',
                    'Never rank, compare or comment on named colleagues. This is a register of accounts, not an assessment of people.',
                ],
                'columns' => [
                    ['Name', 'name'],
                    ['Username', 'username'],
                    ['Profile', 'user_profile'],
                    ['Active', 'active'],
                    ['Administrator', 'is_administrator'],
                    ['Last login', 'last_login'],
                ],
                'footer' => 'Accounts matched: <<count>> &mdash; of which <<never_logged_in>> have no login '
                    .'RECORDED and <<administrators>> are administrators. A missing last login is a '
                    .'missing record and not proof that somebody has never signed in. This register '
                    .'carries account fields only; payroll and identity columns are not readable.',
            ],

            'library' => [
                'label' => 'Library',
                'noun' => 'library loan',
                'plural' => 'library loans',
                'data_source' => 'library.circulation',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Library Circulation Report',
                'report_description' => 'Loans issued from the library, with the ones still out and the '
                    .'ones overdue.',
                'summary_focus' => [
                    'How many loans were issued and over what span of dates.',
                    'How many are still out and how many are past their due date.',
                    'Which titles appear most often.',
                ],
                'analysis_focus' => [
                    'How long the overdue loans have been overdue.',
                    'Whether the overdue ones concentrate on particular titles or classes.',
                    'What the circulation record cannot tell you, including anything owed.',
                    'One or two concrete next steps the librarian could take.',
                ],
                'system_role' => 'You summarise library circulation for a school librarian.',
                'refusals' => [
                    'Do not invent a title, an author, a borrower, a date or a count.',
                    'A loan is OUT when no return date is recorded and OVERDUE when the due date has also passed. A loan with no due date is undated, not overdue — never include one in an overdue count.',
                    'This system records NO fine, penalty, reservation or renewal. Never state what a borrower owes, that a book is reserved, or how many times anything was renewed.',
                    'One catalogue row is a TITLE, not a book on the shelf. Never report a count of titles as a count of books.',
                    'A child with an overdue book has an overdue book. Never describe a named borrower as unreliable, careless or a repeat offender.',
                ],
                'columns' => [
                    ['Title', 'book_title'],
                    ['Borrower', 'student_name'],
                    ['Issued', 'issued_on'],
                    ['Due', 'due_on'],
                    ['Returned', 'returned_on'],
                    ['Days past due', 'days_past_due'],
                ],
                'footer' => 'Loans matched: <<count>> &mdash; <<currently_out>> still out and <<overdue>> '
                    .'past their due date, over every matching loan rather than only the rows listed. No '
                    .'fine, reservation or renewal is recorded anywhere in this system.',
            ],

            'lms' => [
                'label' => 'Learning',
                'noun' => 'course',
                'plural' => 'courses',
                'data_source' => 'lms.courses',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Course Register',
                'report_description' => 'The courses configured for this institute and the activities '
                    .'recorded against them.',
                'summary_focus' => [
                    'How many courses are configured and for which classes.',
                    'What activities are recorded against them.',
                    'Which courses have no activity recorded at all.',
                ],
                'analysis_focus' => [
                    'Where courses are configured but carry no activity.',
                    'How the courses are spread across classes and subjects.',
                    'What the course records cannot tell you about learning.',
                    'One or two concrete next steps a coordinator could take.',
                ],
                'system_role' => 'You summarise course configuration for a school coordinator.',
                'refusals' => [
                    'Do not invent a course, an activity, a class or a count.',
                    'These records are CONFIGURATION — what has been set up. They are not achievement: never state what a child learned, how well anybody did, or that a course was effective.',
                    'A course with no activity recorded is a course with no activity RECORDED. Never describe it as neglected or a teacher as inactive.',
                    'Do not compare teachers, classes or subjects by course count. The number of courses configured is not a measure of anybody\'s work.',
                    'Exam results and attendance belong to other modules and you have not been given them. Never infer either from a course record.',
                ],
                'columns' => [
                    ['Course', 'title'],
                    ['Subject', 'subject_name'],
                    ['Standard', 'standard_name'],
                    ['Division', 'division_name'],
                    ['Status', 'status'],
                ],
                'footer' => 'Courses matched: <<count>>. These rows are how learning has been CONFIGURED; '
                    .'they record nothing about what any child learned or how well, which belongs to the '
                    .'Exam and Attendance modules.',
            ],

            'institute' => [
                'label' => 'Institute',
                'noun' => 'academic structure record',
                'plural' => 'academic structure records',
                'data_source' => 'academics.structure',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Institute Structure Report',
                'report_description' => 'The academic structure of this institute: sections, standards and '
                    .'divisions.',
                'summary_focus' => [
                    'How the institute is structured into sections, standards and divisions.',
                    'How many divisions each standard has.',
                    'Which departments are recorded.',
                ],
                'analysis_focus' => [
                    'Where the structure looks uneven or incomplete.',
                    'Whether any standard has no division recorded.',
                    'What the structure cannot tell you about the people in it.',
                    'One or two concrete next steps an administrator could take.',
                ],
                'system_role' => 'You summarise the academic structure of a school for an administrator.',
                'refusals' => [
                    'Do not invent a section, a standard, a division or a department.',
                    'This is the SHAPE of the institute, not its people. Never state how many students or staff are in anything unless you were given that figure, and never name anybody.',
                    'Do not judge the structure as good, efficient or appropriate. Nothing here records why it is arranged as it is.',
                    'Enrolment, fees, results and attendance belong to other modules and you have not been given them.',
                ],
                'columns' => [
                    ['Section', 'section_title'],
                    ['Standard', 'standard_name'],
                    ['Division', 'division_name'],
                ],
                'footer' => 'Structure records matched: <<count>>. This is the shape of the institute and '
                    .'not a count of the people in it.',
            ],
        ];
    }

    /**
     * Every `ai_templates` row this migration writes, built from the declaration above.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rows(): array
    {
        $rows = [];

        foreach ($this->modules() as $moduleKey => $module) {
            $rules = json_encode($module['refusals']);

            $rows[] = $this->prompt(
                $moduleKey,
                $module,
                'summary',
                $module['label'].' summary',
                'A short summary of the '.$module['plural'].' on the screen.',
                'Summarise the '.$module['plural'].' on this page.',
                $module['summary_focus'],
                $rules,
            );

            $rows[] = $this->prompt(
                $moduleKey,
                $module,
                'analysis',
                $module['label'].' analysis',
                'What the '.$module['plural'].' show, and what the records do not support saying.',
                'Analyse the '.$module['plural'].' in these records.',
                $module['analysis_focus'],
                $rules,
            );

            $rows[] = $this->report($moduleKey, $module);
        }

        return $rows;
    }

    /**
     * One cohort prompt.
     *
     * @param  array<string, mixed>  $module
     * @param  array<int, string>  $focus
     * @return array<string, mixed>
     */
    private function prompt(
        string $moduleKey,
        array $module,
        string $slug,
        string $name,
        string $description,
        string $instruction,
        array $focus,
        string $rules,
    ): array {
        $cover = '';

        foreach ($focus as $index => $line) {
            $cover .= ($index + 1).'. '.$line."\n";
        }

        // Users is the one module here whose output a person must read before it is used
        // anywhere. It is about colleagues by name — who has an account, who has never
        // logged in — and the mistake it is most likely to make is the one that reads as
        // a judgement on somebody's work. The other five are administrative summaries.
        $needsReview = $moduleKey === 'user';

        return [
            'template_key' => 'k12.'.$moduleKey.'.'.$slug,
            'name' => $name,
            'description' => $description,
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
            'user_prompt' => $instruction."\n\n"
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
            'requires_review' => $needsReview ? 1 : 0,
            'allow_as_evidence' => 0,
            'sub_institute_id' => null,
            'client_id' => null,
        ];
    }

    /**
     * One report layout, bound to the module's own read tool.
     *
     * @param  array<string, mixed>  $module
     * @return array<string, mixed>
     */
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
            // NOT NULL on this table, and a report has no prompt.
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

    /**
     * The HTML layout for one module's report.
     *
     * Placeholders come in three kinds and this uses all three: `<<report_title>>` at
     * report level, `<<count>>` from the data source's own scalars, and
     * `<<#rows>> … <</rows>>` repeated per record.
     *
     * @param  array<string, mixed>  $module
     */
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
  <p style="margin:6px 0 0 0;color:#475569;font-size:12px">
    A blank cell is a detail the school has not recorded, not a detail that does not exist.
  </p>

</div>
HTML;
    }

    // ------------------------------------------------------------------ suggestions

    /**
     * @param  array<string, mixed>  $module
     * @return array<int, array{0:string,1:?string,2:string,3:string}>
     */
    private function suggestionsFor(string $moduleKey, array $module): array
    {
        return [
            [
                'generate',
                'k12.'.$moduleKey.'.summary',
                'Summarise '.$module['plural'],
                'Summarise the '.$module['plural'].' on this page from the records themselves.',
            ],
            [
                'generate',
                'k12.'.$moduleKey.'.analysis',
                'Analyse '.$module['plural'],
                'What the '.$module['plural'].' show, and what the records do not support saying.',
            ],
            [
                'report',
                null,
                $module['report_name'],
                'Build the '.strtolower($module['report_name']).' document from the '.$module['noun'].' records.',
            ],
        ];
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

    /**
     * Flip only the named flags, merging into whatever the row already holds.
     *
     * @param  array<string, bool>  $flags
     */
    private function setModuleCapabilities(string $moduleKey, array $flags): void
    {
        if (! Schema::hasTable('ai_modules')) {
            return;
        }

        $rows = DB::table('ai_modules')->where('module_key', $moduleKey)->get(['id', 'capabilities']);

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
};
