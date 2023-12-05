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
        Schema::create('o_net_data_tables', function (Blueprint $table) {
            $table->id();
            $table->integer('importance')->nullable();
            $table->integer('level')->nullable();
            $table->integer('job_zone')->nullable();
            $table->string('code')->nullable();
            $table->text('occupation')->nullable();
            $table->integer('o_net_sub_category_id')->nullable();
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
        Schema::dropIfExists('o_net_data_tables');
    }
};
