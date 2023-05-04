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
        Schema::create('topic_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->bigInteger('sub_institute_id')->nullable()->index('FK_topic_master_school_setup');
            $table->bigInteger('chapter_id')->nullable()->index('FK_topic_master_chapter_master');
            $table->integer('main_topic_id')->nullable();
            $table->string('name', 250)->nullable();
            $table->text('description')->nullable();
            $table->integer('topic_show_hide')->nullable();
            $table->integer('topic_sort_order')->nullable();
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
        Schema::dropIfExists('topic_master');
    }
};
