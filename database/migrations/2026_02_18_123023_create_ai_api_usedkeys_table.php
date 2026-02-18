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
        Schema::create('ai_api_usedkeys', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('api_id')->nullable();
            $table->foreign('api_id')->references('id')->on('ai_api_keys')
                  ->onDelete('no action')->onUpdate('no action');
            
            $table->date('date')->nullable();
            $table->integer('daily_limit_count')->nullable()->comment('openai, google, etc.');
            $table->bigInteger('sub_institute_id')->index()->nullable();
            $table->bigInteger('created_by')->index()->nullable();
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
        Schema::dropIfExists('ai_api_usedkeys');
    }
};
