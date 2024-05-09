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
        // tbluser columns 
        Schema::table('tbluser', function (Blueprint $table) {
            $table->string('qualification',50)->nullable()->after('employee_no');
            $table->string('occupation',50)->nullable()->after('qualification');
        });
        // payroll_types table columns
        Schema::table('payroll_types', function (Blueprint $table) {
            $table->integer('sort_order')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // tbluser columns 
        Schema::table('tbluser', function (Blueprint $table) {
            $table->dropColumn('qualification');
            $table->dropColumn('occupation');
        });
        // payroll_types table columns
        Schema::table('payroll_types', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
