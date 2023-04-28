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
        Schema::create('counselling_answer_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('question_id')->nullable();
            $table->string('answer', 250)->nullable();
            $table->integer('correct_answer')->nullable();
            $table->integer('sub_institute_id')->nullable();
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
        Schema::dropIfExists('counselling_answer_master');
    }
};
