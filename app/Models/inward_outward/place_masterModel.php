<?php

namespace App\Models\inward_outward;

use Illuminate\Database\Eloquent\Model;

class place_masterModel extends Model
{
    protected $table = "place_master"; 
    protected $fillable = [
        'title',
        'description',
        'sub_institute_id'
    ];
}
