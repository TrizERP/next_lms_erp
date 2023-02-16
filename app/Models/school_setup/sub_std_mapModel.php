<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class sub_std_mapModel extends Model
{
    protected $table = "sub_std_map"; 
    protected $fillable = [
        'subject_id',
        'standard_id',
        'allow_grades',
        'elective_subject' ,
        'display_name',
        'allow_content',
        'display_image',
        'subject_category',
        'sub_institute_id',
        'status',
        'sort_order'
    ];
}
