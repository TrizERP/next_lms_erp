<?php

namespace App\Models\hostel_management;

use Illuminate\Database\Eloquent\Model;

class hostel_room_masterModel extends Model
{
   protected $table = "hostel_room_master";
    protected $fillable = [
        'id',
        'sub_institute_id',
        'floor_id',
        'room_name',
        'created_at',
        'updated_at'
    ];

     public function floor(){
        return $this->belongsTo('App\Models\hostel_management\hostel_floor_masterModel');
    }
}
