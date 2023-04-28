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
        Schema::create('inventory_allocation_details', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('ID');
            $table->string('SYEAR', 10)->nullable();
            $table->string('SUB_INSTITUTE_ID', 10)->nullable();
            $table->integer('REQUISITION_DETAILS_ID')->nullable();
            $table->integer('REQUISITION_ID')->nullable();
            $table->string('LOCATION_OF_MATERIAL', 50)->nullable();
            $table->string('PERSON_RESPONSIBLE', 50)->nullable();
            $table->integer('ITEM_ID')->nullable()->index('ITEM_ID');
            $table->string('CREATED_BY', 50)->nullable();
            $table->timestamp('CREATED_ON')->nullable()->useCurrent();
            $table->string('CREATED_IP_ADDRESS', 50)->nullable();

            $table->index(['ID'], 'ID');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inventory_allocation_details');
    }
};
