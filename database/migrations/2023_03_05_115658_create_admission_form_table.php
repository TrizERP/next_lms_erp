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
        Schema::create('admission_form', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('enquiry_id', 50)->nullable();
            $table->string('enquiry_no', 50)->nullable();
            $table->string('form_no', 100)->nullable();
            $table->string('status', 50)->nullable();
            $table->string('remarks', 50)->nullable();
            $table->string('stop_for_transport', 50)->nullable();
            $table->string('counciler_name', 50)->nullable();
            $table->string('last_exam_name', 50)->nullable();
            $table->string('last_exam_percentage', 50)->nullable();
            $table->date('followup_date')->nullable();
            $table->string('annual_income', 50)->nullable();
            $table->string('father_education_qualification', 50)->nullable();
            $table->string('father_occupation', 50)->nullable();
            $table->string('mother_education_qualification', 50)->nullable();
            $table->string('mother_occupation', 50)->nullable();
            $table->string('admission_standard', 50)->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->string('created_by', 50)->nullable();
            $table->string('sub_institute_id', 50)->nullable();
            $table->string('previous_division')->nullable();
            $table->string('send_sms', 50)->nullable();
            $table->string('sms_message', 250)->nullable();
            $table->string('admission_form_fee', 100)->nullable();
            $table->string('receipt_id', 100)->nullable();
            $table->longText('receipt_html')->nullable();
            $table->string('admission_docket_no', 100)->nullable();
            $table->string('registration_no', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('admission_form');
    }
};
