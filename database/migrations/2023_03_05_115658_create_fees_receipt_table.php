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
        Schema::create('fees_receipt', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('FEES_ID', 250)->nullable();
            $table->string('OTHER_FEES_ID', 250)->nullable();
            $table->string('RECEIPT_ID_1', 250)->nullable();
            $table->string('RECEIPT_ID_2', 250)->nullable();
            $table->string('RECEIPT_ID_3', 250)->nullable();
            $table->string('RECEIPT_ID_4', 250)->nullable();
            $table->string('RECEIPT_ID_5', 250)->nullable();
            $table->string('RECEIPT_ID_6', 250)->nullable();
            $table->string('RECEIPT_ID_7', 250)->nullable();
            $table->string('RECEIPT_ID_8', 250)->nullable();
            $table->string('RECEIPT_ID_9', 250)->nullable();
            $table->string('RECEIPT_ID_10', 250)->nullable();
            $table->integer('SYEAR')->nullable();
            $table->bigInteger('SUB_INSTITUTE_ID')->nullable();
            $table->bigInteger('STANDARD')->nullable();
            $table->timestamp('CREATED_ON')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fees_receipt');
    }
};
