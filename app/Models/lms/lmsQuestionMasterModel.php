<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

class lmsQuestionMasterModel extends Model
{
    protected $table = "lms_question_master";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'question_type_id',
        'grade_id',
        'standard_id',
        'subject_id',
        'chapter_id',
        'topic_id',
        'question_title',
        'description',
        'points',
        'multiple_answer',
        'concept',
        'subconcept',
        'pre_grade_topic',
        'post_grade_topic',
        'cross_curriculum_grade_topic',
        'sub_institute_id',
        'status',
        'created_by',
        'created_on',
        'answer',
        'hint_text'
    ];

}
