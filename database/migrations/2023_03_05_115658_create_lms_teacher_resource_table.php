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
        Schema::create('lms_teacher_resource', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('standard_id')->nullable();
            $table->integer('subject_id')->nullable();
            $table->integer('chapter_id')->nullable();
            $table->integer('topic_id')->nullable();
            $table->integer('syear')->nullable();
            $table->string('title', 250)->nullable();
            $table->longText('description')->nullable();
            $table->mediumText('activity')->nullable();
            $table->string('file_folder', 500)->nullable();
            $table->string('file_name', 250)->nullable();
            $table->string('file_type', 250)->nullable();
            $table->string('file_size', 250)->nullable();
            $table->string('status', 5)->nullable();
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
        Schema::dropIfExists('lms_teacher_resource');
    }
};
