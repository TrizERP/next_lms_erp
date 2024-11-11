<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LmsDataContentNeo4j extends Model
{
    protected $table = 'lms_data_content_neo4j';

    protected $fillable = [
        'sub_institute_id',
        'institute_name',
        'section_id',
        'acedemic_section',
        'standard_id',
        'standard',
        'subject_id',
        'subject',
        'chapter_id',
        'chapter_order',
        'chapter',
        'source',
        'type',
        'content_category',
        'id',
        'title',
        'filepath'
    ];

    public $timestamps = false;
}
