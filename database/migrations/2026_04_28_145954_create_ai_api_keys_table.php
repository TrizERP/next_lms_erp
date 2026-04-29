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
        Schema::create('ai_api_keys', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('account_email', 191)->nullable();
            $table->string('api_type', 191)->nullable(); // openai, google, etc.
            $table->mediumText('api_key'); // no default
            $table->string('api_limit', 191)->nullable(); // usage limit

            $table->integer('status')->default(1); // 1=active, 2=inactive

            $table->unsignedBigInteger('sub_institute_id')->nullable();

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
        Schema::dropIfExists('ai_api_keys');
    }
};
