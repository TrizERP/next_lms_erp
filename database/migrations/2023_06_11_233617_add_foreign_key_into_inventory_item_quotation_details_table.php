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
        Schema::create('inventory_item_quotation_details', function (Blueprint $table) {
            $table->foreign('vendor_id')->references('id')->on('inventory_vendor_master')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('inventory_item_sub_category_master', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('inventory_item_category_master')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('inward', function (Blueprint $table) {
            $table->foreign('place_id')->references('id')->on('place_master')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('file_location_id')->references('id')->on('physical_file_location')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('lb_master', function (Blueprint $table) {
            $table->foreign('grade_id')->references('id')->on('grade_master')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('lessonplan', function (Blueprint $table) {
            $table->foreign('subject_id')->references('id')->on('subject')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('grade_id')->references('id')->on('grade_master')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('division_id')->references('id')->on('division')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('lessonplan_execution', function (Blueprint $table) {

            $table->foreign('subject_id')->references('id')->on('subject')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('grade_id')->references('id')->on('grade_master')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('division_id')->references('id')->on('division')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('lms_assignment', function (Blueprint $table) {
            $table->foreign('subject_id')->references('id')->on('subject')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('grade_id')->references('id')->on('grade_master')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('division_id')->references('id')->on('division')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('lms_doubt', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('tbluser')
                ->onDelete('set null')
                ->onUpdate('set null');
            $table->foreign('doubt_id')->references('id')->on('lms_doubt')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
        Schema::create('lms_flashcard', function (Blueprint $table) {

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
        Schema::create('lms_lesson_plan', function (Blueprint $table) {

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
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('inventory_item_quotation_details', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
        });
        Schema::table('inventory_item_sub_category_master', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });
        Schema::table('inward', function (Blueprint $table) {
            $table->dropForeign(['place_id']);
            $table->dropForeign(['file_location_id']);
        });
        Schema::table('lb_master', function (Blueprint $table) {
            $table->dropForeign(['grade_id']);
            $table->dropForeign(['standard_id']);
        });
        Schema::table('lessonplan', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['grade_id']);
            $table->dropForeign(['division_id']);
        });
        Schema::table('lessonplan_execution', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['grade_id']);
            $table->dropForeign(['division_id']);
        });
        Schema::table('lms_assignment', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['grade_id']);
            $table->dropForeign(['division_id']);
        });
        Schema::table('lms_doubt', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['doubt_id']);
        });
        Schema::table('lms_flashcard', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['chapter_id']);
            $table->dropForeign(['topic_id']);
        });
        Schema::table('lms_lesson_plan', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['chapter_id']);
            $table->dropForeign(['topic_id']);
        });
    }
};
