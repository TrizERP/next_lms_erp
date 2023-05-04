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
        Schema::create('inventory_item_quotation_details', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->decimal('syear', 4, 0)->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->integer('item_id')->nullable();
            $table->integer('vendor_id')->nullable();
            $table->string('transportation_charge', 50)->nullable();
            $table->string('installation_charge', 50)->nullable();
            $table->integer('qty')->nullable();
            $table->decimal('price', 10)->nullable();
            $table->decimal('total', 10)->nullable();
            $table->string('unit', 50)->nullable();
            $table->string('tax', 50)->nullable();
            $table->string('remarks', 100)->nullable();
            $table->integer('approved_status')->nullable();
            $table->integer('approved_by')->nullable();
            $table->date('approved_date')->nullable();
            $table->string('approved_remarks', 100)->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->string('created_ip_address', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inventory_item_quotation_details');
    }
};
