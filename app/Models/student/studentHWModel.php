<?php

namespace App\Models\student;

use Illuminate\Database\Eloquent\Model;

class studentHWModel extends Model
{
    protected $table = "student_height_weight";

    public $timestamps = false;

    protected $fillable = [
        'id',
        'student_id',
        'syear',
        'marking_period_id',
        'doctor_name',
        'doctor_contact',
        'height',
        'weight',
        'date',
        'created_on',
        'created_by',
        'sub_institute_id'
    ];
}
