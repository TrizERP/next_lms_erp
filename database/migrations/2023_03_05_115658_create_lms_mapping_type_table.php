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
        Schema::create('lms_mapping_type', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->longText('name');
            $table->integer('parent_id');
            $table->integer('globally')->nullable();
            $table->bigInteger('chapter_id')->nullable();
            $table->bigInteger('topic_id')->nullable();
            $table->integer('status');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lms_mapping_type');
    }
};
