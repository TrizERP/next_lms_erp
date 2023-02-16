<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class proxyModel extends Model
{
    protected $table = "proxy_master"; 
    protected $fillable = [
        'sub_institute_id',
        'syear',
        'timetable_id',
        'grade_id',
        'standard_id' ,
        'division_id',        
        'period_id',       
        'subject_id',       
        'teacher_id',       
        'proxy_teacher_id',       
        'week_day',
        'id',
    ];
}
