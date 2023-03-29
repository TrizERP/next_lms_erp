<?php

namespace App\Models\lms\counselling;

use Illuminate\Database\Eloquent\Model;

class counsellingOnlineExamModel extends Model
{
    protected $table = "counselling_online_exam";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'sub_institute_id',
        'course_id',
        'total_right',
        'total_wrong',
        'obtain_marks',
        'created_at'
    ];
}
