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
        Schema::create('hrms_salary_certificate', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('departement_id');
            $table->unsignedBigInteger('employee_id');
            $table->integer('year');
            $table->string('month');
            $table->string('payroll_type_id');
            $table->string('reason', 255);
            $table->unsignedBigInteger('sub_institute_id');
            $table->string('pdf_file_name');
            $table->text('pdf_html');
            $table->unsignedBigInteger('created_by');
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
        Schema::dropIfExists('hrms_salary_certificate');
    }
};
