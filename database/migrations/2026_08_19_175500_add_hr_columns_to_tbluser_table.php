<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee Directory backend port (G2G / hp_erp -> LMS-K12 organization_management).
 *
 * LMS-K12's `tbluser` table already carries almost every HR column G2G's
 * `tbluser` has (department_id, jobtitle_id, employee_no, qualification,
 * occupation, pan_no, aadhar_no, pf_no, esic_no, uan_no, joined_date,
 * probation_period_from/to, terminated_date, termination_reason,
 * supervisor_opt, employee_id, reporting_method, bank_name, branch_name,
 * account_no, ifsc_code, ...) - confirmed by reading the existing migration
 * chain (2023_05_16, 2023_05_25, 2024_04_11, 2024_05_09, 2024_02_06).
 *
 * This migration adds only the columns G2G's tbluser has that LMS-K12's
 * still lacks:
 *   - fcm_token            G2G: 2026_05_12_054627_add_fcm_token_to_tbluser_table.php
 *   - reporting_manager_id G2G: 2026_08_07_120000_add_reporting_line_and_role_keys.php
 *   - load                 G2G: create_tbluser_table.php (integer, nullable)
 *   - total_experience     G2G: create_tbluser_table.php (kept as string(10) here,
 *                           matching G2G's later change_columns_tbluser.php)
 *   - employee_deposite    G2G: create_tbluser_table.php (decimal(10,2))
 *
 * All columns are nullable and additive. No existing column is renamed,
 * retyped or dropped. Guarded with hasColumn() so this is safe to run
 * against any environment regardless of ad-hoc column history.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tbluser')) {
            return;
        }

        Schema::table('tbluser', function (Blueprint $table) {
            if (! Schema::hasColumn('tbluser', 'fcm_token')) {
                $table->string('fcm_token', 255)->nullable();
            }
            if (! Schema::hasColumn('tbluser', 'load')) {
                $table->integer('load')->nullable();
            }
            if (! Schema::hasColumn('tbluser', 'total_experience')) {
                $table->string('total_experience', 10)->nullable();
            }
            if (! Schema::hasColumn('tbluser', 'employee_deposite')) {
                $table->decimal('employee_deposite', 10, 2)->nullable();
            }
        });

        // Separate Schema::table() call: self-referencing FK column, added
        // after the plain columns above so column-exists checks for the FK
        // target (tbluser.id) are unambiguous.
        if (! Schema::hasColumn('tbluser', 'reporting_manager_id')) {
            Schema::table('tbluser', function (Blueprint $table) {
                $table->unsignedBigInteger('reporting_manager_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tbluser')) {
            return;
        }

        Schema::table('tbluser', function (Blueprint $table) {
            foreach (['fcm_token', 'load', 'total_experience', 'employee_deposite', 'reporting_manager_id'] as $column) {
                if (Schema::hasColumn('tbluser', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
