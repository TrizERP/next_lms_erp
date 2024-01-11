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
        Schema::create('hrms_leave_allocation', function (Blueprint $table) {
            $table->id();
            $table->text('employee_id', 15)->nullable();
            $table->text('leave_type_id', 15)->nullable();
            $table->text('year', 15)->nullable();
            $table->text('value', 20)->nullable();
            $table->text('sub_institute_id')->nullable();
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
        Schema::dropIfExists('hrms_leave_allocation');
    }
};
