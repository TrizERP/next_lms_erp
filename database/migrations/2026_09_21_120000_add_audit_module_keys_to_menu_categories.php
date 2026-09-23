<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which access-log rows a bar's Audit Trail category shows.
 *
 * NOT THE SAME KIND OF KEY AS `platform_module_key`, and that is the whole
 * reason it is a separate column. Workflow and Scheduler configure a module
 * declared in config/platform_services.php. The audit trail reads
 * `access_log_route`, whose `module` column is written by LogRouteMiddleware as
 * the *first path segment of the URL that was opened* — 'fees', 'result',
 * 'student'. It is a route prefix, not a module name, and nobody declared it.
 *
 * WHY A LIST. One bar's screens can sit under several prefixes: the Exam bar
 * links at /exam/marks-entry and /result/upload-result, so an audit trail
 * filtered on one literal would silently drop half of it. Fees happens to be a
 * single prefix, which is why the hand-written Fees page got away with `const
 * FEES_MODULE = 'fees'` — that is Fees being simple, not the rule.
 *
 * Stored comma-separated and derived from the bar's own menus, so it stays a
 * description of where that module's screens actually live rather than a
 * second, hand-kept list that drifts from the menu tree.
 *
 * Nullable: four bars link only at route names that resolve to nothing, or at
 * screens that exist solely in the Next app and therefore never pass through
 * the Laravel middleware that writes this log. They have nothing to show, and
 * the category page says that instead of showing an empty table that looks
 * like "no activity".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_categories')
            || Schema::hasColumn('fees_menu_categories', 'audit_module_keys')) {
            return;
        }

        Schema::table('fees_menu_categories', function (Blueprint $table) {
            // TEXT rather than a short string: a payroll-style bar where every
            // screen has its own top-level segment runs to a dozen prefixes.
            $table->text('audit_module_keys')->nullable()->after('platform_module_key');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_categories')
            || ! Schema::hasColumn('fees_menu_categories', 'audit_module_keys')) {
            return;
        }

        Schema::table('fees_menu_categories', function (Blueprint $table) {
            $table->dropColumn('audit_module_keys');
        });
    }
};
