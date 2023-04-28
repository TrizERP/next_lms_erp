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
        Schema::create('college_timetable', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('sub_institute_id');
            $table->integer('syear');
            $table->bigInteger('academic_section_id')->index('timetable_academic_section_id_foreign');
            $table->bigInteger('standard_id')->index('timetable_standard_id_foreign');
            $table->bigInteger('division_id')->index('timetable_division_id_foreign');
            $table->bigInteger('batch_id')->nullable();
            $table->bigInteger('period_id')->index('timetable_period_id_foreign');
            $table->bigInteger('subject_id')->index('timetable_subject_id_foreign');
            $table->bigInteger('teacher_id')->index('timetable_teacher_id_foreign');
            $table->string('week_day', 255);
            $table->string('extended', 10)->nullable();
            $table->string('type', 50)->nullable();
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
        Schema::dropIfExists('college_timetable');
    }
};
