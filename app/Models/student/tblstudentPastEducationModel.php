<?php

namespace App\Models\student;

use Illuminate\Database\Eloquent\Model;

class tblstudentPastEducationModel extends Model
{
    protected $table = 'tblstudent_past_education';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'student_id',
        'course',
        'medium',
        'name_of_board',
        'year_of_passing',
        'percentage',
        'school_name',
        'place',
        'trial',
        'sub_institute_id',
        'created_on'
    ];
}
