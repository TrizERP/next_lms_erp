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
        Schema::create('hostel_room_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('sub_institute_id')->nullable();
            $table->unsignedInteger('floor_id')->index('hostel_room_master_floor_id_foreign');
            $table->string('room_name', 255);
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
        Schema::dropIfExists('hostel_room_master');
    }
};
