<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a centrally managed template be a report layout, not only a prompt.
 *
 * WHY ONE TABLE AND NOT TWO
 *
 * `ai_templates` already holds everything a template needs that has nothing to do with
 * being a prompt: which module it belongs to, its version, its published/draft status,
 * whether the school owns it or inherits the platform baseline, and the tenant-override
 * resolution that goes with all of that. A second table for report layouts would be a
 * second copy of every one of those rules, and Template Management would become two
 * screens that drift. So a layout is a row here with `kind = 'report'`.
 *
 * WHAT THE TWO KINDS ARE
 *
 *   `prompt` — the existing rows. `system_prompt` and `user_prompt` are sent to a model,
 *              which writes prose. Everything on the estate today is this.
 *   `report` — `html_layout` is a document with `<<placeholders>>`, and `data_source`
 *              names the MCP tool that fetches the rows to put in them. No model writes
 *              the figures; substitution does. That is the point — a model asked to
 *              produce a table of students is a model that can invent a student.
 *
 * WHY `data_source` IS A TOOL NAME
 *
 * The 28 MCP tools already know how to read this estate's fees, attendance, admissions,
 * exams, homework, students and teachers — with the right tenant scoping, joins and
 * field names, and with the governance layer in front of them. A report template that
 * queried independently would be a second opinion about the school. Naming a tool means
 * adding a module to the report flow is publishing a template, not writing a service.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_14_000003_add_report_layout_to_ai_templates.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_templates')) {
            return;
        }

        Schema::table('ai_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_templates', 'kind')) {
                // Defaulted to 'prompt' so every existing row keeps meaning exactly what
                // it meant before this migration ran. A nullable column would have made
                // "not yet classified" a third state that every reader has to handle.
                $table->string('kind', 20)->default('prompt')->after('module_key')->index();
            }

            if (! Schema::hasColumn('ai_templates', 'html_layout')) {
                $table->longText('html_layout')->nullable()->after('user_prompt');
            }

            if (! Schema::hasColumn('ai_templates', 'data_source')) {
                // The MCP tool that fetches the rows, e.g. `fees.get_pending`. Nullable
                // because prompt templates have no data source of their own — the
                // workspace fills their variables from the page already on screen.
                $table->string('data_source', 120)->nullable()->after('html_layout');
            }

            if (! Schema::hasColumn('ai_templates', 'data_arguments')) {
                // The argument mapping handed to that tool, e.g.
                // {"student_id": "{{entity_id}}"} — so one template serves both "all
                // students" and "this student" instead of needing a copy for each.
                $table->json('data_arguments')->nullable()->after('data_source');
            }
        });

        // Belt and braces for estates where the column already existed without the
        // default applied — a NULL kind would drop the row out of both `kind` filters
        // and make a working template invisible in Template Management.
        DB::table('ai_templates')->whereNull('kind')->update(['kind' => 'prompt']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_templates')) {
            return;
        }

        Schema::table('ai_templates', function (Blueprint $table) {
            if (Schema::hasColumn('ai_templates', 'kind')) {
                $table->dropIndex(['kind']);
                $table->dropColumn('kind');
            }

            foreach (['html_layout', 'data_source', 'data_arguments'] as $column) {
                if (Schema::hasColumn('ai_templates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
