<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class standardModel extends Model
{
        public $timestamps = false;

    protected $table = "standard";
    protected $fillable = [
        'id',
        'grade_id',
        'name',
        'short_name',
        'sort_order',
        'medium',
        'sub_institute_id',
        'course_duration',
        'next_grade_id',
        'next_standard_id',
        'created_at',
        'updated_at',
        'school_stream'
    ];
}
