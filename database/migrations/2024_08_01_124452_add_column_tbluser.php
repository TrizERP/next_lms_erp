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
        Schema::table('tbluser', function (Blueprint $table) {
            $table->string('pan_no',20)->nullable()->after('occupation');
            $table->string('aadhar_no',50)->nullable()->after('pan_no');
            $table->string('pf_no',20)->nullable()->after('aadhar_no');
            $table->string('esic_no',20)->nullable()->after('pf_no');
            $table->string('uan_no',20)->nullable()->after('esic_no');
            $table->char('tds_deduction')->default('N')->after('uan_no');
            $table->char('pf_deduction')->default('N')->after('tds_deduction');
            $table->char('pt_deduction')->default('N')->after('pf_deduction');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tbluser', function (Blueprint $table) {
            $table->dropColumn('pan_no');
            $table->dropColumn('aadhar_no');
            $table->dropColumn('pf_no');
            $table->dropColumn('esic_no');
            $table->dropColumn('uan_no');
            $table->dropColumn('tds_deduction');
            $table->dropColumn('pf_deduction');
            $table->dropColumn('pt_deduction');
        });
    }
};
