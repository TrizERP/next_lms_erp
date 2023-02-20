<?php

namespace App\Models\front_desk\parentCommunication;

use Illuminate\Database\Eloquent\Model;

class parentCommunication extends Model {

    protected $table = "parent_communication";
    protected $fillable = [
        'id',
        'syear',
        'date_',
        'student_id',
        'title',
        'message',
        'reply',
        'reply_by',
        'reply_on',
        'sub_institute_id',
        'created_at',
        'updated_at'
    ];

}
