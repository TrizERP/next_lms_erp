<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives Alumni Management (level-2 menu 893) the same category tabs as the other modules
 * (Operations, Reports, Intelligence, Communication, AI Stack, Schedular ...), and places its
 * 16 existing level-3 menus in them. Until now it had no category rows, so it showed one flat
 * bar of every menu.
 *
 * Placement:
 *   operations     894 Alumni Dashboard, 895 Alumni List, 896 Add Alumni, 897 Pending Verification,
 *                  918 Alumni Directory, 919 Batches, 920 Engagement, 921 Mentorship,
 *                  922 Jobs and Internships, 924 Alumni Events, 925 Alumni Surveys,
 *                  926 Donations, 928 Import Alumni
 *   reports        927 Alumni Reports
 *   communication  923 Communication
 *   audit-trail    929 Alumni Audit Log
 *
 * Idempotent: categories are matched on (module_name, category_key), items on
 * (module_name, category_key, menu_id). `down()` removes only this module's rows.
 */
return new class extends Migration
{
    private const MODULE = 'alumni-management';
    private const LEVEL2_MENU_ID = 893;
    private const LABEL = 'Alumni Management';

    /** key => [label, description format, sort_order] */
    private const CATEGORIES = [
        'onboarding' => ['Onboarding', 'Get started with %s.', 1],
        'process-builder' => ['Process Builder', 'Design and manage %s processes and approval flows.', 2],
        'master-setup' => ['Master Setup', 'Configuration and master data for %s.', 3],
        'operations' => ['Operations', 'Day-to-day %s screens.', 4],
        'reports' => ['Reports', '%s reporting and analysis.', 5],
        'intelligence' => ['Intelligence', 'Predictive and analytical views over %s data.', 6],
        'help-guide-support' => ['Help Guide/Support', 'Guides and support material for %s.', 7],
        'communication' => ['Communication', '%s notices and announcements.', 9],
        'ai-stack' => ['AI Stack', 'AI services and automation for %s.', 10],
        'workflow' => ['Workflow', 'Approval chains for %s.', 11],
        'schedular' => ['Schedular', 'Scheduled tasks for %s.', 12],
        'audit-trail' => ['Audit Trail', 'Who opened which %s screen, and when.', 13],
    ];

    /** category key => menu ids, in tab order */
    private const ITEMS = [
        'operations' => [894, 895, 896, 897, 918, 919, 920, 921, 922, 924, 925, 926, 928],
        'reports' => [927],
        'communication' => [923],
        'audit-trail' => [929],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_categories') || ! Schema::hasTable('fees_menu_category_items')) {
            return;
        }

        $hasLevel2 = Schema::hasColumn('fees_menu_categories', 'level2_menu_id');
        $hasAuditKeys = Schema::hasColumn('fees_menu_categories', 'audit_module_keys');
        $now = now();

        foreach (self::CATEGORIES as $key => [$label, $format, $sortOrder]) {
            $exists = DB::table('fees_menu_categories')
                ->where('module_name', self::MODULE)->where('category_key', $key)->exists();

            if ($exists) {
                continue;
            }

            $row = [
                'module_name' => self::MODULE,
                'category_key' => $key,
                'label' => $label,
                'description' => sprintf($format, self::LABEL),
                'route' => '/modules/'.self::MODULE.'/'.$key,
                'sort_order' => $sortOrder,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($hasLevel2) {
                $row['level2_menu_id'] = self::LEVEL2_MENU_ID;
            }
            if ($hasAuditKeys && $key === 'audit-trail') {
                $row['audit_module_keys'] = 'alumni';
            }

            DB::table('fees_menu_categories')->insert($row);
        }

        foreach (self::ITEMS as $category => $menuIds) {
            foreach ($menuIds as $i => $menuId) {
                $exists = DB::table('fees_menu_category_items')
                    ->where('module_name', self::MODULE)->where('category_key', $category)
                    ->where('menu_id', $menuId)->exists();

                if ($exists) {
                    continue;
                }

                DB::table('fees_menu_category_items')->insert([
                    'module_name' => self::MODULE,
                    'category_key' => $category,
                    'menu_id' => $menuId,
                    'sort_order' => $i + 1,
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fees_menu_category_items')) {
            DB::table('fees_menu_category_items')->where('module_name', self::MODULE)->delete();
        }
        if (Schema::hasTable('fees_menu_categories')) {
            DB::table('fees_menu_categories')->where('module_name', self::MODULE)->delete();
        }
    }
};
