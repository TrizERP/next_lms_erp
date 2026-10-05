<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers the "Exam & Assesment" sidebar module (tblmenumaster id 276 — online exams,
 * homework, assignments, worksheets, projects) properly, and corrects the placeholder
 * name it was left under.
 *
 * WHAT WAS WRONG
 *
 * `fees_menu_categories`/`fees_menu_category_items` carried this menu's twelve category
 * rows under `module_name = 'test'` — a placeholder that leaked into production. Every
 * category's `description` read "for Test." on the AI Stack tab and the eleven others
 * beside it, and no `ai_modules` row, MCP tool, template or agent existed for it at all:
 * the module was, in effect, invisible to the AI Stack while still being linked to from
 * the sidebar as "Exam & Assesment".
 *
 * This is DIFFERENT from `ai_modules.module_key = 'exam'` (tblmenumaster id 67, "Exam" —
 * Mark Entry, Upload Result). The two names collide in prose; they are unrelated tables,
 * unrelated menus and, from here on, unrelated modules with their own case types.
 *
 * WHY A RENAME RATHER THAN A NEW ROW SET
 *
 * The twelve rows (Onboarding through Audit Trail) already exist, are already linked from
 * the sidebar, and their `route` columns are already correct for the generic
 * `/modules/{key}/{category}` dispatcher — only the key itself and the "Test" wording in
 * each description are wrong. Renaming in place keeps every existing link working; adding
 * a parallel row set under a new key would leave the old, broken one still reachable.
 *
 * `module_name = 'exam-assessment'` (hyphenated) is the menu/route slug, matching the
 * convention `new-pal`, `curriculum-planning` and `student-request` already use — it does
 * not have to equal, and here does not equal, `ai_modules.module_key = 'exam_assessment'`
 * (underscored, matching `config/ai.php`'s convention). Both spellings are correct for
 * what they name.
 *
 * ONLY module_name = 'test' ROWS ARE TOUCHED. Confirmed before writing this migration:
 * no `ai_modules`, `ai_templates`, `ai_policies` or `ai_conversations` row anywhere uses
 * `module_key = 'test'` — this was genuinely unregistered infrastructure, not a working
 * module being renamed out from under something that depends on the old key.
 */
return new class extends Migration
{
    private const OLD_MODULE_NAME = 'test';

    private const NEW_MODULE_NAME = 'exam-assessment';

    private const MODULE_KEY = 'exam_assessment';

    private const LEVEL2_MENU_ID = 276;

    public function up(): void
    {
        $this->renameMenuCategoryRows();
        $this->registerAiModule();
        $this->registerAgentRbacMenuRow();
    }

    public function down(): void
    {
        if (Schema::hasTable('fees_menu_categories')) {
            foreach (DB::table('fees_menu_categories')->where('module_name', self::NEW_MODULE_NAME)->get() as $row) {
                DB::table('fees_menu_categories')->where('id', $row->id)->update([
                    'module_name' => self::OLD_MODULE_NAME,
                    'description' => str_replace('Exam & Assessment', 'Test', (string) $row->description),
                    'route' => str_replace('/modules/'.self::NEW_MODULE_NAME, '/modules/test', (string) $row->route),
                    'updated_at' => now(),
                ]);
            }
        }

        if (Schema::hasTable('fees_menu_category_items')) {
            DB::table('fees_menu_category_items')
                ->where('module_name', self::NEW_MODULE_NAME)
                ->update(['module_name' => self::OLD_MODULE_NAME, 'updated_at' => now()]);
        }

        if (Schema::hasTable('ai_modules')) {
            DB::table('ai_modules')->where('module_key', self::MODULE_KEY)->update(['status' => 0, 'updated_at' => now()]);
        }

        if (Schema::hasTable('tblmenumaster')) {
            $ids = DB::table('tblmenumaster')->where('link', 'ai_agents.'.self::MODULE_KEY)->pluck('id');

            if ($ids->isNotEmpty()) {
                foreach (['tblgroupwise_rights', 'tblindividual_rights'] as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->whereIn('menu_id', $ids)->delete();
                    }
                }

                DB::table('tblmenumaster')->whereIn('id', $ids)->delete();
            }
        }
    }

    /**
     * Rename the twelve `test` rows in place and replace "Test" with "Exam & Assessment"
     * in every description — a straightforward substring swap, since every description
     * was generated from the same "… for Test." template.
     */
    private function renameMenuCategoryRows(): void
    {
        if (Schema::hasTable('fees_menu_categories')) {
            foreach (DB::table('fees_menu_categories')->where('module_name', self::OLD_MODULE_NAME)->get() as $row) {
                DB::table('fees_menu_categories')->where('id', $row->id)->update([
                    'module_name' => self::NEW_MODULE_NAME,
                    'description' => str_replace('Test', 'Exam & Assessment', (string) $row->description),
                    'route' => str_replace('/modules/test', '/modules/'.self::NEW_MODULE_NAME, (string) $row->route),
                    'updated_at' => now(),
                ]);
            }
        }

        if (Schema::hasTable('fees_menu_category_items')) {
            DB::table('fees_menu_category_items')
                ->where('module_name', self::OLD_MODULE_NAME)
                ->update(['module_name' => self::NEW_MODULE_NAME, 'updated_at' => now()]);
        }
    }

    private function registerAiModule(): void
    {
        if (! Schema::hasTable('ai_modules')) {
            return;
        }

        $existing = DB::table('ai_modules')->where('module_key', self::MODULE_KEY)->whereNull('sub_institute_id')->first();

        $row = [
            'module_key' => self::MODULE_KEY,
            'label' => 'Exam & Assessment',
            'domain' => 'k12',
            'description' => 'Online exams, homework, assignments, worksheets and projects.',
            'route_patterns' => json_encode([
                '/modules/exam-assessment', '/modules/exam-assessment/**',
            ]),
            'entity_key' => null,
            'entity_param' => null,
            'capabilities' => json_encode(['conversational' => true, 'generative' => false, 'agent' => false, 'workflow' => false, 'ontology' => false]),
            'allowed_roles' => null,
            'icon' => 'clipboard-check',
            'sort_order' => 85,
            'match_priority' => 70,
            'status' => 1,
            'sub_institute_id' => null,
            'client_id' => null,
            'updated_at' => now(),
        ];

        if ($existing !== null) {
            DB::table('ai_modules')->where('id', $existing->id)->update($row);

            return;
        }

        DB::table('ai_modules')->insert($row + ['created_at' => now()]);
    }

    /**
     * The `agents.exam_assessment` right's menu row, mirroring
     * 2026_09_22_100300_add_five_module_ai_agent_menu_rows.php exactly — so the same
     * "Your role cannot enable agents…" failure the Fees Automations tab originally
     * shipped with cannot recur for this module either.
     */
    private function registerAgentRbacMenuRow(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $parent = DB::table('tblmenumaster')->where('link', 'ai_agents')->first();

        if ($parent === null) {
            return;
        }

        $link = 'ai_agents.'.self::MODULE_KEY;
        $menuId = DB::table('tblmenumaster')->where('link', $link)->value('id');

        if ($menuId === null) {
            $menuId = DB::table('tblmenumaster')->insertGetId([
                'name' => 'Exam & Assessment',
                'menu_title' => 'Exam & Assessment',
                'description' => 'AI agents — Exam & Assessment.',
                'parent_menu_id' => $parent->id,
                'level' => 3,
                'status' => 1,
                'sort_order' => 10,
                'link' => $link,
                'icon' => 'mdi mdi-clipboard-check-outline',
                'sub_institute_id' => $parent->sub_institute_id,
                'client_id' => $parent->client_id,
                'menu_type' => 'ENTRY',
                'created_at' => now(),
            ]);
        }

        $this->mirrorGrants((int) $menuId);
    }

    /** Group-wise only — see the five-module RBAC migration for why. */
    private function mirrorGrants(int $menuId): void
    {
        if (! Schema::hasTable('tblgroupwise_rights')) {
            return;
        }

        $templateId = DB::table('tblmenumaster')->where('link', 'ai_agents.fees')->value('id');

        if ($templateId === null) {
            return;
        }

        foreach (DB::table('tblgroupwise_rights')->where('menu_id', $templateId)->get() as $source) {
            $profileId = $source->profile_id ?? null;

            if ($profileId === null) {
                continue;
            }

            $institute = $source->sub_institute_id ?? null;

            $exists = DB::table('tblgroupwise_rights')
                ->where('menu_id', $menuId)
                ->where('profile_id', $profileId)
                ->where(fn ($query) => $institute === null
                    ? $query->whereNull('sub_institute_id')
                    : $query->where('sub_institute_id', $institute))
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('tblgroupwise_rights')->insert([
                'menu_id' => $menuId,
                'profile_id' => $profileId,
                'can_view' => $source->can_view ?? 0,
                'can_add' => $source->can_add ?? 0,
                'can_edit' => $source->can_edit ?? 0,
                'can_delete' => $source->can_delete ?? 0,
                'sub_institute_id' => $institute,
                'created_at' => now(),
            ]);
        }
    }
};
