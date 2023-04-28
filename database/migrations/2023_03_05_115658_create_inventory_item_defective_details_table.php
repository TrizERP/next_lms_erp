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
        Schema::create('inventory_item_defective_details', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('ID');
            $table->string('SYEAR', 10)->nullable();
            $table->string('SUB_INSTITUTE_ID', 10)->nullable();
            $table->integer('CATEGORY_ID')->nullable();
            $table->integer('SUB_CATEGORY_ID')->nullable();
            $table->string('ITEM_CODE', 100)->nullable();
            $table->string('ITEM_NAME', 100)->nullable();
            $table->integer('ITEM_ID')->nullable();
            $table->date('WARRANTY_START_DATE')->nullable();
            $table->date('WARRANTY_END_DATE')->nullable();
            $table->longText('DEFECT_REMARKS')->nullable();
            $table->string('ITEM_GIVEN_TO', 255)->nullable();
            $table->date('ESTIMATED_RECEIVED_DATE')->nullable();
            $table->date('ACTUAL_RECEIVED_DATE')->nullable();
            $table->longText('REMARKS')->nullable();
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
        Schema::dropIfExists('inventory_item_defective_details');
    }
};
