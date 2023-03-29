<?php

namespace App\Models\lms\leaderboard;

use Illuminate\Database\Eloquent\Model;

class lb_pointsModel extends Model
{
    protected $table = "lb_points";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'user_profile_id',
        'sub_institute_id',
        'syear',
        'inserted_date',
        'module_name',
        'points',
        'ip_address',
        'created_on'
    ];
}
