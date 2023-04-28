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
        Schema::create('content_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('grade_id')->default(0);
            $table->integer('standard_id')->default(0);
            $table->integer('subject_id')->default(0);
            $table->integer('chapter_id')->nullable();
            $table->integer('topic_id')->nullable();
            $table->integer('sub_topic_id')->nullable();
            $table->integer('lo_master_ids')->nullable();
            $table->integer('lo_indicator_ids')->nullable();
            $table->integer('lo_category_id')->nullable();
            $table->string('title', 250)->nullable();
            $table->mediumText('description')->nullable();
            $table->mediumText('file_folder')->nullable();
            $table->mediumText('filename')->nullable();
            $table->string('file_type', 250)->nullable();
            $table->integer('file_size')->nullable();
            $table->mediumText('url')->nullable();
            $table->integer('sort_order')->nullable();
            $table->integer('show_hide')->nullable();
            $table->string('meta_tags', 250)->nullable();
            $table->string('content_category', 250)->nullable();
            $table->integer('syear')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->date('restrict_date')->nullable();
            $table->string('pre_grade_topic', 250)->nullable();
            $table->string('post_grade_topic', 250)->nullable();
            $table->longText('cross_curriculum_grade_topic')->nullable();
            $table->longText('basic_advance')->nullable();
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
        Schema::dropIfExists('content_master');
    }
};
