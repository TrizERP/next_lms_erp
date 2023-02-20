<?php

namespace App\Models\student;

use Illuminate\Database\Eloquent\Model;

class tblstateModel extends Model
{
    protected $table = "tblstate";

    public $timestamps = false;

    protected $fillable = [
        'id',
        'state_name'
    ];
}
