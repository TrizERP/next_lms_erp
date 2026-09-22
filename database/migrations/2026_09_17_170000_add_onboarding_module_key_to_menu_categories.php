<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which onboarding journey a module's Onboarding category shows.
 *
 * Every module now carries an Onboarding category in its level-3 bar
 * (2026_09_17_100001_seed_all_module_menu_categories.php), and every one of
 * those categories is empty — onboarding is not a set of menus to group, it is
 * the journey that already lives in the centralised onboarding module. Fees
 * and Teach/Learn each solved this by hand, by pointing their category's
 * `route` at a page that renders one hardcoded journey key ('fees', 'lms').
 * That does not scale to 64 modules and cannot be corrected without a deploy.
 *
 * This column is the general form of that hardcoding: the category row says
 * which `onboarding_module.module_key` it shows, so the one dynamic category
 * page serves every module and a wrong mapping is an UPDATE rather than a
 * release.
 *
 * Nullable on purpose. A module bar that groups screens from several different
 * modules — "Other Reports" collects Complaint, Consent, PTM and Visitor
 * reports — has no single journey to show, and inventing one would be worse
 * than saying so. NULL means exactly that, and the category page points those
 * users at the centralised onboarding index instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_categories')
            || Schema::hasColumn('fees_menu_categories', 'onboarding_module_key')) {
            return;
        }

        Schema::table('fees_menu_categories', function (Blueprint $table) {
            // Matches onboarding_module.module_key's width. Not a foreign key:
            // `fees_menu_categories` predates the onboarding tables and one of
            // the two must be installable without the other.
            $table->string('onboarding_module_key', 60)->nullable()->after('route');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_categories')
            || ! Schema::hasColumn('fees_menu_categories', 'onboarding_module_key')) {
            return;
        }

        Schema::table('fees_menu_categories', function (Blueprint $table) {
            $table->dropColumn('onboarding_module_key');
        });
    }
};
