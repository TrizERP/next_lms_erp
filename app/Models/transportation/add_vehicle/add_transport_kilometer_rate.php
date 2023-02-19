<?php

namespace App\Models\transportation\add_vehicle;

use Illuminate\Database\Eloquent\Model;

class add_transport_kilometer_rate extends Model {

    protected $table = "transport_kilometer_rate";
    protected $fillable = [
        'id',
        'syear',
        'sub_institute_id',
        'distance_from_school',
        'from_distance',
        'to_distance',
        'rick_old',
        'rick_new',
        'van_old',
        'van_new',
        'created_on'
    ];

}
