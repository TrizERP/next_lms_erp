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
        Schema::create('sqaa_master', function (Blueprint $table) {
            $table->bigIncrements('id')->unsigned();
            $table->string('title',255)->index()->nullable();
            $table->string('description')->index()->nullable();
            $table->bigInteger('parent_id')->index();
            $table->integer('level')->index();
            $table->integer('status')->index();
            $table->integer('sort_order')->index();
            $table->bigInteger('sub_institute_id')->index();
            $table->bigInteger('created_by')->nullable();            
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
        Schema::dropIfExists('sqaa_master');
    }
};
