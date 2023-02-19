<?php

namespace App\Models\student;

use Illuminate\Database\Eloquent\Model;

class tblstudentTcModel extends Model
{
    protected $table = 'tblstudent_tc_details';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'sub_institute_id',
        'syear',
        'student_id',
        'candidate_belongs_to',
        'date_of_first_admission',
        'class_in_which_pupil_last_studied',
        'last_school_board',
        'whether_failed',
        'subjects_studied',
        'whether_qualified',
        'if_to_which_class',
        'month_up_paid_school_dues',
        'total_working_days',
        'total_working_days_present',
        'games_played',
        'general_conduct',
        'date_of_application_for_certificate',
        'date_of_issue_of_certificate',
        'reason_leaving_school',
        'proof_for_dob',
        'whether_school_is_under_goverment',
        'date_on_which_pupil_name_was_struck',
        'any_fees_concession',
        'whether_ncc_cadet',
        'any_other_remarks',
        'affiliation_no',
        'school_code',
        'created_by',
        'created_on',
        'created_ip'
    ];
}
