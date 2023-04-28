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
        Schema::create('lms_flashcard', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('standard_id')->nullable();
            $table->integer('subject_id')->nullable();
            $table->integer('chapter_id')->nullable();
            $table->integer('topic_id')->nullable();
            $table->integer('content_id')->nullable();
            $table->string('title', 250)->nullable();
            $table->longText('front_text')->nullable();
            $table->longText('back_text')->nullable();
            $table->string('status', 5)->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->integer('syear')->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->integer('created_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lms_flashcard');
    }
};
