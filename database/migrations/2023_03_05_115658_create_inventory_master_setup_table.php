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
        Schema::create('inventory_master_setup', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('ID');
            $table->string('SYEAR', 255);
            $table->integer('SUB_INSTITUTE_ID')->nullable();
            $table->string('GST_REGISTRATION_NO', 255);
            $table->string('GST_REGISTRATION_DATE', 255);
            $table->string('CST_REGISTRATION_NO', 255);
            $table->string('CST_REGISTRATION_DATE', 255);
            $table->string('LOGO', 255);
            $table->string('PO_NO_PREFIX', 255);
            $table->string('ITEM_SETTING_FOR_REQUISITION', 100)->nullable()->default('items_without_chain');
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
        Schema::dropIfExists('inventory_master_setup');
    }
};
