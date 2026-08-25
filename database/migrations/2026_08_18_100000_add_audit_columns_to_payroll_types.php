<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ported PayrollController@payrollStore/payrollDestroy write created_by/
 * updated_by (matching hp_erp's payroll_types schema, which also has
 * deleted_by + softDeletes - see database/migrations/2025_09_11_053450_payroll_types.php
 * there), but this app's payroll_types table predates the HRIT migration and
 * never got those columns, so every save 500s with "Unknown column
 * 'created_by'". Same class of gap as hrms_weekdays/hrms_holidays/
 * hrms_leave_types/hrms_leave_allocation/hrms_emp_leaves fixed earlier this
 * migration (see 2026_08_17_150000_add_missing_audit_columns_to_hrit_leave_tables.php).
 *
 * Only created_by/updated_by are added here, not deleted_by/deleted_at:
 * payrollDestroy() does a hard delete (the PayrollType model has no
 * SoftDeletes trait), so a deleted_at column would sit unused - out of scope
 * for the reported bug. Purely additive: nullable columns, no default, zero
 * impact on existing rows or other queries.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_types')) {
            return;
        }

        Schema::table('payroll_types', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_types', 'created_by')) {
                $table->unsignedInteger('created_by')->nullable();
            }
            if (! Schema::hasColumn('payroll_types', 'updated_by')) {
                $table->unsignedInteger('updated_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payroll_types')) {
            return;
        }

        Schema::table('payroll_types', function (Blueprint $table) {
            foreach (['created_by', 'updated_by'] as $column) {
                if (Schema::hasColumn('payroll_types', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
