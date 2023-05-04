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
        Schema::create('attendance_student', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('syear')->nullable();
            $table->integer('student_id')->nullable()->index('student_id');
            $table->integer('term_id')->nullable();
            $table->date('attendance_date')->nullable()->index('attendance_date');
            $table->string('attendance_code', 50)->nullable();
            $table->integer('teacher_id')->nullable();
            $table->integer('user_group_id')->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->string('created_by', 50)->nullable();
            $table->integer('standard_id')->nullable();
            $table->integer('section_id')->nullable();
            $table->integer('sub_institute_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('attendance_student');
    }
};
