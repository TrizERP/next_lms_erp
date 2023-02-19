<?php

namespace App\Models\result\std_grd_mapping;

use Illuminate\Database\Eloquent\Model;

class std_grd_maping extends Model {

    protected $table = "result_std_grd_maping";
    protected $fillable = [
        'id',
        'sub_institute_id',
        'grade_scale',
        'standard',
        'created_at',
        'updated_at'
    ];

}
