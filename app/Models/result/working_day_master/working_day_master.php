<?php

namespace App\Models\result\working_day_master;

use Illuminate\Database\Eloquent\Model;

class working_day_master extends Model {

    protected $table = "result_working_day_master";
    protected $fillable = [
        'term_id',
        'standard',
        'total_working_day',
        'sub_institute_id',
    ];

}
