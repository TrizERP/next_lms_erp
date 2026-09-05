<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class lmsQuestionMasterModel extends Model
{
    use SoftDeletes;

    protected $table = "lms_question_master";
	public $timestamps = false;

    // created_on/updated_on are managed by hand ($timestamps = false); only the
    // soft-delete column is cast so deletes stamp the current date and time.
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'id',
        'question_type_id',
        'grade_id',
        'standard_id',
        'subject_id',
        'chapter_id',
        'concept_id',
        'topic_id',
        'question_title',
        'description',
        'points',
        'multiple_answer',
        'concept',
        'subconcept',
        // PAL learning-flow category the Question Bank dropdown filters on.
        'category',
        'pre_grade_topic',
        'post_grade_topic',
        'cross_curriculum_grade_topic',
        'sub_institute_id',
        'status',
        'created_by',
        'created_on',
        'answer',
        'hint_text',
        'learning_outcome',
        'deleted_at'
    ];

}
