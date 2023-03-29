<?php

namespace App\Models\transportation\map_route_stop;

use Illuminate\Database\Eloquent\Model;

class map_route_stop extends Model {

    protected $table = "transport_route_stop";
    protected $fillable = [
        'id',
        'syear',
        'route_id',
        'stop_id',
        'sub_institute_id',
        'created_at',
        'updated_at',
        'pickuptime',
        'droptime'
    ];

}
