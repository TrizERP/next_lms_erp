<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

class doubtConversationModel extends Model
{
    protected $table = "lms_doubt_conversation";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'doubt_id',
        'message',
        'user_id',
        'user_profile_id',
        'syear',
        'sub_institute_id',
        'created_at'
    ];
}
