<?php

namespace App\Models\lms\leaderboard;

use Illuminate\Database\Eloquent\Model;

class lb_masterModel extends Model
{
    protected $table = "lb_master";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'grade_id',
        'standard_id',
        'module_name',
        'per_value',
        'points',
        'icon',
        'description',
        'status',
        'sub_institute_id',
        'created_on'
    ];
}
