<?php

namespace App\Models\fees\fees_breackoff;

use Illuminate\Database\Eloquent\Model;

class fees_breackoff extends Model {

    protected $table = "fees_breackoff";
    protected $fillable = [
        'syear',
        'fee_type_id',
        'grade_id',
        'standard_id',
        'section_id',
        'month_id',
        'amount',
        'sub_institute_id',
        'created_at',
        'updated_at',
    ];

}
