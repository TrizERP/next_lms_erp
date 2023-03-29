<?php

namespace App\Models\transportation\add_stop;

use Illuminate\Database\Eloquent\Model;

class add_stop extends Model {

    protected $table = "transport_stop";
    protected $fillable = [
        'id',
        'syear',
        'stop_name',
        'sub_institute_id',
        'created_at',
        'updated_at'
    ];

}
