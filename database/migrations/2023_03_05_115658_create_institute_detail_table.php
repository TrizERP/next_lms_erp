<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('institute_detail', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('sub_institute_id')->nullable();
            $table->string('principal_name', 250)->nullable();
            $table->string('principal_mobile', 50)->nullable();
            $table->string('manager_name', 250)->nullable();
            $table->string('manager_mobile', 50)->nullable();
            $table->string('college_location_condition', 50)->nullable();
            $table->string('total_seats_for_exam', 50)->nullable();
            $table->string('total_furniture', 50)->nullable();
            $table->string('electricity_condition', 50)->nullable();
            $table->string('generator_inverter_condition', 50)->nullable();
            $table->string('drinking_water_condition', 50)->nullable();
            $table->string('toilet_condition', 50)->nullable();
            $table->string('fire_fighting_condition', 50)->nullable();
            $table->string('parking_condition', 50)->nullable();
            $table->string('school_to_road_condition_distance', 50)->nullable();
            $table->string('cctv_condition', 50)->nullable();
            $table->string('total_rooms_with_size', 50)->nullable();
            $table->string('storeroom_condition', 50)->nullable();
            $table->string('college_boundary_gate_condition', 50)->nullable();
            $table->string('principal_house_inside_college', 50)->nullable();
            $table->string('declared_dibar', 50)->nullable();
            $table->string('data_available_AISHE', 50)->nullable();
            $table->string('trustee_conflict', 50)->nullable();
            $table->string('affilitated_college_condition', 50)->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('institute_detail');
    }
};
