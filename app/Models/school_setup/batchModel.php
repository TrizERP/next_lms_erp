<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class batchModel extends Model
{
    protected $table = "batch";
    protected $fillable = [
        'id',
        'title',
        'standard_id',
        'division_id',
        'sub_institute_id',
        'syear',
        'rollover_id',
        'created_at',
        'updated_at',
        'marking_period_id',
    ];
}
