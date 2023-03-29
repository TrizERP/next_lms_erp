<?php

namespace App\Models\result\upload_result;

use Illuminate\Database\Eloquent\Model;

class upload_result_model extends Model
{
    protected $table = "upload_result";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'syear',
        'sub_institute_id',
        'term_id',
        'grade_id',
        'standard_id',
        'student_id',
        'file_name',
        'created_on',
        'created_by',
        'created_ip'
    ];
}
