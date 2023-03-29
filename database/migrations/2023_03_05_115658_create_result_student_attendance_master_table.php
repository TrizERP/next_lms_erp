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
        Schema::create('result_student_attendance_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('term_id');
            $table->string('standard');
            $table->string('sub_institute_id');
            $table->string('syear');
            $table->string('student_id');
            $table->string('attendance');
            $table->string('percentage');
            $table->string('remark_id');
            $table->string('teacher_remark');
            $table->timestamp('created_at')->useCurrentOnUpdate()->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('result_student_attendance_master');
    }
};
