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
        Schema::table('tblstudent_family_history', function (Blueprint $table) {
            //
            $table->string('annual_income')->nullable()->after('relation_with_student');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tblstudent_family_history', function (Blueprint $table) {
            //
            $table->dropColumn('annual_income');
        });
    }
};
