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
        Schema::create('inventory_tax_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('syear', 10)->nullable();
            $table->string('sub_institute_id', 10)->nullable();
            $table->string('title', 255)->nullable();
            $table->decimal('amount_percentage', 10)->nullable();
            $table->mediumText('description_1')->nullable();
            $table->char('status', 1)->default('Y');
            $table->integer('sort_order')->nullable();
            $table->string('created_by', 50)->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->string('created_ip_address', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inventory_tax_master');
    }
};
