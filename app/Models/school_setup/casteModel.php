<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class casteModel extends Model
{
    protected $table = "caste";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'bloodgroup'
    ];
}
