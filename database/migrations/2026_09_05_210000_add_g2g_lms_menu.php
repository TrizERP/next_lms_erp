<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers "LMS" as a level-2 menu NESTED UNDER the existing "People &
 * Competency" level-1 menu (stakeholder direction: this LMS port must not
 * introduce a new top-level module), with its 9 level-3 screens — per
 * PACKAGE 0 of the G2G → LMS-K12 LMS migration. Mirrors
 * 2026_08_21_120000_add_competency_management_menu.php's structure and
 * conventions exactly (same column set, same `link` idempotency guard, same
 * sub_institute_id/client_id inheritance from the parent row, same
 * plain-autoincrement id via insertGetId — no UUIDs are used anywhere in
 * this table).
 *
 * IMPORTANT — parent lookup caveat: unlike Competency Management (whose
 * parent, "Talent Management", was itself inserted by an earlier migration
 * in this same repo, so its exact `link` was known statically), "People &
 * Competency" is pre-existing legacy data that predates this codebase's
 * migrations — it is referenced only in comments elsewhere (see
 * 2026_08_17_120000_fix_hrit_menu_link_collisions.php: "People & Competency
 * > Payroll / User Attendance") and was never found, by name or `link`, in
 * any migration, seeder or SQL fixture checked into either repo. Live DB
 * access was not available while writing this migration, so the parent is
 * resolved defensively by `name` (case-insensitive, both "People &
 * Competency" and "People and Competency") at level 1 rather than by a
 * hardcoded id/link. If your environment's row uses different wording, add
 * it to self::PARENT_NAME_CANDIDATES before running this migration — the
 * migration safely no-ops (like its Competency Management counterpart) if no
 * candidate matches, rather than guessing or creating a duplicate top-level
 * module.
 */
return new class extends Migration
{
    private const PARENT_NAME_CANDIDATES = [
        'People & Competency',
        'People and Competency',
    ];

    private const LMS_MODULE_LINK = 'g2g_lms.index';

    private const SCREENS = [
        ['name' => 'Learning Dashboard', 'link' => 'g2g_lms.learning_dashboard', 'description' => 'LMS: learning overview, progress and highlights'],
        ['name' => 'Learning Catalog', 'link' => 'g2g_lms.learning_catalog', 'description' => 'LMS: browse and enrol into courses'],
        ['name' => 'My Learning', 'link' => 'g2g_lms.my_learning', 'description' => 'LMS: a learner\'s own enrolled and completed courses'],
        ['name' => 'Assignments', 'link' => 'g2g_lms.assignments', 'description' => 'LMS: assignments, submissions and grading'],
        ['name' => 'Sessions & Calendar', 'link' => 'g2g_lms.sessions_calendar', 'description' => 'LMS: scheduled sessions and the learning calendar'],
        ['name' => 'Certifications & Records', 'link' => 'g2g_lms.certifications_records', 'description' => 'LMS: certificates earned and learning records'],
        ['name' => 'Course Builder', 'link' => 'g2g_lms.course_builder', 'description' => 'LMS: author and manage course content'],
        ['name' => 'Administration & Governance', 'link' => 'g2g_lms.administration_governance', 'description' => 'LMS: module administration, policies and governance'],
        ['name' => 'Assessments', 'link' => 'g2g_lms.assessments', 'description' => 'LMS: quizzes, tests and assessment results'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $parent = DB::table('tblmenumaster')
            ->whereIn(DB::raw('LOWER(name)'), array_map('mb_strtolower', self::PARENT_NAME_CANDIDATES))
            ->where('level', 1)
            ->first();

        if ($parent === null) {
            // "People & Competency" not found under this environment's exact
            // name/level - skip rather than create a duplicate top-level
            // module or guess at an id. See class doc-block.
            return;
        }

        // LMS level-2 row, nested under People & Competency.
        $lmsModule = DB::table('tblmenumaster')->where('link', self::LMS_MODULE_LINK)->first();
        if ($lmsModule === null) {
            $moduleSort = (int) DB::table('tblmenumaster')->where('parent_menu_id', $parent->id)->max('sort_order');

            $lmsModuleId = DB::table('tblmenumaster')->insertGetId([
                'name' => 'LMS',
                'menu_title' => 'People & Competency',
                'description' => 'Learning management: courses, assignments, sessions, certifications and assessments',
                'parent_menu_id' => $parent->id,
                'level' => 2,
                'status' => 1,
                'sort_order' => $moduleSort + 1,
                'link' => self::LMS_MODULE_LINK,
                'icon' => 'mdi mdi-school-outline',
                'sub_institute_id' => $parent->sub_institute_id,
                'client_id' => $parent->client_id,
                'menu_type' => 'ENTRY',
                'site_map_name' => 'LMS',
                'menu_path' => 'LMS',
                'created_at' => now(),
            ]);
            $lmsModule = DB::table('tblmenumaster')->where('id', $lmsModuleId)->first();
        }

        // 9 level-3 screens, nested under LMS.
        $screenSort = 0;
        foreach (self::SCREENS as $screen) {
            $screenSort++;

            if (DB::table('tblmenumaster')->where('link', $screen['link'])->exists()) {
                continue;
            }

            DB::table('tblmenumaster')->insert([
                'name' => $screen['name'],
                'menu_title' => 'LMS',
                'description' => $screen['description'],
                'parent_menu_id' => $lmsModule->id,
                'level' => 3,
                'status' => 1,
                'sort_order' => $screenSort,
                'link' => $screen['link'],
                'icon' => 'mdi mdi-school-outline',
                'sub_institute_id' => $lmsModule->sub_institute_id,
                'client_id' => $lmsModule->client_id,
                'menu_type' => 'ENTRY',
                'site_map_name' => $screen['name'],
                'menu_path' => $screen['name'],
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $links = [self::LMS_MODULE_LINK];
        foreach (self::SCREENS as $screen) {
            $links[] = $screen['link'];
        }

        $menuIds = DB::table('tblmenumaster')->whereIn('link', $links)->pluck('id')->all();
        if ($menuIds !== [] && Schema::hasTable('tblgroupwise_rights')) {
            DB::table('tblgroupwise_rights')->whereIn('menu_id', $menuIds)->delete();
        }

        DB::table('tblmenumaster')->whereIn('link', $links)->delete();
    }
};
