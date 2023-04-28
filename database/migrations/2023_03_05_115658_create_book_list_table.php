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
        Schema::create('book_list', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('syear')->default(0);
            $table->integer('standard_id')->default(0);
            $table->integer('subject_id')->default(0);
            $table->integer('chapter_id')->default(0);
            $table->integer('topic_id')->default(0);
            $table->string('title', 250);
            $table->text('message');
            $table->string('file_name');
            $table->string('link', 250)->nullable();
            $table->date('date_');
            $table->integer('sub_institute_id');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('book_list');
    }
};
