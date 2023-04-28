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
        Schema::create('admission_enquiry', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('enquiry_no')->nullable();
            $table->string('first_name', 50)->nullable();
            $table->string('middle_name', 50)->nullable();
            $table->string('last_name', 50)->nullable();
            $table->string('gender', 50)->nullable();
            $table->integer('mobile')->nullable();
            $table->string('email', 50)->nullable();
            $table->string('address', 100)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->integer('age')->nullable();
            $table->integer('syear')->nullable();
            $table->string('previous_school_name', 50)->nullable();
            $table->string('previous_standard', 50)->nullable();
            $table->integer('admission_standard')->nullable();
            $table->string('remarks', 250)->nullable();
            $table->date('followup_date')->nullable();
            $table->string('source_of_enquiry', 50)->nullable();
            $table->string('category', 50)->nullable();
            $table->string('send_sms', 50)->nullable();
            $table->string('sms_message', 250)->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->integer('created_by')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->string('previous_division')->nullable();
            $table->integer('mobile2')->nullable();
            $table->string('test')->nullable();
            $table->string('father_name')->nullable();
            $table->string('mother_tongue')->nullable();
            $table->string('religion')->nullable();
            $table->string('nationality')->nullable();
            $table->string('whether_belongs_to')->nullable();
            $table->string('percentage')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('father_qualification')->nullable();
            $table->string('father_occupation')->nullable();
            $table->string('mother_qualification')->nullable();
            $table->string('mother_occupation')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('counciler_name')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->integer('mobile_number_father')->nullable();
            $table->integer('mobile_number_mother')->nullable();
            $table->string('guardian_relation')->nullable();
            $table->string('annual_income')->nullable();
            $table->string('house_no')->nullable();
            $table->string('street_name')->nullable();
            $table->string('building_name')->nullable();
            $table->string('district_name')->nullable();
            $table->integer('pin_code')->nullable();
            $table->string('state')->nullable();
            $table->string('aadharcard_number')->nullable();
            $table->string('building_name_appratment_name_society_name')->nullable();
            $table->integer('admission_fees')->nullable();
            $table->bigInteger('receipt_id')->nullable();
            $table->string('receipt_html')->nullable();
            $table->integer('fees_amount', 50)->nullable();
            $table->string('fees_remark')->nullable();
            $table->longText('fees_circular_html')->nullable();
            $table->string('fees_circular_form_no', 150)->nullable();
            $table->string('interaction_date')->nullable();
            $table->string('interaction_remarks')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('admission_enquiry');
    }
};
