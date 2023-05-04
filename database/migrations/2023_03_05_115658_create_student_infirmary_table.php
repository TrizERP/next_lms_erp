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
        Schema::create('student_infirmary', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('student_id')->nullable();
            $table->decimal('syear', 4, 0)->nullable();
            $table->integer('marking_period_id')->nullable();
            $table->string('doctor_name', 100)->nullable();
            $table->string('doctor_contact', 11)->nullable();
            $table->string('medical_case_no', 50)->nullable();
            $table->date('date')->nullable();
            $table->string('complaint', 50)->nullable();
            $table->string('symptoms', 50)->nullable();
            $table->string('disease', 50)->nullable();
            $table->string('treatments', 100)->nullable();
            $table->date('medical_close_date')->nullable();
            $table->string('health_center', 50)->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->integer('created_by')->nullable();
            $table->integer('sub_institute_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('student_infirmary');
    }
};
