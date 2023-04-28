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
        Schema::create('ptm_booking_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('ID');
            $table->date('DATE')->nullable();
            $table->integer('TEACHER_ID')->nullable();
            $table->integer('TIME_SLOT_ID')->nullable();
            $table->string('CONFIRM_STATUS', 50)->nullable();
            $table->timestamp('CREATED_ON')->nullable()->useCurrent();
            $table->integer('STUDENT_ID')->nullable();
            $table->integer('SUB_INSTITUTE_ID')->nullable();
            $table->string('PTM_ATTENDED_STATUS', 50)->nullable();
            $table->mediumText('PTM_ATTENDED_REMARKS')->nullable();
            $table->date('PTM_ATTENDED_ENTRY_DATE')->nullable();
            $table->integer('PTM_ATTENDED_BY')->nullable();
            $table->string('PTM_ATTENDED_CREATED_IP', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ptm_booking_master');
    }
};
