<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers the two PAL Student Activity & Performance Report screens as
 * children of the "Reports" sub-module (`new_pal.reports`, added by
 * 2026_09_08_140000_add_new_pal_reports_submodule_menu) — one level deeper
 * than Reports itself, the same level3 -> level4 nesting
 * 2026_08_20_000010_add_ai_administration_menu already uses for AI
 * Administration's own tabs (Agents, Workflows, Ontology, ...).
 *
 * TWO DIFFERENT RIGHTS SOURCES, DELIBERATELY:
 *   - `new_pal.activity_report` (a student's own activity) mirrors the
 *     level-2 PAL parent's grants AS-IS — Student + Teacher + Admin, because
 *     a learner must be able to open their own report, the same reason the
 *     ESO student routes exist at all.
 *   - `new_pal.class_activity_report` (a whole class, aggregated) mirrors
 *     `new_pal.reports` itself, which 2026_09_08_140000 already stripped of
 *     the Student grant — Teacher + Admin only, matching every other
 *     cohort-level report in New PAL (and StudentActivityReportController's
 *     own staff-only gate on the class routes).
 *
 * PARENT LOOKUP IS BY LINK, NOT BY NAME
 * 2026_09_08_140000 resolves ITS OWN parent by matching `name = 'new pal'`
 * (case-insensitive) at level 2 — but the live row is named "PAL", not
 * "New PAL" (confirmed against this estate's tblmenumaster), so that lookup
 * never matches and `new_pal.reports` does not exist here yet. Rather than
 * perpetuate that same fragile name match for a different target, this
 * migration finds its own two anchors by their LINK — `new_pal.index` (the
 * level-2 PAL module, source of the student-inclusive grant) and
 * `new_pal.reports` (the level-3 Reports row, the actual parent of these two
 * new rows) — the same stable identifier every other menu migration in this
 * estate dedupes and links on. If `new_pal.reports` has not been created yet
 * (as on this estate today), this migration is a safe no-op, exactly like
 * its sibling's own "no parent, do nothing" guard: inventing a parent is
 * worse than skipping, and it will attach correctly once that row exists.
 *
 * Idempotent, and narrow: only these two links are touched.
 */
return new class extends Migration
{
    private const PAL_PARENT_LINK = 'new_pal.index';
    private const REPORTS_LINK = 'new_pal.reports';

    private const ACTIVITY_REPORT = [
        'name' => 'Activity Report',
        'link' => 'new_pal.activity_report',
        'icon' => 'mdi mdi-chart-line',
        'description' => "One learner's PAL mastery, diagnostic/adaptive performance, misconceptions and gamification.",
        'sort_order' => 1,
    ];

    private const CLASS_ACTIVITY_REPORT = [
        'name' => 'Class Activity Report',
        'link' => 'new_pal.class_activity_report',
        'icon' => 'mdi mdi-chart-box-multiple-outline',
        'description' => 'The same activity report, aggregated for a whole class — teacher/admin only.',
        'sort_order' => 2,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $palParent = DB::table('tblmenumaster')
            ->where('link', self::PAL_PARENT_LINK)
            ->where('level', 2)
            ->orderBy('id')
            ->first();

        $reports = DB::table('tblmenumaster')->where('link', self::REPORTS_LINK)->first();

        if ($palParent === null || $reports === null) {
            // Nothing to attach to yet — see the class docblock.
            return;
        }

        $activityId = $this->upsertMenu(self::ACTIVITY_REPORT, $reports);
        $this->mirrorGrants((int) $palParent->id, $activityId);

        $classActivityId = $this->upsertMenu(self::CLASS_ACTIVITY_REPORT, $reports);
        $this->mirrorGrants((int) $reports->id, $classActivityId);
    }

    public function down(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $menuIds = DB::table('tblmenumaster')
            ->whereIn('link', [self::ACTIVITY_REPORT['link'], self::CLASS_ACTIVITY_REPORT['link']])
            ->pluck('id');

        if ($menuIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('tblgroupwise_rights')) {
            DB::table('tblgroupwise_rights')->whereIn('menu_id', $menuIds)->delete();
        }

        DB::table('tblmenumaster')->whereIn('id', $menuIds)->delete();
    }

    /** Insert the row (one level under $parent) if its link doesn't exist yet; otherwise just refresh sort_order. */
    private function upsertMenu(array $menu, object $parent): int
    {
        $existing = DB::table('tblmenumaster')->where('link', $menu['link'])->first();

        if ($existing !== null) {
            DB::table('tblmenumaster')->where('id', $existing->id)->update([
                'sort_order' => $menu['sort_order'],
                'updated_at' => now(),
            ]);

            return (int) $existing->id;
        }

        return (int) DB::table('tblmenumaster')->insertGetId([
            'name' => $menu['name'],
            'menu_title' => $parent->menu_title,
            'description' => $menu['description'],
            'parent_menu_id' => $parent->id,
            'level' => (int) $parent->level + 1,
            'status' => 1,
            'sort_order' => $menu['sort_order'],
            'link' => $menu['link'],
            'icon' => $menu['icon'],
            'sub_institute_id' => $parent->sub_institute_id,
            'client_id' => $parent->client_id,
            'menu_type' => 'ENTRY',
            'site_map_name' => $menu['name'],
            'menu_path' => $menu['name'],
            'created_at' => now(),
        ]);
    }

    /** Copy every grant of $fromMenuId onto $toMenuId, skipping ones already there. */
    private function mirrorGrants(int $fromMenuId, int $toMenuId): void
    {
        if (! Schema::hasTable('tblgroupwise_rights')) {
            return;
        }

        foreach (DB::table('tblgroupwise_rights')->where('menu_id', $fromMenuId)->get() as $grant) {
            $exists = DB::table('tblgroupwise_rights')
                ->where('menu_id', $toMenuId)
                ->where('profile_id', $grant->profile_id)
                ->where('sub_institute_id', $grant->sub_institute_id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('tblgroupwise_rights')->insert([
                'menu_id' => $toMenuId,
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
