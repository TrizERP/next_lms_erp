<?php

namespace App\Models\inward_outward;

use Illuminate\Database\Eloquent\Model;

class outwardModel extends Model
{
    protected $table = "outward";
    protected $fillable = [
        'id',
        'sub_institute_id',
        'syear',
        'place_id',
        'file_location_id',
        'outward_number',
        'title',
        'description',
        'attachment',
        'attachment_size',
        'attachment_type',
        'acedemic_year',
        'outward_date',
        'created_at',
        'updated_at'
    ];

    public function place(){
        return $this->belongsTo('App\Models\inward_outward\place_masterModel');
    }

    public function file_location(){
        return $this->belongsTo('App\Models\inward_outward\physical_file_locationModel');
    }
}
