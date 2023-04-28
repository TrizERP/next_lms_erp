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
        Schema::create('transport_route_stop', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->bigInteger('syear');
            $table->bigInteger('route_id')->index('FK_transport_route_stop_transport_route');
            $table->bigInteger('stop_id')->index('FK_transport_route_stop_transport_stop');
            $table->bigInteger('sub_institute_id')->index('FK_transport_route_stop_school_setup');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable();
            $table->string('pickuptime', 50)->nullable();
            $table->string('droptime', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transport_route_stop');
    }
};
