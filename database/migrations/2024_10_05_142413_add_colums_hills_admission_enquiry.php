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
        Schema::table('admission_enquiry', function (Blueprint $table) {
            //
            $table->string('institute_branch')->after('interaction_remarks')->nullable();
            $table->date('activity_date')->after('institute_branch')->nullable();
            $table->time('activity_time')->after('activity_date')->nullable();
            $table->mediumText('activity_remarks')->after('activity_date')->nullable();
            $table->string('siblings')->after('activity_remarks')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('admission_enquiry', function (Blueprint $table) {
            //
            $table->dropColumn('institute_branch');
            $table->dropColumn('activity_date');
            $table->dropColumn('activity_time');
            $table->dropColumn('activity_remarks');
            $table->dropColumn('siblings');
        });
    }
};
