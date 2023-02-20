<?php

namespace App\Models\lms\counselling;

use Illuminate\Database\Eloquent\Model;

class counsellingQuestionMappingModel extends Model
{
    protected $table = "counselling_question_mapping";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'questionmaster_id',
        'mapping_type_id',
        'mapping_value_id'
    ];
}
