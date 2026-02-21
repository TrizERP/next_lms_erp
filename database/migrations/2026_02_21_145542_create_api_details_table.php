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
        Schema::create('api_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('api_type')->nullable()->index();
            $table->string('account')->nullable()->index();
            $table->mediumText('key')->nullable()->index();
            $table->integer('status')->nullable()->index();
            $table->integer('limit')->nullable()->index();
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
        Schema::dropIfExists('api_details');
    }
};
