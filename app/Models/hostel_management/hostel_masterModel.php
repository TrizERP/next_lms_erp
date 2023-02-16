<?php

namespace App\Models\hostel_management;

use Illuminate\Database\Eloquent\Model;

class hostel_masterModel extends Model
{
    protected $table = "hostel_master"; 
    protected $fillable = [
        'code',
        'name',
        'description',
        'warden',
        'warden_contact',
        'hostel_type_id',
        'sub_institute_id'
    ];
    
     public function hostle_type(){
        return $this->belongsTo('App\Models\hostel_management\hostel_type_masterModel');
    }
}
