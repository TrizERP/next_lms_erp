<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Puts the homework review screen on the Operations tab bar.
 *
 * `/lms/homework/review` is where a teacher or admin marks a student's
 * submission question by question. It has worked since the review workflow
 * shipped and has never had a menu row, so the only way in was to type the URL.
 *
 * Three tables have to agree before a tab appears:
 *
 *  1. `tblmenumaster` — the menu itself, a level-3 child of Exam & Assesment
 *     (276), sitting beside Student Homework (90) and Homework Submission (218).
 *  2. `fees_menu_category_items` — which category bar it belongs to. Despite the
 *     table's name it is not fees-specific; every module's bar lives in it (see
 *     2026_09_17_100001). Without a row here the menu exists in the tree but no
 *     tab is drawn.
 *  3. `tblprofilewise_menu` — who can see it.
 *
 * WHO GETS IT. The same profiles that already hold Homework Submission (218),
 * MINUS any Student or Parent profile. That screen is role-branched and so
 * legitimately reaches students; this one is not — `RequireStaff` guards the
 * page and `staff.only` guards the API, so granting it to a student profile
 * would only produce a menu item that 403s on click.
 *
 * Idempotent on the menu's (parent, link) pair, so re-running adds nothing.
 */
return new class extends Migration
{
    /** Exam & Assesment. */
    private const PARENT_MENU_ID = 276;

    /** The menu this one is modelled on and inherits its audience from. */
    private const SIBLING_MENU_ID = 218;

    private const LINK = '/lms/homework/review';

    private const MODULE_NAME = 'test';

    private const CATEGORY_KEY = 'operations';

    /** Straight after Homework Submission, which sits at 2. */
    private const TAB_POSITION = 3;

    /** Profiles this screen must never offer, whatever 218 does. */
    private const BLOCKED_PROFILE_NAMES = ['student', 'parent'];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster') || ! Schema::hasTable('fees_menu_category_items')) {
            return;
        }

        $sibling = DB::table('tblmenumaster')->where('id', self::SIBLING_MENU_ID)->first();

        if (! $sibling) {
            return;
        }

        $now = now();

        $menuId = (int) (DB::table('tblmenumaster')
            ->where('parent_menu_id', self::PARENT_MENU_ID)
            ->where('link', self::LINK)
            ->value('id') ?? 0);

        if ($menuId === 0) {
            $menuId = (int) DB::table('tblmenumaster')->insertGetId([
                'name' => 'Homework Review',
                'menu_title' => $sibling->menu_title,
                'parent_menu_id' => self::PARENT_MENU_ID,
                'level' => 3,
                'status' => 1,
                'sort_order' => $sibling->sort_order,
                'link' => self::LINK,
                'icon' => 'mdi mdi-clipboard-check-outline fa-fw',
                // The tenant and client lists are inherited wholesale: a school
                // that can see Homework Submission is a school that can see its
                // review screen, and keeping the two in step by hand across 300+
                // tenants is not a thing anyone would keep doing.
                'sub_institute_id' => $sibling->sub_institute_id,
                'client_id' => $sibling->client_id,
                'menu_type' => $sibling->menu_type,
                'menu_path' => $sibling->menu_path,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // -- the tab itself --------------------------------------------------
        $hasItem = DB::table('fees_menu_category_items')
            ->where('module_name', self::MODULE_NAME)
            ->where('category_key', self::CATEGORY_KEY)
            ->where('menu_id', $menuId)
            ->exists();

        if (! $hasItem) {
            // Everything from the insertion point rightwards shifts along, so
            // the new tab lands next to Homework Submission rather than at the
            // far end past Project.
            DB::table('fees_menu_category_items')
                ->where('module_name', self::MODULE_NAME)
                ->where('category_key', self::CATEGORY_KEY)
                ->where('sort_order', '>=', self::TAB_POSITION)
                ->increment('sort_order');

            DB::table('fees_menu_category_items')->insert([
                'module_name' => self::MODULE_NAME,
                'category_key' => self::CATEGORY_KEY,
                'menu_id' => $menuId,
                'sort_order' => self::TAB_POSITION,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // -- who can see it ---------------------------------------------------
        if (! Schema::hasTable('tblprofilewise_menu')) {
            return;
        }

        $blocked = DB::table('tbluserprofilemaster')
            ->whereIn(DB::raw('LOWER(TRIM(name))'), self::BLOCKED_PROFILE_NAMES)
            ->pluck('id')
            ->all();

        $existing = DB::table('tblprofilewise_menu')
            ->where('menu_id', $menuId)
            ->pluck('user_profile_id')
            ->all();

        $rows = [];

        foreach (DB::table('tblprofilewise_menu')->where('menu_id', self::SIBLING_MENU_ID)->get() as $grant) {
            if (in_array((int) $grant->user_profile_id, array_map('intval', $blocked), true)
                || in_array($grant->user_profile_id, $existing, true)) {
                continue;
            }

            $rows[] = [
                'menu_id' => $menuId,
                'user_profile_id' => $grant->user_profile_id,
                'sub_institute_id' => $grant->sub_institute_id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('tblprofilewise_menu')->insert($chunk);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $menuId = (int) (DB::table('tblmenumaster')
            ->where('parent_menu_id', self::PARENT_MENU_ID)
            ->where('link', self::LINK)
            ->value('id') ?? 0);

        if ($menuId === 0) {
            return;
        }

        if (Schema::hasTable('fees_menu_category_items')) {
            DB::table('fees_menu_category_items')
                ->where('module_name', self::MODULE_NAME)
                ->where('category_key', self::CATEGORY_KEY)
                ->where('menu_id', $menuId)
                ->delete();

            // Close the gap the removed tab leaves behind.
            DB::table('fees_menu_category_items')
                ->where('module_name', self::MODULE_NAME)
                ->where('category_key', self::CATEGORY_KEY)
                ->where('sort_order', '>', self::TAB_POSITION)
                ->decrement('sort_order');
        }

        if (Schema::hasTable('tblprofilewise_menu')) {
            DB::table('tblprofilewise_menu')->where('menu_id', $menuId)->delete();
        }

        DB::table('tblmenumaster')->where('id', $menuId)->delete();
    }
};
