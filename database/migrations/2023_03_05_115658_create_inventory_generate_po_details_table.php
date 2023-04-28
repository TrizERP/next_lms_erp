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
        Schema::create('inventory_generate_po_details', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->decimal('syear', 4, 0)->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->string('po_number', 50)->nullable();
            $table->integer('item_id')->nullable();
            $table->integer('vendor_id')->nullable();
            $table->decimal('price', 10)->nullable();
            $table->integer('qty')->nullable();
            $table->decimal('amount', 10)->nullable();
            $table->decimal('dis_per', 10)->nullable();
            $table->decimal('dis_amount_value', 10)->nullable();
            $table->decimal('after_dis_amount', 10)->nullable();
            $table->decimal('tax_per', 10)->nullable();
            $table->decimal('tax_amount_value', 10)->nullable();
            $table->decimal('after_tax_amount', 10)->nullable();
            $table->decimal('amount_per_item', 10)->nullable();
            $table->string('transportation_charge', 50)->nullable();
            $table->string('installation_charge', 50)->nullable();
            $table->mediumText('payment_terms')->nullable();
            $table->mediumText('remarks')->nullable();
            $table->dateTime('delivery_time')->nullable();
            $table->integer('po_approval_status')->nullable();
            $table->integer('po_approved_by')->nullable();
            $table->mediumText('po_approval_remark')->nullable();
            $table->dateTime('po_approved_date')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->string('created_ip_address', 50)->nullable();
            $table->string('po_additional_charges_ids', 50)->nullable();
            $table->string('po_place_of_delivery', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inventory_generate_po_details');
    }
};
