<?php

namespace App\Models\visitor_management;

use Illuminate\Database\Eloquent\Model;

class visitor_masterModel extends Model
{
    protected $table = "visitor_master";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'appointment_type',
        'visitor_type',
        'name',
        'contact',
        'email',
        'coming_from',
        'to_meet',
        'relation',
        'purpose',
        'visitor_idcard',
        'photo',
        'file_size',
        'file_type',
        'meet_date',
        'in_time',
        'out_time',
        'sub_institute_id',
        'exit_msg_sent',
        'created_at',
        'updated_at'
    ];
}
