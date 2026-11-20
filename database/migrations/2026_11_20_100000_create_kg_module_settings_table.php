<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the Knowledge Graph's own centralized on/off decision lives.
 *
 * WHAT A ROW MEANS
 *
 * "Is the Knowledge Graph switched on for this module, in this school?" A row with
 * `module_key` null applies to every module; a row with `sub_institute_id` null is
 * the platform's own default. The same two-step, two-dimension precedence
 * `ai_module_model_bindings` already uses for a module's model choice — one rule to
 * learn rather than a new one for this feature.
 *
 * WHY A NEW, SEPARATE TABLE
 *
 * This is not an AI capability (`AiModuleRegistry`/`ai_api_keys`) and not a model
 * choice (`ai_module_model_bindings`): it is the simpler question of whether the
 * Knowledge Graph tab should exist for a module at all. `module_key` deliberately
 * matches the `ai_modules.module_key` vocabulary already shared by
 * `ai_module_model_bindings.product_module` and `ai_ontology_views.module_key`, so
 * resolving this setting for a module never needs a translation table.
 *
 * NON-BREAKING BY DEFAULT
 *
 * A module, school or estate with no row here resolves to enabled — see
 * `KnowledgeGraphSettingsResolver::isEnabledFor()`. This table only ever turns
 * something OFF; its absence changes nothing for anyone.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_11_20_100000_create_kg_module_settings_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kg_module_settings')) {
            return;
        }

        Schema::create('kg_module_settings', function (Blueprint $table) {
            $table->id();

            // An `ai_modules.module_key` value (`fees`, `hostel`, `teach_learn`,
            // `organization-management`, ...). Null applies to every module this
            // school (or the platform) has — a wildcard row, not a module of its own.
            $table->string('module_key', 100)->nullable();

            // Null is the platform's own default, which every school inherits
            // unless it saves a row of its own.
            $table->unsignedBigInteger('sub_institute_id')->nullable();

            $table->boolean('enabled')->default(1);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // Keeps a save an upsert for the common, fully-specific cases. A wildcard
            // row (module_key or sub_institute_id null) is still de-duplicated by
            // KnowledgeGraphSettingsResolver::save() itself — MySQL treats two NULLs
            // in a unique index as distinct, the same caveat ai_module_model_bindings
            // already lives with.
            $table->unique(['module_key', 'sub_institute_id'], 'kg_module_settings_unique');

            // The read path: every resolution asks for one institute's rows plus the
            // platform's, for a given module plus the wildcard.
            $table->index(['sub_institute_id', 'module_key'], 'kg_module_settings_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kg_module_settings');
    }
};
