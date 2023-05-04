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
        Schema::create('lms_lesson_plan', function (Blueprint $table) {
            $table->comment('This table contain all set of lesson plan');
            $table->bigIncrements('id');
            $table->bigInteger('sub_institute_id')->nullable();
            $table->bigInteger('syear')->nullable();
            $table->bigInteger('standard_id')->nullable();
            $table->bigInteger('subject_id')->nullable();
            $table->bigInteger('chapter_id')->nullable();
            $table->bigInteger('topic_id')->nullable();
            $table->bigInteger('numberofperiod')->nullable();
            $table->string('teachingtime', 50)->nullable();
            $table->string('assessmenttime', 50)->nullable();
            $table->string('learningtime', 50)->nullable();
            $table->longText('assessmentqualifying')->nullable();
            $table->longText('focauspoint')->nullable();
            $table->longText('innovativepadagogy')->nullable();
            $table->longText('pedagogicalprocess')->nullable();
            $table->longText('resource')->nullable();
            $table->longText('classroompresentation')->nullable();
            $table->string('classroomactivity', 150)->nullable();
            $table->longText('classroomdiversity')->nullable();
            $table->longText('prerequisite')->nullable();
            $table->longText('learningobjective')->nullable();
            $table->longText('learningknowledge')->nullable();
            $table->longText('learningskill')->nullable();
            $table->longText('selfstudyhomework')->nullable();
            $table->string('selfstudyactivity', 150)->nullable();
            $table->longText('assessment')->nullable();
            $table->string('assessmentactivity', 150)->nullable();
            $table->longText('marks')->nullable();
            $table->longText('assessmentquetions')->nullable();
            $table->longText('hardword')->nullable();
            $table->longText('tagmetatag')->nullable();
            $table->longText('valueintegration')->nullable();
            $table->longText('globalconnection')->nullable();
            $table->longText('crosscurriculum')->nullable();
            $table->longText('sel')->nullable();
            $table->longText('stem')->nullable();
            $table->longText('vocationaltraining')->nullable();
            $table->longText('simulation')->nullable();
            $table->longText('games')->nullable();
            $table->longText('activities')->nullable();
            $table->longText('reallifeapplication')->nullable();
            $table->string('lesson_plan_number', 150)->nullable();
            $table->bigInteger('createdby')->nullable();
            $table->timestamp('timecreated')->nullable()->useCurrent();
            $table->string('ipaddress', 150)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lms_lesson_plan');
    }
};
