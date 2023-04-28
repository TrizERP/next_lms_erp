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
        Schema::create('inventory_item_sub_category_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('syear', 255);
            $table->integer('sub_institute_id')->nullable();
            $table->unsignedInteger('category_id')->index('inventory_item_sub_category_master_category_id_foreign');
            $table->string('title', 255);
            $table->string('description', 255);
            $table->string('status', 255);
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
        Schema::dropIfExists('inventory_item_sub_category_master');
    }
};
