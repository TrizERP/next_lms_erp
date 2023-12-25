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
        Schema::create('result_activity_master', function (Blueprint $table) {
            $table->id();
            $table->text('title', 50)->nullable();
            $table->text('skill_id', 10)->nullable();
            $table->text('sort_order', 10)->nullable();
            $table->bigInteger('sub_institute_id')->nullable();
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
        Schema::dropIfExists('result_activity_master');
    }
};
