<?php

namespace App\Models\user;

use Illuminate\Database\Eloquent\Model;

class tbluserModel extends Model
{
    public $timestamps = false;

    protected $table = "tbluser";

    protected $fillable = [
        'user_name',
        'password',
        'name_suffix',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'mobile',
        'gender',
        'birthdate',
        'address',
        'city',
        'state',
        'pincode',
        'user_profile_id',
        'join_year',
        'image',
        'plain_password',
        'sub_institute_id',
        'status'
    ];
}
