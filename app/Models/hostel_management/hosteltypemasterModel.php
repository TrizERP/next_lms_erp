<?php

namespace App\Models\hostel_management;

use Illuminate\Database\Eloquent\Model;

class hosteltypemasterModel extends Model
{
    protected $table = "hostel_type_master";
    protected $fillable = [
        'id',
        'sub_institute_id',
        'hostel_type',
        'status',
        'description',
        'created_at',
        'updated_at'
    ];
}
