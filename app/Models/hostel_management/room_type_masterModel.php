<?php

namespace App\Models\hostel_management;

use Illuminate\Database\Eloquent\Model;

class room_type_masterModel extends Model
{
    protected $table = "room_type_master"; 
    protected $fillable = [
        'room_type',
        'status',
        'sub_institute_id'
    ];
}
