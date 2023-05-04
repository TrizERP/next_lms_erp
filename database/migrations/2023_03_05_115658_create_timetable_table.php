<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('timetable', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('sub_institute_id');
            $table->integer('syear');
            $table->unsignedInteger('academic_section_id')->index('timetable_academic_section_id_foreign');
            $table->unsignedInteger('standard_id')->index('timetable_standard_id_foreign');
            $table->unsignedInteger('division_id')->index('timetable_division_id_foreign');
            $table->unsignedInteger('batch_id')->nullable();
            $table->unsignedInteger('period_id')->index('timetable_period_id_foreign');
            $table->unsignedInteger('subject_id')->index('timetable_subject_id_foreign');
            $table->unsignedInteger('teacher_id')->index('timetable_teacher_id_foreign');
            $table->string('week_day', 255);
            $table->integer('merge')->nullable()->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('timetable');
    }
};
