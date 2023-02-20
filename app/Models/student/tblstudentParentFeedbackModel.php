<?php

namespace App\Models\student;

use Illuminate\Database\Eloquent\Model;

class tblstudentParentFeedbackModel extends Model
{
    protected $table = 'tblstudent_parent_feedback';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'student_id',
        'person_name',
        'purpose',
        'response',
        'comments',
        'date',
        'sub_institute_id',
        'created_by',
        'created_on'
    ];
}
