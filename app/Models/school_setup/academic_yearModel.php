<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class academic_yearModel extends Model
{
    protected $table = "academic_year";
    protected $fillable = [
        'id',
        'term_id',
        'syear',
        'sub_institute_id',
        'title',
        'short_name',
        'sort_order',
        'start_date',
        'end_date',
        'post_start_date',
        'post_end_date',
        'does_grades',
        'does_exams',
        'created_at',
        'updated_at'
    ];
}
