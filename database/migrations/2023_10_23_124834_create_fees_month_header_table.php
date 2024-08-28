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
        Schema::create('fees_month_header', function (Blueprint $table) {
            $table->id();
            $table->string('month_id',20)->nullable()->index();
            $table->string('header', 50)->nullable()->index();
            $table->bigInteger('sub_institute_id')->index();
            $table->integer('created_by')->nullable()->index();  
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
        Schema::dropIfExists('fees_month_header');
    }
};
