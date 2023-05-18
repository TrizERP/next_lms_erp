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
        Schema::table('employee_salary_structures', function (Blueprint $table) {
            $table->integer('year');
            $table->integer('sub_institute_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('employee_salary_structures', function (Blueprint $table) {
            $table->dropColumn('year');
            $table->dropColumn('sub_institute_id');
        });
    }
};
