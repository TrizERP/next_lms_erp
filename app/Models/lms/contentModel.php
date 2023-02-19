<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

class contentModel extends Model
{
    protected $table = "content_master";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'grade_id',
        'standard_id',
        'subject_id',
        'chapter_id',
        'topic_id',
        'sub_topic_id',
        'lo_master_ids',
        'lo_indicator_ids',
        'lo_category_id',
        'title',
        'description',
        'file_folder',
        'filename',
        'file_type',
        'file_size',
        'url',
        'sort_order',
        'show_hide',
        'meta_tags',
        'content_category',
        'syear',
        'sub_institute_id',
        'restrict_date',
        'pre_grade_topic',
        'post_grade_topic',
        'cross_curriculum_grade_topic',
        'basic_advance',
        'created_at',
        'created_by'
    ];
}
