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
        Schema::create('fees_circular_log', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('ID');
            $table->integer('STUDENT_ID')->nullable();
            $table->string('MONTH', 50)->nullable();
            $table->integer('RECEIPT_BOOK_ID')->nullable();
            $table->integer('SUB_INSTITUTE_ID')->nullable();
            $table->integer('SYEAR')->nullable();
            $table->integer('AMOUNT')->nullable();
            $table->longText('FEES_CIRCULAR_HTML')->nullable();
            $table->integer('CREATED_BY')->nullable();
            $table->timestamp('CREATED_ON')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fees_circular_log');
    }
};
