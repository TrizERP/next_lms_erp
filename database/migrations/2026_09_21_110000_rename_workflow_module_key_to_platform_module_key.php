<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generalises `workflow_module_key` to `platform_module_key`.
 *
 * The column answers "which config/platform_services.php module does this bar
 * configure?", and that answer is the same one for all three platform services.
 * Workflow asked it first, so the column was named after it. Scheduler needs
 * the identical mapping — Fees → Schedular pins the scheduler console to
 * `module=fees` exactly as Fees → Workflow pins the workflow console — and
 * Communication already scopes the same way.
 *
 * A second column per service would be the same 42 rows of mapping copied
 * three times, and the copies would drift: a bar corrected on Workflow would
 * keep pinning Scheduler to the wrong module. One registry, one key.
 *
 * Renamed rather than added-and-dropped so the mapping already filled in
 * survives; both spellings never coexist.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_categories')
            || ! Schema::hasColumn('fees_menu_categories', 'workflow_module_key')
            || Schema::hasColumn('fees_menu_categories', 'platform_module_key')) {
            return;
        }

        Schema::table('fees_menu_categories', function (Blueprint $table) {
            $table->renameColumn('workflow_module_key', 'platform_module_key');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_categories')
            || ! Schema::hasColumn('fees_menu_categories', 'platform_module_key')
            || Schema::hasColumn('fees_menu_categories', 'workflow_module_key')) {
            return;
        }

        Schema::table('fees_menu_categories', function (Blueprint $table) {
            $table->renameColumn('platform_module_key', 'workflow_module_key');
        });
    }
};
