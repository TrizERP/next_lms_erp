<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class attendanceJsonResultModel extends Model
{
    public $timestamps = false;

    protected $table = "attendance_json_result";

    protected $fillable = [
        'id',
        'syear',
        'sub_institute_id',
        'json_data',
        'created_on'
    ];
}
