<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Somewhere for generated reports to live that allows more than one of them.
 *
 * THE BUG THIS FIXES
 *
 * `AiReportGenerator` saved every report it produced into `template_master`, filed
 * under `module_name = 'AI'`. That table carries
 *
 *     UNIQUE KEY `key` (`sub_institute_id`, `module_name`)
 *
 * — one row per school per module, because it is a *template* table: one fee receipt
 * design, one admission letter design. So the first report a school generated saved,
 * and the second threw `UniqueConstraintViolationException` out of an unguarded
 * `insertGetId()`. On this estate that is visible as exactly one AI row across 73
 * template rows, which reads like light usage and is actually a ceiling.
 *
 * A generated report is not a template. It is an instance — this question, these rows,
 * this timestamp — and a school will accumulate hundreds. That needs a table where the
 * only unique thing is the id.
 *
 * WHY THE OLD ROWS ARE NOT MOVED
 *
 * Existing reports keep their `template_master` id, and `/ai-reports/{id}` is a link
 * somebody may have bookmarked or emailed. Copying them here would renumber them and
 * break those links to fix a problem they do not have. `GeneratedReportStore` reads
 * this table first and falls back to the old one, so old links keep working and new
 * reports land somewhere with room.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_14_000004_create_ai_generated_reports_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_generated_reports')) {
            return;
        }

        Schema::create('ai_generated_reports', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('client_id')->nullable();

            // Which module the report is about, as an `ai_modules` key.
            $table->string('module_key', 60)->index();

            // The Template Management layout it was rendered from, when it was rendered
            // from one. Null for a report built by the generator's own composed table,
            // which is still what a module with no published layout produces.
            $table->unsignedBigInteger('layout_template_id')->nullable()->index();

            $table->string('title', 250);
            $table->longText('html_content');

            // The question that asked for it, kept so a reader months later knows what
            // the document was built to answer.
            $table->text('question')->nullable();

            // What produced the figures and with which arguments — the same facts the
            // in-document marker carries, in a form a query can filter on.
            $table->string('source_tool', 120)->nullable();
            $table->json('arguments')->nullable();
            $table->unsignedInteger('row_count')->default(0);

            $table->unsignedTinyInteger('status')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // The listing is always "this school's reports, newest first".
            $table->index(['sub_institute_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generated_reports');
    }
};
