<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WebView-backed mobile menu items.
 *
 * Until now every row in `mobile_homescreen` / `teacher_mobile_homescreen`
 * meant "open the native Flutter screen called `screen_name`", so a new page
 * needed a new Flutter screen and a store release. These three columns let a
 * row instead mean "open this ERP web page in the app's WebView host", which
 * is configured entirely from the web admin.
 *
 *  - `render_type`  'native' (open the `screen_name` screen, what every
 *                   existing row means) or 'webview' (load `web_url`).
 *  - `web_url`      the ERP page to load. Only read when render_type is
 *                   'webview'; TEXT because ERP report URLs carry long query
 *                   strings.
 *  - `open_mode`    'in_app' (the WebView host screen) or 'external' (hand
 *                   off to the system browser).
 *
 * The default is 'native' on purpose, and it is what makes this safe to run
 * against a live tenant: every existing row keeps meaning exactly what it
 * meant before, and an installed app build from before the WebView host
 * simply never reads the new keys. Verified against the live schema on
 * 2026-09-22 -- both tables matched their 2023 create migrations and neither
 * had any of these columns.
 *
 * Additive and hasColumn-guarded, so an installation that has already run a
 * partial version of this is not a failure, matching the treatment
 * AbstractMenuCategoryApiController gives its own rollout columns.
 */
return new class extends Migration
{
    /**
     * Both tables take the same three columns. They are separate tables
     * rather than one with a role column (student vs teacher/admin), and the
     * homescreen APIs read them the same way, so they migrate together.
     */
    private const TABLES = ['mobile_homescreen', 'teacher_mobile_homescreen'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'render_type')) {
                    // 'native' | 'webview'. Kept as a plain string rather than
                    // an enum so adding a third render type later is a code
                    // change, not an ALTER on a live tenant's menu table.
                    $table->string('render_type', 20)->nullable()->default('native')->after('screen_name');
                }
                if (! Schema::hasColumn($tableName, 'web_url')) {
                    $table->text('web_url')->nullable()->after('render_type');
                }
                if (! Schema::hasColumn($tableName, 'open_mode')) {
                    // 'in_app' | 'external'.
                    $table->string('open_mode', 20)->nullable()->default('in_app')->after('web_url');
                }
            });

            // Existing rows predate the column and come back NULL rather than
            // 'native'. The readers coalesce NULL to 'native' anyway, but
            // backfilling means the admin grid and a plain SELECT tell the
            // same story as the API does.
            \Illuminate\Support\Facades\DB::table($tableName)->whereNull('render_type')->update([
                'render_type' => 'native',
                'open_mode'   => 'in_app',
            ]);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['render_type', 'web_url', 'open_mode'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
