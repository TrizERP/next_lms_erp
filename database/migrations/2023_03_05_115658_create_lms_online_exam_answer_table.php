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
        Schema::create('lms_online_exam_answer', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('question_paper_id')->nullable();
            $table->integer('online_exam_id')->nullable();
            $table->integer('student_id')->nullable();
            $table->integer('question_id')->nullable();
            $table->integer('answer_id')->nullable();
            $table->longText('narrative_answer')->nullable();
            $table->string('ans_status', 50)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lms_online_exam_answer');
    }
};
