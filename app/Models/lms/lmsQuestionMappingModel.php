<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

class lmsQuestionMappingModel extends Model
{
    protected $table = "lms_question_mapping";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'questionmaster_id',
        'mapping_type_id',
        'mapping_value_id'
    ];
}
