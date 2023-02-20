<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class subjectModel extends Model
{
    protected $table = "subject";
    protected $fillable = [
        'id',
        'subject_name',
        'subject_code',
        'subject_type',
        'short_name',
        'sub_institute_id',
        'status',
        'created_at',
        'updated_at'
    ];
}
