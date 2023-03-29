<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class temp_signupModel extends Model
{
    protected $table = "temp_signup";
    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_type',
        'first_name',
        'last_name',
        'gender',
        'birthdate',
        'email',
        'mobile',
        'otp',
        'institute_name',
        'institute_image',
        'syear',
        'standard_id',
        'ip_address',
        'created_on'
    ];
}
