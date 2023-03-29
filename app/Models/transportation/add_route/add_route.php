<?php

namespace App\Models\transportation\add_route;

use Illuminate\Database\Eloquent\Model;

class add_route extends Model {

    protected $table = "transport_route";
    protected $fillable = [
        'id',
        'syear',
        'route_name',
        'from_time',
        'to_time',
        'sub_institute_id',
        'created_at',
        'updated_at'
    ];

}
