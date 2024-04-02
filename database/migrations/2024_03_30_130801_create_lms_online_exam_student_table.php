<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lms_online_exam_student', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('student_id')->nullable();
            $table->integer('question_paper_id')->nullable();
            $table->integer('total_right')->nullable();
            $table->integer('total_wrong')->nullable();
            $table->integer('obtain_marks')->nullable();
            $table->timestamp('start_time')->nullable(); // Use timestamp() instead of timestamps()
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lms_online_exam_student');
    }
};
