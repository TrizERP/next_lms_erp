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
        Schema::create('o_net_occupation_detail_tech_skill_summeries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('o_net_occupation_detail_list_id');
            $table->unsignedBigInteger('title_id');
            $table->string('name');
            $table->text('related');
            $table->json('example');
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
        Schema::dropIfExists('o_net_occupation_detail_tech_skill_summeries');
    }
};
