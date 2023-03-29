<?php

namespace App\Models\student;

use Illuminate\Database\Eloquent\Model;

class tblstudentEnrollmentModel extends Model
{
    protected $table = "tblstudent_enrollment";

    public $timestamps = false;

    protected $fillable = [
        'id',
        'syear',
        'student_id',
        'grade_id',
        'standard_id',
        'section_id',
        'student_quota',
        'start_date',
        'end_date',
        'enrollment_code',
        'drop_code',
        'drop_remarks',
        'term_id',
        'remarks',
        'admission_fees',
        'house_id',
        'lc_number',
        'adhar',
        'sub_institute_id',
        'created_on',
        'updated_on'
    ];
}
