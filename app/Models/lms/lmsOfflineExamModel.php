<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

class lmsOfflineExamModel extends Model
{
    protected $table = "lms_offline_exam";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'student_id',
        'question_paper_id',
        'assignment_id',
        'total_right',
        'total_wrong',
        'obtain_marks',
        'syear',
        'sub_institute_id',
        'created_by',
        'created_at'
    ];
}
