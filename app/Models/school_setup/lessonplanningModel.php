<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class lessonplanningModel extends Model
{
    protected $table = "lessonplan"; 
    protected $fillable = [
        'syear',        
        'sub_institute_id',    
        'school_date',    
        'grade_id',    
        'standard_id',    
        'division_id',    
        'course_id',    
        'teacher_id',    
        'user_group_id',    
        'title',    
        'description'
    ];
}
