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
        Schema::create('exam_schedule', function (Blueprint $table) {

            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('division_id')->references('id')->on('division')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('fees_breackoff', function (Blueprint $table) {
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('grade_id')->references('id')->on('grade_master')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('fees_cancel', function (Blueprint $table) {
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('student_id')->references('id')->on('tblstudent')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('fees_circular_master', function (Blueprint $table) {
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('student_id')->references('id')->on('tblstudent')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('fees_collect', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('tblstudent')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('fees_late_master', function (Blueprint $table) {
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('fees_other_cancel', function (Blueprint $table) {
            $table->foreign('fees_other_collection_id')->references('id')->on('fees_collect')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('student_id')->references('id')->on('tblstudent')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('fees_other_collection', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('tblstudent')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('fees_paid_other', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('tblstudent')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('fees_payment', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('tblstudent')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('fees_receipt_book_master', function (Blueprint $table) {
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('grade_id')->references('id')->on('grade_master')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('fees_head_id')->references('id')->on('fees_head_master')
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
        Schema::table('exam_schedule', function (Blueprint $table) {
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['division_id']);
        });
        Schema::table('fees_breackoff', function (Blueprint $table) {
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['grade_id']);
        });
        Schema::table('fees_cancel', function (Blueprint $table) {
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['student_id']);
        });
        Schema::table('fees_circular_master', function (Blueprint $table) {
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['student_id']);
        });
        Schema::table('fees_collect', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
        });
        Schema::table('fees_late_master', function (Blueprint $table) {
            $table->dropForeign(['standard_id']);
        });
        Schema::table('fees_other_cancel', function (Blueprint $table) {
            $table->dropForeign(['fees_other_collection_id']);
            $table->dropForeign(['student_id']);
        });
        Schema::table('fees_other_collection', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
        });
        Schema::table('fees_paid_other', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
        });
        Schema::table('fees_payment', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
        });
        Schema::table('fees_receipt_book_master', function (Blueprint $table) {
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['grade_id']);
            $table->dropForeign(['fees_head_id']);
        });
    }
};
