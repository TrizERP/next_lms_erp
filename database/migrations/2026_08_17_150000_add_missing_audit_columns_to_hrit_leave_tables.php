<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ported HRIT Leave API (app/Http/Controllers/api/Leave/*) was written
 * against hp_erp's actual table schemas, which include `created_by`,
 * `updated_by`, `deleted_by` audit columns (and, for hrms_holidays, a
 * `description` column) on hrms_weekdays / hrms_holidays / hrms_leave_types /
 * hrms_leave_allocation / hrms_emp_leaves. This app's copies of those tables
 * predate the HRIT migration and were never given those columns, so every
 * create/update/delete through the ported controllers 500s with "Unknown
 * column" (first surfaced via hrms_weekdays; the same gap exists on the
 * other four tables for the same reason).
 *
 * Purely additive: nullable columns with no default, so every existing row
 * is unaffected and no other existing query against these tables changes.
 * hrms_weekdays intentionally does NOT get sub_institute_id - hp_erp's own
 * schema doesn't have it there either (weekly-off pattern is institute-wide
 * in both apps, not per-tenant).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addAuditColumns('hrms_weekdays');

        if (Schema::hasTable('hrms_holidays') && ! Schema::hasColumn('hrms_holidays', 'description')) {
            Schema::table('hrms_holidays', function (Blueprint $table) {
                $table->string('description', 500)->nullable()->after('holiday_name');
            });
        }
        $this->addAuditColumns('hrms_holidays');

        $this->addAuditColumns('hrms_leave_types');
        $this->addAuditColumns('hrms_leave_allocation');
        $this->addAuditColumns('hrms_emp_leaves');
    }

    public function down(): void
    {
        if (Schema::hasTable('hrms_holidays') && Schema::hasColumn('hrms_holidays', 'description')) {
            Schema::table('hrms_holidays', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }

        foreach (['hrms_weekdays', 'hrms_holidays', 'hrms_leave_types', 'hrms_leave_allocation', 'hrms_emp_leaves'] as $tableName) {
            $this->dropAuditColumns($tableName);
        }
    }

    private function addAuditColumns(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (! Schema::hasColumn($tableName, 'created_by')) {
                $table->unsignedInteger('created_by')->nullable();
            }
            if (! Schema::hasColumn($tableName, 'updated_by')) {
                $table->unsignedInteger('updated_by')->nullable();
            }
            if (! Schema::hasColumn($tableName, 'deleted_by')) {
                $table->unsignedInteger('deleted_by')->nullable();
            }
        });
    }

    private function dropAuditColumns(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            foreach (['created_by', 'updated_by', 'deleted_by'] as $column) {
                if (Schema::hasColumn($tableName, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
