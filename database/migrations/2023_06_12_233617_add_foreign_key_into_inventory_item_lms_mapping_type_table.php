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
        Schema::create('lms_mapping_type', function (Blueprint $table) {
            $table->foreign('chapter_id')->references('id')->on('chapter_master')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('topic_id')->references('id')->on('topic_master')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('lms_offline_exam_answer', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('tblstudent')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('question_id')->references('id')->on('lms_question_master')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('offline_exam_id')->references('id')->on('lms_offline_exam')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('lms_question_master', function (Blueprint $table) {

            $table->foreign('subject_id')->references('id')->on('subject')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('chapter_id')->references('id')->on('chapter_master')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('topic_id')->references('id')->on('topic_master')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('grade_id')->references('id')->on('grade_master')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('lms_teacher_resource', function (Blueprint $table) {

            $table->foreign('subject_id')->references('id')->on('subject')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('chapter_id')->references('id')->on('chapter_master')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('topic_id')->references('id')->on('topic_master')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('lms_virtual_classroom', function (Blueprint $table) {

            $table->foreign('subject_id')->references('id')->on('subject')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('chapter_id')->references('id')->on('chapter_master')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('topic_id')->references('id')->on('topic_master')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('grade_id')->references('id')->on('grade_master')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('lo_category', function (Blueprint $table) {

            $table->foreign('subject_id')->references('id')->on('subject')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('grade_id')->references('id')->on('grade_master')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('period', function (Blueprint $table) {

            $table->foreign('academic_section_id')->references('id')->on('academic_section')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('academic_year_id')->references('id')->on('academic_year')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('proxy_master', function (Blueprint $table) {

            $table->foreign('division_id')->references('id')->on('division')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('subject_id')->references('id')->on('subject')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('grade_id')->references('id')->on('grade_master')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('period_id')->references('id')->on('period')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('question_master', function (Blueprint $table) {

            $table->foreign('subject_id')->references('id')->on('subject')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('grade_id')->references('id')->on('grade_master')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('question_paper', function (Blueprint $table) {

            $table->foreign('subject_id')->references('id')->on('subject')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('grade_id')->references('id')->on('grade_master')
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
        Schema::table('lms_mapping_type', function (Blueprint $table) {
            $table->dropForeign(['chapter_id']);
            $table->dropForeign(['topic_id']);
        });
        Schema::table('lms_offline_exam_answer', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropForeign(['question_id']);
            $table->dropForeign(['offline_exam_id']);
        });
        Schema::table('lms_question_master', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropForeign(['chapter_id']);
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['topic_id']);
            $table->dropForeign(['grade_id']);
        });
        Schema::table('lms_teacher_resource', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropForeign(['chapter_id']);
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['topic_id']);
        });
        Schema::table('lms_virtual_classroom', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropForeign(['chapter_id']);
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['topic_id']);
            $table->dropForeign(['grade_id']);
        });
        Schema::table('lo_category', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['grade_id']);
        });
        Schema::table('period', function (Blueprint $table) {
            $table->dropForeign(['academic_section_id']);
            $table->dropForeign(['academic_year_id']);
        });
        Schema::table('proxy_master', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
            $table->dropForeign(['subject_id']);
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['grade_id']);
            $table->dropForeign(['period_id']);
        });
        Schema::table('question_master', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['grade_id']);
        });
        Schema::table('question_paper', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['grade_id']);
        });
    }
};
