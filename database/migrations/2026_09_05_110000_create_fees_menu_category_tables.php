<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the Fees level-3 category navigation as data instead of code.
 *
 * The seven Fees categories (Onboarding, Master Setup, Transactional Data,
 * Reports, Intelligence, Help Guide / Support, SOP / Task) and the menus that
 * belong to each were previously a PHP constant inside
 * FeesMenuCategoryApiController. They now live here, so the grouping can be
 * changed by editing rows rather than shipping code.
 *
 * These are deliberately NOT tblmenumaster rows:
 *
 *  - tblmenumaster is the 3-level menu tree (level 1 module -> level 2 menu ->
 *    level 3 screen). Adding the categories to it would either take the level-3
 *    slot the real Fees screens already occupy, or push those screens to a 4th
 *    level the renderer does not support.
 *  - Keeping them in their own tables means no existing menu row changes level,
 *    parent, link, status or rights, and no other module is touched.
 *
 * `fees_menu_category_items.menu_id` points at the existing tblmenumaster row,
 * so a menu is referenced, never copied — one screen, one record, one route,
 * one set of permissions. Visibility is still decided at read time against
 * tblmenumaster.status, the tenant list and the caller's rights; a row here
 * only says "this menu belongs in this category, in this position".
 *
 * The seed resolves each menu by (parent name, parent level, menu name) rather
 * than a hardcoded id, because ids are not portable across tenants and Fees has
 * two rows sharing the link /fees/master/fees-config-master (Fees Config Master
 * and Fees Circular Master), so link is not unique either.
 *
 * Idempotent: categories are matched on their key and items on
 * (category, menu), so re-running re-syncs order without duplicating rows.
 */
