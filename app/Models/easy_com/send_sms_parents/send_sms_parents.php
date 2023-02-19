<?php

namespace App\Models\easy_com\send_sms_parents;

use Illuminate\Database\Eloquent\Model;

class send_sms_parents extends Model
{
    protected $table = 'sms_sent_parents';

    protected $fillable = [
        'ID',
        'SYEAR',
        'STUDENT_ID',
        'SMS_TEXT',
        'SMS_NO',
        'MODULE_NAME',
        'CREATED_ON',
        'sub_institute_id'
    ];
}
