<?php

namespace App\Models\student;

use Illuminate\Database\Eloquent\Model;

class studentRequestModel extends Model
{
    protected $table = "student_change_request";

	public $timestamps = false;

    protected $fillable = [
        'ID',
        'SYEAR',
        'SUB_INSTITUTE_ID',
        'CHANGE_REQUEST_ID',
        'STUDENT_ID',
        'REASON',
        'DESCRIPTION',
        'PROOF_OF_DOCUMENT',
        'CREATED_BY',
        'CREATED_ON',
        'STANDARD_ID',
        'SECTION_ID'
    ];
}
