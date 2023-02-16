<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class standardModel extends Model
{
    protected $table = "standard"; 
    protected $fillable = [
        'name',
        'short_name',
        'sort_order',
        'medium' ,
        'sub_institute_id',    
    ];
}
