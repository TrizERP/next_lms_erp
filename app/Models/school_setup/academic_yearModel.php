<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class academic_yearModel extends Model
{
    protected $table = "academic_year"; 
    protected $fillable = [
        'sub_institute_id',
        'title',
        'short_name',
        'sort_order'               
    ];
}
