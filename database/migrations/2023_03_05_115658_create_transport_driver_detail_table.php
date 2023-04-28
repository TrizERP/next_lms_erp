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
        Schema::create('transport_driver_detail', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('mobile', 15);
            $table->string('icard_icon', 100)->nullable();
            $table->string('type', 15);
            $table->bigInteger('sub_institute_id')->index('FK_transport_driver_detail_school_setup');
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
        Schema::dropIfExists('transport_driver_detail');
    }
};
