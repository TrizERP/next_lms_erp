<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class timetableModel extends Model
{
    protected $table = "timetable"; 
    protected $fillable = [
        'sub_institute_id',
        'syear',
        'academic_section_id',
        'standard_id' ,
        'division_id',
        'batch_id',       
        'period_id',       
        'subject_id',       
        'teacher_id',       
        'week_day',       
    ];
}
