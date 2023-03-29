<?php

namespace App\Models\easy_com\manage_sms_api;

use Illuminate\Database\Eloquent\Model;

class manage_sms_api extends Model {

    protected $table = "sms_api_details";
    protected $fillable = [
        'id',
        'url',
        'pram',
        'mobile_var',
        'text_var',
        'last_var',
        'sub_institute_id',
        'is_active',
        'created_at',
        'updated_at'
    ];

}
