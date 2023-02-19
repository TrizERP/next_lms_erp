<?php

namespace App\Models\transportation\map_student;

use Illuminate\Database\Eloquent\Model;

class map_student extends Model {

    protected $table = "transport_map_student";
    protected $fillable = [
        'id',
        'syear',
        'student_id',
        'from_shift_id',
        'from_bus_id',
        'from_stop',
        'to_shift_id',
        'to_bus_id',
        'to_stop',
        'sub_institute_id',
        'created_at',
        'updated_at'
    ];

}
