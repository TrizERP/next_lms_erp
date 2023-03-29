<?php

namespace App\Models\user;

use Illuminate\Database\Eloquent\Model;

class tbluserPastEducationModel extends Model
{
    protected $table = 'tbluser_past_education';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'degree',
        'medium',
        'university_name',
        'passing_year',
        'main_subject',
        'secondary_subject',
        'percentage',
        'cpi',
        'cgpa',
        'remarks',
        'sub_institute_id',
        'created_on'
    ];
}
