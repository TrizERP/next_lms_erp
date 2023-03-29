<?php

namespace App\Models\result\GradeMaster;

use Illuminate\Database\Eloquent\Model;

class GradeMasterData extends Model {

    //
    protected $table = "grade_master_data";
    protected $fillable = [
        'id',
        'syear',
        'grade_id',
        'title',
        'breakoff',
        'gp',
        'sort_order',
        'comment',
        'sub_institute_id',
        'created_at',
        'updated_at'
    ];

}
