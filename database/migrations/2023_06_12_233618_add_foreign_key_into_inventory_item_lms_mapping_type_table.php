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
        Schema::create('result_co_scholastic_marks_entries', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('tblstudent')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('grade_id')->references('id')->on('grade_master')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('result_create_exam', function (Blueprint $table) {
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('subject_id')->references('id')->on('subject')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('result_create_exam', function (Blueprint $table) {
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('grade_id')->references('id')->on('grade_master')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('student_id')->references('id')->on('tblstudent')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('division_id')->references('id')->on('division')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('result_student_attendance_master', function (Blueprint $table) {
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('student_id')->references('id')->on('tblstudent')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('sms_sent_parents', function (Blueprint $table) {
            $table->foreign('STUDENT_ID')->references('id')->on('tblstudent')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('student_capture_attendance', function (Blueprint $table) {
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('division_id')->references('id')->on('division')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('student_change_request', function (Blueprint $table) {
            $table->foreign('STUDENT_ID')->references('id')->on('tblstudent')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('STANDARD_ID')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('SECTION_ID')->references('id')->on('school_detail')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('student_height_weight', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('tblstudent')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('student_optional_subject', function (Blueprint $table) {
            $table->foreign('subject_id')->references('id')->on('subject')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('student_id')->references('id')->on('tblstudent')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('student_vaccination', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('tblstudent')
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
        Schema::table('result_co_scholastic_marks_entries', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['grade_id']);
        });
        Schema::table('result_create_exam', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropForeign(['standard_id']);
        });
        Schema::table('result_html', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropForeign(['grade_id']);
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['division_id']);
        });
        Schema::table('result_student_attendance_master', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropForeign(['standard_id']);
        });
        Schema::table('sms_sent_parents', function (Blueprint $table) {
            $table->dropForeign(['STUDENT_ID']);
        });
        Schema::table('student_capture_attendance', function (Blueprint $table) {
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['division_id']);
        });
        Schema::table('student_capture_attendance', function (Blueprint $table) {
            $table->dropForeign(['STUDENT_ID']);
            $table->dropForeign(['STANDARD_ID']);
            $table->dropForeign(['SECTION_ID']);
        });
        Schema::table('sms_sent_parents', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
        });
        Schema::table('student_optional_subject', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropForeign(['student_id']);
        });
        Schema::table('student_vaccination', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
        });

    }
};
