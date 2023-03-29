<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class tblapplicationModel extends Model
{
    protected $table = "tblapplications";
    protected $fillable = [
        'id',
        'client_id',
        'app_secret_key',
        'created_at',
        'updated_at'
    ];
}
