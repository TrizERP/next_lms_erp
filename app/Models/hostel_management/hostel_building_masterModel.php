<?php

namespace App\Models\hostel_management;

use Illuminate\Database\Eloquent\Model;

class hostel_building_masterModel extends Model
{
    protected $table = "hostel_building_master";
    protected $fillable = [
        'id',
        'sub_institute_id',
        'hostel_type_id',
        'hostel_id',
        'building_name',
        'created_at',
        'updated_at'
    ];

     public function hostle_type(){
        return $this->belongsTo('App\Models\hostel_management\hostel_type_masterModel');
    }
}
