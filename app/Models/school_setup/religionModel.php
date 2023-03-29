<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class religionModel extends Model
{
    protected $table = "religion";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'religion_name'
    ];
}
