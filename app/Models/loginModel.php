<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class loginModel extends Model
{
    public $timestamps = false;

    protected $table = "tbluser";

    protected $fillable = [
        'id',
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
        'otp',
        'user_profile_id',
        'join_year',
        'image',
        'plain_password',
        'sub_institute_id',
        'client_id',
        'is_admin',
        'status',
        'last_login',
        'landmark',
        'address_2',
        'created_on',
        'expire_date',
        'total_lecture',
        'subject_ids'
    ];
}
