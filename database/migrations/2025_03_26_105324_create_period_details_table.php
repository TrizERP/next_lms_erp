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
        Schema::create('period_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->biginteger('period_id');
            $table->biginteger('standard_id');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('length',255)->nullable();
            $table->biginteger('sub_institute_id');
            $table->biginteger('created_by')->nullable();
            $table->biginteger('updated_by')->nullable();
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
        Schema::dropIfExists('period_details');
    }
};
