<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

class questiontypeModel extends Model
{
    protected $table = "question_type_master";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'question_type',
        'status',
        'sub_institute_id',
        'syear',
        'created_by',
        'created_on'
    ];
}
