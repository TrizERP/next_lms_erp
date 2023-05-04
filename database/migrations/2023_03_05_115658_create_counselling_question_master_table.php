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
        Schema::create('counselling_question_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('counselling_course_id')->default(0);
            $table->integer('question_type_id')->nullable();
            $table->string('question_title', 250)->nullable();
            $table->string('description', 250)->nullable();
            $table->integer('points')->nullable();
            $table->integer('multiple_answer')->nullable();
            $table->integer('sub_institute_id')->nullable();
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
        Schema::dropIfExists('counselling_question_master');
    }
};
