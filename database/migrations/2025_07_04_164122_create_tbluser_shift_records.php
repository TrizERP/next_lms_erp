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
        Schema::create('tbluser_shift_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('department_id')->index()->nullable();
            $table->bigInteger('employee_id')->index()->nullable();
            $table->text('start_data')->nullable();
            $table->text('end_data')->nullable();
            $table->bigInteger('sub_institute_id')->index();
            $table->bigInteger('created_by')->index()->nullable();
            $table->bigInteger('updated_by')->index()->nullable();
            $table->bigInteger('deleted_by')->index()->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tbluser_shift_records');
    }
};
