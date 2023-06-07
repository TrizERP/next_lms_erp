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
        Schema::create('employee_monthly_salary_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->integer('sub_institute_id');
            $table->integer('total_day');
            $table->string('month');
            $table->integer('year');
            $table->longText('employee_salary_data');
            $table->integer('total_deduction')->nullable();
            $table->integer('total_payment')->nullable();
            $table->integer('received_by')->nullable();
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
        Schema::dropIfExists('employee_monthly_salary_data');
    }
};
