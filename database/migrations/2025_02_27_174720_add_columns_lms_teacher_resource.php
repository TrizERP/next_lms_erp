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
        Schema::table('lms_teacher_resource', function (Blueprint $table) {
            //
            $table->text('mapping_value')->after('file_size')->nullable();
            $table->timestamp('updated_on')->nullable()->after('created_on');
        });
        // for lms lessaon plan 
        Schema::table('lms_lesson_plan', function (Blueprint $table) {
            //
            $table->text('mapping_value')->after('lesson_plan_number')->nullable();
            $table->timestamp('updated_on')->nullable()->after('timecreated');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lms_teacher_resource', function (Blueprint $table) {
            //
            $table->dropColumn('mapping_value');
            $table->dropColumn('updated_on');
        });
        // for lms lessaon plan 
        Schema::table('lms_lesson_plan', function (Blueprint $table) {
            //
            $table->dropColumn('mapping_value');
            $table->dropColumn('updated_on');
        });
    }
};
