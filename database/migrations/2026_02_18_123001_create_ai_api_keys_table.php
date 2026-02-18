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
            $table->string('account_email')->nullable();
            $table->string('api_type')->nullable()->comment('openai, google, etc.');
            $table->mediumText('api_key')->unique();
            $table->string('api_limit')->nullable()->comment('How many times can this key be used? unlimited, 1000, etc.');
            $table->integer('status')->default(1)->comment('1=active, 2=inactive');
            $table->bigInteger('sub_institute_id')->index()->nullable();
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
