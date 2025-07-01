<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserExperienceDetail extends Model
{
    use HasFactory;

    protected $table = 'user_experience_details';

    protected $fillable = [
        'user_id',
        'sub_institute_id',
        'institute_name',
        'designation',
        'joining_date',
        'leaving_date',
        'experience',
        'remarks'
    ];
}