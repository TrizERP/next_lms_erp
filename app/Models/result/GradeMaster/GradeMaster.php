<?php

namespace App\Models\result\GradeMaster;

use Illuminate\Database\Eloquent\Model;

class GradeMaster extends Model {

    //
    protected $table = "grade_master";
    protected $fillable = [
        'grade_name',
        'sub_institute_id',
        'sort_order',
    ];

}
