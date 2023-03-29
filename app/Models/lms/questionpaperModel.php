<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

class questionpaperModel extends Model
{
    protected $table = "question_paper";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'grade_id',
        'standard_id',
        'subject_id',
        'paper_name',
        'paper_desc',
        'open_date',
        'close_date',
        'timelimit_enable',
        'time_allowed',
        'total_marks',
        'total_ques',
        'question_ids',
        'shuffle_question',
        'attempt_allowed',
        'show_feedback',
        'show_hide',
        'result_show_ans',
        'created_on',
        'created_by',
        'sub_institute_id',
        'syear',
        'exam_type'
    ];
}
