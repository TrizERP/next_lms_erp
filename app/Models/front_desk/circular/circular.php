<?php

namespace App\Models\front_desk\circular;

use Illuminate\Database\Eloquent\Model;

class circular extends Model
{
    protected $table = "circular";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'syear',
        'standard_id',
        'division_id',
        'title',
        'message',
        'file_name',
        'date_',
        'sub_institute_id',
        'type',
        'created_at',
        'updated_at'
    ];
}
