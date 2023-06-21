<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class normClature extends Model
{
    use HasFactory;
    public $table = "app_language";
    public $fillable=[
        'menu_id',
        'menu',
        'stirng',
        'value',
        'status',
        'sub_institute_id',
        'created_by',
        'updated_at'
    ];
}
