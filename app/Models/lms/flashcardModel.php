<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

class flashcardModel extends Model
{
    protected $table = "lms_flashcard";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'standard_id',
        'subject_id',
        'chapter_id',
        'topic_id',
        'content_id',
        'title',
        'front_text',
        'back_text',
        'status',
        'sub_institute_id',
        'syear',
        'created_on',
        'created_by'
    ];
}
