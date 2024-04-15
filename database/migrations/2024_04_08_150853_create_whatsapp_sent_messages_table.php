<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('whatsapp_sent_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id');
            $table->integer('syear');
            $table->unsignedBigInteger('standard_id');
            $table->unsignedBigInteger('division_id');
            $table->json('student_id');
            $table->unsignedBigInteger('created_by');
            $table->string('created_by_name');
            $table->text('message')->nullable();
            $table->string('attachment')->nullable();
            $table->date('sent_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('whatsapp_sent_messages');
    }
};
