<?php

namespace App\Models\transportation\map_route_bus;

use Illuminate\Database\Eloquent\Model;

class map_route_bus extends Model {

    protected $table = "transport_route_bus";
    protected $fillable = [
        'id',
        'syear',
        'route_id',
        'bus_id',
        'sub_institute_id',
        'created_at',
        'updated_at'
    ];

}
