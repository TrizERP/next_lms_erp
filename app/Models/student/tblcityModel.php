<?php

namespace App\Models\student;

use Illuminate\Database\Eloquent\Model;

class tblcityModel extends Model
{
    protected $table = "tblcity";

    public $timestamps = false;

    protected $fillable = [
        'id',
        'city_name',
        'state_id',
        'state_name'
    ];
}
