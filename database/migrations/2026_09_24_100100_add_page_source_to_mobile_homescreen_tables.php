<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a render_type = 'webview' menu row point at a Custom Mobile Page
 * (mobile_pages, see the sibling migration in this same batch) instead of an
 * admin-typed URL, without touching render_type's existing values or
 * web_url's existing meaning.
 *
 * page_source is the switch: 'external' (today's behavior -- web_url is
 * whatever the admin typed) or 'custom' (web_url is instead COMPUTED and
 * overwritten server-side from the referenced page's slug -- see
 * MobileAppMenuRightsApiController::updateConfig()). Existing rows have
 * page_source = NULL, which every reader treats identically to 'external',
 * so this migration changes no runtime behavior by itself.
 */
return new class extends Migration
{
    private const TABLES = ['mobile_homescreen', 'teacher_mobile_homescreen'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! Schema::hasColumn($table, 'page_source')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->string('page_source', 20)->nullable()->default('external')->after('open_mode');
                });
            }

            if (! Schema::hasColumn($table, 'custom_page_id')) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    $blueprint->unsignedBigInteger('custom_page_id')->nullable()->after('page_source');

                    // nullOnDelete rather than cascade: deleting (deactivating)
                    // a mobile page must not silently delete a menu row --
                    // just fall back to whatever an empty custom_page_id
                    // means for that row's rendering (native).
                    if (Schema::hasTable('mobile_pages')) {
                        $blueprint->foreign('custom_page_id')
                            ->references('id')->on('mobile_pages')
                            ->nullOnDelete();
                    }
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (Schema::hasColumn($table, 'custom_page_id')) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    $blueprint->dropForeign([$table === 'mobile_homescreen' ? 'mobile_homescreen_custom_page_id_foreign' : 'teacher_mobile_homescreen_custom_page_id_foreign']);
                    $blueprint->dropColumn('custom_page_id');
                });
            }

            if (Schema::hasColumn($table, 'page_source')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('page_source');
                });
            }
        }
    }
};
