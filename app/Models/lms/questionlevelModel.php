<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

class questionlevelModel extends Model
{
    protected $table = "question_level_master";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'question_level',
        'status',
        'created_at'
    ];
}
