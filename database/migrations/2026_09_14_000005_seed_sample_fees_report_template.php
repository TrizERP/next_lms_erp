<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One worked example: the Pending Fees Report.
 *
 * A sample rather than a fixture. It is a real, published, working template — the
 * assistant will use it the moment this runs — and it exists to show the shape every
 * other module's template can copy: report-level facts at the top, a repeating block
 * over the rows, and totals taken from the data source rather than recomputed.
 *
 * WHY IT IS ESTATE-WIDE
 *
 * `sub_institute_id` is NULL, so every school on the estate resolves it. That is the
 * decision recorded for Fees templates: anyone who opens the Fees module can use them
 * whatever their sub-institute. A school that wants different wording edits it, which
 * writes that school its own copy and leaves this one untouched for everybody else.
 *
 * WHY IT CONTAINS NO STUDENT
 *
 * Every student-specific value in the layout is a placeholder filled at render time
 * from rows `fees.arrears` read out of the database. There is no name, no amount and no
 * class anywhere in this file. That is what lets one template serve every student and
 * every school: ask about the whole cohort and the `<<#rows>>` block repeats once per
 * defaulter; ask about one student and the same block runs once.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_14_000005_seed_sample_fees_report_template.php
 */
return new class extends Migration
{
    private const TEMPLATE_KEY = 'k12.fees.pending_report';

    private const DATA_SOURCE = 'fees.arrears';

    public function up(): void
    {
        if (! Schema::hasTable('ai_templates') || ! Schema::hasColumn('ai_templates', 'kind')) {
            // The report columns are not on this estate yet. Nothing to seed into.
            return;
        }

        $exists = DB::table('ai_templates')
            ->where('template_key', self::TEMPLATE_KEY)
            ->whereNull('sub_institute_id')
            ->exists();

        if ($exists) {
            return;
        }

        $id = DB::table('ai_templates')->insertGetId([
            'template_key' => self::TEMPLATE_KEY,
            'name' => 'Pending Fees Report',
            'description' => 'Students carrying an outstanding fee balance, with the total owed.',
            'domain' => 'k12',
            'module_key' => 'fees',
            'kind' => 'report',
            'category' => 'report',
            'version' => 1,
            'status' => 'published',
            // NOT NULL on this table, and a report has no prompt — see the controller.
            'user_prompt' => '',
            'html_layout' => $this->layout(),
            'data_source' => self::DATA_SOURCE,
            // The floor the question's own arguments are merged over. 50 is a readable
            // page of a report; a question that names a class or a student narrows it
            // further, and one that asks for everybody does not widen past the tool's
            // own ceiling.
            'data_arguments' => json_encode(['limit' => 50]),
            'output_format' => 'text',
            'allow_as_evidence' => false,
            'requires_review' => false,
            // Estate-wide. Every school resolves this one until it writes its own.
            'sub_institute_id' => null,
            'client_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->bind($id);
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_templates')) {
            return;
        }

        if (Schema::hasTable('ai_suggestions')) {
            DB::table('ai_suggestions')
                ->where('module_key', 'fees')
                ->where('capability', 'generative')
                ->where('action_ref', self::TEMPLATE_KEY)
                ->whereNull('sub_institute_id')
                ->delete();
        }

        DB::table('ai_templates')
            ->where('template_key', self::TEMPLATE_KEY)
            ->whereNull('sub_institute_id')
            ->delete();
    }

    /**
     * Put the template in the Fees AI panel.
     *
     * The second of the two rows that make a template usable: `ai_templates` stores it,
     * `ai_suggestions` is what makes the Fees panel offer it as a button. Written here
     * rather than left to the screen so the sample is complete on arrival.
     */
    private function bind(int $templateId): void
    {
        if (! Schema::hasTable('ai_suggestions')) {
            return;
        }

        $exists = DB::table('ai_suggestions')
            ->where('module_key', 'fees')
            ->where('capability', 'generative')
            ->where('action_ref', self::TEMPLATE_KEY)
            ->whereNull('sub_institute_id')
            ->exists();

        if ($exists) {
            return;
        }

        $sortOrder = ((int) DB::table('ai_suggestions')
            ->where('module_key', 'fees')
            ->where('capability', 'generative')
            ->max('sort_order')) + 10;

        DB::table('ai_suggestions')->insert([
            'module_key' => 'fees',
            'capability' => 'generative',
            'label' => 'Pending fees report',
            'description' => 'Build the pending-fee report from live records.',
            'icon' => null,
            'action_type' => 'generate',
            'action_ref' => self::TEMPLATE_KEY,
            'prompt' => null,
            'payload' => null,
            // A cohort report, not a per-record one: it is offered on the Fees list
            // pages, not gated behind selecting one student.
            'requires_entity' => false,
            'allowed_roles' => null,
            'required_permissions' => null,
            'sort_order' => $sortOrder,
            'status' => 1,
            'sub_institute_id' => null,
            'client_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * The layout.
     *
     * Three kinds of placeholder, which is the whole vocabulary:
     *
     *   `<<report_title>>`        report-level. One value per report.
     *   `<<total_outstanding>>`   a figure the DATA SOURCE computed. Not recomputed from
     *                             the rows below, because the rows are often a window
     *                             onto a larger cohort and the two would disagree.
     *   `<<#rows>> … <</rows>>`   repeated once per record, with that record's fields
     *                             available inside it.
     *
     * Written with plain `<<token>>` delimiters. The HTML editor stores the same
     * placeholders HTML-encoded when a person edits this in the browser, and the
     * renderer accepts either spelling — so editing and re-saving this template does
     * not break it.
     */
    private function layout(): string
    {
        return <<<'HTML'
<div style="font-family:Segoe UI,Arial,sans-serif;color:#0f172a">

  <h2 style="margin:0 0 4px 0;font-size:20px"><<report_title>></h2>
  <p style="margin:0 0 16px 0;color:#475569;font-size:13px">
    <<row_count>> student(s) listed &middot; generated <<generated_at>>
  </p>

  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:#f1f5f9">
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left;width:36px">#</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Student</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Gr. No.</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:left">Std / Div</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:right">Outstanding</th>
        <th style="border:1px solid #cbd5e1;padding:6px;text-align:right">Heads</th>
      </tr>
    </thead>
    <tbody>
      <<#rows>>
      <tr>
        <td style="border:1px solid #cbd5e1;padding:6px"><<row_number>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<student_name>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<enrollment_no>></td>
        <td style="border:1px solid #cbd5e1;padding:6px"><<standard_name>></td>
        <td style="border:1px solid #cbd5e1;padding:6px;text-align:right">&#8377;<<outstanding>></td>
        <td style="border:1px solid #cbd5e1;padding:6px;text-align:right"><<pending_items>></td>
      </tr>
      <</rows>>
    </tbody>
  </table>

  <p style="margin:16px 0 0 0;font-size:13px">
    <strong>Total outstanding: &#8377;<<total_outstanding>></strong>
    across <<defaulter_count>> student(s) with dues,
    from <<students_checked>> of <<cohort_size>> students checked.
  </p>

  <p style="margin:20px 0 0 0;color:#64748b;font-size:11px">
    Every figure above was read from the school database at the time shown. No amount on
    this page was written by a model.
  </p>

</div>
HTML;
    }
};
