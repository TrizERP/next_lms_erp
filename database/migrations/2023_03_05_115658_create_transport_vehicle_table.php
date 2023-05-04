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
        Schema::create('transport_vehicle', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('title', 50);
            $table->string('vehicle_number', 50);
            $table->string('vehicle_type', 50);
            $table->integer('sitting_capacity');
            $table->bigInteger('school_shift')->index('FK_transport_vehicle_transport_school_shift');
            $table->string('vehicle_identity_number', 50);
            $table->bigInteger('driver')->index('FK_transport_vehicle_transport_driver_detail');
            $table->bigInteger('conductor')->nullable()->index('FK_transport_vehicle_transport_driver_detail_2');
            $table->bigInteger('sub_institute_id')->index('FK_transport_vehicle_school_setup');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transport_vehicle');
    }
};
