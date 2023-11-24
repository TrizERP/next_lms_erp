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
        Schema::create('o_net_occupation_detail_work_style_summeries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('o_net_occupation_detail_list_id');
            $table->string('element_id');
            $table->string('name');
            $table->text('related');
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
        Schema::dropIfExists('o_net_occupation_detail_work_style_summeries');
    }
};
