<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

class questionmasterModel extends Model
{
    protected $table = "question_master";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'question_type_id',
        'grade_id',
        'standard_id',
        'subject_id',
        'chapter_id',
        'question_title',
        'points',
        'multiple_answer',
        'sub_institute_id',
        'lo_master_ids',
        'lo_indicator_ids',
        'lo_category_id',
        'question_level_id',
        'question_category_id',
        'status',
        'created_by',
        'created_on'
    ];
}
