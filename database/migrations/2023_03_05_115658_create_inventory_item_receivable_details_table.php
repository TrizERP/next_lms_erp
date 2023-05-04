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
        Schema::create('inventory_item_receivable_details', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('ID');
            $table->string('SYEAR', 10)->nullable();
            $table->string('SUB_INSTITUTE_ID', 10)->nullable();
            $table->string('PURCHASE_ORDER_NO', 100)->nullable();
            $table->integer('ITEM_ID')->nullable();
            $table->string('ITEM_CODE', 100)->nullable();
            $table->integer('ITEM_CATEGORY')->nullable();
            $table->integer('ORDER_QTY')->nullable();
            $table->integer('PREVIOUS_RECEIVED_QTY')->nullable();
            $table->integer('ACTUAL_RECEIVED_QTY')->nullable();
            $table->integer('PENDING_QTY')->nullable();
            $table->longText('REMARKS')->nullable();
            $table->string('WARRANTY_START_DATE', 100)->nullable();
            $table->string('WARRANTY_END_DATE', 100)->nullable();
            $table->string('BILL_NO', 50)->nullable();
            $table->string('BILL_DATE', 100)->nullable();
            $table->string('CHALLAN_NO', 50)->nullable();
            $table->string('CHALLAN_DATE', 100)->nullable();
            $table->integer('RECEIVED_BY')->nullable();
            $table->dateTime('RECEIVED_DATE')->nullable();
            $table->string('CREATED_BY', 50)->nullable();
            $table->timestamp('CREATED_ON')->nullable()->useCurrent();
            $table->string('CREATED_IP_ADDRESS', 50)->nullable();
            $table->string('GATEPASS_NO', 255)->nullable();
            $table->string('CHEQUE_NO', 100)->nullable();
            $table->string('BANK_NAME', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inventory_item_receivable_details');
    }
};
