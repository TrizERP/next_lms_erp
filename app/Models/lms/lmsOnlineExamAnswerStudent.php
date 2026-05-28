<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class lmsOnlineExamAnswerStudent extends Model
{
    use HasFactory;
    protected $table = "lms_online_exam_answer_student";

    protected $fillable = [
        'question_paper_id',
        'online_exam_id',
        'student_id',
        'question_id',
        'answer_id',
        'narrative_answer',
        'ans_status',
        'attempt_time',
    ];    
}
