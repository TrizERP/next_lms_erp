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
        Schema::create('library_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('book_id');
            $table->foreign('book_id')->references('id')->on('library_books')->onDelete('cascade')->onUpdate('cascade');
            $table->string('call_number', 50)->nullable();
            $table->string('item_code', 50)->nullable();
            $table->date('received_date')->nullable();
            $table->string('order_no', 50)->nullable();
            $table->date('order_date')->nullable();
            $table->string('item_status', 10)->nullable();
            $table->string('remarks', 100)->nullable();
            $table->string('invoice')->nullable();
            $table->dateTime('input_date')->default('0000-00-00 00:00:00');
            $table->dateTime('last_update')->default('0000-00-00 00:00:00');
            $table->softDeletes();
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
        Schema::dropIfExists('library_items');
    }
};
