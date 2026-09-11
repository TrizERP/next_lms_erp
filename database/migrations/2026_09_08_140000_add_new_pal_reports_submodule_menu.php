<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers Reports as New PAL's eighth level-3 sub-module.
 *
 * Home for the Coverage vs Attainment report (tracker #7). Follows
 * 2026_09_08_100000 exactly — same `new_pal.<sub_module>` link convention, same
 * parent lookup, same rights mirroring.
 *
 * Rights are mirrored from the New PAL parent and then the STUDENT grant is
 * revoked: this report names how a whole class is performing, which is a
 * teacher/principal view, not something a learner may pull. That is the one
 * place this migration deliberately differs from its ESO sibling, where the
 * student grant was the point.
 *
 * Idempotent, and narrow: only the New PAL branch of tblmenumaster is touched.
 */
return new class extends Migration
{
    private const LINK = 'new_pal.reports';

    private const SUB_MODULE = [
        'name' => 'Reports',
        'icon' => 'mdi mdi-chart-box-outline',
        'description' => 'Curriculum coverage and student attainment — what was taught, and what students demonstrated.',
        'sort_order' => 8,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $parent = DB::table('tblmenumaster')
            ->whereRaw('LOWER(name) = ?', ['new pal'])
            ->where('level', 2)
            ->orderBy('id')
            ->first();

        if ($parent === null) {
            return;
        }

        $existing = DB::table('tblmenumaster')
            ->where('parent_menu_id', $parent->id)
            ->whereRaw('LOWER(name) = ?', ['reports'])
            ->first();

        if ($existing !== null) {
            DB::table('tblmenumaster')->where('id', $existing->id)->update([
                'sort_order' => self::SUB_MODULE['sort_order'],
                'link' => self::LINK,
                'updated_at' => now(),
            ]);

            $this->mirrorRights((int) $parent->id, (int) $existing->id);

            return;
        }

        $menuId = DB::table('tblmenumaster')->insertGetId([
            'name' => self::SUB_MODULE['name'],
            'menu_title' => $parent->menu_title,
            'description' => self::SUB_MODULE['description'],
            'parent_menu_id' => $parent->id,
            'level' => 3,
            'status' => 1,
            'sort_order' => self::SUB_MODULE['sort_order'],
            'link' => self::LINK,
            'icon' => self::SUB_MODULE['icon'],
            'sub_institute_id' => $parent->sub_institute_id,
            'client_id' => $parent->client_id,
            'menu_type' => 'ENTRY',
            'site_map_name' => self::SUB_MODULE['name'],
            'menu_path' => self::SUB_MODULE['name'],
            'created_at' => now(),
        ]);

        $this->mirrorRights((int) $parent->id, (int) $menuId);
    }

    public function down(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $menuIds = DB::table('tblmenumaster')->where('link', self::LINK)->pluck('id');

        if ($menuIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('tblgroupwise_rights')) {
            DB::table('tblgroupwise_rights')->whereIn('menu_id', $menuIds)->delete();
        }

        DB::table('tblmenumaster')->whereIn('id', $menuIds)->delete();
    }

    /**
     * Mirror the parent's grants, minus students.
     *
     * The API refuses a student caller anyway (AttainmentReportController), so
     * this is the menu agreeing with the endpoint rather than a second gate —
     * a tab a student can see but never open is worse than no tab.
     */
    private function mirrorRights(int $parentMenuId, int $menuId): void
    {
        if (! Schema::hasTable('tblgroupwise_rights')) {
            return;
        }

        $studentProfileIds = Schema::hasTable('tbluserprofilemaster')
            ? DB::table('tbluserprofilemaster')->whereRaw('LOWER(name) = ?', ['student'])->pluck('id')->all()
            : [];

        foreach (DB::table('tblgroupwise_rights')->where('menu_id', $parentMenuId)->get() as $grant) {
            if (in_array($grant->profile_id, $studentProfileIds)) {
                continue;
            }

            $exists = DB::table('tblgroupwise_rights')
                ->where('menu_id', $menuId)
                ->where('profile_id', $grant->profile_id)
                ->where('sub_institute_id', $grant->sub_institute_id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('tblgroupwise_rights')->insert([
                'menu_id' => $menuId,
                'profile_id' => $grant->profile_id,
                'sub_institute_id' => $grant->sub_institute_id,
                'can_view' => $grant->can_view,
                'can_add' => $grant->can_add,
                'can_edit' => $grant->can_edit,
                'can_delete' => $grant->can_delete,
                'created_at' => now(),
            ]);
        }
    }
};
