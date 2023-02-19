<?php

namespace App\Models\hostel_management;

use Illuminate\Database\Eloquent\Model;

class hostel_floor_masterModel extends Model
{
    protected $table = "hostel_floor_master";
    protected $fillable = [
        'id',
        'sub_institute_id',
        'building_id',
        'floor_name',
        'created_at',
        'updated_at'
    ];

     public function building_type(){
        return $this->belongsTo('App\Models\hostel_management\hostel_building_masterModel');
    }
}
