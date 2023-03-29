<?php

namespace App\Models\lms\counselling;

use Illuminate\Database\Eloquent\Model;

class counsellingCourseModel extends Model
{
    protected $table = "counselling_course";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'title',
        'description',
        'image',
        'sort_order',
        'sub_institute_id',
        'status',
        'created_at'
    ];
}
