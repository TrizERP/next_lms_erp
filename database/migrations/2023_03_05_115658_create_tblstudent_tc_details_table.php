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
        Schema::create('tblstudent_tc_details', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('sub_institute_id')->nullable();
            $table->string('syear', 50)->nullable();
            $table->integer('student_id')->nullable();
            $table->string('candidate_belongs_to', 50)->nullable();
            $table->text('date_of_first_admission')->nullable();
            $table->string('class_in_which_pupil_last_studied', 100)->nullable();
            $table->string('last_school_board', 100)->nullable();
            $table->string('whether_failed', 100)->nullable();
            $table->text('subjects_studied')->nullable();
            $table->string('whether_qualified', 150)->nullable();
            $table->string('if_to_which_class', 150)->nullable();
            $table->string('month_up_paid_school_dues', 150)->nullable();
            $table->string('total_working_days', 50)->nullable();
            $table->string('total_working_days_present', 50)->nullable();
            $table->text('games_played')->nullable();
            $table->string('general_conduct', 50)->nullable();
            $table->date('date_of_application_for_certificate')->nullable();
            $table->date('date_of_issue_of_certificate')->nullable();
            $table->text('reason_leaving_school')->nullable();
            $table->text('proof_for_dob')->nullable();
            $table->text('whether_school_is_under_goverment')->nullable();
            $table->date('date_on_which_pupil_name_was_struck')->nullable();
            $table->text('any_fees_concession')->nullable();
            $table->text('whether_ncc_cadet')->nullable();
            $table->text('any_other_remarks')->nullable();
            $table->string('affiliation_no', 100)->nullable();
            $table->string('school_code', 100)->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->string('created_ip', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tblstudent_tc_details');
    }
};
