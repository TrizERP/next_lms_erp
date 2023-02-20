<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

class portfolioModel extends Model
{
    protected $table = "lms_portfolio";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'sub_institute_id',
        'syear',
        'user_profile_id',
        'title',
        'description',
        'file_name',
        'type',
        'feedback',
        'feedback_by',
        'created_at'
    ];
}
