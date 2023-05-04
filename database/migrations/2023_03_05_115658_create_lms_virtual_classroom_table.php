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
        Schema::create('lms_virtual_classroom', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('grade_id');
            $table->integer('standard_id');
            $table->integer('subject_id');
            $table->integer('chapter_id');
            $table->integer('topic_id');
            $table->string('room_name', 250)->nullable();
            $table->text('description')->nullable();
            $table->date('event_date')->nullable();
            $table->time('from_time')->nullable();
            $table->time('to_time')->nullable();
            $table->string('recurring', 10)->nullable();
            $table->text('url')->nullable();
            $table->string('password', 100)->nullable();
            $table->string('status', 10)->nullable();
            $table->string('notification', 10)->nullable();
            $table->string('sort_order', 10)->nullable();
            $table->integer('syear')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->integer('created_by')->nullable();
            $table->string('created_ip', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lms_virtual_classroom');
    }
};
