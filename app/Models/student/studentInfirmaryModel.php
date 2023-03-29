<?php

namespace App\Models\student;

use Illuminate\Database\Eloquent\Model;

class studentInfirmaryModel extends Model
{
    protected $table = 'student_infirmary';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'student_id',
        'syear',
        'marking_period_id',
        'doctor_name',
        'doctor_contact',
        'medical_case_no',
        'date',
        'complaint',
        'symptoms',
        'disease',
        'treatments',
        'medical_close_date',
        'health_center',
        'created_on',
        'created_by',
        'sub_institute_id'
    ];
}
