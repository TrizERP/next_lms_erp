<?php

namespace App\Models\transportation\add_vehicle;

use Illuminate\Database\Eloquent\Model;

class add_vehicle extends Model {

    protected $table = "transport_vehicle";
    protected $fillable = [
        'id',
        'title',
        'vehicle_number',
        'vehicle_type',
        'sitting_capacity',
        'school_shift',
        'vehicle_identity_number',
        'driver',
        'conductor',
        'sub_institute_id',
        'created_at',
        'updated_at'
    ];

}
