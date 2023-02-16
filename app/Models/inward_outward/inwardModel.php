<?php

namespace App\Models\inward_outward;

use Illuminate\Database\Eloquent\Model;

class inwardModel extends Model
{
    protected $table = "inward"; 
    protected $fillable = [
        'place_id',
        'file_location_id',
        'inward_number',
        'title',
        'description',
        'acedemic_year',
        'attachment',
        'attachment_size',
        'attachment_type',
        'inward_date',
        'sub_institute_id',
        'syear'
    ];
    
    public function place(){
        return $this->belongsTo('App\Models\inward_outward\place_masterModel');
    }
    
    public function file_location(){
        return $this->belongsTo('App\Models\inward_outward\physical_file_locationModel');
    }
}
