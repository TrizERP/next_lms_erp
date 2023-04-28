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
        Schema::create('question_paper', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('grade_id')->nullable();
            $table->integer('standard_id')->nullable();
            $table->integer('subject_id')->nullable();
            $table->string('paper_name', 250)->nullable();
            $table->string('paper_desc', 250)->nullable();
            $table->dateTime('open_date')->nullable();
            $table->dateTime('close_date')->nullable();
            $table->integer('timelimit_enable')->nullable();
            $table->integer('time_allowed')->nullable();
            $table->integer('total_marks')->nullable();
            $table->integer('total_ques')->nullable();
            $table->string('question_ids', 250)->nullable();
            $table->integer('shuffle_question')->nullable();
            $table->string('attempt_allowed', 250)->nullable();
            $table->integer('show_feedback')->nullable();
            $table->integer('show_hide')->nullable();
            $table->integer('result_show_ans')->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->integer('created_by')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->integer('syear')->nullable();
            $table->string('exam_type', 250)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('question_paper');
    }
};
