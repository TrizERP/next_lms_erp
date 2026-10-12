<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Puts the Alumni Management screens in workflow order (see docs/ALUMNI_MANAGEMENT_NAVIGATION_GUIDE.docx) and
 * adds the one screen that had no menu entry: Convert Passed-Out Students (alumni/conversion).
 *
 *   Records:       Dashboard, List, Add, Pending Verification, Convert Passed-Out Students, Import
 *   Network:       Directory, Batches, Mentorship, Jobs and Internships
 *   Engagement:    Engagement, Events, Communication, Surveys
 *   Money/Insight: Donations, Reports, Audit Log
 *
 * Only sort_order changes on existing rows - names, links, parents and rights are untouched. The new row is
 * matched by link (no duplicate on re-run) and gets exactly the rights of the "Alumni List" menu, because the
 * conversion API is gated by the same "manage" role check as the list.
 *
 * Idempotent and forward-only: re-running re-applies the same order; down() leaves the order alone because
 * the previous order was only the order the screens happened to be built in.
 */
return new class extends Migration
{
    private const ORDER = [
        'alumni/dashboard', 'alumni/list', 'alumni/add', 'alumni/pending_verification', 'alumni/conversion', 'alumni/import',
        'alumni/directory', 'alumni/batches', 'alumni/mentorship', 'alumni/opportunities',
        'alumni/engagement', 'alumni/events', 'alumni/communication', 'alumni/surveys',
        'alumni/donations', 'alumni/reports', 'alumni/audit',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster') || ! Schema::hasTable('tblgroupwise_rights')) {
            return;
        }
        $module = DB::table('tblmenumaster')->where('name', 'Alumni Management')->where('level', 2)->first();
        $reference = DB::table('tblmenumaster')->where('link', 'alumni/list')->first();
        if ($module === null || $reference === null) {
            return;
        }

        $name = 'Convert Passed-Out Students';
        $conversion = DB::table('tblmenumaster')->where('link', 'alumni/conversion')->first();
        if ($conversion === null) {
            $id = DB::table('tblmenumaster')->insertGetId([
                'name' => $name, 'menu_title' => 'Alumni Management', 'description' => 'Turn passed-out students into alumni records (preview, then confirm)',
                'parent_menu_id' => $module->id, 'level' => 3, 'status' => 1, 'sort_order' => 0,
                'link' => 'alumni/conversion', 'icon' => $reference->icon, 'sub_institute_id' => $module->sub_institute_id,
                'client_id' => $module->client_id, 'menu_type' => 'ENTRY', 'site_map_name' => $name,
                'menu_path' => $name, 'created_at' => now(),
            ]);
            foreach (DB::table('tblgroupwise_rights')->where('menu_id', $reference->id)->get() as $r) {
                DB::table('tblgroupwise_rights')->insert([
                    'menu_id' => $id, 'profile_id' => $r->profile_id, 'sub_institute_id' => $r->sub_institute_id,
                    'can_view' => $r->can_view, 'can_add' => $r->can_add, 'can_edit' => $r->can_edit,
                    'can_delete' => $r->can_delete, 'created_at' => now(),
                ]);
            }
        }

        foreach (self::ORDER as $i => $link) {
            DB::table('tblmenumaster')->where('parent_menu_id', $module->id)->where('link', $link)->update(['sort_order' => $i + 1]);
        }
    }

    public function down(): void
    {
        // Forward-only - see class doc-block.
    }
};
