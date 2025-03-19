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
        // add columns in table tblstudent_past_education
        Schema::table('tblstudent_past_education', function (Blueprint $table) {
            $table->mediumText('reason_of_leaving')->after('trial')->nullable();
            $table->biginteger('created_by')->after('sub_institute_id')->nullable();
            $table->timestamp('updated_on')->after('created_on')->nullable();
        });

         // add columns in table tblstudent_past_education
        Schema::table('tblstudent_family_history', function (Blueprint $table) {
            $table->date('birthdate')->after('annual_income')->nullable();
            $table->string('blood_group',255)->after('birthdate')->nullable();
            $table->string('designation',255)->after('blood_group')->nullable();
            $table->string('company_name',255)->after('designation')->nullable();
            $table->string('office_address',255)->after('company_name')->nullable();
            $table->string('mobile_no',255)->after('office_address')->nullable();
            $table->mediumText('guardian_address')->after('mobile_no')->nullable();
            $table->biginteger('created_by')->after('sub_institute_id')->nullable();
            $table->timestamp('updated_on')->after('created_on')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // add columns in table tblstudent_past_education
        Schema::table('tblstudent_past_education', function (Blueprint $table) {
            $table->dropColumn('reason_of_leaving');
            $table->dropColumn('created_by');
            $table->dropColumn('updated_on');
        });

         // add columns in table tblstudent_past_education
        Schema::table('tblstudent_family_history', function (Blueprint $table) {
            $table->dropColumn('birthdate');
            $table->dropColumn('blood_group');
            $table->dropColumn('designation');
            $table->dropColumn('company_name');
            $table->dropColumn('office_address');
            $table->dropColumn('mobile_no');
            $table->dropColumn('guardian_address');
            $table->dropColumn('created_by');
            $table->dropColumn('updated_on');
        });
    }
};
