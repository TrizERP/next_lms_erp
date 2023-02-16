<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class std_div_mappingModel extends Model
{
    protected $table = "std_div_map"; 
    protected $fillable = [
        'standard_id',
        'division_id'
    ];
}
