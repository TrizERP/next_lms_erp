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
         Schema::create('result_sub_activity', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->mediumText('title');
            $table->bigInteger('skill_id');
            $table->bigInteger('sub_skill_id');
            $table->integer('sort_order');
            $table->bigInteger('sub_institute_id')->nullable();
            $table->bigInteger('created_by')->nullable();
            $table->timestamps();
        });
        // add level type and standard in skill set table 
        Schema::table('result_activity_master', function (Blueprint $table) {
            $table->integer('standard')->nullable()->after('sort_order'); // change dtat type from varchar to integer
        });
        Schema::table('result_skillset', function (Blueprint $table) {
            $table->integer('standard')->nullable()->after('title'); 
        });

        Schema::table('result_activity_marks', function (Blueprint $table) {
            $table->integer('sub_activity_id')->nullable()->after('activity_id'); 
            $table->integer('syear')->nullable()->after('sub_institute_id'); 
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('result_sub_activity');
        // add level type and standard in skill set table 
        Schema::table('result_activity_master', function (Blueprint $table) {
            $table->dropColumn('standard');
        });
        Schema::table('result_skillset', function (Blueprint $table) {
            //
            $table->dropColumn('standard');
        });
        
        Schema::table('result_activity_marks', function (Blueprint $table) {
            $table->dropColumn('sub_activity_id');
            $table->dropColumn('syear');
        });
    }
};
