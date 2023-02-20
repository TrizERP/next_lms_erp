<?php

namespace App\Models\hostel_management;

use Illuminate\Database\Eloquent\Model;

class hostel_visitor_masterModel extends Model
{
    protected $table = "hostel_visitor_master";
    protected $fillable = [
        'id',
        'sub_institute_id',
        'name',
        'contact',
        'email',
        'coming_from',
        'to_meet',
        'relation',
        'meet_date',
        'in_time',
        'out_time',
        'created_at',
        'updated_at'
    ];
}
