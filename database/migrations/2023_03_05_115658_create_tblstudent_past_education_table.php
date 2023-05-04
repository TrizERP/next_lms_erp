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
        Schema::create('tblstudent_past_education', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('student_id')->nullable();
            $table->string('course', 100)->nullable();
            $table->string('medium', 100)->nullable();
            $table->string('name_of_board', 100)->nullable();
            $table->string('year_of_passing', 100)->nullable();
            $table->string('percentage', 100)->nullable();
            $table->string('school_name', 100)->nullable();
            $table->string('place', 100)->nullable();
            $table->string('trial', 100)->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tblstudent_past_education');
    }
};
