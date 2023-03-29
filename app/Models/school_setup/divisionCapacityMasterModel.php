<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class divisionCapacityMasterModel extends Model
{
    protected $table = "division_capacity_master";
    protected $fillable = [
        'id',
        'syear',
        'sub_institute_id',
        'grade_id',
        'standard_id',
        'division_id',
        'capacity',
        'created_on',
        'created_by',
        'created_ip',
        'updated_by',
        'updated_on'
    ];
	public $timestamps = false;
}
