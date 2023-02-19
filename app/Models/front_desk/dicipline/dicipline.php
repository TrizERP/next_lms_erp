<?php

namespace App\Models\front_desk\dicipline;

use Illuminate\Database\Eloquent\Model;

class dicipline extends Model
{
    protected $table = 'dicipline';

    protected $fillable = [
        'id',
        'syear',
        'student_id',
        'name',
        'dicipline',
        'message',
        'date_',
        'sub_institute_id',
        'created_by',
        'created_at',
        'updated_at'
    ];
}
