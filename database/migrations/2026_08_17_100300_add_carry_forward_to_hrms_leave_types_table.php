<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Carry forward has no home anywhere in the schema today - the annual quota
     * itself stays in hrms_leave_allocation (per department / employee / year).
     */
    public function up(): void
    {
        if (!Schema::hasColumn('hrms_leave_types', 'carry_forward')) {
            Schema::table('hrms_leave_types', function (Blueprint $table) {
                $table->boolean('carry_forward')->default(false)->after('sort_order');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('hrms_leave_types', 'carry_forward')) {
            Schema::table('hrms_leave_types', function (Blueprint $table) {
                $table->dropColumn('carry_forward');
            });
        }
    }
};
