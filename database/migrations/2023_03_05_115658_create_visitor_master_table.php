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
        Schema::create('visitor_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('appointment_type', 50);
            $table->integer('visitor_type');
            $table->string('name', 255);
            $table->string('contact', 255);
            $table->string('email', 255);
            $table->string('coming_from', 255);
            $table->integer('to_meet')->nullable();
            $table->string('relation', 255);
            $table->string('purpose', 255);
            $table->string('visitor_idcard', 255);
            $table->string('photo', 255);
            $table->text('file_size');
            $table->text('file_type');
            $table->date('meet_date');
            $table->time('in_time');
            $table->time('out_time');
            $table->integer('sub_institute_id')->nullable();
            $table->string('exit_msg_sent', 5)->nullable();
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
        Schema::dropIfExists('visitor_master');
    }
};
