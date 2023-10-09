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
        Schema::create('fees_payphi', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('syear');
            $table->string('merchant_id', 255);
            $table->string('key', 255);
            $table->unsignedBigInteger('sub_institute_id');
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
        Schema::dropIfExists('fees_payphi');
    }
};
