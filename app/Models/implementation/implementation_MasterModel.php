<?php

namespace App\Models\implementation;

use Illuminate\Database\Eloquent\Model;

class implementation_MasterModel extends Model
{
    public $timestamps = false;

    protected $table = "implementation_master";

    protected $fillable = [
        'sub_institute_id',
        'syear',
        'total_boys',
        'total_girls',
        'total_strenght',
        'standard_id',
        'std_wise_total',
        'std_wise_total_boys',
        'std_wise_total_girls'
    ];
}
