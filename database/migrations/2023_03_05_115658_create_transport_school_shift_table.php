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
        Schema::create('transport_school_shift', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('shift_title', 50);
            $table->bigInteger('sub_institute_id')->index('FK_transport_school_shift_school_setup');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transport_school_shift');
    }
};
