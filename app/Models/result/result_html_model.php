<?php

namespace App\Models\result;

use Illuminate\Database\Eloquent\Model;

class result_html_model extends Model
{
    protected $table = "result_html";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'student_id',
        'grade_id',
        'standard_id',
        'division_id',
        'term_id',
        'syear',
        'html',
        'sub_institute_id',
        'is_allowed',
        'created_on'
    ];
}
