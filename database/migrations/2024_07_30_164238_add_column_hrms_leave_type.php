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
        Schema::table('hrms_leave_types', function (Blueprint $table) {
            //
            $table->integer('sort_order')->default(0)->after('leave_type');
            $table->integer('status')->default(1)->after('sort_order');
            $table->integer('sub_institute_id')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('hrms_leave_types', function (Blueprint $table) {
            //
            $table->dropColumn('sort_order');
            $table->dropColumn('status');
            $table->dropColumn('sub_institute_id');
        });
    }
};
