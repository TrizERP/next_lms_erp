<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

class answermasterModel extends Model
{
    protected $table = "answer_master";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'question_id',
        'answer',
        'feedback',
        'correct_answer',
        'sub_institute_id',
        'created_by',
        'created_on'    ];
}
