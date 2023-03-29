<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class bloodgroupModel extends Model
{
    protected $table = "blood_group";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'bloodgroup'
    ];
}
