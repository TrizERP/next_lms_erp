<?php

namespace App\Models\fees\fees_title;

use Illuminate\Database\Eloquent\Model;

class fees_title extends Model {

    protected $table = "fees_title";
    protected $fillable = [
        'id',
        'fees_title_id',
        'fees_title',
        'display_name',
        'sort_order',        
        'cumulative_name',
        'append_name',
        'mandatory',
        'syear',
        'sub_institute_id',
        'other_fee_id',
        'rollover_id',
        'created_at',
        'updated_at'
    ];

}
