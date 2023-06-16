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
        Schema::table('access_log_route', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('tbluser')
                ->onDelete('set null')
                ->onUpdate('set null');
        });

        Schema::table('access_log', function (Blueprint $table) {
            $table->foreign('USER_ID')->references('id')->on('tbluser')
                ->onDelete('set null')
                ->onUpdate('set null');
        });

        Schema::create('activity_log', function (Blueprint $table) {
            $table->foreign('subject_id')->references('id')->on('subject')
                ->onDelete('set null')
                ->onUpdate('set null');
        });

        Schema::create('admission_form', function (Blueprint $table) {
            $table->foreign('enquiry_id')->references('id')->on('admission_enquiry')
                ->onDelete('set null')
                ->onUpdate('set null');
        });

        Schema::create('admission_registration', function (Blueprint $table) {
            $table->foreign('enquiry_id')->references('id')->on('admission_enquiry')
                ->onDelete('set null')
                ->onUpdate('set null');
        });

        Schema::create('answer_master', function (Blueprint $table) {
            $table->foreign('question_id')->references('id')->on('lms_question_master')
                ->onDelete('set null')
                ->onUpdate('set null');
        });

        Schema::create('app_notification', function (Blueprint $table) {
            $table->foreign('STUDENT_ID')->references('id')->on('tblstudent')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('app_notification_teacher', function (Blueprint $table) {
            $table->foreign('USER_ID')->references('id')->on('tbluser')
                ->onDelete('set null')
                ->onUpdate('set null');

        });
        Schema::create('attendance_student', function (Blueprint $table) {
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
        Schema::table('access_log_route', function (Blueprint $table) {
            $table->dropForeign(['USER_ID']);
        });
        Schema::table('access_log', function (Blueprint $table) {
            $table->dropForeign(['USER_ID']);
        });
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
        });
        Schema::table('admission_form', function (Blueprint $table) {
            $table->dropForeign(['enquiry_id']);
        });
        Schema::table('admission_registration', function (Blueprint $table) {
            $table->dropForeign(['enquiry_id']);
        });
        Schema::table('answer_master', function (Blueprint $table) {
            $table->dropForeign(['question_id']);
        });
        Schema::table('app_notification', function (Blueprint $table) {
            $table->dropForeign(['STUDENT_ID']);
        });
        Schema::table('app_notification_teacher', function (Blueprint $table) {
            $table->dropForeign(['USER_ID']);
        });
        Schema::table('attendance_student', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
        });

    }
};
