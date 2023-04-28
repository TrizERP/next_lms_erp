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
        Schema::create('new_admission_inquiry_registration', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('syear')->default(0);
            $table->integer('sub_institute_id')->nullable();
            $table->string('admission_std', 50)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('age', 50)->nullable();
            $table->string('mobile', 50)->nullable();
            $table->string('otp', 50)->nullable();
            $table->text('address')->nullable();
            $table->text('child_name')->nullable();
            $table->text('father_name')->nullable();
            $table->text('mail')->nullable();
            $table->text('father_adhar')->nullable();
            $table->text('mother_adhar')->nullable();
            $table->text('student_quota')->nullable();
            $table->text('sibling_details')->nullable();
            $table->text('token')->nullable();
            $table->text('admission_for_child_twins')->nullable();
            $table->text('birth_place')->nullable();
            $table->text('town')->nullable();
            $table->text('district')->nullable();
            $table->text('state')->nullable();
            $table->text('citizenship')->nullable();
            $table->text('gender')->nullable();
            $table->text('cast')->nullable();
            $table->text('sub_cast')->nullable();
            $table->text('religion')->nullable();
            $table->text('mother_tongue')->nullable();
            $table->text('language_spoken_at_home')->nullable();
            $table->text('other_language_spoken')->nullable();
            $table->text('backward_class')->nullable();
            $table->text('house_no')->nullable();
            $table->text('area')->nullable();
            $table->text('city')->nullable();
            $table->text('pin_code')->nullable();
            $table->text('blood_group')->nullable();
            $table->text('height')->nullable();
            $table->text('weight')->nullable();
            $table->text('vaccination')->nullable();
            $table->text('diabetes')->nullable();
            $table->text('blood_pressure')->nullable();
            $table->text('child_admitted')->nullable();
            $table->text('if_yes_then_reason')->nullable();
            $table->text('how_long')->nullable();
            $table->text('child_allergies')->nullable();
            $table->text('habit_of_bed_wetting')->nullable();
            $table->text('habit_of_thumb_sucking')->nullable();
            $table->text('habit_of_anti_acid_activity')->nullable();
            $table->text('habit_of_drug_allergy')->nullable();
            $table->text('child_dependent')->nullable();
            $table->text('behavioral_problem')->nullable();
            $table->text('child_taking_milk')->nullable();
            $table->text('child_taking_curd')->nullable();
            $table->text('child_taking_vegetables')->nullable();
            $table->date('father_dob')->nullable();
            $table->text('father_qualification')->nullable();
            $table->text('father_blood_group')->nullable();
            $table->text('father_occupation')->nullable();
            $table->text('father_organization_name')->nullable();
            $table->text('father_designation')->nullable();
            $table->text('father_office_address')->nullable();
            $table->text('father_email')->nullable();
            $table->text('father_income')->nullable();
            $table->text('mother_name')->nullable();
            $table->date('mother_dob')->nullable();
            $table->text('mother_qualification')->nullable();
            $table->text('mother_blood_group')->nullable();
            $table->text('mother_occupation')->nullable();
            $table->text('mother_organization_name')->nullable();
            $table->text('mother_designation')->nullable();
            $table->text('mother_office_address')->nullable();
            $table->text('mother_mobile_no')->nullable();
            $table->text('mother_email')->nullable();
            $table->text('mother_income')->nullable();
            $table->text('guardian_name')->nullable();
            $table->text('guardian_address')->nullable();
            $table->text('guardian_mobile_no')->nullable();
            $table->text('guardian_email')->nullable();
            $table->text('guardian_relation_with_child')->nullable();
            $table->text('sibling1_name')->nullable();
            $table->text('sibling3_name')->nullable();
            $table->text('sibling4_name')->nullable();
            $table->date('sibling1_dob')->nullable();
            $table->date('sibling3_dob')->nullable();
            $table->date('sibling4_dob')->nullable();
            $table->text('sibling1_education')->nullable();
            $table->text('sibling3_education')->nullable();
            $table->text('sibling4_education')->nullable();
            $table->text('sibling1_college')->nullable();
            $table->text('sibling3_college')->nullable();
            $table->text('sibling4_college')->nullable();
            $table->text('sibling2_name')->nullable();
            $table->date('sibling2_dob')->nullable();
            $table->string('sibling2_education', 250)->nullable();
            $table->string('sibling2_college', 250)->nullable();
            $table->text('birth_certificate')->nullable();
            $table->text('student_adharcard')->nullable();
            $table->text('student_cast_certificate')->nullable();
            $table->text('father_cast_certificate')->nullable();
            $table->text('student_passport_size_photo')->nullable();
            $table->text('family_photo')->nullable();
            $table->text('vaccination_record')->nullable();
            $table->text('medical_examination_report')->nullable();
            $table->text('father_adharcard')->nullable();
            $table->text('mother_adharcard')->nullable();
            $table->text('address_proof')->nullable();
            $table->text('father_signature')->nullable();
            $table->text('mother_signature')->nullable();
            $table->text('any_other_doc')->nullable();
            $table->text('other_doc')->nullable();
            $table->text('parents_declaration')->nullable();
            $table->text('declare_by_father_mother')->nullable();
            $table->text('admin_status')->nullable();
            $table->text('principal_status')->nullable();
            $table->text('account_status')->nullable();
            $table->text('eligible_status')->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->dateTime('updated_on')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('new_admission_inquiry_registration');
    }
};
