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
        Schema::create('admission_age_validation', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('standard_id')->nullable();
            $table->date('date')->nullable();
            $table->bigInteger('sub_institute_id')->nullable();
            $table->integer('syear')->nullable();
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
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
        Schema::dropIfExists('admission_age_validation');
    }
};
