<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

class lmsOnlineExamAnswerModel extends Model
{
    protected $table = "lms_online_exam_answer";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'question_paper_id',
        'online_exam_id',
        'student_id',
        'question_id',
        'answer_id',
        'narrative_answer',
        'ans_status',
        'created_at'
    ];
}
