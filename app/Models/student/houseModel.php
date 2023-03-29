<?php

namespace App\Models\student;

use Illuminate\Database\Eloquent\Model;

class houseModel extends Model
{
    protected $table = "house_master";
    protected $fillable = [
        'id',
        'sub_institute_id',
        'syear',
        'house_name',
        'created_at',
        'created_by',
        'created_ip',
        'sort_order'
    ];
    public $timestamps = false;
}
