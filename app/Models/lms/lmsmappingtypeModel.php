<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

class lmsmappingtypeModel extends Model
{
    protected $table = "lms_mapping_type";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'parent_id',
        'globally',
        'chapter_id',
        'topic_id',
        'status',
        'created_at'
    ];
}
