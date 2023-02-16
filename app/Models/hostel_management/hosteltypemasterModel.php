<?php

namespace App\Models\hostel_management;

use Illuminate\Database\Eloquent\Model;

class hosteltypemasterModel extends Model
{
    protected $table = "hostel_type_master"; 
    protected $fillable = [
        'hostel_type',
        'status',
        'description',
        'sub_institute_id'
    ];
}
