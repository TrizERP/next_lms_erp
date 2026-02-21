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
        Schema::create('ai_daily_used_api', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('api_name')->nullable()->index();
            $table->bigInteger('parent_id')->nullable()->index();
            $table->mediumText('key')->nullable();
            $table->date('date')->nullable();
            $table->integer('count')->nullable()->index();
            $table->bigInteger('sub_institute_id')->nullable()->index();
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
        Schema::dropIfExists('ai_daily_used_api');
    }
};
