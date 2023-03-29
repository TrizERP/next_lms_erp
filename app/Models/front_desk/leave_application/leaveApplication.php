<?php

namespace App\Models\front_desk\leave_application;

use Illuminate\Database\Eloquent\Model;

class leaveApplication extends Model
{
    protected $table = 'leave_applications';

    protected $fillable = [
        'id',
        'syear',
        'student_id',
        'title',
        'message',
        'files',
        'file_size',
        'file_type',
        'apply_date',
        'from_date',
        'to_date',
        'reply',
        'reply_on',
        'reply_by',
        'status',
        'sub_institute_id',
        'created_at',
        'updated_at'
    ];
}
