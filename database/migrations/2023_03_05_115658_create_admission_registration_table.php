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
        Schema::create('admission_registration', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->bigInteger('enquiry_id')->nullable();
            $table->integer('enquiry_no')->nullable();
            $table->string('place_of_birth', 50)->nullable();
            $table->integer('enrollment_no')->nullable();
            $table->integer('amount')->nullable();
            $table->string('payment_mode', 50)->nullable();
            $table->string('bank_name', 50)->nullable();
            $table->string('bank_branch', 50)->nullable();
            $table->integer('cheque_no')->nullable();
            $table->date('cheque_date')->nullable();
            $table->string('blood_group', 50)->nullable();
            $table->string('aadhar_number', 50)->nullable();
            $table->integer('register_number')->nullable();
            $table->string('mother_name', 50)->nullable();
            $table->integer('mother_mobile_number')->nullable();
            $table->date('admission_date')->nullable();
            $table->string('admission_division', 50)->nullable();
            $table->string('remarks', 50)->nullable();
            $table->date('followup_date')->nullable();
            $table->string('status', 50)->nullable();
            $table->bigInteger('student_quota')->nullable();
            $table->string('admission_status', 50)->nullable();
            $table->date('date_of_payment')->nullable();
            $table->integer('sub_institute_id')->default(0);
            $table->integer('created_by')->default(0);
            $table->timestamp('created_on')->useCurrent();
            $table->string('pri_div')->nullable();
            $table->string('cast')->nullable();
            $table->string('religion')->nullable();
            $table->string('house_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('admission_registration');
    }
};
