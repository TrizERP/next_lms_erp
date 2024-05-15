<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('hrms_leave_allocation', function (Blueprint $table) {
            //
            $table->biginteger('department_id')->nullable()->after('employee_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('hrms_leave_allocation', function (Blueprint $table) {
            //
            $table->dropColumn('department_id');
        });
    }
};
