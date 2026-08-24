<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support tables for the ported `CompetencyStudioController`
 * (Framework & Role Mapping Studio's Weighting Configuration + Proficiency
 * Scale tabs). Ported 1:1 from G2G (hp_erp):
 *
 *   - s_competency_settings (`2026_07_29_130000_create_competency_settings_table.php`)
 *     the key/value settings store behind `ManagesCompetencySettings`.
 *   - s_proficiency_levels (`2025_05_27_120952_create_s_skill_proficiency_level_table.php`)
 *     the tenant-global proficiency scale (skill_id NULL) StudioController reads/writes.
 *   - s_proficiency_knowledge / _ability / _attitude / _behaviour - the KASA
 *     behavioural-indicator tables `proficiencyScale()` joins in per dimension.
 *     No G2G migration exists for these four (legacy/pre-migration tables in
 *     that schema); their shape here is derived directly from
 *     `StudioController::proficiencyScale()`'s own query, which reads exactly
 *     `sub_institute_id`, `level`, `descriptor`, `indicators` per dimension.
 *
 * None of these six tables exist in this target database (confirmed via a
 * repo-wide migration + codebase grep before writing this file - see the
 * Competency Framework Studio port's report for the full gap analysis).
 * Same conventions as the rest of this module's migrations: bigIncrements,
 * indexed sub_institute_id, no FK constraints, audit columns, timestamps
 * (+ soft deletes on the two tables the controller soft-deletes against).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('s_competency_settings')) {
            Schema::create('s_competency_settings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sub_institute_id')->index();
                // Which panel the setting belongs to: weighting | assessment
                $table->string('scope', 50)->index();
                // Optional narrowing (e.g. a framework id). NULL = tenant default.
                $table->unsignedBigInteger('scope_id')->nullable()->index();
                $table->string('key', 100);
                $table->text('value')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                // One row per setting per scope - the controllers upsert on this.
                $table->unique(['sub_institute_id', 'scope', 'scope_id', 'key'], 's_competency_settings_unique');
            });
        }

        if (!Schema::hasTable('s_proficiency_levels')) {
            Schema::create('s_proficiency_levels', function (Blueprint $table) {
                $table->bigIncrements('id');
                // NULL = the tenant-global scale StudioController reads; a
                // real value would be a per-skill override (not read by this
                // port's Studio screen, but kept for schema parity with G2G).
                $table->unsignedBigInteger('skill_id')->nullable()->index();
                $table->string('proficiency_level')->index()->nullable();
                $table->text('description')->nullable();
                $table->string('proficiency_type')->index()->nullable();
                $table->text('type_description')->nullable();
                $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('updated_by')->nullable()->index();
                $table->unsignedBigInteger('deleted_by')->nullable()->index();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        foreach (['s_proficiency_knowledge', 's_proficiency_ability', 's_proficiency_attitude', 's_proficiency_behaviour'] as $table) {
            if (!Schema::hasTable($table)) {
                Schema::create($table, function (Blueprint $t) {
                    $t->bigIncrements('id');
                    $t->unsignedBigInteger('sub_institute_id')->index();
                    $t->string('level', 20)->index();
                    $t->text('descriptor')->nullable();
                    $t->text('indicators')->nullable();
                    $t->timestamps();
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('s_proficiency_behaviour');
        Schema::dropIfExists('s_proficiency_attitude');
        Schema::dropIfExists('s_proficiency_ability');
        Schema::dropIfExists('s_proficiency_knowledge');
        Schema::dropIfExists('s_proficiency_levels');
        Schema::dropIfExists('s_competency_settings');
    }
};
