<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class divisionModel extends Model
{
    protected $table = "division"; 
    protected $fillable = [
        'name',        
        'sub_institute_id',    
    ];
}
