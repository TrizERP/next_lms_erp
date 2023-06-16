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
        Schema::create('batch', function (Blueprint $table) {
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('division_id')->references('id')->on('division')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('book_list', function (Blueprint $table) {
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('subject_id')->references('id')->on('subject')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('chapter_id')->references('id')->on('chapter_master')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('topic_id')->references('id')->on('topic_master')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('certificate_history', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('tblstudent')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('chapter_master', function (Blueprint $table) {
            $table->foreign('grade_id')->references('id')->on('grade_master')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('subject_id')->references('id')->on('subject')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('circular', function (Blueprint $table) {

            $table->foreign('division_id')->references('id')->on('division')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');

        });
        Schema::create('class_teacher', function (Blueprint $table) {
            $table->foreign('division_id')->references('id')->on('division')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('grade_id')->references('id')->on('grade_master')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('college_timetable', function (Blueprint $table) {

            $table->foreign('division_id')->references('id')->on('division')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('academic_section_id')->references('id')->on('academic_section')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('batch_id')->references('id')->on('batch')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('period_id')->references('id')->on('period')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('subject_id')->references('id')->on('subject')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('consent_master', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('tblstudent')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('division_id')->references('id')->on('division')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('topic_id')->references('id')->on('topic_master')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('counselling_answer_master', function (Blueprint $table) {

            $table->foreign('question_id')->references('id')->on('lms_question_master')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('counselling_online_exam_answer', function (Blueprint $table) {

            $table->foreign('user_id')->references('id')->on('tbluser')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('online_exam_id')->references('id')->on('counselling_online_exam')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('question_id')->references('id')->on('lms_question_master')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('answer_id')->references('id')->on('answer_master')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('counselling_online_exam', function (Blueprint $table) {

            $table->foreign('user_id')->references('id')->on('tbluser')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('batch', function (Blueprint $table) {
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['division_id']);
        });
        Schema::table('book_list', function (Blueprint $table) {
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['subject_id']);
            $table->dropForeign(['chapter_id']);
            $table->dropForeign(['topic_id']);
        });
        Schema::table('certificate_history', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
        });
        Schema::table('chapter_master', function (Blueprint $table) {
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['subject_id']);
            $table->dropForeign(['grade_id']);
        });
        Schema::table('circular', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
            $table->dropForeign(['standard_id']);
        });
        Schema::table('class_teacher', function (Blueprint $table) {
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['division_id']);
            $table->dropForeign(['grade_id']);
        });
        Schema::table('college_timetable', function (Blueprint $table) {
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['division_id']);
            $table->dropForeign(['academic_section_id']);
            $table->dropForeign(['batch_id']);
            $table->dropForeign(['period_id']);
            $table->dropForeign(['subject_id']);
        });
        Schema::table('consent_master', function (Blueprint $table) {
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['division_id']);
            $table->dropForeign(['topic_id']);
            $table->dropForeign(['student_id']);
        });
        Schema::table('counselling_answer_master', function (Blueprint $table) {
            $table->dropForeign(['question_id']);
        });
        Schema::table('counselling_online_exam_answer', function (Blueprint $table) {
            $table->dropForeign(['online_exam_id']);
            $table->dropForeign(['user_id']);
            $table->dropForeign(['question_id']);
            $table->dropForeign(['answer_id']);
        });
        Schema::table('counselling_online_exam', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
    }
};
