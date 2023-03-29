<?php

namespace App\Models\student;

use Illuminate\Database\Eloquent\Model;

class appNotificationModel extends Model
{
    protected $table = "app_notification";
    // public $timestamps = false;

    protected $fillable = [
        'ID',
        'NOTIFICATION_TYPE',
        'NOTIFICATION_DATE',
        'STUDENT_ID',
        'NOTIFICATION_DESCRIPTION',
        'STATUS',
        'SUB_INSTITUTE_ID',
        'SYEAR',
        'SCREEN_NAME',
        'CREATED_AT',
        'UPDATED_AT',
        'CREATED_BY',
        'CREATED_IP'
    ];
}
