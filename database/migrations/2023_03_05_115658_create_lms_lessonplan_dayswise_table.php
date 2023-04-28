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
        Schema::create('lms_lessonplan_dayswise', function (Blueprint $table) {
            $table->comment('This table contain all days wise plan');
            $table->bigIncrements('id');
            $table->integer('sub_institute_id')->nullable();
            $table->bigInteger('lpid')->nullable();
            $table->integer('days')->nullable();
            $table->date('date')->nullable();
            $table->integer('period_id')->nullable();
            $table->integer('teacher_id')->nullable();
            $table->string('topicname', 150)->nullable();
            $table->string('classtime', 50)->nullable();
            $table->longText('duringcontent')->nullable();
            $table->longText('assessmentqualifying')->nullable();
            $table->longText('learningobjective')->nullable();
            $table->longText('learningoutcome')->nullable();
            $table->longText('pedagogicalprocess')->nullable();
            $table->longText('resource')->nullable();
            $table->longText('closure')->nullable();
            $table->longText('selfstudyhomework')->nullable();
            $table->string('selfstudyactivity', 150)->nullable();
            $table->longText('assessment')->nullable();
            $table->string('assessmentactivity', 150)->nullable();
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
        Schema::dropIfExists('lms_lessonplan_dayswise');
    }
};
