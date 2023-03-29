<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class classteacherModel extends Model
{
    protected $table = "class_teacher";
    protected $fillable = [
        'id',
        'syear',
        'sub_institute_id',
        'grade_id',
        'standard_id',
        'division_id',
        'teacher_id',
        'created_at',
        'updated_at'
    ];
}
