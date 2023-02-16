<?php

namespace App\Models\transportation\add_driver;

use Illuminate\Database\Eloquent\Model;

class add_driver extends Model {

    protected $table = "transport_driver_detail";
    protected $fillable = [
        'first_name',
        'last_name',
        'mobile',
        'icard_icon',
        'type',
        'sub_institute_id'
    ];

}
