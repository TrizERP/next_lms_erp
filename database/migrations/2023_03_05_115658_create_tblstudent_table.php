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
        Schema::create('tblstudent', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('enrollment_no', 50)->nullable();
            $table->integer('roll_no')->nullable();
            $table->integer('admission_year')->nullable();
            $table->string('first_name', 50)->nullable();
            $table->string('middle_name', 50)->nullable();
            $table->string('last_name', 50)->nullable();
            $table->string('father_name', 50)->nullable();
            $table->string('mother_name', 50)->nullable();
            $table->string('gender', 50)->nullable();
            $table->date('dob')->nullable();
            $table->string('mobile', 50)->nullable();
            $table->string('mother_mobile', 50)->nullable();
            $table->string('student_mobile', 50)->nullable();
            $table->string('email', 50)->nullable();
            $table->string('username', 50)->nullable();
            $table->string('password', 50)->nullable();
            $table->bigInteger('user_profile_id')->nullable()->index('FK_tblstudent_tbluserprofilemaster');
            $table->string('admission_id', 50)->nullable();
            $table->date('admission_date')->nullable();
            $table->string('city', 50)->nullable();
            $table->string('state', 50)->nullable();
            $table->mediumText('address')->nullable();
            $table->integer('pincode')->nullable();
            $table->string('otp', 50)->nullable();
            $table->string('image', 50)->default('');
            $table->text('file_size');
            $table->text('file_type');
            $table->bigInteger('sub_institute_id')->nullable()->index('FK_tblstudent_school_setup');
            $table->integer('status')->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->string('aadhar_document_upload')->nullable();
            $table->string('birth_certificate')->nullable();
            $table->string('student_inactive')->nullable();
            $table->string('enrollment_status')->nullable();
            $table->string('inactive_date')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('house')->nullable();
            $table->string('conduct')->nullable();
            $table->string('since_when')->nullable();
            $table->string('is_physically_handicaped')->nullable();
            $table->string('economy_backward')->nullable();
            $table->string('reserve_categorey')->nullable();
            $table->string('student_achievement')->nullable();
            $table->string('religion')->nullable();
            $table->string('cast')->nullable();
            $table->string('subcast')->nullable();
            $table->string('bloodgroup')->nullable();
            $table->string('adharnumber')->nullable();
            $table->string('anuualincome')->nullable();
            $table->string('uniqueid')->nullable();
            $table->string('studentbatch')->nullable();
            $table->string('optionalsubject')->nullable();
            $table->string('height')->nullable();
            $table->string('weight')->nullable();
            $table->string('school_code')->nullable();
            $table->string('parent_bank_details')->nullable();
            $table->string('subject_offered')->nullable();
            $table->string('percentage_scored_in_last_class')->nullable();
            $table->string('disability_if_any')->nullable();
            $table->longText('academic_achievement_s')->nullable();
            $table->longText('exceptional_achievement')->nullable();
            $table->longText('language_learned')->nullable();
            $table->string('mother_tongue')->nullable();
            $table->string('nationality')->nullable();
            $table->string('present_grade')->nullable();
            $table->string('first_language')->nullable();
            $table->string('second_language')->nullable();
            $table->string('third_language')->nullable();
            $table->string('emergency_contact_no_religion_to_student')->nullable();
            $table->string('form_no')->nullable();
            $table->string('dise_uid')->nullable();
            $table->string('previous_school_name')->nullable();
            $table->string('affiliation_no')->nullable();
            $table->string('transportation')->nullable();
            $table->string('type_of_school')->nullable();
            $table->string('medium_of_interaction')->nullable();
            $table->string('siblings_in_school')->nullable();
            $table->string('number_of_children')->nullable();
            $table->string('caste')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('account_holder')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('micr_code')->nullable();
            $table->string('admission_token_no')->nullable();
            $table->string('udise_no')->nullable();
            $table->string('admission_docket_no', 150)->nullable();
            $table->string('registration_no', 150)->nullable();
            $table->string('admission_under', 150)->nullable();
            $table->string('distance_from_school', 150)->nullable();
            $table->dateTime('updated_on')->nullable();
            $table->date('father_dob')->nullable();
            $table->date('expire_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tblstudent');
    }
};
