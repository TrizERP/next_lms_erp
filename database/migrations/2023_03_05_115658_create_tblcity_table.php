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
        Schema::create('tblcity', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('city_name', 50)->nullable()->unique('city_name');
            $table->integer('state_id')->nullable();
            $table->string('state_name', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tblcity');
    }
};
