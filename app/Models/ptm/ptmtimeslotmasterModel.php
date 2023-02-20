<?php

namespace App\Models\ptm;

use Illuminate\Database\Eloquent\Model;

class ptmtimeslotmasterModel extends Model
{
    protected $table = "ptm_time_slots_master";

	public $timestamps = false;

    protected $fillable = [
        'id',
        'syear',
        'sub_institute_id',
        'ptm_date',
        'standard_id',
        'division_id',
        'title',
        'from_time',
        'to_time',
        'created_by',
        'created_on',
        'created_ip'
    ];
}
