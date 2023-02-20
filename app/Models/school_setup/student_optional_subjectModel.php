<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class student_optional_subjectModel extends Model
{
    protected $table = "student_optional_subject";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'syear',
        'sub_institute_id',
        'subject_id',
        'student_id'
    ];
}
