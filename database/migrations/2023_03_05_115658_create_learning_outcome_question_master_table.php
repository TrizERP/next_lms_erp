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
        Schema::create('learning_outcome_question_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('ID');
            $table->date('DATE')->nullable()->index('DATE');
            $table->string('MEDIUM', 50)->nullable()->index('MEDIUM');
            $table->string('STANDARD', 50)->nullable()->index('STANDARD');
            $table->string('SUBJECT', 50)->nullable()->index('SUBJECT');
            $table->string('EXAM_TYPE', 50)->nullable();
            $table->string('EXAM_CODE', 50)->nullable();
            $table->string('QUESTION_TITLE', 50)->nullable();
            $table->decimal('QUESTION_OUT_OF', 10, 0)->nullable();
            $table->integer('INDICATORE_ID')->nullable()->index('INDICATORE_ID');
            $table->string('SYEAR', 50)->nullable()->index('SYEAR');

            $table->index(['ID'], 'ID');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('learning_outcome_question_master');
    }
};
