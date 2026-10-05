<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The prompts and report layouts for Exam, PTM, Hostel, Student Request and Circular.
 *
 * ONE MIGRATION, FIVE MODULES, AND THAT IS THE POINT
 *
 * The admissions and students template migrations are one file each because each was
 * written on its own. These five arrive together and share one shape exactly - two cohort
 * prompts and one report layout per module, differing only in which tool grounds them,
 * which fields their layout prints and what each must refuse to say. Writing that out five
 * times would be five files to keep in step; `modules()` below is the declaration and the
 * loop in `up()` is the whole of the logic.
 *
 * WHAT A PROMPT IS AND WHAT A REPORT IS
 *
 * Both are `ai_templates` rows, separated by `kind`. A prompt is text sent to a model,
 * which writes prose and may not state a figure on its own authority. A report is an HTML
 * layout whose placeholders are filled by substitution from rows a tool fetched, with no
 * model anywhere near the numbers. That is why the layouts below can print a room number
 * and the prompts below cannot.
 *
 * NO REAL RECORD APPEARS IN THIS FILE
 *
 * Not a meeting, not a room, not a request, not a circular, not a child. Every value is a
 * placeholder. The cohort prompts are filled by `ModuleToolData`, which reads the module's
 * bound read tools as the person who asked, scoped to their institute and academic year by
 * their own token. The layouts are filled by `AiReportGenerator` from whatever the bound
 * tool returned. There is no sample row anywhere in this AI stack and there must not be.
 *
 * EVERY PLACEHOLDER IS A FIELD THE TOOL REALLY RETURNS
 *
 * The field lists below were taken from the services themselves, not from the tables:
 * `PtmMeetingService::meetings()` maps exactly `slot_id, title, ptm_date, from_time,
 * to_time, standard_name, division_name, students_booked, attended, did_not_attend,
 * attendance_not_recorded`, and so on for the other four. `AiReportGenerator::scalarsOf()`
 * carries each payload's top-level scalars through, which is where `<<count>>` comes from.
 * A layout naming a column somebody guessed at renders blank, and nobody notices.
 *
 * GROUNDING IS DECLARED, NOT ASSUMED
 *
 * `GroundingCheck` refuses a generation when every variable marked `grounding` is empty -
 * which is what stops a model writing "the hostel is nearly full" from an empty prompt. So
 * every cohort prompt marks `records` and `metrics`, which are what `ModuleToolData` fills.
 *
 * WHAT EACH MODULE MUST REFUSE TO SAY
 *
 * This is the part that is genuinely per-module, and it is not decoration:
 *
 *   Exam      - a mark is a mark, not a judgement about a child, and an absence carries no
 *               score. Never average an absence in as a zero, and never infer ability.
 *   PTM       - a booking with no attendance recorded is not a parent who stayed away.
 *   Hostel    - rooms carry no bed capacity in this schema, so nothing may say a hostel is
 *               full, has space, or is any percentage of anything.
 *   Requests  - a pending request has not been refused, and nothing here may decide one.
 *   Circular  - the record holds no read receipt, so nothing may report who saw a circular.
 *
 * FEES, ATTENDANCE, ADMISSIONS AND STUDENTS ARE NOT TOUCHED. Every write is keyed on a
 * template key beginning `k12.exam.`, `k12.ptm.`, `k12.hostel.`, `k12.student_request.` or
 * `k12.circular.`, none of which exists yet, and every suggestion row carries one of the
 * five module keys.
 *
 * Run it on its own - `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_22_100100_publish_exam_ptm_hostel_request_circular_ai_templates.php
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

        // None of these five could generate before this migration, because none had a
        // template to generate from. They can now, so the flag catches up with the fact.
        // 2026_09_22_100000 sets the same flag; both are idempotent merges and running
        // either alone leaves a consistent row.
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
        // against, and deleting the row would orphan the audit trail that explains what was
        // written and from which prompt.
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
     * What differs between the five modules, and nothing else.
     *
     * `noun` is how the module's records are named in prose ("meeting", "allocation").
     * `data_source` is the read tool its report is built from - the same tool
     * `config/ai.php` binds first for the module, so the report and a question asked on
     * the page are answered from the same records. `columns` are the layout's table, as
     * [heading, field] pairs taken from the service that returns them. `refusals` are the
     * safety rules, and they are the genuinely per-module part.
     *
     * @return array<string, array<string, mixed>>
     */
    private function modules(): array
    {
        return [
            'exam' => [
                'label' => 'Exam',
                'noun' => 'exam result',
                'plural' => 'exam results',
                'data_source' => 'exams.results',
                'data_arguments' => ['limit' => 100],
                'report_name' => 'Exam Results Report',
                'report_description' => 'The exam marks recorded for this institute, with the exam, '
                    .'subject and class each belongs to.',
                'summary_focus' => [
                    'Which exams and subjects the recorded marks cover, and for which classes.',
                    'What the average across scored entries is, and how many entries carry no score because the student was absent.',
                    'Whether the rows shown are the whole set or a sample of a larger one.',
                ],
                'analysis_focus' => [
                    'Which subjects or classes the recorded marks are strongest and weakest in.',
                    'How many entries are absences rather than low scores, and what that does to the averages.',
                    'What the records do not contain that would be needed to say more.',
                    'One or two concrete next steps an examinations officer could take.',
                ],
                'system_role' => 'You summarise recorded exam marks for an examinations officer.',
                'refusals' => [
                    'Do not invent an exam, a subject, a class, a mark, a grade or a count.',
                    'An absence carries no score. Never treat an absence as a zero, and never include one in an average.',
                    'A mark is a record of one performance. Never characterise a student as able, weak, lazy or gifted from it.',
                    'Do not compare against a previous term or year unless those records were provided.',
                ],
                'columns' => [
                    ['Student', 'student_name'],
                    ['Exam', 'exam_title'],
                    ['Standard', 'standard_name'],
                    ['Subject', 'subject_name'],
                    ['Marks', 'points'],
                    ['Percentage', 'percentage'],
                    ['Grade', 'grade'],
                    ['Absent', 'absent'],
                ],
                'footer' => 'Entries matched: <<count>> &mdash; of which <<scored_count>> carry a score and '
                    .'<<absent_count>> are absences. The average of <<average_percentage>> is taken over '
                    .'scored entries only; an absence carries no score and is never averaged in as a zero.',
            ],

            'ptm' => [
                'label' => 'PTM',
                'noun' => 'parent-teacher meeting',
                'plural' => 'parent-teacher meetings',
                'data_source' => 'ptm.meetings',
                'data_arguments' => ['limit' => 100],
                'report_name' => 'Parent-Teacher Meeting Report',
                'report_description' => 'The parent-teacher meetings scheduled for this institute, with '
                    .'the class each was opened for and how many families booked and attended.',
                'summary_focus' => [
                    'Which meetings are scheduled, for which classes, and on which dates.',
                    'How many families booked each, and how many of those bookings have an attendance recorded.',
                    'Which meetings have bookings whose attendance nobody has saved yet.',
                ],
                'analysis_focus' => [
                    'Which classes take up parent-teacher meetings and which do not.',
                    'How much of the attendance picture is actually recorded, as opposed to blank.',
                    'Whether any meeting was scheduled but has no bookings at all.',
                    'One or two concrete next steps the office could take before the next round.',
                ],
                'system_role' => 'You summarise parent-teacher meeting records for a school office.',
                'refusals' => [
                    'Do not invent a meeting, a date, a time, a class, a family or a count.',
                    'A booking with no attendance recorded means nobody has saved the register. Never report it as a parent who did not attend.',
                    'Do not judge a family, a teacher or a class from attendance figures.',
                    'Do not state a percentage of attendance unless both the bookings and the recorded outcomes were provided.',
                ],
                'columns' => [
                    ['Meeting', 'title'],
                    ['Date', 'ptm_date'],
                    ['From', 'from_time'],
                    ['To', 'to_time'],
                    ['Standard', 'standard_name'],
                    ['Division', 'division_name'],
                    ['Booked', 'students_booked'],
                    ['Attended', 'attended'],
                    ['Did not attend', 'did_not_attend'],
                    ['Not recorded', 'attendance_not_recorded'],
                ],
                'footer' => 'Meetings matched: <<count>> &mdash; every row is a meeting slot on file for '
                    .'this institute and academic year. A booking counted under "Not recorded" is one '
                    .'whose register has not been saved; it is not a family that stayed away.',
            ],

            'hostel' => [
                'label' => 'Hostel',
                'noun' => 'hostel allocation',
                'plural' => 'hostel allocations',
                'data_source' => 'hostel.allocations',
                'data_arguments' => ['limit' => 100],
                'report_name' => 'Hostel Allocation Report',
                'report_description' => 'Who is allocated to which hostel room this academic year, with '
                    .'the bed, locker and admission category each allocation records.',
                'summary_focus' => [
                    'Which hostels and rooms the allocations cover.',
                    'How many occupants are students and how many are staff.',
                    'Which allocations are missing a room, floor or building because the record does not resolve.',
                ],
                'analysis_focus' => [
                    'How the occupants are distributed across the hostels and rooms recorded.',
                    'Which allocations are incomplete - no bed number, no admission category, no resolvable room.',
                    'What the records cannot answer, and why.',
                    'One or two concrete next steps a warden could take.',
                ],
                'system_role' => 'You summarise hostel allocation records for a warden.',
                'refusals' => [
                    'Do not invent a hostel, a room, a bed, an occupant or a count.',
                    'Rooms carry no recorded bed capacity in this system. Never say a hostel or a room is full, has space, or is any percentage occupied.',
                    'Do not judge an occupant, and do not infer anything about a family from an allocation.',
                    'An allocation whose room does not resolve is a record to be corrected, not an occupant to be doubted.',
                ],
                'columns' => [
                    ['Occupant', 'occupant_name'],
                    ['Kind', 'occupant_kind'],
                    ['Enrolment no.', 'enrollment_no'],
                    ['Hostel', 'hostel_name'],
                    ['Building', 'building_name'],
                    ['Floor', 'floor_name'],
                    ['Room', 'room_name'],
                    ['Bed', 'bed_no'],
                    ['Category', 'admission_category'],
                ],
                'footer' => 'Allocations matched: <<count>> &mdash; every row is an allocation on file for '
                    .'this institute and academic year. A blank room, floor or building is an allocation '
                    .'naming a room that is not on this institute\'s own floors. Rooms carry no recorded '
                    .'bed capacity, so this report states no occupancy percentage.',
            ],

            'student_request' => [
                'label' => 'Student request',
                'noun' => 'student request',
                'plural' => 'student requests',
                'data_source' => 'student_requests.list',
                'data_arguments' => ['limit' => 100],
                'report_name' => 'Student Request Report',
                'report_description' => 'The change requests raised against student records this academic '
                    .'year, with the reason given and the decision recorded on each.',
                'summary_focus' => [
                    'How many requests are listed and what status each is in.',
                    'Which kinds of request they are, and which classes they come from.',
                    'Which of them required a proof document and which supplied one.',
                ],
                'analysis_focus' => [
                    'Which requests are still undecided, and how long they have been waiting.',
                    'Whether any request type is over-represented among the ones still open.',
                    'Which pending requests are missing a proof document their type requires.',
                    'One or two concrete next steps the office could take this week.',
                ],
                'system_role' => 'You summarise student change requests for a school office.',
                'refusals' => [
                    'Do not invent a request, a student, a class, a reason, a date or a count.',
                    'A pending request has not been refused. Never describe an undecided request as rejected, or imply what the decision will be.',
                    'Do not decide a request, recommend approving or refusing one, or tell a family an outcome.',
                    'Do not judge a family from the reason they gave for a request.',
                ],
                'columns' => [
                    ['Student', 'student_name'],
                    ['Enrolment no.', 'enrollment_no'],
                    ['Standard', 'standard_name'],
                    ['Division', 'division_name'],
                    ['Request', 'request_title'],
                    ['Reason', 'reason'],
                    ['Status', 'status'],
                    ['Raised on', 'raised_on'],
                    ['Decided on', 'decided_on'],
                    ['Decided by', 'decided_by_name'],
                ],
                'footer' => 'Requests matched: <<count>> &mdash; every row is a change request on file for a '
                    .'student of this institute in this academic year. A request with no decision recorded '
                    .'is pending; it has not been refused.',
            ],

            'circular' => [
                'label' => 'Circular',
                'noun' => 'circular',
                'plural' => 'circulars',
                'data_source' => 'circulars.list',
                'data_arguments' => ['limit' => 100],
                'report_name' => 'Circular Register',
                'report_description' => 'The circulars published this academic year, with the type, date '
                    .'and class each was addressed to.',
                'summary_focus' => [
                    'How many circulars were published, and how many separate notices that represents.',
                    'Which types they were, and over what span of dates.',
                    'Which classes they were addressed to, and how many carry an attachment.',
                ],
                'analysis_focus' => [
                    'Which classes receive the most circulars and which receive few or none.',
                    'Whether any type dominates, and over what period.',
                    'What the register cannot answer - it records publication, not receipt.',
                    'One or two concrete next steps the office could take.',
                ],
                'system_role' => 'You summarise a school\'s circular register for the front office.',
                'refusals' => [
                    'Do not invent a circular, a title, a date, a type, a class or a count.',
                    'The record holds no read receipt. Never state or estimate how many families received, opened or read a circular.',
                    'One row is one circular addressed to one class. Never report the row count as a count of separate notices.',
                    'Do not judge a class or a family from how many circulars were sent to them.',
                ],
                'columns' => [
                    ['Title', 'title'],
                    ['Type', 'circular_type'],
                    ['Date', 'circular_date'],
                    ['Standard', 'standard_name'],
                    ['Division', 'division_name'],
                    ['Attachment', 'attachment'],
                ],
                'footer' => 'Rows matched: <<count>>, covering <<distinct_circulars>> separate circular(s) '
                    .'&mdash; one row is one circular addressed to one class, so a notice sent to six '
                    .'classes is six rows. The register records publication only; it holds no record of '
                    .'who read a circular.',
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
                ucfirst($module['label']).' summary',
                'A short summary of the '.$module['plural'].' on the screen.',
                'Summarise the '.$module['plural'].' on this page.',
                $module['summary_focus'],
                $rules,
            );

            $rows[] = $this->prompt(
                $moduleKey,
                $module,
                'analysis',
                ucfirst($module['label']).' analysis',
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
     * The variable list is identical across all five because `ModuleToolData` fills the
     * same set for every module - `records` and `metrics` are what it reads out of the
     * module's bound tools, and the rest describe the page. Only `records` and `metrics`
     * are marked `grounding`, so a generation with neither is refused before a model is
     * called rather than answered from nothing.
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
                ['key' => 'metrics', 'label' => ucfirst($module['label']).' figures', 'required' => false, 'type' => 'text', 'grounding' => true],
                ['key' => 'page_title', 'label' => 'Page title', 'required' => false, 'type' => 'string'],
                ['key' => 'filters', 'label' => 'Filters', 'required' => false, 'type' => 'string'],
                ['key' => 'search_query', 'label' => 'Search', 'required' => false, 'type' => 'string'],
                ['key' => 'record_count', 'label' => 'Total records', 'required' => false, 'type' => 'string'],
                ['key' => 'rows_shown', 'label' => 'Rows shown', 'required' => false, 'type' => 'string'],
                ['key' => 'is_partial', 'label' => 'Partial view', 'required' => false, 'type' => 'string'],
            ]),
            'safety_rules' => $rules,
            // An internal summary a person reads on screen. Nothing generated from these
            // reaches a family, so a review gate would be friction with no protection
            // behind it - the family-facing gate is on the message prompts, and none of
            // these five modules publishes one.
            'requires_review' => 0,
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
            // The floor the question's own arguments are merged over.
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
     * Placeholders come in three kinds and this uses all three:
     *
     *   `<<report_title>>`       report-level. One value per report.
     *   `<<count>>`              a figure the DATA SOURCE computed, carried through by
     *                            `AiReportGenerator::scalarsOf()`.
     *   `<<#rows>> … <</rows>>`  repeated once per record the tool returned.
     *
     * Written with plain `<<token>>` delimiters. The HTML editor stores the same
     * placeholders HTML-encoded when a person edits this in the browser, and the renderer
     * accepts either spelling - so editing and re-saving does not break it.
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
     * The suggestion rows for one module, as [action_type, action_ref, label, description].
     *
     * Declared so `down()` can remove exactly what `up()` wrote.
     *
     * TWO PROSE ACTIONS AND ONE DOCUMENT ACTION, AND THE SPLIT IS DELIBERATE
     *
     * `action_type = 'generate'` renders a prompt and hands back text to read - right for
     * a summary and an analysis. `action_type = 'report'` builds a saved document that
     * `/ai-reports/{id}` previews, edits, refreshes, prints and sends, and it names no
     * template: `AiReportGenerator` resolves the module's layout itself. That is the
     * correction 2026_09_18_120000 made for Fees, applied here from the start.
     *
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
                    // Cohort actions, not per-record ones: offered on the module's list
                    // pages rather than gated behind selecting one row.
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
     * Writing a freshly built object over `capabilities` would enable these by silently
     * deleting any other flag the estate carries.
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
