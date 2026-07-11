<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

class gammaModel extends Model
{
    protected $table = "gamma_ppt";
    public $timestamps = false;

    protected $fillable = [
        'id',
        'standard_id',
        'subject_id',
        'chapter_id',
        'topic_id',
        'title',
        'description',
        'prompt',
        'format',
        'export_format',
        'generation_id',
        'gamma_url',
        'file_url',
        'file_type',
        'status',
        'credits_used',
        'credits_remaining',
        'sub_institute_id',
        'syear',
        'created_by',
        'created_at'
    ];
}
