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
        Schema::create('fees_refund', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('tblstudent')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
        Schema::create('fees_title', function (Blueprint $table) {
            $table->foreign('fees_title_id')->references('id')->on('fees_title_master')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
        Schema::create('follow_up', function (Blueprint $table) {
            $table->foreign('enquiry_id')->references('id')->on('admission_enquiry')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
        Schema::create('homework', function (Blueprint $table) {
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->foreign('student_id')->references('id')->on('tblstudent')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->foreign('subject_id')->references('id')->on('subject')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->foreign('division_id')->references('id')->on('division')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
        Schema::create('hostel_building_master', function (Blueprint $table) {
            $table->foreign('hostel_type_id')->references('id')->on('hostel_type_id')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->foreign('hostel_id')->references('id')->on('hostel_master')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
        Schema::create('hostel_floor_master', function (Blueprint $table) {
            $table->foreign('building_id')->references('id')->on('hostel_building_master')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
        Schema::create('hostel_room_allocation', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('tbl_user')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->foreign('admission_category_id')->references('id')->on('admission_category_master')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->foreign('hostel_id')->references('id')->on('hostel_master')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->foreign('room_id')->references('id')->on('hostel_room_master')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
        Schema::create('implementation_master', function (Blueprint $table) {
            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
        Schema::create('imprest_fees_cancel', function (Blueprint $table) {

            $table->foreign('standard_id')->references('id')->on('standard')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->foreign('student_id')->references('id')->on('tblstudent')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
        Schema::create('inventory_item_master', function (Blueprint $table) {

            $table->foreign('category_id')->references('id')->on('inventory_item_category_master')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->foreign('sub_category_id')->references('id')->on('inventory_item_sub_category_master')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('fees_refund', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
        });
        Schema::table('fees_title', function (Blueprint $table) {
            $table->dropForeign(['fees_title_id']);
        });
        Schema::table('follow_up', function (Blueprint $table) {
            $table->dropForeign(['enquiry_id']);
        });
        Schema::table('homework', function (Blueprint $table) {
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['student_id']);
            $table->dropForeign(['subject_id']);
            $table->dropForeign(['division_id']);
        });
        Schema::table('hostel_building_master', function (Blueprint $table) {
            $table->dropForeign(['hostel_type_id']);
            $table->dropForeign(['hostel_id']);
        });
        Schema::table('hostel_floor_master', function (Blueprint $table) {
            $table->dropForeign(['building_id']);
        });
        Schema::table('hostel_room_allocation', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['admission_category_id']);
            $table->dropForeign(['hostel_id']);
            $table->dropForeign(['room_id']);
        });
        Schema::table('implementation_master', function (Blueprint $table) {
            $table->dropForeign(['standard_id']);
        });
        Schema::table('imprest_fees_cancel', function (Blueprint $table) {
            $table->dropForeign(['standard_id']);
            $table->dropForeign(['student_id']);
        });
        Schema::table('inventory_item_master', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['sub_category_id']);
        });
    }
};
