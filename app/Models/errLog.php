<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class errLog extends Model
{
    protected $table = "err_log";
    protected $fillable = [
        'id',
        'user_id',
        'code',
        'file',
        'line',
        'message',
        'screen_short',
        'created_on'
    ];
    public $timestamps = false;
}
