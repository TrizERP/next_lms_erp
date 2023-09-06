<?php

namespace App\Models\result\working_day_master;

use Illuminate\Database\Eloquent\Model;

class working_day_master extends Model {

    protected $table = "result_working_day_master";
    protected $fillable = [
        'id',
        'term_id',
        'syear',
        'sub_institute_id',
        'standard',
        'total_working_day',
        'created_at',
        'updated_at'
    ];

}
