<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHrmsAttendancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hrms_attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('user_code')->nullable();
            $table->date('day');
            $table->dateTime('punchin_time')->nullable();
            $table->dateTime('punchout_time')->nullable();
            $table->boolean('in_note')->default(0);
            $table->boolean('out_note')->default(0);
            $table->time('timestamp_diff')->nullable();
            $table->boolean('status')->default(1);
            $table->string('ipaddress_in')->nullable();
            $table->string('ipaddress_out')->nullable();
            $table->unsignedBigInteger('attendance_log_id')->nullable();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('sub_institute_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hrms_attendances');
    }
}
