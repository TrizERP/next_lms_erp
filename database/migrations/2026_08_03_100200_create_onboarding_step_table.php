<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journey definition — the ordered steps of one module's onboarding.
 *
 * Completion is DERIVED, never self-reported: `proof_*` describes the query that
 * proves the step really happened. This generalises the existing engine in
 * tourController@Onboarding (which could only ask "does tblmenumaster.database_table
 * have >= 1 row for this tenant?") by adding:
 *   - per-step proof instead of per-menu-row proof,
 *   - academic-year scoping (`proof_scope = tenant_year`) for year-specific setup
 *     such as Fees, which the legacy engine got wrong,
 *   - a row threshold, so "one stray row" no longer marks a step done forever,
 *   - extra column predicates via `proof_conditions`,
 *   - `proof_type = manual` for steps with no table to point at (training,
 *     communication), which fall back to an explicitly recorded sign-off.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('onboarding_step', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('module_id');
            $table->string('step_key', 60);
            $table->string('title', 160);
            $table->string('description', 500)->nullable();
            $table->integer('sort_order')->default(0);
            $table->tinyInteger('is_required')->default(1);
            $table->tinyInteger('status')->default(1);

            // Ownership — the two markers rendered above/below each chevron.
            // `owner` decides which marker leads (renders above the ribbon).
            $table->enum('owner', ['TRIZ', 'SCHOOL'])->default('SCHOOL');
            $table->string('triz_role', 80)->nullable();
            $table->string('school_role', 80)->nullable();

            // Derived-completion definition.
            $table->enum('proof_type', ['table_rows', 'manual', 'none'])->default('manual');
            $table->string('proof_table', 100)->nullable();
            $table->enum('proof_scope', ['tenant', 'tenant_year', 'global'])->default('tenant');
            $table->unsignedInteger('proof_min_rows')->default(1);
            $table->json('proof_conditions')->nullable()->comment('Extra equality predicates, {"column":"value"}');

            // Where the user goes to actually do the work.
            $table->string('action_route', 160)->nullable()->comment('tblmenumaster.link route name');
            $table->unsignedBigInteger('action_menu_id')->nullable();
            $table->string('help_youtube_link', 255)->nullable();
            $table->string('help_pdf_link', 255)->nullable();

            $table->timestamps();

            $table->unique(['module_id', 'step_key'], 'onboarding_step_module_key_unique');
            $table->index(['module_id', 'sort_order'], 'onboarding_step_module_order_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('onboarding_step');
    }
};
