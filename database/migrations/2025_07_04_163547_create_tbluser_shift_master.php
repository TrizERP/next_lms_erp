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
        Schema::create('tbluser_shift_master', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('shift_name');
            $table->time('start_time')->index()->nullable();
            $table->time('end_time')->index()->nullable();
            $table->bigInteger('sub_institute_id')->index();
            $table->bigInteger('created_by')->index()->nullable();
            $table->bigInteger('updated_by')->index()->nullable();
            $table->bigInteger('deleted_by')->index()->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tbluser_shift_master');
    }
};
