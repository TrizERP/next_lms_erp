<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('o_net_data_sub_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('o_net_data_category_id');
            $table->unsignedBigInteger('parent_id');
            $table->unsignedBigInteger('sub_parent_id');
            $table->string('sub_category_name');
            $table->text('description');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('o_net_data_sub_categories');
    }
};
