<?php

namespace App\Models\hostel_management;

use Illuminate\Database\Eloquent\Model;

class hostel_building_masterModel extends Model
{
    protected $table = "hostel_building_master"; 
    protected $fillable = [
        'building_name',
        'hostel_type_id',
        'hostel_id',
        'sub_institute_id'
    ];
    
     public function hostle_type(){
        return $this->belongsTo('App\Models\hostel_management\hostel_type_masterModel');
    }
}
