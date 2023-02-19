<?php

namespace App\Models\visitor_management;

use Illuminate\Database\Eloquent\Model;

class visitor_typeModel extends Model
{
    protected $table = "visitor_type";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'title',
        'status',
        'sub_institute_id'
    ];
}
