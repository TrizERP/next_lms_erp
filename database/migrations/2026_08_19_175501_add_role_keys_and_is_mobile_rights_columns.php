<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Role & Permissions backend port (G2G / hp_erp -> LMS-K12 organization_management).
 *
 * Per product decision, the new Role & Permissions screen is wired onto
 * LMS-K12's EXISTING `tbluserprofilemaster` / `tblgroupwise_rights` /
 * `tblindividual_rights` tables - no new tblmenumaster_g2g-style tables.
 *
 * `tblgroupwise_rights` already has `dashboard_right`
 * (2025_03_13_161022_add_column_dashboard_rights.php), so only `role_key`,
 * `data_scope` and `is_system` are missing on `tbluserprofilemaster`
 * (source: G2G's 2026_08_07_120000_add_reporting_line_and_role_keys.php).
 *
 * `is_mobile` is added to both rights tables to match the flag G2G's
 * storeGroupwiseRightsG2g()/tblgroupwise_rights_g2gModel actually writes -
 * simple boolean, no tri-state columns added (menu-rights.ts's
 * MenuRightFlags is boolean-only, confirmed by reading the source
 * controller: can_view/can_add/can_edit/can_delete/dashboard_right/is_mobile,
 * all plain ints).
 *
 * All columns nullable/defaulted, additive only. Existing
 * app/general/groupwise_rights and individual_rights code is untouched -
 * this migration only adds columns those tables' existing consumers simply
 * do not select, so their behaviour is unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tbluserprofilemaster')) {
            Schema::table('tbluserprofilemaster', function (Blueprint $table) {
                if (! Schema::hasColumn('tbluserprofilemaster', 'role_key')) {
                    $table->string('role_key', 64)->nullable()->index();
                }
                if (! Schema::hasColumn('tbluserprofilemaster', 'data_scope')) {
                    $table->enum('data_scope', ['self', 'team', 'department', 'organization'])->nullable();
                }
                if (! Schema::hasColumn('tbluserprofilemaster', 'is_system')) {
                    $table->boolean('is_system')->default(false);
                }
            });
        }

        if (Schema::hasTable('tblgroupwise_rights') && ! Schema::hasColumn('tblgroupwise_rights', 'is_mobile')) {
            Schema::table('tblgroupwise_rights', function (Blueprint $table) {
                $table->integer('is_mobile')->nullable()->default(0);
            });
        }

        if (Schema::hasTable('tblindividual_rights') && ! Schema::hasColumn('tblindividual_rights', 'is_mobile')) {
            Schema::table('tblindividual_rights', function (Blueprint $table) {
                $table->integer('is_mobile')->nullable()->default(0);
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'tbluserprofilemaster' => ['role_key', 'data_scope', 'is_system'],
            'tblgroupwise_rights' => ['is_mobile'],
            'tblindividual_rights' => ['is_mobile'],
        ] as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $columns) {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
