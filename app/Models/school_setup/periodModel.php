<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class periodModel extends Model
{
    protected $table = "period"; 
    protected $fillable = [
        'title',
        'short_name',
        'sort_order',
        'used_for_attendance' ,
        'start_time',
        'end_time',
        'length',
        'academic_section_id',
        'academic_year_id',
        'sub_institute_id',        
        'status'       
    ];
}
