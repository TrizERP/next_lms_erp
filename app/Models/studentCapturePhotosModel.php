<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class studentCapturePhotosModel extends Model
{
    public $timestamps = false;

    protected $table = "student_capture_photos";

    protected $fillable = [
        'id',
        'syear',
        'sub_institute_id',
        'student_id',
        'stu_image',
        'created_on'
    ];
}
