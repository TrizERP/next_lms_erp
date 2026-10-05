<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The prompts and report layouts for the six modules registered by 2026_09_23_100000.
 *
 * Built exactly like 2026_09_22_100100: one declaration, one loop, two cohort prompts and
 * one report layout per module. See that file for why the shape is shared and why the
 * refusals are the part that genuinely differs.
 *
 * EVERY PLACEHOLDER IS A FIELD THE BOUND TOOL REALLY RETURNS
 *
 * The column lists below were taken from the services, not from the tables:
 * `MobileAppService::homescreen()` maps `section, label, opens_screen, user_profile,
 * enabled`; `StudentIcardService::roster()` maps `student_name, roll_no, standard_name,
 * missing_fields`; and so on. `AiReportGenerator::scalarsOf()` carries each payload's
 * top-level scalars through, which is where `<<count>>` comes from.
 *
 * WHAT EACH MODULE MUST REFUSE TO SAY
 *
 *   Mobile Apps      - the tables hold configuration, not usage. Nothing may report
 *                      adoption, downloads, active users or sessions; none is recorded.
 *   Student I-Card   - a missing photo is a detail the office has not collected, not a
 *                      child who may not have a card. And a card is a name and a face:
 *                      nothing may characterise the student on it.
 *   Certificate      - the printed document is not returned to the model, so nothing may
 *                      quote or paraphrase what a certificate says about a child.
 *   Communication    - only WhatsApp records delivery. Nothing may report reach, open
 *                      rates or receipt for SMS or app notifications.
 *   Time Table       - the table holds no room and no teacher availability, so the only
 *                      judgement available is a clash. Nothing may call a timetable
 *                      balanced, fair or overloaded.
 *   Student Medical  - the hardest line in this file. Nothing may diagnose, infer a
 *                      condition, describe a pattern across visits, or say a child is
 *                      unwell. A clinical judgement is a clinician's, and an absent record
 *                      is an absent record — never evidence of health.
 *
 * NO RECORD APPEARS IN THIS FILE. No child, no certificate number, no message, no
 * clinical detail. Every value is a placeholder filled at runtime by `ModuleToolData` or
 * `AiReportGenerator` from the caller's own institute and year.
 *
 * NOTHING BELONGING TO AN EXISTING MODULE IS TOUCHED. Every template key begins
 * `k12.mobile_apps.`, `k12.student_icard.`, `k12.certificate.`, `k12.easy_com.`,
 * `k12.timetable.` or `k12.student_medical.`, none of which exists yet.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_23_100100_publish_six_module_ai_templates.php
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
            'mobile_apps' => [
                'label' => 'Users Mobile Apps',
                'noun' => 'mobile app tile',
                'plural' => 'mobile app tiles',
                'data_source' => 'mobile_apps.homescreen',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Mobile App Home Screen Report',
                'report_description' => 'The tiles configured on the mobile app home screen, with the '
                    .'section each sits under and whether it is switched on.',
                'summary_focus' => [
                    'Which sections the home screen is built from, and how many tiles sit under each.',
                    'Which tiles are switched off, and for which user profile.',
                    'Which screens and APIs the tiles point at.',
                ],
                'analysis_focus' => [
                    'Whether any section is empty or has every tile switched off.',
                    'Whether the parent app and the teacher app differ in ways that look unintended.',
                    'What the configuration does not tell you, and why.',
                    'One or two concrete next steps an administrator could take.',
                ],
                'system_role' => 'You summarise mobile app home-screen configuration for an administrator.',
                'refusals' => [
                    'Do not invent a section, a tile, a screen, a user profile or a count.',
                    'These rows are configuration, not usage. Never report downloads, installs, active users, sessions or adoption — this system records none of them.',
                    'A tile being switched on does not mean anybody has used it. Never imply that it does.',
                    'Do not recommend switching a tile on or off for a profile you were given no rows for.',
                ],
                'columns' => [
                    ['Section', 'section'],
                    ['Tile', 'label'],
                    ['Profile', 'user_profile'],
                    ['Opens', 'opens_screen'],
                    ['Enabled', 'enabled'],
                ],
                'footer' => 'Tiles matched: <<count>> &mdash; of which <<tiles_enabled>> are switched on and '
                    .'<<tiles_disabled>> are off. These rows are the app\'s configured navigation; this '
                    .'system records no session, device or login, so nothing here describes app usage.',
            ],

            'student_icard' => [
                'label' => 'Student I-Card',
                'noun' => 'identity card',
                'plural' => 'identity cards',
                'data_source' => 'student_icard.roster',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Student I-Card Print List',
                'report_description' => 'The students a card can be printed for, and which cards are '
                    .'missing a photo, a roll number or a class.',
                'summary_focus' => [
                    'How many cards are ready to print and how many are missing something.',
                    'Which fields are missing most often.',
                    'Which classes the listed students are in.',
                ],
                'analysis_focus' => [
                    'Which classes have the most cards not ready, and what is missing in each.',
                    'Whether transport details are recorded for the students who need them on a card.',
                    'What the roster does not tell you.',
                    'One or two concrete next steps the office could take before a print run.',
                ],
                'system_role' => 'You summarise an identity-card print list for a school office.',
                'refusals' => [
                    'Do not invent a student, a roll number, a class, a photo or a count.',
                    'A missing photo or roll number is a detail the office has not collected. Never describe it as a student who is not entitled to a card.',
                    'A card carries a name, a class and a face. Never say anything about the student beyond the fields you were given.',
                    'Do not state when a card was printed or issued. This system records no card history.',
                ],
                'columns' => [
                    ['Student', 'student_name'],
                    ['Roll', 'roll_no'],
                    ['Enrolment no.', 'enrollment_no'],
                    ['Standard', 'standard_name'],
                    ['Division', 'division_name'],
                    ['Blood group', 'blood_group'],
                    ['Bus', 'bus'],
                    ['Ready', 'card_ready'],
                ],
                'footer' => 'Students matched: <<count>> &mdash; of which <<card_ready>> have every card field '
                    .'recorded and <<missing_something>> are missing a photo, a roll number or a class. A '
                    .'blank cell is a detail the school has not recorded.',
            ],

            'certificate' => [
                'label' => 'Certificate',
                'noun' => 'certificate',
                'plural' => 'certificates',
                'data_source' => 'certificate.issued',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Certificate Issue Register',
                'report_description' => 'The certificates issued this academic year, with the type, number '
                    .'and student on each.',
                'summary_focus' => [
                    'How many certificates were issued and of which types.',
                    'Over what span of dates they were issued.',
                    'Whether every issued certificate has a number and a stored document.',
                ],
                'analysis_focus' => [
                    'Which certificate types are issued most and least.',
                    'Whether any issued certificate is missing a number or a stored document.',
                    'What the register cannot answer.',
                    'One or two concrete next steps the office could take.',
                ],
                'system_role' => 'You summarise a certificate issue register for a school office.',
                'refusals' => [
                    'Do not invent a certificate, a number, a type, a student or a date.',
                    'You have not been given the printed certificate text. Never quote, paraphrase or summarise what a certificate says about a child.',
                    'A certificate records that a document was issued. Never infer why it was requested, or anything about the family from the type.',
                    'Do not state that a certificate is valid, invalid, approved or pending. The register records issue only.',
                ],
                'columns' => [
                    ['Student', 'student_name'],
                    ['Enrolment no.', 'enrollment_no'],
                    ['Type', 'certificate_type'],
                    ['Number', 'certificate_number'],
                    ['Issued on', 'issued_on'],
                    ['Document', 'document_stored'],
                ],
                'footer' => 'Certificates matched: <<count>> &mdash; every row is a certificate issued by '
                    .'this institute in this academic year. The printed text is stored but is deliberately '
                    .'not reproduced here; open the certificate on the Certificate screen to read it.',
            ],

            'easy_com' => [
                'label' => 'Communication',
                'noun' => 'message',
                'plural' => 'messages',
                'data_source' => 'communication.messages',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Communication Register',
                'report_description' => 'What the school has sent across SMS, WhatsApp and app '
                    .'notifications this academic year.',
                'summary_focus' => [
                    'How many messages were sent, broken down by channel.',
                    'Over what span of dates, and what triggered them where that is recorded.',
                    'Which channels record a delivery outcome and which do not.',
                ],
                'analysis_focus' => [
                    'Which channels the school actually uses and which sit idle.',
                    'For WhatsApp only, what the recorded delivery outcomes show.',
                    'What the register cannot answer about the other channels.',
                    'One or two concrete next steps the office could take.',
                ],
                'system_role' => 'You summarise a school\'s communication register for the front office.',
                'refusals' => [
                    'Do not invent a message, a recipient, a channel, a date or a count.',
                    'Only WhatsApp records a delivery outcome. For SMS and app notifications, never state or estimate that a message was received, read or opened — the log records only that it was submitted.',
                    'Never report a reach, open rate or response rate. None is recorded anywhere in this system.',
                    'Do not quote a message about one named family back in a summary read by others.',
                ],
                'columns' => [
                    ['Channel', 'channel_label'],
                    ['Sent to', 'sent_to'],
                    ['Message', 'message'],
                    ['Sent at', 'sent_at'],
                    ['Triggered by', 'triggered_by'],
                    ['Delivery', 'delivery_status'],
                ],
                'footer' => 'Messages matched: <<count>> across every channel asked for. A blank delivery '
                    .'column means that channel records no delivery outcome &mdash; the send was logged, '
                    .'which is not the same as the message arriving.',
            ],

            'timetable' => [
                'label' => 'Time Table',
                'noun' => 'timetable entry',
                'plural' => 'timetable entries',
                'data_source' => 'timetable.schedule',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Class Timetable Report',
                'report_description' => 'The published timetable, one row per period of one class on one '
                    .'weekday.',
                'summary_focus' => [
                    'Which classes, weekdays and periods the listed entries cover.',
                    'Which subjects and teachers appear, and how often.',
                    'Whether any entry is missing a subject or a teacher.',
                ],
                'analysis_focus' => [
                    'How the periods are distributed across weekdays for the classes listed.',
                    'Whether any entry has no teacher or no subject recorded.',
                    'What the timetable does not record, and why that limits what can be said.',
                    'One or two concrete next steps the timetable office could take.',
                ],
                'system_role' => 'You summarise a published class timetable for a school office.',
                'refusals' => [
                    'Do not invent a period, a subject, a teacher, a class or a weekday.',
                    'This table records no room and no teacher availability. Never say a timetable is balanced, fair, efficient or overloaded, and never say a teacher is free at a given time.',
                    'The only clash you may report is one already reported to you: a teacher in two different classes in the same period on the same day. Do not derive any other conflict.',
                    'Do not suggest moving a period unless you were given every entry it would affect.',
                ],
                'columns' => [
                    ['Day', 'week_day_name'],
                    ['Period', 'period'],
                    ['From', 'start_time'],
                    ['To', 'end_time'],
                    ['Standard', 'standard_name'],
                    ['Division', 'division_name'],
                    ['Subject', 'subject_name'],
                    ['Teacher', 'teacher_name'],
                ],
                'footer' => 'Entries matched: <<count>> &mdash; one row is one period of one class on one '
                    .'weekday, from the published timetable. Drafts still being arranged are not included. '
                    .'A blank teacher is an entry with no teacher recorded, or one naming a user of '
                    .'another institute.',
            ],

            'student_medical' => [
                'label' => 'Student Medical',
                'noun' => 'infirmary visit',
                'plural' => 'infirmary visits',
                'data_source' => 'student_medical.visits',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Infirmary Visit Register',
                'report_description' => 'Infirmary visits recorded this academic year, with the date, case '
                    .'number, attending doctor and whether the case is still open.',
                'summary_focus' => [
                    'How many visits are listed and how many cases are still open.',
                    'Over what span of dates, and at which health centres.',
                    'Which doctors attended, where that is recorded.',
                ],
                'analysis_focus' => [
                    'How many cases remain open and how long they have been open.',
                    'Whether any visit is missing a case number, a date or an attending doctor.',
                    'What the register does not record, and why that limits what can be said.',
                    'One or two concrete administrative next steps — not clinical ones.',
                ],
                'system_role' => 'You summarise an infirmary visit register for a school office. You are '
                    .'not a clinician and this is not a clinical document.',
                'refusals' => [
                    'Do not invent a visit, a student, a date, a case number or a count.',
                    'NEVER diagnose, suggest a diagnosis, or name a condition that is not written in a record you were given.',
                    'NEVER describe a pattern, trend or recurrence across a student\'s visits, and never say a child is unwell, frail, at risk or frequently ill. Several visits is a count, not a finding.',
                    'A student with no record has no record. Never report that as healthy, well or unaffected.',
                    'Do not name a student\'s complaint, symptoms, disease or treatment in a summary covering more than one student, even if you were given them.',
                    'Do not recommend treatment, medication, exclusion from school, or contacting a parent about a child\'s health. Those are decisions for a clinician and the school office.',
                ],
                'columns' => [
                    ['Student', 'student_name'],
                    ['Enrolment no.', 'enrollment_no'],
                    ['Standard', 'standard_name'],
                    ['Division', 'division_name'],
                    ['Case no.', 'case_no'],
                    ['Visit date', 'visit_date'],
                    ['Closed on', 'closed_on'],
                    ['Doctor', 'doctor_name'],
                    ['Health centre', 'health_center'],
                ],
                'footer' => 'Visits matched: <<count>> &mdash; of which <<open_cases>> have no close date '
                    .'recorded. Complaint, symptoms, disease and treatment are deliberately omitted from a '
                    .'register covering more than one student; they are recorded, and are read one student '
                    .'at a time on the Student Infirmary screen.',
            ],
        ];
    }

    // ------------------------------------------------------------------ construction

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

        // Student Medical is the one module whose output a person must read before it is
        // used anywhere. Everything else here is an internal administrative summary.
        $needsReview = $moduleKey === 'student_medical';

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
