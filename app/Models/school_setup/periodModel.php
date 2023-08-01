<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class periodModel extends Model
{
    protected $table = "period";
    protected $fillable = [
        'id',
        'title',
        'short_name',
        'sort_order',
        'used_for_attendance',
        'start_time',
        'end_time',
        'length',
        'academic_section_id',
        'academic_year_id',
        'status',
        'sub_institute_id',
        'created_at',
        'updated_at',
        'marking_period_id',
    ];
}
