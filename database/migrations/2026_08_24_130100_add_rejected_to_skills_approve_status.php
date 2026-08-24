<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-fit fix for the ported `CompetencyApprovalController`.
 *
 * The source's approval SUBJECTS config for `subject_type = competency` writes
 * `s_users_skills.approve_status` through three states: 'Pending' -> 'Approved'
 * on approval, 'Pending' -> 'Rejected' on rejection (deliberately distinct from
 * 'Pending' so a rejected competency is resubmittable - see the controller's
 * class doc). This target's `s_users_skills.approve_status` column
 * (`2026_08_19_090000_create_s_users_skills_table.php`) was created with enum
 * `['Approved', 'Pending', 'Cancelled']` - no 'Rejected' value, so writing the
 * source's exact state would fail with a data-truncated/enum-constraint error.
 *
 * Purely additive: widens the enum by one value, changes no existing data or
 * behaviour of the columns's two other consumers (skill library approve/
 * reject flows already in this codebase, which never write 'Rejected').
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('s_users_skills', 'approve_status')) {
            return;
        }

        DB::statement("ALTER TABLE `s_users_skills` MODIFY `approve_status` ENUM('Approved','Pending','Cancelled','Rejected') NULL");
    }

    public function down(): void
    {
        if (!Schema::hasColumn('s_users_skills', 'approve_status')) {
            return;
        }

        DB::statement("ALTER TABLE `s_users_skills` MODIFY `approve_status` ENUM('Approved','Pending','Cancelled') NULL");
    }
};
