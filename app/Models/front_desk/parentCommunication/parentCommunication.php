<?php

namespace App\Models\front_desk\parentCommunication;

use Illuminate\Database\Eloquent\Model;

class parentCommunication extends Model {

    protected $table = "parent_communication";
    protected $fillable = [
        'syear',
        'student_id',
        'message',
        'date_',
        'reply',
        'sub_institute_id'
    ];

}
