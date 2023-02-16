<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class classteacherModel extends Model
{
    protected $table = "class_teacher"; 
    protected $fillable = [
        'syear',
        'grade_id',
        'standard_id',
        'division_id',
        'sub_institute_id',             
        'teacher_id'              
    ];
}
