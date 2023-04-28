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
        Schema::create('transport_map_student', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->bigInteger('syear');
            $table->bigInteger('student_id')->index('FK_transport_map_student_tblstudent');
            $table->bigInteger('from_shift_id');
            $table->bigInteger('from_bus_id');
            $table->bigInteger('from_stop');
            $table->bigInteger('to_shift_id');
            $table->bigInteger('to_bus_id');
            $table->bigInteger('to_stop');
            $table->bigInteger('sub_institute_id');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable();

            $table->index(['from_stop', 'to_stop'], 'FK_transport_map_student_transport_stop');
            $table->index(['from_bus_id', 'to_bus_id'], 'FK_transport_map_student_transport_vehicle');
            $table->index(['from_shift_id', 'to_shift_id'], 'FK_transport_map_student_transport_school_shift');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transport_map_student');
    }
};
