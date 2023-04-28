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
        Schema::create('lms_doubt', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('subject_id')->nullable();
            $table->integer('chapter_id')->nullable();
            $table->integer('topic_id')->nullable();
            $table->string('title', 250)->nullable();
            $table->longText('description')->nullable();
            $table->longText('file_name')->nullable();
            $table->string('visibility', 50)->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->integer('syear')->nullable();
            $table->integer('user_id')->nullable();
            $table->integer('user_profile_id')->nullable();
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
        Schema::dropIfExists('lms_doubt');
    }
};
