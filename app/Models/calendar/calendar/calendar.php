<?php

namespace App\Models\calendar\calendar;

use Illuminate\Database\Eloquent\Model;

class calendar extends Model {

    protected $table = "calendar_events";
    protected $fillable = [
        'syear',
        'school_date',
        'title',
        'description',
        'event_type',
        'standard',
        'sub_institute_id',
    ];

}
