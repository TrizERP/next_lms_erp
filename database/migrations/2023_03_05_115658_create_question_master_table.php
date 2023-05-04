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
        Schema::create('question_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('question_type_id')->nullable();
            $table->integer('grade_id')->nullable();
            $table->integer('standard_id')->nullable();
            $table->integer('subject_id')->nullable();
            $table->integer('chapter_id')->nullable();
            $table->string('question_title', 250)->nullable();
            $table->integer('points')->nullable();
            $table->integer('multiple_answer')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->string('lo_master_ids', 250)->nullable();
            $table->string('lo_indicator_ids', 250)->nullable();
            $table->integer('lo_category_id')->nullable();
            $table->integer('question_level_id')->nullable();
            $table->integer('question_category_id')->nullable();
            $table->integer('status')->nullable();
            $table->integer('created_by')->nullable();
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
        Schema::dropIfExists('question_master');
    }
};
