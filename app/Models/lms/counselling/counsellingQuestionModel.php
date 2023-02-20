<?php

namespace App\Models\lms\counselling;

use Illuminate\Database\Eloquent\Model;

class counsellingQuestionModel extends Model
{
    protected $table = "counselling_question_master";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'counselling_course_id',
        'question_type_id',
        'question_title',
        'description',
        'points',
        'multiple_answer',
        'sub_institute_id',
        'status',
        'created_by',
        'created_on'
    ];
}
