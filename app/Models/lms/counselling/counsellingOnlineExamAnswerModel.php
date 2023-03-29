<?php

namespace App\Models\lms\counselling;

use Illuminate\Database\Eloquent\Model;

class counsellingOnlineExamAnswerModel extends Model
{
    protected $table = "counselling_online_exam_answer";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'online_exam_id',
        'user_id',
        'question_id',
        'answer_id',
        'narrative_answer',
        'ans_status',
        'created_at'
    ];
}
