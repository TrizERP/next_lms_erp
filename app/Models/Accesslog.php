<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Accesslog extends Model
{
    protected $table = "access_log_route";
    public $timestamps = false;        
}
