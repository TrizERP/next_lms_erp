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
        Schema::create('o_net_occupation_detail_lists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('o_net_occupation_detail_id');
            $table->string('title');
            $table->text('description');
            $table->string('resource_title');
            $table->text('href');
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
        Schema::dropIfExists('o_net_occupation_detail_lists');
    }
};
