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
        Schema::create('sqaa_documant_master', function (Blueprint $table) {
            $table->bigIncrements('id')->unsigned();
            $table->bigInteger('menu_id')->index();            
            $table->string('title',255)->index()->nullable();
            $table->bigInteger('sub_institute_id')->index();
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
        Schema::dropIfExists('sqaa_documant_master');
    }
};
