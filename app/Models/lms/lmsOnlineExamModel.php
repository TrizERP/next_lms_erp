<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

class lmsOnlineExamModel extends Model
{
    protected $table = "lms_online_exam";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'student_id',
        'question_paper_id',
        'total_right',
        'total_wrong',
        'obtain_marks',
        'start_time',
        'created_at'
    ];
}
