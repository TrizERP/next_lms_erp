<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which platform-services module a bar's Workflow category configures.
 *
 * The same shape as `onboarding_module_key`, and for the same reason. Fees
 * shipped a Workflow category that renders the central workflow console pinned
 * to `module=fees`, with the key written into a frontend file
 * (app/fees/workflow/_screens/workflow-screens.tsx). No other module had the
 * category at all, and copying that file 63 times would put 63 module keys in
 * the frontend, each one a deploy to correct.
 *
 * So the key moves into the row: the category says which
 * config/platform_services.php module it pins the console to, one dynamic page
 * serves every module, and a wrong pin is an UPDATE.
 *
 * Nullable on purpose, and most rows will stay null. The platform registry
 * declares 16 modules — the ones whose components actually raise approval
 * points — while the menu tree has 64 level-2 bars. A bar with no counterpart
 * there (Skill Assessment, SQAA Report, Task Management) has nothing to pin to,
 * and NULL says exactly that; the category page then points those users at
 * Platform services → Workflow rather than pinning them to somebody else's
 * approvals.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_categories')
            || Schema::hasColumn('fees_menu_categories', 'workflow_module_key')) {
            return;
        }

        Schema::table('fees_menu_categories', function (Blueprint $table) {
            // Matches the registry's module keys ('front_desk', 'examination').
            // Not a foreign key: the registry is a config file, not a table.
            $table->string('workflow_module_key', 60)->nullable()->after('onboarding_module_key');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_categories')
            || ! Schema::hasColumn('fees_menu_categories', 'workflow_module_key')) {
            return;
        }

        Schema::table('fees_menu_categories', function (Blueprint $table) {
            $table->dropColumn('workflow_module_key');
        });
    }
};
