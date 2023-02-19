<?php

namespace App\Models\student;

use Illuminate\Database\Eloquent\Model;

class tblstudentQuotaModel extends Model
{
    protected $table = "student_quota";

    public $timestamps = false;

    protected $fillable = [
        'id',
        'title',
        'sort_order',
        'sub_institute_id',
        'created_by',
        'created_on'
    ];
}
