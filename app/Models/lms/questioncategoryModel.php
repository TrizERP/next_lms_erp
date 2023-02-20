<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

class questioncategoryModel extends Model
{
    protected $table = "question_category_master";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'question_category',
        'description',
        'status',
        'created_at'
    ];
}
