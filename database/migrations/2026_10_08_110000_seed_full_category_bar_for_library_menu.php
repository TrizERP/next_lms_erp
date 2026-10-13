<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Follow-up to 2026_10_08_100000_regroup_library_menus_under_one_level2.
 *
 * That migration gave the new "Library" Level 2 menu only the Operations,
 * Reports and (pre-existing) AI Stack categories. Every other module's bar
 * carries the full set - Onboarding, Process Builder, Master Setup,
 * Intelligence, Help Guide/Support, Communication, Workflow, Schedular,
 * Audit Trail - so Library's bar looked incomplete.
 *
 * This copies whichever of those categories Library is missing from the old
 * "Library Report" module (module_name 'library-report'), which carries the
 * standard set with the right labels, order and module keys. Copies start
 * empty, like every other module's un-used categories. Existing Library
 * categories and their items are never touched.
 */
return new class extends Migration
{
    private const SLUG = 'library';
    private const SOURCE_SLUG = 'library-report';

    public function up(): void
    {
        $libraryId = DB::table('tblmenumaster')
            ->where('parent_menu_id', 1)->where('level', 2)->where('name', 'Library')
            ->whereNotIn('id', [359, 467, 404])->value('id');
        if (! $libraryId) {
            return;
        }

        $have = DB::table('fees_menu_categories')->where('module_name', self::SLUG)->pluck('category_key')->all();

        foreach (DB::table('fees_menu_categories')->where('module_name', self::SOURCE_SLUG)->orderBy('sort_order')->get() as $source) {
            if (in_array($source->category_key, $have, true)) {
                continue;
            }

            $row = (array) $source;
            unset($row['id']);
            $row['module_name'] = self::SLUG;
            $row['level2_menu_id'] = $libraryId;
            $row['route'] = '/modules/'.self::SLUG.'/'.$source->category_key;
            $row['description'] = str_replace('Library Report', 'Library', (string) $source->description);
            $row['created_at'] = now();
            $row['updated_at'] = now();

            DB::table('fees_menu_categories')->insert($row);
        }
    }

    public function down(): void
    {
        // Only what this migration added; Operations / Reports / AI Stack belong to the previous one.
        DB::table('fees_menu_categories')->where('module_name', self::SLUG)
            ->whereNotIn('category_key', ['operations', 'reports', 'ai-stack'])->delete();
    }
};
