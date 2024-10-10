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
        Schema::create('admission_registration_v1', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('enquiry_id')->nullable();
            $table->string('enquiry_no',255)->nullable();
            $table->string('h_n',10)->nullable();
            $table->string('h_n_remarks',255)->nullable();
            $table->string('activity',50)->nullable();
            $table->string('p_int',10)->nullable();
            $table->date('p_int_date')->nullable();
            $table->time('p_int_time')->nullable();
            $table->string('confi',10)->nullable();
            $table->date('confi_date')->nullable();
            $table->time('confi_time')->nullable();
            $table->string('paid',10)->nullable();
            $table->string('transport_fees',10)->nullable();
            $table->bigInteger('sub_institute_id')->nullable();
            $table->bigInteger('created_by')->nullable();
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
        Schema::dropIfExists('admission_registration_v1');
    }
};
