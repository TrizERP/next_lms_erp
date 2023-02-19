<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

class lmsOfflineExamAnswerModel extends Model
{
    protected $table = "lms_offline_exam_answer";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'question_paper_id',
        'offline_exam_id',
        'student_id',
        'question_id',
        'ans_status',
        'created_at',
        'created_by'
    ];
}
