<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class tourModel extends Model {
	public $timestamps = false;

	protected $table = "erptour";

    protected $fillable = [
        'id',
        'dashboard',
        'school_sidebar',
        'student_quota',
        'fees_title',
        'fees_structure',
        'fees_receipt',
        'fees_map',
        'fees_collect',
        'user_id',
        'sub_institute_id'
    ];
}
