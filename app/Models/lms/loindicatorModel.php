<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

class loindicatorModel extends Model
{
    protected $table = "lo_indicator";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'grade_id',
        'standard_id',
        'subject_id',
        'chapter_id',
        'lomaster_id',
        'indicator',
        'availability',
        'show_hide',
        'sort_order',
        'syear',
        'sub_institute_id',
        'created_by',
        'created_at'
    ];
}
