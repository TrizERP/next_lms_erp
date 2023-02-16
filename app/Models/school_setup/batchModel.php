<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class batchModel extends Model
{
    protected $table = "batch"; 
    protected $fillable = [
        'title',
        'standard_id',
        'division_id',
        'sub_institute_id'              
    ];
}
