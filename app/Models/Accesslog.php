<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Accesslog extends Model
{
    protected $table = "access_log_route";
    public $timestamps = false;

    protected $fillable = [
        'id',
        'url',
        'module',
        'action',
        'sub_institute_id',
        'user_id',
        'profile_id',
        'ip_address',
        'created_at'
    ];
}
