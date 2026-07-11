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
        Schema::create('gamma_ppt', function (Blueprint $table) {
            $table->comment('Gamma PPT Generation Records');
            $table->bigIncrements('id');
            $table->integer('standard_id')->nullable();
            $table->integer('subject_id')->nullable();
            $table->integer('chapter_id')->nullable();
            $table->integer('topic_id')->nullable();
            $table->string('title', 250)->nullable();
            $table->longText('description')->nullable();
            $table->longText('prompt')->nullable();
            $table->string('format', 50)->nullable();
            $table->string('export_format', 10)->nullable();
            $table->string('generation_id', 250)->nullable();
            $table->string('gamma_url', 500)->nullable();
            $table->string('file_url', 500)->nullable();
            $table->string('file_type', 50)->nullable();
            $table->string('status', 50)->nullable();
            $table->integer('credits_used')->nullable();
            $table->integer('credits_remaining')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->integer('syear')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
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
        Schema::dropIfExists('gamma_ppt');
    }
};
