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
        Schema::create('inventory_item_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('syear')->default(0);
            $table->integer('sub_institute_id')->nullable();
            $table->unsignedInteger('category_id')->index('inventory_item_master_category_id_foreign');
            $table->unsignedInteger('sub_category_id')->index('inventory_item_master_sub_category_id_foreign');
            $table->unsignedInteger('item_type_id')->index('inventory_item_master_item_type_id_foreign');
            $table->string('title', 255);
            $table->string('description', 255);
            $table->integer('opening_stock')->default(0);
            $table->integer('minimum_stock')->default(0);
            $table->integer('direct_purchase_stock')->default(0);
            $table->string('item_attachment', 255);
            $table->string('item_status', 255);
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
        Schema::dropIfExists('inventory_item_master');
    }
};
