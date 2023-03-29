<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class lessonplanningModel extends Model
{
    protected $table = "lessonplan";
    protected $fillable = [
        'id',
        'syear',
        'sub_institute_id',
        'user_group_id',
        'school_date',
        'grade_id',
        'standard_id',
        'division_id',
        'subject_id',
        'teacher_id',
        'title',
        'description',
        'created_at',
        'updated_at',
        'total_marks',
        'book_link'
    ];
}
