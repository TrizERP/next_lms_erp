<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The menu rows the five LMS + PAL `agents.<module>` rights are stored against, plus the
 * same grants the equivalent Fees row already carries.
 *
 * Same pattern as 2026_09_26_100300_add_remaining_module_ai_agent_menu_rows.php — see that
 * file for why a row is needed at all and why the grants are mirrored rather than invented.
 *
 * ALL FIVE KEYS ARE NEW. Unlike the six modules the previous migration in this series
 * covered, none of `teach_learn`, `curriculum_planning`, `engagement`, `interactions` or
 * `new_pal` had an `ai_modules` row before this batch — they are registered by the
 * companion migrations in this same change.
 *
 * NOTHING BELONGING TO AN EARLIER MODULE IS MODIFIED. The Fees row is read and never
 * written; every row added before these is left alone.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_28_100300_add_lms_pal_module_ai_agent_menu_rows.php
 */
return new class extends Migration
{
    private const PARENT_LINK = 'ai_agents';

    private const TEMPLATE_LINK = 'ai_agents.fees';

    /**
     * link => [name, icon, sort order]
     *
     * Sort orders continue from 33, which `ai_agents.institute` holds.
     */
    private const CHILDREN = [
        'ai_agents.teach_learn' => ['Teach/Learn', 'mdi mdi-book-open-page-variant-outline', 34],
        'ai_agents.curriculum_planning' => ['Curriculum Planning', 'mdi mdi-clipboard-text-outline', 35],
        'ai_agents.engagement' => ['Engagement', 'mdi mdi-account-group-outline', 36],
        'ai_agents.interactions' => ['Interactions', 'mdi mdi-message-text-outline', 37],
        'ai_agents.new_pal' => ['New PAL', 'mdi mdi-brain', 38],
    ];

    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('tblmenumaster')) {
            return;
        }

        $parent = DB::table('tblmenumaster')->where('link', self::PARENT_LINK)->first();

        if ($parent === null) {
            return;
        }

        foreach (self::CHILDREN as $link => [$name, $icon, $sortOrder]) {
            $menuId = DB::table('tblmenumaster')->where('link', $link)->value('id');

            if ($menuId === null) {
                $menuId = DB::table('tblmenumaster')->insertGetId([
                    'name' => $name,
                    'menu_title' => $name,
                    'description' => "AI agents — {$name}.",
                    'parent_menu_id' => $parent->id,
                    'level' => 3,
                    'status' => 1,
                    'sort_order' => $sortOrder,
                    'link' => $link,
                    'icon' => $icon,
                    'sub_institute_id' => $parent->sub_institute_id,
                    'client_id' => $parent->client_id,
                    'menu_type' => 'ENTRY',
                    'created_at' => now(),
                ]);
            }

            $this->mirrorGrants((int) $menuId);
        }
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('tblmenumaster')) {
            return;
        }

        $ids = DB::table('tblmenumaster')->whereIn('link', array_keys(self::CHILDREN))->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        foreach (['tblgroupwise_rights', 'tblindividual_rights'] as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::table($table)->whereIn('menu_id', $ids)->delete();
            }
        }

        DB::table('tblmenumaster')->whereIn('id', $ids)->delete();
    }

    private function mirrorGrants(int $menuId): void
    {
        if (! DB::getSchemaBuilder()->hasTable('tblgroupwise_rights')) {
            return;
        }

        $templateId = DB::table('tblmenumaster')->where('link', self::TEMPLATE_LINK)->value('id');

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
