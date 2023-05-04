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
        Schema::create('lms_question_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('question_type_id')->nullable();
            $table->integer('grade_id')->nullable();
            $table->integer('standard_id')->nullable();
            $table->integer('subject_id')->nullable();
            $table->integer('chapter_id')->nullable();
            $table->integer('topic_id')->nullable();
            $table->longText('question_title')->nullable();
            $table->string('description', 250)->nullable();
            $table->integer('points')->default(1);
            $table->integer('multiple_answer')->nullable();
            $table->string('concept', 250)->nullable();
            $table->string('subconcept', 250)->nullable();
            $table->string('pre_grade_topic', 250)->nullable();
            $table->string('post_grade_topic', 250)->nullable();
            $table->string('cross_curriculum_grade_topic', 250)->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->integer('status')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->longText('answer')->nullable();
            $table->longText('hint_text')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lms_question_master');
    }
};
