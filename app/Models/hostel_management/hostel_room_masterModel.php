<?php

namespace App\Models\hostel_management;

use Illuminate\Database\Eloquent\Model;

class hostel_room_masterModel extends Model
{
   protected $table = "hostel_room_master"; 
    protected $fillable = [
        'room_name',
        'floor_id',
        'sub_institute_id'
    ];
    
     public function floor(){
        return $this->belongsTo('App\Models\hostel_management\hostel_floor_masterModel');
    }
}
