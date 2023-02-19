<?php

namespace App\Models\consent;

use Illuminate\Database\Eloquent\Model;

class consent_masterModel extends Model
{
    protected $table = "consent_master";

	public $timestamps = false;

    protected $fillable = [
        'ID',
        'student_id',
        'syear',
        'standard_id',
        'sub_institute_id',
        'division_id',
        'title',
        'date',
        'accountable_status',
        'amount',
        'imprest_head_id',
        'status',
        'created_on',
        'created_by',
        'created_ip'
    ];
}
