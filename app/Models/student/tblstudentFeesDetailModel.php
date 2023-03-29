<?php

namespace App\Models\student;

use Illuminate\Database\Eloquent\Model;

class tblstudentFeesDetailModel extends Model
{
    protected $table = 'tblstudent_bank_detail';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'student_id',
        'sub_institute_id',
        'ac_holder_name',
        'ac_number',
        'bank_name',
        'bank_branch',
        'ifsc_code',
        'is_registered',
        'ac_type',
        'UMRN',
        'registration_date',
        'created_on',
        'created_by'
    ];
}
