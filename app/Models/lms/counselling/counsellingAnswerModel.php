<?php

namespace App\Models\lms\counselling;

use Illuminate\Database\Eloquent\Model;

class counsellingAnswerModel extends Model
{
    protected $table = "counselling_answer_master";
	public $timestamps = false;
    protected $fillable = [
        'id',
        'sub_institute_id',
        'student_id',
        'title',
        'description',
        'standard_id',
        'division_id',
        'subject_id',
        'exam_id',
        'exam_pdf',
        'created_date',
        'syear',
        'submission_date',
        'submission_image',
        'student_submitted_date',
        'student_submission_status',
        'student_submitted_by',
        'teacher_remarks',
        'teacher_submission_date',
        'teacher_id',
        'teacher_submission_status',
        'created_by',
        'created_ip',
        'created_on',
        'json_annotation'
    ];
}
