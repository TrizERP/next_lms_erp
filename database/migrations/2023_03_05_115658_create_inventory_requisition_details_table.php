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
        Schema::create('inventory_requisition_details', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('syear', 50)->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->integer('marking_period_id')->nullable();
            $table->string('requisition_no', 50)->nullable();
            $table->integer('requisition_by')->nullable();
            $table->dateTime('requisition_date')->nullable();
            $table->integer('item_id')->nullable();
            $table->integer('item_qty')->nullable();
            $table->string('item_unit', 50)->nullable();
            $table->integer('approved_qty')->nullable();
            $table->integer('item_qty_in_stock')->nullable();
            $table->dateTime('expected_delivery_time')->nullable();
            $table->integer('requisition_status')->nullable();
            $table->string('remarks', 50)->nullable();
            $table->integer('requisition_approved_by')->nullable();
            $table->string('requisition_approved_remarks', 50)->nullable();
            $table->dateTime('requisition_approved_date')->nullable();
            $table->integer('department_id')->nullable();
            $table->integer('user_group_id')->nullable();
            $table->integer('created_by')->nullable();
            $table->string('created_ip_address', 50)->nullable();
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
        Schema::dropIfExists('inventory_requisition_details');
    }
};
