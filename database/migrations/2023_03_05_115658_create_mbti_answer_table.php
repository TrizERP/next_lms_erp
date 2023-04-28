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
        Schema::create('mbti_answer', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('ans_key', 50)->nullable();
            $table->longText('answer_html')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mbti_answer');
    }
};
