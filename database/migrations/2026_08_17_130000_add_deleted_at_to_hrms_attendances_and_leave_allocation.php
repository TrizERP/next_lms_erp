<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ported HRIT Attendance/Leave JSON API (app/Http/Controllers/api/
 * Attendance, app/Http/Controllers/api/Leave, app/Services/Leave) queries and
 * writes `deleted_at` on `hrms_attendances` and `hrms_leave_allocation`,
 * matching how these tables are shaped in the source project (hp_erp) it was
 * ported from. Both tables already exist here with real data (reused as-is,
 * per the "don't duplicate an existing table" migration rule) but were
 * created before soft-deletes were added to their hp_erp counterparts, so
 * they are missing the column. This is purely additive - a nullable column
 * with no default, so every existing row is unaffected (reads as "not
 * deleted") and no other existing query against either table changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hrms_attendances') && ! Schema::hasColumn('hrms_attendances', 'deleted_at')) {
            Schema::table('hrms_attendances', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('hrms_leave_allocation') && ! Schema::hasColumn('hrms_leave_allocation', 'deleted_at')) {
            Schema::table('hrms_leave_allocation', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hrms_attendances') && Schema::hasColumn('hrms_attendances', 'deleted_at')) {
            Schema::table('hrms_attendances', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('hrms_leave_allocation') && Schema::hasColumn('hrms_leave_allocation', 'deleted_at')) {
            Schema::table('hrms_leave_allocation', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
