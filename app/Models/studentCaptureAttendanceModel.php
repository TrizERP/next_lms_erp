<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class studentCaptureAttendanceModel extends Model
{
    public $timestamps = false;

    protected $table = "student_capture_attendance";

    protected $fillable = [
        'id',
        'syear',
        'sub_institute_id',
        'standard_id',
        'division_id',
        'date',
        'image',
        'created_on'
    ];
}
