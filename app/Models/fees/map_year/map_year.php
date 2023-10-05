<?php

namespace App\Models\fees\map_year;

use Illuminate\Database\Eloquent\Model;

class map_year extends Model {

    protected $table = "fees_map_years";
    protected $fillable = [
        'id',
        'from_month',
        'to_month',
        'syear',
        'sub_institute_id',
        'type',
        'created_at',
        'updated_at'
    ];

}
