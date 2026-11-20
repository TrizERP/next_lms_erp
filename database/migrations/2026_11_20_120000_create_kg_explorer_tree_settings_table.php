<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where on/off decisions for the Centralized Explorer's own browse tree live —
 * a DIFFERENT concern from `kg_module_settings`.
 *
 * TWO SEPARATE THINGS BOTH CALLED "A MODULE"
 *
 * `kg_module_settings.module_key` is an `ai_modules.module_key` slug (`fees`,
 * `teach_learn`) — it decides whether a PRODUCT MODULE's Knowledge Graph TAB
 * renders at all (module-category-page.tsx).
 *
 * This table is keyed by the Explorer's OWN tree — `tblmenumaster` level-1
 * labels (`Institute ERP`, `LMS + PAL`, `People & Competency`, `Career
 * Counseling`, `Enterprise Brain` — see GraphModuleBrowser::erpModules()) and,
 * under one of them, a level-2 submodule label (`PTM`, `Fees`, `Student`...
 * see GraphModuleBrowser::erpSubmodules()). It decides whether that branch of
 * the Explorer's own tree is browsable, and whether its erp-module/
 * erp-submodule graph endpoints serve data at all.
 *
 * Text labels, not `tblmenumaster` ids, because a label reads in this table
 * and a numeric id does not, and because the id of "the same" submodule can
 * differ across a re-seed while its label does not.
 *
 * WHAT A ROW MEANS
 *
 * `submodule_label` null is the MODULE's own switch. A module switched off
 * takes every submodule under it with it, whatever those submodules' own rows
 * say — see KnowledgeGraphSettingsResolver::isSubmoduleTreeEnabledFor(). A
 * non-null `submodule_label` is one specific submodule within that module.
 *
 * Only two scopes, not four: with five real modules, there is no case for a
 * blanket "every module" wildcard the way `kg_module_settings` needs one for
 * sixty product modules — an administrator switches each of the five
 * directly. `sub_institute_id` null is still the platform's own default,
 * which every school inherits unless it saves a row of its own.
 *
 * NON-BREAKING BY DEFAULT
 *
 * No row anywhere resolves to `true` — see the resolver. This table only ever
 * takes a branch away, never grants one that was not already there.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_11_20_120000_create_kg_explorer_tree_settings_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kg_explorer_tree_settings')) {
            return;
        }

        Schema::create('kg_explorer_tree_settings', function (Blueprint $table) {
            $table->id();

            // A GraphModuleBrowser::erpModules() label — "Institute ERP", "LMS + PAL", ...
            $table->string('module_label', 150);

            // A GraphModuleBrowser::erpSubmodules() label under that module, or null
            // for the module's own row.
            $table->string('submodule_label', 150)->nullable();

            // Null is the platform's own default, which every school inherits
            // unless it saves a row of its own.
            $table->unsignedBigInteger('sub_institute_id')->nullable();

            $table->boolean('enabled')->default(1);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // Keeps a save an upsert for the common, fully-specific cases. A
            // module's own row (submodule_label null) is still de-duplicated by
            // KnowledgeGraphSettingsResolver::save*() itself — MySQL treats two
            // NULLs in a unique index as distinct, the same caveat
            // kg_module_settings already lives with.
            $table->unique(
                ['module_label', 'submodule_label', 'sub_institute_id'],
                'kg_explorer_tree_settings_unique'
            );

            $table->index(['sub_institute_id', 'module_label'], 'kg_explorer_tree_settings_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kg_explorer_tree_settings');
    }
};
