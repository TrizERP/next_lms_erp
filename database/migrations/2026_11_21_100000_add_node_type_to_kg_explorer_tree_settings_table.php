<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A third level under `kg_explorer_tree_settings`: one specific node type
 * (a GraphSchema label ModuleGraphMapper resolved for a submodule — "Student",
 * "FeeRecord" — see GraphModuleBrowser::erpSubmodules()'s `labels`) within one
 * submodule, within one module.
 *
 * `node_type` null is the module's or the submodule's own row, exactly as
 * before — this migration only adds the column and widens the index, it does
 * not change what a null-node_type row means. A non-null `node_type` is one
 * specific node type's own switch, parent-gated the same way a submodule is
 * gated by its module: `KnowledgeGraphSettingsResolver::isNodeTypeTreeEnabledFor()`
 * refuses before it even looks at this row if the submodule itself is off.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_11_21_100000_add_node_type_to_kg_explorer_tree_settings_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('kg_explorer_tree_settings', 'node_type')) {
            return;
        }

        Schema::table('kg_explorer_tree_settings', function (Blueprint $table) {
            $table->string('node_type', 150)->nullable()->after('submodule_label');
        });

        Schema::table('kg_explorer_tree_settings', function (Blueprint $table) {
            $table->dropUnique('kg_explorer_tree_settings_unique');
            $table->unique(
                ['module_label', 'submodule_label', 'node_type', 'sub_institute_id'],
                'kg_explorer_tree_settings_unique'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('kg_explorer_tree_settings', 'node_type')) {
            return;
        }

        Schema::table('kg_explorer_tree_settings', function (Blueprint $table) {
            $table->dropUnique('kg_explorer_tree_settings_unique');
            $table->unique(
                ['module_label', 'submodule_label', 'sub_institute_id'],
                'kg_explorer_tree_settings_unique'
            );
            $table->dropColumn('node_type');
        });
    }
};
