<?php

namespace App\Models\result\result_remark_mater;

use Illuminate\Database\Eloquent\Model;

class result_remark_master extends Model
{
    protected $table = "result_remark_masters";
    protected $fillable = [
        'id',
        'syear',
        'sub_institute_id',
        'marking_period_id',
        'title',
        'remark_status',
        'sort_order',
        'created_at',
        'updated_at'
    ];
}
