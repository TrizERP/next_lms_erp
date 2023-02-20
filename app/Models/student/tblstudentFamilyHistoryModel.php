<?php

namespace App\Models\student;

use Illuminate\Database\Eloquent\Model;

class tblstudentFamilyHistoryModel extends Model
{
    protected $table = 'tblstudent_family_history';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'student_id',
        'name',
        'institute_name',
        'course',
        'year',
        'percentage',
        'relation_with_student',
        'sub_institute_id',
        'created_on'
    ];
}
