<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class chapterModel extends Model
{
    protected $table = "chapter_master";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'syear',
        'sub_institute_id',
        'grade_id',
        'standard_id',
        'subject_id',
        'chapter_name',
        'chapter_desc',
        'availability',
        'show_hide',
        'sort_order',
        'created_at',
        'created_by'
    ];
}
