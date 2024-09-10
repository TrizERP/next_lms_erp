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
        Schema::create('incoming_messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_sid');
            $table->string('account_sid');
            $table->string('type');
            $table->string('whatsapp_number');
            $table->text('message');
            $table->dateTime('message_date');
            $table->string('attachment')->nullable();
            $table->boolean('is_seen')->default(0);
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
        Schema::dropIfExists('incoming_messages');
    }
};
