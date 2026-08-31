<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The original migration declared enum('approved','pending','rejected') but the
     * controllers have always written 'cancelled' and 'approved_lwp' as well. On a
     * strict-mode connection those writes fail, so widen the column to match reality.
     */
    public function up(): void
    {
        if (!Schema::hasTable('hrms_emp_leaves')) {
            return;
        }

        DB::statement("ALTER TABLE `hrms_emp_leaves` MODIFY `status` ENUM('approved','pending','rejected','cancelled','approved_lwp','sent_back') NULL");
    }

    public function down(): void
    {
        if (!Schema::hasTable('hrms_emp_leaves')) {
            return;
        }

        DB::statement("UPDATE `hrms_emp_leaves` SET `status` = 'pending' WHERE `status` IN ('cancelled','approved_lwp','sent_back')");
        DB::statement("ALTER TABLE `hrms_emp_leaves` MODIFY `status` ENUM('approved','pending','rejected') NULL");
    }
};
