<?php

namespace App\Models\transportation\add_vehicle;

use Illuminate\Database\Eloquent\Model;

class add_vehicle_type extends Model {

    protected $table = "transport_vehicle_type";
    protected $fillable = [
        'id',
        'name',
        'created_on'
    ];

}
