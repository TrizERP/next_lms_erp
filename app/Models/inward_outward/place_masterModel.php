<?php

namespace App\Models\inward_outward;

use Illuminate\Database\Eloquent\Model;

class place_masterModel extends Model
{
    protected $table = "place_master";
    protected $fillable = [
        'id',
        'sub_institute_id',
        'title',
        'description',
        'created_at',
        'updated_at'
    ];
}
