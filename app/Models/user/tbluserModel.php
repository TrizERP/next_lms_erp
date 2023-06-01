<?php

namespace App\Models\user;

use Illuminate\Database\Eloquent\Model;

class tbluserModel extends Model
{
    public $timestamps = false;

    protected $table = "tbluser";
    protected $appends = ['full_name'];

    protected $fillable = [
        'id',
        'user_name',
        'password',
        'name_suffix',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'mobile',
        'gender',
        'birthdate',
        'address',
        'city',
        'state',
        'pincode',
        'otp',
        'user_profile_id',
        'join_year',
        'image',
        'plain_password',
        'sub_institute_id',
        'client_id',
        'is_admin',
        'status',
        'last_login',
        'landmark',
        'address_2',
        'created_on',
        'expire_date',
        'total_lecture',
        'subject_ids',
        'jobtitle_id',
        'department_id',
        'jobtitle_id',
        'department_id',
        'joined_date',
        'probation_period_from',
        'probation_period_to',
        'terminated_date',
        'termination_reason',
        'notice_fromdate',
        'notice_todate',
        'noticereason',
        'openingleave',
        'relieving_date',
        'relieving_reason',
        'CL_opening_leave',
        'supervisor_opt',
        'employee_id',
        'reporting_method',
        'branch_name',
        'amount',
        'transfer_type',
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
        'monday_in_date',
        'monday_out_date',
        'tuesday_in_date',
        'tuesday_out_date',
        'wednesday_in_date',
        'wednesday_out_date',
        'thursday_in_date',
        'thursday_out_date',
        'friday_in_date',
        'friday_out_date',
        'saturday_in_date',
        'saturday_out_date',
        'sunday_in_date',
        'sunday_out_date',
        'bank_name',
        'account_no',
        'ifsc_code'
    ];

    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }
}
