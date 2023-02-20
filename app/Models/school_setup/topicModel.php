<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class topicModel extends Model
{
    protected $table = "topic_master";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'sub_institute_id',
        'chapter_id',
        'main_topic_id',
        'name',
        'description',
        'topic_show_hide',
        'topic_sort_order',
        'syear',
        'created_at',
        'created_by'
    ];
}
