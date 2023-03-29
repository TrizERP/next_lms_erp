<?php

namespace App\Models\inward_outward;

use Illuminate\Database\Eloquent\Model;

class physical_file_locationModel extends Model
{
   protected $table = "physical_file_location";
    protected $fillable = [
        'id',
        'sub_institute_id',
        'title',
        'description',
        'file_code',
        'file_location',
        'created_at',
        'updated_at'
    ];
}
