<?php

namespace App\Models\result\result_book_master;

use Illuminate\Database\Eloquent\Model;

class result_trust_master extends Model
{
      protected $table = "result_trust_master";
    protected $fillable = [
        'id',
        'syear',
        'line1',
        'line2',
        'line3',
        'line4',
        'left_logo',
        'right_logo',
        'status',
        'sub_institute_id',
        'created_at',
        'updated_at'
    ];
}
