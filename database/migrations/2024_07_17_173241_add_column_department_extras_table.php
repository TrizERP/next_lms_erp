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
        Schema::table('hrms_departments', function (Blueprint $table) {
            //
            $table->biginteger('parent_id')->nullable()->after('department');
            $table->mediumText('tasks')->nullable()->after('parent_id');
            $table->mediumText('roles_responsibility')->nullable()->after('tasks');
            $table->biginteger('sub_institute_id')->nullable()->after('is_calculated');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('hrms_departments', function (Blueprint $table) {
            //
            $table->dropColumn('parent_id');
            $table->dropColumn('tasks');
            $table->dropColumn('roles_responsibility');
            $table->dropColumn('sub_institute_id');
        });
    }
};
