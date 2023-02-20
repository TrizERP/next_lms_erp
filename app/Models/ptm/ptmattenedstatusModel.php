<?php

namespace App\Models\ptm;

use Illuminate\Database\Eloquent\Model;

class ptmattenedstatusModel extends Model
{
    protected $table = "ptm_booking_master";

	public $timestamps = false;

    protected $fillable = [
        'ID',
        'DATE',
        'TEACHER_ID',
        'TIME_SLOT_ID',
        'CONFIRM_STATUS',
        'CREATED_ON',
        'STUDENT_ID',
        'SUB_INSTITUTE_ID',
        'PTM_ATTENDED_STATUS',
        'PTM_ATTENDED_REMARKS',
        'PTM_ATTENDED_ENTRY_DATE',
        'PTM_ATTENDED_BY',
        'PTM_ATTENDED_CREATED_IP'
    ];
}
