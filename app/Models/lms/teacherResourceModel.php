<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

class teacherResourceModel extends Model
{
    protected $table = "lms_teacher_resource";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'standard_id',
        'subject_id',
        'chapter_id',
        'topic_id',
        'syear',
        'title',
        'description',
        'activity',
        'file_folder',
        'file_name',
        'file_type',
        'file_size',
        'status',
        'sub_institute_id',
        'created_by',
        'created_on'
    ];
}
