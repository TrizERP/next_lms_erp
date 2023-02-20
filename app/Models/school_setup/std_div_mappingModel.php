<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class std_div_mappingModel extends Model
{
    protected $table = "std_div_map";
    protected $fillable = [
        'id',
        'standard_id',
        'division_id',
        'sub_institute_id',
        'created_at',
        'updated_at'
    ];
}
