<?php

namespace App\Models\result\student_attendance_master;

use Illuminate\Database\Eloquent\Model;

class student_attendance_master extends Model {

    protected $table = "result_student_attendance_master";
    protected $fillable = [
        'id',
        'term_id',
        'standard',
        'sub_institute_id',
        'syear',
        'student_id',
        'attendance',
        'percentage',
        'remark_id',
        'teacher_remark',
        'created_at',
        'updated_at'
    ];

}
