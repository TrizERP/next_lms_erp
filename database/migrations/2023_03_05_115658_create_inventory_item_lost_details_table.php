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
        Schema::create('inventory_item_lost_details', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('ID');
            $table->string('SYEAR', 10)->nullable();
            $table->integer('SUB_INSTITUTE_ID')->nullable();
            $table->integer('ITEM_ID')->nullable();
            $table->integer('REQUISITION_BY')->nullable();
            $table->date('LOST_DATE')->nullable();
            $table->mediumText('REMARKS')->nullable();
            $table->string('CREATED_BY', 50)->nullable();
            $table->timestamp('CREATED_ON')->nullable()->useCurrent();
            $table->string('CREATED_IP_ADDRESS', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inventory_item_lost_details');
    }
};
