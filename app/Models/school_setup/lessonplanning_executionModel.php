<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class lessonplanning_executionModel extends Model
{
    protected $table = "lessonplan_execution";
    protected $fillable = [
        'id',
        'syear',
        'sub_institute_id',
        'user_group_id',
        'school_date',
        'standard_id',
        'division_id',
        'subject_id',
        'teacher_id',
        'lessonplan_id',
        'lessonplan_status',
        'lessonplan_reason',
        'created_at',
        'updated_at'
    ];
}
