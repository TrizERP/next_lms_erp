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
        Schema::create('learning_outcome_student_marks', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('ID');
            $table->integer('SUB_INSTITUTE_ID')->nullable()->index('SUB_INSTITUTE_ID');
            $table->integer('STUDENT_ID')->nullable()->index('STUDENT_ID');
            $table->string('MEDIUM', 50)->nullable()->index('MEDIUM');
            $table->string('STANDARD', 50)->nullable()->index('STANDARD');
            $table->string('SUBJECT', 50)->nullable()->index('SUBJECT');
            $table->integer('QUESTION_ID')->nullable()->index('QUESTION_ID');
            $table->date('DATE')->nullable()->index('DATE');
            $table->integer('CREATED_BY')->nullable();
            $table->timestamp('CREATED_ON')->nullable()->useCurrent();
            $table->integer('CREATED_BY_USER_GROUP_ID')->nullable();
            $table->integer('SYEAR')->nullable();
            $table->decimal('MARKS', 10)->nullable();
            $table->string('IP_ADDRESS', 50)->nullable();

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
        Schema::dropIfExists('learning_outcome_student_marks');
    }
};
