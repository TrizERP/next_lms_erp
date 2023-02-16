<?php

namespace App\Models\student;

use Illuminate\Database\Eloquent\Model;

class houseModel extends Model
{
    protected $table = "house_master"; 
    protected $fillable = [
        'syear',
        'sub_institute_id',
        'house_name',
        'sort_order',
        'created_by',
        'created_at',
        'created_ip'
    ];
    public $timestamps = false;    
}