return new class extends Migration
{
    /** key => [label, description, sort_order] */
    private const CATEGORIES = [
        'onboarding' => ['Onboarding', 'Set up a new institute or academic year for fees collection.', 1],
        'master-setup' => ['Master Setup', 'Fee heads, break-offs, receipt books and other configuration.', 2],
        'transactional-data' => ['Transactional Data', 'Day-to-day collection, cancellation, refunds and NACH operations.', 3],
        'reports' => ['Reports', 'Collection, cancellation, structure and defaulter reporting.', 4],
        'intelligence' => ['Intelligence', 'Predictive and analytical views over fees data.', 5],
        'help-guide-support' => ['Help Guide / Support', 'Guides and support material for the fees module.', 6],
        'sop-task' => ['SOP / Task', 'Standard operating procedures and fees tasks.', 7],
    ];

    /** category key => ordered [parent name, parent level, menu name] */
    private const ITEMS = [
        'master-setup' => [
            ['Fees (Master)', 1, 'Fees Late Master'],
            ['Fees (Master)', 1, 'Update Fees BreckOff'],
            ['Fees (Master)', 1, 'Bank Master'],
            ['Fees (Master)', 1, 'Other Fees Title'],
            ['Fees (Master)', 1, 'Fees Circular Master'],
            ['Fees (Master)', 1, 'Additional Fees Mapping'],
            ['Fees (Master)', 1, 'New Fees Title'],
            ['Fees (Master)', 1, 'Fees BreakOff'],
            ['Fees (Master)', 1, 'Fees Config Master'],
            ['Fees (Master)', 1, 'Fees Receipt Book Master'],
        ],
        'transactional-data' => [
            ['Fees', 2, 'Fees Collect'],
            ['Fees', 2, 'Online Fees Collect'],
            ['Fees', 2, 'Fees Cancel/Refund'],
            ['Fees', 2, 'Fees Circular'],
            ['Fees', 2, 'Other Fees Collect'],
            ['Fees', 2, 'Other Fees Cancel'],
            ['Fees', 2, 'S1 Export NACH Excel'],
            ['Fees', 2, 'S2 Import NACH Excel'],
            ['Fees', 2, 'S3 Export NACH Excel'],
            ['Fees', 2, 'S4 Import NACH Excel'],
            ['Fees', 2, 'Upload Reconciliation Sheet'],
            ['Document Templates', 2, 'Fee documents'],
        ],
        'reports' => [
            ['Fees Report', 2, 'Fees Collection Report'],
            ['Fees Report', 2, 'Other Fees Report'],
            ['Fees Report', 2, 'Other Fees Cancel Report'],
            ['Fees Report', 2, 'DateWise Summary Report'],
            ['Fees Report', 2, 'Fees Structure Report'],
            ['Fees Report', 2, 'Fees Monthly Report'],
            ['Fees Report', 2, 'Fees Cancel Report'],
            ['Fees Report', 2, 'Fees Type Wise Report'],
            ['Fees Report', 2, 'Fees Defaulter Report'],
            ['Fees Report', 2, 'Student Breakoff Report'],
        ],
        'intelligence' => [
            ['(AI) Artificial Intelligence', 2, 'Fees Prediction'],
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_categories')) {
            Schema::create('fees_menu_categories', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('category_key', 64)->unique();
                $table->string('label', 128);
                $table->string('description', 255)->nullable();
                $table->integer('sort_order')->default(0);
                $table->tinyInteger('status')->default(1);
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->dateTime('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('fees_menu_category_items')) {
            Schema::create('fees_menu_category_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('category_key', 64);
                // References tblmenumaster.id. No FK constraint: tblmenumaster
                // predates this schema and carries no engine-level keys, so a
                // constraint here would be the only one of its kind.
                $table->unsignedBigInteger('menu_id');
                $table->integer('sort_order')->default(0);
                $table->tinyInteger('status')->default(1);
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->dateTime('updated_at')->nullable();

                $table->unique(['category_key', 'menu_id'], 'fees_menu_category_items_unique');
                $table->index('category_key');
            });
        }

        $this->seed();
    }

    public function down(): void
    {
        Schema::dropIfExists('fees_menu_category_items');
        Schema::dropIfExists('fees_menu_categories');
    }

    private function seed(): void
    {
        foreach (self::CATEGORIES as $key => [$label, $description, $sortOrder]) {
            $existing = DB::table('fees_menu_categories')->where('category_key', $key)->first();

            if ($existing === null) {
                DB::table('fees_menu_categories')->insert([
                    'category_key' => $key,
                    'label' => $label,
                    'description' => $description,
                    'sort_order' => $sortOrder,
                    'status' => 1,
                    'created_at' => now(),
                ]);

                continue;
            }

            // Re-sync order only. Label and description are left alone so an
            // edit made in the database is not overwritten by a re-run.
            DB::table('fees_menu_categories')->where('id', $existing->id)->update([
                'sort_order' => $sortOrder,
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        foreach (self::ITEMS as $categoryKey => $items) {
            $sortOrder = 0;

            foreach ($items as [$parentName, $parentLevel, $menuName]) {
                $menuId = $this->resolveMenuId($parentName, $parentLevel, $menuName);
                if ($menuId === null) {
                    continue;
                }

                $sortOrder++;

                $existing = DB::table('fees_menu_category_items')
                    ->where('category_key', $categoryKey)
                    ->where('menu_id', $menuId)
                    ->first();

                if ($existing === null) {
                    DB::table('fees_menu_category_items')->insert([
                        'category_key' => $categoryKey,
                        'menu_id' => $menuId,
                        'sort_order' => $sortOrder,
                        'status' => 1,
                        'created_at' => now(),
                    ]);

                    continue;
                }

                DB::table('fees_menu_category_items')->where('id', $existing->id)->update([
                    'sort_order' => $sortOrder,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * The menu's tblmenumaster id. The parent is matched without a status
     * filter on purpose: "Fees Prediction" hangs off a status=0 parent, and it
     * is the menu's own status — checked at read time, not here — that decides
     * whether it is ever shown.
     */
    private function resolveMenuId(string $parentName, int $parentLevel, string $menuName): ?int
    {
        $parentId = DB::table('tblmenumaster')
            ->where('name', $parentName)
            ->where('level', $parentLevel)
            ->orderBy('id')
            ->value('id');

        if ($parentId === null) {
            return null;
        }

        $id = DB::table('tblmenumaster')
            ->where('parent_menu_id', $parentId)
            ->where('name', $menuName)
            ->orderBy('id')
            ->value('id');

        return $id === null ? null : (int) $id;
    }
};
