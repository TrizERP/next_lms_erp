<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

class doubtModel extends Model
{
    protected $table = "lms_doubt";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'subject_id',
        'chapter_id',
        'topic_id',
        'title',
        'description',
        'file_name',
        'visibility',
        'sub_institute_id',
        'syear',
        'user_id',
        'user_profile_id',
        'created_at'
    ];
}
