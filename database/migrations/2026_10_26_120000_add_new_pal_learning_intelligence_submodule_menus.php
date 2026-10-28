<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers New PAL's Learning Intelligence sub-modules: Knowledge Graph,
 * Learning Journey, Teacher Insights and Context Inspector — four more
 * level-3 rows under the same New PAL parent (id 531) as AI Stack,
 * Gamification, Reports, etc.
 *
 * Follows 2026_09_29_100500_add_new_pal_ai_stack_submodule_menu.php's
 * pattern exactly: idempotent on `link`, dynamic sort_order
 * (max(sort_order)+1 per insert, so this never collides with whatever the
 * live sort_order values actually are), and the frontend list half
 * (DashboardShell's NEW_PAL_LEVEL3_ITEMS / NEW_PAL_LEVEL3_DB_NAMES, plus
 * app/data/routeMapper.ts) is added in the same commit.
 *
 * Learning Journey deliberately points at the EXISTING /pal/eso/learning-path
 * page (built long before this migration) rather than a new route — it had
 * no tblmenumaster row at all, so it was correct but unreachable from any
 * tab. This migration is what makes it tab-reachable for the first time.
 *
 * Teacher Insights withholds the student grant, mirroring
 * 2026_09_08_140000_add_new_pal_reports_submodule_menu.php's reasoning: it
 * names how a whole class is performing, which is a teacher/admin view, not
 * something a learner may pull. The other three keep the full parent grant
 * (a student may view their own knowledge graph, their own journey, and —
 * read-only, no live re-run — their own context inspector).
 */
return new class extends Migration
{
    private const SUB_MODULES = [
        [
            'name' => 'Knowledge Graph',
            'link' => 'new_pal.knowledge_graph',
            'icon' => 'mdi mdi-graph-outline',
            'description' => 'The curriculum hierarchy and concept-relationship graph, browsable from Curriculum down to Concept.',
            'withhold_student' => false,
        ],
        [
            'name' => 'Learning Journey',
            'link' => 'new_pal.learning_journey',
            'icon' => 'mdi mdi-map-marker-path',
            'description' => 'A student\'s real cross-chapter PAL plan, as a visual roadmap — the existing Learning Path screen, now tab-reachable.',
            'withhold_student' => false,
        ],
        [
            'name' => 'Teacher Insights',
            'link' => 'new_pal.teacher_insights',
            'icon' => 'mdi mdi-account-group-outline',
            'description' => 'Class and student PAL progress for teachers: diagnostics, mastery, weak concepts and students who need intervention.',
            'withhold_student' => true,
        ],
        [
            'name' => 'Context Inspector',
            'link' => 'new_pal.context_inspector',
            'icon' => 'mdi mdi-magnify-scan',
            'description' => 'How one learning decision was assembled: the student state, concept relationships and rule that produced it.',
            'withhold_student' => false,
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        // Matched by `link`, not `name` — this DB's level-2 New PAL row is
        // literally named "PAL" (confirmed live), so the name-based lookup
        // 2026_09_29_100500_add_new_pal_ai_stack_submodule_menu.php uses
        // silently no-ops here. `new_pal.index` is stable across every
        // environment that has New PAL at all (it's what routeMapper.ts and
        // every sibling sub-module migration resolve the parent by too).
        $parent = DB::table('tblmenumaster')
            ->where('link', 'new_pal.index')
            ->where('level', 2)
            ->orderBy('id')
            ->first();

        if ($parent === null) {
            return;
        }

        foreach (self::SUB_MODULES as $subModule) {
            if (DB::table('tblmenumaster')->where('link', $subModule['link'])->exists()) {
                continue;
            }

            $sortOrder = ((int) DB::table('tblmenumaster')
                ->where('parent_menu_id', $parent->id)
                ->max('sort_order')) + 1;

            $menuId = DB::table('tblmenumaster')->insertGetId([
                'name' => $subModule['name'],
                'menu_title' => $parent->menu_title,
                'description' => $subModule['description'],
                'parent_menu_id' => $parent->id,
                'level' => 3,
                'status' => 1,
                'sort_order' => $sortOrder,
                'link' => $subModule['link'],
                'icon' => $subModule['icon'],
                'sub_institute_id' => $parent->sub_institute_id,
                'client_id' => $parent->client_id,
                'menu_type' => 'ENTRY',
                'site_map_name' => $subModule['name'],
                'menu_path' => $subModule['name'],
                'created_at' => now(),
            ]);

            $this->mirrorRights((int) $parent->id, (int) $menuId, $subModule['withhold_student']);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $links = array_column(self::SUB_MODULES, 'link');
        $menuIds = DB::table('tblmenumaster')->whereIn('link', $links)->pluck('id');

        if ($menuIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('tblgroupwise_rights')) {
            DB::table('tblgroupwise_rights')->whereIn('menu_id', $menuIds)->delete();
        }

        DB::table('tblmenumaster')->whereIn('id', $menuIds)->delete();
    }

    /** A sub-module is visible to exactly the profiles its parent module is, minus students when withheld. */
    private function mirrorRights(int $parentMenuId, int $menuId, bool $withholdStudent): void
    {
        if (! Schema::hasTable('tblgroupwise_rights')) {
            return;
        }

        $studentProfileIds = $withholdStudent && Schema::hasTable('tbluserprofilemaster')
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
