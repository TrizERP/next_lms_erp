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
        Schema::create('sqaa_marks', function (Blueprint $table) {
            $table->bigIncrements('id')->unsigned();
            $table->bigInteger('menu_id')->index();
            $table->integer('mark')->index();     
            $table->bigInteger('created_by')->nullable();      
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
        Schema::dropIfExists('sqaa_marks');
    }
};
