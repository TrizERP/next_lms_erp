<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHrmsInOutTimesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hrms_in_out_times', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->unsignedBigInteger('user_id');
            $table->time('in_time')->nullable();
            $table->time('out_time')->nullable();
            $table->unsignedBigInteger('client_id');
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
        Schema::dropIfExists('hrms_in_out_times');
    }
}
