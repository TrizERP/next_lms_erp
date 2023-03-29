<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

class virtualclassroomModel extends Model
{
    protected $table = "lms_virtual_classroom";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'grade_id',
        'standard_id',
        'subject_id',
        'chapter_id',
        'topic_id',
        'room_name',
        'description',
        'event_date',
        'from_time',
        'to_time',
        'recurring',
        'url',
        'password',
        'status',
        'notification',
        'sort_order',
        'syear',
        'sub_institute_id',
        'created_at',
        'created_by',
        'created_ip'
    ];
}
