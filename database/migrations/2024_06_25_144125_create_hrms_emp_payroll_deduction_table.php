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
        Schema::create('hrms_emp_payroll_deduction', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('month',20)->nullable();
            $table->integer('year')->nullable();
            $table->bigInteger('employee_id')->nullable();
            $table->bigInteger('deduction_type')->nullable();
            $table->integer('deduction_amount')->nullable();
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('sub_institute_id')->nullable();
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
        Schema::dropIfExists('hrms_emp_payroll_deduction');
    }
};
