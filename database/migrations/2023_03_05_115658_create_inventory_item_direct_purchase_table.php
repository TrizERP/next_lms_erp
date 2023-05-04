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
        Schema::create('inventory_item_direct_purchase', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('sub_institute_id')->nullable();
            $table->string('syear', 100)->nullable();
            $table->integer('vendor_id')->nullable();
            $table->integer('category_id')->nullable();
            $table->integer('sub_category_id')->nullable();
            $table->integer('item_id')->nullable();
            $table->integer('item_qty')->nullable();
            $table->decimal('price', 10)->nullable();
            $table->decimal('amount', 10)->nullable();
            $table->string('challan_no', 150)->nullable();
            $table->date('challan_date')->nullable();
            $table->string('bill_no', 150)->nullable();
            $table->date('bill_date')->nullable();
            $table->string('remarks', 250)->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->string('created_ip', 250)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inventory_item_direct_purchase');
    }
};
