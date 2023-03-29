<?php

namespace App\Models\implementation;

use Illuminate\Database\Eloquent\Model;

class implementation_MasterModel extends Model
{
    public $timestamps = false;

    protected $table = "implementation_master";

    protected $fillable = [
        'id',
        'sub_institute_id',
        'total_male',
        'total_female',
        'syear',
        'total_boys',
        'total_girls',
        'total_strenght',
        'standard_id',
        'std_wise_total',
        'std_wise_total_boys',
        'std_wise_total_girls',
        'final_std_total_boys',
        'final_std_total_girls',
        'final_std_total',
        'created_on'
    ];
}
