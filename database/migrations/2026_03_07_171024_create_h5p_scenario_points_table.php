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
        Schema::create('h5p_scenario_points', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('scenario_id');

            $table->string('title');
            $table->text('description')->nullable();

            $table->integer('position_x');
            $table->integer('position_y');

            $table->bigInteger('sub_institute_id')->nullable();
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->bigInteger('deleted_by')->nullable();

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
        Schema::dropIfExists('h5p_scenario_points');
    }
};
