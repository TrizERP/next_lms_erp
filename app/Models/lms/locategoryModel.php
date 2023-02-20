<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

class locategoryModel extends Model
{
    protected $table = "lo_category";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'grade_id',
        'standard_id',
        'subject_id',
        'title',
        'availability',
        'show_hide',
        'sort_order',
        'syear',
        'sub_institute_id',
        'created_by',
        'created_at'
    ];
}
