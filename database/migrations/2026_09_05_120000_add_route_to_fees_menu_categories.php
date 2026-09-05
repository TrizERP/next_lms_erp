<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives each Fees category its own page route.
 *
 * A category is no longer a state the level-3 bar switches into — it is a page.
 * Clicking "Master Setup" in the level-3 bar navigates to /fees/master-setup,
 * and that page renders the category's existing menus as its own horizontal tab
 * bar.
 *
 * The route lives in the database next to the category rather than being
 * derived from the key in code, so the category -> page mapping is data like
 * everything else about these categories: a category can be pointed at a
 * different page by updating one column.
 *
 * Backfills /fees/<category_key>, which is the convention the seven category
 * pages are created under (app/fees/<category_key>/page.tsx in the lms_k12
 * repo) and matches the module's existing /fees/* routes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_categories')) {
            return;
        }

        if (! Schema::hasColumn('fees_menu_categories', 'route')) {
            Schema::table('fees_menu_categories', function (Blueprint $table) {
                $table->string('route', 255)->nullable()->after('description');
            });
        }

        // Only fill rows that have no route yet, so a route edited in the
        // database is never overwritten by a re-run.
        DB::table('fees_menu_categories')
            ->where(function ($query) {
                $query->whereNull('route')->orWhere('route', '');
            })
            ->orderBy('id')
            ->get(['id', 'category_key'])
            ->each(function ($category) {
                DB::table('fees_menu_categories')
                    ->where('id', $category->id)
                    ->update([
                        'route' => '/fees/'.$category->category_key,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_categories') || ! Schema::hasColumn('fees_menu_categories', 'route')) {
            return;
        }

        Schema::table('fees_menu_categories', function (Blueprint $table) {
            $table->dropColumn('route');
        });
    }
};
