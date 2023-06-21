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
        Schema::create('app_language', function (Blueprint $table) {
            $table->bigIncrements('id')->unsigned();
            $table->integer('menu_id');
            $table->string('menu',50)->nullable();
            $table->string('string',50)->nullable();
            $table->text('value')->nullable();
            $table->integer('status')->nullable();
            $table->integer('sub_institute_id')->nullable(); 
            $table->integer('created_by')->nullable();                            
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
        Schema::dropIfExists('app_language');
    }
};
