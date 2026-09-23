<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The prompts and report layouts for the six modules registered by 2026_09_24_100000.
 *
 * Built exactly like 2026_09_22_100100 and 2026_09_23_100100: one declaration, one loop,
 * two cohort prompts and one report layout per module. See the first of those for why the
 * shape is shared and why the refusals are the part that genuinely differs.
 *
 * EVERY PLACEHOLDER IS A FIELD THE BOUND TOOL REALLY RETURNS
 *
 * The column lists below were taken from the services, not from the tables:
 * `InwardService::register()` maps `inward_number, title, inward_date, place,
 * file_location, attachment_present`; `VisitorService::visits()` maps `visitor_name,
 * to_meet, purpose, in_time, out_time, presence_state`; and so on.
 * `AiReportGenerator::scalarsOf()` carries each payload's top-level scalars through, which
 * is where `<<count>>`, `<<without_bill>>` and `<<awaiting_decision>>` come from — and
 * every one of those scalars is now counted over the whole matching set rather than the
 * page, so a footer cannot report three of four thousand.
 *
 * WHAT EACH MODULE MUST REFUSE TO SAY
 *
 * Three of these six are defined by a column that does not exist, and the refusals are
 * where that is enforced in words:
 *
 *   Inward           - the register records NO status, owner, due date or reply. Nothing
 *                      may call a document pending, overdue, actioned, closed or answered.
 *   User I-Card      - the staff record carries payroll, bank and government identity
 *                      numbers that the tool does not return; nothing may refer to them.
 *                      `expire_date` is an ERP ACCOUNT expiry, never a card expiry.
 *   Petty Cash       - the book records NO approval and NO opening float. Nothing may be
 *                      described as awaiting approval, and no balance may be stated.
 *   Consent          - an empty status means nobody has answered. It is NEVER a refusal,
 *                      and there is no expiry and no reminder history to report.
 *   Visitor          - a missing exit time means no exit was RECORDED. Nothing may say a
 *                      named person is currently in the building.
 *   Transport        - the times are the schedule. Nothing records a boarding, a live
 *                      position, a delay, or a vehicle's fitness or insurance.
 *
 * WHY CONSENT IS THE ONE THAT NEEDS REVIEWING
 *
 * `requires_review = 1` is set for Consent alone. The other five produce internal
 * administrative summaries, where a mistake is a mistake. Consent's output can state that
 * a family did or did not agree to something on behalf of their child, and the underlying
 * column is empty on every row in this estate — so the one error the module is most likely
 * to make is also the one nobody should publish unread.
 *
 * NO RECORD APPEARS IN THIS FILE. No child, no visitor, no amount, no inward number. Every
 * value is a placeholder filled at runtime by `ModuleToolData` or `AiReportGenerator` from
 * the caller's own institute and year.
 *
 * NOTHING BELONGING TO AN EXISTING MODULE IS TOUCHED. Every template key begins
 * `k12.inward_outward.`, `k12.user_icard.`, `k12.petty_cash.`, `k12.consent.`,
 * `k12.visitor_management.` or `k12.transportation.`, none of which exists yet — including
 * for the two modules whose `ai_modules` rows predate this work, which have never had a
 * template of their own.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_24_100100_publish_six_more_module_ai_templates.php
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
    // ------------------------------------------------------------------ declaration

    /**
     * What differs between the six modules, and nothing else.
     *
     * @return array<string, array<string, mixed>>
     */
    private function modules(): array
    {
        return [
            'inward_outward' => [
                'label' => 'Inward',
                'noun' => 'inward record',
                'plural' => 'inward records',
                'data_source' => 'inward.register',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Inward Register Report',
                'report_description' => 'Documents received and entered in the inward register, with the '
                    .'place each came from and the physical file it was filed into.',
                'summary_focus' => [
                    'How many documents were received, and over what span of dates.',
                    'Which places they came from, and which appear most often.',
                    'How many have a scan attached and how many have a physical file location recorded.',
                ],
                'analysis_focus' => [
                    'Which places account for most of what arrives.',
                    'Where the register has gaps — no file location recorded, or no scan attached.',
                    'What the register cannot tell you, and why.',
                    'One or two concrete next steps the office could take.',
                ],
                'system_role' => 'You summarise an inward document register for a school office.',
                'refusals' => [
                    'Do not invent an inward number, a title, a date, a place, a file location or a count.',
                    'This register records NO status, owner, assignee, due date or action taken, and no link from an inward record to an outward reply. Never describe a record as pending, overdue, actioned, closed, answered or awaiting anybody.',
                    'A missing file location or a missing scan is a gap in the REGISTER — a detail the office has not entered. It is not a document that has been ignored, and it is not somebody\'s fault.',
                    'Days since received is arithmetic on a date. Never present it as lateness; nothing here records what was due or when.',
                    'The outward register is a separate record and you have not been given it. Never state what was sent in reply to anything.',
                ],
                'columns' => [
                    ['Inward no.', 'inward_number'],
                    ['Title', 'title'],
                    ['Received', 'inward_date'],
                    ['From', 'place'],
                    ['File', 'file_location'],
                    ['Scan', 'attachment_present'],
                ],
                'footer' => 'Records matched: <<count>> &mdash; of which <<with_attachment>> have a scan on '
                    .'file and <<without_file_location>> have no physical file location recorded. These '
                    .'figures cover every matching record, not only the rows listed. This register holds no '
                    .'status, owner or due date, so no row here is pending, overdue or closed.',
            ],

            'user_icard' => [
                'label' => 'User I-Card',
                'noun' => 'staff identity card',
                'plural' => 'staff identity cards',
                'data_source' => 'user_icard.roster',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Staff I-Card Print List',
                'report_description' => 'The staff a card can be printed for, and which cards are missing a '
                    .'photograph, an employee number or a profile.',
                'summary_focus' => [
                    'How many cards are ready to print and how many are missing something.',
                    'Which field is missing most often.',
                    'Which profiles and departments the listed staff sit in.',
                ],
                'analysis_focus' => [
                    'Which profiles or departments have the most cards not ready, and what is missing in each.',
                    'Whether the gaps look like a single collection exercise nobody has run.',
                    'What the roster does not tell you.',
                    'One or two concrete next steps the office could take before a print run.',
                ],
                'system_role' => 'You summarise a staff identity-card print list for a school office.',
                'refusals' => [
                    'Do not invent a member of staff, an employee number, a profile, a department or a count.',
                    'You have been given only the fields a card prints. The staff record also holds salary, bank, PAN, Aadhaar, provident fund and contract details — you have not been given any of them and must never refer to, estimate or ask about them.',
                    'A missing photograph or employee number is a detail the office has not collected. Never describe it as a person who is not entitled to a card.',
                    'This estate records NO card issue date, NO card expiry and NO print history. Never say a card has expired, is due for renewal, or was printed on any date.',
                    'Where an account expiry date is shown, it is the ERP ACCOUNT expiry on the staff record. Never call it a card expiry, and never infer anything about the person\'s employment from it.',
                ],
                'columns' => [
                    ['Staff', 'user_name'],
                    ['Employee no.', 'employee_no'],
                    ['Profile', 'user_profile'],
                    ['Department', 'department'],
                    ['Photo', 'photo_present'],
                    ['Ready', 'card_ready'],
                ],
                'footer' => 'Staff matched: <<count>> &mdash; of which <<card_ready>> have every card field '
                    .'recorded and <<missing_something>> are missing a photograph, an employee number or a '
                    .'profile. A blank cell is a detail the school has not recorded. This list carries only '
                    .'the fields a card prints.',
            ],

            'petty_cash' => [
                'label' => 'Petty Cash',
                'noun' => 'petty cash transaction',
                'plural' => 'petty cash transactions',
                'data_source' => 'petty_cash.transactions',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Petty Cash Report',
                'report_description' => 'Petty cash spends with the head each was booked to, the amount, the '
                    .'date and whether a bill is on file.',
                'summary_focus' => [
                    'How much was recorded as spent, and over what span of dates.',
                    'Which heads the spending was booked to.',
                    'How many spends have no bill on file.',
                ],
                'analysis_focus' => [
                    'Which heads account for most of the spending.',
                    'Whether any single spend is unusually large against the rest of the list.',
                    'What the book cannot tell you — above all, what is left.',
                    'One or two concrete next steps the office could take.',
                ],
                'system_role' => 'You summarise a petty cash book for a school office.',
                'refusals' => [
                    'Do not invent a transaction, a head, an amount, a date or a count. Do not add up figures yourself — every total you need has been given to you.',
                    'This book records NO approval of any kind: no approver, no approved-at, no rejection. Never describe a transaction as pending, awaiting approval, approved or rejected.',
                    'It records NO opening float, top-up or reimbursement, so nothing that came in is recorded anywhere. Never state a balance, a remaining amount, or how much is left in the tin. The total is money recorded as spent and nothing more.',
                    'A missing bill is a missing document. Never call a transaction suspicious, irregular or improper, and never suggest that a named person did anything wrong.',
                    'Do not compare this spending to a budget. No budget is recorded in this system.',
                ],
                'columns' => [
                    ['Head', 'head'],
                    ['Description', 'description'],
                    ['Amount', 'amount'],
                    ['Date', 'recorded_on'],
                    ['Entered by', 'entered_by'],
                    ['Bill', 'bill_attached'],
                ],
                'footer' => 'Transactions matched: <<count>>, totalling <<total_amount>> &mdash; over every '
                    .'matching transaction, not only the rows listed. <<without_bill>> have no bill on file. '
                    .'This total is money recorded as spent; it is NOT a balance, because no float, top-up '
                    .'or reimbursement is recorded anywhere in this book.',
            ],

            'consent' => [
                'label' => 'Consent',
                'noun' => 'consent',
                'plural' => 'consents',
                'data_source' => 'consent.records',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Consent Register',
                'report_description' => 'Consents raised for students, with the class, the date and whether '
                    .'a decision has been recorded.',
                'summary_focus' => [
                    'How many consents were raised, for what, and over what span of dates.',
                    'How many have a decision recorded and how many are still waiting for one.',
                    'How many are marked accountable.',
                ],
                'analysis_focus' => [
                    'Which classes have the most consents still waiting for a decision.',
                    'Whether the consents listed are mostly one exercise or several.',
                    'What the register cannot tell you — including when anything expires, which it does not record.',
                    'One or two concrete next steps the office could take.',
                ],
                'system_role' => 'You summarise a consent register for a school office.',
                'refusals' => [
                    'Do not invent a consent, a student, a class, a date, an amount or a count.',
                    'A consent has THREE states, never two: a decision is recorded, no decision is recorded yet, or the consent does not exist for that student. An empty decision means NOBODY HAS ANSWERED. Never report it as a refusal, a decline, a denial, or a parent saying no.',
                    'Where a decision is recorded, repeat the word the office entered. Do not translate it into granted, approved, declined or rejected.',
                    'This register records NO expiry date, NO reminder sent and NO response time. Never say a consent is expiring, has lapsed, has been chased, or is overdue.',
                    'Do not say anything about a child beyond the consent row you were given, and do not infer anything about a family from whether they have answered.',
                ],
                'columns' => [
                    ['Student', 'student_name'],
                    ['Class', 'standard_name'],
                    ['Consent', 'title'],
                    ['Date', 'consent_date'],
                    ['Accountable', 'accountable_status'],
                    ['Decision', 'decision_state'],
                ],
                'footer' => 'Consents matched: <<count>> &mdash; of which <<awaiting_decision>> have no '
                    .'decision recorded and <<decision_recorded>> do, over every matching consent rather '
                    .'than only the rows listed. A consent with no decision has NOT been refused: nobody '
                    .'has answered it yet.',
            ],

            'visitor_management' => [
                'label' => 'Visitor Management',
                'noun' => 'visit',
                'plural' => 'visits',
                'data_source' => 'visitor.visits',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Visitor Register',
                'report_description' => 'Visits to the school, who they were for, the purpose, and the entry '
                    .'and exit times recorded at the gate.',
                'summary_focus' => [
                    'How many visits there were, and over what span of dates.',
                    'Who the visits were for, and what the recorded purposes were.',
                    'How many have an exit time recorded and how many do not.',
                ],
                'analysis_focus' => [
                    'Which visitor types and purposes come up most.',
                    'How consistently exit times are being recorded, and on which days they are not.',
                    'What the register cannot tell you.',
                    'One or two concrete next steps the front desk could take.',
                ],
                'system_role' => 'You summarise a school visitor register for the front desk.',
                'refusals' => [
                    'Do not invent a visitor, a host, a purpose, a time or a count.',
                    'A missing exit time means NO EXIT WAS RECORDED. The visitor may still be on site or may have left without signing out, and the register cannot tell the difference. NEVER state or imply that a named person is currently in the building, and never produce a list described as who is on the premises.',
                    'This register records NO approval, approver, decision or rejection. Never describe a visit as pending approval, approved or rejected. Whether a visit was direct or by appointment is not an approval.',
                    'Do not repeat a visitor\'s phone number or email address. They are in the register for the front desk and do not belong in a summary.',
                    'The Hostel module keeps a separate visitor register which you have not been given. Never state a total for visitors to the school as a whole.',
                ],
                'columns' => [
                    ['Visitor', 'visitor_name'],
                    ['Type', 'visitor_type'],
                    ['To meet', 'to_meet'],
                    ['Purpose', 'purpose'],
                    ['Date', 'meet_date'],
                    ['In', 'in_time'],
                    ['Out', 'out_time'],
                    ['Status', 'presence_state'],
                ],
                'footer' => 'Visits matched: <<count>> &mdash; <<exit_recorded>> with an exit time recorded '
                    .'and <<no_exit_recorded>> with none, over every matching visit rather than only the '
                    .'rows listed. A missing exit time means no exit was RECORDED; it does not mean the '
                    .'visitor is still in the building.',
            ],

            'transportation' => [
                'label' => 'Transport',
                'noun' => 'transport route',
                'plural' => 'transport routes',
                'data_source' => 'transport.routes',
                'data_arguments' => ['limit' => 200],
                'report_name' => 'Transport Route Report',
                'report_description' => 'Routes with their scheduled times, the stops they call at and the '
                    .'vehicles assigned to run them.',
                'summary_focus' => [
                    'How many routes there are, and what times they are scheduled for.',
                    'How many stops each route calls at.',
                    'Which routes have a vehicle assigned and which do not.',
                ],
                'analysis_focus' => [
                    'Which routes carry the most stops, and which look thin.',
                    'Whether any route has no vehicle assigned to run it.',
                    'What the route records cannot tell you about what actually runs.',
                    'One or two concrete next steps the transport office could take.',
                ],
                'system_role' => 'You summarise school transport routes for a transport office.',
                'refusals' => [
                    'Do not invent a route, a stop, a time, a vehicle or a count.',
                    'The times on a route are the PUBLISHED SCHEDULE. This system records no departure or arrival that actually happened, no delay and no live position, so never say a route is running, has left, is on time or is late.',
                    'Nothing records that a child boarded a bus. An assignment is a plan, not a journey — never say who travelled or how many did.',
                    'Where seats and assignments are both given, more assignments than seats on one leg is a real finding. The morning and afternoon legs are SEPARATE TRIPS: never add them together, because most children ride the same bus both ways.',
                    'No vehicle fitness, insurance, permit or licence expiry is recorded anywhere in this system. Never say a vehicle is roadworthy, safe, or overdue for anything, and never comment on a driver\'s fitness to drive.',
                ],
                'columns' => [
                    ['Route', 'route_name'],
                    ['From', 'scheduled_from'],
                    ['To', 'scheduled_to'],
                    ['Stops', 'stop_count'],
                    ['Vehicles', 'vehicle_count'],
                ],
                'footer' => 'Routes matched: <<count>> for this academic year. The times shown are the '
                    .'published schedule &mdash; this system records no departure or arrival that actually '
                    .'happened, no delay and no live position, so nothing here describes what ran today.',
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

        // Consent is the one module here whose output a person must read before it is
        // used anywhere: it can state that a family did or did not agree to something
        // on their child's behalf, and the column it reads is empty on every row in this
        // estate. The other five produce internal administrative summaries.
        $needsReview = $moduleKey === 'consent';

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
