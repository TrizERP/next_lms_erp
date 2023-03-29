<?php

namespace App\Models\student;

use Illuminate\Database\Eloquent\Model;

class studentHealthModel extends Model
{
    protected $table = "student_health";

    public $timestamps = false;

    protected $fillable = [
        'id',
        'student_id',
        'syear',
        'marking_period_id',
        'doctor_name',
        'doctor_contact',
        'date',
        'file',
        'file_size',
        'file_type',
        'created_on',
        'created_by',
        'sub_institute_id'
    ];
}
