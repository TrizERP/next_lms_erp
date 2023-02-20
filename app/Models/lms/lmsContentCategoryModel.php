<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

class lmsContentCategoryModel extends Model
{
    protected $table = "lms_content_category";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'category_name',
        'sub_institute_id',
        'status'
    ];
}
