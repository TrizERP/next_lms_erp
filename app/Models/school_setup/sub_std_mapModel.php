<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class sub_std_mapModel extends Model
{
    protected $table = "sub_std_map";
    protected $fillable = [
        'id',
        'subject_id',
        'standard_id',
        'allow_grades',
        'elective_subject',
        'display_name',
        'add_content',
        'allow_content',
        'subject_category',
        'display_image',
        'sort_order',
        'sub_institute_id',
        'status',
        'created_at',
        'updated_at'
    ];
}
