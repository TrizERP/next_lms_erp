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
        Schema::create('transport_route', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->bigInteger('syear');
            $table->string('route_name', 50);
            $table->string('from_time', 50);
            $table->string('to_time', 50);
            $table->bigInteger('sub_institute_id')->index('FK_transport_route_school_setup');
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
        Schema::dropIfExists('transport_route');
    }
};
